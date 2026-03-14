<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Domain\Role;

use CurserPos\Domain\Role\RoleRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use PDO;
use PDOStatement;

#[AllowMockObjectsWithoutExpectations]
final class RoleRepositoryTest extends TestCase
{
    public function testGetByIdReturnsNullWhenNotFound(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute');
        $stmt->method('fetch')->willReturn(false);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new RoleRepository($pdo);
        $result = $repo->getById('nonexistent');
        $this->assertNull($result);
    }

    public function testGetByIdReturnsRoleWithPermissions(): void
    {
        $row = [
            'id' => 'b0000000-0000-0000-0000-000000000001',
            'name' => 'admin',
            'permissions' => '{"all": true}',
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute');
        $stmt->method('fetch')->willReturn($row);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new RoleRepository($pdo);
        $result = $repo->getById('b0000000-0000-0000-0000-000000000001');
        $this->assertIsArray($result);
        $this->assertSame('admin', $result['name']);
        $this->assertArrayHasKey('permissions', $result);
        $this->assertTrue($result['permissions']['all']);
    }
}
