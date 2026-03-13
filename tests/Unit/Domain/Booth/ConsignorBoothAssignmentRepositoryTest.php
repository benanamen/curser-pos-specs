<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Domain\Booth;

use CurserPos\Domain\Booth\ConsignorBoothAssignment;
use CurserPos\Domain\Booth\ConsignorBoothAssignmentRepository;
use PHPUnit\Framework\TestCase;
use PDO;
use PDOStatement;

final class ConsignorBoothAssignmentRepositoryTest extends TestCase
{
    public function testGetActiveByConsignorIdReturnsNullWhenNotFound(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['cons-1']));
        $stmt->method('fetch')->with(PDO::FETCH_ASSOC)->willReturn(false);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new ConsignorBoothAssignmentRepository($pdo);
        $this->assertNull($repo->getActiveByConsignorId('cons-1'));
    }

    public function testGetActiveByConsignorIdReturnsAssignmentWhenFound(): void
    {
        $row = [
            'id' => 'a1',
            'consignor_id' => 'cons-1',
            'booth_id' => 'booth-1',
            'started_at' => '2025-01-01',
            'ended_at' => null,
            'monthly_rent' => 150.0,
            'created_at' => '2025-01-01 00:00:00',
            'updated_at' => '2025-01-01 00:00:00',
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['cons-1']));
        $stmt->method('fetch')->with(PDO::FETCH_ASSOC)->willReturn($row);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new ConsignorBoothAssignmentRepository($pdo);
        $a = $repo->getActiveByConsignorId('cons-1');
        $this->assertInstanceOf(ConsignorBoothAssignment::class, $a);
        $this->assertSame('a1', $a->id);
        $this->assertNull($a->endedAt);
    }

    public function testGetActiveByConsignorIdWithEndedAtSet(): void
    {
        $row = [
            'id' => 'a2',
            'consignor_id' => 'cons-1',
            'booth_id' => 'booth-1',
            'started_at' => '2025-01-01',
            'ended_at' => '2025-02-01',
            'monthly_rent' => 100.0,
            'created_at' => '2025-01-01 00:00:00',
            'updated_at' => '2025-01-01 00:00:00',
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['cons-1']));
        $stmt->method('fetch')->with(PDO::FETCH_ASSOC)->willReturn($row);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new ConsignorBoothAssignmentRepository($pdo);
        $a = $repo->getActiveByConsignorId('cons-1');
        $this->assertInstanceOf(\DateTimeImmutable::class, $a->endedAt);
        $this->assertSame('2025-02-01', $a->endedAt->format('Y-m-d'));
    }

    public function testGetByConsignorIdReturnsList(): void
    {
        $rows = [
            ['id' => 'a1', 'consignor_id' => 'cons-1', 'booth_id' => 'b1', 'started_at' => '2025-01-01', 'ended_at' => null, 'monthly_rent' => 100.0, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['cons-1']));
        $stmt->method('fetchAll')->with(PDO::FETCH_ASSOC)->willReturn($rows);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new ConsignorBoothAssignmentRepository($pdo);
        $list = $repo->getByConsignorId('cons-1');
        $this->assertCount(1, $list);
        $this->assertInstanceOf(ConsignorBoothAssignment::class, $list[0]);
    }

    public function testGetActiveByBoothIdReturnsList(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['booth-1']));
        $stmt->method('fetchAll')->with(PDO::FETCH_ASSOC)->willReturn([]);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new ConsignorBoothAssignmentRepository($pdo);
        $this->assertSame([], $repo->getActiveByBoothId('booth-1'));
    }

    public function testAssignExecutesAndReturnsId(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with($this->callback(function (array $params): bool {
            return $params[1] === 'cons-1' && $params[2] === 'booth-1' && (float) $params[4] === 200.0 && $params[3] === '2025-01-01';
        }));

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new ConsignorBoothAssignmentRepository($pdo);
        $start = new \DateTimeImmutable('2025-01-01');
        $id = $repo->assign('cons-1', 'booth-1', 200.0, $start);
        $this->assertNotEmpty($id);
    }

    public function testEndAssignmentExecutes(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with($this->callback(function (array $params): bool {
            return $params[0] === '2025-02-01' && $params[2] === 'cons-1';
        }));

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new ConsignorBoothAssignmentRepository($pdo);
        $repo->endAssignment('cons-1', new \DateTimeImmutable('2025-02-01'));
    }
}
