<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Data\SampleData;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

/**
 * Pantalla 3 del prototipo: Detalle de Curso (GET /cursos/{id}).
 *
 * Muestra descripción, temario, reseñas, requisitos y datos del grupo.
 * Cuando haya BD se sustituye SampleData::cursoDetalle() por consultas
 * sobre CURSO, INSTRUCTOR, REQUISITO CURSO, GRUPO, HORARIO y AULA.
 */
class CourseDetailController
{
    private PhpRenderer $view;

    public function __construct(PhpRenderer $view)
    {
        $this->view = $view;
    }

    public function detail(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $curso = SampleData::cursoDetalle($id);

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
