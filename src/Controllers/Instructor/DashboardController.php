<?php

declare(strict_types=1);

namespace App\Controllers\Instructor;

use App\Application\Instructor\InstructorService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

class DashboardController
{
    private PhpRenderer $view;
    private InstructorService $service;

    public function __construct(PhpRenderer $view, InstructorService $service)
    {
        $this->view = $view;
        $this->service = $service;
    }

    public function index(Request $request, Response $response): Response
    {
        $usuario = $_SESSION['usuario'] ?? [];
        $this->view->setLayout('instructor/layout.php');
        $data = $this->service->dashboard((int) $usuario['id_referencia']);
        return $this->view->render($response, 'instructor/dashboard.php', [
            'tituloPagina' => 'Dashboard Instructor',
            'paginaInstructor' => 'dashboard',
            'data' => $data,
            'usuario' => $usuario,
        ]);
    }
}
