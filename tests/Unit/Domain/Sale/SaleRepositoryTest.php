<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Domain\Sale;

use CurserPos\Domain\Sale\Sale;
use CurserPos\Domain\Sale\SaleRepository;
use PHPUnit\Framework\TestCase;
use PDO;
use PDOStatement;

final class SaleRepositoryTest extends TestCase
{
    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['bad-id']));
        $stmt->method('fetch')->with(PDO::FETCH_ASSOC)->willReturn(false);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new SaleRepository($pdo);
        $this->assertNull($repo->findById('bad-id'));
    }

    public function testFindByIdReturnsSaleWhenFound(): void
    {
        $row = [
            'id' => 'sale-1',
            'register_id' => 'reg-1',
            'location_id' => 'loc-1',
            'user_id' => 'user-1',
            'sale_number' => 'S001',
            'subtotal' => 50.0,
            'discount_amount' => 0.0,
            'tax_amount' => 4.0,
            'total' => 54.0,
            'status' => Sale::STATUS_COMPLETED,
            'created_at' => '2025-01-01 00:00:00',
            'updated_at' => '2025-01-01 00:00:00',
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['sale-1']));
        $stmt->method('fetch')->with(PDO::FETCH_ASSOC)->willReturn($row);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new SaleRepository($pdo);
        $sale = $repo->findById('sale-1');
        $this->assertInstanceOf(Sale::class, $sale);
        $this->assertSame('sale-1', $sale->id);
        $this->assertSame(Sale::STATUS_COMPLETED, $sale->status);
    }

    public function testGenerateSaleNumberReturnsFirstWhenNoExisting(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute');
        $stmt->method('fetch')->with(PDO::FETCH_ASSOC)->willReturn(false);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new SaleRepository($pdo);
        $num = $repo->generateSaleNumber();
        $this->assertMatchesRegularExpression('/^S\d{8}0001$/', $num);
    }

    public function testGenerateSaleNumberIncrementsWhenExisting(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute');
        $stmt->method('fetch')->with(PDO::FETCH_ASSOC)->willReturn(['sale_number' => 'S202501010042']);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new SaleRepository($pdo);
        $num = $repo->generateSaleNumber();
        $this->assertMatchesRegularExpression('/^S\d{8}0043$/', $num);
    }

    public function testCreateExecutesInsert(): void
    {
        $selectStmt = $this->createMock(PDOStatement::class);
        $selectStmt->method('execute');
        $selectStmt->method('fetch')->with(PDO::FETCH_ASSOC)->willReturn(false);

        $insertStmt = $this->createMock(PDOStatement::class);
        $insertStmt->expects($this->once())->method('execute')->with($this->callback(function (array $params): bool {
            return $params[1] === 'reg-1' && $params[2] === 'loc-1' && $params[3] === 'user-1' && (float) $params[5] === 50.0 && (float) $params[8] === 54.0 && $params[9] === Sale::STATUS_COMPLETED;
        }));

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturnOnConsecutiveCalls($selectStmt, $insertStmt);

        $repo = new SaleRepository($pdo);
        $id = $repo->create('reg-1', 'loc-1', 'user-1', 50.0, 0.0, 4.0, 54.0);
        $this->assertNotEmpty($id);
    }

    public function testAddSaleItemExecutes(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with($this->callback(function (array $params): bool {
            return $params[1] === 'sale-1' && $params[2] === 'item-1' && $params[3] === 'cons-1' && $params[4] === 1 && (float) $params[5] === 25.0 && (float) $params[8] === 12.5 && (float) $params[9] === 12.5;
        }));

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new SaleRepository($pdo);
        $id = $repo->addSaleItem('sale-1', 'item-1', 'cons-1', 1, 25.0, 0.0, 0.0, 12.5, 12.5);
        $this->assertNotEmpty($id);
    }

    public function testVoidSaleExecutes(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with($this->callback(function (array $params): bool {
            return $params[0] === Sale::STATUS_VOIDED && $params[2] === 'sale-1';
        }));

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new SaleRepository($pdo);
        $repo->voidSale('sale-1');
    }

    public function testMarkRefundedExecutes(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with($this->callback(function (array $params): bool {
            return $params[0] === Sale::STATUS_REFUNDED && $params[2] === 'sale-1';
        }));

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new SaleRepository($pdo);
        $repo->markRefunded('sale-1');
    }

    public function testListWithNoFilters(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo([]));
        $stmt->method('fetchAll')->with(PDO::FETCH_ASSOC)->willReturn([]);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new SaleRepository($pdo);
        $this->assertSame([], $repo->list());
    }

    public function testListWithDateAndRegisterFilters(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with($this->equalTo(['2025-01-01', '2025-01-31 23:59:59', 'reg-1']));
        $stmt->method('fetchAll')->with(PDO::FETCH_ASSOC)->willReturn([]);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new SaleRepository($pdo);
        $repo->list('2025-01-01', '2025-01-31', 'reg-1', 50);
    }

    public function testGetSaleItemsReturnsRows(): void
    {
        $rows = [['id' => 'si1', 'sale_id' => 'sale-1', 'item_id' => 'item-1', 'consignor_id' => 'cons-1', 'quantity' => 1, 'unit_price' => 25.0, 'store_share' => 12.5, 'consignor_share' => 12.5]];
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['sale-1']));
        $stmt->method('fetchAll')->with(PDO::FETCH_ASSOC)->willReturn($rows);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new SaleRepository($pdo);
        $this->assertSame($rows, $repo->getSaleItems('sale-1'));
    }

    public function testGetSalesByConsignorExecutes(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['cons-1', Sale::STATUS_COMPLETED, 50]));
        $stmt->method('fetchAll')->with(PDO::FETCH_ASSOC)->willReturn([]);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new SaleRepository($pdo);
        $this->assertSame([], $repo->getSalesByConsignor('cons-1', 50));
    }
}
