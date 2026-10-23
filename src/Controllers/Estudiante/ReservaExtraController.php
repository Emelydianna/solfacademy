<?php

declare(strict_types=1);

namespace App\Controllers\Estudiante;

use App\Application\Estudiante\ReservaService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

class ReservaExtraController
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
        if (($usuario['rol'] ?? '') !== 'estudiante') {
            return $response->withStatus(302)->withHeader('Location', '/');
        }

        $this->view->setLayout('estudiante/layout.php');
        $mensaje = $_SESSION['reserva_msg'] ?? null;
        $tipoMsg = $_SESSION['reserva_msg_tipo'] ?? null;
        unset($_SESSION['reserva_msg'], $_SESSION['reserva_msg_tipo']);

        return $this->view->render($response, 'estudiante/reservas/extras.php', [
            'tituloPagina' => 'Solicitar Clase Extra — Portal del Estudiante',
            'paginaPortal' => 'reservas',
            'grupos' => $this->reserva->getGruposActivosParaEstudiante((int) $usuario['id_referencia']),
            'mensaje' => $mensaje,
            'tipoMsg' => $tipoMsg,
        ]);
    }

    public function crear(Request $request, Response $response): Response
    {
        $usuario = $_SESSION['usuario'] ?? [];
        if (($usuario['rol'] ?? '') !== 'estudiante') {
            return $response->withStatus(302)->withHeader('Location', '/');
        }

        $body = (array) $request->getParsedBody();
        $res = $this->reserva->reservarClaseExtra((int) $usuario['id_referencia'], $body);
        $_SESSION['reserva_msg'] = $res['msg'];
        $_SESSION['reserva_msg_tipo'] = $res['ok'] ? 'success' : 'danger';

        if ($res['ok']) {
            return $response->withStatus(302)->withHeader('Location', '/estudiante/reservas');
        }
        return $response->withStatus(302)->withHeader('Location', '/estudiante/reservas/extras');
    }
}
