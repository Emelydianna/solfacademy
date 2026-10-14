<?php

declare(strict_types=1);

namespace App\Controllers\Estudiante;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

/**
 * Pantalla 5 del portal: Calificaciones y Evaluaciones (/estudiante/calificaciones).
 */
class CalificacionesController
{
    private PhpRenderer $view;

    public function __construct(PhpRenderer $view)
    {
        $this->view = $view;
    }

    public function index(Request $request, Response $response): Response
    {
        $this->view->setLayout('estudiante/layout.php');

        return $this->view->render($response, 'estudiante/calificaciones.php', [
            'tituloPagina' => 'Calificaciones — Portal del Estudiante',
            'paginaPortal' => 'calificaciones',
        ]);
    }
}
