<?php

declare(strict_types=1);

namespace App\Controllers\Instructor;

use App\Application\Instructor\InstructorService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

class AsistenciasController
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
        $params = $request->getQueryParams();
        $idGrupo = isset($params['grupo']) ? (int) $params['grupo'] : 0;
        $fecha = $params['fecha'] ?? date('Y-m-d');
        $this->view->setLayout('instructor/layout.php');
        $data = $this->service->asistenciaPorGrupo((int) $usuario['id_referencia'], $idGrupo, $fecha);
        $flash = $_SESSION['asistencia_flash'] ?? null;
        unset($_SESSION['asistencia_flash']);
        return $this->view->render($response, 'instructor/asistencias/index.php', [
            'tituloPagina' => 'Registrar Asistencia',
            'paginaInstructor' => 'asistencias',
            'grupo' => $data['grupo'],
            'alumnos' => $data['alumnos'],
            'grupos' => $data['grupos'],
            'fecha' => $fecha,
            'idGrupo' => $idGrupo,
            'flash' => $flash,
        ]);
    }

    public function guardar(Request $request, Response $response): Response
    {
        $usuario = $_SESSION['usuario'] ?? [];
        $body = (array) $request->getParsedBody();
        $idGrupo = (int) ($body['id_grupo'] ?? 0);
        $fecha = $body['fecha'] ?? date('Y-m-d');
        $asistencias = $body['asistencia'] ?? [];
        $this->service->guardarAsistencia((int) $usuario['id_referencia'], $idGrupo, $fecha, $asistencias);
        $_SESSION['asistencia_flash'] = ['tipo' => 'exito', 'mensaje' => 'Registro de asistencia guardado correctamente.'];
        return $response->withStatus(302)->withHeader('Location', '/instructor/asistencias?grupo=' . $idGrupo . '&fecha=' . $fecha);
    }
}
