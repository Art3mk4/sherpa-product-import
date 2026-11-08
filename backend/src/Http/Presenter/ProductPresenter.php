<?php

declare(strict_types=1);

namespace App\Http\Presenter;

use App\Entity\Product;

final class ProductPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function listItem(Product $product, string|null $image): array
    {
        return [
            'id' => $product->getId(),
            'externalCode' => $product->getExternalCode(),
            'name' => $product->getName(),
            'price' => (float) $product->getPrice(),
            'discount' => $product->getDiscount() === null ? null : (float) $product->getDiscount(),
            'image' => $image,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(Product $product): array
    {
        $attributes = [];
        foreach ($product->getAttributes() as $attribute) {
            $attributes[] = [
                'id' => $attribute->getId(),
                'key' => $attribute->getKey(),
                'value' => $attribute->getValue(),
            ];
        }

        $images = [];
        foreach ($product->getImages() as $image) {
            $images[] = [
                'id' => $image->getId(),
                'url' => $image->getUrl(),
                'path' => $image->getPath(),
                'publicUrl' => $image->publicUrl(),
            ];
        }

        return [
            'id' => $product->getId(),
            'externalCode' => $product->getExternalCode(),
            'name' => $product->getName(),
            'description' => $product->getDescription(),
            'price' => (float) $product->getPrice(),
            'discount' => $product->getDiscount() === null ? null : (float) $product->getDiscount(),
            'createdAt' => $product->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $product->getUpdatedAt()->format(DATE_ATOM),
            'attributes' => $attributes,
            'images' => $images,
        ];
    }
}
