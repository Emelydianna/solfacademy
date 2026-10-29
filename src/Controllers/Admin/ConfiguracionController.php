<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Application\Config\ConfiguracionService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

/**
 * Configuración de la academia (/admin/configuracion): identidad, contacto,
 * redes sociales, pie de página, nota de los PDF y logo. Los datos se
 * reflejan en los tres portales, en la web pública y en los documentos.
 */
final class ConfiguracionController
{
    private const FLASH = 'configuracion_flash';
    private const ERRORES = 'configuracion_errores';
    private const VALORES = 'configuracion_valores';

    private PhpRenderer $view;
    private ConfiguracionService $service;

    public function __construct(PhpRenderer $view, ConfiguracionService $service)
    {
        $this->view = $view;
        $this->service = $service;
    }

    public function index(Request $request, Response $response): Response
    {
        $flash = $_SESSION[self::FLASH] ?? null;
        unset($_SESSION[self::FLASH]);

        $errores = $_SESSION[self::ERRORES] ?? [];
        $valores = $_SESSION[self::VALORES] ?? null;
        unset($_SESSION[self::ERRORES], $_SESSION[self::VALORES]);

        $this->view->setLayout('admin/layout.php');
        return $this->view->render($response, 'admin/configuracion/index.php', [
            'tituloPagina' => 'Configuración de la Academia — SolfAcademy',
            'paginaAdmin'  => 'config',
            'errores'      => $errores,
            'valores'      => $valores,
            'flash'        => $flash,
            'hayLogo'      => ConfiguracionService::archivoLogo() !== null,
        ]);
    }

    public function guardar(Request $request, Response $response): Response
    {
        $r = $this->service->guardar((array) $request->getParsedBody());

        if ($r['errores']) {
            $_SESSION[self::ERRORES] = $r['errores'];
            $_SESSION[self::VALORES] = $r['valores'];
            return $response->withStatus(303)->withHeader('Location', '/admin/configuracion');
        }

        $_SESSION[self::FLASH] = [
            'tipo'    => 'exito',
            'mensaje' => 'Configuración guardada. Los portales, la web y los PDF ya muestran estos datos.',
        ];

        return $response->withStatus(303)->withHeader('Location', '/admin/configuracion');
    }

    public function logo(Request $request, Response $response): Response
    {
        $files = $request->getUploadedFiles();
        $archivo = isset($files['logo']) && is_object($files['logo']) ? $files['logo'] : null;

        $r = $this->service->guardarLogo($archivo);
        $_SESSION[self::FLASH] = ['tipo' => $r['ok'] ? 'exito' : 'error', 'mensaje' => $r['mensaje']];

        return $response->withStatus(303)->withHeader('Location', '/admin/configuracion');
    }

    public function quitarLogo(Request $request, Response $response): Response
    {
        $r = $this->service->quitarLogo();
        $_SESSION[self::FLASH] = ['tipo' => $r['ok'] ? 'exito' : 'error', 'mensaje' => $r['mensaje']];

        return $response->withStatus(303)->withHeader('Location', '/admin/configuracion');
    }
}
