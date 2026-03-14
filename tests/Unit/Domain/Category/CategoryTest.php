<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Domain\Category;

use CurserPos\Domain\Category\Category;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class CategoryTest extends TestCase
{
    public function testConstruction(): void
    {
        $now = new \DateTimeImmutable();
        $cat = new Category('cat-1', null, 'Clothing', 1, false, $now, $now);
        $this->assertSame('cat-1', $cat->id);
        $this->assertNull($cat->parentId);
        $this->assertSame('Clothing', $cat->name);
        $this->assertSame(1, $cat->sortOrder);
        $this->assertFalse($cat->taxExempt);
        $this->assertSame($now, $cat->createdAt);
        $this->assertSame($now, $cat->updatedAt);
    }

    public function testConstructionWithParent(): void
    {
        $now = new \DateTimeImmutable();
        $cat = new Category('cat-2', 'cat-1', 'Subcategory', 0, true, $now, $now);
        $this->assertSame('cat-1', $cat->parentId);
        $this->assertTrue($cat->taxExempt);
    }
}
