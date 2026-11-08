<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

final class NotFoundException extends RuntimeException
{
    public function __construct(string $message = 'Не найдено')
    {
        parent::__construct($message, 404);
    }
}
