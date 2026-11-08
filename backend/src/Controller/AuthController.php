<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\JsonResponder;
use App\Service\Auth\JwtService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpUnauthorizedException;

/**
 * POST /api/auth/login {email, password} → {token, expiresIn}.
 * Единственный админ живёт в .env — таблицы пользователей нет.
 */
final class AuthController
{
    /** @param array{email: string, password: string} $admin */
    public function __construct(
        private readonly JwtService $jwt,
        private readonly JsonResponder $json,
        private readonly array $admin,
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = (array) ($request->getParsedBody() ?: []);
        $email = (string) ($body['email'] ?? '');
        $password = (string) ($body['password'] ?? '');

        $valid = hash_equals($this->admin['email'], $email)
            && hash_equals($this->admin['password'], $password);

        if (!$valid) {
            throw new HttpUnauthorizedException($request, 'Неверный логин или пароль');
        }

        return $this->json->respond($response, $this->jwt->issue($email));
    }
}
