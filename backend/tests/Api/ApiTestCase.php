<?php

declare(strict_types=1);

namespace App\Tests\Api;

use DI\Container;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\UploadedFile;

/**
 * In-process прогон приложения: собираем $app так же, как public/index.php,
 * и подаём запросы напрямую, без HTTP-сервера.
 */
abstract class ApiTestCase extends TestCase
{
    protected const FIXTURE_XLSX = __DIR__ . '/../Fixtures/import_example.xlsx';

    protected Container $container;

    /** @var App<\Psr\Container\ContainerInterface|null> */
    protected App $app;

    protected function setUp(): void
    {
        $this->container = (require __DIR__ . '/../../config/container.php')();

        \Slim\Factory\AppFactory::setContainer($this->container);
        $this->app = \Slim\Factory\AppFactory::create();
        (require __DIR__ . '/../../config/middleware.php')($this->app);
        (require __DIR__ . '/../../config/routes.php')($this->app);

        $this->container->get(\Doctrine\DBAL\Connection::class)->executeStatement(
            'TRUNCATE products, product_attributes, product_images, import_jobs RESTART IDENTITY CASCADE',
        );
    }

    protected function tearDown(): void
    {
        $this->container->get(\Doctrine\DBAL\Connection::class)->close();
    }

    /**
     * @param array<string, string> $headers
     */
    protected function request(string $method, string $uri, array $headers = [], ?string $body = null): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest($method, $uri);
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        if ($body !== null) {
            $request->getBody()->write($body);
        }

        return $this->app->handle($request);
    }

    /** @return array<string, mixed> */
    protected function json(ResponseInterface $response): array
    {
        return (array) json_decode((string) $response->getBody(), true);
    }

    /** @return array<string, string> */
    protected function authHeaders(): array
    {
        $token = $this->container->get(\App\Service\Auth\JwtService::class)->issue('admin@example.com')['token'];

        return ['Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json'];
    }

    /** POST multipart-запрос с файлом. */
    protected function uploadFile(string $path, string $clientName): ResponseInterface
    {
        clearstatcache();
        $size = (int) filesize($path);
        $uploaded = new UploadedFile($path, $clientName, 'application/octet-stream', $size, UPLOAD_ERR_OK);

        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/api/imports')
            ->withHeader('Authorization', $this->authHeaders()['Authorization'])
            ->withUploadedFiles(['file' => $uploaded]);

        return $this->app->handle($request);
    }
}
