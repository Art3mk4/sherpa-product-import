<?php

declare(strict_types=1);

namespace App\Service\Import;

use DateTimeInterface;
use Generator;
use OpenSpout\Reader\XLSX\Reader;

/**
 * Потоковое чтение xlsx (openspout): файл никогда не грузится в память целиком.
 * Сопоставление столбцов — по имени заголовка, не по индексу.
 */
final class XlsxReader
{
    private const array REQUIRED_HEADERS = ['Внешний код', 'Наименование', 'Цена: Цена продажи'];

    /**
     * @return Generator<int, array{int, array<string, string>}, mixed, void>
     *         yield [номер строки листа (заголовок = 1), ассоциативный массив заголовок => значение]
     */
    public function rows(string $path): Generator
    {
        $reader = new Reader();
        $reader->open($path);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {   // только первый лист
                $headers = null;
                $rowNumber = 0;

                foreach ($sheet->getRowIterator() as $row) {
                    $rowNumber++;
                    $cells = array_map(self::cellToString(...), $row->toArray());

                    if ($headers === null) {
                        $headers = array_map($this->normalizeHeader(...), $cells);
                        $this->assertRequiredHeaders($headers);
                        continue;
                    }

                    if (implode('', $cells) === '') {
                        continue;   // пустая строка
                    }

                    $cells = array_pad($cells, count($headers), '');
                    /** @var array<string, string> $assoc */
                    $assoc = array_combine($headers, array_slice($cells, 0, count($headers)));

                    yield [$rowNumber, $assoc];
                }

                break;   // остальные листы игнорируем
            }
        } finally {
            $reader->close();
        }
    }

    /** Первый проход — сколько строк данных ждём (для прогресса). */
    public function countRows(string $path): int
    {
        return iterator_count($this->rows($path));
    }

    /**
     * Значение ячейки → строку: даты (openspout возвращает DateTimeImmutable),
     * float без научной записи и хвостовых нулей, null → ''.
     */
    private static function cellToString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_float($value)) {
            $formatted = number_format($value, 6, '.', '');

            return rtrim(rtrim($formatted, '0'), '.');
        }

        return trim((string) $value);
    }

    /** NBSP → пробел, схлопывание пробельных последовательностей. */
    private function normalizeHeader(string $header): string
    {
        $normalized = str_replace("\u{00A0}", ' ', $header);

        return (string) preg_replace('/\s+/u', ' ', trim($normalized));
    }

    /** @param list<string> $headers */
    private function assertRequiredHeaders(array $headers): void
    {
        $missing = array_diff(self::REQUIRED_HEADERS, $headers);
        if ($missing !== []) {
            throw new ImportFileException('Отсутствуют обязательные столбцы: ' . implode(', ', $missing));
        }
    }
}
