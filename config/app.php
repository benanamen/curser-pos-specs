<?php

declare(strict_types=1);

return [
    'env' => $_ENV['APP_ENV'] ?? 'development',
    'debug' => ($_ENV['APP_DEBUG'] ?? 'false') === 'true',
    'tenant_path_prefix' => '/t/',
    'api_prefix' => '/api/v1',
    'billing' => [
        'provider' => $_ENV['BILLING_PROVIDER'] ?? 'stripe',
        'stripe_prices' => [
            'a0000000-0000-0000-0000-000000000001' => $_ENV['STRIPE_PRICE_LITE'] ?? '',
            'a0000000-0000-0000-0000-000000000002' => $_ENV['STRIPE_PRICE_PRO'] ?? '',
            'a0000000-0000-0000-0000-000000000003' => $_ENV['STRIPE_PRICE_ENTERPRISE'] ?? '',
        ],
    ],
];
