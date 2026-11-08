<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Service\Auth\JwtService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpUnauthorizedException;
use Throwable;

final class JwtAuthMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly JwtService $jwt)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $header = $request->getHeaderLine('Authorization');
        $token = '';
        if (preg_match('/^Bearer\s+(.+)$/i', $header, $m) === 1) {
            $token = trim($m[1]);
        }

        if ($token === '') {
            throw new HttpUnauthorizedException($request, 'Требуется заголовок Authorization: Bearer <token>');
        }

        try {
            $claims = $this->jwt->decode($token);
        } catch (Throwable) {
            throw new HttpUnauthorizedException($request, 'Неверный или просроченный токен');
        }

        return $handler->handle($request->withAttribute('user', (string) $claims->sub));
    }
}
