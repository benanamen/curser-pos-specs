<?php

declare(strict_types=1);

use CurserPos\Http\Middleware\Pipeline;
use PerfectApp\Container\Container;

$container = new Container(true);
$container->set(Pipeline::class, new class {
    public function process(): void
    {
    }
});
return $container;
