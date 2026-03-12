<?php

declare(strict_types=1);

/**
 * Test login locally by calling AuthService (same as API). No HTTP.
 * Usage: php bin/test-login.php owner2@store.com password
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

$envFile = dirname(__DIR__) . '/.env';
if (is_file($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (str_contains($line, '=')) {
            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            if ($name !== '') {
                putenv("$name=$value");
                $_ENV[$name] = $value;
            }
        }
    }
}

$email = $argv[1] ?? '';
$password = $argv[2] ?? '';

if ($email === '' || $password === '') {
    echo "Usage: php bin/test-login.php <email> <password>\n";
    exit(1);
}

$container = require dirname(__DIR__) . '/config/container.php';
$authService = $container->get(\CurserPos\Service\AuthService::class);

try {
    $result = $authService->login($email, $password);
    echo "OK - Login succeeded.\n";
    echo "User: " . ($result['user']['email'] ?? '') . " (id: " . ($result['user']['id'] ?? '') . ")\n";
    echo "Tenant: " . ($result['tenant']['name'] ?? '') . " (slug: " . ($result['tenant']['slug'] ?? '') . ")\n";
    exit(0);
} catch (\Throwable $e) {
    echo "FAIL - " . $e->getMessage() . "\n";
    exit(1);
}
