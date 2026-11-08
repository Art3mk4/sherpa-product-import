<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\ValidationException;

final readonly class ProductFilter
{
    public function __construct(
        public int $page = 1,
        public int $limit = 20,
        public ?string $name = null,
        public ?float $priceMin = null,
        public ?float $priceMax = null,
    ) {
    }

    /**
     * @param array<string, mixed> $query
     */
    public static function fromQuery(array $query): self
    {
        /** @var array<string, list<string>> $errors */
        $errors = [];

        $page = self::intField($query['page'] ?? '1', 'page', $errors, min: 1) ?? 1;
        $limit = self::intField($query['limit'] ?? '20', 'limit', $errors, min: 1, max: 100) ?? 20;

        $name = isset($query['name']) ? trim((string) $query['name']) : '';
        if ($name === '') {
            $name = null;
        }

        $priceMin = self::floatField($query['price_min'] ?? null, 'price_min', $errors);
        $priceMax = self::floatField($query['price_max'] ?? null, 'price_max', $errors);

        if ($priceMin !== null && $priceMax !== null && $priceMin > $priceMax) {
            $errors['price_max'] = ['«Цена до» должна быть не меньше «Цена от»'];
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return new self($page, $limit, $name, $priceMin, $priceMax);
    }

    /** @param array<string, list<string>> $errors */
    private static function intField(
        mixed $value,
        string $field,
        array &$errors,
        ?int $min = null,
        ?int $max = null,
    ): ?int {
        $int = filter_var($value, FILTER_VALIDATE_INT);
        if ($int === false) {
            $errors[$field] = [sprintf('Параметр «%s» должен быть целым числом', $field)];

            return null;
        }

        if ($min !== null && $int < $min) {
            $errors[$field] = [sprintf('Параметр «%s» должен быть не меньше %d', $field, $min)];

            return null;
        }

        if ($max !== null && $int > $max) {
            $errors[$field] = [sprintf('Параметр «%s» должен быть не больше %d', $field, $max)];

            return null;
        }

        return $int;
    }

    /** @param array<string, list<string>> $errors */
    private static function floatField(mixed $value, string $field, array &$errors): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            $errors[$field] = [sprintf('Параметр «%s» должен быть числом', $field)];

            return null;
        }

        $float = (float) $value;
        if ($float < 0) {
            $errors[$field] = [sprintf('Параметр «%s» не может быть отрицательным', $field)];

            return null;
        }

        return $float;
    }
}
