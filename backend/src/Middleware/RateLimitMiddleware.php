<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Exception\TooManyRequestsException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Ключ — пользователь из JWT (кладёт JwtAuthMiddleware): REMOTE_ADDR за
 * nginx — это IP nginx-контейнера, он не различает клиентов. Этот middleware
 * вешается на маршрут (исполняется ПОСЛЕ group-мiddleware с JWT) — атрибут
 * user уже доступен.
 */
final class RateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly RateLimiterFactory $factory)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $key = 'import_' . (is_string($user) && $user !== ''
            ? $user
            : (string) ($request->getServerParams()['REMOTE_ADDR'] ?? 'anon'));

        $limiter = $this->factory->create($key);
        $limit = $limiter->consume(1);

        if (!$limit->isAccepted()) {
            $retryAfter = max(1, $limit->getRetryAfter()->getTimestamp() - time());
            throw new TooManyRequestsException($retryAfter);
        }

        $response = $handler->handle($request);

        return $response
            ->withHeader('X-RateLimit-Remaining', (string) $limit->getRemainingTokens())
            ->withHeader('X-RateLimit-Limit', (string) $limit->getLimit());
    }
}
