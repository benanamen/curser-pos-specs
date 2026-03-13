<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Domain\Sale;

use CurserPos\Domain\Sale\HeldSaleRepository;
use PHPUnit\Framework\TestCase;
use PDO;
use PDOStatement;

final class HeldSaleRepositoryTest extends TestCase
{
    public function testCreateExecutesAndReturnsId(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with($this->callback(function (array $params): bool {
            return $params[2] === 'user-1' && count($params) === 4;
        }));

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new HeldSaleRepository($pdo);
        $id = $repo->create('user-1', ['cart' => [], 'payments' => []]);
        $this->assertNotEmpty($id);
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['bad-id']));
        $stmt->method('fetch')->with(PDO::FETCH_ASSOC)->willReturn(false);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new HeldSaleRepository($pdo);
        $this->assertNull($repo->findById('bad-id'));
    }

    public function testFindByIdReturnsRowWithDecodedCartData(): void
    {
        $row = [
            'id' => 'held-1',
            'cart_data' => '{"cart":[{"item_id":"i1"}],"payments":[]}',
            'user_id' => 'user-1',
            'created_at' => '2025-01-01 00:00:00',
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['held-1']));
        $stmt->method('fetch')->with(PDO::FETCH_ASSOC)->willReturn($row);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new HeldSaleRepository($pdo);
        $result = $repo->findById('held-1');
        $this->assertIsArray($result);
        $this->assertSame('held-1', $result['id']);
        $this->assertIsArray($result['cart_data']);
        $this->assertArrayHasKey('cart', $result['cart_data']);
    }

    public function testListByUserReturnsRowsWithDecodedCartData(): void
    {
        $rows = [
            ['id' => 'h1', 'cart_data' => '{}', 'user_id' => 'user-1', 'created_at' => '2025-01-01 00:00:00'],
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['user-1', 200]));
        $stmt->method('fetchAll')->with(PDO::FETCH_ASSOC)->willReturn($rows);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new HeldSaleRepository($pdo);
        $result = $repo->listByUser('user-1', 200);
        $this->assertCount(1, $result);
        $this->assertIsArray($result[0]['cart_data']);
    }

    public function testDeleteExecutes(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with($this->equalTo(['held-1']));

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new HeldSaleRepository($pdo);
        $repo->delete('held-1');
    }
}
