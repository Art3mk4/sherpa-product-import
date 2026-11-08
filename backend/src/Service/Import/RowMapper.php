<?php

declare(strict_types=1);

namespace App\Service\Import;

use InvalidArgumentException;

/**
 * Ассоциативный массив строки → ProductImportDto. Колонки со ссылками
 * сохраняются и как атрибуты (буквально по ТЗ: источник атрибутов — все
 * столбцы «Доп. поле»), и как изображения.
 */
final class RowMapper
{
    public const string COL_CODE = 'Внешний код';

    public const string COL_NAME = 'Наименование';

    public const string COL_DESCR = 'Описание';

    public const string COL_PRICE = 'Цена: Цена продажи';

    public const string COL_PURCHASE = 'Закупочная цена';

    public const string ATTR_PREFIX = 'Доп. поле:';

    /** Порядок = position изображения */
    private const array IMAGE_COLUMNS = ['Доп. поле: Ссылки на фото', 'Доп. поле: Ссылка на упаковку'];

    /**
     * @param array<string, string> $assoc
     */
    public function map(array $assoc, ImportReport $report, int $row): ProductImportDto
    {
        $code = trim((string) ($assoc[self::COL_CODE] ?? ''));
        $codeOrNull = $code !== '' ? $code : null;

        $attributes = [];
        foreach ($assoc as $header => $value) {
            $header = (string) $header;
            if (str_starts_with($header, self::ATTR_PREFIX) && trim((string) $value) !== '') {
                $key = trim(mb_substr($header, mb_strlen(self::ATTR_PREFIX)));
                $attributes[$key] = trim((string) $value);
            }
        }

        $imageUrls = [];
        foreach (self::IMAGE_COLUMNS as $column) {
            $imageUrls = [...$imageUrls, ...$this->extractUrls((string) ($assoc[$column] ?? ''), $report, $row, $codeOrNull)];
        }
        $imageUrls = array_values(array_unique($imageUrls));

        $parseErrors = [];
        $price = $this->parsePrice((string) ($assoc[self::COL_PRICE] ?? ''), 'Цена продажи', $parseErrors);
        $purchase = $this->parsePrice((string) ($assoc[self::COL_PURCHASE] ?? ''), 'Закупочная цена', $parseErrors);

        $discount = null;
        if ($price !== null && $price !== '0.00' && $purchase !== null) {
            $discount = DiscountCalculator::calculate($price, $purchase);
        }

        return new ProductImportDto(
            $code,
            trim((string) ($assoc[self::COL_NAME] ?? '')),
            $this->nullable((string) ($assoc[self::COL_DESCR] ?? '')),
            $price,
            $discount,
            $attributes,
            $imageUrls,
            $parseErrors,
        );
    }

    /** @param list<string> $parseErrors */
    private function parsePrice(string $value, string $field, array &$parseErrors): ?string
    {
        try {
            return PriceParser::parse($value);
        } catch (InvalidArgumentException $e) {
            $parseErrors[] = sprintf('%s: %s', $field, $e->getMessage());

            return null;
        }
    }

    /**
     * Разбиение "url1, url2; url3" + фильтр валидных http(s)-ссылок.
     *
     * @return list<string>
     */
    private function extractUrls(string $value, ImportReport $report, int $row, ?string $code): array
    {
        if (trim($value) === '') {
            return [];
        }

        $parts = preg_split('/[\s,;]+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $urls = [];
        foreach ($parts as $part) {
            $scheme = is_string(filter_var($part, FILTER_VALIDATE_URL))
                ? parse_url($part, PHP_URL_SCHEME)
                : false;

            if ($scheme === 'http' || $scheme === 'https') {
                $urls[] = $part;
                continue;
            }

            $report->addWarning($row, $code, sprintf('Невалидный URL изображения: "%s"', $part));
        }

        return array_values(array_unique($urls));
    }

    private function nullable(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
