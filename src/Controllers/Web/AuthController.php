<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

/**
 * Pantalla 5 del prototipo: Login y Registro (GET /login y GET /registro).
 *
 * Por ahora solo se muestran los formularios; el envío (POST con sesión de
 * usuario y validación contra la tabla USUARIO) llega en la fase 2 de la
 * aplicación.
 */
class AuthController
{
    private PhpRenderer $view;

    public function __construct(PhpRenderer $view)
    {
        $this->view = $view;
    }

    public function loginForm(Request $request, Response $response): Response
    {
        $this->view->setLayout('web/layout.php');

        return $this->view->render($response, 'web/auth/login.php', [
            'tituloPagina' => 'Iniciar Sesión — SolfAcademy',
            'paginaActiva' => 'login',
        ]);
    }

    public function registerForm(Request $request, Response $response): Response
    {
        $this->view->setLayout('web/layout.php');

        return $this->view->render($response, 'web/auth/registro.php', [
            'tituloPagina' => 'Crear Cuenta — SolfAcademy',
            'paginaActiva' => 'registro',
        ]);
    }
}
