<?php

declare(strict_types=1);

namespace CurserPos\Domain\Tenant;

use PDO;

final class TenantRepository implements TenantRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function findBySlug(string $slug): ?Tenant
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, slug, name, status, plan_id, settings, created_at, updated_at FROM tenants WHERE slug = ? AND status = ?'
        );
        $stmt->execute([$slug, 'active']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function findById(string $id): ?Tenant
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, slug, name, status, plan_id, settings, created_at, updated_at FROM tenants WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function slugExists(string $slug): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM tenants WHERE slug = ?');
        $stmt->execute([$slug]);
        return $stmt->fetch() !== false;
    }

    /**
     * @return list<Tenant>
     */
    public function list(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, slug, name, status, plan_id, settings, created_at, updated_at FROM tenants ORDER BY created_at DESC'
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->hydrate($row);
        }
        return $result;
    }

    public function update(string $id, string $name, string $status, string $planId): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'UPDATE tenants SET name = ?, status = ?, plan_id = ?, updated_at = ? WHERE id = ?'
        );
        $stmt->execute([$name, $status, $planId, $now, $id]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Tenant
    {
        $settings = is_string($row['settings'] ?? '{}')
            ? json_decode($row['settings'], true)
            : ($row['settings'] ?? []);
        $settings = is_array($settings) ? $settings : [];

        return new Tenant(
            (string) $row['id'],
            (string) $row['slug'],
            (string) $row['name'],
            (string) $row['status'],
            (string) $row['plan_id'],
            $settings,
            new \DateTimeImmutable((string) $row['created_at']),
            new \DateTimeImmutable((string) $row['updated_at'])
        );
    }
}
