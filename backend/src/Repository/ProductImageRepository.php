<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

final class ProductImageRepository
{
    private readonly Connection $connection;

    public function __construct(private readonly EntityManagerInterface $em)
    {
        $this->connection = $em->getConnection();
    }

    /**
     * Синхронизация изображений товара по URL: старые удаляются, path у
     * существующих обновляется (COALESCE — не затирать скачанный файл null'ом),
     * файлы на диске не трогаем (ими могут пользоваться другие товары).
     *
     * @param list<array{url: string, path: string|null}> $images
     */
    public function syncForProduct(int $productId, array $images): void
    {
        if ($images === []) {
            $this->connection->executeStatement(
                'DELETE FROM product_images WHERE product_id = :pid',
                ['pid' => $productId],
            );

            return;
        }

        $urls = array_map(static fn (array $i): string => $i['url'], $images);

        $this->connection->executeStatement(
            'DELETE FROM product_images WHERE product_id = :pid AND url NOT IN (:urls)',
            ['pid' => $productId, 'urls' => $urls],
            ['urls' => ArrayParameterType::STRING],
        );

        $position = 0;
        foreach ($images as $image) {
            $this->connection->executeStatement(
                'INSERT INTO product_images (product_id, url, path, position)
                 VALUES (:pid, :url, :path, :pos)
                 ON CONFLICT (product_id, md5(url)) DO UPDATE
                     SET path = COALESCE(EXCLUDED.path, product_images.path),
                         position = EXCLUDED.position',
                [
                    'pid' => $productId,
                    'url' => $image['url'],
                    'path' => $image['path'],
                    'pos' => $position++,
                ],
            );
        }
    }
}
