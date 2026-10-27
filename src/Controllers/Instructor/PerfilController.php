<?php

declare(strict_types=1);

namespace App\Controllers\Instructor;

use App\Application\Auth\AuthService;
use App\Application\Instructor\InstructorService;
use App\Application\Perfil\InstructorFotoService;
use PDOException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

class PerfilController
{
    private const FLASH = 'flash_perfil';

    private PhpRenderer $view;
    private InstructorService $service;
    private AuthService $auth;
    private InstructorFotoService $fotos;

    public function __construct(PhpRenderer $view, InstructorService $service, AuthService $auth, InstructorFotoService $fotos)
    {
        $this->view = $view;
        $this->service = $service;
        $this->auth = $auth;
        $this->fotos = $fotos;
    }

    public function index(Request $request, Response $response): Response
    {
        $usuario = $_SESSION['usuario'] ?? [];

        $this->view->setLayout('instructor/layout.php');
        $perfil = $this->service->perfilInstructor((int) $usuario['id_referencia']);
        $flash = $this->leerFlash();

        return $this->view->render($response, 'instructor/perfil/index.php', [
            'tituloPagina' => 'Mi Perfil — Portal del Instructor',
            'paginaInstructor' => 'perfil',
            'perfil' => $perfil,
            'usuario' => $usuario,
            'usuarioSesion' => $usuario,
            'valores' => $this->valoresDesdePerfil($perfil, $usuario),
            'errores' => [],
            'flash' => $flash,
        ]);
    }

    public function actualizar(Request $request, Response $response): Response
    {
        $usuario = $_SESSION['usuario'] ?? [];

        $valores = $this->leerValores($request);
        $errores = $this->validarPerfil($valores);

        $perfil = $this->service->perfilInstructor((int) $usuario['id_referencia']);

        if ($errores === []) {
            try {
                $this->service->actualizarPerfilInstructor((int) $usuario['id_referencia'], $valores);

                $nombreCompleto = trim($valores['nombre'] . ' ' . $valores['apellido']);
                if ($nombreCompleto !== '') {
                    $_SESSION['usuario']['nombre'] = $nombreCompleto;
                }
                if (isset($valores['email'])) {
                    $_SESSION['usuario']['email'] = trim((string) $valores['email']);
                }

                $_SESSION[self::FLASH] = ['tipo' => 'exito', 'mensaje' => 'Tus datos se han guardado correctamente.'];
                return $response->withStatus(302)->withHeader('Location', '/instructor/perfil');
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $errores['email'] = 'Ya existe una cuenta con ese correo electrónico.';
                } else {
                    throw $e;
                }
            } catch (\DomainException $e) {
                $errores['email'] = $e->getMessage();
            } catch (\Throwable $e) {
                $_SESSION[self::FLASH] = ['tipo' => 'error', 'mensaje' => 'No se pudo actualizar el perfil.'];
                return $response->withStatus(302)->withHeader('Location', '/instructor/perfil');
            }
        }

        $this->view->setLayout('instructor/layout.php');
        return $this->view->render($response->withStatus(422), 'instructor/perfil/index.php', [
            'tituloPagina' => 'Mi Perfil — Portal del Instructor',
            'paginaInstructor' => 'perfil',
            'perfil' => $perfil,
            'usuario' => $usuario,
            'usuarioSesion' => $usuario,
            'valores' => $valores,
            'errores' => $errores,
            'flash' => null,
        ]);
    }

    public function cambiarPassword(Request $request, Response $response): Response
    {
        $usuario = $_SESSION['usuario'] ?? [];

        $datos = (array) $request->getParsedBody();
        $actual = (string) ($datos['password_actual'] ?? '');
        $nueva = (string) ($datos['password_nueva'] ?? '');
        $confirmar = (string) ($datos['password_confirmar'] ?? '');

        $erroresPassword = [];

        if ($actual === '') {
            $erroresPassword['password_actual'] = 'Escribe tu contraseña actual.';
        }
        if (strlen($nueva) < 8) {
            $erroresPassword['password_nueva'] = 'La contraseña debe tener al menos 8 caracteres.';
        } elseif ($nueva === $actual) {
            $erroresPassword['password_nueva'] = 'La nueva contraseña debe ser distinta de la actual.';
        }
        if ($confirmar !== $nueva) {
            $erroresPassword['password_confirmar'] = 'Las contraseñas no coinciden.';
        }

        if ($erroresPassword === []) {
            if ($this->auth->cambiarPassword((int) $usuario['id'], $actual, $nueva)) {
                $_SESSION[self::FLASH] = ['tipo' => 'exito', 'mensaje' => 'Contraseña actualizada. Usa la nueva en tu próximo acceso.'];
                return $response->withStatus(302)->withHeader('Location', '/instructor/perfil');
            }

            $erroresPassword['password_actual'] = 'La contraseña actual no es correcta.';
        }

        $perfil = $this->service->perfilInstructor((int) $usuario['id_referencia']);
        $this->view->setLayout('instructor/layout.php');

        return $this->view->render($response->withStatus(422), 'instructor/perfil/index.php', [
            'tituloPagina' => 'Mi Perfil — Portal del Instructor',
            'paginaInstructor' => 'perfil',
            'perfil' => $perfil,
            'usuario' => $usuario,
            'usuarioSesion' => $usuario,
            'valores' => array_merge($this->valoresDesdePerfil($perfil, $usuario), [
                'password_actual' => '',
                'password_nueva' => '',
                'password_confirmar' => '',
            ]),
            'errores' => $erroresPassword,
            'flash' => null,
        ]);
    }

    public function subirFoto(Request $request, Response $response): Response
    {
        $usuario = $_SESSION['usuario'] ?? [];
        $idInstructor = (int) $usuario['id_referencia'];
        $uploadedFiles = $request->getUploadedFiles();
        if (!isset($uploadedFiles['foto']) || !$uploadedFiles['foto'] instanceof \Psr\Http\Message\UploadedFileInterface) {
            $_SESSION[self::FLASH] = ['tipo' => 'error', 'mensaje' => 'Selecciona una imagen para subir.'];
            return $response->withStatus(302)->withHeader('Location', '/instructor/perfil');
        }
        try {
            $this->fotos->guardar($idInstructor, $uploadedFiles['foto']);
        } catch (\DomainException $e) {
            $_SESSION[self::FLASH] = ['tipo' => 'error', 'mensaje' => $e->getMessage()];
            return $response->withStatus(302)->withHeader('Location', '/instructor/perfil');
        } catch (\Throwable $e) {
            $_SESSION[self::FLASH] = ['tipo' => 'error', 'mensaje' => 'No se pudo guardar la foto en el perfil.'];
            return $response->withStatus(302)->withHeader('Location', '/instructor/perfil');
        }
        $_SESSION[self::FLASH] = ['tipo' => 'exito', 'mensaje' => 'Foto de perfil actualizada correctamente.'];
        return $response->withStatus(302)->withHeader('Location', '/instructor/perfil');
    }

    private function leerFlash(): ?array
    {
        $flash = $_SESSION[self::FLASH] ?? null;
        unset($_SESSION[self::FLASH]);
        return is_array($flash) ? $flash : null;
    }

    private function leerValores(Request $request): array
    {
        $datos = (array) $request->getParsedBody();
        $limpiar = static fn (string $clave): string => \App\Application\Admin\GestionAcademicaService::texto($datos, $clave);

        return [
            'nombre' => $limpiar('nombre'),
            'apellido' => $limpiar('apellido'),
            'email' => $limpiar('email'),
            'telefono' => $limpiar('telefono'),
        ];
    }

    private function valoresDesdePerfil(array $perfil, array $usuario): array
    {
        return [
            'nombre' => (string) ($perfil['nombre'] ?? ($usuario['nombre'] ?? '')),
            'apellido' => (string) ($perfil['apellido'] ?? ''),
            'email' => (string) ($perfil['email'] ?? ($usuario['email'] ?? '')),
            'telefono' => (string) ($perfil['telefono'] ?? ''),
        ];
    }

    private function validarPerfil(array $valores): array
    {
        $errores = [];

        if ($valores['nombre'] === '') {
            $errores['nombre'] = 'El nombre es obligatorio.';
        } elseif (mb_strlen($valores['nombre']) > 100) {
            $errores['nombre'] = 'Máximo 100 caracteres.';
        }

        if ($valores['apellido'] === '') {
            $errores['apellido'] = 'Los apellidos son obligatorios.';
        } elseif (mb_strlen($valores['apellido']) > 100) {
            $errores['apellido'] = 'Máximo 100 caracteres.';
        }

        if ($valores['email'] === '') {
            $errores['email'] = 'El correo electrónico es obligatorio.';
        } elseif (filter_var($valores['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errores['email'] = 'El correo electrónico no tiene un formato válido.';
        } elseif (mb_strlen($valores['email']) > 150) {
            $errores['email'] = 'Máximo 150 caracteres.';
        }

        if ($valores['telefono'] !== '') {
            if (mb_strlen($valores['telefono']) > 20) {
                $errores['telefono'] = 'Máximo 20 caracteres.';
            } elseif (preg_match('/^[0-9+()\-\s]+$/', $valores['telefono']) !== 1) {
                $errores['telefono'] = 'Usa solo dígitos, espacios y los símbolos + ( ) -';
            }
        }

        return $errores;
    }
}
