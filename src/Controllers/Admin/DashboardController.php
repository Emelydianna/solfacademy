<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Application\Admin\AdminDashboardService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

/**
 * Panel de administración (/admin/dashboard).
 *
 * Protegido por RequireRole('admin'), registrado en app/routes.php junto al
 * grupo /admin. El nombre del admin lo pone AuthService::normalizar().
 */
class DashboardController
{
    private PhpRenderer $view;
    private AdminDashboardService $service;

    public function __construct(PhpRenderer $view, AdminDashboardService $service)
    {
        $this->view = $view;
        $this->service = $service;
    }

    public function index(Request $request, Response $response): Response
    {
        // ?rango=5m | 12m | ytd | mes — cualquier valor desconocido lo
        // corrige el service al defecto.
        $rango = (string) ($request->getQueryParams()['rango'] ?? '');

        $this->view->setLayout('admin/layout.php');

        return $this->view->render($response, 'admin/dashboard.php', [
            'tituloPagina' => 'Panel de Administración — SolfAcademy',
            'paginaAdmin'  => 'dashboard',
            'data'         => $this->service->dashboard($rango),
            'usuario'      => $_SESSION['usuario'] ?? [],
        ]);
    }
}
