<?php

declare(strict_types=1);

/**
 * Run tenant migrations by store slug.
 * Usage: php bin/migrate-tenant-by-slug.php <slug>
 *        php bin/migrate-tenant-by-slug.php   (list tenants and schema names)
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

$config = require dirname(__DIR__) . '/config/database.php';
$c = $config['shared'];
$dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $c['host'], $c['port'], $c['dbname']);
$pdo = new PDO($dsn, $c['username'], $c['password'] ?? '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$slug = isset($argv[1]) ? trim($argv[1]) : '';

if ($slug === '') {
    $stmt = $pdo->query('SELECT id, slug, name FROM tenants ORDER BY slug');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Tenants (run migrations with: php bin/migrate-tenant-by-slug.php <slug>):\n";
    foreach ($rows as $r) {
        $schemaName = 'tenant_' . str_replace('-', '_', (string) $r['id']);
        echo "  " . ($r['slug'] ?? '') . "  →  " . $schemaName . "\n";
    }
    exit(0);
}

$stmt = $pdo->prepare('SELECT id, slug, name FROM tenants WHERE slug = ?');
$stmt->execute([$slug]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row === false) {
    echo "Tenant not found for slug: " . $slug . "\n";
    exit(1);
}

$tenantId = (string) $row['id'];
$tenantId = strtolower($tenantId);
$schemaName = 'tenant_' . str_replace('-', '_', $tenantId);
$safeSchema = preg_replace('/[^a-zA-Z0-9_]/', '', $schemaName);
if ($safeSchema !== $schemaName) {
    echo "Invalid schema name derived from tenant id.\n";
    exit(1);
}

echo "Running tenant migrations for: " . ($row['name'] ?? $slug) . " (schema: $safeSchema)\n";

$pdo->exec("CREATE SCHEMA IF NOT EXISTS \"$safeSchema\"");
$pdo->exec("SET search_path TO \"$safeSchema\", public");

$currentSchema = $pdo->query('SELECT current_schema()')->fetchColumn();
echo "  current_schema() = " . $currentSchema . "\n";

$dir = dirname(__DIR__) . '/migrations/tenant';
$files = glob($dir . '/*.sql');
sort($files);

foreach ($files as $file) {
    $name = basename($file);
    echo "  $name\n";
    $sql = file_get_contents($file);
    $pdo->exec($sql);
}

$stmt = $pdo->query("SELECT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = '" . str_replace("'", "''", $safeSchema) . "' AND table_name = 'consignors')");
$val = $stmt ? $stmt->fetchColumn() : false;
$consignorsExists = $val === true || $val === 't' || $val === '1' || $val === 1;
$pdo->exec('SET search_path TO public');

if (!$consignorsExists) {
    echo "ERROR: consignors table not found in schema \"$safeSchema\" after migrations. Check errors above.\n";
    exit(1);
}
echo "Done. Verified: consignors table exists in $safeSchema.\n";
