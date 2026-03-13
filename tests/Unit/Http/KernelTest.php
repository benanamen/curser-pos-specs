<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Http;

use CurserPos\Http\Kernel;
use CurserPos\Http\Middleware\Pipeline;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class KernelTest extends TestCase
{
    public function testHandleGetsPipelineAndCallsProcess(): void
    {
        $pipeline = new class {
            public bool $processCalled = false;
            public function process(): void
            {
                $this->processCalled = true;
            }
        };
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->with(Pipeline::class)->willReturn($pipeline);

        $kernel = new Kernel($container);
        $kernel->handle();
        $this->assertTrue($pipeline->processCalled);
    }
}
