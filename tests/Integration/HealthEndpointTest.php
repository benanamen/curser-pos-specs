<?php

declare(strict_types=1);

namespace CurserPos\Tests\Integration;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class HealthEndpointTest extends TestCase
{
    public function testPlaceholder(): void
    {
        $this->assertTrue(true);
    }
}
