<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ImportProductsMessage;
use App\Service\Import\ImportService;
use Doctrine\DBAL\Connection;
use Throwable;

/**
 * Обработчик не пробрасывает исключения: ImportService сам помечает задачу
 * failed, а проброс включил бы бесконечные ретраи, которые здесь не нужны —
 * повторный импорт запускает пользователь.
 */
final class ImportProductsHandler
{
    public function __construct(
        private readonly ImportService $importService,
        private readonly Connection $connection,
    ) {
    }

    public function __invoke(ImportProductsMessage $message): void
    {
        // Соединение могло умереть за часы простоя — «пинг», DBAL переподключится лениво
        try {
            $this->connection->executeQuery('SELECT 1');
        } catch (Throwable) {
            $this->connection->close();
        }

        try {
            $this->importService->process($message->jobId);
        } catch (Throwable $e) {
            error_log(sprintf('Import job %s crashed: %s', $message->jobId, $e->getMessage()));
        }
    }
}
