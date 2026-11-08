<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\NotFoundException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** Swagger UI (CDN) + сам спек OpenAPI. */
final class DocsController
{
    public function __construct(private readonly string $specPath)
    {
    }

    public function ui(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $html = <<<'HTML'
            <!doctype html>
            <html lang="ru">
            <head>
              <meta charset="utf-8">
              <meta name="viewport" content="width=device-width, initial-scale=1">
              <title>Импорт товаров — API</title>
              <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.17.14/swagger-ui.css">
            </head>
            <body>
            <div id="ui"></div>
            <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.17.14/swagger-ui-bundle.js"></script>
            <script>
              window.onload = () => SwaggerUIBundle({
                url: '/api/docs/openapi.yaml',
                dom_id: '#ui',
                persistAuthorization: true,
              });
            </script>
            </body>
            </html>
            HTML;

        $response->getBody()->write($html);

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public function spec(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!is_file($this->specPath)) {
            throw new NotFoundException('openapi.yaml не найден');
        }

        $response->getBody()->write((string) file_get_contents($this->specPath));

        return $response->withHeader('Content-Type', 'application/yaml; charset=utf-8');
    }
}
