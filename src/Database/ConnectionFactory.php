<?php

declare(strict_types=1);

namespace App\Database;

final class ConnectionFactory
{
    public static function pdo(): \PDO
    {
        $host = trim((string) ($_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: ''));
        $port = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: '3306';
        $db = $_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE') ?: '';
        $user = $_ENV['DB_USERNAME'] ?? getenv('DB_USERNAME') ?: '';
        $pass = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: '';

        if ($host === '') {
            throw new \RuntimeException(
                'Set DB_HOST in .env. Your database is on the server: use DB_HOST=localhost when PHP runs on cPanel ' .
                '(same machine as MySQL). If you use php -S on your PC, use the remote MySQL hostname from cPanel ' .
                'and add your public IP under cPanel → Remote MySQL. See HOSTING_DATABASE.md in this project.'
            );
        }

        if ($db === '' || $user === '') {
            throw new \RuntimeException('Set DB_DATABASE and DB_USERNAME in .env (see .env.example).');
        }

        if (!\extension_loaded('pdo_mysql')) {
            throw new \RuntimeException(
                'PHP extension pdo_mysql is not enabled, so PDO cannot connect to MySQL ' .
                '("could not find driver"). In cPanel: Software → Select PHP Version → Extensions, ' .
                'enable pdo_mysql (and mysqli if you use it elsewhere). See ENABLE_PHP_MYSQL.md in this project.'
            );
        }

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $db);

        $pdo = new \PDO($dsn, $user, $pass, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);

        return $pdo;
    }
}
