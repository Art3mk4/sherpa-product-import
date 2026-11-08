<?php

declare(strict_types=1);

namespace App\Service\Import;

/**
 * discount = (Цена продажи − Закупочная цена) / Цена продажи × 100.
 * Может быть отрицательной (закупка дороже продажи) — это допустимо.
 * Точности float при округлении до 2 знаков достаточно, bcmath не тянем.
 */
final class DiscountCalculator
{
    public static function calculate(string $price, ?string $purchasePrice): ?string
    {
        if ($purchasePrice === null) {
            return null;
        }

        $p = (float) $price;
        $c = (float) $purchasePrice;

        return number_format(($p - $c) / $p * 100, 2, '.', '');
    }
}
