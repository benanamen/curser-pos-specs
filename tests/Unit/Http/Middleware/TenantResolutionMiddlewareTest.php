<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Http\Middleware;

use CurserPos\Domain\Tenant\Tenant;
use CurserPos\Domain\Tenant\TenantRepositoryInterface;
use CurserPos\Http\Middleware\TenantResolutionMiddleware;
use CurserPos\Http\RequestContext;
use PHPUnit\Framework\TestCase;

final class TenantResolutionMiddlewareTest extends TestCase
{
    public function testCallsNextWhenPathDoesNotStartWithPrefix(): void
    {
        $repo = $this->createMock(TenantRepositoryInterface::class);
        $repo->expects($this->never())->method('findBySlug');

        $context = new RequestContext();
        $context->requestUri = '/api/v1/health';
        $called = false;
        $next = function () use (&$called) { $called = true; };

        $middleware = new TenantResolutionMiddleware($repo, '/t/');
        $middleware($context, $next);
        $this->assertTrue($called);
    }

    public function testCallsNextWhenPathDoesNotMatchSlugPattern(): void
    {
        $repo = $this->createMock(TenantRepositoryInterface::class);
        $repo->expects($this->never())->method('findBySlug');

        $context = new RequestContext();
        $context->requestUri = '/t//api/v1/foo';
        $called = false;
        $next = function () use (&$called) { $called = true; };

        $middleware = new TenantResolutionMiddleware($repo, '/t/');
        $middleware($context, $next);
        $this->assertTrue($called);
    }

    public function testReturns404WhenTenantNotFound(): void
    {
        $repo = $this->createMock(TenantRepositoryInterface::class);
        $repo->method('findBySlug')->with('missing')->willReturn(null);

        $context = new RequestContext();
        $context->requestUri = '/t/missing/api/v1/items';
        $next = function () { $this->fail('Next should not be called'); };

        $middleware = new TenantResolutionMiddleware($repo, '/t/');

        ob_start();
        $middleware($context, $next);
        $output = ob_get_clean();

        $this->assertSame(404, http_response_code());
        $this->assertStringContainsString('Tenant not found', $output);
        $this->assertStringContainsString('missing', $output);
    }

    public function testSetsTenantAndCallsNextWhenFound(): void
    {
        $now = new \DateTimeImmutable();
        $tenant = new Tenant('t1', 'mystore', 'My Store', 'active', 'plan-1', [], $now, $now);
        $repo = $this->createMock(TenantRepositoryInterface::class);
        $repo->method('findBySlug')->with('mystore')->willReturn($tenant);

        $context = new RequestContext();
        $context->requestUri = '/t/mystore/api/v1/items';
        $called = false;
        $next = function () use (&$called) { $called = true; };

        $middleware = new TenantResolutionMiddleware($repo, '/t/');
        $middleware($context, $next);

        $this->assertSame('mystore', $context->tenantSlug);
        $this->assertSame($tenant, $context->tenant);
        $this->assertTrue($called);
    }
}
