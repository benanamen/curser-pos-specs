<?php

declare(strict_types=1);

namespace CurserPos\Api\V1;

use CurserPos\Domain\Tenant\TenantUserRepository;
use CurserPos\Service\AuditService;
use CurserPos\Service\UserInviteService;
use PerfectApp\Routing\Route;

final class UserManagementController
{
    public function __construct(
        private readonly TenantUserRepository $tenantUserRepository,
        private readonly UserInviteService $userInviteService,
        private readonly AuditService $auditService
    ) {
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/users', ['GET'])]
    public function list(string $slug): void
    {
        $context = \CurserPos\Http\RequestContextHolder::get();
        $tenant = $context?->tenant;
        if ($tenant === null) {
            $this->jsonResponse(404, ['error' => 'Tenant not found']);
            return;
        }
        $users = $this->tenantUserRepository->listByTenant($tenant->id);
        $this->jsonResponse(200, ['users' => $users]);
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/users/invite', ['POST'])]
    public function invite(string $slug): void
    {
        $context = \CurserPos\Http\RequestContextHolder::get();
        $tenant = $context?->tenant;
        if ($tenant === null) {
            $this->jsonResponse(404, ['error' => 'Tenant not found']);
            return;
        }
        $input = $this->getJsonInput();
        $email = isset($input['email']) && is_string($input['email']) ? trim($input['email']) : '';
        $roleId = isset($input['role_id']) && is_string($input['role_id']) ? trim($input['role_id']) : '';
        if ($email === '' || $roleId === '') {
            $this->jsonResponse(400, ['error' => 'Missing email or role_id']);
            return;
        }
        $token = $this->userInviteService->invite($tenant->id, $email, $roleId);
        $inviteUrl = sprintf('/t/%s/accept-invite?token=%s', $slug, $token);
        $this->auditService->log(
            $tenant->id,
            $context->user?->id,
            $context->supportUserId,
            'user.invite',
            'invite',
            null,
            ['email' => $email, 'role_id' => $roleId],
            $context->clientIp
        );
        $this->jsonResponse(201, [
            'message' => 'Invite sent',
            'invite_url' => $inviteUrl,
            'expires_in_days' => 7,
        ]);
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/users/([0-9a-fA-F-]{36})', ['PATCH', 'PUT'])]
    public function update(string $slug, string $tenantUserId): void
    {
        $context = \CurserPos\Http\RequestContextHolder::get();
        $tenant = $context?->tenant;
        if ($tenant === null) {
            $this->jsonResponse(404, ['error' => 'Tenant not found']);
            return;
        }
        $tu = $this->tenantUserRepository->getById($tenantUserId);
        if ($tu === null || (string) $tu['tenant_id'] !== $tenant->id) {
            $this->jsonResponse(404, ['error' => 'User not found']);
            return;
        }
        $input = $this->getJsonInput();
        if (isset($input['role_id']) && is_string($input['role_id']) && $input['role_id'] !== '') {
            $this->tenantUserRepository->updateRole($tenantUserId, $input['role_id']);
            $this->auditService->log(
                $tenant->id,
                $context->user?->id,
                $context->supportUserId,
                'user.role_update',
                'tenant_user',
                $tenantUserId,
                ['role_id' => $input['role_id']],
                $context->clientIp
            );
        }
        if (isset($input['active']) && is_bool($input['active'])) {
            $this->tenantUserRepository->setActive($tenantUserId, $input['active']);
            $this->auditService->log(
                $tenant->id,
                $context->user?->id,
                $context->supportUserId,
                'user.active_update',
                'tenant_user',
                $tenantUserId,
                ['active' => $input['active']],
                $context->clientIp
            );
        }
        $this->jsonResponse(200, ['message' => 'Updated']);
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
    private function jsonResponse(int $status, array $data): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}
