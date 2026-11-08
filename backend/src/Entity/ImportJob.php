<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'import_jobs')]
class ImportJob
{
    public const string STATUS_PENDING = 'pending';

    public const string STATUS_PROCESSING = 'processing';

    public const string STATUS_COMPLETED = 'completed';

    public const string STATUS_FAILED = 'failed';

    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status;

    #[ORM\Column(type: 'string', length: 255)]
    private string $originalName;

    #[ORM\Column(type: 'string', length: 512)]
    private string $filePath;

    #[ORM\Column(type: 'integer')]
    private int $totalRows = 0;

    #[ORM\Column(type: 'integer')]
    private int $processedRows = 0;

    #[ORM\Column(type: 'integer')]
    private int $successCount = 0;

    #[ORM\Column(type: 'integer')]
    private int $errorCount = 0;

    /** @var list<array{row: int, externalCode: string|null, messages: list<string>}> */
    #[ORM\Column(type: 'json', options: ['jsonb' => true])]
    private array $errors = [];

    /** @var list<array{row: int, externalCode: string|null, message: string}> */
    #[ORM\Column(type: 'json', options: ['jsonb' => true])]
    private array $warnings = [];

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $message = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $startedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $finishedAt = null;

    public function __construct(string $id, string $originalName, string $filePath)
    {
        $this->id = $id;
        $this->originalName = $originalName;
        $this->filePath = $filePath;
        $this->status = self::STATUS_PENDING;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function getTotalRows(): int
    {
        return $this->totalRows;
    }

    public function getProcessedRows(): int
    {
        return $this->processedRows;
    }

    public function getSuccessCount(): int
    {
        return $this->successCount;
    }

    public function getErrorCount(): int
    {
        return $this->errorCount;
    }

    /** @return list<array{row: int, externalCode: string|null, messages: list<string>}> */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /** @return list<array{row: int, externalCode: string|null, message: string}> */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getStartedAt(): ?DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getFinishedAt(): ?DateTimeImmutable
    {
        return $this->finishedAt;
    }
}
