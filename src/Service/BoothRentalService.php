<?php

declare(strict_types=1);

namespace CurserPos\Service;

use CurserPos\Domain\Booth\ConsignorBoothAssignmentRepository;
use CurserPos\Domain\Booth\RentDeductionRepository;

final class BoothRentalService
{
    public function __construct(
        private readonly ConsignorBoothAssignmentRepository $assignmentRepository,
        private readonly RentDeductionRepository $rentDeductionRepository,
        private readonly \CurserPos\Domain\Booth\BoothRepository $boothRepository
    ) {
    }

    /**
     * Assign consignor to booth; ends any current assignment first. monthly_rent overrides booth default when provided.
     */
    public function assignToBooth(string $consignorId, string $boothId, ?float $monthlyRentOverride = null, ?\DateTimeImmutable $startedAt = null): string
    {
        $booth = $this->boothRepository->findById($boothId);
        if ($booth === null) {
            throw new \InvalidArgumentException('Booth not found');
        }
        $this->assignmentRepository->endAssignment($consignorId, $startedAt ?? new \DateTimeImmutable());
        $rent = $monthlyRentOverride ?? $booth->monthlyRent;
        $start = $startedAt ?? new \DateTimeImmutable();
        return $this->assignmentRepository->assign($consignorId, $boothId, $rent, $start);
    }

    public function endAssignment(string $consignorId, ?\DateTimeImmutable $endedAt = null): void
    {
        $end = $endedAt ?? new \DateTimeImmutable();
        $this->assignmentRepository->endAssignment($consignorId, $end);
    }

    /**
     * Compute rent due from last deduction (or assignment start) through end of the given date.
     * Returns [amount, period_start, period_end] or null if no assignment or no rent due.
     *
     * @return array{amount: float, period_start: \DateTimeImmutable, period_end: \DateTimeImmutable}|null
     */
    public function getRentDue(string $consignorId, ?\DateTimeImmutable $throughDate = null): ?array
    {
        $assignment = $this->assignmentRepository->getActiveByConsignorId($consignorId);
        if ($assignment === null) {
            return null;
        }

        $through = $throughDate ?? new \DateTimeImmutable();
        $periodStart = $this->rentDeductionRepository->getLastDeductionDate($consignorId);
        if ($periodStart !== null) {
            $periodStart = $periodStart->modify('+1 day');
        } else {
            $periodStart = $assignment->startedAt;
        }

        if ($periodStart > $through) {
            return null;
        }

        $months = $this->countFullMonths($periodStart, $through);
        if ($months < 1) {
            return null;
        }

        $amount = round($assignment->monthlyRent * $months, 2);
        if ($amount <= 0) {
            return null;
        }

        $periodEnd = $periodStart->modify('+' . $months . ' months')->modify('-1 day');
        if ($periodEnd > $through) {
            $periodEnd = $through;
        }

        return [
            'amount' => $amount,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
        ];
    }

    /**
     * Record a rent deduction (e.g. when deducted from payout) and optionally link to payout.
     */
    public function recordDeduction(string $consignorId, float $amount, \DateTimeImmutable $periodStart, \DateTimeImmutable $periodEnd, ?string $payoutId = null): string
    {
        return $this->rentDeductionRepository->record($consignorId, $amount, $periodStart, $periodEnd, $payoutId);
    }

    /**
     * Deduct rent from consignor balance (when rent is taken from payout we already deduct payout amount;
     * we need to also reduce balance by the rent portion so the net effect is balance -= payout + rent, then we add back payout as paid.
     * Actually: current flow is balance has sales. Payout = balance. We want payout_after_rent = balance - rent_due, then deduct balance by payout_after_rent and record rent_deduction.
     * So we don't "deduct rent from balance" separately - we just reduce the payout amount. So recordDeduction is enough. The ConsignorService.deductForPayout will be called with (payout_amount) which is already (balance - rent). So we're good.
     */
    private function countFullMonths(\DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        if ($from > $to) {
            return 0;
        }
        $diff = $from->diff($to);
        return $diff->y * 12 + $diff->m + 1;
    }
}
