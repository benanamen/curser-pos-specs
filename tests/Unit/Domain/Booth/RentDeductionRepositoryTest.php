<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Domain\Booth;

use CurserPos\Domain\Booth\RentDeductionRepository;
use PHPUnit\Framework\TestCase;
use PDO;
use PDOStatement;

final class RentDeductionRepositoryTest extends TestCase
{
    public function testGetLastDeductionDateReturnsNullWhenNotFound(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['cons-1']));
        $stmt->method('fetch')->with(PDO::FETCH_ASSOC)->willReturn(false);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new RentDeductionRepository($pdo);
        $this->assertNull($repo->getLastDeductionDate('cons-1'));
    }

    public function testGetLastDeductionDateReturnsDateWhenFound(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['cons-1']));
        $stmt->method('fetch')->with(PDO::FETCH_ASSOC)->willReturn(['period_end' => '2025-01-31']);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new RentDeductionRepository($pdo);
        $date = $repo->getLastDeductionDate('cons-1');
        $this->assertInstanceOf(\DateTimeImmutable::class, $date);
        $this->assertSame('2025-01-31', $date->format('Y-m-d'));
    }

    public function testRecordExecutesAndReturnsId(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with($this->callback(function (array $params): bool {
            return $params[1] === 'cons-1' && (float) $params[2] === 100.0 && $params[3] === '2025-01-01' && $params[4] === '2025-01-31' && $params[5] === null;
        }));

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new RentDeductionRepository($pdo);
        $start = new \DateTimeImmutable('2025-01-01');
        $end = new \DateTimeImmutable('2025-01-31');
        $id = $repo->record('cons-1', 100.0, $start, $end, null);
        $this->assertNotEmpty($id);
    }

    public function testListByConsignorReturnsRows(): void
    {
        $rows = [['id' => 'rd1', 'consignor_id' => 'cons-1', 'amount' => 100.0, 'period_start' => '2025-01-01', 'period_end' => '2025-01-31', 'payout_id' => null, 'created_at' => '2025-01-15 00:00:00']];
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['cons-1', 50]));
        $stmt->method('fetchAll')->with(PDO::FETCH_ASSOC)->willReturn($rows);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new RentDeductionRepository($pdo);
        $this->assertSame($rows, $repo->listByConsignor('cons-1', 50));
    }

    public function testSumCollectedWithNoParamsUsesQuery(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetch')->with(PDO::FETCH_NUM)->willReturn([500.0]);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('query')->willReturn($stmt);

        $repo = new RentDeductionRepository($pdo);
        $this->assertSame(500.0, $repo->sumCollected());
    }

    public function testSumCollectedWithDateRangeUsesPrepare(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['2025-01-01', '2025-01-31']));
        $stmt->method('fetch')->with(PDO::FETCH_NUM)->willReturn([250.0]);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new RentDeductionRepository($pdo);
        $this->assertSame(250.0, $repo->sumCollected('2025-01-01', '2025-01-31'));
    }

    public function testSumCollectedReturnsZeroWhenNoRow(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetch')->with(PDO::FETCH_NUM)->willReturn(false);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('query')->willReturn($stmt);

        $repo = new RentDeductionRepository($pdo);
        $this->assertSame(0.0, $repo->sumCollected());
    }
}
