<?php

declare(strict_types=1);

namespace CurserPos\Api\V1;

use PerfectApp\Routing\Route;

final class AuthController
{
    public function __construct(
        private readonly \CurserPos\Service\AuthService $authService,
        private readonly \CurserPos\Service\PasswordResetService $passwordResetService,
        private readonly \CurserPos\Service\AuditService $auditService,
        private readonly \CurserPos\Service\UserInviteService $userInviteService
    ) {
    }

    #[Route('/api/v1/signup', ['POST'])]
    public function signup(): void
    {
        $input = $this->getJsonInput();
        $email = $input['email'] ?? '';
        $password = $input['password'] ?? '';
        $storeName = $input['store_name'] ?? '';
        $storeSlug = $input['store_slug'] ?? '';

        if ($email === '' || $password === '' || $storeName === '' || $storeSlug === '') {
            $this->jsonResponse(400, ['error' => 'Missing required fields: email, password, store_name, store_slug']);
            return;
        }

        try {
            $result = $this->authService->signup($email, $password, $storeName, $storeSlug);
            $this->jsonResponse(201, $result);
        } catch (\InvalidArgumentException $e) {
            $this->jsonResponse(400, ['error' => $e->getMessage()]);
        }
    }

    #[Route('/api/v1/auth/login', ['POST'])]
    public function login(): void
    {
        $input = $this->getJsonInput();
        $email = $input['email'] ?? '';
        $password = $input['password'] ?? '';

        if ($email === '' || $password === '') {
            $this->jsonResponse(400, ['error' => 'Missing email or password']);
            return;
        }

        try {
            $result = $this->authService->login($email, $password);
            $context = \CurserPos\Http\RequestContextHolder::get();
            $this->auditService->log(
                $result['tenant']['id'] ?? null,
                $result['user']['id'] ?? null,
                null,
                'auth.login',
                null,
                null,
                ['email' => $email],
                $context?->clientIp
            );
            $this->jsonResponse(200, $result);
        } catch (\InvalidArgumentException $e) {
            $this->jsonResponse(401, ['error' => $e->getMessage()]);
        }
    }

    #[Route('/api/v1/auth/logout', ['POST'])]
    public function logout(): void
    {
        $user = $this->authService->getCurrentUser();
        $tenantId = $this->authService->getCurrentTenantId();
        $userId = $user?->id;
        $this->authService->logout();
        $context = \CurserPos\Http\RequestContextHolder::get();
        $this->auditService->log($tenantId, $userId, null, 'auth.logout', null, null, [], $context?->clientIp);
        $this->jsonResponse(200, ['message' => 'Logged out']);
    }

    #[Route('/api/v1/auth/forgot-password', ['POST'])]
    public function forgotPassword(): void
    {
        $input = $this->getJsonInput();
        $email = isset($input['email']) && is_string($input['email']) ? trim($input['email']) : '';
        if ($email === '') {
            $this->jsonResponse(400, ['error' => 'Missing email']);
            return;
        }
        $this->passwordResetService->requestReset($email);
        $this->jsonResponse(200, ['message' => 'If that email is registered, a reset link has been sent.']);
    }

    #[Route('/api/v1/auth/reset-password', ['POST'])]
    public function resetPassword(): void
    {
        $input = $this->getJsonInput();
        $token = isset($input['token']) && is_string($input['token']) ? trim($input['token']) : '';
        $newPassword = isset($input['new_password']) && is_string($input['new_password']) ? $input['new_password'] : '';
        if ($token === '' || $newPassword === '') {
            $this->jsonResponse(400, ['error' => 'Missing token or new_password']);
            return;
        }
        try {
            $this->passwordResetService->resetPassword($token, $newPassword);
            $this->jsonResponse(200, ['message' => 'Password has been reset.']);
        } catch (\InvalidArgumentException $e) {
            $this->jsonResponse(400, ['error' => $e->getMessage()]);
        }
    }

    #[Route('/api/v1/auth/accept-invite', ['POST'])]
    public function acceptInvite(): void
    {
        $input = $this->getJsonInput();
        $token = isset($input['token']) && is_string($input['token']) ? trim($input['token']) : '';
        $password = isset($input['password']) && is_string($input['password']) ? $input['password'] : '';
        if ($token === '' || $password === '') {
            $this->jsonResponse(400, ['error' => 'Missing token or password']);
            return;
        }
        try {
            $result = $this->userInviteService->acceptInvite($token, $password);
            $this->jsonResponse(200, [
                'message' => 'Invite accepted',
                'user_id' => $result['user_id'],
                'email' => $result['email'],
                'tenant_id' => $result['tenant_id'],
            ]);
        } catch (\InvalidArgumentException $e) {
            $this->jsonResponse(400, ['error' => $e->getMessage()]);
        }
    }

    #[Route('/t/([a-zA-Z0-9_-]+)/api/v1/me', ['GET'])]
    public function me(string $slug): void
    {
        $user = $this->authService->getCurrentUser();
        if ($user === null) {
            $this->jsonResponse(401, ['error' => 'Not authenticated']);
            return;
        }

        $context = \CurserPos\Http\RequestContextHolder::get();
        $tenant = $context?->tenant;

        $this->jsonResponse(200, [
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'status' => $user->status,
            ],
            'tenant' => $tenant ? [
                'id' => $tenant->id,
                'slug' => $tenant->slug,
                'name' => $tenant->name,
            ] : null,
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
    private function jsonResponse(int $status, array $data): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}
