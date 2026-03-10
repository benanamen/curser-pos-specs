<?php

declare(strict_types=1);

namespace CurserPos\Service;

use CurserPos\Domain\Platform\PlatformUserRepository;
use PerfectApp\Session\Session;

final class PlatformAuthService
{
    private const SESSION_PLATFORM_USER_ID = 'platform_user_id';

    public function __construct(
        private readonly PlatformUserRepository $platformUserRepository,
        private readonly Session $session
    ) {
    }

    /**
     * @return array{id: string, email: string}
     */
    public function login(string $email, string $password): array
    {
        $row = $this->platformUserRepository->findByEmail($email);
        if ($row === null) {
            throw new \InvalidArgumentException('Invalid credentials');
        }
        if (!password_verify($password, $this->platformUserRepository->getPasswordHash((string) $row['id']))) {
            throw new \InvalidArgumentException('Invalid credentials');
        }
        $this->session->set(self::SESSION_PLATFORM_USER_ID, (string) $row['id']);
        return [
            'id' => (string) $row['id'],
            'email' => (string) $row['email'],
        ];
    }

    public function logout(): void
    {
        $this->session->delete(self::SESSION_PLATFORM_USER_ID);
    }
}
