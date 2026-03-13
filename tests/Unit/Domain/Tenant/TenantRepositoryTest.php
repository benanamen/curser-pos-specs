<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Domain\Tenant;

use CurserPos\Domain\Tenant\Tenant;
use CurserPos\Domain\Tenant\TenantRepository;
use PHPUnit\Framework\TestCase;
use PDO;
use PDOStatement;

class TenantRepositoryTest extends TestCase
{
    public function testFindBySlugReturnsNullWhenNotFound(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute');
        $stmt->method('fetch')->willReturn(false);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new TenantRepository($pdo);
        $result = $repo->findBySlug('nonexistent');
        $this->assertNull($result);
    }

    public function testFindBySlugReturnsTenantWhenFound(): void
    {
        $row = [
            'id' => '123e4567-e89b-12d3-a456-426614174000',
            'slug' => 'mystore',
            'name' => 'My Store',
            'status' => 'active',
            'plan_id' => 'a0000000-0000-0000-0000-000000000001',
            'settings' => '{}',
            'created_at' => '2025-01-01 00:00:00',
            'updated_at' => '2025-01-01 00:00:00',
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute');
        $stmt->method('fetch')->willReturn($row);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new TenantRepository($pdo);
        $tenant = $repo->findBySlug('mystore');
        $this->assertInstanceOf(Tenant::class, $tenant);
        $this->assertSame('mystore', $tenant->slug);
        $this->assertSame('My Store', $tenant->name);
    }

    public function testSlugExistsReturnsTrueWhenFound(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute');
        $stmt->method('fetch')->willReturn(['1']);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new TenantRepository($pdo);
        $this->assertTrue($repo->slugExists('mystore'));
    }

    public function testSlugExistsReturnsFalseWhenNotFound(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute');
        $stmt->method('fetch')->willReturn(false);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new TenantRepository($pdo);
        $this->assertFalse($repo->slugExists('mystore'));
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute');
        $stmt->method('fetch')->willReturn(false);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new TenantRepository($pdo);
        $this->assertNull($repo->findById('nonexistent'));
    }

    public function testFindByIdReturnsTenantWhenFound(): void
    {
        $row = [
            'id' => '123e4567-e89b-12d3-a456-426614174000',
            'slug' => 'mystore',
            'name' => 'My Store',
            'status' => 'active',
            'plan_id' => 'a0000000-0000-0000-0000-000000000001',
            'settings' => '{}',
            'created_at' => '2025-01-01 00:00:00',
            'updated_at' => '2025-01-01 00:00:00',
        ];
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute');
        $stmt->method('fetch')->willReturn($row);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new TenantRepository($pdo);
        $tenant = $repo->findById('123e4567-e89b-12d3-a456-426614174000');
        $this->assertInstanceOf(Tenant::class, $tenant);
        $this->assertSame('123e4567-e89b-12d3-a456-426614174000', $tenant->id);
    }

    public function testFindBySlugHydratesSettingsAsJson(): void
    {
        $row = [
            'id' => '123e4567-e89b-12d3-a456-426614174000',
            'slug' => 'mystore',
            'name' => 'My Store',
            'status' => 'active',
            'plan_id' => 'a0000000-0000-0000-0000-000000000001',
            'settings' => '{"default_commission_pct": 60}',
            'created_at' => '2025-01-01 00:00:00',
            'updated_at' => '2025-01-01 00:00:00',
        ];
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute');
        $stmt->method('fetch')->willReturn($row);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new TenantRepository($pdo);
        $tenant = $repo->findBySlug('mystore');
        $this->assertSame(['default_commission_pct' => 60], $tenant->settings);
    }

    public function testListReturnsAllTenants(): void
    {
        $rows = [
            [
                'id' => 'id1',
                'slug' => 's1',
                'name' => 'Store 1',
                'status' => 'active',
                'plan_id' => 'p1',
                'settings' => '{}',
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ],
        ];
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetchAll')->with(\PDO::FETCH_ASSOC)->willReturn($rows);
        $pdo = $this->createMock(PDO::class);
        $pdo->method('query')->willReturn($stmt);

        $repo = new TenantRepository($pdo);
        $list = $repo->list();
        $this->assertCount(1, $list);
        $this->assertInstanceOf(Tenant::class, $list[0]);
        $this->assertSame('s1', $list[0]->slug);
    }

    public function testUpdateCallsExecuteWithCorrectParams(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with($this->callback(function (array $params): bool {
            return count($params) === 5
                && $params[0] === 'New Name'
                && $params[1] === 'suspended'
                && $params[2] === 'plan-2'
                && $params[4] === 'tenant-id';
        }));
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new TenantRepository($pdo);
        $repo->update('tenant-id', 'New Name', 'suspended', 'plan-2');
    }
}
