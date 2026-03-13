<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Http\Middleware;

use CurserPos\Domain\Consignor\Consignor;
use CurserPos\Domain\Consignor\ConsignorRepository;
use CurserPos\Domain\Tenant\Tenant;
use CurserPos\Http\Middleware\ConsignorPortalMiddleware;
use CurserPos\Http\RequestContext;
use PHPUnit\Framework\TestCase;

final class ConsignorPortalMiddlewareTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_X_CONSIGNOR_PORTAL_TOKEN'], $_GET['token']);
        parent::tearDown();
    }

    public function testCallsNextWhenPathNotPortal(): void
    {
        $repo = $this->createMock(ConsignorRepository::class);
        $repo->expects($this->never())->method('findByPortalToken');

        $context = new RequestContext();
        $context->requestUri = '/t/mystore/api/v1/items';
        $called = false;
        $next = function () use (&$called) { $called = true; };

        $middleware = new ConsignorPortalMiddleware($repo);
        $middleware($context, $next);
        $this->assertTrue($called);
    }

    public function testCallsNextWhenNoToken(): void
    {
        $_SERVER['HTTP_X_CONSIGNOR_PORTAL_TOKEN'] = '';
        $repo = $this->createMock(ConsignorRepository::class);
        $repo->expects($this->never())->method('findByPortalToken');

        $context = new RequestContext();
        $context->requestUri = '/t/mystore/api/v1/portal/me';
        $context->tenant = new Tenant('t1', 'mystore', 'My Store', 'active', 'plan-1', [], new \DateTimeImmutable(), new \DateTimeImmutable());
        $called = false;
        $next = function () use (&$called) { $called = true; };

        $middleware = new ConsignorPortalMiddleware($repo);
        $middleware($context, $next);
        $this->assertTrue($called);
    }

    public function testCallsNextWhenTenantNull(): void
    {
        $_SERVER['HTTP_X_CONSIGNOR_PORTAL_TOKEN'] = 'tok123';
        $repo = $this->createMock(ConsignorRepository::class);
        $repo->expects($this->never())->method('findByPortalToken');

        $context = new RequestContext();
        $context->requestUri = '/t/mystore/api/v1/portal/me';
        $context->tenant = null;
        $called = false;
        $next = function () use (&$called) { $called = true; };

        $middleware = new ConsignorPortalMiddleware($repo);
        $middleware($context, $next);
        $this->assertTrue($called);
    }

    public function testSetsConsignorWhenTokenValid(): void
    {
        $_SERVER['HTTP_X_CONSIGNOR_PORTAL_TOKEN'] = 'tok123';
        $now = new \DateTimeImmutable();
        $consignor = new Consignor('c1', 'jane', null, 'Jane', null, null, null, 50.0, null, 'active', null, $now, $now);
        $repo = $this->createMock(ConsignorRepository::class);
        $repo->method('findByPortalToken')->with('tok123')->willReturn($consignor);

        $context = new RequestContext();
        $context->requestUri = '/t/mystore/api/v1/portal/me';
        $context->tenant = new Tenant('t1', 'mystore', 'My Store', 'active', 'plan-1', [], $now, $now);
        $called = false;
        $next = function () use (&$called) { $called = true; };

        $middleware = new ConsignorPortalMiddleware($repo);
        $middleware($context, $next);
        $this->assertSame($consignor, $context->consignor);
        $this->assertTrue($called);
    }

    public function testUsesGetTokenWhenHeaderNotSet(): void
    {
        $_GET['token'] = 'query-tok';
        $now = new \DateTimeImmutable();
        $consignor = new Consignor('c2', 'bob', null, 'Bob', null, null, null, 50.0, null, 'active', null, $now, $now);
        $repo = $this->createMock(ConsignorRepository::class);
        $repo->method('findByPortalToken')->with('query-tok')->willReturn($consignor);

        $context = new RequestContext();
        $context->requestUri = '/t/mystore/api/v1/portal/me';
        $context->tenant = new Tenant('t1', 'mystore', 'My Store', 'active', 'plan-1', [], $now, $now);
        $called = false;
        $next = function () use (&$called) { $called = true; };

        $middleware = new ConsignorPortalMiddleware($repo);
        $middleware($context, $next);
        $this->assertSame($consignor, $context->consignor);
        $this->assertTrue($called);
    }
}
