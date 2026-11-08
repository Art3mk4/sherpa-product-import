<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

final class ConflictException extends RuntimeException
{
    public function __construct(string $message = 'Конфликт данных')
    {
        parent::__construct($message, 409);
    }
}
