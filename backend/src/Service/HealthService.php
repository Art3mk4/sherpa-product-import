<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;
use Throwable;

final class HealthService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $messengerDsn,
    ) {
    }

    /**
     * @return array{status: string, checks: array{db: string, broker: string}}
     */
    public function check(): array
    {
        $db = $this->checkDb();
        $broker = $this->checkBroker();

        return [
            'status' => $db === 'ok' && $broker === 'ok' ? 'ok' : 'fail',
            'checks' => ['db' => $db, 'broker' => $broker],
        ];
    }

    private function checkDb(): string
    {
        try {
            $this->connection->executeQuery('SELECT 1');

            return 'ok';
        } catch (Throwable) {
            return 'fail';
        }
    }

    private function checkBroker(): string
    {
        $parts = parse_url($this->messengerDsn);
        $host = (string) ($parts['host'] ?? '');
        $port = (int) ($parts['port'] ?? 5672);

        // InMemory-транспорт (тесты/CI) — реального брокера нет
        if (($parts['scheme'] ?? '') === 'in-memory' || $host === '') {
            return 'ok';
        }

        $socket = @fsockopen($host, $port, $errno, $errstr, 1.0);
        if ($socket === false) {
            return 'fail';
        }

        fclose($socket);

        return 'ok';
    }
}
