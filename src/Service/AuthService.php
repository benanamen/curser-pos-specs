<?php

declare(strict_types=1);

namespace CurserPos\Service;

use CurserPos\Domain\Tenant\TenantProvisioningService;
use CurserPos\Domain\User\User;
use CurserPos\Domain\User\UserRepositoryInterface;
use PerfectApp\Session\Session;

final class AuthService
{
    private const SESSION_USER_ID = 'user_id';
    private const SESSION_TENANT_ID = 'tenant_id';

    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly TenantProvisioningService $tenantProvisioningService,
        private readonly Session $session
    ) {
    }

    /**
     * @return array{user: array{id: string, email: string}, tenant: array{id: string, slug: string, name: string}}
     */
    public function signup(string $email, string $password, string $storeName, string $storeSlug): array
    {
        if ($this->userRepository->findByEmail($email) !== null) {
            throw new \InvalidArgumentException('Email already registered');
        }

        if ($this->tenantProvisioningService->slugExists($storeSlug)) {
            throw new \InvalidArgumentException('Store slug already taken');
        }

        $this->validatePassword($password);
        $this->validateSlug($storeSlug);

        $result = $this->tenantProvisioningService->provision($storeName, $storeSlug, $email, $password);

        $this->session->set(self::SESSION_USER_ID, $result['user_id']);
        $this->session->set(self::SESSION_TENANT_ID, $result['tenant_id']);

        return [
            'user' => [
                'id' => $result['user_id'],
                'email' => $email,
            ],
            'tenant' => [
                'id' => $result['tenant_id'],
                'slug' => $storeSlug,
                'name' => $storeName,
            ],
        ];
    }

    /**
     * @return array{user: array{id: string, email: string}, tenant: array{id: string, slug: string, name: string}}
     */
    public function login(string $email, string $password): array
    {
        $user = $this->userRepository->findByEmail($email);
        if ($user === null) {
            throw new \InvalidArgumentException('Invalid credentials');
        }

        if (!password_verify($password, $this->userRepository->getPasswordHash($user->id))) {
            throw new \InvalidArgumentException('Invalid credentials');
        }

        $tenantUser = $this->userRepository->getDefaultTenantForUser($user->id);
        if ($tenantUser === null) {
            throw new \InvalidArgumentException('User has no tenant access');
        }

        $this->session->set(self::SESSION_USER_ID, $user->id);
        $this->session->set(self::SESSION_TENANT_ID, $tenantUser['tenant_id']);

        return [
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
            ],
            'tenant' => [
                'id' => $tenantUser['tenant_id'],
                'slug' => $tenantUser['tenant_slug'],
                'name' => $tenantUser['tenant_name'],
            ],
        ];
    }

    public function logout(): void
    {
        $this->session->delete(self::SESSION_USER_ID);
        $this->session->delete(self::SESSION_TENANT_ID);
    }

    public function getCurrentUser(): ?User
    {
        $userId = $this->session->get(self::SESSION_USER_ID);
        if ($userId === null) {
            return null;
        }
        return $this->userRepository->findById($userId);
    }

    public function getCurrentTenantId(): ?string
    {
        $tenantId = $this->session->get(self::SESSION_TENANT_ID);
        return $tenantId !== null ? (string) $tenantId : null;
    }

    private function validatePassword(string $password): void
    {
        if (strlen($password) < 8) {
            throw new \InvalidArgumentException('Password must be at least 8 characters');
        }
    }

    private function validateSlug(string $slug): void
    {
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $slug)) {
            throw new \InvalidArgumentException('Store slug can only contain letters, numbers, underscores and hyphens');
        }
    }
}
