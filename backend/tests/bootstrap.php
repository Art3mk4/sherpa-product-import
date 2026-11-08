<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// Для integration/api-тестов прогоняем миграции на app_test один раз.
// APP_ENV=test задаётся phpunit.xml; контейнер при этом выбирает DB_NAME_TEST.
$runMigrations = static function (): void {
    $exit = passthru(
        sprintf('php -d error_reporting=E_ALL %s migrations:migrate --no-interaction --allow-no-migration 2>&1', escapeshellarg(__DIR__ . '/../bin/console')),
        $code,
    );
    if ($code !== 0) {
        fwrite(STDERR, (string) $exit . PHP_EOL);
        fwrite(STDERR, 'Не удалось выполнить миграции на тестовую БД.' . PHP_EOL);
        exit(1);
    }
};

if (PHP_SAPI === 'cli' && class_exists(\PHPUnit\Framework\TestCase::class)) {
    $runMigrations();
}
