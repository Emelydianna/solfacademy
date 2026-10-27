<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Application\Catalogo\CatalogoService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

/**
 * Pantalla 3 del prototipo: Detalle de Curso (GET /cursos/{id}).
 *
 * Muestra descripción, requisitos y grupos reales del curso.
 */
class CourseDetailController
{
    private PhpRenderer $view;
    private CatalogoService $service;

    public function __construct(PhpRenderer $view, CatalogoService $service)
    {
        $this->view = $view;
        $this->service = $service;
    }

    public function detail(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $curso = $this->service->detalle($id);

        $this->view->setLayout('web/layout.php');

        if ($curso === null) {
            return $this->view->render($response->withStatus(404), 'web/cursos/no-encontrado.php', [
                'tituloPagina' => 'Curso no encontrado — SolfAcademy',
                'paginaActiva' => 'cursos',
            ]);
        }

        return $this->view->render($response, 'web/cursos/detalle.php', [
            'tituloPagina' => $curso['nombre'] . ' — SolfAcademy',
            'paginaActiva' => 'cursos',
            'curso' => $curso,
        ]);
    }
}
