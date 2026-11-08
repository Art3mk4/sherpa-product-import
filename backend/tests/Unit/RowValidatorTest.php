<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\Import\ProductImportDto;
use App\Service\Import\RowValidator;
use PHPUnit\Framework\TestCase;

final class RowValidatorTest extends TestCase
{
    private RowValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new RowValidator();
    }

    public function testValidRowHasNoMessages(): void
    {
        self::assertSame([], $this->validator->validate($this->dto()));
    }

    public function testEmptyCodeAndName(): void
    {
        $messages = $this->validator->validate($this->dto(externalCode: '', name: ''));

        self::assertContains('Не указан внешний код', $messages);
        self::assertContains('Не указано наименование', $messages);
    }

    public function testZeroPriceRejected(): void
    {
        $messages = $this->validator->validate($this->dto(price: '0.00'));

        self::assertContains('Цена продажи должна быть больше нуля', $messages);
    }

    public function testMissingPriceWithoutParseError(): void
    {
        $messages = $this->validator->validate($this->dto(price: null));

        self::assertContains('Цена продажи не указана', $messages);
    }

    public function testParseErrorsAreCarriedOver(): void
    {
        $messages = $this->validator->validate($this->dto(parseErrors: ['Цена продажи: Некорректная цена: "abc"']));

        self::assertSame(['Цена продажи: Некорректная цена: "abc"'], $messages);
    }

    /** @param list<string> $parseErrors */
    private function dto(
        string $externalCode = 'CODE-1',
        string $name = 'Товар',
        ?string $price = '100.00',
        array $parseErrors = [],
    ): ProductImportDto {
        return new ProductImportDto($externalCode, $name, null, $price, '10.00', [], [], $parseErrors);
    }
}
