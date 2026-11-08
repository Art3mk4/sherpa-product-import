<?php

declare(strict_types=1);

namespace App\Service\Import;

use Symfony\Contracts\HttpClient\ChunkInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Throwable;

/**
 * Скачивание изображений параллельными пачками (IMAGE_CONCURRENCY).
 * Один URL скачивается один раз (файл sha1(url).ext переиспользуется),
 * упавшие в рамках задачи URL не перебиваются повторно. Ошибки — только
 * warnings: картинка никогда не валит импорт строки. Запись атомарна:
 * сначала *.tmp, затем rename().
 */
final class ImageDownloader
{
    /** @var array<string, string> url => относительный путь (успешно скачанные в этой задаче) */
    private array $downloaded = [];

    /** @var array<string, true> URL, которые уже падали в этой задаче */
    private array $failed = [];

    /** @var array<string, bool> кэш DNS-проверки хостов (SSRF-фильтр) */
    private array $hostAllowed = [];

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly int $timeout,
        private readonly int $maxSize,
        private readonly int $concurrency,
        private readonly string $storagePath,
        private readonly bool $allowPrivateHosts = false,
    ) {
    }

    /**
     * @param list<string> $urls
     *
     * @return array<string, string|null> url => относительный путь (images/ab/…) либо null при неудаче
     */
    public function downloadMany(array $urls, ImportReport $report, int $row, ?string $externalCode): array
    {
        /** @var array<string, string|null> $result */
        $result = [];

        $pending = [];
        foreach ($urls as $url) {
            if (isset($this->failed[$url])) {
                $result[$url] = null;
                continue;
            }

            $existing = $this->existingPath($url);
            if ($existing !== null) {
                $result[$url] = $existing;
                continue;
            }

            $pending[] = $url;
        }

        foreach (array_chunk($pending, max(1, $this->concurrency)) as $batch) {
            $this->downloadBatch($batch, $result, $report, $row, $externalCode);
        }

        return $result;
    }

    /**
     * @param list<string>               $batch
     * @param array<string, string|null> $result
     */
    private function downloadBatch(array $batch, array &$result, ImportReport $report, int $row, ?string $externalCode): void
    {
        $urlByResponse = [];
        $responses = [];

        foreach ($batch as $url) {
            if (!$this->hostAllowed($url)) {
                $this->fail($url, $result, $report, $row, $externalCode, 'хост запрещён политикой безопасности (private/loopback или не резолвится)');
                continue;
            }

            try {
                $response = $this->client->request('GET', $url, [
                    'buffer' => false,
                    'max_duration' => $this->timeout,
                ]);
            } catch (Throwable $e) {
                $this->fail($url, $result, $report, $row, $externalCode, $e->getMessage());
                continue;
            }

            $responses[] = $response;
            $urlByResponse[spl_object_id($response)] = $url;
        }

        if ($responses === []) {
            return;
        }

        /** @var array<int, array{url: string, tmp: resource|null, final: string, size: int, failed: string|null, done: bool}> $state */
        $state = [];

        try {
            foreach ($this->client->stream($responses, $this->timeout + 2.0) as $response => $chunk) {
                /** @var ResponseInterface $response */
                /** @var ChunkInterface $chunk */
                $oid = spl_object_id($response);
                $url = $urlByResponse[$oid] ?? null;
                if ($url === null) {
                    continue;
                }

                $st = $state[$oid] ?? null;
                if ($st !== null && ($st['failed'] !== null || $st['done'])) {
                    continue;
                }

                try {
                    if ($chunk->isFirst()) {
                        $status = $response->getStatusCode();
                        $contentTypes = $response->getHeaders(false)['content-type'] ?? [];
                        $contentType = (string) ($contentTypes[0] ?? '');

                        if ($status !== 200) {
                            $response->cancel();
                            $state[$oid] = ['url' => $url, 'tmp' => null, 'final' => '', 'size' => 0, 'failed' => sprintf('HTTP %d', $status), 'done' => false];
                            continue;
                        }

                        if (!str_starts_with(strtolower($contentType), 'image/')) {
                            $response->cancel();
                            $state[$oid] = ['url' => $url, 'tmp' => null, 'final' => '', 'size' => 0, 'failed' => sprintf('ответ не является изображением (%s)', $contentType), 'done' => false];
                            continue;
                        }

                        $final = $this->relativePath($url);
                        $this->ensureDir(dirname($this->storagePath . '/' . $final));
                        $tmp = fopen($this->storagePath . '/' . $final . '.tmp', 'wb');
                        if ($tmp === false) {
                            $response->cancel();
                            $state[$oid] = ['url' => $url, 'tmp' => null, 'final' => '', 'size' => 0, 'failed' => 'не удалось создать временный файл', 'done' => false];
                            continue;
                        }

                        $state[$oid] = ['url' => $url, 'tmp' => $tmp, 'final' => $final, 'size' => 0, 'failed' => null, 'done' => false];
                    }

                    $st = $state[$oid];
                    if ($st['failed'] !== null || $st['done']) {
                        continue;
                    }

                    $data = $chunk->getContent();
                    $st['size'] += strlen($data);

                    if ($st['size'] > $this->maxSize) {
                        $response->cancel();
                        $this->closeTmp($st['tmp']);
                        @unlink($this->storagePath . '/' . $st['final'] . '.tmp');
                        $state[$oid]['failed'] = sprintf('файл больше %d байт', $this->maxSize);
                        continue;
                    }

                    if (is_resource($st['tmp'])) {
                        fwrite($st['tmp'], $data);
                    }
                    $state[$oid]['size'] = $st['size'];

                    if ($chunk->isLast()) {
                        $this->closeTmp($st['tmp']);
                        $absolute = $this->storagePath . '/' . $st['final'];
                        $absoluteTmp = $absolute . '.tmp';
                        if (!@rename($absoluteTmp, $absolute)) {
                            @unlink($absoluteTmp);
                            $state[$oid]['failed'] = 'не удалось сохранить файл';
                            continue;
                        }
                        $state[$oid]['done'] = true;
                        $result[$url] = $st['final'];
                        $this->downloaded[$url] = $st['final'];
                    }
                } catch (Throwable $e) {
                    $response->cancel();
                    $this->closeTmp($state[$oid]['tmp'] ?? null);
                    @unlink($this->storagePath . '/' . ($state[$oid]['final'] ?? '') . '.tmp');
                    $state[$oid]['failed'] = $e->getMessage();
                }
            }
        } finally {
            // Ответы, не дошедшие до isLast (таймаут стрима), и упавшие — добиваем
            foreach ($state as $oid => $st) {
                if (!$st['done']) {
                    $this->closeTmp($st['tmp']);
                    @unlink($this->storagePath . '/' . $st['final'] . '.tmp');
                }
            }
        }

        foreach ($state as $st) {
            if (!$st['done']) {
                $this->fail($st['url'], $result, $report, $row, $externalCode, $st['failed'] ?? 'истёк таймаут загрузки');
            }
        }
    }

    /** Регистрирует неудачу: null в результате + warning + блокировка повторов в этой задаче. */
    /** @param array<string, string|null> $result */
    private function fail(string $url, array &$result, ImportReport $report, int $row, ?string $externalCode, string $reason): void
    {
        $result[$url] = null;
        $this->failed[$url] = true;
        $report->addWarning($row, $externalCode, sprintf('Не удалось скачать изображение %s: %s', $url, $reason));
    }

    /** Кэш на диске: имя файла детерминировано, повторный импорт не качает заново. */
    private function existingPath(string $url): ?string
    {
        if (isset($this->downloaded[$url])) {
            return $this->downloaded[$url];
        }

        $relative = $this->relativePath($url);
        if (is_file($this->storagePath . '/' . $relative)) {
            $this->downloaded[$url] = $relative;

            return $relative;
        }

        return null;
    }

    /** images/ab/abcdef….jpg — шардирование по двум первым символам sha1. */
    private function relativePath(string $url): string
    {
        $hash = sha1($url);
        $extension = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

        return sprintf('images/%s/%s.%s', substr($hash, 0, 2), $hash, $extension);
    }

    /** Лёгкая защита от SSRF: не ходим на private/loopback/reserved адреса. */
    private function hostAllowed(string $url): bool
    {
        if ($this->allowPrivateHosts) {
            return true;   // только для тестов с мок-клиентом
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return false;
        }

        if (!array_key_exists($host, $this->hostAllowed)) {
            if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
                $ip = $host;
            } else {
                $resolved = gethostbyname($host);
                $ip = $resolved === $host ? false : $resolved;   // false = не резолвится
            }

            $this->hostAllowed[$host] = $ip !== false
                && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }

        return $this->hostAllowed[$host];
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0o775, true);
        }
    }

    private function closeTmp(mixed $tmp): void
    {
        if (is_resource($tmp)) {
            fclose($tmp);
        }
    }
}
