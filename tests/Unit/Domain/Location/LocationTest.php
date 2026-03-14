<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Domain\Location;

use CurserPos\Domain\Location\Location;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class LocationTest extends TestCase
{
    public function testConstruction(): void
    {
        $now = new \DateTimeImmutable();
        $loc = new Location('loc-1', 'Main Store', '123 Main St', [], $now, $now);
        $this->assertSame('loc-1', $loc->id);
        $this->assertSame('Main Store', $loc->name);
        $this->assertSame('123 Main St', $loc->address);
        $this->assertSame([], $loc->taxRates);
        $this->assertSame($now, $loc->createdAt);
        $this->assertSame($now, $loc->updatedAt);
    }

    public function testConstructionWithTaxRates(): void
    {
        $now = new \DateTimeImmutable();
        $rates = [['rate' => 8.5, 'name' => 'State'], ['rate' => 2.0, 'name' => 'County', 'type' => 'county']];
        $loc = new Location('loc-2', 'Second', '456 Oak', $rates, $now, $now);
        $this->assertCount(2, $loc->taxRates);
        $this->assertSame(8.5, $loc->taxRates[0]['rate']);
        $this->assertSame('county', $loc->taxRates[1]['type'] ?? null);
    }
}
