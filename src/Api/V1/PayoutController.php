<?php

declare(strict_types=1);

namespace CurserPos\Api\V1;

use CurserPos\Domain\Consignor\ConsignorRepository;
use CurserPos\Domain\Payout\PayoutRepository;
use CurserPos\Service\PayoutService;
use PerfectApp\Routing\Route;

final class PayoutController
{
    public function __construct(
        private readonly PayoutService $payoutService,
        private readonly ConsignorRepository $consignorRepository,
        private readonly PayoutRepository $payoutRepository,
        private readonly \CurserPos\Service\AuditService $auditService
    ) {
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/payouts/run', ['POST'])]
    public function run(string $slug): void
    {
        $context = \CurserPos\Http\RequestContextHolder::get();
        $input = $this->getJsonInput();
        $consignorIds = $input['consignor_ids'] ?? [];
        $consignorIds = is_array($consignorIds) ? array_values(array_filter($consignorIds, 'is_string')) : [];
        $minimumAmount = (float) ($input['minimum_amount'] ?? 0);
        $method = (string) ($input['method'] ?? 'check');
        $rentCycleDay = (int) ($context?->tenant?->settings['booth_rent_cycle_day'] ?? 1);
        $rentCycleDay = max(1, min(31, $rentCycleDay));
        $deductRentFromPayout = (bool) ($context?->tenant?->settings['booth_rent_deduct_from_payout'] ?? true);

        $results = $this->payoutService->runPayoutRun($consignorIds, $minimumAmount, $method, $rentCycleDay, $deductRentFromPayout);
        $this->auditService->log(
            $context?->tenant?->id,
            $context?->user?->id,
            $context?->supportUserId,
            'payout.run',
            null,
            null,
            ['consignor_ids' => $consignorIds, 'count' => count($results)],
            $context?->clientIp
        );
        $this->json(200, ['payouts' => $results]);
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/payouts/preview', ['GET'])]
    public function preview(string $slug): void
    {
        $context = \CurserPos\Http\RequestContextHolder::get();
        $minimumAmount = isset($_GET['minimum_amount']) ? (float) $_GET['minimum_amount'] : 0.0;
        $rentCycleDay = (int) ($context?->tenant?->settings['booth_rent_cycle_day'] ?? 1);
        $rentCycleDay = max(1, min(31, $rentCycleDay));
        $deductRentFromPayout = (bool) ($context?->tenant?->settings['booth_rent_deduct_from_payout'] ?? true);
        $rows = $this->payoutService->previewPayoutRun($minimumAmount, $rentCycleDay, $deductRentFromPayout);
        $this->json(200, ['preview' => $rows]);
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/consignors/([0-9a-fA-F-]{36})/payouts', ['GET'])]
    public function listByConsignor(string $slug, string $consignorId): void
    {
        $limit = (int) ($_GET['limit'] ?? 50);
        $rows = $this->payoutRepository->listByConsignor($consignorId, $limit);
        $this->json(200, $rows);
    }

    /**
     * @return array<string, mixed>
     */
    private function getJsonInput(): array
    {
        $body = file_get_contents('php://input');
        if ($body === false || $body === '') {
            return [];
        }
        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function json(int $status, array $data): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}
