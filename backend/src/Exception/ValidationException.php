<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

final class ValidationException extends RuntimeException
{
    /**
     * @param array<string, list<string>> $details поле => список сообщений
     */
    public function __construct(
        public readonly array $details = [],
        string $message = 'Ошибка валидации',
    ) {
        parent::__construct($message, 422);
    }
}
