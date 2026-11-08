<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Команда воркеру: обработать задачу импорта $jobId.
 */
final readonly class ImportProductsMessage
{
    public function __construct(public string $jobId)
    {
    }
}
