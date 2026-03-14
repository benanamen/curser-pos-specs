<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Domain\Tenant;

use CurserPos\Domain\Tenant\Tenant;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class TenantTest extends TestCase
{
    public function testSchemaName(): void
    {
        $tenant = new Tenant(
            '123e4567-e89b-12d3-a456-426614174000',
            'mystore',
            'My Store',
            'active',
            'plan-1',
            [],
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );
        $this->assertSame('tenant_123e4567_e89b_12d3_a456_426614174000', $tenant->schemaName());
    }
}
