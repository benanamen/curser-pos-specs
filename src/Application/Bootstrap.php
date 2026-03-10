<?php

declare(strict_types=1);

namespace CurserPos\Application;

use CurserPos\Http\Kernel as HttpKernel;

final class Bootstrap
{
    public function boot(): HttpKernel
    {
        $container = require dirname(__DIR__, 2) . '/config/container.php';
        return new HttpKernel($container);
    }
}
