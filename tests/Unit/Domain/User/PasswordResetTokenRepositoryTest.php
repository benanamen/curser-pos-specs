<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Domain\User;

use CurserPos\Domain\User\PasswordResetTokenRepository;
use PHPUnit\Framework\TestCase;
use PDO;
use PDOStatement;

final class PasswordResetTokenRepositoryTest extends TestCase
{
    public function testCreateTokenReturnsToken(): void
    {
        $deleteStmt = $this->createMock(PDOStatement::class);
        $deleteStmt->method('execute');
        $insertStmt = $this->createMock(PDOStatement::class);
        $insertStmt->method('execute');

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturnCallback(function (string $sql) use ($deleteStmt, $insertStmt) {
            return str_contains($sql, 'DELETE') ? $deleteStmt : $insertStmt;
        });

        $repo = new PasswordResetTokenRepository($pdo);
        $token = $repo->createToken('123e4567-e89b-12d3-a456-426614174000');
        $this->assertIsString($token);
        $this->assertSame(64, strlen($token));
        $this->assertMatchesRegularExpression('/^[a-f0-9]+$/', $token);
    }

    public function testConsumeTokenReturnsNullWhenNotFound(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute');
        $stmt->method('fetch')->willReturn(false);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new PasswordResetTokenRepository($pdo);
        $result = $repo->consumeToken('invalid-token');
        $this->assertNull($result);
    }

    public function testConsumeTokenReturnsUserIdWhenValid(): void
    {
        $fetchStmt = $this->createMock(PDOStatement::class);
        $fetchStmt->method('execute');
        $fetchStmt->method('fetch')->willReturn(['user_id' => '123e4567-e89b-12d3-a456-426614174000']);
        $deleteStmt = $this->createMock(PDOStatement::class);
        $deleteStmt->method('execute');

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturnCallback(function (string $sql) use ($fetchStmt, $deleteStmt) {
            return str_contains($sql, 'DELETE') ? $deleteStmt : $fetchStmt;
        });

        $repo = new PasswordResetTokenRepository($pdo);
        $result = $repo->consumeToken('valid-64-char-hex-token-1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcd');
        $this->assertIsArray($result);
        $this->assertArrayHasKey('user_id', $result);
        $this->assertSame('123e4567-e89b-12d3-a456-426614174000', $result['user_id']);
    }
}
