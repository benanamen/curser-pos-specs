<?php

declare(strict_types=1);

namespace CurserPos\Domain\User;

use PDO;

class PasswordResetTokenRepository
{
    private const TOKEN_BYTES = 32;
    private const EXPIRY_HOURS = 24;

    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function createToken(string $userId): string
    {
        $this->invalidateExisting($userId);
        $token = bin2hex(random_bytes(self::TOKEN_BYTES));
        $tokenHash = hash('sha256', $token);
        $expiresAt = (new \DateTimeImmutable('+' . self::EXPIRY_HOURS . ' hours'))->format('Y-m-d H:i:s');
        $id = $this->generateUuid();
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare(
            'INSERT INTO password_reset_tokens (id, user_id, token_hash, expires_at, created_at)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$id, $userId, $tokenHash, $expiresAt, $now]);
        return $token;
    }

    /**
     * @return array{user_id: string}|null
     */
    public function consumeToken(string $token): ?array
    {
        $tokenHash = hash('sha256', $token);
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare(
            'SELECT user_id FROM password_reset_tokens WHERE token_hash = ? AND expires_at > ?'
        );
        $stmt->execute([$tokenHash, $now]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        $userId = (string) $row['user_id'];
        $this->pdo->prepare('DELETE FROM password_reset_tokens WHERE token_hash = ?')->execute([$tokenHash]);
        return ['user_id' => $userId];
    }

    private function invalidateExisting(string $userId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = ?');
        $stmt->execute([$userId]);
    }

    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
