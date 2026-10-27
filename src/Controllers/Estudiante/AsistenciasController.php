<?php

declare(strict_types=1);

namespace App\Controllers\Estudiante;

use App\Application\Estudiante\EstudiantePortalService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

/**
 * Pantalla 4 del portal: Asistencias e Historial (/estudiante/asistencias).
 * Acepta filtros por query string: ?curso=, ?estado=, ?desde=, ?hasta=
 */
class AsistenciasController
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

        $rango = isset($params['rango']) ? (string) $params['rango'] : '';
        $desde = self::fechaValida($params['desde'] ?? null);
        $hasta = self::fechaValida($params['hasta'] ?? null);

        // Un rango predefinido (en días) equivale a fijar la fecha "desde".
        if ($desde === '' && $hasta === '' && $rango !== '' && ctype_digit($rango)) {
            $desde = date('Y-m-d', strtotime('-' . (int) $rango . ' days'));
        }

        $filtros = [
            'curso'  => isset($params['curso']) ? (string) $params['curso'] : '',
            'estado' => isset($params['estado']) ? (string) $params['estado'] : '',
            'rango'  => $rango,
            'desde'  => $desde,
            'hasta'  => $hasta,
        ];

        $this->view->setLayout('estudiante/layout.php');

        return $this->view->render($response, 'estudiante/asistencias.php', [
            'tituloPagina' => 'Asistencias — Portal del Estudiante',
            'paginaPortal' => 'asistencias',
            'datos' => $this->portal->asistencias((int) $usuario['id_referencia'], $filtros),
            'filtros' => $filtros,
        ]);
    }

    /**
     * Valida un valor YYYY-MM-DD; devuelve '' si no lo es.
     */
    private static function fechaValida(mixed $valor): string
    {
        $valor = is_string($valor) ? trim($valor) : '';

        if ($valor === '') {
            return '';
        }

        $fecha = \DateTimeImmutable::createFromFormat('Y-m-d', $valor);

        return $fecha !== false && $fecha->format('Y-m-d') === $valor ? $valor : '';
    }
}
