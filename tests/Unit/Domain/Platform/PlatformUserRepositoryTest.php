<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Domain\Platform;

use CurserPos\Domain\Platform\PlatformUserRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use PDO;
use PDOStatement;

#[AllowMockObjectsWithoutExpectations]
final class PlatformUserRepositoryTest extends TestCase
{
    public function testFindByEmailReturnsNullWhenNotFound(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute');
        $stmt->method('fetch')->willReturn(false);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new PlatformUserRepository($pdo);
        $result = $repo->findByEmail('nobody@platform.com');
        $this->assertNull($result);
    }

    public function testFindByEmailReturnsRowWhenFound(): void
    {
        $row = ['id' => 'p1', 'email' => 'admin@platform.com', 'password_hash' => 'hash'];
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute');
        $stmt->method('fetch')->willReturn($row);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new PlatformUserRepository($pdo);
        $result = $repo->findByEmail('admin@platform.com');
        $this->assertSame($row, $result);
    }

    public function testGetPasswordHashReturnsHash(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute');
        $stmt->method('fetch')->willReturn(['password_hash' => 'stored_hash']);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new PlatformUserRepository($pdo);
        $result = $repo->getPasswordHash('p1');
        $this->assertSame('stored_hash', $result);
    }

    public function testGetPasswordHashReturnsEmptyWhenNotFound(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute');
        $stmt->method('fetch')->willReturn(false);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new PlatformUserRepository($pdo);
        $result = $repo->getPasswordHash('p1');
        $this->assertSame('', $result);
    }
}
