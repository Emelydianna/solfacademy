<?php

declare(strict_types=1);

namespace App\Controllers\Instructor;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

class ReportesController
{
    private PhpRenderer $view;

    public function __construct(PhpRenderer $view)
    {
        $this->view = $view;
    }

    public function index(Request $request, Response $response): Response
    {
        $usuario = $_SESSION['usuario'] ?? [];
        if (($usuario['rol'] ?? '') !== 'instructor') {
            return $response->withStatus(302)->withHeader('Location', '/');
        }
        $this->view->setLayout('instructor/layout.php');
        return $this->view->render($response, 'instructor/reportes/index.php', [
            'tituloPagina' => 'Reportes',
            'paginaInstructor' => 'reportes',
        ]);
    }
}
