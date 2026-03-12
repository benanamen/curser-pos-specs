<?php

declare(strict_types=1);

namespace CurserPos\Api\V1;

use CurserPos\Domain\Tenant\TenantRepositoryInterface;
use PerfectApp\Routing\Route;

final class PlatformDashboardController
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenantRepository
    ) {
    }

    #[Route('/api/v1/platform/dashboard', ['GET'])]
    public function stats(): void
    {
        $tenants = $this->tenantRepository->list();
        $total = count($tenants);
        $active = 0;
        foreach ($tenants as $t) {
            if ($t->status === 'active') {
                $active++;
            }
        }
        $this->json(200, [
            'total_tenants' => $total,
            'active_tenants' => $active,
        ]);
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
