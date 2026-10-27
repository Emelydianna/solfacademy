<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Application\Admin\GestionAcademicaService;
use App\Application\Admin\InstructoresService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;
use Slim\Exception\HttpNotFoundException;
use Slim\Views\PhpRenderer;

final class InstructoresController
{
    private PhpRenderer $view;
    private InstructoresService $service;

    public function __construct(PhpRenderer $view, InstructoresService $service)
    {
        $this->view = $view;
        $this->service = $service;
    }

    private function instructor(Request $request, int $id): array
    {
        $instructor = $this->service->buscar($id);
        if (!$instructor) {
            throw new HttpNotFoundException($request);
        }
        return $instructor;
    }

    public function index(Request $request, Response $response): Response
    {
        $this->view->setLayout('admin/layout.php');
        return $this->view->render($response, 'admin/instructores/index.php', [
            'tituloPagina' => 'Gestión de Instructores — SolfAcademy', 'paginaAdmin' => 'instructores',
            'listado' => $this->service->listar($request->getQueryParams()), 'instrumentos' => $this->service->instrumentos(),
        ]);
    }

    public function formulario(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $instructor = $id ? $this->instructor($request, $id) : null;
        return $this->renderFormulario($response, $id, $instructor ?? ['anios_experiencia' => '0',
            'especialidades' => [['id_instrumento' => '', 'nivel_dominio' => 'intermedio', 'anios_experiencia_i' => '0']]], [], $instructor);
    }

    public function guardar(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $instructor = $id ? $this->instructor($request, $id) : null;
        $foto = $request->getUploadedFiles()['foto'] ?? null;
        if ($foto !== null && !$foto instanceof UploadedFileInterface) {
            return $this->renderFormulario($response->withStatus(422), $id, $instructor ?? [], ['foto' => 'Adjunta una única imagen.'], $instructor);
        }
        $r = $this->service->guardar((array) $request->getParsedBody(), $id, $foto);
        if ($r['errores']) {
            return $this->renderFormulario($response->withStatus(422), $id, $r['valores'], $r['errores'], $instructor);
        }
        $_SESSION['instructores_flash'] = ['tipo' => 'exito', 'mensaje' => 'Instructor, especialidades y acceso guardados correctamente.'];
        return $response->withStatus(303)->withHeader('Location', '/admin/instructores/' . $r['id']);
    }

    public function ver(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $instructor = $this->instructor($request, $id);
        $flash = $_SESSION['instructores_flash'] ?? null;
        unset($_SESSION['instructores_flash']);
        $this->view->setLayout('admin/layout.php');
        return $this->view->render($response, 'admin/instructores/detalle.php', [
            'tituloPagina' => 'Ficha del Instructor — SolfAcademy', 'paginaAdmin' => 'instructores',
            'instructor' => $instructor, 'grupos' => $this->service->grupos($id), 'extras' => $this->service->extras($id), 'flash' => $flash,
        ]);
    }

    public function estado(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $this->instructor($request, $id);
        $d = (array) $request->getParsedBody();
        $_SESSION['instructores_flash'] = $this->service->cambiarEstado($id,
            GestionAcademicaService::texto($d, 'activo'), GestionAcademicaService::texto($d, 'confirmar') === '1');
        return $response->withStatus(303)->withHeader('Location', '/admin/instructores/' . $id . '#estado-instructor');
    }

    private function renderFormulario(Response $response, int $id, array $valores, array $errores, ?array $instructor): Response
    {
        $this->view->setLayout('admin/layout.php');
        return $this->view->render($response, 'admin/instructores/formulario.php', [
            'tituloPagina' => ($id ? 'Editar' : 'Nuevo') . ' Instructor — SolfAcademy', 'paginaAdmin' => 'instructores',
            'idEdicion' => $id, 'valores' => $valores, 'errores' => $errores, 'instructor' => $instructor,
            'instrumentos' => $this->service->instrumentos(),
        ]);
    }
}
