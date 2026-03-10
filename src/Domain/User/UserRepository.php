<?php

declare(strict_types=1);

namespace CurserPos\Domain\User;

use PDO;

final class UserRepository implements UserRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function findByEmail(string $email): ?User
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, email, status, created_at, updated_at FROM users WHERE email = ? AND status = ?'
        );
        $stmt->execute([strtolower($email), 'active']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function findById(string $id): ?User
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, email, status, created_at, updated_at FROM users WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function getPasswordHash(string $userId): string
    {
        $stmt = $this->pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? (string) $row['password_hash'] : '';
    }

    /**
     * @return array{tenant_id: string, tenant_slug: string, tenant_name: string}|null
     */
    public function getDefaultTenantForUser(string $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT t.id AS tenant_id, t.slug AS tenant_slug, t.name AS tenant_name
             FROM tenant_users tu
             JOIN tenants t ON t.id = tu.tenant_id
             WHERE tu.user_id = ? AND tu.active = true
             ORDER BY tu.created_at ASC
             LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function create(string $email, string $passwordHash): string
    {
        $id = $this->generateUuid();
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare(
            'INSERT INTO users (id, email, password_hash, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$id, strtolower($email), $passwordHash, 'active', $now, $now]);
        return $id;
    }

    public function updatePassword(string $userId, string $passwordHash): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$passwordHash, $now, $userId]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): User
    {
        return new User(
            (string) $row['id'],
            (string) $row['email'],
            (string) $row['status'],
            new \DateTimeImmutable((string) $row['created_at']),
            new \DateTimeImmutable((string) $row['updated_at'])
        );
    }

    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
