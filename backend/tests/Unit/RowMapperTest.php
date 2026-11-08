<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\Import\ImportReport;
use App\Service\Import\RowMapper;
use PHPUnit\Framework\TestCase;

final class RowMapperTest extends TestCase
{
    private RowMapper $mapper;

    private ImportReport $report;

    protected function setUp(): void
    {
        $this->mapper = new RowMapper();
        $this->report = new ImportReport();
    }

    public function testMapsCoreFieldsAndPrice(): void
    {
        $dto = $this->mapper->map($this->row(), $this->report, 2);

        self::assertSame('AN-AZxf2i24obi-6ehzeU0', $dto->externalCode);
        self::assertSame('Легинсы женские', $dto->name);
        self::assertSame('Тёплые легинсы', $dto->description);
        self::assertSame('1320.00', $dto->price);
        self::assertSame('33.33', $dto->discount);
    }

    public function testAttributesFromDopPolePrefixWithoutEmpty(): void
    {
        $row = $this->row();
        $row['Доп. поле: Размер'] = '46(M)';
        $row['Доп. поле: seo h1'] = '';        // пустое — не сохраняем
        $row['Доп. поле: Цвет'] = 'чёрный';

        $dto = $this->mapper->map($row, $this->report, 2);

        self::assertSame(
            ['Размер' => '46(M)', 'Цвет' => 'чёрный'],
            $dto->attributes,
        );
    }

    public function testImageUrlsSplitAndDeduped(): void
    {
        $row = $this->row();
        $row['Доп. поле: Ссылки на фото'] = 'http://cdn.example/a_m.jpg, http://cdn.example/b_m.jpg; http://cdn.example/a_m.jpg';
        $row['Доп. поле: Ссылка на упаковку'] = 'http://cdn.example/pack_m.jpg';

        $dto = $this->mapper->map($row, $this->report, 2);

        self::assertSame([
            'http://cdn.example/a_m.jpg',
            'http://cdn.example/b_m.jpg',
            'http://cdn.example/pack_m.jpg',
        ], $dto->imageUrls);
    }

    public function testInvalidUrlBecomesWarning(): void
    {
        $row = $this->row();
        $row['Доп. поле: Ссылки на фото'] = 'not-an-url, http://cdn.example/ok_m.jpg';

        $dto = $this->mapper->map($row, $this->report, 5);

        self::assertSame(['http://cdn.example/ok_m.jpg'], $dto->imageUrls);
        self::assertCount(1, $this->report->warnings());
        self::assertSame(5, $this->report->warnings()[0]['row']);
    }

    public function testBrokenPriceGoesToParseErrors(): void
    {
        $row = $this->row();
        $row['Цена: Цена продажи'] = 'abc';

        $dto = $this->mapper->map($row, $this->report, 7);

        self::assertNull($dto->price);
        self::assertNotEmpty($dto->parseErrors);
    }

    /** @return array<string, string> */
    private function row(): array
    {
        return [
            'Внешний код' => 'AN-AZxf2i24obi-6ehzeU0',
            'Наименование' => 'Легинсы женские',
            'Описание' => 'Тёплые легинсы',
            'Цена: Цена продажи' => '1320,00',
            'Закупочная цена' => '880,00',
            'Тип' => 'Товар',
        ];
    }
}
