<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Application\Admin\GestionAcademicaService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Views\PhpRenderer;

final class GestionAcademicaController
{
    private PhpRenderer $view;
    private GestionAcademicaService $service;

    public function __construct(PhpRenderer $view, GestionAcademicaService $service)
    {
        $this->view = $view;
        $this->service = $service;
    }

    public function index(Request $request, Response $response, array $args): Response
    {
        $seccion = $args['seccion'];
        $query = $request->getQueryParams();
        $id = max(0, (int) GestionAcademicaService::texto($query, 'editar', '0'));
        $valores = $id ? $this->service->buscar($seccion, $id) : [];
        if ($valores === null) {
            throw new HttpNotFoundException($request);
        }
        return $this->render($request, $response, $seccion, $id, $valores);
    }

    public function guardar(Request $request, Response $response, array $args): Response
    {
        $seccion = $args['seccion'];
        $id = (int) ($args['id'] ?? 0);
        if ($id && !$this->service->buscar($seccion, $id)) {
            throw new HttpNotFoundException($request);
        }
        $resultado = $this->service->guardar($seccion, (array) $request->getParsedBody(), $id);
        if ($resultado['errores']) {
            return $this->render($request, $response->withStatus(422), $seccion, $id, $resultado['valores'], $resultado['errores']);
        }
        $_SESSION['academica_flash'] = ['tipo' => 'exito', 'mensaje' => 'Registro guardado correctamente.'];
        return $response->withStatus(303)->withHeader('Location', '/admin/' . $seccion . '?editar=' . $resultado['id']);
    }

    public function eliminar(Request $request, Response $response, array $args): Response
    {
        $_SESSION['academica_flash'] = $this->service->eliminar($args['seccion'], (int) $args['id']);
        return $response->withStatus(303)->withHeader('Location', '/admin/' . $args['seccion']);
    }

    public function estado(Request $request, Response $response, array $args): Response
    {
        $_SESSION['academica_flash'] = $this->service->cambiarEstadoCurso(
            (int) $args['id'], GestionAcademicaService::texto((array) $request->getParsedBody(), 'activo')
        );
        return $response->withStatus(303)->withHeader('Location', '/admin/cursos');
    }

    private function render(Request $request, Response $response, string $seccion, int $id, array $valores, array $errores = []): Response
    {
        $flash = $_SESSION['academica_flash'] ?? null;
        unset($_SESSION['academica_flash']);
        $this->view->setLayout('admin/layout.php');
        return $this->view->render($response, 'admin/academica/index.php', [
            'tituloPagina' => 'Gestión de ' . ucfirst($seccion) . ' — SolfAcademy',
            'paginaAdmin' => $seccion === 'instrumentos' ? 'categorias' : $seccion,
            'seccion' => $seccion, 'idEdicion' => $id, 'valores' => $valores,
            'errores' => $errores, 'flash' => $flash, 'opciones' => $this->service->opciones(),
            'listado' => $this->service->listar($seccion, $request->getQueryParams()),
        ]);
    }
}
