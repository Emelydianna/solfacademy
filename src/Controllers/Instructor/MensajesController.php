<?php

declare(strict_types=1);

namespace App\Controllers\Instructor;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

class MensajesController
{
    private PhpRenderer $view;

    public function __construct(PhpRenderer $view)
    {
        $this->view = $view;
    }

    public function index(Request $request, Response $response): Response
    {
        $usuario = $_SESSION['usuario'] ?? [];
        if (($usuario['rol'] ?? '') !== 'instructor') {
            return $response->withStatus(302)->withHeader('Location', '/');
        }
        $params = $request->getQueryParams();
        $this->view->setLayout('instructor/layout.php');
        return $this->view->render($response, 'instructor/mensajes/index.php', [
            'tituloPagina' => 'Mensajes',
            'paginaInstructor' => 'grupos',
            'alumnoId' => $params['alumno'] ?? null,
        ]);
    }
}
