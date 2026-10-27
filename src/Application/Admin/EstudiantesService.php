<?php

declare(strict_types=1);

namespace App\Application\Admin;

use DomainException;
use PDO;
use PDOException;

final class EstudiantesService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function listar(array $f): array
    {
        $filtros = ['q' => mb_substr(GestionAcademicaService::texto($f, 'q'), 0, 150),
            'estado' => GestionAcademicaService::texto($f, 'estado'), 'acceso' => GestionAcademicaService::texto($f, 'acceso')];
        $where = [];
        $params = [];
        if ($filtros['q'] !== '') {
            $where[] = "(CONCAT(e.nombre, ' ', e.apellido) LIKE :nombre OR e.email LIKE :email OR e.telefono LIKE :telefono
                OR EXISTS (SELECT 1 FROM inscripcion m JOIN grupo g ON g.id_grupo = m.id_grupo
                    JOIN curso c ON c.id_curso = g.id_curso JOIN instrumento ins ON ins.id_instrumento = c.id_instrumento
                    WHERE m.id_estudiante = e.id_estudiante AND ins.nombre LIKE :instrumento))";
            foreach (['nombre', 'email', 'telefono', 'instrumento'] as $campo) {
                $params[$campo] = '%' . $filtros['q'] . '%';
            }
        }
        if (in_array($filtros['estado'], ['Activo', 'Suspendido'], true)) {
            $where[] = 'e.estado = :estado';
            $params['estado'] = $filtros['estado'];
        } else {
            $filtros['estado'] = '';
        }
        $accesos = ['activo' => 'u.acceso = 1', 'inactivo' => 'u.acceso = 0', 'sin-cuenta' => 'u.cuentas IS NULL'];
        if (isset($accesos[$filtros['acceso']])) {
            $where[] = $accesos[$filtros['acceso']];
        } else {
            $filtros['acceso'] = '';
        }
        $lista = ListadoAdmin::pagina($this->pdo,
            'e.*, u.cuentas, u.acceso, (SELECT ruta FROM estudiante_foto f WHERE f.id_estudiante = e.id_estudiante) AS foto',
            "estudiante e LEFT JOIN (SELECT id_estudiante, COUNT(*) AS cuentas, MAX(activo) AS acceso FROM usuario WHERE rol = 'estudiante' GROUP BY id_estudiante) u ON u.id_estudiante = e.id_estudiante",
            $where ? ' WHERE ' . implode(' AND ', $where) : '', $params, 'e.apellido, e.nombre, e.id_estudiante', $f);
        $stmt = $this->pdo->prepare("SELECT DISTINCT ins.nombre FROM inscripcion m JOIN grupo g ON g.id_grupo = m.id_grupo
            JOIN curso c ON c.id_curso = g.id_curso JOIN instrumento ins ON ins.id_instrumento = c.id_instrumento
            WHERE m.id_estudiante = :id AND m.estado IN ('Activa','Pendiente') ORDER BY ins.nombre");
        foreach ($lista['filas'] as &$fila) {
            $stmt->execute(['id' => $fila['id_estudiante']]);
            $fila['instrumentos'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
        unset($fila);
        return $lista + ['filtros' => $filtros];
    }

    public function buscar(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM estudiante WHERE id_estudiante = :id');
        $stmt->execute(['id' => $id]);
        $estudiante = $stmt->fetch();
        if (!$estudiante) {
            return null;
        }
        $stmt = $this->pdo->prepare("SELECT id_usuario, email_usuario, activo FROM usuario WHERE id_estudiante = :id AND rol = 'estudiante' ORDER BY id_usuario");
        $stmt->execute(['id' => $id]);
        $estudiante['cuentas'] = $stmt->fetchAll();
        $stmt = $this->pdo->prepare('SELECT ruta FROM estudiante_foto WHERE id_estudiante = :id');
        $stmt->execute(['id' => $id]);
        $estudiante['foto'] = $stmt->fetchColumn() ?: null;
        $estudiante['acceso_activo'] = (string) (int) ($estudiante['cuentas'][0]['activo'] ?? 0);
        return $estudiante;
    }

    public function matriculas(int $id): array
    {
        $stmt = $this->pdo->prepare("SELECT m.*, c.nombre AS curso, ins.nombre AS instrumento, g.estado AS grupo_estado,
            (SELECT COALESCE(SUM(p.monto_pagado), 0) FROM pago p WHERE p.id_inscripcion = m.id_inscripcion AND p.estado = 'Pagado') AS total_pagado,
            (SELECT ROUND(AVG(ev.calificacion), 2) FROM evaluacion ev WHERE ev.id_inscripcion = m.id_inscripcion) AS promedio,
            (SELECT ROUND(100 * SUM(a.presente = 'S') / NULLIF(COUNT(*), 0), 1) FROM asistencia a WHERE a.id_inscripcion = m.id_inscripcion) AS asistencia
            FROM inscripcion m JOIN grupo g ON g.id_grupo = m.id_grupo JOIN curso c ON c.id_curso = g.id_curso
            JOIN instrumento ins ON ins.id_instrumento = c.id_instrumento
            WHERE m.id_estudiante = :id ORDER BY m.fecha DESC, m.id_inscripcion DESC");
        $stmt->execute(['id' => $id]);
        return $stmt->fetchAll();
    }

    public function revision(int $id): string
    {
        $stmt = $this->pdo->prepare("SELECT id_inscripcion, id_grupo, estado FROM inscripcion WHERE id_estudiante = :id AND estado IN ('Activa','Pendiente') ORDER BY id_inscripcion");
        $stmt->execute(['id' => $id]);
        return hash('sha256', json_encode($stmt->fetchAll()));
    }

    public function guardar(array $datos, int $id = 0): array
    {
        $v = [];
        $errores = [];
        foreach (['nombre', 'apellido', 'email', 'telefono', 'direccion', 'fecha_inscripcion', 'crear_cuenta', 'acceso_activo'] as $campo) {
            $v[$campo] = GestionAcademicaService::texto($datos, $campo);
        }
        foreach (['nombre', 'apellido'] as $campo) {
            if ($v[$campo] === '' || mb_strlen($v[$campo]) > 100) {
                $errores[$campo] = 'Campo obligatorio, máximo 100 caracteres.';
            }
        }
        if (mb_strlen($v['email']) > 150 || !filter_var($v['email'], FILTER_VALIDATE_EMAIL)) {
            $errores['email'] = 'Indica un correo válido de hasta 150 caracteres.';
        }
        if ($v['telefono'] !== '' && (mb_strlen($v['telefono']) > 20 || !preg_match('/^[0-9+()\-\s]+$/D', $v['telefono']))) {
            $errores['telefono'] = 'Máximo 20 caracteres: dígitos, espacios y + ( ) -.';
        }
        if (mb_strlen($v['direccion']) > 255) {
            $errores['direccion'] = 'Máximo 255 caracteres.';
        }
        if (!$id && $v['fecha_inscripcion'] === '') {
            $v['fecha_inscripcion'] = date('Y-m-d');
        }
        if ($v['fecha_inscripcion'] !== '' && (!ListadoAdmin::fecha($v['fecha_inscripcion']) || $v['fecha_inscripcion'] > date('Y-m-d'))) {
            $errores['fecha_inscripcion'] = 'Indica una fecha válida, no futura.';
        }
        if (!in_array($v['acceso_activo'], ['', '0', '1'], true)) {
            $errores['acceso_activo'] = 'Selecciona un estado de acceso válido.';
        }
        $password = isset($datos['password']) && is_string($datos['password']) ? $datos['password'] : '';
        if ($v['crear_cuenta'] === '1') {
            if (strlen($password) < 8 || strlen($password) > 72 || str_contains($password, "\0")) {
                $errores['password'] = 'La contraseña inicial debe tener entre 8 y 72 bytes y no contener caracteres nulos.';
            }
            if ($password !== ($datos['password_confirmar'] ?? null)) {
                $errores['password_confirmar'] = 'Las contraseñas no coinciden.';
            }
        }
        $resultado = ['valores' => $v, 'errores' => $errores, 'id' => $id];
        if ($errores) {
            return $resultado;
        }
        try {
            $this->pdo->beginTransaction();
            $actual = null;
            if ($id) {
                $stmt = $this->pdo->prepare('SELECT estado FROM estudiante WHERE id_estudiante = :id FOR UPDATE');
                $stmt->execute(['id' => $id]);
                $actual = $stmt->fetch();
                if (!$actual) {
                    throw new DomainException('El estudiante ya no existe.');
                }
            }
            $stmt = $this->pdo->prepare("SELECT id_usuario FROM usuario WHERE id_estudiante = :id AND rol = 'estudiante' ORDER BY id_usuario FOR UPDATE");
            $stmt->execute(['id' => $id]);
            $cuentas = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (count($cuentas) > 1) {
                throw new DomainException('El estudiante tiene varias cuentas asociadas. Deben revisarse antes de editar su acceso o correo.');
            }
            if ($v['crear_cuenta'] === '1' && ($cuentas || ($actual && $actual['estado'] === 'Suspendido'))) {
                throw new DomainException('No se puede crear una cuenta: ya existe una o el estudiante está suspendido.');
            }
            if ($actual && $actual['estado'] === 'Suspendido' && $v['acceso_activo'] === '1') {
                throw new DomainException('Reactiva al estudiante antes de habilitar su acceso.');
            }
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM estudiante WHERE email = :email AND id_estudiante <> :id');
            $stmt->execute(['email' => $v['email'], 'id' => $id]);
            $duplicado = (int) $stmt->fetchColumn() > 0;
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM usuario WHERE email_usuario = :email AND (id_estudiante IS NULL OR id_estudiante <> :id OR rol <> 'estudiante')");
            $stmt->execute(['email' => $v['email'], 'id' => $id]);
            if ($duplicado || (int) $stmt->fetchColumn() > 0) {
                throw new DomainException('Ya existe una ficha o cuenta con ese correo electrónico.', 1);
            }
            $campos = array_intersect_key($v, array_flip(['nombre', 'apellido', 'email', 'telefono', 'direccion', 'fecha_inscripcion']));
            $campos['telefono'] = $campos['telefono'] ?: null;
            $campos['direccion'] = $campos['direccion'] ?: null;
            $campos['fecha_inscripcion'] = $campos['fecha_inscripcion'] ?: null;
            if ($id) {
                $sql = 'UPDATE estudiante SET ' . implode(', ', array_map(static fn ($c): string => "$c = :$c", array_keys($campos))) . ' WHERE id_estudiante = :id';
                $campos['id'] = $id;
            } else {
                $sql = 'INSERT INTO estudiante (' . implode(', ', array_keys($campos)) . ') VALUES (:' . implode(', :', array_keys($campos)) . ')';
            }
            $this->pdo->prepare($sql)->execute($campos);
            $id = $id ?: (int) $this->pdo->lastInsertId();
            if ($v['crear_cuenta'] === '1') {
                $stmt = $this->pdo->prepare("INSERT INTO usuario (id_estudiante, email_usuario, password_hash, rol, activo) VALUES (:id, :email, :hash, 'estudiante', 1)");
                $stmt->execute(['id' => $id, 'email' => $v['email'], 'hash' => password_hash($password, PASSWORD_DEFAULT)]);
            } elseif ($cuentas) {
                $params = ['email' => $v['email'], 'id' => $cuentas[0]];
                $acceso = '';
                if ($v['acceso_activo'] !== '' && (!$actual || $actual['estado'] === 'Activo')) {
                    // Incrementar antes de actualizar activo invalida todas las
                    // sesiones antiguas cuando se cambia el estado de acceso.
                    $acceso = ', version_sesion = version_sesion + IF(activo <> :estado_version, 1, 0), activo = :estado';
                    $params['estado_version'] = (int) $v['acceso_activo'];
                    $params['estado'] = (int) $v['acceso_activo'];
                }
                $this->pdo->prepare("UPDATE usuario SET email_usuario = :email$acceso WHERE id_usuario = :id")->execute($params);
            }
            $this->pdo->commit();
            $resultado['id'] = $id;
        } catch (DomainException $e) {
            $this->pdo->rollBack();
            $resultado['errores'][$e->getCode() === 1 ? 'email' : 'general'] = $e->getMessage();
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if (!in_array((string) $e->getCode(), ['23000', '40001'], true)) {
                throw $e;
            }
            $resultado['errores']['general'] = 'El correo ya está en uso o hubo un cambio simultáneo. No se guardó ningún cambio; revisa e inténtalo de nuevo.';
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
        return $resultado;
    }

    public function cambiarEstado(int $id, string $estado, string $motivo, string $revision, bool $confirmado): array
    {
        $motivo = trim($motivo);
        if (!$confirmado || !in_array($estado, ['Activo', 'Suspendido'], true)
            || ($estado === 'Suspendido' && ($motivo === '' || mb_strlen($motivo) > 255))) {
            return ['tipo' => 'error', 'mensaje' => 'Confirma la acción e indica un motivo de suspensión de hasta 255 caracteres.'];
        }
        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare('SELECT estado FROM estudiante WHERE id_estudiante = :id FOR UPDATE');
            $stmt->execute(['id' => $id]);
            $actual = $stmt->fetchColumn();
            if (!$actual) {
                throw new DomainException('El estudiante ya no existe.');
            }
            if ($actual === $estado) {
                $this->pdo->commit();
                return ['tipo' => 'exito', 'mensaje' => 'El estudiante ya tenía ese estado. No se realizaron cambios.'];
            }
            if ($estado === 'Suspendido') {
                $stmt = $this->pdo->prepare("SELECT DISTINCT id_grupo FROM inscripcion WHERE id_estudiante = :id AND estado IN ('Activa','Pendiente') ORDER BY id_grupo");
                $stmt->execute(['id' => $id]);
                foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $grupo) {
                    $this->pdo->prepare('SELECT id_grupo FROM grupo WHERE id_grupo = :id FOR UPDATE')->execute(['id' => $grupo]);
                }
                // Lectura bloqueante actual: incluye matrículas confirmadas
                // mientras esperábamos los bloqueos, no un snapshot antiguo.
                $stmt = $this->pdo->prepare("SELECT id_inscripcion, id_grupo, estado FROM inscripcion WHERE id_estudiante = :id AND estado IN ('Activa','Pendiente') ORDER BY id_inscripcion FOR UPDATE");
                $stmt->execute(['id' => $id]);
                $matriculas = $stmt->fetchAll();
                if (!hash_equals(hash('sha256', json_encode($matriculas)), $revision)) {
                    throw new DomainException('Las matrículas cambiaron. Revisa nuevamente la ficha y confirma los lugares que se liberarán.');
                }
                $this->pdo->prepare("UPDATE inscripcion SET estado = 'Cancelada' WHERE id_estudiante = :id AND estado IN ('Activa','Pendiente')")->execute(['id' => $id]);
                $this->pdo->prepare("UPDATE estudiante SET estado = 'Suspendido', suspension_fecha = NOW(), suspension_motivo = :motivo WHERE id_estudiante = :id")->execute(['id' => $id, 'motivo' => $motivo]);
                $this->pdo->prepare("UPDATE usuario SET reactivar_tras_suspension = activo, activo = 0, version_sesion = version_sesion + 1 WHERE id_estudiante = :id AND rol = 'estudiante'")->execute(['id' => $id]);
                $mensaje = 'Estudiante suspendido. Se cancelaron ' . count($matriculas) . ' matrículas vigentes y se liberaron sus lugares. Historial y pagos conservados.';
            } else {
                $this->pdo->prepare("UPDATE estudiante SET estado = 'Activo' WHERE id_estudiante = :id")->execute(['id' => $id]);
                $this->pdo->prepare("UPDATE usuario SET activo = reactivar_tras_suspension, reactivar_tras_suspension = 0 WHERE id_estudiante = :id AND rol = 'estudiante'")->execute(['id' => $id]);
                $mensaje = 'Estudiante reactivado. Se restableció solo el acceso que estaba habilitado antes de suspenderlo. Las matrículas canceladas no se restauran.';
            }
            $this->pdo->commit();
            return ['tipo' => 'exito', 'mensaje' => $mensaje];
        } catch (DomainException $e) {
            $this->pdo->rollBack();
            return ['tipo' => 'error', 'mensaje' => $e->getMessage()];
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ((string) $e->getCode() !== '40001') {
                throw $e;
            }
            return ['tipo' => 'error', 'mensaje' => 'Hubo un cambio simultáneo. No se guardaron cambios; recarga y confirma nuevamente.'];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
