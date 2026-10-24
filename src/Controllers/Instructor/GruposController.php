<?php

declare(strict_types=1);

namespace App\Controllers\Instructor;

use App\Application\Instructor\InstructorService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

class GruposController
{
    private PhpRenderer $view;
    private InstructorService $service;

    public function __construct(PhpRenderer $view, InstructorService $service)
    {
        $this->view = $view;
        $this->service = $service;
    }

    public function index(Request $request, Response $response): Response
    {
        $usuario = $_SESSION['usuario'] ?? [];
        if (($usuario['rol'] ?? '') !== 'instructor') {
            return $response->withStatus(302)->withHeader('Location', '/');
        }
        $this->view->setLayout('instructor/layout.php');
        $grupos = $this->service->misGrupos((int) $usuario['id_referencia']);
        return $this->view->render($response, 'instructor/grupos/index.php', [
            'tituloPagina' => 'Mis Grupos',
            'paginaInstructor' => 'grupos',
            'grupos' => $grupos,
        ]);
    }

    public function ver(Request $request, Response $response, array $args): Response
    {
        $usuario = $_SESSION['usuario'] ?? [];
        if (($usuario['rol'] ?? '') !== 'instructor') {
            return $response->withStatus(302)->withHeader('Location', '/');
        }
        $idGrupo = (int) ($args['id'] ?? 0);
        $this->view->setLayout('instructor/layout.php');
        $data = $this->service->estudiantesGrupo((int) $usuario['id_referencia'], $idGrupo);
        return $this->view->render($response, 'instructor/grupos/ver.php', [
            'tituloPagina' => 'Estudiantes del Grupo',
            'paginaInstructor' => 'grupos',
            'grupo' => $data['grupo'],
            'alumnos' => $data['alumnos'],
        ]);
    }
}
