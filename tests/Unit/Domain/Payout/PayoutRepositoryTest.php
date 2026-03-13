<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Domain\Payout;

use CurserPos\Domain\Payout\PayoutRepository;
use PHPUnit\Framework\TestCase;
use PDO;
use PDOStatement;

final class PayoutRepositoryTest extends TestCase
{
    public function testCreateExecutesInsertAndReturnsId(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with($this->callback(function (array $params): bool {
            return $params[1] === 'cons-1'
                && (float) $params[2] === 100.5
                && $params[3] === 'check'
                && $params[4] === 'pending'
                && $params[5] === null
                && $params[6] === null;
        }));

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new PayoutRepository($pdo);
        $id = $repo->create('cons-1', 100.5, 'check', null, null);
        $this->assertNotEmpty($id);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id);
    }

    public function testCreateWithReferenceAndMetadata(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with($this->callback(function (array $params): bool {
            return $params[5] === 'ref-123' && $params[6] !== null && str_contains($params[6], 'last4');
        }));

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new PayoutRepository($pdo);
        $repo->create('cons-1', 50.0, 'ach', 'ref-123', ['last4' => '1234', 'bank_name' => 'Test']);
    }

    public function testMarkProcessedExecutes(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with($this->callback(function (array $params): bool {
            return $params[0] === 'processed' && $params[2] === 'payout-1' && count($params) === 3;
        }));

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new PayoutRepository($pdo);
        $repo->markProcessed('payout-1');
    }

    public function testListByConsignorReturnsRows(): void
    {
        $rows = [
            ['id' => 'p1', 'consignor_id' => 'cons-1', 'amount' => 100.0, 'method' => 'check', 'status' => 'processed'],
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['cons-1', 50]));
        $stmt->method('fetchAll')->with(PDO::FETCH_ASSOC)->willReturn($rows);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new PayoutRepository($pdo);
        $result = $repo->listByConsignor('cons-1', 50);
        $this->assertCount(1, $result);
        $this->assertSame('p1', $result[0]['id']);
    }

    public function testListByConsignorUsesDefaultLimit(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with($this->equalTo(['cons-1', 50]));
        $stmt->method('fetchAll')->with(PDO::FETCH_ASSOC)->willReturn([]);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new PayoutRepository($pdo);
        $repo->listByConsignor('cons-1');
    }
}
