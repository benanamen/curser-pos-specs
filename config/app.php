<?php

declare(strict_types=1);

return [
    'env' => $_ENV['APP_ENV'] ?? 'development',
    'debug' => ($_ENV['APP_DEBUG'] ?? 'false') === 'true',
    'tenant_path_prefix' => '/t/',
    'api_prefix' => '/api/v1',
];
