<?php

declare(strict_types=1);

namespace App\Http;

use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Exception\TooManyRequestsException;
use App\Exception\ValidationException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Exception\HttpUnauthorizedException;
use Slim\Interfaces\ErrorHandlerInterface;
use Slim\Psr7\Response;
use Throwable;

/**
 * Все ошибки уходят клиенту единым JSON-форматом:
 * {"error": {"code": "...", "message": "...", "details": {...}}}
 */
final class ErrorHandler implements ErrorHandlerInterface
{
    public function __construct(private readonly JsonResponder $json)
    {
    }

    public function __invoke(
        ServerRequestInterface $request,
        Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails,
    ): ResponseInterface {
        if ($exception instanceof ValidationException) {
            return $this->error(422, 'validation_failed', $exception->getMessage(), $exception->details);
        }

        if ($exception instanceof NotFoundException || $exception instanceof HttpNotFoundException) {
            return $this->error(404, 'not_found', 'Не найдено');
        }

        if ($exception instanceof HttpMethodNotAllowedException) {
            return $this->error(405, 'method_not_allowed', 'Метод не разрешён для этого ресурса');
        }

        if ($exception instanceof HttpUnauthorizedException) {
            return $this->error(401, 'unauthorized', $exception->getMessage() !== '' ? $exception->getMessage() : 'Не авторизован');
        }

        if ($exception instanceof ConflictException) {
            return $this->error(409, 'conflict', $exception->getMessage());
        }

        if ($exception instanceof TooManyRequestsException) {
            return $this
                ->error(429, 'too_many_requests', 'Слишком много запросов, повторите позже')
                ->withHeader('Retry-After', (string) max(1, $exception->retryAfter));
        }

        error_log(sprintf(
            'Unhandled %s: %s in %s:%d',
            $exception::class,
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
        ));

        return $this->error(
            500,
            'internal_error',
            $displayErrorDetails ? $exception->getMessage() : 'Internal Server Error',
        );
    }

    /**
     * @param array<string, list<string>>|null $details
     */
    private function error(int $status, string $code, string $message, ?array $details = null): ResponseInterface
    {
        $body = ['error' => ['code' => $code, 'message' => $message]];
        if ($details !== null) {
            $body['error']['details'] = $details;
        }

        return $this->json->respond(new Response(), $body, $status);
    }
}
