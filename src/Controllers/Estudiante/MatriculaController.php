<?php

declare(strict_types=1);

namespace App\Controllers\Estudiante;

use App\Application\Academico\MatriculasService;
use App\Application\Admin\GestionAcademicaService;
use DomainException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Views\PhpRenderer;

final class MatriculaController
{
    private PhpRenderer $view;
    private MatriculasService $service;

    public function __construct(PhpRenderer $view, MatriculasService $service)
    {
        $this->view = $view;
        $this->service = $service;
    }

    public function solicitar(Request $request, Response $response, array $args): Response
    {
        $usuario = $_SESSION['usuario'] ?? [];
        $idGrupo = (int) $args['grupo'];
        $datos = $this->service->datosSolicitud($idGrupo, (int) $usuario['id_referencia']);
        if (!$datos || !$datos['estudiante']) {
            throw new HttpNotFoundException($request);
        }
        $this->view->setLayout('estudiante/layout.php');
        return $this->view->render($response, 'estudiante/matricula/solicitar.php', [
            'tituloPagina' => 'Solicitar Matrícula — Portal del Estudiante', 'paginaPortal' => 'cursos',
            'datos' => $datos, 'errores' => [],
        ]);
    }

    public function confirmar(Request $request, Response $response, array $args): Response
    {
        $usuario = $_SESSION['usuario'] ?? [];
        $idEstudiante = (int) $usuario['id_referencia'];
        $idGrupo = (int) $args['grupo'];
        $d = (array) $request->getParsedBody();
        try {
            $r = $this->service->solicitar($idGrupo, $idEstudiante, (int) $usuario['id'], GestionAcademicaService::texto($d, 'confirmar_requisitos') === '1');
            $_SESSION['matricula_flash'] = ['tipo' => $r['ok'] ? 'exito' : 'error', 'mensaje' => $r['mensaje']];
            return $response->withStatus(303)->withHeader('Location', '/estudiante/cursos');
        } catch (DomainException $e) {
            $datos = $this->service->datosSolicitud($idGrupo, $idEstudiante);
            if (!$datos || !$datos['estudiante']) {
                throw new HttpNotFoundException($request);
            }
            $this->view->setLayout('estudiante/layout.php');
            return $this->view->render($response->withStatus(422), 'estudiante/matricula/solicitar.php', [
                'tituloPagina' => 'Solicitar Matrícula — Portal del Estudiante', 'paginaPortal' => 'cursos',
                'datos' => $datos, 'errores' => ['general' => $e->getMessage()],
            ]);
        }
    }

    public function cancelar(Request $request, Response $response, array $args): Response
    {
        $usuario = $_SESSION['usuario'] ?? [];
        try {
            $r = $this->service->cancelarPropia((int) $args['id'], (int) $usuario['id_referencia'], (int) $usuario['id']);
            $_SESSION['matricula_flash'] = ['tipo' => 'exito', 'mensaje' => $r['mensaje']];
        } catch (DomainException $e) {
            $_SESSION['matricula_flash'] = ['tipo' => 'error', 'mensaje' => $e->getMessage()];
        }
        return $response->withStatus(303)->withHeader('Location', '/estudiante/cursos');
    }
}
