<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'product_images')]
class ProductImage
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'images')]
    #[ORM\JoinColumn(name: 'product_id', onDelete: 'CASCADE', nullable: false)]
    private Product $product;

    /** Исходная ссылка из xlsx */
    #[ORM\Column(type: 'string', length: 2048)]
    private string $url;

    /** Локальный путь относительно storage; NULL = не удалось скачать */
    #[ORM\Column(type: 'string', length: 512, nullable: true)]
    private ?string $path;

    #[ORM\Column(type: 'smallint')]
    private int $position;

    public function __construct(Product $product, string $url, ?string $path, int $position = 0)
    {
        $this->product = $product;
        $this->url = $url;
        $this->path = $path;
        $this->position = $position;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    /** Публичный URL для отдачи через nginx; null, если файл не скачан */
    public function publicUrl(): ?string
    {
        return $this->path === null ? null : '/storage/' . $this->path;
    }
}
