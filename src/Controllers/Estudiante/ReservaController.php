<?php

declare(strict_types=1);

namespace App\Controllers\Estudiante;

use App\Application\Estudiante\ReservaService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

class ReservaController
{
    private PhpRenderer $view;
    private ReservaService $reserva;

    public function __construct(PhpRenderer $view, ReservaService $reserva)
    {
        $this->view = $view;
        $this->reserva = $reserva;
    }

    public function index(Request $request, Response $response): Response
    {
        $usuario = $_SESSION['usuario'] ?? [];

        $this->view->setLayout('estudiante/layout.php');
        $reservas = $this->reserva->getReservasEstudiante((int) $usuario['id_referencia']);
        $flash = $_SESSION['reserva_flash'] ?? null;
        unset($_SESSION['reserva_flash']);

        return $this->view->render($response, 'estudiante/reservas/index.php', [
            'tituloPagina' => 'Mis Reservas — Portal del Estudiante',
            'paginaPortal' => 'reservas',
            'reservas' => $reservas,
            'flash' => $flash,
        ]);
    }

    public function cancelar(Request $request, Response $response, array $args): Response
    {
        $usuario = $_SESSION['usuario'] ?? [];

        $idReserva = (int) ($args['id'] ?? 0);
        $body = (array) $request->getParsedBody();
        $tipo = (string) ($body['tipo'] ?? '');

        $res = $this->reserva->cancelarReserva((int) $usuario['id_referencia'], $idReserva, $tipo);
        $_SESSION['reserva_flash'] = ['tipo' => $res['ok'] ? 'exito' : 'error', 'mensaje' => $res['msg']];

        return $response->withStatus(302)->withHeader('Location', '/estudiante/reservas');
    }
}
