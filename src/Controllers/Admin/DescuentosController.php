<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Application\Descuentos\DescuentosService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Views\PhpRenderer;

/**
 * Códigos de descuento (/admin/descuentos): alta, edición y activación
 * de cupones por parte del administrador. No se borran: se desactivan.
 */
final class DescuentosController
{
    private const FLASH = 'descuentos_flash';
    private const ERRORES = 'descuentos_errores';
    private const VALORES = 'descuentos_valores';

    private PhpRenderer $view;
    private DescuentosService $service;

    public function __construct(PhpRenderer $view, DescuentosService $service)
    {
        $this->view = $view;
        $this->service = $service;
    }

    public function index(Request $request, Response $response): Response
    {
        $flash = $_SESSION[self::FLASH] ?? null;
        unset($_SESSION[self::FLASH]);

        $this->view->setLayout('admin/layout.php');
        return $this->view->render($response, 'admin/descuentos/index.php', [
            'tituloPagina' => 'Descuentos — SolfAcademy',
            'paginaAdmin'  => 'descuentos',
            'listado'      => $this->service->listar($request->getQueryParams()),
            'flash'        => $flash,
        ]);
    }

    /** Alta (?editar no aplica: el alta usa /admin/descuentos/nuevo). */
    public function nuevo(Request $request, Response $response): Response
    {
        return $this->formulario($request, $response, ['id' => 0]);
    }

    public function formulario(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $descuento = $id > 0 ? $this->service->buscar($id) : null;
        if ($id > 0 && !$descuento) {
            throw new HttpNotFoundException($request);
        }

        $errores = $_SESSION[self::ERRORES] ?? [];
        $valores = $_SESSION[self::VALORES] ?? null;
        unset($_SESSION[self::ERRORES], $_SESSION[self::VALORES]);

        $this->view->setLayout('admin/layout.php');
        return $this->view->render($response, 'admin/descuentos/formulario.php', [
            'tituloPagina' => ($id > 0 ? 'Editar código' : 'Nuevo código') . ' de descuento — SolfAcademy',
            'paginaAdmin'  => 'descuentos',
            'descuento'    => $descuento,
            'cursos'       => $this->service->cursos(),
            'errores'      => $errores,
            'valores'      => $valores,
            'sugerido'     => $valores === null ? $this->service->sugerirCodigo() : '',
        ]);
    }

    public function guardarNuevo(Request $request, Response $response): Response
    {
        return $this->guardar($request, $response, ['id' => 0]);
    }

    public function guardar(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        $r = $this->service->guardar((array) $request->getParsedBody(), $id);

        if ($r['errores']) {
            $_SESSION[self::ERRORES] = $r['errores'];
            $_SESSION[self::VALORES] = $r['valores'];
            $destino = $id > 0 ? '/admin/descuentos/' . $id : '/admin/descuentos/nuevo';
            return $response->withStatus(303)->withHeader('Location', $destino);
        }

        $_SESSION[self::FLASH] = [
            'tipo'    => 'exito',
            'mensaje' => $id > 0
                ? 'Código "' . $r['valores']['codigo'] . '" actualizado correctamente.'
                : 'Código "' . $r['valores']['codigo'] . '" creado. Compártelo con tus estudiantes para que lo usen al matricularse.',
        ];
        return $response->withStatus(303)->withHeader('Location', '/admin/descuentos');
    }

    public function estado(Request $request, Response $response, array $args): Response
    {
        $datos = (array) $request->getParsedBody();
        $activo = (string) ($datos['activo'] ?? '');
        $_SESSION[self::FLASH] = $this->service->cambiarEstado((int) $args['id'], $activo === '1' ? 1 : 0);

        return $response->withStatus(303)->withHeader('Location', '/admin/descuentos');
    }
}
