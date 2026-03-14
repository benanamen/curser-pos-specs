<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Domain\User;

use CurserPos\Domain\User\InviteTokenRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use PDO;
use PDOStatement;

#[AllowMockObjectsWithoutExpectations]
final class InviteTokenRepositoryTest extends TestCase
{
    public function testCreateReturnsToken(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute');

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new InviteTokenRepository($pdo);
        $token = $repo->create('tenant-1', 'user@test.com', 'role-1');
        $this->assertIsString($token);
        $this->assertSame(64, strlen($token));
    }

    public function testConsumeTokenReturnsNullWhenInvalid(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute');
        $stmt->method('fetch')->willReturn(false);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new InviteTokenRepository($pdo);
        $result = $repo->consumeToken('invalid');
        $this->assertNull($result);
    }

    public function testConsumeTokenReturnsDataWhenValid(): void
    {
        $fetchStmt = $this->createMock(PDOStatement::class);
        $fetchStmt->method('execute');
        $fetchStmt->method('fetch')->willReturn([
            'tenant_id' => 't1',
            'email' => 'user@test.com',
            'role_id' => 'r1',
        ]);
        $deleteStmt = $this->createMock(PDOStatement::class);
        $deleteStmt->method('execute');

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturnCallback(function (string $sql) use ($fetchStmt, $deleteStmt) {
            return str_contains($sql, 'DELETE') ? $deleteStmt : $fetchStmt;
        });

        $repo = new InviteTokenRepository($pdo);
        $result = $repo->consumeToken('valid-64-char-token-1234567890123456789012345678901234567890123456789012345678901234');
        $this->assertSame('t1', $result['tenant_id']);
        $this->assertSame('user@test.com', $result['email']);
        $this->assertSame('r1', $result['role_id']);
    }
}
