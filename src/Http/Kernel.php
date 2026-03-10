<?php

declare(strict_types=1);

namespace CurserPos\Http;

use Psr\Container\ContainerInterface;

final class Kernel
{
    public function __construct(
        private readonly ContainerInterface $container
    ) {
    }

    public function handle(): void
    {
        $pipeline = $this->container->get(\CurserPos\Http\Middleware\Pipeline::class);
        $pipeline->process();
    }
}
