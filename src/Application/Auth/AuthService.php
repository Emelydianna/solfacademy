<?php

declare(strict_types=1);

namespace App\Application\Auth;

use PDO;

/**
 * Autenticación de usuarios contra la tabla `usuario` (con `password_hash`).
 * La sesión es PHP nativa ($_SESSION), iniciada por StartSessionMiddleware.
 */
final class AuthService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Valida credenciales y, si son correctas, crea la sesión.
     * Devuelve el usuario normalizado o null si no coincide.
     *
     * @return array<string, mixed>|null
     */
    public function intentarLogin(string $email, string $password): ?array
    {
        $sql = 'SELECT u.id_usuario, u.email_usuario, u.password_hash, u.rol, u.activo,
                       u.id_estudiante, u.id_instructor, u.version_sesion, e.estado AS estudiante_estado,
                       e.nombre AS estudiante_nombre, e.apellido AS estudiante_apellido,
                       i.nombre AS instructor_nombre, i.apellido AS instructor_apellido, i.activo AS instructor_activo
                FROM usuario u
                LEFT JOIN estudiante e ON e.id_estudiante = u.id_estudiante
                LEFT JOIN instructor i ON i.id_instructor = u.id_instructor
                WHERE u.email_usuario = :email
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['email' => $email]);
        $usuario = $stmt->fetch();

        if (
            $usuario === false
            || !(int) $usuario['activo']
            || ($usuario['rol'] === 'estudiante' && $usuario['estudiante_estado'] !== 'Activo')
            || ($usuario['rol'] === 'instructor' && (int) $usuario['instructor_activo'] !== 1)
            || !password_verify($password, $usuario['password_hash'])
        ) {
            return null;
        }

        return $this->iniciarSesion($usuario);
    }

    /**
     * Crea un estudiante + su usuario y abre la sesión.
     *
     * @return array<string, mixed>
     */
    public function registrar(string $nombre, string $apellido, string $email, string $password, ?string $fechaNacimiento = null): array
    {
        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO estudiante (nombre, apellido, email, fecha_nacimiento, fecha_inscripcion)
                 VALUES (:nombre, :apellido, :email, :fecha_nacimiento, CURDATE())'
            );
            $stmt->execute([
                'nombre' => $nombre,
                'apellido' => $apellido,
                'email' => $email,
                'fecha_nacimiento' => ($fechaNacimiento ?? '') !== '' ? $fechaNacimiento : null,
            ]);
            $idEstudiante = (int) $this->pdo->lastInsertId();

            $stmt = $this->pdo->prepare(
                "INSERT INTO usuario (id_estudiante, email_usuario, password_hash, rol, activo)
                 VALUES (:id_estudiante, :email, :hash, 'estudiante', 1)"
            );
            $stmt->execute([
                'id_estudiante' => $idEstudiante,
                'email' => $email,
                'hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
            $idUsuario = (int) $this->pdo->lastInsertId();

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return $this->iniciarSesion([
            'id_usuario' => $idUsuario,
            'email_usuario' => $email,
            'rol' => 'estudiante',
            'activo' => 1,
            'id_estudiante' => $idEstudiante,
            'id_instructor' => null,
            'estudiante_nombre' => $nombre,
            'estudiante_apellido' => $apellido,
            'instructor_nombre' => null,
            'instructor_apellido' => null,
        ]);
    }

    /**
     * Guarda el usuario en la sesión y devuelve su forma normalizada.
     *
     * @param array<string, mixed> $fila
     * @return array<string, mixed>
     */
    public function iniciarSesion(array $fila): array
    {
        session_regenerate_id(true); // evita fijación de sesión

        $usuario = $this->normalizar($fila);
        $_SESSION['usuario'] = $usuario;

        return $usuario;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function usuarioActual(): ?array
    {
        return $_SESSION['usuario'] ?? null;
    }

    public function cerrarSesion(): void
    {
        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /**
     * Cambia la contraseña del usuario tras verificar la actual.
     * Devuelve false si la contraseña actual no coincide.
     */
    public function cambiarPassword(int $idUsuario, string $passwordActual, string $passwordNueva): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT password_hash FROM usuario WHERE id_usuario = :id LIMIT 1'
        );
        $stmt->execute(['id' => $idUsuario]);
        $hash = $stmt->fetchColumn();

        if ($hash === false || !password_verify($passwordActual, (string) $hash)) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE usuario SET password_hash = :hash WHERE id_usuario = :id'
        );
        $stmt->execute([
            'hash' => password_hash($passwordNueva, PASSWORD_DEFAULT),
            'id'   => $idUsuario,
        ]);

        // Regenera el identificador de sesión al rotar credenciales
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        return true;
    }

    /**
     * @param array<string, mixed> $fila
     * @return array<string, mixed>
     */
    private function normalizar(array $fila): array
    {
        $rol = (string) $fila['rol'];

        if ($rol === 'instructor') {
            $nombre = trim(($fila['instructor_nombre'] ?? '') . ' ' . ($fila['instructor_apellido'] ?? ''));
            $idReferencia = (int) ($fila['id_instructor'] ?? 0);
        } else {
            $nombre = trim(($fila['estudiante_nombre'] ?? '') . ' ' . ($fila['estudiante_apellido'] ?? ''));
            $idReferencia = (int) ($fila['id_estudiante'] ?? 0);
        }

        // El rol admin no tiene fila propia (ni estudiante ni instructor):
        // sin este nombre, el sidebar y el saludo salían vacíos.
        if ($rol === 'admin' && $nombre === '') {
            $nombre = 'Administrador';
        }

        return [
            'id' => (int) $fila['id_usuario'],
            'nombre' => $nombre,
            'email' => (string) $fila['email_usuario'],
            'rol' => $rol,
            'id_referencia' => $idReferencia,
            'version_sesion' => (int) ($fila['version_sesion'] ?? 0),
        ];
    }
}
