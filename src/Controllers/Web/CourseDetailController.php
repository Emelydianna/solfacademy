<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Application\Catalogo\CatalogoService;
use App\Application\Academico\MatriculasService;
use App\Application\Imagen\ImagenCursoStorage;
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
    private ImagenCursoStorage $fotos;

    public function __construct(PhpRenderer $view, CatalogoService $service, MatriculasService $matriculas, ImagenCursoStorage $fotos)
    {
        $this->view = $view;
        $this->service = $service;
        $this->matriculas = $matriculas;
        $this->fotos = $fotos;
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

    /**
     * Imagen de referencia del curso (GET /cursos/{id}/imagen).
     *
     * Sirve el fichero de var/uploads/cursos. Devuelve 404 si el curso no
     * tiene imagen, si el archivo ya no existe o si no es una imagen.
     */
    public function imagen(Request $request, Response $response, array $args): Response
    {
        $ruta = $this->fotos->ruta($this->service->imagenCurso((int) $args['id']));
        if ($ruta === null) {
            return $response->withStatus(404)->withHeader('Cache-Control', 'no-store');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($ruta);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return $response->withStatus(404)->withHeader('Cache-Control', 'no-store');
        }
        $archivo = @fopen($ruta, 'rb');
        if ($archivo === false) {
            return $response->withStatus(404);
        }
        return $response->withHeader('Content-Type', (string) $mime)
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Cache-Control', 'public, max-age=3600')
            ->withBody(new \Slim\Psr7\Stream($archivo));
    }
}
