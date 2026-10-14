<?php

declare(strict_types=1);

use App\Application\Middleware\StartSessionMiddleware;
use App\Application\Middleware\ViewDataMiddleware;
use Slim\App;

return function (App $app) {
    // El orden importa en Slim (LIFO): el último registrado se ejecuta primero
    $app->add(StartSessionMiddleware::class);
    $app->add(ViewDataMiddleware::class);
};
