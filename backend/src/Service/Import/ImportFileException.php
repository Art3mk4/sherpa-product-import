<?php

declare(strict_types=1);

namespace App\Service\Import;

use RuntimeException;

/**
 * Фатальная проблема файла импорта (нет обязательных столбцов и т.п.) —
 * задача целиком помечается failed с этим сообщением.
 */
final class ImportFileException extends RuntimeException
{
}
