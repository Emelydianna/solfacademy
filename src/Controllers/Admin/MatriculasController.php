<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Application\Academico\MatriculasService;
use App\Application\Admin\GestionAcademicaService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Views\PhpRenderer;

final class MatriculasController
{
    private PhpRenderer $view;
    private MatriculasService $service;

    public function __construct(PhpRenderer $view, MatriculasService $service)
    {
        $this->view = $view;
        $this->service = $service;
    }

    public function index(Request $request, Response $response): Response
    {
        $this->view->setLayout('admin/layout.php');
        return $this->view->render($response, 'admin/matriculas/index.php', [
            'tituloPagina' => 'Gestión de Matrículas — SolfAcademy', 'paginaAdmin' => 'matriculas',
            'listado' => $this->service->listarAdmin($request->getQueryParams()),
        ]);
    }

    public function detalle(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $m = $this->service->detalle($id);
        if (!$m) {
            throw new HttpNotFoundException($request);
        }
        $flash = $_SESSION['matriculas_flash'] ?? null;
        unset($_SESSION['matriculas_flash']);
        $this->view->setLayout('admin/layout.php');
        return $this->view->render($response, 'admin/matriculas/detalle.php', [
            'tituloPagina' => 'Matrícula #' . $id . ' — SolfAcademy', 'paginaAdmin' => 'matriculas',
            'matricula' => $m, 'flash' => $flash,
        ]);
    }

    public function aprobar(Request $request, Response $response, array $args): Response
    {
        $d = (array) $request->getParsedBody();
        try {
            $r = $this->service->aprobar((int) $args['id'], (int) $_SESSION['usuario']['id'],
                GestionAcademicaService::texto($d, 'confirmar_requisitos') === '1',
                GestionAcademicaService::texto($d, 'confirmar_edad') === '1', GestionAcademicaService::texto($d, 'observaciones'));
            $_SESSION['matriculas_flash'] = ['tipo' => 'exito', 'mensaje' => $r['mensaje']];
        } catch (\DomainException $e) {
            $_SESSION['matriculas_flash'] = ['tipo' => 'error', 'mensaje' => $e->getMessage()];
        }
        return $response->withStatus(303)->withHeader('Location', '/admin/matriculas/' . (int) $args['id']);
    }

    public function rechazar(Request $request, Response $response, array $args): Response
    {
        $d = (array) $request->getParsedBody();
        try {
            $r = $this->service->rechazar((int) $args['id'], (int) $_SESSION['usuario']['id'], GestionAcademicaService::texto($d, 'motivo'));
            $_SESSION['matriculas_flash'] = $r['ok'] ? ['tipo' => 'exito', 'mensaje' => $r['mensaje']] : ['tipo' => 'error', 'mensaje' => $r['mensaje']];
        } catch (\DomainException $e) {
            $_SESSION['matriculas_flash'] = ['tipo' => 'error', 'mensaje' => $e->getMessage()];
        }
        return $response->withStatus(303)->withHeader('Location', '/admin/matriculas/' . (int) $args['id']);
    }
}
