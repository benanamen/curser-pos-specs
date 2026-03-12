<?php

declare(strict_types=1);

namespace CurserPos\Domain\Tenant;

use CurserPos\Domain\User\UserRepositoryInterface;
use PDO;

class TenantProvisioningService
{
    private const DEFAULT_PLAN_ID = 'a0000000-0000-0000-0000-000000000001';

    public function __construct(
        private readonly PDO $pdo,
        private readonly TenantRepositoryInterface $tenantRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly string $migrationsPath
    ) {
    }

    /**
     * @return array{tenant_id: string, user_id: string}
     */
    public function provision(string $storeName, string $storeSlug, string $ownerEmail, string $ownerPassword, ?string $planId = null): array
    {
        if ($this->tenantRepository->slugExists($storeSlug)) {
            throw new \InvalidArgumentException('Store slug already taken');
        }

        $userId = $this->userRepository->create($ownerEmail, password_hash($ownerPassword, PASSWORD_DEFAULT));

        $tenantId = $this->createTenant($storeName, $storeSlug, $planId ?? self::DEFAULT_PLAN_ID);
        $this->linkUserToTenant($userId, $tenantId);
        $this->createTenantSchema($tenantId);
        $this->runTenantMigrations($tenantId);
        $this->seedTenantBaseline($tenantId);

        return [
            'tenant_id' => $tenantId,
            'user_id' => $userId,
        ];
    }

    public function slugExists(string $slug): bool
    {
        return $this->tenantRepository->slugExists($slug);
    }

    private function createTenant(string $name, string $slug, string $planId): string
    {
        $id = $this->generateUuid();
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare(
            'INSERT INTO tenants (id, slug, name, status, plan_id, settings, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$id, $slug, $name, 'active', $planId, '{}', $now, $now]);
        return $id;
    }

    private function linkUserToTenant(string $userId, string $tenantId): void
    {
        $id = $this->generateUuid();
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $adminRoleId = 'b0000000-0000-0000-0000-000000000001';

        $stmt = $this->pdo->prepare(
            'INSERT INTO tenant_users (id, tenant_id, user_id, role_id, active, created_at, updated_at)
             VALUES (?, ?, ?, ?, true, ?, ?)'
        );
        $stmt->execute([$id, $tenantId, $userId, $adminRoleId, $now, $now]);
    }

    private function createTenantSchema(string $tenantId): void
    {
        $schemaName = 'tenant_' . str_replace('-', '_', $tenantId);
        $this->pdo->exec("CREATE SCHEMA IF NOT EXISTS \"{$schemaName}\"");
    }

    private function runTenantMigrations(string $tenantId): void
    {
        $schemaName = 'tenant_' . str_replace('-', '_', $tenantId);
        $this->pdo->exec("SET search_path TO \"{$schemaName}\", public");

        $files = glob($this->migrationsPath . '/*.sql');
        sort($files);

        foreach ($files as $file) {
            $sql = file_get_contents($file);
            if ($sql !== false) {
                $this->pdo->exec($sql);
            }
        }

        $this->pdo->exec('SET search_path TO public');
    }

    private function seedTenantBaseline(string $tenantId): void
    {
        $schemaName = 'tenant_' . str_replace('-', '_', $tenantId);
        $this->pdo->exec("SET search_path TO \"{$schemaName}\", public");

        $locationId = $this->generateUuid();
        $stmt = $this->pdo->prepare(
            'INSERT INTO locations (id, name, address, tax_rates) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$locationId, 'Main Store', '', '[]']);

        $stmt = $this->pdo->prepare(
            'INSERT INTO categories (id, name, sort_order, tax_exempt) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$this->generateUuid(), 'General', 0, 'f']);

        $this->pdo->exec('SET search_path TO public');
    }

    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
