<?php

declare(strict_types=1);

namespace CurserPos\Api\V1;

use CurserPos\Domain\Plan\PlanRepository;
use CurserPos\Domain\Tenant\TenantProvisioningService;
use CurserPos\Domain\Tenant\TenantRepositoryInterface;
use PerfectApp\Routing\Route;

final class PlatformTenantController
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenantRepository,
        private readonly TenantProvisioningService $provisioningService,
        private readonly PlanRepository $planRepository
    ) {
    }

    #[Route('/api/v1/platform/tenants', ['GET'])]
    public function list(): void
    {
        $tenants = $this->tenantRepository->list();
        $data = [];
        foreach ($tenants as $t) {
            $data[] = [
                'id' => $t->id,
                'slug' => $t->slug,
                'name' => $t->name,
                'status' => $t->status,
                'plan_id' => $t->planId,
                'created_at' => $t->createdAt->format(\DateTimeInterface::ATOM),
            ];
        }
        $this->json(200, ['tenants' => $data]);
    }

    #[Route('/api/v1/platform/tenants/([0-9a-fA-F-]{36})', ['GET'])]
    public function show(string $id): void
    {
        $tenant = $this->tenantRepository->findById($id);
        if ($tenant === null) {
            $this->json(404, ['error' => 'Tenant not found']);
            return;
        }
        $plan = $this->planRepository->findById($tenant->planId);
        $this->json(200, [
            'id' => $tenant->id,
            'slug' => $tenant->slug,
            'name' => $tenant->name,
            'status' => $tenant->status,
            'plan_id' => $tenant->planId,
            'plan' => $plan,
            'settings' => $tenant->settings,
            'created_at' => $tenant->createdAt->format(\DateTimeInterface::ATOM),
            'updated_at' => $tenant->updatedAt->format(\DateTimeInterface::ATOM),
        ]);
    }

    #[Route('/api/v1/platform/tenants', ['POST'])]
    public function create(): void
    {
        $input = $this->getJsonInput();
        $storeName = isset($input['store_name']) && is_string($input['store_name']) ? trim($input['store_name']) : '';
        $storeSlug = isset($input['store_slug']) && is_string($input['store_slug']) ? trim($input['store_slug']) : '';
        $ownerEmail = isset($input['owner_email']) && is_string($input['owner_email']) ? trim($input['owner_email']) : '';
        $ownerPassword = isset($input['owner_password']) && is_string($input['owner_password']) ? $input['owner_password'] : '';
        $planId = isset($input['plan_id']) && is_string($input['plan_id']) && $input['plan_id'] !== ''
            ? trim($input['plan_id'])
            : null;

        if ($storeName === '' || $storeSlug === '' || $ownerEmail === '' || $ownerPassword === '') {
            $this->json(400, ['error' => 'Missing required fields: store_name, store_slug, owner_email, owner_password']);
            return;
        }

        try {
            $result = $this->provisioningService->provision($storeName, $storeSlug, $ownerEmail, $ownerPassword, $planId);
            $tenant = $this->tenantRepository->findById($result['tenant_id']);
            $this->json(201, [
                'tenant_id' => $result['tenant_id'],
                'user_id' => $result['user_id'],
                'tenant' => $tenant ? [
                    'id' => $tenant->id,
                    'slug' => $tenant->slug,
                    'name' => $tenant->name,
                    'status' => $tenant->status,
                    'plan_id' => $tenant->planId,
                ] : null,
            ]);
        } catch (\InvalidArgumentException $e) {
            $this->json(400, ['error' => $e->getMessage()]);
        }
    }

    #[Route('/api/v1/platform/tenants/([0-9a-fA-F-]{36})', ['PATCH', 'PUT'])]
    public function update(string $id): void
    {
        $tenant = $this->tenantRepository->findById($id);
        if ($tenant === null) {
            $this->json(404, ['error' => 'Tenant not found']);
            return;
        }

        $input = $this->getJsonInput();
        $name = isset($input['name']) && is_string($input['name']) ? trim($input['name']) : $tenant->name;
        $status = isset($input['status']) && is_string($input['status']) ? trim($input['status']) : $tenant->status;
        $planId = isset($input['plan_id']) && is_string($input['plan_id']) ? trim($input['plan_id']) : $tenant->planId;

        $allowedStatuses = ['active', 'suspended', 'cancelled'];
        if (!in_array($status, $allowedStatuses, true)) {
            $this->json(400, ['error' => 'status must be one of: ' . implode(', ', $allowedStatuses)]);
            return;
        }

        if ($this->planRepository->findById($planId) === null) {
            $this->json(400, ['error' => 'Invalid plan_id']);
            return;
        }

        $this->tenantRepository->update($id, $name, $status, $planId);
        $updated = $this->tenantRepository->findById($id);
        $this->json(200, [
            'id' => $updated->id,
            'slug' => $updated->slug,
            'name' => $updated->name,
            'status' => $updated->status,
            'plan_id' => $updated->planId,
        ]);
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
