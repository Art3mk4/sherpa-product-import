<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Exception\ValidationException;
use App\Service\Import\UploadValidator;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\UploadedFile;

final class UploadValidatorTest extends TestCase
{
    private const MAX_SIZE = 10485760;

    private UploadValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new UploadValidator(self::MAX_SIZE);
    }

    public function testValidXlsxPasses(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate($this->uploadedFile($this->copyFixture(), 'import.xlsx'));
    }

    public function testNullFile(): void
    {
        $this->expectException(ValidationException::class);
        $this->validator->validate(null);
    }

    public function testWrongExtension(): void
    {
        $file = $this->uploadedFile($this->copyFixture(), 'import.txt');

        $this->assertFileErrorContains($file, 'расширение');
    }

    public function testTooBig(): void
    {
        $validator = new UploadValidator(10);
        $file = $this->uploadedFile($this->copyFixture(), 'import.xlsx');

        try {
            $validator->validate($file);
            self::fail('Ожидалась ValidationException');
        } catch (ValidationException $e) {
            self::assertStringContainsString('больш', implode(' ', $e->details['file']));
        }
    }

    public function testRenamedZipWithoutWorkbookFails(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'zip') . '.zip';
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('readme.txt', 'not an xlsx');
        $zip->close();

        try {
            $this->assertFileErrorContains($this->uploadedFile($path, 'fake.xlsx'), 'workbook.xml');
        } finally {
            @unlink($path);
        }
    }

    private function assertFileErrorContains(UploadedFile $file, string $needle): void
    {
        try {
            $this->validator->validate($file);
            self::fail('Ожидалась ValidationException');
        } catch (ValidationException $e) {
            self::assertStringContainsString($needle, implode(' ', $e->details['file']));
        }
    }

    private function copyFixture(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx') . '.xlsx';
        copy(__DIR__ . '/../Fixtures/import_example.xlsx', $path);

        return $path;
    }

    private function uploadedFile(string $path, string $clientName): UploadedFile
    {
        clearstatcache();
        $size = (int) filesize($path);

        return new UploadedFile($path, $clientName, 'application/octet-stream', $size, UPLOAD_ERR_OK);
    }
}
