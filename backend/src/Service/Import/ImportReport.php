<?php

declare(strict_types=1);

namespace App\Service\Import;

/**
 * Накопитель ошибок и предупреждений импорта. Хранит не более $maxEntries
 * записей (счётчик ошибок продолжает расти) — иначе на 100k битых строк
 * JSON-отчёт раздувался бы.
 */
final class ImportReport
{
    /** @var list<array{row: int, externalCode: string|null, messages: list<string>}> */
    private array $errors = [];

    private int $errorCount = 0;

    /** @var list<array{row: int, externalCode: string|null, message: string}> */
    private array $warnings = [];

    public function __construct(private readonly int $maxEntries = 1000)
    {
    }

    /** @param list<string> $messages */
    public function addError(int $row, ?string $externalCode, array $messages): void
    {
        $this->errorCount++;
        if (count($this->errors) < $this->maxEntries) {
            $this->errors[] = ['row' => $row, 'externalCode' => $externalCode, 'messages' => $messages];
        }
    }

    public function addWarning(int $row, ?string $externalCode, string $message): void
    {
        if (count($this->warnings) < $this->maxEntries) {
            $this->warnings[] = ['row' => $row, 'externalCode' => $externalCode, 'message' => $message];
        }
    }

    public function errorCount(): int
    {
        return $this->errorCount;
    }

    /** @return list<array{row: int, externalCode: string|null, messages: list<string>}> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return list<array{row: int, externalCode: string|null, message: string}> */
    public function warnings(): array
    {
        return $this->warnings;
    }
}
