<?php

declare(strict_types=1);

namespace CurserPos\Service;

use CurserPos\Domain\Consignor\ConsignorRepository;
use CurserPos\Domain\Payout\PayoutRepository;

final class PayoutService
{
    public function __construct(
        private readonly ConsignorRepository $consignorRepository,
        private readonly ConsignorService $consignorService,
        private readonly PayoutRepository $payoutRepository,
        private readonly BoothRentalService $boothRentalService
    ) {
    }

    /**
     * Run payouts for consignors with balance >= minimum. Deducts booth rent first when applicable, then pays remainder.
     *
     * @param list<string> $consignorIds Optional; if empty, all consignors with balance >= minimum
     * @return list<array{payout_id: string, consignor_id: string, amount: float, rent_deducted: float, method: string}>
     */
    public function runPayoutRun(array $consignorIds, float $minimumAmount, string $method = 'check'): array
    {
        $method = in_array($method, ['check', 'cash', 'store_credit', 'ach'], true) ? $method : 'check';
        $results = [];

        $consignors = $consignorIds !== []
            ? array_filter(array_map(fn ($id) => $this->consignorRepository->findById($id), $consignorIds))
            : $this->consignorRepository->findAll('active');

        foreach ($consignors as $consignor) {
            $balance = $this->consignorService->getBalance($consignor->id);
            $rentDue = $this->boothRentalService->getRentDue($consignor->id);
            $rentAmount = $rentDue !== null ? $rentDue['amount'] : 0.0;
            $payoutAmount = round($balance->balance - $rentAmount, 2);

            if ($payoutAmount < $minimumAmount) {
                continue;
            }

            if ($rentAmount > 0 && $rentDue !== null) {
                $payoutId = $this->payoutRepository->create($consignor->id, $payoutAmount, $method);
                $this->boothRentalService->recordDeduction(
                    $consignor->id,
                    $rentAmount,
                    $rentDue['period_start'],
                    $rentDue['period_end'],
                    $payoutId
                );
                $this->consignorService->deductForPayoutAndRent($consignor->id, $payoutAmount, $rentAmount);
                $this->payoutRepository->markProcessed($payoutId);
                $results[] = [
                    'payout_id' => $payoutId,
                    'consignor_id' => $consignor->id,
                    'amount' => $payoutAmount,
                    'rent_deducted' => $rentAmount,
                    'method' => $method,
                ];
            } else {
                $amount = round($balance->balance, 2);
                $payoutId = $this->payoutRepository->create($consignor->id, $amount, $method);
                $this->consignorService->deductForPayout($consignor->id, $amount);
                $this->payoutRepository->markProcessed($payoutId);
                $results[] = [
                    'payout_id' => $payoutId,
                    'consignor_id' => $consignor->id,
                    'amount' => $amount,
                    'rent_deducted' => 0.0,
                    'method' => $method,
                ];
            }
        }

        return $results;
    }

    /**
     * Preview which consignors would be paid in a payout run without creating payouts.
     *
     * @return list<array{consignor_id: string, name: string, balance: float, rent_due: float, payout_amount: float}>
     */
    public function previewPayoutRun(float $minimumAmount): array
    {
        $consignors = $this->consignorRepository->findAll('active');
        $results = [];

        foreach ($consignors as $consignor) {
            $balance = $this->consignorService->getBalance($consignor->id);
            $rentDue = $this->boothRentalService->getRentDue($consignor->id);
            $rentAmount = $rentDue !== null ? $rentDue['amount'] : 0.0;
            $payoutAmount = round($balance->balance - $rentAmount, 2);

            if ($payoutAmount < $minimumAmount) {
                continue;
            }

            $results[] = [
                'consignor_id' => $consignor->id,
                'name' => $consignor->name,
                'balance' => $balance->balance,
                'rent_due' => $rentAmount,
                'payout_amount' => $payoutAmount,
            ];
        }

        return $results;
    }
}
