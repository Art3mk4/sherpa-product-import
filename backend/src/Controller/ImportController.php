<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\NotFoundException;
use App\Http\JsonResponder;
use App\Http\Presenter\ImportJobPresenter;
use App\Repository\ImportJobRepository;
use App\Service\Import\ImportService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ImportController
{
    public function __construct(
        private readonly ImportService $importService,
        private readonly ImportJobRepository $jobs,
        private readonly ImportJobPresenter $presenter,
        private readonly JsonResponder $json,
    ) {
    }

    /** POST /api/imports (multipart, поле file) → 202 {id, status} */
    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $file = $request->getUploadedFiles()['file'] ?? null;

        $job = $this->importService->enqueue($file);

        return $this->json->respond($response, ['id' => $job->getId(), 'status' => $job->getStatus()], 202);
    }

    /** @param array<string, string> $args */
    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $job = $this->jobs->find((string) $args['id']);
        if ($job === null) {
            throw new NotFoundException('Задача импорта не найдена');
        }

        return $this->json->respond($response, $this->presenter->toArray($job));
    }

    /** Фронт использует для восстановления состояния после перезагрузки; 404 = импортов ещё не было. */
    public function latest(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $job = $this->jobs->findLatest();
        if ($job === null) {
            throw new NotFoundException('Импорты ещё не запускались');
        }

        return $this->json->respond($response, $this->presenter->toArray($job));
    }
}
