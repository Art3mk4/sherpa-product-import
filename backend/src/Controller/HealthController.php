<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\JsonResponder;
use App\Service\HealthService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class HealthController
{
    public function __construct(
        private readonly HealthService $health,
        private readonly JsonResponder $json,
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $result = $this->health->check();

        return $this->json->respond($response, $result, $result['status'] === 'ok' ? 200 : 503);
    }
}
