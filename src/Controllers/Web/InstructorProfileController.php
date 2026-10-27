<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Application\Catalogo\InstructoresPublicosService;
use App\Application\Perfil\FotoInstructorStorage;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

/**
 * Pantalla 4 del prototipo: Perfil de Instructor (GET /instructores/{id}).
 *
 * Muestra especialidades, experiencia y cursos reales de instructores activos.
 */
class InstructorProfileController
{
    private PhpRenderer $view;
    private InstructoresPublicosService $service;
    private FotoInstructorStorage $fotos;

    public function __construct(PhpRenderer $view, InstructoresPublicosService $service, FotoInstructorStorage $fotos)
    {
        $this->view = $view;
        $this->service = $service;
        $this->fotos = $fotos;
    }

    public function profile(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $instructor = $this->service->detalle($id);

        $this->view->setLayout('web/layout.php');

        if ($instructor === null) {
            return $this->view->render($response->withStatus(404), 'web/instructores/no-encontrado.php', [
                'tituloPagina' => 'Instructor no encontrado — SolfAcademy',
                'paginaActiva' => 'instructores',
            ]);
        }

        $nombreCompleto = $instructor['nombre'] . ' ' . $instructor['apellido'];

        return $this->view->render($response, 'web/instructores/perfil.php', [
            'tituloPagina' => $nombreCompleto . ' — SolfAcademy',
            'paginaActiva' => 'instructores',
            'instructor' => $instructor,
        ]);
    }

    public function foto(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $ruta = $this->fotos->ruta($this->service->foto($id), $id);
        if ($ruta === null) {
            return $response->withStatus(404)->withHeader('Cache-Control', 'no-store');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($ruta);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            return $response->withStatus(404)->withHeader('Cache-Control', 'no-store');
        }
        $archivo = @fopen($ruta, 'rb');
        if ($archivo === false) {
            return $response->withStatus(404);
        }
        return $response->withHeader('Content-Type', $mime)->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Cache-Control', 'no-store')->withBody(new \Slim\Psr7\Stream($archivo));
    }
}
