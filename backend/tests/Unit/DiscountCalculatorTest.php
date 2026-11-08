<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\Import\DiscountCalculator;
use PHPUnit\Framework\TestCase;

final class DiscountCalculatorTest extends TestCase
{
    public function testExampleFromTask(): void
    {
        // (1320 − 880) / 1320 × 100 = 33.33
        self::assertSame('33.33', DiscountCalculator::calculate('1320.00', '880.00'));
    }

    public function testWithoutPurchaseIsNull(): void
    {
        self::assertNull(DiscountCalculator::calculate('1320.00', null));
    }

    public function testPurchaseAbovePriceIsNegative(): void
    {
        self::assertSame('-25.00', DiscountCalculator::calculate('1000.00', '1250.00'));
    }

    public function testEqualPrices(): void
    {
        self::assertSame('0.00', DiscountCalculator::calculate('500.00', '500.00'));
    }
}
