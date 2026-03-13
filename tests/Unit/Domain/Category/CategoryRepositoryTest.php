<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Domain\Category;

use CurserPos\Domain\Category\Category;
use CurserPos\Domain\Category\CategoryRepository;
use PHPUnit\Framework\TestCase;
use PDO;
use PDOStatement;

final class CategoryRepositoryTest extends TestCase
{
    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['bad-id']));
        $stmt->method('fetch')->with(PDO::FETCH_ASSOC)->willReturn(false);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new CategoryRepository($pdo);
        $this->assertNull($repo->findById('bad-id'));
    }

    public function testFindByIdReturnsCategoryWhenFound(): void
    {
        $row = [
            'id' => 'cat-1',
            'parent_id' => null,
            'name' => 'Clothing',
            'sort_order' => 1,
            'tax_exempt' => 'f',
            'created_at' => '2025-01-01 00:00:00',
            'updated_at' => '2025-01-01 00:00:00',
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['cat-1']));
        $stmt->method('fetch')->with(PDO::FETCH_ASSOC)->willReturn($row);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new CategoryRepository($pdo);
        $cat = $repo->findById('cat-1');
        $this->assertInstanceOf(Category::class, $cat);
        $this->assertSame('cat-1', $cat->id);
        $this->assertSame('Clothing', $cat->name);
        $this->assertFalse($cat->taxExempt);
    }

    public function testFindByIdWithParentIdAndTaxExemptTrue(): void
    {
        $row = [
            'id' => 'cat-2',
            'parent_id' => 'cat-1',
            'name' => 'Sub',
            'sort_order' => 0,
            'tax_exempt' => 't',
            'created_at' => '2025-01-01 00:00:00',
            'updated_at' => '2025-01-01 00:00:00',
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->with($this->equalTo(['cat-2']));
        $stmt->method('fetch')->with(PDO::FETCH_ASSOC)->willReturn($row);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new CategoryRepository($pdo);
        $cat = $repo->findById('cat-2');
        $this->assertSame('cat-1', $cat->parentId);
        $this->assertTrue($cat->taxExempt);
    }

    public function testFindAllReturnsList(): void
    {
        $rows = [
            ['id' => 'c1', 'parent_id' => null, 'name' => 'A', 'sort_order' => 0, 'tax_exempt' => 'f', 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetchAll')->with(PDO::FETCH_ASSOC)->willReturn($rows);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('query')->willReturn($stmt);

        $repo = new CategoryRepository($pdo);
        $list = $repo->findAll();
        $this->assertCount(1, $list);
        $this->assertInstanceOf(Category::class, $list[0]);
    }

    public function testCreateExecutesWithTaxExemptTrue(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with($this->callback(function (array $params): bool {
            return $params[2] === 'New Cat' && $params[4] === 't';
        }));

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new CategoryRepository($pdo);
        $id = $repo->create('New Cat', null, 0, true);
        $this->assertNotEmpty($id);
    }

    public function testUpdateExecutes(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with($this->callback(function (array $params): bool {
            return $params[0] === 'Updated' && $params[1] === 'cat-1' && $params[2] === 2 && $params[3] === 'f' && $params[5] === 'cat-1';
        }));

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new CategoryRepository($pdo);
        $repo->update('cat-1', 'Updated', 'cat-1', 2, false);
    }

    public function testDeleteExecutes(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with($this->equalTo(['cat-1']));

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new CategoryRepository($pdo);
        $repo->delete('cat-1');
    }
}
