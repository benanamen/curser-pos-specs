<?php

declare(strict_types=1);

namespace CurserPos\Domain\Tenant;

use CurserPos\Domain\Role\RoleRepository;
use PDO;

class TenantUserRepository
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly RoleRepository $roleRepository
    ) {
    }

    /**
     * @return array{tenant_user_id: string, role_id: string, permissions: array<string, bool>}|null
     */
    public function getByUserAndTenant(string $userId, string $tenantId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, role_id FROM tenant_users WHERE user_id = ? AND tenant_id = ? AND active = true'
        );
        $stmt->execute([$userId, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        $role = $this->roleRepository->getById((string) $row['role_id']);
        if ($role === null) {
            return null;
        }
        return [
            'tenant_user_id' => (string) $row['id'],
            'role_id' => (string) $row['role_id'],
            'permissions' => $role['permissions'],
        ];
    }

    /**
     * @return list<array{tenant_user_id: string, user_id: string, email: string, role_id: string, role_name: string, active: bool, created_at: string}>
     */
    public function listByTenant(string $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT tu.id AS tenant_user_id, tu.user_id, u.email, tu.role_id, r.name AS role_name, tu.active, tu.created_at
             FROM tenant_users tu
             JOIN users u ON u.id = tu.user_id
             JOIN roles r ON r.id = tu.role_id
             WHERE tu.tenant_id = ?
             ORDER BY tu.created_at ASC'
        );
        $stmt->execute([$tenantId]);
        $rows = [];
        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            $rows[] = [
                'tenant_user_id' => (string) $row['tenant_user_id'],
                'user_id' => (string) $row['user_id'],
                'email' => (string) $row['email'],
                'role_id' => (string) $row['role_id'],
                'role_name' => (string) $row['role_name'],
                'active' => (bool) $row['active'],
                'created_at' => (string) $row['created_at'],
            ];
        }
        return $rows;
    }

    public function addUserToTenant(string $tenantId, string $userId, string $roleId): void
    {
        $id = $this->generateUuid();
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'INSERT INTO tenant_users (id, tenant_id, user_id, role_id, active, created_at, updated_at)
             VALUES (?, ?, ?, ?, true, ?, ?)
             ON CONFLICT (tenant_id, user_id) DO UPDATE SET role_id = EXCLUDED.role_id, active = true, updated_at = EXCLUDED.updated_at'
        );
        $stmt->execute([$id, $tenantId, $userId, $roleId, $now, $now]);
    }

    public function updateRole(string $tenantUserId, string $roleId): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('UPDATE tenant_users SET role_id = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$roleId, $now, $tenantUserId]);
    }

    public function setActive(string $tenantUserId, bool $active): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('UPDATE tenant_users SET active = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$active, $now, $tenantUserId]);
    }

    public function getById(string $tenantUserId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, tenant_id, user_id, role_id, active FROM tenant_users WHERE id = ?'
        );
        $stmt->execute([$tenantUserId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
