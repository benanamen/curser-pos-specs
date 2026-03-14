<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Domain\Item;

use CurserPos\Domain\Item\Item;
use CurserPos\Domain\Item\ItemRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use PDO;
use PDOStatement;

#[AllowMockObjectsWithoutExpectations]
final class ItemRepositoryTest extends TestCase
{
    private function itemRow(string $id = 'item-1'): array
    {
        return [
            'id' => $id,
            'sku' => 'SKU001',
            'barcode' => '123',
            'consignor_id' => 'cons-1',
            'category_id' => 'cat-1',
            'location_id' => 'loc-1',
            'description' => 'Desc',
            'size' => 'M',
            'condition' => 'good',
            'price' => 25.0,
            'store_share_pct' => 50.0,
            'consignor_share_pct' => 50.0,
            'status' => Item::STATUS_AVAILABLE,
            'quantity' => 1,
            'intake_date' => '2025-01-01',
            'expiry_date' => '2025-06-01',
            'created_at' => '2025-01-01 00:00:00',
            'updated_at' => '2025-01-01 00:00:00',
        ];
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['bad-id']));
        $stmt->method('fetch')->with(PDO::FETCH_ASSOC)->willReturn(false);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new ItemRepository($pdo);
        $this->assertNull($repo->findById('bad-id'));
    }

    public function testFindByIdReturnsItemWhenFound(): void
    {
        $row = $this->itemRow();
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['item-1']));
        $stmt->method('fetch')->with(PDO::FETCH_ASSOC)->willReturn($row);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new ItemRepository($pdo);
        $item = $repo->findById('item-1');
        $this->assertInstanceOf(Item::class, $item);
        $this->assertSame('item-1', $item->id);
        $this->assertSame(Item::STATUS_AVAILABLE, $item->status);
        $this->assertSame(1, $item->quantity);
    }

    public function testFindBySkuReturnsNullWhenNotFound(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['SKU999']));
        $stmt->method('fetch')->with(PDO::FETCH_ASSOC)->willReturn(false);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new ItemRepository($pdo);
        $this->assertNull($repo->findBySku('SKU999'));
    }

    public function testFindByBarcodeExecutesWithStatus(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['123456', Item::STATUS_AVAILABLE]));
        $stmt->method('fetch')->with(PDO::FETCH_ASSOC)->willReturn(false);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new ItemRepository($pdo);
        $this->assertNull($repo->findByBarcode('123456'));
    }

    public function testSearchWithNoParams(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo([]));
        $stmt->method('fetchAll')->with(PDO::FETCH_ASSOC)->willReturn([]);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new ItemRepository($pdo);
        $this->assertSame([], $repo->search());
    }

    public function testSearchWithAllParams(): void
    {
        $row = $this->itemRow();
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->callback(function (array $params): bool {
            return count($params) === 6 && $params[0] === '%shirt%' && $params[3] === 'available' && $params[4] === 'cons-1' && $params[5] === 'cat-1';
        }));
        $stmt->method('fetchAll')->with(PDO::FETCH_ASSOC)->willReturn([$row]);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new ItemRepository($pdo);
        $list = $repo->search('shirt', 'available', 'cons-1', 'cat-1', 20);
        $this->assertCount(1, $list);
        $this->assertInstanceOf(Item::class, $list[0]);
    }

    public function testSkuExistsReturnsTrueWhenFound(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['SKU1']));
        $stmt->method('fetch')->willReturn(['1']);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new ItemRepository($pdo);
        $this->assertTrue($repo->skuExists('SKU1'));
    }

    public function testSkuExistsWithExcludeId(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with($this->equalTo(['SKU1', 'item-1']));
        $stmt->method('fetch')->willReturn(false);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new ItemRepository($pdo);
        $this->assertFalse($repo->skuExists('SKU1', 'item-1'));
    }

    public function testCreateExecutesAndReturnsId(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with($this->callback(function (array $params): bool {
            return $params[1] === 'SKU1' && (float) $params[9] === 25.0 && $params[12] === Item::STATUS_AVAILABLE && $params[13] === 1 && count($params) === 18;
        }));

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new ItemRepository($pdo);
        $id = $repo->create('SKU1', null, 'cons-1', 'cat-1', null, null, null, null, 25.0, 50.0, 50.0, new \DateTimeImmutable('2025-01-01'), null);
        $this->assertNotEmpty($id);
    }

    public function testCreateWithQuantity(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with($this->callback(function (array $params): bool {
            return $params[13] === 5;
        }));

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new ItemRepository($pdo);
        $id = $repo->create('SKU1', null, 'cons-1', 'cat-1', null, null, null, null, 25.0, 50.0, 50.0, new \DateTimeImmutable('2025-01-01'), null, 5);
        $this->assertNotEmpty($id);
    }

    public function testUpdateExecutes(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with($this->callback(function (array $params): bool {
            return $params[0] === 'SKU2' && (float) $params[8] === 30.0 && $params[13] === 'item-1' && count($params) === 14;
        }));

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new ItemRepository($pdo);
        $repo->update('item-1', 'SKU2', null, null, null, null, null, null, null, 30.0, 50.0, 50.0, new \DateTimeImmutable('2025-06-01'), null);
    }

    public function testUpdateWithQuantity(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with($this->callback(function (array $params): bool {
            return $params[12] === 10 && $params[14] === 'item-1' && count($params) === 15;
        }));

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new ItemRepository($pdo);
        $repo->update('item-1', 'SKU2', null, null, null, null, null, null, null, 30.0, 50.0, 50.0, new \DateTimeImmutable('2025-06-01'), 10);
    }

    public function testUpdatePriceExecutes(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with($this->callback(function (array $params): bool {
            return (float) $params[0] === 40.0 && $params[2] === 'item-1';
        }));

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new ItemRepository($pdo);
        $repo->updatePrice('item-1', 40.0);
    }

    public function testUpdateStatusExecutes(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with($this->callback(function (array $params): bool {
            return $params[0] === Item::STATUS_SOLD && $params[2] === 'item-1' && count($params) === 3;
        }));

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new ItemRepository($pdo);
        $repo->updateStatus('item-1', Item::STATUS_SOLD);
    }

    public function testUpdateStatusForIdsNoopWhenEmpty(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->never())->method('prepare');

        $repo = new ItemRepository($pdo);
        $repo->updateStatusForIds([], Item::STATUS_SOLD);
    }

    public function testUpdateStatusForIdsExecutes(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with($this->callback(function (array $params): bool {
            return $params[0] === Item::STATUS_SOLD && in_array('item-1', $params, true) && in_array('item-2', $params, true);
        }));

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new ItemRepository($pdo);
        $repo->updateStatusForIds(['item-1', 'item-2'], Item::STATUS_SOLD);
    }

    public function testBulkUpdatePricesExecutes(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->exactly(2))->method('execute');

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new ItemRepository($pdo);
        $repo->bulkUpdatePrices([['id' => 'i1', 'price' => 10.0], ['id' => 'i2', 'price' => 20.0]]);
    }

    public function testCountActiveByConsignorReturnsCount(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['cons-1', Item::STATUS_AVAILABLE]));
        $stmt->method('fetchColumn')->willReturn(5);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new ItemRepository($pdo);
        $this->assertSame(5, $repo->countActiveByConsignor('cons-1'));
    }

    public function testDecreaseQuantityExecutes(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->exactly(2))->method('execute')->with($this->callback(function (array $params): bool {
            return ($params[0] === 2 && $params[2] === 'item-1') || ($params[0] === Item::STATUS_SOLD && $params[1] === 'item-1');
        }));

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new ItemRepository($pdo);
        $repo->decreaseQuantity('item-1', 2);
    }

    public function testIncreaseQuantityExecutes(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with($this->callback(function (array $params): bool {
            return $params[0] === 3 && $params[1] === Item::STATUS_AVAILABLE && $params[3] === 'item-1';
        }));

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new ItemRepository($pdo);
        $repo->increaseQuantity('item-1', 3);
    }

    public function testGetAvailableQuantityReturnsValue(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['item-1']));
        $stmt->method('fetchColumn')->willReturn(7);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new ItemRepository($pdo);
        $this->assertSame(7, $repo->getAvailableQuantity('item-1'));
    }

    public function testGetAvailableQuantityReturnsZeroWhenNull(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['item-1']));
        $stmt->method('fetchColumn')->willReturn(null);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new ItemRepository($pdo);
        $this->assertSame(0, $repo->getAvailableQuantity('item-1'));
    }
}
