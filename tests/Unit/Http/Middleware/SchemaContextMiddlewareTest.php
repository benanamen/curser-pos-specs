<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Http\Middleware;

use CurserPos\Domain\Tenant\Tenant;
use CurserPos\Http\Middleware\SchemaContextMiddleware;
use CurserPos\Http\RequestContext;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use PDO;

#[AllowMockObjectsWithoutExpectations]
final class SchemaContextMiddlewareTest extends TestCase
{
    public function testCallsNextWhenTenantIsNull(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->never())->method('exec');

        $context = new RequestContext();
        $context->tenant = null;
        $called = false;
        $next = function () use (&$called) { $called = true; };

        $middleware = new SchemaContextMiddleware($pdo);
        $middleware($context, $next);
        $this->assertTrue($called);
    }

    public function testSetsSearchPathWhenTenantPresent(): void
    {
        $now = new \DateTimeImmutable();
        $tenant = new Tenant('abc-123', 'store', 'Store', 'active', 'plan-1', [], $now, $now);

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())->method('exec')->with('SET search_path TO "tenant_abc_123", public');

        $context = new RequestContext();
        $context->tenant = $tenant;
        $called = false;
        $next = function () use (&$called) { $called = true; };

        $middleware = new SchemaContextMiddleware($pdo);
        $middleware($context, $next);
        $this->assertTrue($called);
    }
}
