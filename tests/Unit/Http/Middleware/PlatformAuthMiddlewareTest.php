<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Http\Middleware;

use CurserPos\Http\Middleware\PlatformAuthMiddleware;
use CurserPos\Http\RequestContext;
use PHPUnit\Framework\TestCase;
use PerfectApp\Session\Session;

final class PlatformAuthMiddlewareTest extends TestCase
{
    public function testCallsNextWhenPathNotPlatform(): void
    {
        $session = $this->createMock(Session::class);
        $session->expects($this->never())->method('get');

        $context = new RequestContext();
        $context->requestUri = '/api/v1/health';
        $called = false;
        $next = function () use (&$called) { $called = true; };

        $middleware = new PlatformAuthMiddleware($session);
        $middleware($context, $next);
        $this->assertTrue($called);
    }

    public function testCallsNextWhenPathIsLogin(): void
    {
        $session = $this->createMock(Session::class);
        $context = new RequestContext();
        $context->requestUri = '/api/v1/platform/login';
        $called = false;
        $next = function () use (&$called) { $called = true; };

        $middleware = new PlatformAuthMiddleware($session);
        $middleware($context, $next);
        $this->assertTrue($called);
    }

    public function testCallsNextWhenPathIsLogout(): void
    {
        $session = $this->createMock(Session::class);
        $context = new RequestContext();
        $context->requestUri = '/api/v1/platform/logout';
        $called = false;
        $next = function () use (&$called) { $called = true; };

        $middleware = new PlatformAuthMiddleware($session);
        $middleware($context, $next);
        $this->assertTrue($called);
    }

    public function testReturns401WhenPlatformUserIdMissing(): void
    {
        $session = $this->createMock(Session::class);
        $session->method('get')->with('platform_user_id')->willReturn(null);

        $context = new RequestContext();
        $context->requestUri = '/api/v1/platform/tenants';
        $next = function () { $this->fail('Next should not be called'); };

        $middleware = new PlatformAuthMiddleware($session);
        ob_start();
        $middleware($context, $next);
        $out = ob_get_clean();
        $this->assertStringContainsString('Platform authentication required', $out);
    }

    public function testCallsNextWhenPlatformUserIdPresent(): void
    {
        $session = $this->createMock(Session::class);
        $session->method('get')->with('platform_user_id')->willReturn('plat-1');

        $context = new RequestContext();
        $context->requestUri = '/api/v1/platform/tenants';
        $called = false;
        $next = function () use (&$called) { $called = true; };

        $middleware = new PlatformAuthMiddleware($session);
        $middleware($context, $next);
        $this->assertTrue($called);
    }
}
