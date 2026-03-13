<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Http\Middleware;

use CurserPos\Http\Middleware\Pipeline;
use CurserPos\Http\RequestContext;
use PHPUnit\Framework\TestCase;
use PerfectApp\Routing\Router;
use Psr\Container\ContainerInterface;

final class PipelineTest extends TestCase
{
    public function testAddReturnsSelf(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $router = $this->createMock(Router::class);
        $pipeline = new Pipeline($container, $router);
        $this->assertSame($pipeline, $pipeline->add(function () {}));
    }

    public function testProcessWithNullContextUsesFromGlobals(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $router = $this->createMock(Router::class);
        $router->expects($this->once())->method('dispatch')->with($this->anything(), $this->anything());

        $pipeline = new Pipeline($container, $router);
        $pipeline->process(null);
    }

    public function testProcessWithContextCallsRouterWhenNoMiddleware(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $router = $this->createMock(Router::class);
        $router->expects($this->once())->method('dispatch')->with('/t/foo/api/v1/health', 'GET');

        $context = new RequestContext();
        $context->requestUri = '/t/foo/api/v1/health';
        $context->requestMethod = 'GET';

        $pipeline = new Pipeline($container, $router);
        $pipeline->process($context);
    }

    public function testProcessWithMiddlewareCallsEachThenRouter(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $router = $this->createMock(Router::class);
        $router->expects($this->once())->method('dispatch')->with('/api/v1/health', 'GET');

        $called = false;
        $middleware = function (RequestContext $ctx, callable $next) use (&$called): void {
            $called = true;
            $next();
        };

        $context = new RequestContext();
        $context->requestUri = '/api/v1/health';
        $context->requestMethod = 'GET';

        $pipeline = new Pipeline($container, $router);
        $pipeline->add($middleware);
        $pipeline->process($context);

        $this->assertTrue($called);
    }
}
