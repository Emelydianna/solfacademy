<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

/**
 * Inicia la sesión PHP nativa en cada petición web.
 * (Sustituye al SessionMiddleware del skeleton, que solo servía
 * para APIs con cabecera Authorization.)
 */
class StartSessionMiddleware implements Middleware
{
    /**
     * {@inheritdoc}
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        return $handler->handle($request);
    }
}
