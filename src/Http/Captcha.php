<?php

declare(strict_types=1);

namespace App\Http;

use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Response;

final class Captcha
{
    public static function pngResponse(string $sessionKey): ResponseInterface
    {
        if (session_status() !== \PHP_SESSION_ACTIVE) {
            session_start();
        }
        $code = (string) random_int(1000, 9999);
        $_SESSION[$sessionKey] = $code;

        $im = imagecreatetruecolor(50, 24);
        if ($im === false) {
            $r = new Response(500);
            $r->getBody()->write('GD extension required for captcha.');

            return $r->withHeader('Content-Type', 'text/plain; charset=UTF-8');
        }
        $bg = imagecolorallocate($im, 22, 86, 165);
        $fg = imagecolorallocate($im, 255, 255, 255);
        imagefill($im, 0, 0, $bg);
        imagestring($im, 5, 5, 5, $code, $fg);
        ob_start();
        imagepng($im);
        imagedestroy($im);
        $png = (string) ob_get_clean();

        $res = new Response(200);
        $res = $res->withHeader('Content-Type', 'image/png')->withHeader('Cache-Control', 'no-cache, must-revalidate');
        $res->getBody()->write($png);

        return $res;
    }

    public static function verify(string $sessionKey, string $userInput): bool
    {
        if (session_status() !== \PHP_SESSION_ACTIVE) {
            session_start();
        }
        $expected = $_SESSION[$sessionKey] ?? null;

        return $expected !== null && (string) $expected === trim($userInput);
    }
}
