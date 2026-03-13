<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Domain\Plan;

use CurserPos\Domain\Plan\PlanRepository;
use PHPUnit\Framework\TestCase;
use PDO;
use PDOStatement;

final class PlanRepositoryTest extends TestCase
{
    public function testListReturnsPlansWithJsonDecodedFeatures(): void
    {
        $rows = [
            [
                'id' => 'plan-1',
                'name' => 'Basic',
                'tier' => 'lite',
                'item_limit' => 1000,
                'features' => '{"vendor_portal":false}',
            ],
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetchAll')->with(PDO::FETCH_ASSOC)->willReturn($rows);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('query')->willReturn($stmt);

        $repo = new PlanRepository($pdo);
        $result = $repo->list();
        $this->assertCount(1, $result);
        $this->assertSame('plan-1', $result[0]['id']);
        $this->assertSame('Basic', $result[0]['name']);
        $this->assertSame('lite', $result[0]['tier']);
        $this->assertSame(1000, $result[0]['item_limit']);
        $this->assertIsArray($result[0]['features']);
        $this->assertFalse($result[0]['features']['vendor_portal'] ?? true);
    }

    public function testListWithArrayFeaturesUsesAsIs(): void
    {
        $rows = [
            [
                'id' => 'plan-2',
                'name' => 'Pro',
                'tier' => 'pro',
                'item_limit' => 9999,
                'features' => ['vendor_portal' => true],
            ],
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetchAll')->with(PDO::FETCH_ASSOC)->willReturn($rows);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('query')->willReturn($stmt);

        $repo = new PlanRepository($pdo);
        $result = $repo->list();
        $this->assertTrue($result[0]['features']['vendor_portal']);
    }

    public function testListReturnsEmptyWhenNoRows(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetchAll')->with(PDO::FETCH_ASSOC)->willReturn([]);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('query')->willReturn($stmt);

        $repo = new PlanRepository($pdo);
        $this->assertSame([], $repo->list());
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['plan-bad']));
        $stmt->method('fetch')->with(PDO::FETCH_ASSOC)->willReturn(false);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new PlanRepository($pdo);
        $this->assertNull($repo->findById('plan-bad'));
    }

    public function testFindByIdReturnsPlanWhenFound(): void
    {
        $row = [
            'id' => 'plan-basic',
            'name' => 'Basic',
            'tier' => 'lite',
            'item_limit' => 1000,
            'features' => '{}',
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['plan-basic']));
        $stmt->method('fetch')->with(PDO::FETCH_ASSOC)->willReturn($row);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new PlanRepository($pdo);
        $result = $repo->findById('plan-basic');
        $this->assertNotNull($result);
        $this->assertSame('plan-basic', $result['id']);
        $this->assertSame([], $result['features']);
    }

    public function testFindByIdWithInvalidJsonFeaturesReturnsEmptyArray(): void
    {
        $row = [
            'id' => 'plan-1',
            'name' => 'Basic',
            'tier' => 'lite',
            'item_limit' => 1000,
            'features' => 'not-json',
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['plan-1']));
        $stmt->method('fetch')->with(PDO::FETCH_ASSOC)->willReturn($row);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new PlanRepository($pdo);
        $result = $repo->findById('plan-1');
        $this->assertIsArray($result['features']);
        $this->assertSame([], $result['features']);
    }
}
