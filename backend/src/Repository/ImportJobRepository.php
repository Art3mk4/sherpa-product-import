<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ImportJob;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * create/find/findLatest — ORM; прогресс воркера — DBAL: воркер живёт долго,
 * а EM после исключения в транзакции закрывается до конца процесса.
 */
final class ImportJobRepository
{
    private readonly Connection $connection;

    public function __construct(private readonly EntityManagerInterface $em)
    {
        $this->connection = $em->getConnection();
    }

    public function create(ImportJob $job): void
    {
        $this->em->persist($job);
        $this->em->flush();
    }

    public function find(string $id): ?ImportJob
    {
        return $this->em->find(ImportJob::class, $id);
    }

    public function findLatest(): ?ImportJob
    {
        $job = $this->em->createQueryBuilder()
            ->select('j')
            ->from(ImportJob::class, 'j')
            ->orderBy('j.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $job instanceof ImportJob ? $job : null;
    }

    /** @return array{id: string, status: string, file_path: string}|null */
    public function findForWorker(string $id): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, status, file_path FROM import_jobs WHERE id = :id',
            ['id' => $id],
        );

        if ($row === false) {
            return null;
        }

        return [
            'id' => (string) $row['id'],
            'status' => (string) $row['status'],
            'file_path' => (string) $row['file_path'],
        ];
    }

    public function markProcessing(string $id, int $totalRows): void
    {
        $this->connection->executeStatement(
            "UPDATE import_jobs SET status = 'processing', total_rows = :total, started_at = now() WHERE id = :id",
            ['total' => $totalRows, 'id' => $id],
        );
    }

    public function updateProgress(string $id, int $processed, int $success, int $errorCount): void
    {
        $this->connection->executeStatement(
            'UPDATE import_jobs SET processed_rows = :p, success_count = :s, error_count = :e WHERE id = :id',
            ['p' => $processed, 's' => $success, 'e' => $errorCount, 'id' => $id],
        );
    }

    /**
     * @param list<array{row: int, externalCode: string|null, messages: list<string>}> $errors
     * @param list<array{row: int, externalCode: string|null, message: string}>       $warnings
     */
    public function finish(
        string $id,
        string $status,
        int $processed,
        int $success,
        int $errorCount,
        array $errors,
        array $warnings,
        ?string $message,
    ): void {
        $this->connection->executeStatement(
            'UPDATE import_jobs
             SET status = :status,
                 processed_rows = :p,
                 success_count = :s,
                 error_count = :e,
                 errors = CAST(:errors AS jsonb),
                 warnings = CAST(:warnings AS jsonb),
                 message = :message,
                 finished_at = now()
             WHERE id = :id',
            [
                'status' => $status,
                'p' => $processed,
                's' => $success,
                'e' => $errorCount,
                'errors' => (string) json_encode($errors, JSON_UNESCAPED_UNICODE),
                'warnings' => (string) json_encode($warnings, JSON_UNESCAPED_UNICODE),
                'message' => $message,
                'id' => $id,
            ],
        );
    }
}
