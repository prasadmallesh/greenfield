<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

final class CustomerAuthMiddleware implements MiddlewareInterface
{
    /**
     * @param list<string> $publicPaths exact paths (no trailing slash required; both /shop and /shop/ match)
     */
    public function __construct(
        private string $loginPath,
        private array $publicPaths,
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

        if (!isset($_SESSION['customer_partyid']) || (string) $_SESSION['customer_partyid'] === '') {
            return (new Response(302))->withHeader('Location', $this->loginPath);
        }

        return $handler->handle($request);
    }
}
