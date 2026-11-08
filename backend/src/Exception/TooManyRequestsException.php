<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

final class TooManyRequestsException extends RuntimeException
{
    public function __construct(public readonly int $retryAfter)
    {
        parent::__construct('Слишком много запросов', 429);
    }
}
