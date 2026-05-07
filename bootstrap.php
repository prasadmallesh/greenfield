<?php

declare(strict_types=1);

$root = __DIR__;

if (!is_file($root . '/vendor/autoload.php')) {
    throw new RuntimeException('Run composer install in ' . $root);
}

require $root . '/vendor/autoload.php';

if (is_readable($root . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable($root);
    $dotenv->safeLoad();
}
