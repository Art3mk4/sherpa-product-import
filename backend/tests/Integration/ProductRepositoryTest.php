<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Product;
use App\Repository\ProductAttributeRepository;
use App\Repository\ProductImageRepository;
use App\Repository\ProductRepository;
use App\Service\ProductFilter;

final class ProductRepositoryTest extends IntegrationTestCase
{
    private ProductRepository $products;

    private ProductAttributeRepository $attributes;

    private ProductImageRepository $images;

    protected function setUp(): void
    {
        parent::setUp();
        $this->products = $this->container->get(ProductRepository::class);
        $this->attributes = $this->container->get(ProductAttributeRepository::class);
        $this->images = $this->container->get(ProductImageRepository::class);
    }

    public function testCrudLifecycle(): void
    {
        // create
        $product = new Product('CRUD-1', 'Тестовый товар', '999.00', 'Описание', '10.00');
        $product->replaceAttributes(['Размер' => '46']);
        $product->addImage(new \App\Entity\ProductImage($product, 'https://picsum.photos/seed/crud/600', null, 0));
        $this->products->save($product);
        $id = (int) $product->getId();
        self::assertGreaterThan(0, $id);

        // read
        $found = $this->products->findWithRelations($id);
        self::assertNotNull($found);
        self::assertSame('CRUD-1', $found->getExternalCode());
        self::assertSame('999.00', $found->getPrice());
        self::assertCount(1, $found->getAttributes());
        self::assertCount(1, $found->getImages());

        // update
        $found->updateFrom('CRUD-1', 'Новое имя', null, '1200.00', null);
        $this->products->save($found);
        $reread = $this->products->find($id);
        self::assertSame('Новое имя', $reread->getName());
        self::assertNull($reread->getDiscount());

        // delete
        $this->products->remove($reread);
        self::assertNull($this->products->find($id));
    }

    public function testPaginateWithNameAndPriceFilters(): void
    {
        foreach ($this->sampleProducts() as [$code, $name, $price]) {
            $this->products->save(new Product($code, $name, $price));
        }

        // поиск по имени, кириллица, регистронезависимо
        $result = $this->products->paginate(ProductFilter::fromQuery(['name' => 'легинсы']));
        self::assertCount(2, $result['items']);
        self::assertSame(2, $result['total']);

        // диапазон цены
        $result = $this->products->paginate(ProductFilter::fromQuery(['price_min' => '1000', 'price_max' => '2000']));
        self::assertSame(1, $result['total']);

        // пагинация: 2-я страница по 2 (ORDER BY id DESC: P-05..P-02.., вторая страница — P-03, P-02)
        $result = $this->products->paginate(ProductFilter::fromQuery(['page' => '2', 'limit' => '2']));
        self::assertCount(2, $result['items']);
        self::assertSame(5, $result['total']);
        self::assertSame('P-03', $result['items'][0]->getExternalCode());
    }

    public function testUpsertSameCodeUpdatesRow(): void
    {
        $dtoFirst = new \App\Service\Import\ProductImportDto('UPS-1', 'Первая версия', null, '500.00', null, ['К' => 'В'], []);
        $dtoSecond = new \App\Service\Import\ProductImportDto('UPS-1', 'Обновлённая версия', 'desc', '700.00', '10.00', ['К' => 'В2'], []);

        $id1 = $this->products->upsertFromImport($dtoFirst);
        $this->attributes->replaceForProduct($id1, $dtoFirst->attributes);
        $id2 = $this->products->upsertFromImport($dtoSecond);
        $this->attributes->replaceForProduct($id2, $dtoSecond->attributes);

        self::assertSame($id1, $id2, 'upsert по external_code не должен создавать дублей');

        $count = (int) $this->container->get(\Doctrine\DBAL\Connection::class)
            ->fetchOne('SELECT count(*) FROM products WHERE external_code = ?', ['UPS-1']);
        self::assertSame(1, $count);

        $stored = $this->products->find($id1);
        self::assertSame('Обновлённая версия', $stored->getName());
        self::assertSame('700.00', $stored->getPrice());
    }

    public function testSyncImagesReplacesAndKeepsDownloadedPath(): void
    {
        $product = new Product('IMG-1', 'Товар', '100.00');
        $this->products->save($product);
        $id = (int) $product->getId();

        $this->images->syncForProduct($id, [
            ['url' => 'http://x/a_m.jpg', 'path' => 'images/aa/a1.jpg'],
            ['url' => 'http://x/b_m.jpg', 'path' => 'images/bb/b1.jpg'],
        ]);
        $this->images->syncForProduct($id, [
            ['url' => 'http://x/a_m.jpg', 'path' => null],   // повторный импорт: не затираем скачанное
            ['url' => 'http://x/c_m.jpg', 'path' => null],
        ]);

        $rows = $this->container->get(\Doctrine\DBAL\Connection::class)
            ->fetchAllAssociative('SELECT url, path, position FROM product_images WHERE product_id = ? ORDER BY position', [$id]);

        self::assertSame([['url' => 'http://x/a_m.jpg', 'path' => 'images/aa/a1.jpg', 'position' => 0], ['url' => 'http://x/c_m.jpg', 'path' => null, 'position' => 1]], array_map(
            static fn (array $r): array => ['url' => $r['url'], 'path' => $r['path'], 'position' => (int) $r['position']],
            $rows,
        ));
    }

    /** @return list<array{string, string, string}> */
    private function sampleProducts(): array
    {
        return [
            ['P-01', 'Легинсы спортивные', '800.00'],
            ['P-02', 'Легинсы утеплённые', '1500.00'],
            ['P-03', 'Джинсы классические', '2500.00'],
            ['P-04', 'Футболка белая', '500.00'],
            ['P-05', 'Свитер вязаный', '3000.00'],
        ];
    }
}
