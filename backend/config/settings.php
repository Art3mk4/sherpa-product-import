<?php

declare(strict_types=1);

/**
 * Читает переменные окружения; $default = null — переменная обязательна.
 * Guard: тесты собирают контейнер много раз в одном процессе.
 */
if (!function_exists('env')) {
    function env(string $key, ?string $default = null): string
    {
        $value = getenv($key);
        if ($value === false || $value === '') {
            if ($default === null) {
                throw new RuntimeException(sprintf('Обязательная переменная окружения "%s" не задана', $key));
            }

            return $default;
        }

        return $value;
    }
}

return [
    'app' => [
        'env' => env('APP_ENV', 'dev'),
        'debug' => (bool) env('APP_DEBUG', '1'),
        'target' => env('APP_TARGET', 'dev'),
        'cache_dir' => __DIR__ . '/../var/cache',
    ],
    'db' => [
        'host' => env('DB_HOST', 'db'),
        'port' => (int) env('DB_PORT', '5432'),
        'name' => env('DB_NAME', 'app'),
        'user' => env('DB_USER', 'app'),
        'password' => env('DB_PASSWORD', 'app_secret'),
        'name_test' => env('DB_NAME_TEST', 'app_test'),
    ],
    'messenger' => [
        'dsn' => env('MESSENGER_TRANSPORT_DSN', 'amqp://app:app_secret@rabbitmq:5672/%2f/messages'),
    ],
    'jwt' => [
        'secret' => env('JWT_SECRET'),
        'ttl' => (int) env('JWT_TTL', '3600'),
    ],
    'admin' => [
        'email' => env('ADMIN_EMAIL', 'admin@example.com'),
        'password' => env('ADMIN_PASSWORD', 'admin123'),
    ],
    'import' => [
        'max_file_size' => (int) env('IMPORT_MAX_FILE_SIZE', '10485760'),
        'rate_limit' => (int) env('IMPORT_RATE_LIMIT', '5'),
        'rate_interval' => env('IMPORT_RATE_INTERVAL', '1 minute'),
        'image_timeout' => (int) env('IMAGE_DOWNLOAD_TIMEOUT', '10'),
        'image_max_size' => (int) env('IMAGE_MAX_SIZE', '5242880'),
        'image_concurrency' => (int) env('IMAGE_CONCURRENCY', '8'),
        'storage_path' => env('STORAGE_PATH', '/var/www/storage'),
    ],
];
