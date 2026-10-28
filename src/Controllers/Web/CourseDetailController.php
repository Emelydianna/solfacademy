<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Application\Catalogo\CatalogoService;
use App\Application\Academico\MatriculasService;
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
    private MatriculasService $matriculas;

    public function __construct(PhpRenderer $view, CatalogoService $service, MatriculasService $matriculas)
    {
        $this->view = $view;
        $this->service = $service;
        $this->matriculas = $matriculas;
    }

    public function detail(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $curso = $this->service->detalle($id);
        if ($curso !== null) {
            $idEstudiante = ($_SESSION['usuario']['rol'] ?? '') === 'estudiante' ? (int) ($_SESSION['usuario']['id_referencia'] ?? 0) : 0;
            $grupos = $this->matriculas->gruposDisponibles($id, $idEstudiante);
            $curso['grupos'] = $grupos['grupos'];
            $curso['matricula_vigente'] = $grupos['vigente'];
        }

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
