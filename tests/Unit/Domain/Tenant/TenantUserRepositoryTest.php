<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Domain\Tenant;

use CurserPos\Domain\Role\RoleRepository;
use CurserPos\Domain\Tenant\TenantUserRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use PDO;
use PDOStatement;

#[AllowMockObjectsWithoutExpectations]
final class TenantUserRepositoryTest extends TestCase
{
    public function testGetByUserAndTenantReturnsNullWhenNotFound(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute');
        $stmt->method('fetch')->willReturn(false);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        $roleRepo = $this->createMock(RoleRepository::class);

        $repo = new TenantUserRepository($pdo, $roleRepo);
        $result = $repo->getByUserAndTenant('user-1', 'tenant-1');
        $this->assertNull($result);
    }

    public function testGetByUserAndTenantReturnsTenantUserWhenFound(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute');
        $stmt->method('fetch')->willReturn(['id' => 'tu-1', 'role_id' => 'role-1']);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        $roleRepo = $this->createMock(RoleRepository::class);
        $roleRepo->method('getById')->with('role-1')->willReturn([
            'id' => 'role-1',
            'name' => 'cashier',
            'permissions' => ['pos' => true],
        ]);

        $repo = new TenantUserRepository($pdo, $roleRepo);
        $result = $repo->getByUserAndTenant('user-1', 'tenant-1');
        $this->assertIsArray($result);
        $this->assertSame('tu-1', $result['tenant_user_id']);
        $this->assertSame(['pos' => true], $result['permissions']);
    }

    public function testGetByUserAndTenantReturnsNullWhenRoleNotFound(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute');
        $stmt->method('fetch')->willReturn(['id' => 'tu-1', 'role_id' => 'role-1']);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        $roleRepo = $this->createMock(RoleRepository::class);
        $roleRepo->method('getById')->with('role-1')->willReturn(null);

        $repo = new TenantUserRepository($pdo, $roleRepo);
        $result = $repo->getByUserAndTenant('user-1', 'tenant-1');
        $this->assertNull($result);
    }

    public function testListByTenantReturnsEmptyWhenNone(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute');
        $stmt->method('fetch')->willReturn(false);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        $roleRepo = $this->createMock(RoleRepository::class);

        $repo = new TenantUserRepository($pdo, $roleRepo);
        $result = $repo->listByTenant('tenant-1');
        $this->assertSame([], $result);
    }

    public function testListByTenantReturnsUsers(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute');
        $stmt->method('fetch')->willReturnOnConsecutiveCalls(
            [
                'tenant_user_id' => 'tu-1',
                'user_id' => 'u1',
                'email' => 'a@test.com',
                'role_id' => 'r1',
                'role_name' => 'admin',
                'active' => true,
                'created_at' => '2025-01-01',
            ],
            false
        );

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        $roleRepo = $this->createMock(RoleRepository::class);

        $repo = new TenantUserRepository($pdo, $roleRepo);
        $result = $repo->listByTenant('tenant-1');
        $this->assertCount(1, $result);
        $this->assertSame('a@test.com', $result[0]['email']);
    }

    public function testAddUserToTenantExecutesInsert(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute');

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        $roleRepo = $this->createMock(RoleRepository::class);

        $repo = new TenantUserRepository($pdo, $roleRepo);
        $repo->addUserToTenant('tenant-1', 'user-1', 'role-1');
    }

    public function testUpdateRoleExecutesUpdate(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute');

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        $roleRepo = $this->createMock(RoleRepository::class);

        $repo = new TenantUserRepository($pdo, $roleRepo);
        $repo->updateRole('tu-1', 'role-2');
    }

    public function testSetActiveExecutesUpdate(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute');

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        $roleRepo = $this->createMock(RoleRepository::class);

        $repo = new TenantUserRepository($pdo, $roleRepo);
        $repo->setActive('tu-1', false);
    }

    public function testGetByIdReturnsNullWhenNotFound(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute');
        $stmt->method('fetch')->willReturn(false);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        $roleRepo = $this->createMock(RoleRepository::class);

        $repo = new TenantUserRepository($pdo, $roleRepo);
        $result = $repo->getById('tu-nonexistent');
        $this->assertNull($result);
    }

    public function testGetByIdReturnsRowWhenFound(): void
    {
        $row = ['id' => 'tu-1', 'tenant_id' => 't1', 'user_id' => 'u1', 'role_id' => 'r1', 'active' => true];
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute');
        $stmt->method('fetch')->willReturn($row);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        $roleRepo = $this->createMock(RoleRepository::class);

        $repo = new TenantUserRepository($pdo, $roleRepo);
        $result = $repo->getById('tu-1');
        $this->assertSame($row, $result);
    }
}
