<?php

declare(strict_types=1);

use App\Application\Middleware\CsrfMiddleware;
use App\Application\Middleware\MultipartBodyMiddleware;
use App\Application\Middleware\StartSessionMiddleware;
use App\Application\Middleware\ViewDataMiddleware;
use App\Application\Middleware\ValidarCuentaMiddleware;
use Slim\App;

return function (App $app) {
    // El orden importa en Slim (LIFO): el último registrado se ejecuta primero
    $app->add(StartSessionMiddleware::class);
    $app->add(ViewDataMiddleware::class);
    $app->add(ValidarCuentaMiddleware::class);
    // Va el último de aquí para que se ejecute antes que las dos anteriores:
    // lee el body ya parseado y rechaza con 403 cualquier POST sin token CSRF.
    $app->add(CsrfMiddleware::class);
    // El último de todos: rellena el body de las peticiones multipart
    // (Slim no parsea multipart/form-data) para que el CSRF y los
    // controladores reciban los campos de cualquier form con enctype.
    $app->add(MultipartBodyMiddleware::class);
};
