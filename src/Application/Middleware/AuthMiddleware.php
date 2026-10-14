<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

/**
 * Protege las rutas del portal (/estudiante/*): redirige a /login
 * si no hay usuario en la sesión.
 */
class AuthMiddleware implements Middleware
{
    /**
     * {@inheritdoc}
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (empty($_SESSION['usuario'])) {
            $response = new \Slim\Psr7\Response();

            return $response->withStatus(302)->withHeader('Location', '/login');
        }

        return $handler->handle($request);
    }
}
