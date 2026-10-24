<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Application\Auth\AuthService;
use PDOException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

/**
 * Autenticación del sitio web (pantalla 5 del prototipo + fase 2):
 * GET  /login, /registro  → formularios
 * POST /login, /registro  → acciones reales contra la tabla USUARIO
 * GET  /logout            → cierra la sesión
 */
class AuthController
{
    private PhpRenderer $view;
    private AuthService $auth;

    public function __construct(PhpRenderer $view, AuthService $auth)
    {
        $this->view = $view;
        $this->auth = $auth;
    }

    public function loginForm(Request $request, Response $response): Response
    {
        $this->view->setLayout('web/layout.php');

        return $this->view->render($response, 'web/auth/login.php', [
            'tituloPagina' => 'Iniciar Sesión — SolfAcademy',
            'paginaActiva' => 'login',
        ]);
    }

    public function login(Request $request, Response $response): Response
    {
        $datos = (array) $request->getParsedBody();
        $email = trim((string) ($datos['email'] ?? ''));
        $password = (string) ($datos['password'] ?? '');

        $usuario = ($email !== '' && $password !== '')
            ? $this->auth->intentarLogin($email, $password)
            : null;

        if ($usuario === null) {
            $this->view->setLayout('web/layout.php');

            return $this->view->render($response->withStatus(422), 'web/auth/login.php', [
                'tituloPagina' => 'Iniciar Sesión — SolfAcademy',
                'paginaActiva' => 'login',
                'errorLogin' => 'El correo o la contraseña no son correctos.',
                'emailAnterior' => $email,
            ]);
        }

        return $this->redirigirSegunRol($response, (string) $usuario['rol']);
    }

    public function registerForm(Request $request, Response $response): Response
    {
        $this->view->setLayout('web/layout.php');

        return $this->view->render($response, 'web/auth/registro.php', [
            'tituloPagina' => 'Crear Cuenta — SolfAcademy',
            'paginaActiva' => 'registro',
        ]);
    }

    public function registro(Request $request, Response $response): Response
    {
        $datos = (array) $request->getParsedBody();
        $nombre = trim((string) ($datos['nombre'] ?? ''));
        $apellido = trim((string) ($datos['apellido'] ?? ''));
        $email = trim((string) ($datos['email'] ?? ''));
        $password = (string) ($datos['password'] ?? '');
        $password2 = (string) ($datos['password2'] ?? '');
        $terminos = isset($datos['terminos']);

        $error = null;

        if ($nombre === '' || $apellido === '' || $email === '') {
            $error = 'Completa todos los campos personales.';
        } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $error = 'El correo electrónico no tiene un formato válido.';
        } elseif (strlen($password) < 8) {
            $error = 'La contraseña debe tener al menos 8 caracteres.';
        } elseif ($password !== $password2) {
            $error = 'Las contraseñas no coinciden.';
        } elseif (!$terminos) {
            $error = 'Debes aceptar los Términos y Condiciones.';
        }

        if ($error === null) {
            try {
                $this->auth->registrar($nombre, $apellido, $email, $password);

                return $this->redirigirSegunRol($response, 'estudiante');
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $error = 'Ya existe una cuenta con ese correo electrónico.';
                } else {
                    throw $e;
                }
            }
        }

        $this->view->setLayout('web/layout.php');

        return $this->view->render($response->withStatus(422), 'web/auth/registro.php', [
            'tituloPagina' => 'Crear Cuenta — SolfAcademy',
            'paginaActiva' => 'registro',
            'errorRegistro' => $error,
            'datosAnteriores' => [
                'nombre' => $nombre,
                'apellido' => $apellido,
                'email' => $email,
            ],
        ]);
    }

    public function logout(Request $request, Response $response): Response
    {
        $this->auth->cerrarSesion();

        return $response->withStatus(302)->withHeader('Location', '/');
    }

    private function redirigirSegunRol(Response $response, string $rol): Response
    {
        $destino = match ($rol) {
            'estudiante' => '/estudiante/dashboard',
            'instructor' => '/instructor/dashboard',
            'admin' => '/admin/dashboard',
            default => '/',
        };

        return $response->withStatus(302)->withHeader('Location', $destino);
    }
}
