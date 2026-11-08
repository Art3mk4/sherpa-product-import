<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'products')]
class Product
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $externalCode;

    #[ORM\Column(type: 'string', length: 500)]
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /** Doctrine возвращает decimal строкой — так и храним */
    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private string $price;

    /** Скидка в %; может быть отрицательной, если закупка дороже продажи */
    #[ORM\Column(type: 'decimal', precision: 7, scale: 2, nullable: true)]
    private ?string $discount = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    /** @var Collection<int, ProductAttribute> */
    #[ORM\OneToMany(
        mappedBy: 'product',
        targetEntity: ProductAttribute::class,
        cascade: ['persist'],
        orphanRemoval: true,
    )]
    private Collection $attributes;

    /** @var Collection<int, ProductImage> */
    #[ORM\OneToMany(
        mappedBy: 'product',
        targetEntity: ProductImage::class,
        cascade: ['persist'],
        orphanRemoval: true,
    )]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $images;

    public function __construct(
        string $externalCode,
        string $name,
        string $price,
        ?string $description = null,
        ?string $discount = null,
    ) {
        $this->externalCode = $externalCode;
        $this->name = $name;
        $this->price = $price;
        $this->description = $description;
        $this->discount = $discount;
        $now = new DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->attributes = new ArrayCollection();
        $this->images = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getExternalCode(): string
    {
        return $this->externalCode;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getPrice(): string
    {
        return $this->price;
    }

    public function getDiscount(): ?string
    {
        return $this->discount;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return Collection<int, ProductAttribute> */
    public function getAttributes(): Collection
    {
        return $this->attributes;
    }

    /** @return Collection<int, ProductImage> */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function updateFrom(
        string $externalCode,
        string $name,
        ?string $description,
        string $price,
        ?string $discount,
    ): void {
        $this->externalCode = $externalCode;
        $this->name = $name;
        $this->description = $description;
        $this->price = $price;
        $this->discount = $discount;
        $this->touch();
    }

    /**
     * Полная замена набора доп. атрибутов (orphanRemoval удалит старые).
     *
     * @param array<string, string> $kv
     */
    public function replaceAttributes(array $kv): void
    {
        $this->attributes->clear();
        foreach ($kv as $key => $value) {
            $this->attributes->add(new ProductAttribute($this, $key, $value));
        }
    }

    public function addImage(ProductImage $image): void
    {
        $this->images->add($image);
    }

    public function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
