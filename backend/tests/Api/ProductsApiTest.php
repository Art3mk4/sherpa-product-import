<?php

declare(strict_types=1);

namespace App\Tests\Api;

final class ProductsApiTest extends ApiTestCase
{
    public function testHealth(): void
    {
        $response = $this->request('GET', '/api/health');
        $body = $this->json($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('ok', $body['status']);
    }

    public function testLoginOkAndWrongPassword(): void
    {
        $ok = $this->request('POST', '/api/auth/login', ['Content-Type' => 'application/json'], '{"email":"admin@example.com","password":"admin123"}');
        self::assertSame(200, $ok->getStatusCode());
        $body = $this->json($ok);
        self::assertArrayHasKey('token', $body);

        $bad = $this->request('POST', '/api/auth/login', ['Content-Type' => 'application/json'], '{"email":"admin@example.com","password":"wrong"}');
        self::assertSame(401, $bad->getStatusCode());
    }

    public function testProductsRequireAuth(): void
    {
        $response = $this->request('GET', '/api/products');
        self::assertSame(401, $response->getStatusCode());
        $body = $this->json($response);
        self::assertSame('unauthorized', $body['error']['code']);
    }

    public function testProductsListStructure(): void
    {
        $this->createProduct('API-1', 'Товар из теста', '777.00');

        $response = $this->request('GET', '/api/products?page=1&limit=10', $this->authHeaders());
        $body = $this->json($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, $body['meta']['total']);
        self::assertSame('API-1', $body['data'][0]['externalCode']);
        self::assertEquals(777.0, $body['data'][0]['price']);
    }

    public function testInvalidLimitGives422(): void
    {
        $response = $this->request('GET', '/api/products?limit=1000', $this->authHeaders());
        self::assertSame(422, $response->getStatusCode());
        self::assertSame('validation_failed', $this->json($response)['error']['code']);
    }

    public function testMissingProductGives404(): void
    {
        $response = $this->request('GET', '/api/products/999999', $this->authHeaders());
        self::assertSame(404, $response->getStatusCode());
    }

    public function testCrud(): void
    {
        // create
        $created = $this->request('POST', '/api/products', $this->authHeaders(), json_encode([
            'externalCode' => 'API-CRUD',
            'name' => 'Создан через API',
            'price' => 1234.5,
            'attributes' => [['key' => 'Размер', 'value' => '48']],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        self::assertSame(201, $created->getStatusCode());
        $product = $this->json($created);
        $id = $product['id'];
        self::assertSame('Создан через API', $product['name']);
        self::assertEquals(1234.5, $product['price']);
        self::assertSame('Размер', $product['attributes'][0]['key']);

        // update
        $updated = $this->request('PUT', '/api/products/' . $id, $this->authHeaders(), json_encode([
            'externalCode' => 'API-CRUD',
            'name' => 'Обновлён',
            'price' => 1500,
        ], JSON_THROW_ON_ERROR));
        self::assertSame(200, $updated->getStatusCode());
        self::assertSame('Обновлён', $this->json($updated)['name']);

        // delete
        $deleted = $this->request('DELETE', '/api/products/' . $id, $this->authHeaders());
        self::assertSame(204, $deleted->getStatusCode());
        self::assertSame(404, $this->request('GET', '/api/products/' . $id, $this->authHeaders())->getStatusCode());
    }

    /**
     * Отдельным тестом: после неуникального externalCode Doctrine закрывает EM
     * (как и в проде — там каждый HTTP-запрос получает свежий EM из нового контейнера).
     */
    public function testDuplicateExternalCodeGives409(): void
    {
        $this->request('POST', '/api/products', $this->authHeaders(), json_encode([
            'externalCode' => 'API-DUP',
            'name' => 'Первый',
            'price' => 100,
        ], JSON_THROW_ON_ERROR));

        $duplicate = $this->request('POST', '/api/products', $this->authHeaders(), json_encode([
            'externalCode' => 'API-DUP',
            'name' => 'Дубль',
            'price' => 100,
        ], JSON_THROW_ON_ERROR));

        self::assertSame(409, $duplicate->getStatusCode());
        self::assertSame('conflict', $this->json($duplicate)['error']['code']);
    }

    private function createProduct(string $code, string $name, string $price): void
    {
        $this->container->get(\App\Repository\ProductRepository::class)
            ->save(new \App\Entity\Product($code, $name, $price));
    }
}
