<?php

declare(strict_types=1);

namespace App\Controllers\Estudiante;

use App\Application\Estudiante\EstudiantePortalService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

/**
 * Pantalla 6 del portal: Pagos y Facturación (/estudiante/pagos).
 * Solo estado de cuenta, referencia y comprobante
 * Acepta filtro por query string: ?estado=Pagado|Pendiente|Fallido
 */
class PagosController
{
    private PhpRenderer $view;
    private EstudiantePortalService $portal;

    public function __construct(PhpRenderer $view, EstudiantePortalService $portal)
    {
        $this->view = $view;
        $this->portal = $portal;
    }

    public function index(Request $request, Response $response): Response
    {
        $usuario = $_SESSION['usuario'] ?? [];


        $params = $request->getQueryParams();
        $estado = isset($params['estado']) ? (string) $params['estado'] : '';
        $estado = in_array($estado, ['Pagado', 'Pendiente', 'Fallido'], true) ? $estado : '';

        $this->view->setLayout('estudiante/layout.php');

        return $this->view->render($response, 'estudiante/pagos.php', [
            'tituloPagina' => 'Pagos — Portal del Estudiante',
            'paginaPortal' => 'pagos',
            'datos' => $this->portal->pagos((int) $usuario['id_referencia'], ['estado' => $estado]),
            'filtros' => ['estado' => $estado],
        ]);
    }
}
