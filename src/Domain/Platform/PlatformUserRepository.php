<?php

declare(strict_types=1);

namespace CurserPos\Domain\Platform;

use PDO;

class PlatformUserRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, email, password_hash FROM platform_users WHERE email = ?'
        );
        $stmt->execute([strtolower($email)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function getPasswordHash(string $platformUserId): string
    {
        $stmt = $this->pdo->prepare('SELECT password_hash FROM platform_users WHERE id = ?');
        $stmt->execute([$platformUserId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? (string) $row['password_hash'] : '';
    }
}
