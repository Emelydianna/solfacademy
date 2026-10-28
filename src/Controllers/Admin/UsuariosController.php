<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Application\Admin\GestionAcademicaService;
use App\Application\Admin\UsuariosService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Views\PhpRenderer;

/**
 * Usuarios del sistema (/admin/usuarios): cuentas de acceso, roles y
 * seguridad (bloqueo). Solo consulta y bloqueo: el correo y los datos
 * personales se editan desde Estudiantes/Instructores.
 */
final class UsuariosController
{
    private const FLASH = 'usuarios_flash';
    private const ERRORES = 'usuarios_errores';
    private const VALORES = 'usuarios_valores';

    private PhpRenderer $view;
    private UsuariosService $service;

    public function __construct(PhpRenderer $view, UsuariosService $service)
    {
        $this->view = $view;
        $this->service = $service;
    }

    public function index(Request $request, Response $response): Response
    {
        $flash = $_SESSION[self::FLASH] ?? null;
        unset($_SESSION[self::FLASH]);

        $this->view->setLayout('admin/layout.php');
        return $this->view->render($response, 'admin/usuarios/index.php', [
            'tituloPagina' => 'Usuarios del Sistema — SolfAcademy',
            'paginaAdmin'  => 'usuarios',
            'listado'      => $this->service->listar($request->getQueryParams()),
            'flash'        => $flash,
            'idSesion'     => (int) ($_SESSION['usuario']['id'] ?? 0),
        ]);
    }

    public function detalle(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $usuario = $this->service->detalle($id, (int) ($_SESSION['usuario']['id'] ?? 0));
        if (!$usuario) {
            throw new HttpNotFoundException($request);
        }

        $this->view->setLayout('admin/layout.php');
        return $this->view->render($response, 'admin/usuarios/detalle.php', [
            'tituloPagina' => 'Usuario #' . $id . ' — SolfAcademy',
            'paginaAdmin'  => 'usuarios',
            'usuario'      => $usuario,
        ]);
    }

    public function nuevo(Request $request, Response $response): Response
    {
        $errores = $_SESSION[self::ERRORES] ?? [];
        $valores = $_SESSION[self::VALORES] ?? null;
        unset($_SESSION[self::ERRORES], $_SESSION[self::VALORES]);

        $this->view->setLayout('admin/layout.php');
        return $this->view->render($response, 'admin/usuarios/formulario.php', [
            'tituloPagina' => 'Crear usuario — SolfAcademy',
            'paginaAdmin'  => 'usuarios',
            'errores'      => $errores,
            'valores'      => $valores,
            // Las dos listas: la plantilla muestra la del rol elegido.
            'candidatos'   => [
                'estudiante' => $this->service->candidatos('estudiante'),
                'instructor' => $this->service->candidatos('instructor'),
            ],
        ]);
    }

    public function guardar(Request $request, Response $response): Response
    {
        $r = $this->service->crear((array) $request->getParsedBody());

        if ($r['errores']) {
            $_SESSION[self::ERRORES] = $r['errores'];
            $_SESSION[self::VALORES] = $r['valores'];
            return $response->withStatus(303)->withHeader('Location', '/admin/usuarios/nuevo');
        }

        $_SESSION[self::FLASH] = [
            'tipo'    => 'exito',
            'mensaje' => 'Cuenta creada para ' . $r['valores']['email'] . '. Ya puede iniciar sesión con la contraseña que definiste.',
        ];
        return $response->withStatus(303)->withHeader('Location', '/admin/usuarios');
    }

    public function bloqueo(Request $request, Response $response, array $args): Response
    {
        $datos = (array) $request->getParsedBody();
        $accion = GestionAcademicaService::texto($datos, 'accion');
        if (!in_array($accion, ['bloquear', 'desbloquear'], true)) {
            $_SESSION[self::FLASH] = ['tipo' => 'error', 'mensaje' => 'Acción no válida.'];
            return $response->withStatus(303)->withHeader('Location', '/admin/usuarios');
        }

        $_SESSION[self::FLASH] = $this->service->bloquear(
            (int) $args['id'],
            $accion === 'bloquear',
            (int) ($_SESSION['usuario']['id'] ?? 0)
        );

        return $response->withStatus(303)->withHeader('Location', '/admin/usuarios');
    }
}
