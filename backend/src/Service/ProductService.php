<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Product;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Repository\ProductRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final class ProductService
{
    public function __construct(private readonly ProductRepository $products)
    {
    }

    /**
     * @return array{items: list<Product>, total: int, thumbs: array<int, string|null>}
     */
    public function list(ProductFilter $filter): array
    {
        return $this->products->paginate($filter);
    }

    public function get(int $id): Product
    {
        $product = $this->products->findWithRelations($id);
        if ($product === null) {
            throw new NotFoundException(sprintf('Товар %d не найден', $id));
        }

        return $product;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): Product
    {
        [$code, $name, $description, $price, $discount, $attributes] = $this->validatePayload($data);

        $product = new Product($code, $name, $price, $description, $discount);
        $product->replaceAttributes($attributes);

        try {
            $this->products->save($product);
        } catch (UniqueConstraintViolationException) {
            // После исключения в flush Doctrine сам закрывает EM; в проде каждый
            // HTTP-запрос получает свежий EM из нового контейнера.
            throw new ConflictException(sprintf('Внешний код "%s" уже занят другим товаром', $code));
        }

        return $product;
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): Product
    {
        $product = $this->get($id);
        [$code, $name, $description, $price, $discount, $attributes] = $this->validatePayload($data);

        $product->updateFrom($code, $name, $description, $price, $discount);
        $product->replaceAttributes($attributes);

        try {
            $this->products->save($product);
        } catch (UniqueConstraintViolationException) {
            throw new ConflictException(sprintf('Внешний код "%s" уже занят другим товаром', $code));
        }

        return $product;
    }

    public function delete(int $id): void
    {
        $product = $this->get($id);
        $this->products->remove($product);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{0: string, 1: string, 2: string|null, 3: string, 4: string|null, 5: array<string, string>}
     */
    private function validatePayload(array $data): array
    {
        /** @var array<string, list<string>> $errors */
        $errors = [];

        $code = trim((string) ($data['externalCode'] ?? ''));
        if ($code === '') {
            $errors['externalCode'] = ['Не указан внешний код'];
        } elseif (mb_strlen($code) > 255) {
            $errors['externalCode'] = ['Внешний код длиннее 255 символов'];
        }

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            $errors['name'] = ['Не указано наименование'];
        } elseif (mb_strlen($name) > 500) {
            $errors['name'] = ['Наименование длиннее 500 символов'];
        }

        $description = trim((string) ($data['description'] ?? ''));
        $description = $description === '' ? null : $description;

        $priceRaw = $data['price'] ?? null;
        $price = null;
        if (!is_numeric($priceRaw)) {
            $errors['price'] = ['Цена должна быть числом'];
        } else {
            $priceValue = (float) $priceRaw;
            if ($priceValue <= 0) {
                $errors['price'] = ['Цена должна быть больше нуля'];
            } else {
                $price = number_format($priceValue, 2, '.', '');
            }
        }

        $discount = null;
        if (isset($data['discount']) && $data['discount'] !== '') {
            if (!is_numeric($data['discount'])) {
                $errors['discount'] = ['Скидка должна быть числом (проценты)'];
            } else {
                $discount = number_format((float) $data['discount'], 2, '.', '');
            }
        }

        /** @var array<string, string> $attributes */
        $attributes = [];
        if (array_key_exists('attributes', $data) && $data['attributes'] !== null) {
            if (!is_array($data['attributes'])) {
                $errors['attributes'] = ['Атрибуты должны быть списком {key, value}'];
            } else {
                foreach ($data['attributes'] as $i => $attribute) {
                    $key = trim((string) ($attribute['key'] ?? ''));
                    $value = trim((string) ($attribute['value'] ?? ''));
                    if ($key === '') {
                        $errors['attributes'] = [sprintf('Атрибут #%d: пустой ключ', $i + 1)];
                        continue;
                    }
                    $attributes[$key] = $value;
                }
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [$code, $name, $description, $price ?? '0.00', $discount, $attributes];
    }
}
