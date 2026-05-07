<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

final class StaffAuthMiddleware implements MiddlewareInterface
{
    /**
     * @param list<string> $publicPaths exact paths (and path + '/')
     */
    public function __construct(
        private string $loginPath,
        private array $publicPaths = [],
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        foreach ($this->publicPaths as $pub) {
            if ($pub !== '' && ($path === $pub || $path === $pub . '/')) {
                return $handler->handle($request);
            }
        }

        if (!isset($_SESSION['login_user_id']) || (int) $_SESSION['login_user_id'] < 1) {
            return (new Response(302))->withHeader('Location', $this->loginPath);
        }

        return $handler->handle($request);
    }
}
