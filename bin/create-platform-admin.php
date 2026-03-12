<?php

declare(strict_types=1);

/**
 * Create or update a platform (support) admin user.
 * Usage: php bin/create-platform-admin.php <email> <password>
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

loadEnv(dirname(__DIR__) . '/.env');

$email = isset($argv[1]) ? trim((string) $argv[1]) : '';
$password = isset($argv[2]) ? (string) $argv[2] : '';

if ($email === '' || $password === '') {
    echo "Usage: php bin/create-platform-admin.php <email> <password>\n";
    exit(1);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "Invalid email format.\n";
    exit(1);
}

if (strlen($password) < 8) {
    echo "Password must be at least 8 characters.\n";
    exit(1);
}

$config = require dirname(__DIR__) . '/config/database.php';
$dsn = sprintf(
    'pgsql:host=%s;port=%s;dbname=%s',
    $config['shared']['host'],
    (string) $config['shared']['port'],
    $config['shared']['dbname']
);

$pdo = new PDO($dsn, $config['shared']['username'], $config['shared']['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$normalizedEmail = strtolower($email);
$passwordHash = password_hash($password, PASSWORD_DEFAULT);
$now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

$existingStmt = $pdo->prepare('SELECT id FROM platform_users WHERE email = ?');
$existingStmt->execute([$normalizedEmail]);
$existing = $existingStmt->fetch(PDO::FETCH_ASSOC);

if ($existing !== false) {
    $updateStmt = $pdo->prepare('UPDATE platform_users SET password_hash = ?, updated_at = ? WHERE id = ?');
    $updateStmt->execute([$passwordHash, $now, (string) $existing['id']]);
    echo "Updated existing platform admin: {$normalizedEmail}\n";
    exit(0);
}

$insertStmt = $pdo->prepare(
    'INSERT INTO platform_users (id, email, password_hash, created_at, updated_at) VALUES (?::uuid, ?, ?, ?, ?)'
);
$insertStmt->execute([generateUuid(), $normalizedEmail, $passwordHash, $now, $now]);

echo "Created platform admin: {$normalizedEmail}\n";

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
        if (!str_contains($line, '=')) {
            continue;
        }
        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        if ($name === '') {
            continue;
        }
        $_ENV[$name] = $value;
        putenv($name . '=' . $value);
    }
}

function generateUuid(): string
{
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}
