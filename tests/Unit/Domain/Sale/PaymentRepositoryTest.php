<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Domain\Sale;

use CurserPos\Domain\Sale\PaymentRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use PDO;
use PDOStatement;

#[AllowMockObjectsWithoutExpectations]
final class PaymentRepositoryTest extends TestCase
{
    public function testGetBySaleIdReturnsMappedRows(): void
    {
        $rows = [
            ['id' => 'pay-1', 'method' => 'cash', 'amount' => 25.0, 'reference' => null],
            ['id' => 'pay-2', 'method' => 'card', 'amount' => 25.0, 'reference' => 'ch_xyz'],
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['sale-1']));
        $stmt->method('fetchAll')->with(PDO::FETCH_ASSOC)->willReturn($rows);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new PaymentRepository($pdo);
        $result = $repo->getBySaleId('sale-1');
        $this->assertCount(2, $result);
        $this->assertSame('pay-1', $result[0]['id']);
        $this->assertSame('cash', $result[0]['method']);
        $this->assertSame(25.0, $result[0]['amount']);
        $this->assertNull($result[0]['reference']);
        $this->assertSame('ch_xyz', $result[1]['reference']);
    }

    public function testGetBySaleIdWithEmptyReference(): void
    {
        $rows = [
            ['id' => 'pay-1', 'method' => 'cash', 'amount' => 10.0, 'reference' => ''],
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['sale-1']));
        $stmt->method('fetchAll')->with(PDO::FETCH_ASSOC)->willReturn($rows);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new PaymentRepository($pdo);
        $result = $repo->getBySaleId('sale-1');
        $this->assertNull($result[0]['reference']);
    }

    public function testAddPaymentExecutesAndReturnsId(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with($this->callback(function (array $params): bool {
            return $params[1] === 'sale-1'
                && $params[2] === 'card'
                && (float) $params[3] === 50.0
                && $params[4] === 'ch_abc'
                && $params[5] === 'completed'
                && $params[6] === null;
        }));

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new PaymentRepository($pdo);
        $id = $repo->addPayment('sale-1', 'card', 50.0, 'ch_abc', null);
        $this->assertNotEmpty($id);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id);
    }

    public function testAddPaymentWithRefundOfId(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with($this->callback(function (array $params): bool {
            return $params[6] === 'pay-original';
        }));

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new PaymentRepository($pdo);
        $repo->addPayment('sale-1', 'refund', -25.0, null, 'pay-original');
    }
}
