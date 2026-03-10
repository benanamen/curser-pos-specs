<?php

declare(strict_types=1);

namespace CurserPos\Http\Middleware;

use CurserPos\Http\RequestContext;
use CurserPos\Http\RequestContextHolder;
use PerfectApp\Routing\Router;
use Psr\Container\ContainerInterface;

final class Pipeline
{
    /** @var array<callable(RequestContext, callable): void> */
    private array $middleware = [];

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly Router $router
    ) {
    }

    public function add(callable $middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    public function process(?RequestContext $context = null): void
    {
        $context ??= RequestContext::fromGlobals();
        RequestContextHolder::set($context);
        $index = 0;

        $next = function () use (&$index, &$next, $context): void {
            if ($index >= count($this->middleware)) {
                $this->router->dispatch($context->requestUri, $context->requestMethod);
                return;
            }
            $middleware = $this->middleware[$index++];
            $middleware($context, $next);
        };

        $next();
    }
}
