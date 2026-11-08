<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use App\Service\Import\ProductImportDto;
use App\Service\ProductFilter;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * Запись импорта — намеренно через DBAL (ON CONFLICT атомарен; долгоживущий
 * воркер после исключения в ORM-транзакции терял бы EntityManager).
 * ORM здесь — чтение и CRUD API.
 */
final class ProductRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function find(int $id): ?Product
    {
        return $this->em->find(Product::class, $id);
    }

    /**
     * Атрибуты подтягиваются fetch-join'ом, изображения — lazy (1 доп. запрос).
     * Два fetch-join коллекций одновременно не делаем (декартово произведение).
     */
    public function findWithRelations(int $id): ?Product
    {
        $result = $this->em->createQuery(
            'SELECT p, a FROM App\Entity\Product p LEFT JOIN p.attributes a WHERE p.id = :id',
        )->setParameter('id', $id)->getResult();

        $product = $result[0] ?? null;

        return $product instanceof Product ? $product : null;
    }

    /**
     * Пагинация + миниатюры одним доп. запросом (без N+1).
     *
     * @return array{items: list<Product>, total: int, thumbs: array<int, string|null>}
     */
    public function paginate(ProductFilter $filter): array
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('COUNT(p.id)')->from(Product::class, 'p');
        $this->applyConditions($qb, $filter);
        $total = (int) (int) $qb->getQuery()->getSingleScalarResult();

        $qb = $this->em->createQueryBuilder();
        $qb->select('p')->from(Product::class, 'p');
        $this->applyConditions($qb, $filter);
        $qb->orderBy('p.id', 'DESC')
            ->setFirstResult(($filter->page - 1) * $filter->limit)
            ->setMaxResults($filter->limit);

        /** @var list<Product> $items */
        $items = $qb->getQuery()->getResult();

        return [
            'items' => $items,
            'total' => $total,
            'thumbs' => $this->loadThumbnails($items),
        ];
    }

    /** Upsert по external_code; возвращает id товара. */
    public function upsertFromImport(ProductImportDto $dto): int
    {
        $id = $this->em->getConnection()->executeQuery(
            'INSERT INTO products (external_code, name, description, price, discount, created_at, updated_at)
             VALUES (:code, :name, :descr, :price, :discount, now(), now())
             ON CONFLICT (external_code) DO UPDATE
                 SET name = EXCLUDED.name,
                     description = EXCLUDED.description,
                     price = EXCLUDED.price,
                     discount = EXCLUDED.discount,
                     updated_at = now()
             RETURNING id',
            [
                'code' => $dto->externalCode,
                'name' => $dto->name,
                'descr' => $dto->description,
                'price' => $dto->price,
                'discount' => $dto->discount,
            ],
        )->fetchOne();

        return (int) $id;
    }

    public function save(Product $product): void
    {
        $this->em->persist($product);
        $this->em->flush();
    }

    public function remove(Product $product): void
    {
        $this->em->remove($product);
        $this->em->flush();
    }

    private function applyConditions(QueryBuilder $qb, ProductFilter $filter): void
    {
        if ($filter->name !== null && $filter->name !== '') {
            $qb->andWhere('LOWER(p.name) LIKE :name')
                ->setParameter('name', '%' . addcslashes(mb_strtolower($filter->name), '%_\\') . '%');
        }

        if ($filter->priceMin !== null) {
            $qb->andWhere('p.price >= :price_min')->setParameter('price_min', $filter->priceMin);
        }

        if ($filter->priceMax !== null) {
            $qb->andWhere('p.price <= :price_max')->setParameter('price_max', $filter->priceMax);
        }
    }

    /**
     * Миниатюра = первое изображение товара: публичный путь, если файл скачан,
     * иначе исходный url.
     *
     * @param list<Product> $items
     * @return array<int, string|null>
     */
    private function loadThumbnails(array $items): array
    {
        if ($items === []) {
            return [];
        }

        $ids = array_map(static fn (Product $p): int => (int) $p->getId(), $items);

        $rows = $this->em->getConnection()->executeQuery(
            'SELECT product_id, url, path FROM product_images
             WHERE product_id IN (:ids) AND position = 0
             ORDER BY id',
            ['ids' => $ids],
            ['ids' => ArrayParameterType::INTEGER],
        )->fetchAllAssociative();

        $thumbs = [];
        foreach ($rows as $row) {
            $productId = (int) $row['product_id'];
            if (array_key_exists($productId, $thumbs)) {
                continue;
            }
            $thumbs[$productId] = $row['path'] !== null ? '/storage/' . (string) $row['path'] : (string) $row['url'];
        }

        return $thumbs;
    }
}
