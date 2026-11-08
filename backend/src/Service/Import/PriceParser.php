<?php

declare(strict_types=1);

namespace App\Service\Import;

use InvalidArgumentException;

/**
 * Цены в выгрузке приходят как "1320,00": запятая — десятичный разделитель,
 * пробелы и NBSP — разделители тысяч.
 */
final class PriceParser
{
    /** '' → null; мусор → InvalidArgumentException; иначе '1320.00'. */
    public static function parse(string $value): ?string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $normalized = str_replace([' ', "\u{00A0}"], '', $trimmed);
        $normalized = str_replace(',', '.', $normalized);

        if (!preg_match('/^\d+(\.\d{1,2})?$/', $normalized)) {
            throw new InvalidArgumentException(sprintf('Некорректная цена: "%s"', $trimmed));
        }

        return number_format((float) $normalized, 2, '.', '');
    }
}
