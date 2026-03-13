<?php

declare(strict_types=1);

namespace CurserPos\Application;

use CurserPos\Http\Kernel as HttpKernel;

final class Bootstrap
{
    public function boot(): HttpKernel
    {
        $configPath = getenv('APP_CONTAINER_FILE');
        $path = $configPath !== false && $configPath !== ''
            ? $configPath
            : dirname(__DIR__, 2) . '/config/container.php';
        $container = require $path;
        return new HttpKernel($container);
    }
}
