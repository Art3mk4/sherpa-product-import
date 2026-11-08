<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Exception\ValidationException;
use Psr\Http\Message\UploadedFileInterface;
use ZipArchive;

/**
 * libmagic часто определяет xlsx как application/zip или octet-stream,
 * поэтому помимо MIME обязательна структурная проверка zip-архива.
 */
final class UploadValidator
{
    private const array ALLOWED_MIME = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip',
        'application/octet-stream',
    ];

    public function __construct(private readonly int $maxFileSize)
    {
    }

    /** @throws ValidationException */
    public function validate(?UploadedFileInterface $file): void
    {
        if ($file === null) {
            throw new ValidationException(['file' => ['Файл не передан или превышает лимит post_max_size']]);
        }

        switch ($file->getError()) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new ValidationException(['file' => ['Файл слишком большой']]);
            case UPLOAD_ERR_NO_FILE:
                throw new ValidationException(['file' => ['Файл не передан']]);
            default:
                throw new ValidationException(['file' => [sprintf('Ошибка загрузки файла (код %d)', $file->getError())]]);
        }

        $size = $file->getSize();
        if ($size === null || $size === 0) {
            throw new ValidationException(['file' => ['Пустой файл']]);
        }

        if ($size > $this->maxFileSize) {
            $mb = round($this->maxFileSize / 1048576, 1);
            throw new ValidationException(['file' => [sprintf('Файл больше допустимого размера (%s МБ)', $mb)]]);
        }

        $clientName = $file->getClientFilename() ?? '';
        $extension = strtolower(pathinfo($clientName, PATHINFO_EXTENSION));
        if ($extension !== 'xlsx') {
            throw new ValidationException(['file' => ['Допустимо только расширение .xlsx']]);
        }

        $uri = $file->getStream()->getMetadata('uri');
        if (!is_string($uri) || !is_file($uri)) {
            throw new ValidationException(['file' => ['Загруженный файл недоступен']]);
        }

        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($uri);
        if (!in_array($mime, self::ALLOWED_MIME, true)) {
            throw new ValidationException(['file' => [sprintf('Недопустимый тип файла: %s', $mime)]]);
        }

        $zip = new ZipArchive();
        $opened = $zip->open($uri, ZipArchive::RDONLY);
        if ($opened !== true) {
            throw new ValidationException(['file' => ['Файл не является корректным xlsx-архивом']]);
        }
        try {
            if ($zip->locateName('xl/workbook.xml') === false) {
                throw new ValidationException(['file' => ['Файл не является xlsx: отсутствует xl/workbook.xml (переименованный zip/архив не пройдёт)']]);
            }
        } finally {
            $zip->close();
        }

        // Клиентский Content-Type не проверяем осознанно — ему нельзя доверять.
    }
}
