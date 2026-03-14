<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Domain\Register;

use CurserPos\Domain\Register\RegisterCashDropRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use PDO;
use PDOStatement;

#[AllowMockObjectsWithoutExpectations]
final class RegisterCashDropRepositoryTest extends TestCase
{
    public function testRecordExecutesAndReturnsId(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with($this->callback(function (array $params): bool {
            return $params[1] === 'reg-1' && (float) $params[2] === 100.0 && count($params) === 4;
        }));

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new RegisterCashDropRepository($pdo);
        $id = $repo->record('reg-1', 100.0);
        $this->assertNotEmpty($id);
    }

    public function testTotalByRegisterSinceReturnsSum(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['reg-1', '2025-01-01 00:00:00']));
        $stmt->method('fetch')->with(PDO::FETCH_NUM)->willReturn([250.5]);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new RegisterCashDropRepository($pdo);
        $this->assertSame(250.5, $repo->totalByRegisterSince('reg-1', '2025-01-01 00:00:00'));
    }

    public function testTotalByRegisterSinceReturnsZeroWhenNoRow(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute');
        $stmt->method('fetch')->with(PDO::FETCH_NUM)->willReturn(false);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new RegisterCashDropRepository($pdo);
        $this->assertSame(0.0, $repo->totalByRegisterSince('reg-1', '2025-01-01'));
    }

    public function testListByRegisterReturnsRows(): void
    {
        $rows = [['id' => 'cd1', 'register_id' => 'reg-1', 'amount' => 50.0, 'created_at' => '2025-01-01 00:00:00']];
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['reg-1', 50]));
        $stmt->method('fetchAll')->with(PDO::FETCH_ASSOC)->willReturn($rows);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new RegisterCashDropRepository($pdo);
        $this->assertSame($rows, $repo->listByRegister('reg-1', 50));
    }
}
