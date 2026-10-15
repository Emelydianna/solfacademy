<?php

declare(strict_types=1);

namespace App\Controllers\Estudiante;

use App\Application\Estudiante\EstudiantePortalService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

/**
 * Pantalla 5 del portal: Calificaciones y Evaluaciones (/estudiante/calificaciones).
 */
class CalificacionesController
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

        // El portal es solo para alumnos (instructores/admin: fase futura)
        if (($usuario['rol'] ?? '') !== 'estudiante') {
            return $response->withStatus(302)->withHeader('Location', '/');
        }

        $this->view->setLayout('estudiante/layout.php');

        return $this->view->render($response, 'estudiante/calificaciones.php', [
            'tituloPagina' => 'Calificaciones — Portal del Estudiante',
            'paginaPortal' => 'calificaciones',
            'datos' => $this->portal->calificaciones((int) $usuario['id_referencia']),
        ]);
    }
}
