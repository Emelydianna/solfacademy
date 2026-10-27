<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

/**
 * Panel de administración — pendiente de la fase de admin.
 *
 * Existe ya porque AuthController::redirigirSegunRol() y
 * RequireRole() envían al rol `admin` a /admin/dashboard: sin esta
 * ruta, un admin quedaría en un 404 al iniciar sesión.
 */
class DashboardController
{
    private PhpRenderer $view;

    public function __construct(PhpRenderer $view)
    {
        $this->view = $view;
    }

    public function index(Request $request, Response $response): Response
    {
        $this->view->setLayout('admin/layout.php');

        return $this->view->render($response, 'admin/dashboard.php', [
            'tituloPagina' => 'Panel de Administración — SolfAcademy',
            'paginaAdmin' => 'dashboard',
        ]);
    }
}
