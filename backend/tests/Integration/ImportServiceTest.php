<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Repository\ImportJobRepository;
use App\Service\Import\ImportService;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Ramsey\Uuid\Uuid;

final class ImportServiceTest extends IntegrationTestCase
{
    private ImportService $service;

    private ImportJobRepository $jobs;

    private string $storage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storage = $this->tmpStorage();

        $this->service = new ImportService(
            new \App\Service\Import\UploadValidator(10485760),
            new \App\Service\Import\XlsxReader(),
            new \App\Service\Import\ImageDownloader(
                $this->container->get(\Symfony\Contracts\HttpClient\HttpClientInterface::class),
                5,
                1024 * 1024,
                4,
                $this->storage,
                true,   // SSRF-фильтр выключен: мок-хосты не резолвятся
            ),
            $this->container->get(ImportJobRepository::class),
            $this->container->get(\App\Repository\ProductRepository::class),
            $this->container->get(\App\Repository\ProductAttributeRepository::class),
            $this->container->get(\App\Repository\ProductImageRepository::class),
            $this->container->get(\Doctrine\DBAL\Connection::class),
            $this->container->get(\Symfony\Component\Messenger\MessageBusInterface::class),
            $this->storage,
        );
        $this->jobs = $this->container->get(ImportJobRepository::class);
    }

    /** Полный прогон реального файла-примера: 40 товаров, атрибуты, изображения. */
    public function testExampleFileImportsFortyProducts(): void
    {
        $jobId = $this->enqueueFixture();
        $this->service->process($jobId);

        // воркер пишет прогресс в обход ORM — сбрасываем identity map, иначе
        // find() вернёт устаревшую сущность из памяти
        $this->container->get(\Doctrine\ORM\EntityManagerInterface::class)->clear();
        $job = $this->jobs->find($jobId);
        self::assertNotNull($job);
        self::assertSame('completed', $job->getStatus(), $job->getMessage() ?? '');
        self::assertSame(40, $job->getTotalRows());
        self::assertSame(40, $job->getSuccessCount());
        self::assertSame(0, $job->getErrorCount());

        $connection = $this->container->get(\Doctrine\DBAL\Connection::class);
        self::assertSame(40, (int) $connection->fetchOne('SELECT count(*) FROM products'));
        self::assertGreaterThan(0, (int) $connection->fetchOne('SELECT count(*) FROM product_attributes'));
        self::assertGreaterThan(0, (int) $connection->fetchOne('SELECT count(*) FROM product_images'));

        // файлы картинок реально записаны и шардируются
        $downloaded = (int) $connection->fetchOne('SELECT count(*) FROM product_images WHERE path IS NOT NULL');
        self::assertGreaterThan(0, $downloaded);
        $downloadedPaths = $connection->fetchFirstColumn('SELECT DISTINCT path FROM product_images WHERE path IS NOT NULL');
        foreach ($downloadedPaths as $relative) {
            self::assertFileExists($this->storage . '/' . $relative);
        }

        // исходный xlsx удалён после обработки
        self::assertFileDoesNotExist($this->storage . '/imports/' . $jobId . '.xlsx');
    }

    /** Повторный импорт — upsert, дубликатов нет. */
    public function testReimportUpsertsWithoutDuplicates(): void
    {
        $connection = $this->container->get(\Doctrine\DBAL\Connection::class);

        $this->service->process($this->enqueueFixture());
        $this->service->process($this->enqueueFixture());

        self::assertSame(40, (int) $connection->fetchOne('SELECT count(*) FROM products'));
        self::assertSame(2, (int) $connection->fetchOne('SELECT count(*) FROM import_jobs'));
    }

    /** Битая строка не прерывает импорт: completed, errorCount=1, остальные сохранены. */
    public function testBrokenRowGoesToReport(): void
    {
        $path = $this->writeXlsx([
            ['Внешний код', 'Наименование', 'Цена: Цена продажи', 'Закупочная цена'],
            ['OK-1', 'Хороший товар', '100,00', '80,00'],
            ['BAD-1', 'Товар без цены', 'не-цена', ''],
            ['', 'Товар без кода', '50,00', ''],
        ]);

        $jobId = $this->enqueuePath($path);
        $this->service->process($jobId);

        $this->container->get(\Doctrine\ORM\EntityManagerInterface::class)->clear();
        $job = $this->jobs->find($jobId);
        self::assertNotNull($job);
        self::assertSame('completed', $job->getStatus());
        self::assertSame(3, $job->getTotalRows());
        self::assertSame(1, $job->getSuccessCount());
        self::assertSame(2, $job->getErrorCount());

        $errorRows = array_map(static fn (array $e): int => $e['row'], $job->getErrors());
        self::assertSame([3, 4], $errorRows);

        $connection = $this->container->get(\Doctrine\DBAL\Connection::class);
        self::assertSame(1, (int) $connection->fetchOne('SELECT count(*) FROM products'));
        // (100 − 80) / 100 × 100 = 20.00
        self::assertSame('20.00', (string) $connection->fetchOne('SELECT discount FROM products WHERE external_code = ?', ['OK-1']));
    }

    /** Нет обязательных столбцов → задача failed с сообщением. */
    public function testMissingRequiredColumnsFailsJob(): void
    {
        $path = $this->writeXlsx([
            ['Артикул', 'Название'],
            ['A-1', 'Что-то'],
        ]);

        $jobId = $this->enqueuePath($path);
        $this->service->process($jobId);

        $this->container->get(\Doctrine\ORM\EntityManagerInterface::class)->clear();
        $job = $this->jobs->find($jobId);
        self::assertNotNull($job);
        self::assertSame('failed', $job->getStatus());
        self::assertStringContainsString('обязательные столбцы', (string) $job->getMessage());
    }

    /** @param list<list<string>> $rows */
    private function writeXlsx(array $rows): string
    {
        $path = $this->storage . '/imports/' . uniqid('gen', true) . '.xlsx';
        $writer = new Writer();
        $writer->openToFile($path);
        foreach ($rows as $cells) {
            $writer->addRow(Row::fromValues($cells));
        }
        $writer->close();

        return $path;
    }

    /** enqueue реального примера: файл копируем в tmp-storage (enqueue его переносит). */
    private function enqueueFixture(): string
    {
        return $this->enqueuePath(self::FIXTURE_XLSX);
    }

    private function enqueuePath(string $source): string
    {
        copy($source, $tmp = $this->storage . '/uploads-' . uniqid() . '.xlsx');

        $jobId = Uuid::uuid4()->toString();
        $target = $this->storage . '/imports/' . $jobId . '.xlsx';
        rename($tmp, $target);

        $job = new \App\Entity\ImportJob($jobId, basename($source), $target);
        $this->jobs->create($job);

        return $jobId;
    }
}
