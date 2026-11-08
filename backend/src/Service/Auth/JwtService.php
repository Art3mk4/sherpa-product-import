<?php

declare(strict_types=1);

namespace App\Service\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

final class JwtService
{
    public function __construct(
        private readonly string $secret,
        private readonly int $ttl,
    ) {
    }

    /** @return array{token: string, expiresIn: int} */
    public function issue(string $subject): array
    {
        $now = time();
        $token = JWT::encode([
            'sub' => $subject,
            'iat' => $now,
            'exp' => $now + $this->ttl,
        ], $this->secret, 'HS256');

        return ['token' => $token, 'expiresIn' => $this->ttl];
    }

    /**
     * @return object{sub: string, iat: int, exp: int}
     */
    public function decode(string $token): object
    {
        return JWT::decode($token, new Key($this->secret, 'HS256'));
    }
}
