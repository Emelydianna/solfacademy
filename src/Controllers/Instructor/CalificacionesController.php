<?php

declare(strict_types=1);

namespace App\Controllers\Instructor;

use App\Application\Instructor\InstructorService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

class CalificacionesController
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
        $params = $request->getQueryParams();
        $idGrupo = isset($params['grupo']) ? (int) $params['grupo'] : 0;
        $idEvaluacion = isset($params['evaluacion']) ? (int) $params['evaluacion'] : null;
        $this->view->setLayout('instructor/layout.php');
        $data = $this->service->calificacionesPorGrupo((int) $usuario['id_referencia'], $idGrupo, $idEvaluacion);
        return $this->view->render($response, 'instructor/calificaciones/index.php', [
            'tituloPagina' => 'Ingresar Calificaciones',
            'paginaInstructor' => 'calificaciones',
            'grupos' => $data['grupos'],
            'grupo' => $data['grupo'],
            'alumnos' => $data['alumnos'],
            'evaluaciones' => $data['evaluaciones'],
            'idGrupo' => $data['idGrupo'],
            'idEvaluacion' => $data['idEvaluacion'],
            'mensaje' => $_SESSION['calif_msg'] ?? null,
            'tipoMsg' => $_SESSION['calif_msg_tipo'] ?? null,
        ]);
        unset($_SESSION['calif_msg'], $_SESSION['calif_msg_tipo']);
    }

    public function guardar(Request $request, Response $response): Response
    {
        $usuario = $_SESSION['usuario'] ?? [];
        if (($usuario['rol'] ?? '') !== 'instructor') {
            return $response->withStatus(302)->withHeader('Location', '/');
        }
        $body = (array) $request->getParsedBody();
        $idGrupo = (int) ($body['id_grupo'] ?? 0);
        $idEvaluacion = (int) ($body['id_evaluacion'] ?? 0);
        $calificaciones = $body['calificacion'] ?? [];
        $observaciones = $body['observaciones'] ?? [];
        $this->service->guardarCalificaciones((int) $usuario['id_referencia'], $idGrupo, $idEvaluacion, $calificaciones, $observaciones);
        $_SESSION['calif_msg'] = 'Calificaciones guardadas correctamente.';
        $_SESSION['calif_msg_tipo'] = 'success';
        return $response->withStatus(302)->withHeader('Location', '/instructor/calificaciones?grupo=' . $idGrupo . '&evaluacion=' . $idEvaluacion);
    }
}
