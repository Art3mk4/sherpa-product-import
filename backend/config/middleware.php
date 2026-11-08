<?php

declare(strict_types=1);

use App\Http\ErrorHandler;
use Slim\App;

/**
 * Порядок важен — Slim выполняет middleware по LIFO:
 * ErrorMiddleware добавляем последним, чтобы он ловил всё остальное.
 */
return static function (App $app): void {
    $app->addBodyParsingMiddleware();
    $app->addRoutingMiddleware();

    $settings = $app->getContainer()?->get('settings');
    $debug = (bool) ($settings['app']['debug'] ?? true);

    $errorMw = $app->addErrorMiddleware($debug, true, true);
    $errorMw->setDefaultErrorHandler(ErrorHandler::class);
};
