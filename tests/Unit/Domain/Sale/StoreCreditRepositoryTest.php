<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Domain\Sale;

use CurserPos\Domain\Sale\StoreCreditRepository;
use PHPUnit\Framework\TestCase;
use PDO;
use PDOStatement;

final class StoreCreditRepositoryTest extends TestCase
{
    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['sc1', 'active']));
        $stmt->method('fetch')->with(PDO::FETCH_ASSOC)->willReturn(false);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new StoreCreditRepository($pdo);
        $this->assertNull($repo->findById('sc1'));
    }

    public function testGetByConsignorIdReturnsRows(): void
    {
        $rows = [['id' => 'sc1', 'consignor_id' => 'cons-1', 'balance' => 50.0, 'status' => 'active']];
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['cons-1', 'active']));
        $stmt->method('fetchAll')->with(PDO::FETCH_ASSOC)->willReturn($rows);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new StoreCreditRepository($pdo);
        $this->assertSame($rows, $repo->getByConsignorId('cons-1'));
    }

    public function testCreateExecutesWithNullConsignorId(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with($this->callback(function (array $params): bool {
            return $params[1] === null && (float) $params[2] === 25.0;
        }));

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new StoreCreditRepository($pdo);
        $id = $repo->create(null, 25.0);
        $this->assertNotEmpty($id);
    }

    public function testDeductThrowsWhenRowCountNotOne(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute');
        $stmt->method('rowCount')->willReturn(0);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new StoreCreditRepository($pdo);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Insufficient store credit balance or not found');
        $repo->deduct('sc1', 100.0);
    }

    public function testDeductSucceedsWhenRowCountOne(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute');
        $stmt->method('rowCount')->willReturn(1);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new StoreCreditRepository($pdo);
        $repo->deduct('sc1', 10.0);
    }
}
