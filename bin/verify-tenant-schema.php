<?php

declare(strict_types=1);

/**
 * Verify a tenant schema has required tables (e.g. consignors).
 * Usage: php bin/verify-tenant-schema.php <slug>
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

$slug = isset($argv[1]) ? trim($argv[1]) : '';
if ($slug === '') {
    echo "Usage: php bin/verify-tenant-schema.php <slug>\n";
    exit(1);
}

$config = require dirname(__DIR__) . '/config/database.php';
$c = $config['shared'];
$dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $c['host'], $c['port'], $c['dbname']);
$pdo = new PDO($dsn, $c['username'], $c['password'] ?? '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$stmt = $pdo->prepare('SELECT id, slug, name FROM tenants WHERE slug = ?');
$stmt->execute([$slug]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row === false) {
    echo "Tenant not found for slug: $slug\n";
    exit(1);
}

$tenantId = strtolower((string) $row['id']);
$schemaName = 'tenant_' . str_replace('-', '_', $tenantId);
$safeSchema = preg_replace('/[^a-zA-Z0-9_]/', '', $schemaName);
if ($safeSchema !== $schemaName) {
    echo "Invalid schema name.\n";
    exit(1);
}

echo "Tenant: " . ($row['name'] ?? $slug) . " (slug: $slug)\n";
echo "Schema: $safeSchema (must match API's Tenant::schemaName())\n";

$pdo->exec("SET search_path TO \"$safeSchema\", public");
$current = $pdo->query('SELECT current_schema()')->fetchColumn();
echo "current_schema() = $current\n";

$stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = '" . str_replace("'", "''", $safeSchema) . "' ORDER BY table_name");
$tables = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
echo "Tables in schema: " . (count($tables) > 0 ? implode(', ', $tables) : '(none)') . "\n";

$hasConsignors = in_array('consignors', $tables, true);
$pdo->exec('SET search_path TO public');

if ($hasConsignors) {
    echo "OK: consignors table exists. API should work.\n";
    exit(0);
}
echo "MISSING: consignors table not found. Run: php bin/migrate-tenant-by-slug.php $slug\n";
exit(1);
