<?php

declare(strict_types=1);

namespace App\Application\Admin;

use App\Application\Instructor\InstructorContactoService;
use App\Application\Perfil\FotoInstructorStorage;
use DomainException;
use PDO;
use PDOException;
use Psr\Http\Message\UploadedFileInterface;

final class InstructoresService
{
    public const NIVELES = ['principiante', 'intermedio', 'avanzado'];
    private PDO $pdo;
    private InstructorContactoService $contacto;
    private FotoInstructorStorage $fotos;

    public function __construct(PDO $pdo, ?FotoInstructorStorage $fotos = null)
    {
        $this->pdo = $pdo;
        $this->contacto = new InstructorContactoService($pdo);
        $this->fotos = $fotos ?? new FotoInstructorStorage();
    }

    public function instrumentos(): array
    {
        return $this->pdo->query('SELECT id_instrumento, nombre FROM instrumento ORDER BY nombre')->fetchAll();
    }

    public function especialidades(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT ii.id_instrumento, ins.nombre, ii.nivel_dominio, ii.anios_experiencia_i
            FROM instructor_instrumento ii JOIN instrumento ins ON ins.id_instrumento = ii.id_instrumento
            WHERE ii.id_instructor = :id ORDER BY ins.nombre');
        $stmt->execute(['id' => $id]);
        return $stmt->fetchAll();
    }

    public function listar(array $f): array
    {
        $filtros = ['q' => mb_substr(GestionAcademicaService::texto($f, 'q'), 0, 150),
            'estado' => GestionAcademicaService::texto($f, 'estado'), 'instrumento' => GestionAcademicaService::texto($f, 'instrumento')];
        $where = [];
        $params = [];
        if ($filtros['q'] !== '') {
            $where[] = "(CONCAT(i.nombre, ' ', i.apellido) LIKE :nombre OR i.email LIKE :email OR i.telefono LIKE :telefono
                OR especialidades.coincide_q = 1)";
            foreach (['nombre', 'email', 'telefono'] as $campo) {
                $params[$campo] = '%' . $filtros['q'] . '%';
            }
        }
        if (in_array($filtros['estado'], ['0', '1'], true)) {
            $where[] = 'i.activo = :estado';
            $params['estado'] = (int) $filtros['estado'];
        } else {
            $filtros['estado'] = '';
        }
        if (ctype_digit($filtros['instrumento']) && (int) $filtros['instrumento'] > 0) {
            $where[] = 'especialidades.coincide_instrumento = 1';
        } else {
            $filtros['instrumento'] = '';
        }
        // El listado no une grupos ni especialidades en filas individuales:
        // cada instructor ocupa exactamente una fila en la paginación.
        $params['especialidad'] = '%' . $filtros['q'] . '%';
        $params['instrumento'] = (int) $filtros['instrumento'];
        $from = 'instructor i LEFT JOIN (SELECT ii.id_instructor, MAX(ins.nombre LIKE :especialidad) AS coincide_q,
            MAX(ii.id_instrumento = :instrumento) AS coincide_instrumento FROM instructor_instrumento ii
            JOIN instrumento ins ON ins.id_instrumento = ii.id_instrumento GROUP BY ii.id_instructor) especialidades
            ON especialidades.id_instructor = i.id_instructor';
        $lista = ListadoAdmin::pagina($this->pdo, 'i.*', $from,
            $where ? ' WHERE ' . implode(' AND ', $where) : '', $params, 'i.apellido, i.nombre, i.id_instructor', $f);
        foreach ($lista['filas'] as &$fila) {
            $fila['especialidades'] = $this->especialidades((int) $fila['id_instructor']);
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM grupo WHERE id_instructor = :id AND estado IN ('activo','planeado')");
            $stmt->execute(['id' => $fila['id_instructor']]);
            $fila['grupos_count'] = (int) $stmt->fetchColumn();
        }
        unset($fila);
        return $lista + ['filtros' => $filtros];
    }

    public function buscar(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM instructor WHERE id_instructor = :id');
        $stmt->execute(['id' => $id]);
        $instructor = $stmt->fetch();
        if (!$instructor) {
            return null;
        }
        $instructor['especialidades'] = $this->especialidades($id);
        $stmt = $this->pdo->prepare("SELECT id_usuario, email_usuario, activo FROM usuario WHERE id_instructor = :id AND rol = 'instructor' ORDER BY id_usuario");
        $stmt->execute(['id' => $id]);
        $instructor['cuentas'] = $stmt->fetchAll();
        $instructor['acceso_activo'] = (string) (int) ($instructor['cuentas'][0]['activo'] ?? 0);
        return $instructor;
    }

    public function grupos(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT g.*, c.nombre AS curso, a.nombre AS aula FROM grupo g
            JOIN curso c ON c.id_curso = g.id_curso JOIN aula a ON a.id_aula = g.id_aula
            WHERE g.id_instructor = :id ORDER BY g.fecha_inicio DESC, g.id_grupo DESC');
        $stmt->execute(['id' => $id]);
        $grupos = $stmt->fetchAll();
        $horarios = new GruposService($this->pdo);
        foreach ($grupos as &$grupo) {
            $grupo['horarios'] = $horarios->horarios((int) $grupo['id_grupo']);
        }
        unset($grupo);
        return $grupos;
    }

    public function extras(int $id): array
    {
        $stmt = $this->pdo->prepare("SELECT id_reserva, id_grupo, fecha, hora_inicio, hora_fin, estado FROM reserva_clase_extra
            WHERE id_instructor = :id AND fecha >= CURDATE() AND estado IN ('Pendiente','Confirmada') ORDER BY fecha, hora_inicio");
        $stmt->execute(['id' => $id]);
        return $stmt->fetchAll();
    }

    public function guardar(array $datos, int $id = 0, ?UploadedFileInterface $foto = null): array
    {
        $v = [];
        $errores = [];
        foreach (['nombre', 'apellido', 'email', 'telefono', 'anios_experiencia', 'crear_cuenta', 'acceso_activo'] as $campo) {
            $v[$campo] = GestionAcademicaService::texto($datos, $campo);
        }
        foreach (['nombre', 'apellido'] as $campo) {
            if ($v[$campo] === '' || mb_strlen($v[$campo]) > 100) {
                $errores[$campo] = 'Campo obligatorio, máximo 100 caracteres.';
            }
        }
        if ($v['email'] !== '' && (mb_strlen($v['email']) > 150 || !filter_var($v['email'], FILTER_VALIDATE_EMAIL))) {
            $errores['email'] = 'Indica un correo válido de hasta 150 caracteres.';
        }
        if ($v['telefono'] !== '' && (mb_strlen($v['telefono']) > 20 || !preg_match('/^[0-9+()\-\s]+$/D', $v['telefono']))) {
            $errores['telefono'] = 'Máximo 20 caracteres: dígitos, espacios y + ( ) -.';
        }
        if (!$this->experienciaValida($v['anios_experiencia'])) {
            $errores['anios_experiencia'] = 'Indica un entero entre 0 y 255.';
        }
        if (!in_array($v['acceso_activo'], ['', '0', '1'], true)) {
            $errores['acceso_activo'] = 'Selecciona un estado de acceso válido.';
        }
        $v['especialidades'] = [];
        $especialidades = $datos['especialidades'] ?? [];
        $ids = [];
        if (!is_array($especialidades) || count($especialidades) < 1 || count($especialidades) > 50) {
            $errores['especialidades'] = 'Agrega entre 1 y 50 instrumentos.';
        } else {
            foreach ($especialidades as $fila) {
                $fila = is_array($fila) ? $fila : [];
                $e = [];
                foreach (['id_instrumento', 'nivel_dominio', 'anios_experiencia_i'] as $campo) {
                    $e[$campo] = GestionAcademicaService::texto($fila, $campo);
                }
                if (!preg_match('/^[1-9][0-9]{0,8}$/D', $e['id_instrumento']) || in_array((int) $e['id_instrumento'], $ids, true)
                    || !in_array($e['nivel_dominio'], self::NIVELES, true) || !$this->experienciaValida($e['anios_experiencia_i'])) {
                    $errores['especialidades'] = 'Selecciona instrumentos sin repetir, un nivel válido y años de experiencia entre 0 y 255.';
                }
                $ids[] = (int) $e['id_instrumento'];
                $v['especialidades'][] = $e;
            }
        }
        $password = isset($datos['password']) && is_string($datos['password']) ? $datos['password'] : '';
        if ($v['crear_cuenta'] === '1') {
            if (strlen($password) < 8 || strlen($password) > 72 || str_contains($password, "\0")) {
                $errores['password'] = 'La contraseña inicial debe tener entre 8 y 72 bytes, sin caracteres nulos.';
            }
            if ($password !== ($datos['password_confirmar'] ?? null)) {
                $errores['password_confirmar'] = 'Las contraseñas no coinciden.';
            }
        }
        $r = ['valores' => $v, 'errores' => $errores, 'id' => $id];
        if ($errores) {
            return $r;
        }
        $fotoNueva = null;
        $fotoAnterior = null;
        try {
            $this->pdo->beginTransaction();
            $actual = null;
            if ($id) {
                $stmt = $this->pdo->prepare('SELECT activo, foto FROM instructor WHERE id_instructor = :id FOR UPDATE');
                $stmt->execute(['id' => $id]);
                $actual = $stmt->fetch();
                if (!$actual) {
                    throw new DomainException('El instructor ya no existe.');
                }
                $fotoAnterior = $actual['foto'];
            }
            $cuentas = $this->contacto->cuentas($id);
            $this->contacto->validarCorreo($v['email'], $id, (bool) $cuentas || $v['crear_cuenta'] === '1');
            if ($v['crear_cuenta'] === '1' && ($cuentas || ($actual && !$actual['activo']))) {
                throw new DomainException('El instructor ya tiene cuenta o está suspendido. No se puede crear otra cuenta.');
            }
            if ($actual && !$actual['activo'] && $v['acceso_activo'] === '1') {
                throw new DomainException('Reactiva al instructor antes de habilitar su acceso.');
            }
            foreach ($ids as $instrumento) {
                $stmt = $this->pdo->prepare('SELECT id_instrumento FROM instrumento WHERE id_instrumento = :id');
                $stmt->execute(['id' => $instrumento]);
                if (!$stmt->fetchColumn()) {
                    throw new DomainException('Uno de los instrumentos seleccionados ya no existe.', 2);
                }
            }
            if ($id) {
                $anteriores = array_map('intval', array_column($this->especialidades($id), 'id_instrumento'));
                $quitadas = array_diff($anteriores, $ids);
                $stmt = $this->pdo->prepare("SELECT DISTINCT c.id_instrumento FROM grupo g JOIN curso c ON c.id_curso = g.id_curso
                    WHERE g.id_instructor = :id AND g.estado IN ('activo','planeado')");
                $stmt->execute(['id' => $id]);
                if (array_intersect($quitadas, array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)))) {
                    throw new DomainException('No puedes quitar un instrumento requerido por sus grupos activos o planeados. Reasígnalos primero.', 2);
                }
            }
            $campos = array_intersect_key($v, array_flip(['nombre', 'apellido', 'email', 'telefono', 'anios_experiencia']));
            $campos['email'] = $campos['email'] ?: null;
            $campos['telefono'] = $campos['telefono'] ?: null;
            if ($id) {
                $sql = 'UPDATE instructor SET ' . implode(', ', array_map(static fn ($c): string => "$c = :$c", array_keys($campos))) . ' WHERE id_instructor = :id';
                $campos['id'] = $id;
            } else {
                $sql = 'INSERT INTO instructor (' . implode(', ', array_keys($campos)) . ') VALUES (:' . implode(', :', array_keys($campos)) . ')';
            }
            $this->pdo->prepare($sql)->execute($campos);
            $id = $id ?: (int) $this->pdo->lastInsertId();
            $this->pdo->prepare('DELETE FROM instructor_instrumento WHERE id_instructor = :id')->execute(['id' => $id]);
            $stmt = $this->pdo->prepare('INSERT INTO instructor_instrumento (id_instructor, id_instrumento, nivel_dominio, anios_experiencia_i) VALUES (:profesor, :id_instrumento, :nivel_dominio, :anios_experiencia_i)');
            foreach ($v['especialidades'] as $e) {
                $stmt->execute(['profesor' => $id] + $e);
            }
            if ($v['crear_cuenta'] === '1') {
                $this->pdo->prepare("INSERT INTO usuario (id_instructor, email_usuario, password_hash, rol, activo) VALUES (:id, :email, :hash, 'instructor', 1)")
                    ->execute(['id' => $id, 'email' => $v['email'], 'hash' => password_hash($password, PASSWORD_DEFAULT)]);
            } elseif ($cuentas) {
                $this->contacto->sincronizarCorreo($id, $v['email']);
                if ($v['acceso_activo'] !== '' && $actual['activo']) {
                    $this->pdo->prepare('UPDATE usuario SET version_sesion = version_sesion + IF(activo <> :anterior, 1, 0), activo = :activo WHERE id_usuario = :id')
                        ->execute(['anterior' => (int) $v['acceso_activo'], 'activo' => (int) $v['acceso_activo'], 'id' => $cuentas[0]['id_usuario']]);
                }
            }
            if ($foto && $foto->getError() !== UPLOAD_ERR_NO_FILE) {
                $fotoNueva = $this->fotos->guardar($foto, $id);
                $this->pdo->prepare('UPDATE instructor SET foto = :foto WHERE id_instructor = :id')->execute(['foto' => $fotoNueva, 'id' => $id]);
            }
            $this->pdo->commit();
            if ($fotoNueva !== null) {
                $this->fotos->borrar($fotoAnterior, $id);
            }
            $r['id'] = $id;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($fotoNueva !== null) {
                $this->fotos->borrar($fotoNueva, $id);
            }
            if ($e instanceof DomainException) {
                $campo = [1 => 'email', 2 => 'especialidades', 3 => 'foto'][$e->getCode()] ?? 'general';
                $r['errores'][$campo] = $e->getMessage();
            } elseif ($e instanceof PDOException && in_array((string) $e->getCode(), ['23000', '40001'], true)) {
                $r['errores']['general'] = 'Correo duplicado, relación inválida o cambio simultáneo. No se guardaron cambios; revisa y vuelve a intentar.';
            } else {
                throw $e;
            }
        }
        return $r;
    }

    private function experienciaValida(string $valor): bool
    {
        return preg_match('/^(0|[1-9][0-9]{0,2})$/D', $valor) === 1 && (int) $valor <= 255;
    }

    public function cambiarEstado(int $id, string $activo, bool $confirmado): array
    {
        if (!$confirmado || !in_array($activo, ['0', '1'], true)) {
            return ['tipo' => 'error', 'mensaje' => 'Confirma un estado válido.'];
        }
        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare('SELECT activo FROM instructor WHERE id_instructor = :id FOR UPDATE');
            $stmt->execute(['id' => $id]);
            $anterior = $stmt->fetchColumn();
            if ($anterior === false) {
                throw new DomainException('El instructor ya no existe.');
            }
            if ((int) $anterior === (int) $activo) {
                $this->pdo->commit();
                return ['tipo' => 'exito', 'mensaje' => 'El instructor ya tenía ese estado. No se realizaron cambios.'];
            }
            if ($activo === '0') {
                $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM grupo WHERE id_instructor = :id AND estado IN ('activo','planeado')");
                $stmt->execute(['id' => $id]);
                if ((int) $stmt->fetchColumn()) {
                    throw new DomainException('No se puede suspender: tiene grupos activos o planeados. Reasígnalos o ciérralos primero.');
                }
                if ($this->extras($id)) {
                    throw new DomainException('No se puede suspender: tiene clases extras futuras pendientes o confirmadas. Deben resolverse primero.');
                }
                $this->pdo->prepare("UPDATE usuario SET reactivar_tras_suspension = activo, activo = 0, version_sesion = version_sesion + 1 WHERE id_instructor = :id AND rol = 'instructor'")->execute(['id' => $id]);
            } else {
                $this->pdo->prepare("UPDATE usuario SET activo = reactivar_tras_suspension, reactivar_tras_suspension = 0 WHERE id_instructor = :id AND rol = 'instructor'")->execute(['id' => $id]);
            }
            $this->pdo->prepare('UPDATE instructor SET activo = :activo WHERE id_instructor = :id')->execute(['id' => $id, 'activo' => (int) $activo]);
            $this->pdo->commit();
            return ['tipo' => 'exito', 'mensaje' => $activo === '0' ? 'Instructor suspendido. Acceso bloqueado; historial y matrículas conservados.' : 'Instructor reactivado. Solo se restaura el acceso que estaba habilitado antes de suspenderlo. No se reasignan grupos.'];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($e instanceof DomainException) {
                return ['tipo' => 'error', 'mensaje' => $e->getMessage()];
            }
            if ($e instanceof PDOException && (string) $e->getCode() === '40001') {
                return ['tipo' => 'error', 'mensaje' => 'Hubo un cambio simultáneo. Recarga y confirma nuevamente.'];
            }
            throw $e;
        }
    }
}
