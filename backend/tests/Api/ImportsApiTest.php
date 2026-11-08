<?php

declare(strict_types=1);

namespace App\Tests\Api;

use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class ImportsApiTest extends ApiTestCase
{
    public function testTxtUploadRejected(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'txt');
        file_put_contents((string) $path, 'not a spreadsheet');

        try {
            $response = $this->uploadFile((string) $path, 'import.txt');
            self::assertSame(422, $response->getStatusCode());
            $body = $this->json($response);
            self::assertSame('validation_failed', $body['error']['code']);
            self::assertArrayHasKey('file', $body['error']['details']);
        } finally {
            @unlink((string) $path);
        }
    }

    public function testValidUploadQueuesMessage(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx') . '.xlsx';
        copy(self::FIXTURE_XLSX, $path);

        try {
            $response = $this->uploadFile($path, 'import_example.xlsx');
            self::assertSame(202, $response->getStatusCode());

            $body = $this->json($response);
            self::assertArrayHasKey('id', $body);
            self::assertSame('pending', $body['status']);

            // сообщение ушло в InMemory-транспорт
            $transport = $this->container->get('messenger.transport.async');
            self::assertInstanceOf(InMemoryTransport::class, $transport);
            self::assertCount(1, $transport->get());

            // статус доступен
            $status = $this->request('GET', '/api/imports/' . $body['id'], $this->authHeaders());
            self::assertSame(200, $status->getStatusCode());

            // latest отдаёт ту же задачу
            $latest = $this->request('GET', '/api/imports/latest', $this->authHeaders());
            self::assertSame(200, $latest->getStatusCode());
            self::assertSame($body['id'], $this->json($latest)['id']);
        } finally {
            @unlink($path);
        }
    }

    public function testLatestWithoutImportsIs404(): void
    {
        $response = $this->request('GET', '/api/imports/latest', $this->authHeaders());
        self::assertSame(404, $response->getStatusCode());
    }

    public function testImportRateLimit(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx') . '.xlsx';
        copy(self::FIXTURE_XLSX, $path);

        try {
            $codes = [];
            for ($i = 0; $i < 6; $i++) {
                $copy = $path . '.' . $i;              // enqueue переносит файл — каждому запросу своя копия
                copy($path, $copy);
                $codes[] = $this->uploadFile($copy, 'import.xlsx')->getStatusCode();
                // не unlink $copy: Slim UploadedFile уже перенёс его в storage
            }

            self::assertSame([202, 202, 202, 202, 202, 429], $codes);
        } finally {
            @unlink($path);
        }
    }
}
