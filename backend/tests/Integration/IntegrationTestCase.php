<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use DI\Container;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * База для интеграционных тестов: свежий контейнер + чистые таблицы
 * (TRUNCATE, не транзакции — ImportService сам открывает свои транзакции)
 * + MockHttpClient вместо интернета.
 */
abstract class IntegrationTestCase extends TestCase
{
    protected const FIXTURE_XLSX = __DIR__ . '/../Fixtures/import_example.xlsx';

    /** 1×1 прозрачный PNG */
    protected const PNG_1PX = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    protected Container $container;

    protected function setUp(): void
    {
        $this->container = (require __DIR__ . '/../../config/container.php')();
        $this->container->set(HttpClientInterface::class, $this->imageHttpClient());

        $this->container->get(\Doctrine\DBAL\Connection::class)->executeStatement(
            'TRUNCATE products, product_attributes, product_images, import_jobs RESTART IDENTITY CASCADE',
        );
    }

    protected function tearDown(): void
    {
        $this->container->get(\Doctrine\DBAL\Connection::class)->close();
    }

    /**
     * Картинки: URL, заканчивающиеся на _m./_b./_p. + расширение картинки,
     * отдают 1×1 PNG, остальные — 404 (тесты не ходят в интернет).
     */
    protected function imageHttpClient(): MockHttpClient
    {
        return new MockHttpClient(static function (string $method, string $url): MockResponse {
            unset($method);

            if (preg_match('~_[mbp]\.(jpe?g|png|webp|gif)$~i', $url) === 1) {
                return new MockResponse(
                    base64_decode(self::PNG_1PX, true),
                    ['http_code' => 200, 'response_headers' => ['content-type' => 'image/png']],
                );
            }

            return new MockResponse(
                'not found',
                ['http_code' => 404, 'response_headers' => ['content-type' => 'text/plain']],
            );
        });
    }

    protected function tmpStorage(): string
    {
        $dir = sys_get_temp_dir() . '/sherpa_import_test_' . uniqid();
        mkdir($dir . '/images', 0o775, true);
        mkdir($dir . '/imports', 0o775, true);

        return $dir;
    }
}
