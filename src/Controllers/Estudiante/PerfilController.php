<?php

declare(strict_types=1);

namespace App\Controllers\Estudiante;

use App\Application\Auth\AuthService;
use App\Application\Estudiante\EstudiantePortalService;
use App\Application\Perfil\FotoPerfilStorage;
use PDOException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;
use PDO;

/**
 * Pantalla 7 del portal: Mi Perfil (/estudiante/perfil).
 *
 *  GET  /estudiante/perfil          → ver datos y cambiar contraseña
 *  POST /estudiante/perfil          → guardar la ficha personal
 *  POST /estudiante/perfil/password → cambiar la contraseña
 */
class PerfilController
{
    private const FLASH = 'flash_perfil';

    private PhpRenderer $view;
    private EstudiantePortalService $portal;
    private AuthService $auth;

    public function __construct(PhpRenderer $view, EstudiantePortalService $portal, AuthService $auth)
    {
        $this->view = $view;
        $this->portal = $portal;
        $this->auth = $auth;
    }

    /**
     * Muestra la ficha personal y los formularios.
     */
    public function index(Request $request, Response $response): Response
    {
        $usuario = $_SESSION['usuario'] ?? [];


        $perfil = $this->portal->perfil((int) $usuario['id_referencia']);

        if ($perfil === null) {
            return $response->withStatus(302)->withHeader('Location', '/estudiante/dashboard');
        }

        return $this->render($response, [
            'perfil' => $perfil,
            'valores'=> $this->valoresDesdePerfil($perfil),
            'errores'=> [],
            'flash'  => $this->leerFlash(),
        ]);
    }

    /**
     * Guarda la ficha personal (POST del primer formulario).
     */
    public function actualizar(Request $request, Response $response): Response
    {
        $usuario = $_SESSION['usuario'] ?? [];


        $idEstudiante = (int) $usuario['id_referencia'];
        $idUsuario = (int) $usuario['id'];

        $valores = $this->leerValores($request);
        $errores = $this->validarPerfil($valores);

        $perfil = $this->portal->perfil($idEstudiante) ?? [];

        if ($errores === []) {
            try {
                $this->portal->actualizarPerfil($idEstudiante, $idUsuario, $valores);

                // El sidebar muestra el nombre de la sesión: hay que refrescarlo
                $_SESSION['usuario']['nombre'] = $valores['nombre'] . ' ' . $valores['apellido'];

                return $this->conExito($response, 'Tus datos se han guardado correctamente.');
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    // Correo duplicado en estudiante.email o usuario.email_usuario
                    $errores['email'] = 'Ya existe una cuenta con ese correo electrónico.';
                } else {
                    throw $e;
                }
            }
        }

        return $this->render($response->withStatus(422), [
            'perfil' => $perfil,
            'valores'=> $valores,
            'errores'=> $errores,
            'flash'  => null,
        ]);
    }

    /**
     * Cambia la contraseña (POST del segundo formulario).
     */
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
                return $this->conExito($response, 'Contraseña actualizada. Usa la nueva en tu próximo acceso.');
            }

            $erroresPassword['password_actual'] = 'La contraseña actual no es correcta.';
        }

        $idEstudiante = (int) $usuario['id_referencia'];
        $perfil = $this->portal->perfil($idEstudiante) ?? [];

        return $this->render($response->withStatus(422), [
            'perfil' => $perfil,
            // Los campos de contraseña nunca se rellenan en el reenvío
            'valores'=> array_merge($this->valoresDesdePerfil($perfil), [
                'password_actual'    => '',
                'password_nueva'     => '',
                'password_confirmar' => '',
            ]),
            'errores'=> $erroresPassword,
            'flash'  => null,
        ]);
    }

    // ------------------------------------------------------------------
    //  Auxiliares
    // ------------------------------------------------------------------

    /**
     * @param array{perfil: array<string, mixed>, valores: array<string, string>, errores: array<string, string>, flash: ?array<string, string>} $vars
     */
    private function render(Response $response, array $vars): Response
    {
        $this->view->setLayout('estudiante/layout.php');

        return $this->view->render($response, 'estudiante/perfil.php', [
            'tituloPagina' => 'Mi Perfil — Portal del Estudiante',
            'paginaPortal' => 'perfil',
            'perfil'  => $vars['perfil'],
            'valores' => $vars['valores'],
            'errores' => $vars['errores'],
            'flash'   => $vars['flash'],
        ]);
    }

    /**
     * Guarda el mensaje en sesión y redirige (patrón POST-Redirect-GET),
     * para que recargar la página no repita la acción.
     */
    private function conExito(Response $response, string $mensaje): Response
    {
        $_SESSION[self::FLASH] = ['tipo' => 'exito', 'mensaje' => $mensaje];

        return $response->withStatus(302)->withHeader('Location', '/estudiante/perfil');
    }

    /**
     * @return array<string, string>|null
     */
    private function leerFlash(): ?array
    {
        $flash = $_SESSION[self::FLASH] ?? null;
        unset($_SESSION[self::FLASH]);

        return is_array($flash) ? $flash : null;
    }

    /**
     * @return array<string, string>
     */
    private function leerValores(Request $request): array
    {
        $datos = (array) $request->getParsedBody();
        $limpiar = static fn (string $clave): string => trim((string) ($datos[$clave] ?? ''));

        return [
            'nombre'    => $limpiar('nombre'),
            'apellido'  => $limpiar('apellido'),
            'email'     => $limpiar('email'),
            'telefono'  => $limpiar('telefono'),
            'direccion' => $limpiar('direccion'),
            'fecha_nacimiento' => $limpiar('fecha_nacimiento'),
        ];
    }

    /**
     * @param array<string, mixed> $perfil
     * @return array<string, string>
     */
    private function valoresDesdePerfil(array $perfil): array
    {
        return [
            'nombre'    => (string) ($perfil['nombre'] ?? ''),
            'apellido'  => (string) ($perfil['apellido'] ?? ''),
            'email'     => (string) ($perfil['email'] ?? ''),
            'telefono'  => (string) ($perfil['telefono'] ?? ''),
            'direccion' => (string) ($perfil['direccion'] ?? ''),
            'fecha_nacimiento' => (string) ($perfil['fecha_nacimiento'] ?? ''),
        ];
    }

    /**
     * @param array<string, string> $valores
     * @return array<string, string> mensaje de error por campo (vacío si todo va bien)
     */
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

        if (mb_strlen($valores['direccion']) > 255) {
            $errores['direccion'] = 'Máximo 255 caracteres.';
        }

        if (
            ($valores['fecha_nacimiento'] ?? '') !== ''
            && (!\App\Application\Admin\ListadoAdmin::fecha($valores['fecha_nacimiento']) || $valores['fecha_nacimiento'] > date('Y-m-d'))
        ) {
            $errores['fecha_nacimiento'] = 'Indica una fecha de nacimiento válida, no futura.';
        }

        return $errores;
    }


    public function subirFoto(Request $request, Response $response): Response
    {
        $usuario = $_SESSION['usuario'] ?? [];
        $idEstudiante = (int) $usuario['id_referencia'];
        $uploadedFiles = $request->getUploadedFiles();
        if (empty($uploadedFiles['foto'])) {
            $_SESSION[self::FLASH] = ['tipo' => 'error', 'mensaje' => 'Selecciona una imagen para subir.'];
            return $response->withStatus(302)->withHeader('Location', '/estudiante/perfil');
        }
        $file = $uploadedFiles['foto'];
        if ($file->getError() !== UPLOAD_ERR_OK) {
            $_SESSION[self::FLASH] = ['tipo' => 'error', 'mensaje' => 'Error al subir la imagen.'];
            return $response->withStatus(302)->withHeader('Location', '/estudiante/perfil');
        }
        $size = $file->getSize();
        if ($size > 2 * 1024 * 1024) {
            $_SESSION[self::FLASH] = ['tipo' => 'error', 'mensaje' => 'La imagen no debe superar 2MB.'];
            return $response->withStatus(302)->withHeader('Location', '/estudiante/perfil');
        }
        $clientName = $file->getClientFilename();
        $ext = strtolower(pathinfo($clientName ?? '', PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            $_SESSION[self::FLASH] = ['tipo' => 'error', 'mensaje' => 'Formato de imagen no válido.'];
            return $response->withStatus(302)->withHeader('Location', '/estudiante/perfil');
        }
        $nombreArchivo = 'perfil_' . $idEstudiante . '_' . time() . '.' . $ext;
        $directorio = dirname(__DIR__, 3) . '/var/uploads/perfiles';
        if (!is_dir($directorio)) {
            @mkdir($directorio, 0755, true);
        }
        $rutaCompleta = $directorio . '/' . $nombreArchivo;
        try {
            $file->moveTo($rutaCompleta);
        } catch (\Throwable $e) {
            $_SESSION[self::FLASH] = ['tipo' => 'error', 'mensaje' => 'No se pudo guardar la imagen.'];
            return $response->withStatus(302)->withHeader('Location', '/estudiante/perfil');
        }
        // Guardar ruta relativa desde public o directa; servir desde var no es público directo
        $rutaPublica = '/perfil/foto/' . $nombreArchivo;
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare('SELECT ruta FROM estudiante_foto WHERE id_estudiante = :id');
        $stmt->execute(['id' => $idEstudiante]);
        $fotoAnterior = (string) ($stmt->fetchColumn() ?: '');
        $stmt = $pdo->prepare('REPLACE INTO estudiante_foto (id_estudiante, ruta) VALUES (:id, :ruta)');
        $stmt->execute(['id' => $idEstudiante, 'ruta' => $rutaPublica]);
        // La nueva ya está en la BD: se retira la anterior del disco
        if ($fotoAnterior !== $rutaPublica) {
            FotoPerfilStorage::borrarAnterior($fotoAnterior !== '' ? $fotoAnterior : null, FotoPerfilStorage::prefijoEstudiante($idEstudiante));
        }
        $_SESSION[self::FLASH] = ['tipo' => 'exito', 'mensaje' => 'Foto de perfil actualizada correctamente.'];
        return $response->withStatus(302)->withHeader('Location', '/estudiante/perfil');
    }

    public function guardarPreferencias(Request $request, Response $response): Response
    {
        $usuario = $_SESSION['usuario'] ?? [];
        $idEstudiante = (int) $usuario['id_referencia'];
        $datos = (array) $request->getParsedBody();
        $generos = trim((string) ($datos['generos_favoritos'] ?? ''));
        $objetivo = trim((string) ($datos['objetivo_academia'] ?? ''));
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare('REPLACE INTO estudiante_preferencias (id_estudiante, generos_favoritos, objetivo_academia) VALUES (:id, :g, :o)');
        $stmt->execute(['id' => $idEstudiante, 'g' => $generos !== '' ? $generos : null, 'o' => $objetivo !== '' ? $objetivo : null]);
        $_SESSION[self::FLASH] = ['tipo' => 'exito', 'mensaje' => 'Preferencias musicales guardadas correctamente.'];
        return $response->withStatus(302)->withHeader('Location', '/estudiante/perfil');
    }

    private function getPdo()
    {
        $c = require dirname(__DIR__, 3) . '/config/db.php';
        $pdo = new \PDO(sprintf('mysql:host=%s;dbname=%s;charset=%s', $c['host'], $c['dbname'], $c['charset']), $c['user'], $c['pass'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]);
        return $pdo;
    }

}
