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

    /**
     * Ficha académica de un alumno desde el punto de vista del instructor.
     * Solo es visible si el alumno está inscrito en alguno de sus grupos.
     */
    public function perfil(Request $request, Response $response, array $args): Response
    {
        $usuario = $_SESSION['usuario'] ?? [];

        $idEstudiante = (int) ($args['id'] ?? 0);
        $ficha = $this->service->fichaAlumno((int) $usuario['id_referencia'], $idEstudiante);

        $this->view->setLayout('instructor/layout.php');

        if ($ficha === null) {
            return $this->view->render($response->withStatus(404), 'instructor/alumnos/no-encontrado.php', [
                'tituloPagina' => 'Alumno no encontrado',
                'paginaInstructor' => 'grupos',
                'idEstudiante' => $idEstudiante,
            ]);
        }

        return $this->view->render($response, 'instructor/alumnos/perfil.php', [
            'tituloPagina' => 'Perfil Académico del Alumno',
            'paginaInstructor' => 'grupos',
            'ficha' => $ficha,
        ]);
    }
}
