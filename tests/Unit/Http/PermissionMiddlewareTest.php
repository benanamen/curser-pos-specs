<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Http;

use CurserPos\Domain\Tenant\Tenant;
use CurserPos\Http\Middleware\PermissionMiddleware;
use CurserPos\Http\RequestContext;
use PHPUnit\Framework\TestCase;

final class PermissionMiddlewareTest extends TestCase
{
    public function testCallsNextWhenNoTenant(): void
    {
        $context = new RequestContext();
        $context->tenant = null;
        $called = false;
        $next = function () use (&$called) {
            $called = true;
        };
        $middleware = new PermissionMiddleware();
        $middleware($context, $next);
        $this->assertTrue($called);
    }

    public function testCallsNextWhenSupportAccess(): void
    {
        $context = new RequestContext();
        $context->tenant = $this->createTenant();
        $context->isSupportAccess = true;
        $called = false;
        $next = function () use (&$called) {
            $called = true;
        };
        $middleware = new PermissionMiddleware();
        $middleware($context, $next);
        $this->assertTrue($called);
    }

    public function testCallsNextWhenTenantUserHasAllPermission(): void
    {
        $context = new RequestContext();
        $context->tenant = $this->createTenant();
        $context->tenantUser = ['permissions' => ['all' => true]];
        $context->requestUri = '/t/mystore/api/v1/store/config';
        $called = false;
        $next = function () use (&$called) {
            $called = true;
        };
        $middleware = new PermissionMiddleware();
        $middleware($context, $next);
        $this->assertTrue($called);
    }

    public function testCallsNextWhenTenantUserHasRequiredPermission(): void
    {
        $context = new RequestContext();
        $context->tenant = $this->createTenant();
        $context->tenantUser = ['permissions' => ['settings' => true]];
        $context->requestUri = '/t/mystore/api/v1/store/config';
        $called = false;
        $next = function () use (&$called) {
            $called = true;
        };
        $middleware = new PermissionMiddleware();
        $middleware($context, $next);
        $this->assertTrue($called);
    }

    public function testReturns403WhenPermissionDenied(): void
    {
        $context = new RequestContext();
        $context->tenant = $this->createTenant();
        $context->tenantUser = ['permissions' => ['pos' => true]];
        $context->requestUri = '/t/mystore/api/v1/store/config';
        $called = false;
        $next = function () use (&$called) {
            $called = true;
        };
        $middleware = new PermissionMiddleware();
        $middleware($context, $next);
        $this->assertFalse($called);
    }

    public function testCallsNextWhenNoTenantUser(): void
    {
        $context = new RequestContext();
        $context->tenant = $this->createTenant();
        $context->tenantUser = null;
        $called = false;
        $next = function () use (&$called) {
            $called = true;
        };
        $middleware = new PermissionMiddleware();
        $middleware($context, $next);
        $this->assertTrue($called);
    }

    private function createTenant(): Tenant
    {
        return new Tenant(
            't1',
            'mystore',
            'My Store',
            'active',
            'plan-1',
            [],
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );
    }
}
