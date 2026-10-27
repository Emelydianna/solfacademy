<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Application\Admin\AulasService;
use App\Application\Admin\GestionAcademicaService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Views\PhpRenderer;

final class AulasController
{
    private PhpRenderer $view;
    private AulasService $service;

    public function __construct(PhpRenderer $view, AulasService $service)
    {
        $this->view = $view;
        $this->service = $service;
    }

    private function aula(Request $request, int $id): array
    {
        $aula = $this->service->buscar($id);
        if (!$aula) {
            throw new HttpNotFoundException($request);
        }
        return $aula;
    }

    public function index(Request $request, Response $response): Response
    {
        $this->view->setLayout('admin/layout.php');
        return $this->view->render($response, 'admin/aulas/index.php', [
            'tituloPagina' => 'Gestión de Aulas de Clase — SolfAcademy', 'paginaAdmin' => 'aulas',
            'listado' => $this->service->listar($request->getQueryParams()),
        ]);
    }

    public function formulario(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        return $this->renderFormulario($response, $id, $id ? $this->aula($request, $id) : ['capacidad' => '12']);
    }

    public function guardar(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        if ($id) {
            $this->aula($request, $id);
        }
        $r = $this->service->guardar((array) $request->getParsedBody(), $id);
        if ($r['errores']) {
            return $this->renderFormulario($response->withStatus(422), $id, $r['valores'], $r['errores']);
        }
        $_SESSION['aulas_flash'] = ['tipo' => 'exito', 'mensaje' => 'Aula guardada correctamente.'];
        return $response->withStatus(303)->withHeader('Location', '/admin/aulas/' . $r['id']);
    }

    public function ver(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $aula = $this->aula($request, $id);
        $flash = $_SESSION['aulas_flash'] ?? null;
        unset($_SESSION['aulas_flash']);
        $this->view->setLayout('admin/layout.php');
        return $this->view->render($response, 'admin/aulas/detalle.php', [
            'tituloPagina' => 'Agenda del Aula — SolfAcademy', 'paginaAdmin' => 'aulas',
            'aula' => $aula, 'agenda' => $this->service->agenda($id), 'flash' => $flash,
        ]);
    }

    public function estado(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $this->aula($request, $id);
        $d = (array) $request->getParsedBody();
        $_SESSION['aulas_flash'] = $this->service->cambiarEstado($id,
            GestionAcademicaService::texto($d, 'estado'), GestionAcademicaService::texto($d, 'confirmar') === '1');
        return $response->withStatus(303)->withHeader('Location', '/admin/aulas/' . $id . '#estado-aula');
    }

    private function renderFormulario(Response $response, int $id, array $valores, array $errores = []): Response
    {
        $this->view->setLayout('admin/layout.php');
        return $this->view->render($response, 'admin/aulas/formulario.php', [
            'tituloPagina' => ($id ? 'Editar' : 'Nueva') . ' Aula — SolfAcademy', 'paginaAdmin' => 'aulas',
            'idEdicion' => $id, 'valores' => $valores, 'errores' => $errores,
        ]);
    }
}
