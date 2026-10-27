<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

/**
 * Protege un grupo de rutas exigiendo un rol concreto de la sesión.
 *
 * Reemplaza el chequeo `if ($usuario['rol'] !== 'x')` que estaba copiado
 * en cada controlador. Hace tres cosas, en orden:
 *
 *  1. Sin sesión  → redirige a /login (lo que ya hacía AuthMiddleware).
 *  2. Con sesión pero otro rol → redirige al portal del rol que tiene.
 *  3. Rol correcto → deja pasar.
 *
 * Uso en app/routes.php:
 *
 *     $app->group('/instructor', function (Group $group) { ... })
 *         ->add(new RequireRole('instructor'));
 */
final class RequireRole implements Middleware
{
    /**
     * @var string Rol exigido: 'estudiante' | 'instructor' | 'admin'
     */
    private string $rol;

    public function __construct(string $rol)
    {
        $this->rol = $rol;
    }

    /**
     * {@inheritdoc}
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $usuario = $_SESSION['usuario'] ?? null;

        // No hay sesión: al login
        if ($usuario === null || $usuario === []) {
            return $this->redirigir('/login');
        }

        $rolActual = (string) ($usuario['rol'] ?? '');

        // Sesión correcta: adelante
        if ($rolActual === $this->rol) {
            return $handler->handle($request);
        }

        // Otro rol: a su propio portal (nunca a una página ajena)
        return $this->redirigir($this->portalDelRol($rolActual));
    }

    /**
     * Portal de cada rol; se mantiene en sincronía con
     * AuthController::redirigirSegunRol().
     */
    private function portalDelRol(string $rol): string
    {
        return match ($rol) {
            'estudiante' => '/estudiante/dashboard',
            'instructor' => '/instructor/dashboard',
            'admin'      => '/admin/dashboard',
            default      => '/',
        };
    }

    private function redirigir(string $destino): Response
    {
        return (new SlimResponse())
            ->withStatus(302)
            ->withHeader('Location', $destino);
    }
}
