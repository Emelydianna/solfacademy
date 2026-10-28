<?php

declare(strict_types=1);

namespace App\Controllers\Estudiante;

use App\Application\Estudiante\EstudiantePortalService;
use App\Application\Pagos\ComprobanteStorage;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

/**
 * Pantalla 6 del portal: Pagos y Facturación (/estudiante/pagos).
 * Estado de cuenta, referencia, comprobante y envío del comprobante
 * de pago para su verificación por administración.
 * Acepta filtro por query string: ?estado=Pagado|Pendiente|Fallido
 */
class PagosController
{
    private const FLASH = 'pagos_flash';

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

        $flash = $_SESSION[self::FLASH] ?? null;
        unset($_SESSION[self::FLASH]);

        $this->view->setLayout('estudiante/layout.php');

        return $this->view->render($response, 'estudiante/pagos.php', [
            'tituloPagina' => 'Pagos — Portal del Estudiante',
            'paginaPortal' => 'pagos',
            'datos' => $this->portal->pagos((int) $usuario['id_referencia'], ['estado' => $estado]),
            'filtros' => ['estado' => $estado],
            'flash' => $flash,
            'metodos' => EstudiantePortalService::METODOS_PAGO,
        ]);
    }

    /**
     * POST /estudiante/pagos/{id}/comprobante
     * Registra el movimiento en 'Pendiente' y guarda el archivo. No
     * descuenta saldo: lo hará administración al verificarlo.
     */
    public function subirComprobante(Request $request, Response $response, array $args): Response
    {
        $usuario = $_SESSION['usuario'] ?? [];
        $files = $request->getUploadedFiles();
        $archivo = isset($files['comprobante']) && is_object($files['comprobante']) ? $files['comprobante'] : null;

        $r = $this->portal->enviarComprobante(
            (int) $usuario['id_referencia'],
            (int) ($args['id'] ?? 0),
            (array) $request->getParsedBody(),
            $archivo
        );
        $_SESSION[self::FLASH] = ['tipo' => $r['ok'] ? 'exito' : 'error', 'mensaje' => $r['mensaje']];

        return $response->withStatus(303)->withHeader('Location', '/estudiante/pagos');
    }

    /**
     * GET /estudiante/pagos/comprobante/{archivo}
     * Sirve el comprobante solo si pertenece a un pago del estudiante
     * en sesión. El archivo nunca está en un directorio público.
     */
    public function verComprobante(Request $request, Response $response, array $args): Response
    {
        $usuario = $_SESSION['usuario'] ?? [];
        $archivo = basename((string) ($args['archivo'] ?? ''));

        if (
            $archivo === ''
            || !ComprobanteStorage::existe(ComprobanteStorage::URL . $archivo)
            || !$this->portal->comprobantePropio((int) $usuario['id_referencia'], $archivo)
        ) {
            return $response->withStatus(404);
        }

        $ruta = ComprobanteStorage::rutaAbsoluta($archivo);
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo !== false ? (finfo_file($finfo, $ruta) ?: 'application/octet-stream') : 'application/octet-stream';
        if ($finfo !== false) {
            finfo_close($finfo);
        }

        $stream = new \Slim\Psr7\Stream(fopen($ruta, 'rb'));

        return $response
            ->withHeader('Content-Type', (string) $mime)
            ->withHeader('Content-Disposition', 'inline; filename="' . $archivo . '"')
            ->withHeader('Cache-Control', 'private, max-age=600')
            ->withBody($stream);
    }
}
