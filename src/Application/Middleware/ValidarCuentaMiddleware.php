<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Auth\CuentaService;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** Revoca sesiones suspendidas en la siguiente petición, incluso tras reactivar. */
final class ValidarCuentaMiddleware implements MiddlewareInterface
{
    private ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $usuario = $_SESSION['usuario'] ?? null;
        if ($usuario !== null && (!is_array($usuario) || !$this->container->get(CuentaService::class)->vigente($usuario))) {
            $_SESSION = [];
            session_regenerate_id(true);
        }
        return $handler->handle($request);
    }
}
