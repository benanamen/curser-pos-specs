<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Domain\Sale;

use CurserPos\Domain\Sale\ItemHoldRepository;
use PHPUnit\Framework\TestCase;
use PDO;
use PDOStatement;

final class ItemHoldRepositoryTest extends TestCase
{
    public function testReserveItemsNoopWhenEmpty(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->never())->method('prepare');

        $repo = new ItemHoldRepository($pdo);
        $repo->reserveItems('held-1', 'user-1', []);
    }

    public function testReserveItemsExecutesForEachItem(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->exactly(2))->method('execute')->with($this->callback(function (array $params): bool {
            return $params[1] === 'held-1' && $params[2] === 'user-1' && in_array($params[0], ['item-1', 'item-2'], true) && $params[3] === 1;
        }));

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new ItemHoldRepository($pdo);
        $repo->reserveItems('held-1', 'user-1', [['item_id' => 'item-1', 'quantity' => 1], ['item_id' => 'item-2', 'quantity' => 1]]);
    }

    public function testReserveItemsAggregatesQuantityByItem(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with($this->callback(function (array $params): bool {
            return $params[0] === 'item-1' && $params[1] === 'held-1' && $params[2] === 'user-1' && $params[3] === 5 && count($params) === 5;
        }));

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new ItemHoldRepository($pdo);
        $repo->reserveItems('held-1', 'user-1', [['item_id' => 'item-1', 'quantity' => 2], ['item_id' => 'item-1', 'quantity' => 3]]);
    }

    public function testGetQuantityHeldReturnsSum(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['item-1']));
        $stmt->method('fetchColumn')->willReturn(4);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new ItemHoldRepository($pdo);
        $this->assertSame(4, $repo->getQuantityHeld('item-1'));
    }

    public function testGetQuantityHeldReturnsZeroWhenNull(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['item-1']));
        $stmt->method('fetchColumn')->willReturn(false);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new ItemHoldRepository($pdo);
        $this->assertSame(0, $repo->getQuantityHeld('item-1'));
    }

    public function testIsReservedByHoldReturnsTrueWhenFound(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['item-1', 'held-1']));
        $stmt->method('fetchColumn')->willReturn('1');

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new ItemHoldRepository($pdo);
        $this->assertTrue($repo->isReservedByHold('item-1', 'held-1'));
    }

    public function testIsReservedByHoldReturnsFalseWhenNotFound(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['item-1', 'held-1']));
        $stmt->method('fetchColumn')->willReturn(false);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new ItemHoldRepository($pdo);
        $this->assertFalse($repo->isReservedByHold('item-1', 'held-1'));
    }

    public function testListItemIdsByHoldReturnsFilteredIds(): void
    {
        $rows = [['item_id' => 'i1'], ['item_id' => ''], ['item_id' => 'i2']];
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['held-1']));
        $stmt->method('fetchAll')->with(PDO::FETCH_ASSOC)->willReturn($rows);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new ItemHoldRepository($pdo);
        $ids = $repo->listItemIdsByHold('held-1');
        $this->assertSame(['i1', 'i2'], $ids);
    }

    public function testDeleteByHoldExecutes(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with($this->equalTo(['held-1']));

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new ItemHoldRepository($pdo);
        $repo->deleteByHold('held-1');
    }
}
