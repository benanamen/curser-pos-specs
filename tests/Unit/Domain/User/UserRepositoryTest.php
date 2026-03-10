<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Domain\User;

use CurserPos\Domain\User\User;
use CurserPos\Domain\User\UserRepository;
use PHPUnit\Framework\TestCase;
use PDO;
use PDOStatement;

final class UserRepositoryTest extends TestCase
{
    public function testFindByEmailReturnsNullWhenNotFound(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute');
        $stmt->method('fetch')->willReturn(false);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new UserRepository($pdo);
        $result = $repo->findByEmail('nobody@test.com');
        $this->assertNull($result);
    }

    public function testFindByEmailReturnsUserWhenFound(): void
    {
        $row = [
            'id' => '123e4567-e89b-12d3-a456-426614174000',
            'email' => 'user@test.com',
            'status' => 'active',
            'created_at' => '2025-01-01 00:00:00',
            'updated_at' => '2025-01-01 00:00:00',
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute');
        $stmt->method('fetch')->willReturn($row);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new UserRepository($pdo);
        $user = $repo->findByEmail('user@test.com');
        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('user@test.com', $user->email);
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute');
        $stmt->method('fetch')->willReturn(false);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new UserRepository($pdo);
        $result = $repo->findById('nonexistent');
        $this->assertNull($result);
    }

    public function testFindByIdReturnsUserWhenFound(): void
    {
        $row = [
            'id' => '123e4567-e89b-12d3-a456-426614174000',
            'email' => 'user@test.com',
            'status' => 'active',
            'created_at' => '2025-01-01 00:00:00',
            'updated_at' => '2025-01-01 00:00:00',
        ];
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute');
        $stmt->method('fetch')->willReturn($row);
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        $repo = new UserRepository($pdo);
        $user = $repo->findById('123e4567-e89b-12d3-a456-426614174000');
        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('123e4567-e89b-12d3-a456-426614174000', $user->id);
    }

    public function testGetPasswordHashReturnsHash(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute');
        $stmt->method('fetch')->willReturn(['password_hash' => 'stored_hash']);
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        $repo = new UserRepository($pdo);
        $this->assertSame('stored_hash', $repo->getPasswordHash('user-1'));
    }

    public function testGetPasswordHashReturnsEmptyWhenNotFound(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute');
        $stmt->method('fetch')->willReturn(false);
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        $repo = new UserRepository($pdo);
        $this->assertSame('', $repo->getPasswordHash('user-1'));
    }

    public function testGetDefaultTenantForUserReturnsNullWhenNotFound(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute');
        $stmt->method('fetch')->willReturn(false);
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        $repo = new UserRepository($pdo);
        $this->assertNull($repo->getDefaultTenantForUser('user-1'));
    }

    public function testGetDefaultTenantForUserReturnsTenantWhenFound(): void
    {
        $row = ['tenant_id' => 't1', 'tenant_slug' => 'mystore', 'tenant_name' => 'My Store'];
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute');
        $stmt->method('fetch')->willReturn($row);
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        $repo = new UserRepository($pdo);
        $result = $repo->getDefaultTenantForUser('user-1');
        $this->assertSame('t1', $result['tenant_id']);
        $this->assertSame('mystore', $result['tenant_slug']);
    }

    public function testCreateInsertsAndReturnsId(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute');
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        $repo = new UserRepository($pdo);
        $id = $repo->create('new@test.com', 'hashed_password');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id);
    }

    public function testUpdatePasswordExecutesUpdate(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute');
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        $repo = new UserRepository($pdo);
        $repo->updatePassword('user-1', 'new_hash');
    }
}
