<?php

declare(strict_types=1);

namespace App\Http\Presenter;

use App\Entity\ImportJob;

final class ImportJobPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(ImportJob $job): array
    {
        $total = $job->getTotalRows();

        return [
            'id' => $job->getId(),
            'status' => $job->getStatus(),
            'originalName' => $job->getOriginalName(),
            'totalRows' => $total,
            'processedRows' => $job->getProcessedRows(),
            'successCount' => $job->getSuccessCount(),
            'errorCount' => $job->getErrorCount(),
            'progress' => $total > 0 ? round($job->getProcessedRows() / $total * 100, 1) : 0.0,
            'errors' => $job->getErrors(),
            'warnings' => $job->getWarnings(),
            'message' => $job->getMessage(),
            'createdAt' => $job->getCreatedAt()->format(DATE_ATOM),
            'startedAt' => $job->getStartedAt()?->format(DATE_ATOM),
            'finishedAt' => $job->getFinishedAt()?->format(DATE_ATOM),
        ];
    }
}
