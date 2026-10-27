<?php

declare(strict_types=1);

namespace App\Application\Admin;

use DateTimeImmutable;
use DomainException;
use PDO;
use PDOException;

final class GruposService
{
    public const ESTADOS = ['planeado', 'activo', 'finalizado', 'cancelado'];
    public const DIAS = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function opciones(): array
    {
        return [
            'cursos' => $this->pdo->query('SELECT id_curso, id_instrumento, nombre, activo, disponible, max_grupos FROM curso ORDER BY nombre')->fetchAll(),
            'instructores' => $this->pdo->query('SELECT id_instructor, nombre, apellido, activo FROM instructor ORDER BY nombre, apellido')->fetchAll(),
            'especialidades' => $this->pdo->query('SELECT id_instructor, id_instrumento FROM instructor_instrumento')->fetchAll(),
            'aulas' => $this->pdo->query('SELECT id_aula, nombre, capacidad, activa, tipo_inactividad FROM aula ORDER BY nombre')->fetchAll(),
        ];
    }

    public function listar(array $f): array
    {
        $filtros = ['q' => mb_substr(GestionAcademicaService::texto($f, 'q'), 0, 150),
            'curso' => GestionAcademicaService::texto($f, 'curso'), 'instructor' => GestionAcademicaService::texto($f, 'instructor'),
            'estado' => GestionAcademicaService::texto($f, 'estado')];
        $condiciones = [];
        $params = [];
        if ($filtros['q'] !== '') {
            $condiciones[] = "(c.nombre LIKE :curso_q OR CONCAT(i.nombre, ' ', i.apellido) LIKE :instructor_q OR a.nombre LIKE :aula_q OR CAST(g.id_grupo AS CHAR) = :id_q)";
            foreach (['curso_q', 'instructor_q', 'aula_q'] as $clave) {
                $params[$clave] = '%' . $filtros['q'] . '%';
            }
            $params['id_q'] = ltrim($filtros['q'], '#');
        }
        foreach (['curso' => 'g.id_curso', 'instructor' => 'g.id_instructor'] as $clave => $columna) {
            if (ctype_digit($filtros[$clave]) && (int) $filtros[$clave] > 0) {
                $condiciones[] = "$columna = :$clave";
                $params[$clave] = (int) $filtros[$clave];
            } else {
                $filtros[$clave] = '';
            }
        }
        if (in_array($filtros['estado'], self::ESTADOS, true)) {
            $condiciones[] = 'g.estado = :estado';
            $params['estado'] = $filtros['estado'];
        } else {
            $filtros['estado'] = '';
        }
        $listado = ListadoAdmin::pagina($this->pdo,
            "g.*, c.nombre AS curso, CONCAT(i.nombre, ' ', i.apellido) AS instructor, a.nombre AS aula,
                (SELECT COUNT(*) FROM inscripcion m WHERE m.id_grupo = g.id_grupo AND m.estado IN ('Activa','Pendiente')) AS inscritos",
            'grupo g JOIN curso c ON c.id_curso = g.id_curso JOIN instructor i ON i.id_instructor = g.id_instructor JOIN aula a ON a.id_aula = g.id_aula',
            $condiciones ? ' WHERE ' . implode(' AND ', $condiciones) : '', $params, 'g.fecha_inicio DESC, g.id_grupo DESC', $f);
        foreach ($listado['filas'] as &$fila) {
            $fila['horarios'] = $this->horarios((int) $fila['id_grupo']);
        }
        unset($fila);
        return $listado + ['filtros' => $filtros];
    }

    public function horarios(int $id): array
    {
        $stmt = $this->pdo->prepare("SELECT dia_semana, TIME_FORMAT(hora_inicio, '%H:%i') AS hora_inicio, TIME_FORMAT(hora_fin, '%H:%i') AS hora_fin
            FROM horario WHERE id_grupo = :id ORDER BY FIELD(dia_semana, 'Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'), hora_inicio");
        $stmt->execute(['id' => $id]);
        return $stmt->fetchAll();
    }

    public function buscar(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT g.*, c.nombre AS curso, CONCAT(i.nombre, ' ', i.apellido) AS instructor, a.nombre AS aula
            FROM grupo g JOIN curso c ON c.id_curso = g.id_curso JOIN instructor i ON i.id_instructor = g.id_instructor
            JOIN aula a ON a.id_aula = g.id_aula WHERE g.id_grupo = :id");
        $stmt->execute(['id' => $id]);
        $grupo = $stmt->fetch();
        if (!$grupo) {
            return null;
        }
        $grupo['horarios'] = $this->horarios($id);
        return $grupo;
    }

    public function alumnos(int $id): array
    {
        $stmt = $this->pdo->prepare("SELECT m.id_inscripcion, m.estado, m.fecha, e.id_estudiante, e.nombre, e.apellido, e.email, e.estado AS estudiante_estado
            FROM inscripcion m JOIN estudiante e ON e.id_estudiante = m.id_estudiante
            WHERE m.id_grupo = :id ORDER BY e.apellido, e.nombre, m.id_inscripcion");
        $stmt->execute(['id' => $id]);
        return $stmt->fetchAll();
    }

    public function guardar(array $datos, int $id = 0): array
    {
        $v = [];
        $errores = [];
        foreach (['id_curso', 'id_instructor', 'id_aula', 'capacidad_maxima', 'fecha_inicio', 'fecha_fin', 'estado', 'confirmar_reasignacion'] as $campo) {
            $v[$campo] = GestionAcademicaService::texto($datos, $campo);
        }
        foreach (['id_curso', 'id_instructor', 'id_aula', 'capacidad_maxima'] as $campo) {
            if (!preg_match('/^[1-9][0-9]{0,8}$/D', $v[$campo])) {
                $errores[$campo] = 'Selecciona un valor válido mayor que cero.';
            }
        }
        if (!ListadoAdmin::fecha($v['fecha_inicio'])) {
            $errores['fecha_inicio'] = 'Indica una fecha válida.';
        }
        if ($v['fecha_fin'] !== '' && (!ListadoAdmin::fecha($v['fecha_fin']) || $v['fecha_fin'] < $v['fecha_inicio'])) {
            $errores['fecha_fin'] = 'La fecha final debe ser válida y no anterior al inicio.';
        }
        if (!in_array($v['estado'], self::ESTADOS, true)) {
            $errores['estado'] = 'Selecciona un estado válido.';
        }
        $horarios = $datos['horarios'] ?? [];
        $v['horarios'] = [];
        if (!is_array($horarios) || count($horarios) < 1 || count($horarios) > 21) {
            $errores['horarios'] = 'Agrega entre 1 y 21 franjas semanales.';
        } else {
            foreach ($horarios as $h) {
                $h = is_array($h) ? $h : [];
                $fila = [];
                foreach (['dia_semana', 'hora_inicio', 'hora_fin'] as $campo) {
                    $fila[$campo] = GestionAcademicaService::texto($h, $campo);
                }
                if (!in_array($fila['dia_semana'], self::DIAS, true)
                    || !preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/D', $fila['hora_inicio'])
                    || !preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/D', $fila['hora_fin']) || $fila['hora_fin'] <= $fila['hora_inicio']) {
                    $errores['horarios'] = 'Revisa día y horas: el fin debe ser posterior al inicio.';
                }
                foreach ($v['horarios'] as $anterior) {
                    if ($anterior['dia_semana'] === $fila['dia_semana'] && $anterior['hora_inicio'] < $fila['hora_fin'] && $anterior['hora_fin'] > $fila['hora_inicio']) {
                        $errores['horarios'] = 'Las franjas del mismo grupo no pueden superponerse.';
                    }
                }
                $v['horarios'][] = $fila;
            }
        }
        $resultado = ['valores' => $v, 'errores' => $errores, 'id' => $id];
        if ($errores) {
            return $resultado;
        }
        $antes = $id ? $this->buscar($id) : null;
        try {
            $this->pdo->beginTransaction();
            // Serializa las validaciones de cupos, horarios y recursos. Todas
            // las escrituras de grupos toman estos bloqueos en el mismo orden.
            foreach (['curso' => 'id_curso', 'instructor' => 'id_instructor', 'aula' => 'id_aula'] as $tabla => $campo) {
                $ids = array_unique([(int) $v[$campo], (int) ($antes[$campo] ?? $v[$campo])]);
                sort($ids, SORT_NUMERIC);
                foreach ($ids as $recurso) {
                    $stmt = $this->pdo->prepare("SELECT $campo FROM $tabla WHERE $campo = :id FOR UPDATE");
                    $stmt->execute(['id' => $recurso]);
                    if (!$stmt->fetchColumn()) {
                        throw new DomainException('El curso, instructor o aula ya no existe.');
                    }
                }
            }
            if ($id) {
                $stmt = $this->pdo->prepare('SELECT * FROM grupo WHERE id_grupo = :id FOR UPDATE');
                $stmt->execute(['id' => $id]);
                $actual = $stmt->fetch();
                if (!$actual || !$antes || $actual['id_curso'] != $antes['id_curso'] || $actual['id_instructor'] != $antes['id_instructor'] || $actual['id_aula'] != $antes['id_aula']) {
                    throw new DomainException('El grupo cambió o ya no existe. Recarga la página.');
                }
                $antes = $actual;
            }
            $this->validarRecursos($v, $id, $antes);
            if ($this->abierto($v['estado'])) {
                $this->validarCruces($v, $id);
            }
            $campos = array_intersect_key($v, array_flip(['id_curso', 'id_instructor', 'id_aula', 'capacidad_maxima', 'fecha_inicio', 'fecha_fin', 'estado']));
            $campos['fecha_fin'] = $campos['fecha_fin'] ?: null;
            if ($id) {
                $sql = 'UPDATE grupo SET ' . implode(', ', array_map(static fn ($c): string => "$c = :$c", array_keys($campos))) . ' WHERE id_grupo = :id';
                $campos['id'] = $id;
            } else {
                $sql = 'INSERT INTO grupo (' . implode(', ', array_keys($campos)) . ') VALUES (:' . implode(', :', array_keys($campos)) . ')';
            }
            $this->pdo->prepare($sql)->execute($campos);
            $id = $id ?: (int) $this->pdo->lastInsertId();
            $this->pdo->prepare('DELETE FROM horario WHERE id_grupo = :id')->execute(['id' => $id]);
            $stmt = $this->pdo->prepare('INSERT INTO horario (id_grupo, dia_semana, hora_inicio, hora_fin) VALUES (:grupo, :dia_semana, :hora_inicio, :hora_fin)');
            foreach ($v['horarios'] as $h) {
                $stmt->execute(['grupo' => $id] + $h);
            }
            $this->pdo->commit();
            $resultado['id'] = $id;
        } catch (DomainException $e) {
            $this->pdo->rollBack();
            $resultado['errores']['general'] = $e->getMessage();
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if (!in_array((string) $e->getCode(), ['23000', '40001'], true)) {
                throw $e;
            }
            $resultado['errores']['general'] = 'No se pudo guardar por un cambio simultáneo o una relación inválida. Recarga e inténtalo de nuevo.';
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
        return $resultado;
    }

    private function abierto(string $estado): bool
    {
        return in_array($estado, ['activo', 'planeado'], true);
    }

    private function validarRecursos(array $v, int $id, ?array $antes): void
    {
        $stmt = $this->pdo->prepare('SELECT * FROM curso WHERE id_curso = :id');
        $stmt->execute(['id' => $v['id_curso']]);
        $curso = $stmt->fetch();
        if ((!$antes || $antes['id_curso'] != $v['id_curso'] || (!$this->abierto($antes['estado']) && $this->abierto($v['estado']))) && (!$curso['activo'] || !$curso['disponible'])) {
            throw new DomainException('El curso debe estar activo y disponible para abrir grupos.');
        }
        $stmt = $this->pdo->prepare('SELECT activo FROM instructor WHERE id_instructor = :id');
        $stmt->execute(['id' => $v['id_instructor']]);
        $activo = (bool) $stmt->fetchColumn();
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM instructor_instrumento WHERE id_instructor = :i AND id_instrumento = :ins');
        $stmt->execute(['i' => $v['id_instructor'], 'ins' => $curso['id_instrumento']]);
        $especialidad = (int) $stmt->fetchColumn() > 0;
        if ((!$activo || !$especialidad) && (!$antes || $antes['id_instructor'] != $v['id_instructor'] || $antes['id_curso'] != $v['id_curso'] || (!$this->abierto($antes['estado']) && $this->abierto($v['estado'])))) {
            throw new DomainException('Selecciona un instructor activo especializado en el instrumento del curso.');
        }
        $stmt = $this->pdo->prepare('SELECT capacidad, activa FROM aula WHERE id_aula = :id');
        $stmt->execute(['id' => $v['id_aula']]);
        $aula = $stmt->fetch();
        if (!$aula['activa'] && (!$antes || $antes['id_aula'] != $v['id_aula'] || (!$this->abierto($antes['estado']) && $this->abierto($v['estado'])))) {
            throw new DomainException('Selecciona un aula activa.');
        }
        if ((int) $v['capacidad_maxima'] > (int) $aula['capacidad']) {
            throw new DomainException('La capacidad del grupo no puede superar la del aula (' . $aula['capacidad'] . ').');
        }
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM inscripcion WHERE id_grupo = :id AND estado IN ('Activa','Pendiente')");
        $stmt->execute(['id' => $id]);
        $ocupados = (int) $stmt->fetchColumn();
        if ($ocupados > (int) $v['capacidad_maxima']) {
            throw new DomainException('Hay ' . $ocupados . ' matrículas vigentes. No puedes reducir el cupo por debajo de esa cantidad.');
        }
        if ($ocupados && (!$this->abierto($v['estado']) || ($antes && $antes['estado'] === 'activo' && $v['estado'] === 'planeado'))) {
            throw new DomainException('Resuelve las matrículas vigentes antes de cerrar el grupo o devolverlo a planeado. No se alterarán pagos ni matrículas automáticamente.');
        }
        if ($antes && $antes['id_curso'] != $v['id_curso']) {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM inscripcion WHERE id_grupo = :id');
            $stmt->execute(['id' => $id]);
            if ((int) $stmt->fetchColumn()) {
                throw new DomainException('No se puede cambiar el curso de un grupo con matrículas registradas.');
            }
        }
        if ($antes && $antes['id_instructor'] != $v['id_instructor'] && $v['confirmar_reasignacion'] !== '1') {
            throw new DomainException('Confirma la reasignación: el nuevo instructor tendrá acceso al historial académico del grupo.');
        }
        if ($antes && ($antes['id_instructor'] != $v['id_instructor'] || !$this->abierto($v['estado']))) {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM reserva_clase_extra WHERE id_grupo = :id AND estado IN ('Pendiente','Confirmada') AND fecha >= CURDATE()");
            $stmt->execute(['id' => $id]);
            if ((int) $stmt->fetchColumn()) {
                throw new DomainException('Hay clases extras futuras pendientes o confirmadas. Deben resolverse antes de reasignar o cerrar el grupo.');
            }
        }
        if ($this->abierto($v['estado'])) {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM grupo WHERE id_curso = :curso AND id_grupo <> :id AND estado IN ('activo','planeado')");
            $stmt->execute(['curso' => $v['id_curso'], 'id' => $id]);
            if ((int) $stmt->fetchColumn() >= (int) $curso['max_grupos']) {
                throw new DomainException('El curso ya alcanzó su máximo de grupos activos o planeados.');
            }
        }
    }

    private function validarCruces(array $v, int $id): void
    {
        $stmt = $this->pdo->prepare("SELECT g.id_grupo, g.fecha_inicio, g.fecha_fin, h.dia_semana, h.hora_inicio, h.hora_fin
            FROM grupo g JOIN horario h ON h.id_grupo = g.id_grupo
            WHERE g.id_grupo <> :id AND g.estado IN ('activo','planeado')
              AND (g.id_instructor = :profesor OR g.id_aula = :aula)
              AND g.fecha_inicio <= :fin AND COALESCE(g.fecha_fin, '9999-12-31') >= :inicio");
        $stmt->execute(['id' => $id, 'profesor' => $v['id_instructor'], 'aula' => $v['id_aula'],
            'fin' => $v['fecha_fin'] ?: '9999-12-31', 'inicio' => $v['fecha_inicio']]);
        foreach ($stmt->fetchAll() as $otro) {
            foreach ($v['horarios'] as $h) {
                if ($h['dia_semana'] === $otro['dia_semana'] && $h['hora_inicio'] < substr($otro['hora_fin'], 0, 5) && $h['hora_fin'] > substr($otro['hora_inicio'], 0, 5)
                    && self::coincideDia(max($v['fecha_inicio'], $otro['fecha_inicio']), min($v['fecha_fin'] ?: '9999-12-31', $otro['fecha_fin'] ?: '9999-12-31'), $h['dia_semana'])) {
                    throw new DomainException('Cruce de instructor o aula con el grupo #' . $otro['id_grupo'] . '. Revisa días, horas y fechas.');
                }
            }
        }
        $stmt = $this->pdo->prepare("SELECT fecha, hora_inicio, hora_fin FROM reserva_clase_extra
            WHERE id_instructor = :id AND estado IN ('Pendiente','Confirmada') AND fecha BETWEEN :inicio AND :fin");
        $stmt->execute(['id' => $v['id_instructor'], 'inicio' => $v['fecha_inicio'], 'fin' => $v['fecha_fin'] ?: '9999-12-31']);
        foreach ($stmt->fetchAll() as $extra) {
            $dia = self::DIAS[(int) (new DateTimeImmutable($extra['fecha']))->format('N') - 1];
            foreach ($v['horarios'] as $h) {
                if ($h['dia_semana'] === $dia && $h['hora_inicio'] < substr($extra['hora_fin'], 0, 5) && $h['hora_fin'] > substr($extra['hora_inicio'], 0, 5)) {
                    throw new DomainException('El instructor tiene una clase extra en conflicto el ' . $extra['fecha'] . '.');
                }
            }
        }
    }

    public static function coincideDia(string $inicio, string $fin, string $dia): bool
    {
        $fecha = new DateTimeImmutable($inicio);
        $numero = array_search($dia, self::DIAS, true);
        if ($numero === false || $inicio > $fin) {
            return false;
        }
        $desfase = ($numero + 1 - (int) $fecha->format('N') + 7) % 7;
        return $fecha->modify('+' . $desfase . ' days')->format('Y-m-d') <= $fin;
    }
}
