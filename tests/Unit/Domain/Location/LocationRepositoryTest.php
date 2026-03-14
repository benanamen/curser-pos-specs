<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Domain\Location;

use CurserPos\Domain\Location\Location;
use CurserPos\Domain\Location\LocationRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use PDO;
use PDOStatement;

#[AllowMockObjectsWithoutExpectations]
final class LocationRepositoryTest extends TestCase
{
    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['bad-id']));
        $stmt->method('fetch')->with(PDO::FETCH_ASSOC)->willReturn(false);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new LocationRepository($pdo);
        $this->assertNull($repo->findById('bad-id'));
    }

    public function testFindByIdReturnsLocationWhenFound(): void
    {
        $row = [
            'id' => 'loc-1',
            'name' => 'Main',
            'address' => '123 Main St',
            'tax_rates' => '[{"rate":8.5,"name":"State"}]',
            'created_at' => '2025-01-01 00:00:00',
            'updated_at' => '2025-01-01 00:00:00',
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['loc-1']));
        $stmt->method('fetch')->with(PDO::FETCH_ASSOC)->willReturn($row);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new LocationRepository($pdo);
        $loc = $repo->findById('loc-1');
        $this->assertInstanceOf(Location::class, $loc);
        $this->assertSame('loc-1', $loc->id);
        $this->assertSame('Main', $loc->name);
        $this->assertCount(1, $loc->taxRates);
    }

    public function testFindAllReturnsList(): void
    {
        $rows = [
            ['id' => 'l1', 'name' => 'A', 'address' => '', 'tax_rates' => '[]', 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetchAll')->with(PDO::FETCH_ASSOC)->willReturn($rows);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('query')->willReturn($stmt);

        $repo = new LocationRepository($pdo);
        $list = $repo->findAll();
        $this->assertCount(1, $list);
        $this->assertInstanceOf(Location::class, $list[0]);
    }

    public function testCreateExecutesAndReturnsId(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with($this->callback(function (array $params): bool {
            return $params[1] === 'Store' && $params[2] === '' && $params[3] === '[]';
        }));

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new LocationRepository($pdo);
        $id = $repo->create('Store', '', []);
        $this->assertNotEmpty($id);
    }

    public function testUpdateExecutes(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with($this->callback(function (array $params): bool {
            return $params[0] === 'New Name' && $params[1] === '456 Oak' && $params[4] === 'loc-1' && count($params) === 5;
        }));

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new LocationRepository($pdo);
        $repo->update('loc-1', 'New Name', '456 Oak', []);
    }

    public function testDeleteExecutes(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with($this->equalTo(['loc-1']));

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new LocationRepository($pdo);
        $repo->delete('loc-1');
    }
}
