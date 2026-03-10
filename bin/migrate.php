<?php

declare(strict_types=1);

/**
 * Migration runner for Curser POS
 * Usage: php bin/migrate.php [shared|tenant <schema_name>]
 * - shared: Run shared schema migrations
 * - tenant <schema_name>: Run tenant migrations in the given schema
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

loadEnv(dirname(__DIR__) . '/.env');

$config = require dirname(__DIR__) . '/config/database.php';
$dsn = sprintf(
    'pgsql:host=%s;port=%s;dbname=%s',
    $config['shared']['host'],
    $config['shared']['port'],
    $config['shared']['dbname']
);
$pdo = new PDO($dsn, $config['shared']['username'], $config['shared']['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$command = $argv[1] ?? 'shared';
$schemaName = $argv[2] ?? null;

if ($command === 'shared') {
    runSharedMigrations($pdo);
} elseif ($command === 'tenant' && $schemaName !== null) {
    runTenantMigrations($pdo, $schemaName);
} else {
    echo "Usage: php bin/migrate.php shared\n";
    echo "       php bin/migrate.php tenant <schema_name>\n";
    exit(1);
}

echo "Migrations completed.\n";

function loadEnv(string $path): void
{
    if (!is_file($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        if (str_contains($line, '=')) {
            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            if ($name !== '') {
                $_ENV[$name] = $value;
                putenv("$name=$value");
            }
        }
    }
}

function runSharedMigrations(PDO $pdo): void
{
    $dir = dirname(__DIR__) . '/migrations/shared';
    $files = glob($dir . '/*.sql');
    sort($files);

    foreach ($files as $file) {
        $name = basename($file);
        echo "Running shared migration: $name\n";
        $sql = file_get_contents($file);
        $pdo->exec($sql);
    }
}

function runTenantMigrations(PDO $pdo, string $schemaName): void
{
    $safeSchema = preg_replace('/[^a-zA-Z0-9_]/', '', $schemaName);
    if ($safeSchema !== $schemaName) {
        throw new InvalidArgumentException('Invalid schema name');
    }

    echo "Creating schema: $safeSchema\n";
    $pdo->exec("CREATE SCHEMA IF NOT EXISTS \"$safeSchema\"");
    $pdo->exec("SET search_path TO \"$safeSchema\", public");

    $dir = dirname(__DIR__) . '/migrations/tenant';
    $files = glob($dir . '/*.sql');
    sort($files);

    foreach ($files as $file) {
        $name = basename($file);
        echo "Running tenant migration: $name\n";
        $sql = file_get_contents($file);
        $pdo->exec($sql);
    }

    $pdo->exec('SET search_path TO public');
}
