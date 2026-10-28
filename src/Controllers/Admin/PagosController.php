<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Application\Admin\PagosService;
use App\Application\Pagos\ComprobanteStorage;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Views\PhpRenderer;

/**
 * Panel de Pagos (/admin/pagos): verificación de comprobantes enviados
 * desde el portal del estudiante. Solo se aprueba o se rechaza; los
 * montos los declara el estudiante y el saldo se recalcula solo.
 */
final class PagosController
{
    private const FLASH = 'pagos_flash';

    private PhpRenderer $view;
    private PagosService $service;

    public function __construct(PhpRenderer $view, PagosService $service)
    {
        $this->view = $view;
        $this->service = $service;
    }

    public function index(Request $request, Response $response): Response
    {
        $this->view->setLayout('admin/layout.php');
        return $this->view->render($response, 'admin/pagos/index.php', [
            'tituloPagina' => 'Pagos — SolfAcademy',
            'paginaAdmin'  => 'pagos',
            'listado'      => $this->service->listar($request->getQueryParams()),
            'resumen'      => $this->service->resumen(),
        ]);
    }

    public function detalle(Request $request, Response $response, array $args): Response
    {
        $pago = $this->service->detalle((int) $args['id']);
        if (!$pago) {
            throw new HttpNotFoundException($request);
        }

        $flash = $_SESSION[self::FLASH] ?? null;
        unset($_SESSION[self::FLASH]);

        $this->view->setLayout('admin/layout.php');
        return $this->view->render($response, 'admin/pagos/detalle.php', [
            'tituloPagina' => 'Pago #' . (int) $args['id'] . ' — SolfAcademy',
            'paginaAdmin'  => 'pagos',
            'pago'         => $pago,
            'flash'        => $flash,
        ]);
    }

    public function aprobar(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        try {
            $r = $this->service->aprobar($id, (int) $_SESSION['usuario']['id']);
            $_SESSION[self::FLASH] = ['tipo' => $r['ok'] ? 'exito' : 'error', 'mensaje' => $r['mensaje']];
        } catch (\DomainException $e) {
            $_SESSION[self::FLASH] = ['tipo' => 'error', 'mensaje' => $e->getMessage()];
        }
        return $response->withStatus(303)->withHeader('Location', '/admin/pagos/' . $id);
    }

    public function rechazar(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $datos = (array) $request->getParsedBody();
        $motivo = (string) ($datos['motivo'] ?? '');
        try {
            $r = $this->service->rechazar($id, (int) $_SESSION['usuario']['id'], $motivo);
            $_SESSION[self::FLASH] = ['tipo' => $r['ok'] ? 'exito' : 'error', 'mensaje' => $r['mensaje']];
        } catch (\DomainException $e) {
            $_SESSION[self::FLASH] = ['tipo' => 'error', 'mensaje' => $e->getMessage()];
        }
        return $response->withStatus(303)->withHeader('Location', '/admin/pagos/' . $id);
    }

    /**
     * GET /admin/pagos/comprobante/{archivo}
     * El archivo vive fuera de public: solo el panel autenticado puede
     * servirlo, validando que el nombre pertenezca a un pago real.
     */
    public function comprobante(Request $request, Response $response, array $args): Response
    {
        $archivo = basename((string) ($args['archivo'] ?? ''));
        if (
            $archivo === ''
            || !ComprobanteStorage::existe(ComprobanteStorage::URL . $archivo)
            || !$this->service->comprobanteExistente($archivo)
        ) {
            return $response->withStatus(404);
        }

        $ruta = ComprobanteStorage::rutaAbsoluta($archivo);
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo !== false ? (finfo_file($finfo, $ruta) ?: 'application/octet-stream') : 'application/octet-stream';
        if ($finfo !== false) {
            finfo_close($finfo);
        }

        return $response
            ->withHeader('Content-Type', (string) $mime)
            ->withHeader('Content-Disposition', 'inline; filename="' . $archivo . '"')
            ->withHeader('Cache-Control', 'private, max-age=600')
            ->withBody(new \Slim\Psr7\Stream(fopen($ruta, 'rb')));
    }
}
