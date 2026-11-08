<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

final class ProductAttributeRepository
{
    private readonly Connection $connection;

    public function __construct(private readonly EntityManagerInterface $em)
    {
        $this->connection = $em->getConnection();
    }

    /**
     * Полная замена атрибутов товара: DELETE + один multi-row INSERT.
     *
     * @param array<string, string> $kv
     */
    public function replaceForProduct(int $productId, array $kv): void
    {
        $this->connection->executeStatement(
            'DELETE FROM product_attributes WHERE product_id = :pid',
            ['pid' => $productId],
        );

        if ($kv === []) {
            return;
        }

        $placeholders = [];
        $params = ['pid' => $productId];
        $index = 0;
        foreach ($kv as $key => $value) {
            $placeholders[] = sprintf('(:pid, :k%d, :v%d)', $index, $index);
            $params['k' . $index] = (string) $key;
            $params['v' . $index] = (string) $value;
            $index++;
        }

        $this->connection->executeStatement(
            'INSERT INTO product_attributes (product_id, key, value) VALUES ' . implode(', ', $placeholders),
            $params,
        );
    }
}
