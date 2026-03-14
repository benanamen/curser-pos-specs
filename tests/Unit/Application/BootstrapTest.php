<?php

declare(strict_types=1);

namespace CurserPos\Tests\Unit\Application;

use CurserPos\Application\Bootstrap;
use CurserPos\Http\Kernel;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class BootstrapTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('APP_CONTAINER_FILE');
        parent::tearDown();
    }

    public function testBootReturnsHttpKernel(): void
    {
        $containerPath = dirname(__DIR__, 2) . '/config/container-test.php';
        putenv('APP_CONTAINER_FILE=' . $containerPath);
        $bootstrap = new Bootstrap();
        $kernel = $bootstrap->boot();
        $this->assertInstanceOf(Kernel::class, $kernel);
    }

    public function testBootUsesDefaultContainerPathWhenEnvNotSet(): void
    {
        putenv('APP_CONTAINER_FILE');
        $bootstrap = new Bootstrap();
        $kernel = $bootstrap->boot();
        $this->assertInstanceOf(Kernel::class, $kernel);
    }
}
