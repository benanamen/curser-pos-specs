<?php

declare(strict_types=1);

use CurserPos\Http\Middleware\AuthMiddleware;
use CurserPos\Http\Middleware\ConsignorPortalMiddleware;
use CurserPos\Http\Middleware\PermissionMiddleware;
use CurserPos\Http\Middleware\Pipeline;
use CurserPos\Http\Middleware\PlatformAuthMiddleware;
use CurserPos\Http\Middleware\SchemaContextMiddleware;
use CurserPos\Http\Middleware\TenantResolutionMiddleware;
use PerfectApp\Container\Container;
use PerfectApp\Database\PostgresConnection;
use PerfectApp\Logger\FileLogger;
use PerfectApp\Routing\Router;
use PerfectApp\Session\Session;

$container = new Container(true);

$appConfig = require __DIR__ . '/app.php';
$dbConfig = require __DIR__ . '/database.php';

$container->set('config.app', $appConfig);
$container->set('config.database', $dbConfig);

$container->set(PDO::class, function () use ($dbConfig): PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    $connection = new PostgresConnection();
    $config = $dbConfig['shared'];
    $config['options'] ??= [];
    $pdo = $connection->connect($config);
    return $pdo;
});

$container->set(FileLogger::class, function () {
    $logDir = __DIR__ . '/../storage/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    return new FileLogger($logDir . '/app.log');
});

$container->set(Session::class, Session::class);

$container->set(\CurserPos\Domain\Tenant\TenantProvisioningService::class, function (Container $c) {
    return new \CurserPos\Domain\Tenant\TenantProvisioningService(
        $c->get(PDO::class),
        $c->get(\CurserPos\Domain\Tenant\TenantRepositoryInterface::class),
        $c->get(\CurserPos\Domain\User\UserRepositoryInterface::class),
        __DIR__ . '/../migrations/tenant'
    );
});

$container->set(Router::class, function (Container $c) {
    $router = new Router($c);
    $router->autoRegisterControllers(__DIR__ . '/../src/Api/V1');
    $router->setNotFoundHandler(function (string $uri, string $method): void {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Not found', 'path' => $uri]);
    });
    return $router;
});

$container->set(TenantResolutionMiddleware::class, function (Container $c) {
    $config = $c->get('config.app');
    return new TenantResolutionMiddleware(
        $c->get(\CurserPos\Domain\Tenant\TenantRepositoryInterface::class),
        $config['tenant_path_prefix'] ?? '/t/'
    );
});

$container->set(ConsignorPortalMiddleware::class, function (Container $c) {
    return new ConsignorPortalMiddleware($c->get(\CurserPos\Domain\Consignor\ConsignorRepository::class));
});

$container->set(PlatformAuthMiddleware::class, function (Container $c) {
    return new PlatformAuthMiddleware($c->get(Session::class));
});

$container->set(Pipeline::class, function (Container $c) {
    $pipeline = new Pipeline($c, $c->get(Router::class));
    $pipeline->add($c->get(TenantResolutionMiddleware::class));
    $pipeline->add($c->get(SchemaContextMiddleware::class));
    $pipeline->add($c->get(ConsignorPortalMiddleware::class));
    $pipeline->add($c->get(PlatformAuthMiddleware::class));
    $pipeline->add($c->get(AuthMiddleware::class));
    $pipeline->add($c->get(PermissionMiddleware::class));
    return $pipeline;
});

$container->set(\CurserPos\Domain\Tenant\TenantRepositoryInterface::class, \CurserPos\Domain\Tenant\TenantRepository::class);
$container->set(\CurserPos\Domain\User\UserRepositoryInterface::class, \CurserPos\Domain\User\UserRepository::class);
$container->set(\CurserPos\Domain\Role\RoleRepository::class, \CurserPos\Domain\Role\RoleRepository::class);
$container->set(\CurserPos\Domain\Tenant\TenantUserRepository::class, \CurserPos\Domain\Tenant\TenantUserRepository::class);
$container->set(\CurserPos\Domain\Audit\ActivityLogRepository::class, \CurserPos\Domain\Audit\ActivityLogRepository::class);
$container->set(\CurserPos\Domain\User\PasswordResetTokenRepository::class, \CurserPos\Domain\User\PasswordResetTokenRepository::class);
$container->set(\CurserPos\Domain\Platform\PlatformUserRepository::class, \CurserPos\Domain\Platform\PlatformUserRepository::class);
$container->set(\CurserPos\Domain\Plan\PlanRepository::class, \CurserPos\Domain\Plan\PlanRepository::class);
$container->set(\CurserPos\Domain\User\InviteTokenRepository::class, \CurserPos\Domain\User\InviteTokenRepository::class);
$container->set(\CurserPos\Domain\Billing\TenantBillingRepository::class, \CurserPos\Domain\Billing\TenantBillingRepository::class);

$appConfig = $container->get('config.app');
$container->set(\CurserPos\Domain\Billing\BillingProviderInterface::class, function (Container $c) use ($appConfig) {
    $provider = $appConfig['billing']['provider'] ?? 'stripe';
    if ($provider === 'stripe') {
        $prices = $appConfig['billing']['stripe_prices'] ?? [];
        return new \CurserPos\Infrastructure\Billing\StripeBillingProvider(
            $_ENV['STRIPE_SECRET_KEY'] ?? '',
            is_array($prices) ? $prices : [],
            $c->get(\CurserPos\Domain\Billing\TenantBillingRepository::class)
        );
    }
    throw new \InvalidArgumentException('Unsupported BILLING_PROVIDER: ' . $provider);
});

$container->set(\CurserPos\Service\BillingService::class, \CurserPos\Service\BillingService::class);

$container->set(\CurserPos\Infrastructure\Payment\PaymentProcessorInterface::class, function (Container $c) {
    $key = $_ENV['STRIPE_SECRET_KEY'] ?? 'test';
    return new \CurserPos\Infrastructure\Payment\StripePaymentProcessor($key);
});

return $container;
