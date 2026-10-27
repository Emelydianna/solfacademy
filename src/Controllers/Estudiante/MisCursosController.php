<?php

declare(strict_types=1);

namespace App\Controllers\Estudiante;

use App\Application\Estudiante\EstudiantePortalService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

/**
 * Pantalla 2 del portal: Mis Cursos (/estudiante/cursos).
 */
class MisCursosController
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

        return $this->view->render($response, 'estudiante/cursos.php', [
            'tituloPagina' => 'Mis Cursos — Portal del Estudiante',
            'paginaPortal' => 'cursos',
            'cursos' => $this->portal->misCursos((int) $usuario['id_referencia']),
        ]);
    }
}
