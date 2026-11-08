<?php

declare(strict_types=1);

namespace App\Service\Import;

/**
 * Валидация уже смапленной строки. Ошибки не прерывают импорт —
 * попадают в отчёт задачи.
 */
final class RowValidator
{
    /** @return list<string> */
    public function validate(ProductImportDto $dto): array
    {
        $messages = $dto->parseErrors;

        if ($dto->externalCode === '') {
            $messages[] = 'Не указан внешний код';
        } elseif (mb_strlen($dto->externalCode) > 255) {
            $messages[] = 'Внешний код длиннее 255 символов';
        } elseif (preg_match('/[\x00-\x1F\x7F]/', $dto->externalCode) === 1) {
            $messages[] = 'Внешний код содержит управляющие символы';
        }

        if ($dto->name === '') {
            $messages[] = 'Не указано наименование';
        } elseif (mb_strlen($dto->name) > 500) {
            $messages[] = 'Наименование длиннее 500 символов';
        }

        if ($dto->price === null) {
            if ($dto->parseErrors === []) {
                $messages[] = 'Цена продажи не указана';
            }
        } elseif ((float) $dto->price <= 0.0) {
            $messages[] = 'Цена продажи должна быть больше нуля';
        }

        return $messages;
    }
}
