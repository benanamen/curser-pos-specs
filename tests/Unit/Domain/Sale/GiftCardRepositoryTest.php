<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Domain\Sale;

use CurserPos\Domain\Sale\GiftCardRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use PDO;
use PDOStatement;

#[AllowMockObjectsWithoutExpectations]
final class GiftCardRepositoryTest extends TestCase
{
    public function testFindByCodeReturnsNullWhenNotFound(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['CODE123', 'active']));
        $stmt->method('fetch')->with(PDO::FETCH_ASSOC)->willReturn(false);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new GiftCardRepository($pdo);
        $this->assertNull($repo->findByCode('CODE123'));
    }

    public function testFindByCodeTrimsCode(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with($this->equalTo(['CODE123', 'active']));
        $stmt->method('fetch')->with(PDO::FETCH_ASSOC)->willReturn(['id' => 'gc1', 'code' => 'CODE123', 'balance' => 50.0, 'status' => 'active']);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new GiftCardRepository($pdo);
        $repo->findByCode('  CODE123  ');
    }

    public function testFindByIdReturnsRowWhenFound(): void
    {
        $row = ['id' => 'gc1', 'code' => 'GC001', 'balance' => 25.0, 'status' => 'active'];
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['gc1']));
        $stmt->method('fetch')->with(PDO::FETCH_ASSOC)->willReturn($row);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new GiftCardRepository($pdo);
        $this->assertSame($row, $repo->findById('gc1'));
    }

    public function testCreateExecutesAndReturnsId(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with($this->callback(function (array $params): bool {
            return $params[1] === 'GC50' && (float) $params[2] === 50.0 && $params[3] === 'active';
        }));

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new GiftCardRepository($pdo);
        $id = $repo->create('GC50', 50.0);
        $this->assertNotEmpty($id);
    }

    public function testDeductExecutesAndSucceedsWhenRowCountOne(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute');
        $stmt->method('rowCount')->willReturn(1);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new GiftCardRepository($pdo);
        $repo->deduct('gc1', 10.0);
    }

    public function testDeductThrowsWhenRowCountNotOne(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute');
        $stmt->method('rowCount')->willReturn(0);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new GiftCardRepository($pdo);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Insufficient gift card balance or not found');
        $repo->deduct('gc1', 1000.0);
    }
}
