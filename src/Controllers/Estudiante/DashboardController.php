<?php

declare(strict_types=1);

namespace App\Controllers\Estudiante;

use App\Application\Estudiante\EstudiantePortalService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

/**
 * Pantalla 1 del portal: Dashboard del Estudiante (/estudiante/dashboard).
 */
class DashboardController
{
    private PhpRenderer $view;
    private EstudiantePortalService $portal;

    public function __construct(PhpRenderer $view, EstudiantePortalService $portal)
    {
        $this->view = $view;
        $this->portal = $portal;
    }

    public function index(Request $request, Response $response): Response
    {
        $usuario = $_SESSION['usuario'] ?? [];


        $this->view->setLayout('estudiante/layout.php');

        return $this->view->render($response, 'estudiante/dashboard.php', [
            'tituloPagina' => 'Dashboard — Portal del Estudiante',
            'paginaPortal' => 'dashboard',
            'datos' => $this->portal->dashboard((int) $usuario['id_referencia']),
        ]);
    }
}
