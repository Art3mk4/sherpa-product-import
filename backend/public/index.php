<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$container = (require __DIR__ . '/../config/container.php')();

Slim\Factory\AppFactory::setContainer($container);
$app = Slim\Factory\AppFactory::create();

(require __DIR__ . '/../config/middleware.php')($app);
(require __DIR__ . '/../config/routes.php')($app);

$app->run();
