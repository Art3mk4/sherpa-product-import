<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\Import\PriceParser;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PriceParserTest extends TestCase
{
    /** @return non-empty-array<string, array{string, string|null}> */
    public static function prices(): array
    {
        return [
            'запятая как десятичный' => ['1320,00', '1320.00'],
            'пробел-тысячи' => ['1 320,50', '1320.50'],
            'nbsp-тысячи' => ["1\u{00A0}320,05", '1320.05'],
            'целое' => ['880', '880.00'],
            'пусто → null' => ['', null],
            'пробелы → null' => ['   ', null],
        ];
    }

    #[DataProvider('prices')]
    public function testParse(string $value, ?string $expected): void
    {
        self::assertSame($expected, PriceParser::parse($value));
    }

    /** @return non-empty-array<string, array{string}> */
    public static function garbage(): array
    {
        return [
            'текст' => ['abc'],
            'отрицательное' => ['-5'],
            'две запятые' => ['1,2,3'],
            'валюта' => ['1 320,00 руб.'],
            'точка+запятая' => ['1.320,00x'],
        ];
    }

    #[DataProvider('garbage')]
    public function testGarbageThrows(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        PriceParser::parse($value);
    }
}
