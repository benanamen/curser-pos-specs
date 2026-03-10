<?php

declare(strict_types=1);

namespace CurserPos\Service;

use CurserPos\Domain\Tenant\TenantUserRepository;
use CurserPos\Domain\User\InviteTokenRepository;
use CurserPos\Domain\User\UserRepositoryInterface;

final class UserInviteService
{
    private const MIN_PASSWORD_LENGTH = 8;

    public function __construct(
        private readonly InviteTokenRepository $inviteTokenRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly TenantUserRepository $tenantUserRepository
    ) {
    }

    public function invite(string $tenantId, string $email, string $roleId): string
    {
        $token = $this->inviteTokenRepository->create($tenantId, strtolower($email), $roleId);
        return $token;
    }

    /**
     * @return array{user_id: string, email: string, tenant_id: string}
     */
    public function acceptInvite(string $token, string $password): array
    {
        $this->validatePassword($password);
        $data = $this->inviteTokenRepository->consumeToken($token);
        if ($data === null) {
            throw new \InvalidArgumentException('Invalid or expired invite token');
        }
        $email = $data['email'];
        $tenantId = $data['tenant_id'];
        $roleId = $data['role_id'];

        $user = $this->userRepository->findByEmail($email);
        if ($user === null) {
            $userId = $this->userRepository->create($email, password_hash($password, PASSWORD_DEFAULT));
        } else {
            $userId = $user->id;
        }

        $this->tenantUserRepository->addUserToTenant($tenantId, $userId, $roleId);
        return [
            'user_id' => $userId,
            'email' => $email,
            'tenant_id' => $tenantId,
        ];
    }

    private function validatePassword(string $password): void
    {
        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            throw new \InvalidArgumentException('Password must be at least ' . self::MIN_PASSWORD_LENGTH . ' characters');
        }
    }
}
