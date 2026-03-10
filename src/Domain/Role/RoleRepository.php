<?php

declare(strict_types=1);

namespace CurserPos\Domain\Role;

use PDO;

class RoleRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    /**
     * @return array{id: string, name: string, permissions: array<string, bool>}|null
     */
    public function getById(string $roleId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, name, permissions FROM roles WHERE id = ?');
        $stmt->execute([$roleId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        $perms = $row['permissions'];
        if (is_string($perms)) {
            $decoded = json_decode($perms, true);
            $perms = is_array($decoded) ? $decoded : [];
        }
        return [
            'id' => (string) $row['id'],
            'name' => (string) $row['name'],
            'permissions' => is_array($perms) ? $perms : [],
        ];
    }
}
