<?php

declare(strict_types=1);

namespace App\Application\Admin;

use App\Application\Formato\Fecha;
use PDO;
use PDOException;

/**
 * Cuentas de acceso del sistema (tabla `usuario`).
 *
 * Módulo de solo consulta + acciones de seguridad: el email y los datos
 * personales se siguen editando desde Estudiantes/Instructores, donde ya
 * se sincronizan en transacción. Las cuentas nunca se borran: se bloquean.
 */
final class UsuariosService
{
    /** Roles admitidos por usuario.rol (el ENUM de la base de datos). */
    public const ROLES = ['admin', 'instructor', 'estudiante'];

    public const ESTADOS = ['activo', 'bloqueado'];

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Listado paginado (10 por página) con búsqueda, filtro de rol (las
     * píldoras) y de estado. Los más recientes primero.
     *
     * @return array{filas: array<int, array<string, mixed>>, total: int, pagina: int, paginas: int, desde: int, hasta: int, filtros: array{q: string, rol: string, estado: string}}
     */
    public function listar(array $f): array
    {
        $q = mb_substr(GestionAcademicaService::texto($f, 'q'), 0, 150);
        $rol = GestionAcademicaService::texto($f, 'rol');
        $estado = GestionAcademicaService::texto($f, 'estado');

        $where = [];
        $params = [];
        if ($q !== '') {
            $where[] = "(u.email_usuario LIKE :email
                         OR COALESCE(CONCAT(e.nombre, ' ', e.apellido), CONCAT(i.nombre, ' ', i.apellido)) LIKE :nombre
                         OR CAST(u.id_usuario AS CHAR) = :id_q)";
            $params['email'] = '%' . $q . '%';
            $params['nombre'] = '%' . $q . '%';
            $params['id_q'] = ltrim($q, '#');
        }
        if (in_array($rol, self::ROLES, true)) {
            $where[] = 'u.rol = :rol';
            $params['rol'] = $rol;
        } else {
            $rol = '';
        }
        if (in_array($estado, self::ESTADOS, true)) {
            $where[] = 'u.activo = :activo';
            $params['activo'] = $estado === 'activo' ? 1 : 0;
        } else {
            $estado = '';
        }

        $filas = ListadoAdmin::pagina(
            $this->pdo,
            self::SELECT_LISTA,
            'usuario u
             LEFT JOIN estudiante e ON e.id_estudiante = u.id_estudiante
             LEFT JOIN instructor i ON i.id_instructor = u.id_instructor',
            $where ? ' WHERE ' . implode(' AND ', $where) : '',
            $params,
            'u.ultimo_acceso DESC, FIELD(u.rol, \'admin\', \'instructor\', \'estudiante\'), u.id_usuario',
            $f
        );

        foreach ($filas['filas'] as &$fila) {
            $this->presentar($fila);
        }
        unset($fila);

        return $filas + ['filtros' => ['q' => $q, 'rol' => $rol, 'estado' => $estado]];
    }

    /**
     * Ficha de una cuenta: datos, persona vinculada y estado de sesión.
     *
     * @return array<string, mixed>|null
     */
    public function detalle(int $id, int $idSesion): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.id_usuario, u.email_usuario, u.rol, u.activo, u.ultimo_acceso, u.version_sesion,
                    u.id_estudiante, u.id_instructor,
                    COALESCE(CONCAT(e.nombre, \' \', e.apellido), CONCAT(i.nombre, \' \', i.apellido)) AS nombre,
                    e.email AS estudiante_email, e.estado AS estudiante_estado, e.fecha_inscripcion,
                    i.email AS instructor_email, i.activo AS instructor_activo, i.anios_experiencia,
                    (SELECT f.ruta FROM estudiante_foto f WHERE f.id_estudiante = u.id_estudiante) AS foto
             FROM usuario u
             LEFT JOIN estudiante e ON e.id_estudiante = u.id_estudiante
             LEFT JOIN instructor i ON i.id_instructor = u.id_instructor
             WHERE u.id_usuario = :id'
        );
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch();
        if (!$fila) {
            return null;
        }

        $this->presentar($fila);

        $fila['puede_bloquearse'] = $this->puedeBloquear($id, $idSesion);
        $fila['motivo_bloqueo'] = $fila['puede_bloquearse']
            ? $this->motivoBloqueo($id)
            : 'No puedes bloquear tu propia cuenta de administrador.';

        if ($fila['rol'] === 'estudiante' && $fila['estudiante_estado'] !== null) {
            $fila['nota_estado'] = $fila['estudiante_estado'] === 'Activo'
                ? null
                : 'Estudiante suspendido: reactívalo desde su ficha antes de usar la cuenta.';
        } elseif ($fila['rol'] === 'instructor' && $fila['instructor_activo'] !== null) {
            $fila['nota_estado'] = (int) $fila['instructor_activo'] === 1
                ? null
                : 'Instructor inactivo: reactívalo desde su ficha antes de usar la cuenta.';
        }

        return $fila;
    }

    /**
     * Bloquea o desbloquea una cuenta. Bloquear invalida además todas las
     * sesiones abiertas (version_sesion), como en Estudiantes/Instructores.
     *
     * @return array{tipo: string, mensaje: string}
     */
    public function bloquear(int $id, bool $bloquear, int $idAdmin): array
    {
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare('SELECT id_usuario, activo FROM usuario WHERE id_usuario = :id FOR UPDATE');
            $stmt->execute(['id' => $id]);
            $cuenta = $stmt->fetch();

            if (!$cuenta) {
                $this->pdo->rollBack();
                return ['tipo' => 'error', 'mensaje' => 'La cuenta ya no existe.'];
            }
            if ($id === $idAdmin) {
                $this->pdo->rollBack();
                return ['tipo' => 'error', 'mensaje' => 'No puedes bloquear tu propia cuenta de administrador.'];
            }
            if ((int) $cuenta['activo'] === ($bloquear ? 0 : 1)) {
                $this->pdo->rollBack();
                return [
                    'tipo'    => 'exito',
                    'mensaje' => $bloquear ? 'La cuenta ya estaba bloqueada.' : 'La cuenta ya estaba activa.',
                ];
            }

            if (!$bloquear) {
                $motivo = $this->motivoBloqueo($id);
                if ($motivo !== null) {
                    $this->pdo->rollBack();
                    return ['tipo' => 'error', 'mensaje' => $motivo];
                }
            }

            // version_sesion cierra en la siguiente petición cualquier
            // sesión abierta con la cuenta (CuentaService lo comprueba).
            $this->pdo->prepare('UPDATE usuario SET activo = :activo, version_sesion = version_sesion + 1 WHERE id_usuario = :id')
                ->execute(['activo' => $bloquear ? 0 : 1, 'id' => $id]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($e instanceof PDOException && (string) $e->getCode() === '40001') {
                return ['tipo' => 'error', 'mensaje' => 'Hubo un cambio simultáneo. Recarga e inténtalo de nuevo.'];
            }
            throw $e;
        }

        return [
            'tipo'    => 'exito',
            'mensaje' => $bloquear
                ? 'Cuenta bloqueada. La persona quedó sin acceso y sus sesiones abiertas se cerraron.'
                : 'Cuenta reactivada. Ya puede iniciar sesión con sus credenciales.',
        ];
    }

    /**
     * Personas sin cuenta para el formulario de alta (select del rol elegido).
     * Solo activas: a los suspendidos/inactivos no se les crea acceso aquí.
     *
     * @return array<int, array<string, mixed>>
     */
    public function candidatos(string $rol): array
    {
        if ($rol === 'estudiante') {
            $sql = "SELECT e.id_estudiante AS id, CONCAT(e.nombre, ' ', e.apellido) AS nombre, e.email
                    FROM estudiante e
                    WHERE e.estado = 'Activo'
                      AND NOT EXISTS (SELECT 1 FROM usuario u WHERE u.id_estudiante = e.id_estudiante)
                    ORDER BY e.nombre, e.apellido";
        } elseif ($rol === 'instructor') {
            $sql = "SELECT i.id_instructor AS id, CONCAT(i.nombre, ' ', i.apellido) AS nombre, i.email
                    FROM instructor i
                    WHERE i.activo = 1
                      AND NOT EXISTS (SELECT 1 FROM usuario u WHERE u.id_instructor = i.id_instructor)
                    ORDER BY i.nombre, i.apellido";
        } else {
            return [];
        }

        return $this->pdo->query($sql)->fetchAll();
    }

    /**
     * Crea una cuenta de acceso. La contraseña solo se guarda como hash.
     *
     * @param array<string, mixed> $datos
     * @return array{valores: array<string, string>, errores: array<string, string>, id: int}
     */
    public function crear(array $datos): array
    {
        $v = [];
        foreach (['rol', 'id_persona', 'email', 'password', 'password_confirmar'] as $campo) {
            $v[$campo] = GestionAcademicaService::texto($datos, $campo);
        }

        $errores = [];
        if (!in_array($v['rol'], self::ROLES, true)) {
            $errores['rol'] = 'Selecciona un rol válido.';
        }
        if (strlen($v['password']) < 8 || strlen($v['password']) > 72 || str_contains($v['password'], "\0")) {
            $errores['password'] = 'La contraseña debe tener entre 8 y 72 bytes y no contener caracteres nulos.';
        }
        if ($v['password_confirmar'] !== $v['password']) {
            $errores['password_confirmar'] = 'Las contraseñas no coinciden.';
        }
        if ($v['email'] !== '' && (mb_strlen($v['email']) > 150 || !filter_var($v['email'], FILTER_VALIDATE_EMAIL))) {
            $errores['email'] = 'Indica un correo válido de hasta 150 caracteres.';
        }

        $idPersona = ctype_digit($v['id_persona']) ? (int) $v['id_persona'] : 0;
        $rol = $v['rol'];
        if ($rol !== 'admin') {
            if ($idPersona < 1) {
                $errores['id_persona'] = 'Selecciona a la persona que recibirá la cuenta.';
            }
        } else {
            $idPersona = 0;
            if ($v['email'] === '') {
                $errores['email'] = 'Indica el correo de la cuenta administrativa.';
            }
        }

        $resultado = ['valores' => $v, 'errores' => $errores, 'id' => 0];
        if ($errores) {
            return $resultado;
        }

        $persona = null;
        try {
            $this->pdo->beginTransaction();

            if ($rol !== 'admin') {
                $esEstudiante = $rol === 'estudiante';
                $tabla = $esEstudiante ? 'estudiante' : 'instructor';
                $columna = $esEstudiante ? 'id_estudiante' : 'id_instructor';

                $stmt = $this->pdo->prepare(
                    "SELECT {$columna} AS id, nombre, apellido, email"
                    . ($esEstudiante ? ', estado' : ', activo')
                    . " FROM {$tabla} WHERE {$columna} = :id FOR UPDATE"
                );
                $stmt->execute(['id' => $idPersona]);
                $persona = $stmt->fetch();

                if (!$persona) {
                    $this->pdo->rollBack();
                    $resultado['errores'] = ['id_persona' => 'La persona seleccionada ya no existe.'];
                    return $resultado;
                }
                if ($esEstudiante && $persona['estado'] !== 'Activo') {
                    $this->pdo->rollBack();
                    $resultado['errores'] = ['id_persona' => 'Los estudiantes suspendidos no pueden recibir una cuenta nueva.'];
                    return $resultado;
                }
                if (!$esEstudiante && (int) $persona['activo'] !== 1) {
                    $this->pdo->rollBack();
                    $resultado['errores'] = ['id_persona' => 'Los instructores inactivos no pueden recibir una cuenta nueva.'];
                    return $resultado;
                }

                $stmt = $this->pdo->prepare(
                    "SELECT COUNT(*) FROM usuario WHERE rol = :rol AND {$columna} = :persona"
                );
                $stmt->execute(['rol' => $rol, 'persona' => $idPersona]);
                if ((int) $stmt->fetchColumn() > 0) {
                    $this->pdo->rollBack();
                    $resultado['errores'] = ['id_persona' => 'Esa persona ya tiene una cuenta de acceso.'];
                    return $resultado;
                }

                // El correo de la persona manda: así no se desincroniza con
                // las fichas de Estudiantes/Instructores.
                $emailPersona = trim((string) ($persona['email'] ?? ''));
                if ($emailPersona !== '') {
                    $v['email'] = $emailPersona;
                } elseif ($v['email'] === '') {
                    $this->pdo->rollBack();
                    $resultado['errores'] = ['email' => 'Esta persona no tiene correo: indícalo para crear la cuenta.'];
                    return $resultado;
                }
            }

            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM usuario WHERE email_usuario = :email');
            $stmt->execute(['email' => $v['email']]);
            if ((int) $stmt->fetchColumn() > 0) {
                $this->pdo->rollBack();
                $resultado['errores'] = ['email' => 'Ya existe una cuenta con ese correo.'];
                return $resultado;
            }

            $this->pdo->prepare(
                'INSERT INTO usuario (id_estudiante, id_instructor, email_usuario, password_hash, rol, activo)
                 VALUES (:estudiante, :instructor, :email, :hash, :rol, 1)'
            )->execute([
                'estudiante' => $rol === 'estudiante' ? $idPersona : null,
                'instructor' => $rol === 'instructor' ? $idPersona : null,
                'email'      => $v['email'],
                'hash'       => password_hash($v['password'], PASSWORD_DEFAULT),
                'rol'        => $rol,
            ]);
            $id = (int) $this->pdo->lastInsertId();

            // Un instructor sin correo en su ficha: se completa aquí.
            if ($rol === 'instructor' && trim((string) ($persona['email'] ?? '')) === '') {
                $this->pdo->prepare('UPDATE instructor SET email = :email WHERE id_instructor = :id')
                    ->execute(['email' => $v['email'], 'id' => $idPersona]);
            }

            $this->pdo->commit();
            $resultado['id'] = $id;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($e instanceof PDOException && (string) $e->getCode() === '23000') {
                $resultado['errores'] = ['email' => 'Ya existe una cuenta con ese correo.'];
            } elseif ($e instanceof PDOException && (string) $e->getCode() === '40001') {
                $resultado['errores'] = ['general' => 'Hubo un cambio simultáneo. Recarga e inténtalo de nuevo.'];
            } else {
                throw $e;
            }
        }

        return $resultado;
    }

    // =========================================================
    // Consultas y presentación
    // =========================================================

    private const SELECT_LISTA = "u.id_usuario, u.email_usuario, u.rol, u.activo, u.ultimo_acceso, u.version_sesion,
            u.id_estudiante, u.id_instructor,
            COALESCE(CONCAT(e.nombre, ' ', e.apellido), CONCAT(i.nombre, ' ', i.apellido), 'Administrador') AS nombre,
            e.estado AS estudiante_estado, i.activo AS instructor_activo,
            (SELECT f.ruta FROM estudiante_foto f WHERE f.id_estudiante = u.id_estudiante) AS foto_estudiante,
            i.foto AS foto_instructor";

    /** Avatar, iniciales, acceso relativo y enlace de ficha de una fila. */
    private function presentar(array &$fila): void
    {
        $fila['nombre'] = (string) ($fila['nombre'] ?: 'Administrador');
        $fila['rol'] = (string) $fila['rol'];
        $fila['activo'] = (int) $fila['activo'];

        $foto = null;
        foreach (['foto', 'foto_estudiante', 'foto_instructor'] as $clave) {
            if (!empty($fila[$clave]) && is_string($fila[$clave])) {
                $foto = $fila[$clave];
                break;
            }
        }
        $fila['avatar'] = $foto !== null && !str_contains($foto, '..') ? $foto : null;

        $palabras = preg_split('/\s+/', trim($fila['nombre'])) ?: [$fila['nombre']];
        $iniciales = mb_strtoupper(mb_substr($palabras[0] ?? 'A', 0, 1));
        if (count($palabras) > 1) {
            $iniciales .= mb_strtoupper(mb_substr(end($palabras), 0, 1));
        }
        $fila['iniciales'] = $iniciales;

        $fila['ultimo_acceso_relativo'] = Fecha::relativo(
            isset($fila['ultimo_acceso']) ? (string) $fila['ultimo_acceso'] : null
        );
        $fila['estado_texto'] = $fila['activo'] === 1 ? 'Activo' : 'Bloqueado';

        $fila['persona_ficha'] = $fila['id_estudiante'] !== null
            ? '/admin/estudiantes/' . (int) $fila['id_estudiante']
            : ($fila['id_instructor'] !== null ? '/admin/instructores/' . (int) $fila['id_instructor'] : null);
    }

    /** ¿Se permite bloquear/desbloquear esta cuenta desde la sesión dada? */
    private function puedeBloquear(int $id, int $idSesion): bool
    {
        return $id > 0 && $id !== $idSesion;
    }

    /**
     * Motivo por el que NO se puede reactivar la cuenta, o null si sí.
     * Una cuenta bloqueada por suspensión/inactividad se reactiva desde la
     * ficha de la persona, no desde aquí.
     */
    private function motivoBloqueo(int $id): ?string
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.rol, u.id_estudiante, u.id_instructor, e.estado AS estudiante_estado, i.activo AS instructor_activo
             FROM usuario u
             LEFT JOIN estudiante e ON e.id_estudiante = u.id_estudiante
             LEFT JOIN instructor i ON i.id_instructor = u.id_instructor
             WHERE u.id_usuario = :id'
        );
        $stmt->execute(['id' => $id]);
        $cuenta = $stmt->fetch();
        if (!$cuenta) {
            return 'La cuenta ya no existe.';
        }
        if ($cuenta['rol'] === 'estudiante' && $cuenta['estudiante_estado'] !== null && $cuenta['estudiante_estado'] !== 'Activo') {
            return 'El estudiante está suspendido: reactívalo desde su ficha antes de habilitar la cuenta.';
        }
        if ($cuenta['rol'] === 'instructor' && $cuenta['instructor_activo'] !== null && (int) $cuenta['instructor_activo'] !== 1) {
            return 'El instructor está inactivo: reactívalo desde su ficha antes de habilitar la cuenta.';
        }

        return null;
    }
}
