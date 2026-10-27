<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Application\Admin\GestionAcademicaService;
use App\Application\Admin\GruposService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Views\PhpRenderer;

final class GruposController
{
    private PhpRenderer $view;
    private GruposService $service;

    public function __construct(PhpRenderer $view, GruposService $service)
    {
        $this->view = $view;
        $this->service = $service;
    }

    public function index(Request $request, Response $response): Response
    {
        $q = $request->getQueryParams();
        $id = max(0, (int) GestionAcademicaService::texto($q, 'editar'));
        $v = $id ? $this->service->buscar($id) : ['id_curso' => GestionAcademicaService::texto($q, 'curso'),
            'estado' => 'planeado', 'fecha_inicio' => date('Y-m-d'), 'capacidad_maxima' => '12',
            'horarios' => [['dia_semana' => 'Lunes', 'hora_inicio' => '', 'hora_fin' => '']]];
        if ($v === null) {
            throw new HttpNotFoundException($request);
        }
        return $this->render($request, $response, $id, $v);
    }

    public function guardar(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        if ($id && !$this->service->buscar($id)) {
            throw new HttpNotFoundException($request);
        }
        $r = $this->service->guardar((array) $request->getParsedBody(), $id);
        if ($r['errores']) {
            return $this->render($request, $response->withStatus(422), $id, $r['valores'], $r['errores']);
        }
        $_SESSION['grupos_flash'] = ['tipo' => 'exito', 'mensaje' => 'Grupo y horarios guardados correctamente.'];
        return $response->withStatus(303)->withHeader('Location', '/admin/grupos?editar=' . $r['id']);
    }

    public function ver(Request $request, Response $response, array $args): Response
    {
        $grupo = $this->service->buscar((int) $args['id']);
        if (!$grupo) {
            throw new HttpNotFoundException($request);
        }
        $this->view->setLayout('admin/layout.php');
        return $this->view->render($response, 'admin/grupos/detalle.php', [
            'tituloPagina' => 'Grupo #' . $grupo['id_grupo'] . ' — SolfAcademy', 'paginaAdmin' => 'grupos',
            'grupo' => $grupo, 'alumnos' => $this->service->alumnos((int) $args['id']),
        ]);
    }

    private function render(Request $request, Response $response, int $id, array $valores, array $errores = []): Response
    {
        $flash = $_SESSION['grupos_flash'] ?? null;
        unset($_SESSION['grupos_flash']);
        $this->view->setLayout('admin/layout.php');
        return $this->view->render($response, 'admin/grupos/index.php', [
            'tituloPagina' => 'Gestión de Grupos — SolfAcademy', 'paginaAdmin' => 'grupos',
            'idEdicion' => $id, 'valores' => $valores, 'errores' => $errores, 'flash' => $flash,
            'opciones' => $this->service->opciones(), 'listado' => $this->service->listar($request->getQueryParams()),
            'grupoOriginal' => $id ? $this->service->buscar($id) : null,
        ]);
    }
}
