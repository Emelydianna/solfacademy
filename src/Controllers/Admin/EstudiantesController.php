<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Application\Admin\EstudiantesService;
use App\Application\Admin\GestionAcademicaService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Views\PhpRenderer;

final class EstudiantesController
{
    private PhpRenderer $view;
    private EstudiantesService $service;

    public function __construct(PhpRenderer $view, EstudiantesService $service)
    {
        $this->view = $view;
        $this->service = $service;
    }

    public function index(Request $request, Response $response): Response
    {
        $this->view->setLayout('admin/layout.php');
        return $this->view->render($response, 'admin/estudiantes/index.php', [
            'tituloPagina' => 'Gestión de Estudiantes — SolfAcademy', 'paginaAdmin' => 'estudiantes',
            'listado' => $this->service->listar($request->getQueryParams()),
        ]);
    }

    private function estudiante(Request $request, int $id): array
    {
        $estudiante = $this->service->buscar($id);
        if (!$estudiante) {
            throw new HttpNotFoundException($request);
        }
        return $estudiante;
    }

    public function formulario(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $estudiante = $id ? $this->estudiante($request, $id) : null;
        return $this->renderFormulario($response, $id, $estudiante ?? ['fecha_inscripcion' => date('Y-m-d')], [], $estudiante);
    }

    public function guardar(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $estudiante = $id ? $this->estudiante($request, $id) : null;
        $r = $this->service->guardar((array) $request->getParsedBody(), $id);
        if ($r['errores']) {
            return $this->renderFormulario($response->withStatus(422), $id, $r['valores'], $r['errores'], $estudiante);
        }
        $_SESSION['estudiantes_flash'] = ['tipo' => 'exito', 'mensaje' => 'Ficha y datos de acceso guardados correctamente.'];
        return $response->withStatus(303)->withHeader('Location', '/admin/estudiantes/' . $r['id']);
    }

    public function ver(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $estudiante = $this->estudiante($request, $id);
        $flash = $_SESSION['estudiantes_flash'] ?? null;
        unset($_SESSION['estudiantes_flash']);
        $this->view->setLayout('admin/layout.php');
        return $this->view->render($response, 'admin/estudiantes/detalle.php', [
            'tituloPagina' => 'Ficha del Estudiante — SolfAcademy', 'paginaAdmin' => 'estudiantes',
            'estudiante' => $estudiante, 'matriculas' => $this->service->matriculas($id),
            'revision' => $this->service->revision($id), 'flash' => $flash,
        ]);
    }

    public function estado(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $this->estudiante($request, $id);
        $d = (array) $request->getParsedBody();
        $_SESSION['estudiantes_flash'] = $this->service->cambiarEstado($id,
            GestionAcademicaService::texto($d, 'estado'), GestionAcademicaService::texto($d, 'motivo'),
            GestionAcademicaService::texto($d, 'revision'), GestionAcademicaService::texto($d, 'confirmar') === '1');
        return $response->withStatus(303)->withHeader('Location', '/admin/estudiantes/' . $id . '#estado-estudiante');
    }

    private function renderFormulario(Response $response, int $id, array $valores, array $errores, ?array $estudiante): Response
    {
        $this->view->setLayout('admin/layout.php');
        return $this->view->render($response, 'admin/estudiantes/formulario.php', [
            'tituloPagina' => ($id ? 'Editar' : 'Nuevo') . ' Estudiante — SolfAcademy', 'paginaAdmin' => 'estudiantes',
            'idEdicion' => $id, 'valores' => $valores, 'errores' => $errores, 'estudiante' => $estudiante,
        ]);
    }
}
