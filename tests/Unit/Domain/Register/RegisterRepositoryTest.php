<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Domain\Register;

use CurserPos\Domain\Register\Register;
use CurserPos\Domain\Register\RegisterRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use PDO;
use PDOStatement;

#[AllowMockObjectsWithoutExpectations]
final class RegisterRepositoryTest extends TestCase
{
    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['bad-id']));
        $stmt->method('fetch')->with(PDO::FETCH_ASSOC)->willReturn(false);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new RegisterRepository($pdo);
        $this->assertNull($repo->findById('bad-id'));
    }

    public function testFindByIdReturnsRegisterWhenFound(): void
    {
        $row = [
            'id' => 'reg-1',
            'location_id' => 'loc-1',
            'register_id' => 'R1',
            'assigned_user_id' => 'user-1',
            'status' => Register::STATUS_OPEN,
            'opening_cash' => 100.0,
            'closing_cash' => null,
            'opened_at' => '2025-01-01 00:00:00',
            'closed_at' => null,
            'created_at' => '2025-01-01 00:00:00',
            'updated_at' => '2025-01-01 00:00:00',
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['reg-1']));
        $stmt->method('fetch')->with(PDO::FETCH_ASSOC)->willReturn($row);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new RegisterRepository($pdo);
        $reg = $repo->findById('reg-1');
        $this->assertInstanceOf(Register::class, $reg);
        $this->assertSame('reg-1', $reg->id);
        $this->assertSame(Register::STATUS_OPEN, $reg->status);
        $this->assertSame(100.0, $reg->openingCash);
        $this->assertNull($reg->closingCash);
    }

    public function testFindAllReturnsList(): void
    {
        $rows = [
            ['id' => 'r1', 'location_id' => 'loc-1', 'register_id' => 'R1', 'assigned_user_id' => null, 'status' => 'closed', 'opening_cash' => 0, 'closing_cash' => null, 'opened_at' => null, 'closed_at' => null, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetchAll')->with(PDO::FETCH_ASSOC)->willReturn($rows);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('query')->willReturn($stmt);

        $repo = new RegisterRepository($pdo);
        $list = $repo->findAll();
        $this->assertCount(1, $list);
        $this->assertInstanceOf(Register::class, $list[0]);
    }

    public function testCreateExecutes(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with($this->callback(function (array $params): bool {
            return $params[1] === 'loc-1' && $params[2] === 'R1' && $params[3] === Register::STATUS_CLOSED;
        }));

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new RegisterRepository($pdo);
        $id = $repo->create('loc-1', 'R1');
        $this->assertNotEmpty($id);
    }

    public function testOpenExecutes(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with($this->callback(function (array $params): bool {
            return $params[0] === Register::STATUS_OPEN && $params[1] === 'user-1' && (float) $params[2] === 50.0 && $params[5] === 'reg-1';
        }));

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new RegisterRepository($pdo);
        $repo->open('reg-1', 'user-1', 50.0);
    }

    public function testCloseExecutes(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with($this->callback(function (array $params): bool {
            return $params[0] === Register::STATUS_CLOSED && (float) $params[1] === 150.0 && $params[4] === 'reg-1';
        }));

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new RegisterRepository($pdo);
        $repo->close('reg-1', 150.0);
    }
}
