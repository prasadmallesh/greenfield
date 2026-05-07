<?php

declare(strict_types=1);

use App\App\RouteRegistrar;
use App\Database\ConnectionFactory;
use App\Http\SessionMiddleware;
use App\Outbound\OutboundCommunicationsGuard;
use Slim\Factory\AppFactory;

$projectRoot = dirname(__DIR__);

$envDebug = static function () use ($projectRoot): bool {
        if (getenv('APP_DEBUG') === '1' || strtolower((string) getenv('APP_DEBUG')) === 'true') {
            return true;
        }
        if (isset($_ENV['APP_DEBUG'])) {
            $v = strtolower(trim((string) $_ENV['APP_DEBUG']));
            if ($v === '1' || $v === 'true' || $v === 'yes') {
                return true;
            }
        }
        $path = $projectRoot . '/.env';
        if (!is_readable($path)) {
            return false;
        }
        $raw = @file_get_contents($path);
        if (!is_string($raw)) {
            return false;
        }

        return preg_match('/^\s*APP_DEBUG\s*=\s*["\']?1["\']?\s*(?:#.*)?$/mi', $raw) === 1;
    };

try {
    require $projectRoot . '/bootstrap.php';

    OutboundCommunicationsGuard::enforcePolicy();

    $pdo = ConnectionFactory::pdo();

    $app = AppFactory::create();
    $app->addRoutingMiddleware();
    $app->addErrorMiddleware(true, true, true);
    $app->add(new SessionMiddleware());

    RouteRegistrar::register($app, $pdo);

    $app->run();
} catch (Throwable $e) {
    error_log('[msoft-greenfield] ' . $e->getMessage() . "\n" . $e->getTraceAsString());

    if (!headers_sent()) {
        header('Content-Type: text/plain; charset=UTF-8');
    }
    http_response_code(500);

    if ($envDebug()) {
        echo "APP_DEBUG is on — showing this error only for troubleshooting. Turn APP_DEBUG off after fix.\n\n";
        echo $e->getMessage() . "\n\n";
        echo $e->getFile() . ':' . $e->getLine() . "\n";
    } else {
        echo "Application error (HTTP 500).\n\n";
        echo "1) Open install-check.php in the same folder as this file.\n";
        echo "2) In cPanel → Metrics → Errors (or error_log), look for lines starting with [msoft-greenfield].\n";
        echo "3) Temporarily add this line to .env next to bootstrap.php, reload the site once, then remove it:\n";
        echo "   APP_DEBUG=1\n";
    }
}
