<?php

declare(strict_types=1);

namespace App\Controllers\Instructor;

use App\Application\Auth\AuthService;
use App\Application\Instructor\InstructorService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

class PerfilController
{
    private const FLASH = 'flash_perfil';

    private PhpRenderer $view;
    private InstructorService $service;
    private AuthService $auth;

    public function __construct(PhpRenderer $view, InstructorService $service, AuthService $auth)
    {
        $this->view = $view;
        $this->service = $service;
        $this->auth = $auth;
    }

    public function index(Request $request, Response $response): Response
    {
        $usuario = $_SESSION['usuario'] ?? [];
        if (($usuario['rol'] ?? '') !== 'instructor') {
            return $response->withStatus(302)->withHeader('Location', '/');
        }
        $this->view->setLayout('instructor/layout.php');
        $perfil = $this->service->perfilInstructor((int) $usuario['id_referencia']);
        $flash = $this->leerFlash();
        return $this->view->render($response, 'instructor/perfil/index.php', [
            'tituloPagina' => 'Mi Perfil',
            'paginaInstructor' => 'perfil',
            'perfil' => $perfil,
            'usuario' => $usuario,
            'flash' => $flash,
        ]);
    }

    public function actualizar(Request $request, Response $response): Response
    {
        $usuario = $_SESSION['usuario'] ?? [];
        if (($usuario['rol'] ?? '') !== 'instructor') {
            return $response->withStatus(302)->withHeader('Location', '/');
        }
        $body = (array) $request->getParsedBody();
        try {
            $this->service->actualizarPerfilInstructor((int) $usuario['id_referencia'], $body);
            $_SESSION['usuario']['nombre'] = trim(($body['nombre'] ?? '') . ' ' . ($body['apellido'] ?? ''));
            if (isset($body['email'])) {
                $_SESSION['usuario']['email'] = trim((string) $body['email']);
            }
            $_SESSION[self::FLASH] = ['tipo' => 'exito', 'mensaje' => 'Perfil actualizado correctamente.'];
        } catch (\Throwable $e) {
            $_SESSION[self::FLASH] = ['tipo' => 'error', 'mensaje' => 'No se pudo actualizar el perfil.'];
        }
        return $response->withStatus(302)->withHeader('Location', '/instructor/perfil');
    }

    public function subirFoto(Request $request, Response $response): Response
    {
        $usuario = $_SESSION['usuario'] ?? [];
        if (($usuario['rol'] ?? '') !== 'instructor') {
            return $response->withStatus(302)->withHeader('Location', '/');
        }
        $idInstructor = (int) $usuario['id_referencia'];
        $uploadedFiles = $request->getUploadedFiles();
        if (empty($uploadedFiles['foto'])) {
            $_SESSION[self::FLASH] = ['tipo' => 'error', 'mensaje' => 'Selecciona una imagen para subir.'];
            return $response->withStatus(302)->withHeader('Location', '/instructor/perfil');
        }
        $file = $uploadedFiles['foto'];
        if ($file->getError() !== UPLOAD_ERR_OK) {
            $_SESSION[self::FLASH] = ['tipo' => 'error', 'mensaje' => 'Error al subir la imagen.'];
            return $response->withStatus(302)->withHeader('Location', '/instructor/perfil');
        }
        $size = $file->getSize();
        if ($size > 2 * 1024 * 1024) {
            $_SESSION[self::FLASH] = ['tipo' => 'error', 'mensaje' => 'La imagen no debe superar 2MB.'];
            return $response->withStatus(302)->withHeader('Location', '/instructor/perfil');
        }
        $clientName = $file->getClientFilename();
        $ext = strtolower(pathinfo($clientName ?? '', PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            $_SESSION[self::FLASH] = ['tipo' => 'error', 'mensaje' => 'Formato de imagen no válido.'];
            return $response->withStatus(302)->withHeader('Location', '/instructor/perfil');
        }
        $nombreArchivo = 'perfil_instructor_' . $idInstructor . '_' . time() . '.' . $ext;
        $directorio = dirname(__DIR__, 3) . '/var/uploads/perfiles';
        if (!is_dir($directorio)) {
            @mkdir($directorio, 0755, true);
        }
        $rutaCompleta = $directorio . '/' . $nombreArchivo;
        try {
            $file->moveTo($rutaCompleta);
        } catch (\Throwable $e) {
            $_SESSION[self::FLASH] = ['tipo' => 'error', 'mensaje' => 'No se pudo guardar la imagen.'];
            return $response->withStatus(302)->withHeader('Location', '/instructor/perfil');
        }
        $rutaPublica = '/perfil/foto/' . $nombreArchivo;
        $this->service->actualizarFotoInstructor($idInstructor, $rutaPublica);
        $_SESSION[self::FLASH] = ['tipo' => 'exito', 'mensaje' => 'Foto de perfil actualizada correctamente.'];
        return $response->withStatus(302)->withHeader('Location', '/instructor/perfil');
    }

    private function leerFlash(): ?array
    {
        $flash = $_SESSION[self::FLASH] ?? null;
        unset($_SESSION[self::FLASH]);
        return is_array($flash) ? $flash : null;
    }
}
