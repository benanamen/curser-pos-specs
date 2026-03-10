<?php

declare(strict_types=1);

namespace CurserPos\Domain\User;

use PDO;

class InviteTokenRepository
{
    private const TOKEN_BYTES = 32;
    private const EXPIRY_DAYS = 7;

    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function create(string $tenantId, string $email, string $roleId): string
    {
        $token = bin2hex(random_bytes(self::TOKEN_BYTES));
        $tokenHash = hash('sha256', $token);
        $expiresAt = (new \DateTimeImmutable('+' . self::EXPIRY_DAYS . ' days'))->format('Y-m-d H:i:s');
        $id = $this->generateUuid();
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare(
            'INSERT INTO invite_tokens (id, tenant_id, email, role_id, token_hash, expires_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$id, $tenantId, strtolower($email), $roleId, $tokenHash, $expiresAt, $now]);
        return $token;
    }

    /**
     * @return array{tenant_id: string, email: string, role_id: string}|null
     */
    public function consumeToken(string $token): ?array
    {
        $tokenHash = hash('sha256', $token);
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare(
            'SELECT tenant_id, email, role_id FROM invite_tokens WHERE token_hash = ? AND expires_at > ?'
        );
        $stmt->execute([$tokenHash, $now]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        $tenantId = (string) $row['tenant_id'];
        $email = (string) $row['email'];
        $roleId = (string) $row['role_id'];
        $this->pdo->prepare('DELETE FROM invite_tokens WHERE token_hash = ?')->execute([$tokenHash]);
        return ['tenant_id' => $tenantId, 'email' => $email, 'role_id' => $roleId];
    }

    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
