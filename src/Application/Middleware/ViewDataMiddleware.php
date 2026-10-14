<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Views\PhpRenderer;

/**
 * Expone el usuario de la sesión a todas las plantillas como atributo
 * `usuarioSesion` (lo usa el layout para la navbar).
 */
class ViewDataMiddleware implements Middleware
{
    private PhpRenderer $view;

    public function __construct(PhpRenderer $view)
    {
        $this->view = $view;
    }

    /**
     * {@inheritdoc}
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $this->view->addAttribute('usuarioSesion', $_SESSION['usuario'] ?? null);

        return $handler->handle($request);
    }
}
