<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Domain\Booth;

use CurserPos\Domain\Booth\Booth;
use CurserPos\Domain\Booth\BoothRepository;
use PHPUnit\Framework\TestCase;
use PDO;
use PDOStatement;

final class BoothRepositoryTest extends TestCase
{
    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['bad-id']));
        $stmt->method('fetch')->with(PDO::FETCH_ASSOC)->willReturn(false);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new BoothRepository($pdo);
        $this->assertNull($repo->findById('bad-id'));
    }

    public function testFindByIdReturnsBoothWhenFound(): void
    {
        $row = [
            'id' => 'booth-1',
            'name' => 'Booth A',
            'location_id' => 'loc-1',
            'monthly_rent' => 150.0,
            'status' => 'active',
            'created_at' => '2025-01-01 00:00:00',
            'updated_at' => '2025-01-01 00:00:00',
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['booth-1']));
        $stmt->method('fetch')->with(PDO::FETCH_ASSOC)->willReturn($row);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new BoothRepository($pdo);
        $booth = $repo->findById('booth-1');
        $this->assertInstanceOf(Booth::class, $booth);
        $this->assertSame('booth-1', $booth->id);
        $this->assertSame('Booth A', $booth->name);
        $this->assertSame('loc-1', $booth->locationId);
        $this->assertSame(150.0, $booth->monthlyRent);
        $this->assertSame('active', $booth->status);
    }

    public function testFindByIdWithNullLocationId(): void
    {
        $row = [
            'id' => 'booth-2',
            'name' => 'Booth B',
            'location_id' => null,
            'monthly_rent' => 100.0,
            'status' => 'active',
            'created_at' => '2025-01-01 00:00:00',
            'updated_at' => '2025-01-01 00:00:00',
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['booth-2']));
        $stmt->method('fetch')->with(PDO::FETCH_ASSOC)->willReturn($row);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new BoothRepository($pdo);
        $booth = $repo->findById('booth-2');
        $this->assertNull($booth->locationId);
    }

    public function testFindAllReturnsList(): void
    {
        $rows = [
            [
                'id' => 'b1',
                'name' => 'A',
                'location_id' => 'loc-1',
                'monthly_rent' => 50.0,
                'status' => 'active',
                'created_at' => '2025-01-01 00:00:00',
                'updated_at' => '2025-01-01 00:00:00',
            ],
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['active']));
        $stmt->method('fetchAll')->with(PDO::FETCH_ASSOC)->willReturn($rows);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new BoothRepository($pdo);
        $list = $repo->findAll('active');
        $this->assertCount(1, $list);
        $this->assertInstanceOf(Booth::class, $list[0]);
        $this->assertSame('b1', $list[0]->id);
    }

    public function testCreateExecutesInsertAndReturnsId(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with($this->callback(function (array $params): bool {
            return count($params) === 7
                && $params[1] === 'Booth X'
                && $params[2] === 'loc-1'
                && (float) $params[3] === 200.0
                && $params[4] === 'active';
        }));

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new BoothRepository($pdo);
        $id = $repo->create('Booth X', 'loc-1', 200.0);
        $this->assertNotEmpty($id);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id);
    }

    public function testUpdateExecutes(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with($this->callback(function (array $params): bool {
            return $params[0] === 'New Name' && $params[1] === null && (float) $params[2] === 175.0 && $params[3] === 'inactive' && $params[5] === 'booth-1';
        }));

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new BoothRepository($pdo);
        $repo->update('booth-1', 'New Name', null, 175.0, 'inactive');
    }
}
