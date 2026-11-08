<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Entity\ImportJob;
use App\Message\ImportProductsMessage;
use App\Repository\ImportJobRepository;
use App\Repository\ProductAttributeRepository;
use App\Repository\ProductImageRepository;
use App\Repository\ProductRepository;
use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

/**
 * Импорт: enqueue() — валидация, сохранение файла, job и сообщение в очередь;
 * process() — работа воркера: потоковая обработка строк, скачивание картинок
 * ВНЕ транзакции, атомарная запись строки (upsert + атрибуты + изображения).
 */
final class ImportService
{
    private const int PROGRESS_EVERY = 5;

    public function __construct(
        private readonly UploadValidator $uploadValidator,
        private readonly XlsxReader $reader,
        private readonly ImageDownloader $downloader,
        private readonly ImportJobRepository $jobs,
        private readonly ProductRepository $products,
        private readonly ProductAttributeRepository $attributes,
        private readonly ProductImageRepository $images,
        private readonly Connection $connection,
        private readonly MessageBusInterface $bus,
        private readonly string $storagePath,
    ) {
    }

    /** @throws \App\Exception\ValidationException файл не прошёл проверку */
    public function enqueue(?\Psr\Http\Message\UploadedFileInterface $file): ImportJob
    {
        $this->uploadValidator->validate($file);

        $jobId = Uuid::uuid4()->toString();
        $dir = $this->storagePath . '/imports';
        if (!is_dir($dir)) {
            mkdir($dir, 0o775, true);
        }

        $path = $dir . '/' . $jobId . '.xlsx';
        $file->moveTo($path);

        $job = new ImportJob($jobId, (string) ($file->getClientFilename() ?? 'import.xlsx'), $path);
        $this->jobs->create($job);

        $this->bus->dispatch(new ImportProductsMessage($jobId));

        return $job;
    }

    public function process(string $jobId): void
    {
        $job = $this->jobs->findForWorker($jobId);

        // Идемпотентность при редоставке сообщения: обрабатываем только
        // pending и зависшие processing (upsert безопасен для повтора).
        if ($job === null || !in_array($job['status'], [ImportJob::STATUS_PENDING, ImportJob::STATUS_PROCESSING], true)) {
            return;
        }

        $path = $job['file_path'];
        if (!is_file($path)) {
            $this->jobs->finish($jobId, ImportJob::STATUS_FAILED, 0, 0, 0, [], [], 'Файл импорта не найден: ' . $path);

            return;
        }

        $report = new ImportReport(maxEntries: 1000);
        $processed = 0;
        $success = 0;

        try {
            // Проход 1: посчитать строки — для прогресс-бара
            $total = $this->reader->countRows($path);
            $this->jobs->markProcessing($jobId, $total);

            $mapper = new RowMapper();
            $validator = new RowValidator();
            $seenCodes = [];

            // Проход 2: обработка
            foreach ($this->reader->rows($path) as [$rowNumber, $assoc]) {
                $processed++;
                $code = null;

                try {
                    $dto = $mapper->map($assoc, $report, $rowNumber);
                    $code = $dto->externalCode !== '' ? $dto->externalCode : null;

                    $messages = $validator->validate($dto);
                    if ($messages !== []) {
                        $report->addError($rowNumber, $code, $messages);
                        continue;
                    }

                    if (isset($seenCodes[$dto->externalCode])) {
                        $report->addWarning(
                            $rowNumber,
                            $code,
                            sprintf('Дубликат внешнего кода в файле (строка %d), данные перезаписаны', $seenCodes[$dto->externalCode]),
                        );
                    }
                    $seenCodes[$dto->externalCode] = $rowNumber;

                    // Скачивание — строго вне транзакции
                    $downloaded = $this->downloader->downloadMany($dto->imageUrls, $report, $rowNumber, $code);
                    $imageList = [];
                    foreach ($dto->imageUrls as $url) {
                        $imageList[] = ['url' => $url, 'path' => $downloaded[$url] ?? null];
                    }

                    $this->connection->transactional(function () use ($dto, $imageList): void {
                        $productId = $this->products->upsertFromImport($dto);
                        $this->attributes->replaceForProduct($productId, $dto->attributes);
                        $this->images->syncForProduct($productId, $imageList);
                    });

                    $success++;
                } catch (Throwable $e) {
                    $report->addError($rowNumber, $code, ['Ошибка сохранения: ' . $e->getMessage()]);
                } finally {
                    if ($processed % self::PROGRESS_EVERY === 0) {
                        $this->jobs->updateProgress($jobId, $processed, $success, $report->errorCount());
                    }
                }
            }

            $this->jobs->finish(
                $jobId,
                ImportJob::STATUS_COMPLETED,
                $processed,
                $success,
                $report->errorCount(),
                $report->errors(),
                $report->warnings(),
                null,
            );
        } catch (Throwable $e) {
            $this->jobs->finish(
                $jobId,
                ImportJob::STATUS_FAILED,
                $processed,
                $success,
                $report->errorCount(),
                $report->errors(),
                $report->warnings(),
                $e->getMessage(),
            );
        } finally {
            @unlink($path);   // иначе storage/imports растёт бесконечно
        }
    }
}
