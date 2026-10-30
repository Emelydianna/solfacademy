<?php

declare(strict_types=1);

namespace App\Controllers\Estudiante;

use App\Application\Estudiante\ReservaService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

class ReservaCabinaController
{
    private PhpRenderer $view;
    private ReservaService $reserva;

    public function __construct(PhpRenderer $view, ReservaService $reserva)
    {
        $this->view = $view;
        $this->reserva = $reserva;
    }

    public function crearFormulario(Request $request, Response $response): Response
    {
        $usuario = $_SESSION['usuario'] ?? [];

        $this->view->setLayout('estudiante/layout.php');
        $flash = $_SESSION['reserva_flash'] ?? null;
        unset($_SESSION['reserva_flash']);

        return $this->view->render($response, 'estudiante/reservas/cabinas.php', [
            'tituloPagina' => 'Reservar Cabina — Portal del Estudiante',
            'paginaPortal' => 'reservas',
            'cabinas' => $this->reserva->getCabinasActivas(),
            'flash' => $flash,
        ]);
    }

    public function crear(Request $request, Response $response): Response
    {
        $usuario = $_SESSION['usuario'] ?? [];

        $body = (array) $request->getParsedBody();
        $res = $this->reserva->reservarCabina((int) $usuario['id_referencia'], $body);
        $_SESSION['reserva_flash'] = ['tipo' => $res['ok'] ? 'exito' : 'error', 'mensaje' => $res['msg']];

        if ($res['ok']) {
            return $response->withStatus(302)->withHeader('Location', '/estudiante/reservas');
        }
        return $response->withStatus(302)->withHeader('Location', '/estudiante/reservas/cabinas');
    }
}
