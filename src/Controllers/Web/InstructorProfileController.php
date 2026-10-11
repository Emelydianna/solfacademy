<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Data\SampleData;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

/**
 * Pantalla 4 del prototipo: Perfil de Instructor (GET /instructores/{id}).
 *
 * Muestra biografía, especialidades, experiencia, valoración y los cursos
 * que imparte. Cuando haya BD se sustituye SampleData::instructorDetalle()
 * por consultas sobre INSTRUCTOR, INSTRUCTOR-INSTRUMENTO y CURSO.
 */
class InstructorProfileController
{
    private PhpRenderer $view;

    public function __construct(PhpRenderer $view)
    {
        $this->view = $view;
    }

    public function profile(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $instructor = SampleData::instructorDetalle($id);

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
}
