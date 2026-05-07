<?php

/**
 * One-time deploy diagnostic. DELETE this file after the site works (security).
 * Open in browser: https://yoursite.com/install-check.php
 */
header('Content-Type: text/plain; charset=UTF-8');

$root = dirname(__DIR__);
echo "PHP version: " . PHP_VERSION . "\n";
echo "pdo_mysql loaded: " . (extension_loaded('pdo_mysql') ? 'yes' : 'NO — enable in cPanel → Select PHP Version → Extensions') . "\n";
echo "gd loaded: " . (extension_loaded('gd') ? 'yes' : 'NO — needed for captcha') . "\n";
echo "vendor/autoload.php: " . (is_file($root . '/vendor/autoload.php') ? 'yes' : 'NO — run: composer install --no-dev') . "\n";
echo ".env readable: " . (is_readable($root . '/.env') ? 'yes' : 'NO — create .env next to bootstrap.php') . "\n\n";

if (!is_file($root . '/vendor/autoload.php')) {
    exit;
}

require $root . '/vendor/autoload.php';
if (is_readable($root . '/.env')) {
    Dotenv\Dotenv::createImmutable($root)->safeLoad();
}

echo "DB_HOST set: " . (trim((string) ($_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: '')) !== '' ? 'yes' : 'NO') . "\n";
echo "DB_DATABASE set: " . (trim((string) ($_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE') ?: '')) !== '' ? 'yes' : 'NO') . "\n\n";

if (!extension_loaded('pdo_mysql')) {
    exit;
}

try {
    $pdo = App\Database\ConnectionFactory::pdo();
    $pdo->query('SELECT 1');
    echo "Database: OK\n";
} catch (Throwable $e) {
    echo "Database: FAILED\n";
    echo $e->getMessage() . "\n";
}
