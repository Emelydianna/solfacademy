<?php

declare(strict_types=1);

namespace App\Controllers\Estudiante;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

/**
 * Pantalla 4 del portal: Asistencias e Historial (/estudiante/asistencias).
 */
class AsistenciasController
{
    private PhpRenderer $view;

    public function __construct(PhpRenderer $view)
    {
        $this->view = $view;
    }

    public function index(Request $request, Response $response): Response
    {
        $this->view->setLayout('estudiante/layout.php');

        return $this->view->render($response, 'estudiante/asistencias.php', [
            'tituloPagina' => 'Asistencias — Portal del Estudiante',
            'paginaPortal' => 'asistencias',
        ]);
    }
}
