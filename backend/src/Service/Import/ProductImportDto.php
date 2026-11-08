<?php

declare(strict_types=1);

namespace App\Service\Import;

/**
 * Результат маппинга строки xlsx. price/discount — нормализованные
 * decimal-строки ('1320.00'); parseErrors заполняет RowMapper при
 * непарсящихся ценах, RowValidator превращает их в сообщения отчёта.
 */
final readonly class ProductImportDto
{
    /**
     * @param array<string, string> $attributes
     * @param list<string>          $imageUrls
     * @param list<string>          $parseErrors
     */
    public function __construct(
        public string $externalCode,
        public string $name,
        public ?string $description,
        public ?string $price,
        public ?string $discount,
        public array $attributes,
        public array $imageUrls,
        public array $parseErrors = [],
    ) {
    }
}
