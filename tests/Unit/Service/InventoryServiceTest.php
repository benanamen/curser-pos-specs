<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Service;

use CurserPos\Domain\Item\Item;
use CurserPos\Domain\Item\ItemRepository;
use CurserPos\Service\InventoryService;
use PHPUnit\Framework\TestCase;
use PDO;

final class InventoryServiceTest extends TestCase
{
    public function testCreateItemThrowsWhenSkuExists(): void
    {
        $repo = $this->createMock(ItemRepository::class);
        $repo->method('skuExists')->with('SKU001')->willReturn(true);
        $pdo = $this->createMock(PDO::class);

        $service = new InventoryService($repo, $pdo);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("SKU 'SKU001' already exists");
        $service->createItem('SKU001', null, null, null, null, null, null, null, 10.0, 50.0, 50.0, new \DateTimeImmutable(), null, null);
    }

    public function testCreateItemSuccess(): void
    {
        $item = new Item(
            'i1',
            'SKU001',
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            10.0,
            50.0,
            50.0,
            Item::STATUS_AVAILABLE,
            new \DateTimeImmutable(),
            null,
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );
        $repo = $this->createMock(ItemRepository::class);
        $repo->method('skuExists')->willReturn(false);
        $repo->method('create')->willReturn('i1');
        $repo->method('findById')->willReturn($item);
        $pdo = $this->createMock(PDO::class);

        $service = new InventoryService($repo, $pdo);
        $result = $service->createItem('SKU001', null, null, null, null, null, null, null, 10.0, 50.0, 50.0, new \DateTimeImmutable(), null, null);
        $this->assertSame('i1', $result->id);
    }

    public function testGenerateSkuWhenNoItems(): void
    {
        $repo = $this->createMock(ItemRepository::class);
        $repo->method('search')->willReturn([]);
        $pdo = $this->createMock(PDO::class);

        $service = new InventoryService($repo, $pdo);
        $sku = $service->generateSku();
        $this->assertMatchesRegularExpression('/^IT\d{8}0001$/', $sku);
    }

    public function testUpdateItemStatusThrowsWhenInvalid(): void
    {
        $repo = $this->createMock(ItemRepository::class);
        $pdo = $this->createMock(PDO::class);

        $service = new InventoryService($repo, $pdo);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid status');
        $service->updateItemStatus('i1', 'invalid');
    }

    public function testUpdateItemStatusSuccess(): void
    {
        $repo = $this->createMock(ItemRepository::class);
        $repo->expects($this->once())->method('updateStatus')->with('i1', 'sold');
        $pdo = $this->createMock(PDO::class);

        $service = new InventoryService($repo, $pdo);
        $service->updateItemStatus('i1', 'sold');
    }

    public function testBulkUpdatePricesEmpty(): void
    {
        $repo = $this->createMock(ItemRepository::class);
        $pdo = $this->createMock(PDO::class);

        $service = new InventoryService($repo, $pdo);
        $result = $service->bulkUpdatePrices([]);
        $this->assertSame(0, $result['updated']);
    }

    public function testBulkUpdatePricesSuccess(): void
    {
        $repo = $this->createMock(ItemRepository::class);
        $repo->expects($this->once())->method('bulkUpdatePrices')->with([['id' => 'i1', 'price' => 20.0]]);
        $pdo = $this->createMock(PDO::class);

        $service = new InventoryService($repo, $pdo);
        $result = $service->bulkUpdatePrices([['id' => 'i1', 'price' => 20.0]]);
        $this->assertSame(1, $result['updated']);
    }

    public function testBulkUpdateStatusSuccess(): void
    {
        $repo = $this->createMock(ItemRepository::class);
        $repo->expects($this->once())->method('updateStatusForIds')->with(['i1'], 'available');
        $pdo = $this->createMock(PDO::class);

        $service = new InventoryService($repo, $pdo);
        $result = $service->bulkUpdateStatus(['i1'], 'available');
        $this->assertSame(1, $result['updated']);
    }
}
