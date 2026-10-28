<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Application\Academico\RequisitosService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Views\PhpRenderer;

final class RequisitosController
{
    private PhpRenderer $view;
    private RequisitosService $service;

    public function __construct(PhpRenderer $view, RequisitosService $service)
    {
        $this->view = $view;
        $this->service = $service;
    }

    public function index(Request $request, Response $response): Response
    {
        $flash = $_SESSION['requisitos_flash'] ?? null;
        unset($_SESSION['requisitos_flash']);
        $this->view->setLayout('admin/layout.php');
        return $this->view->render($response, 'admin/requisitos/index.php', [
            'tituloPagina' => 'Gestión de Requisitos — SolfAcademy', 'paginaAdmin' => 'requisitos',
            'listado' => $this->service->listar($request->getQueryParams()), 'flash' => $flash,
        ]);
    }

    public function formulario(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $curso = $this->service->buscar($id);
        if (!$curso) {
            throw new HttpNotFoundException($request);
        }
        $errores = $_SESSION['requisitos_errores'] ?? [];
        $valores = $_SESSION['requisitos_valores'] ?? null;
        unset($_SESSION['requisitos_errores'], $_SESSION['requisitos_valores']);
        $this->view->setLayout('admin/layout.php');
        return $this->view->render($response, 'admin/requisitos/formulario.php', [
            'tituloPagina' => 'Requisitos — ' . $curso['nombre'], 'paginaAdmin' => 'requisitos',
            'curso' => $curso, 'errores' => $errores, 'valores' => $valores,
        ]);
    }

    public function guardar(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        if (!$this->service->buscar($id)) {
            throw new HttpNotFoundException($request);
        }
        $r = $this->service->guardar((array) $request->getParsedBody(), $id);
        if ($r['errores']) {
            $_SESSION['requisitos_errores'] = $r['errores'];
            $_SESSION['requisitos_valores'] = $r['valores'];
        } else {
            $_SESSION['requisitos_flash'] = ['tipo' => 'exito', 'mensaje' => 'Requisitos guardados correctamente.'];
        }
        return $response->withStatus(303)->withHeader('Location', '/admin/requisitos/' . $id);
    }
}
