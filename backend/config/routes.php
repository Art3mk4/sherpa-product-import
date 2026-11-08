<?php

declare(strict_types=1);

use App\Controller\AuthController;
use App\Controller\DocsController;
use App\Controller\HealthController;
use App\Controller\ImportController;
use App\Controller\ProductController;
use App\Middleware\JwtAuthMiddleware;
use App\Middleware\RateLimitMiddleware;
use Slim\Interfaces\RouteCollectorProxyInterface;

/**
 * /imports/latest объявлен ДО /imports/{id}, иначе "latest" попадёт в {id}.
 */
return function (RouteCollectorProxyInterface $app): void {
    $app->group('/api', function (RouteCollectorProxyInterface $g): void {
        $g->get('/health', HealthController::class);
        $g->post('/auth/login', AuthController::class);
        $g->get('/docs', [DocsController::class, 'ui']);
        $g->get('/docs/openapi.yaml', [DocsController::class, 'spec']);

        $g->group('', function (RouteCollectorProxyInterface $p): void {
            $p->get('/products', [ProductController::class, 'list']);
            $p->get('/products/{id:[0-9]+}', [ProductController::class, 'show']);
            $p->post('/products', [ProductController::class, 'create']);
            $p->put('/products/{id:[0-9]+}', [ProductController::class, 'update']);
            $p->delete('/products/{id:[0-9]+}', [ProductController::class, 'delete']);

            $p->post('/imports', [ImportController::class, 'create'])->add(RateLimitMiddleware::class);
            $p->get('/imports/latest', [ImportController::class, 'latest']);
            $p->get('/imports/{id:[0-9a-f-]{36}}', [ImportController::class, 'show']);
        })->add(JwtAuthMiddleware::class);
    });
};
