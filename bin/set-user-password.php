<?php

declare(strict_types=1);

/**
 * Set a new password for a tenant user by email (shared users table).
 * Use for local/dev when you know the email but not the password.
 * Usage: php bin/set-user-password.php <email> <new-password>
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

loadEnv(dirname(__DIR__) . '/.env');

$email = isset($argv[1]) ? trim((string) $argv[1]) : '';
$password = isset($argv[2]) ? (string) $argv[2] : '';

if ($email === '' || $password === '') {
    echo "Usage: php bin/set-user-password.php <email> <new-password>\n";
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

$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$stmt->execute([$normalizedEmail]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row === false) {
    echo "No user found with email: {$normalizedEmail}\n";
    exit(1);
}

$updateStmt = $pdo->prepare('UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?');
$updateStmt->execute([$passwordHash, $now, (string) $row['id']]);

echo "Password updated for: {$normalizedEmail}\n";

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
