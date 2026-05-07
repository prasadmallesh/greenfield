<?php

declare(strict_types=1);

namespace App\Support;

use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Response;

final class HttpUtil
{
    public static function html(string $html, int $status = 200): ResponseInterface
    {
        $r = new Response($status);
        $r = $r->withHeader('Content-Type', 'text/html; charset=UTF-8');
        $r->getBody()->write($html);

        return $r;
    }

    public static function redirect(string $to, int $status = 302): ResponseInterface
    {
        return (new Response($status))->withHeader('Location', $to);
    }

    public static function emptyPdf(string $message): ResponseInterface
    {
        $r = new Response(404);
        $r->getBody()->write($message);

        return $r->withHeader('Content-Type', 'text/plain; charset=UTF-8');
    }
}
