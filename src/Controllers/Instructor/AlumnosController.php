<?php

declare(strict_types=1);

namespace App\Controllers\Instructor;

use App\Application\Instructor\InstructorService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

class AlumnosController
{
    private PhpRenderer $view;
    private InstructorService $service;

    public function __construct(PhpRenderer $view, InstructorService $service)
    {
        $this->view = $view;
        $this->service = $service;
    }

    public function perfil(Request $request, Response $response, array $args): Response
    {
        $usuario = $_SESSION['usuario'] ?? [];
        if (($usuario['rol'] ?? '') !== 'instructor') {
            return $response->withStatus(302)->withHeader('Location', '/');
        }
        $idEstudiante = (int) ($args['id'] ?? 0);
        $this->view->setLayout('instructor/layout.php');
        return $this->view->render($response, 'instructor/alumnos/perfil.php', [
            'tituloPagina' => 'Perfil Académico del Alumno',
            'paginaInstructor' => 'grupos',
            'idEstudiante' => $idEstudiante,
        ]);
    }
}
