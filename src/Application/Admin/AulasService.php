<?php

declare(strict_types=1);

namespace App\Application\Admin;

use DateTimeImmutable;
use DomainException;
use PDO;
use PDOException;

final class AulasService
{
    public const ESTADOS = ['Activa', 'Inactiva', 'Mantenimiento'];
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function listar(array $f, ?DateTimeImmutable $ahora = null): array
    {
        $ahora = $ahora ?? new DateTimeImmutable();
        $filtros = ['q' => mb_substr(GestionAcademicaService::texto($f, 'q'), 0, 150), 'estado' => GestionAcademicaService::texto($f, 'estado')];
        $from = "aula a LEFT JOIN (SELECT DISTINCT g.id_aula FROM grupo g JOIN horario h ON h.id_grupo = g.id_grupo
            WHERE g.estado IN ('activo','planeado') AND g.fecha_inicio <= :fecha_inicio AND (g.fecha_fin IS NULL OR g.fecha_fin >= :fecha_fin)
            AND h.dia_semana = :dia AND h.hora_inicio <= :hora_inicio AND h.hora_fin > :hora_fin) ocupadas ON ocupadas.id_aula = a.id_aula";
        $params = ['fecha_inicio' => $ahora->format('Y-m-d'), 'fecha_fin' => $ahora->format('Y-m-d'),
            'dia' => GruposService::DIAS[(int) $ahora->format('N') - 1], 'hora_inicio' => $ahora->format('H:i:s'), 'hora_fin' => $ahora->format('H:i:s')];
        $condiciones = [];
        if ($filtros['q'] !== '') {
            $condiciones[] = '(a.nombre LIKE :nombre OR a.ubicacion LIKE :ubicacion OR a.recursos LIKE :recursos OR CAST(a.id_aula AS CHAR) = :id_q)';
            foreach (['nombre', 'ubicacion', 'recursos'] as $campo) {
                $params[$campo] = '%' . $filtros['q'] . '%';
            }
            $params['id_q'] = ltrim($filtros['q'], '#');
        }
        $estados = ['Disponible' => 'a.activa = 1 AND ocupadas.id_aula IS NULL', 'Ocupada' => 'a.activa = 1 AND ocupadas.id_aula IS NOT NULL',
            'Mantenimiento' => "a.activa = 0 AND a.tipo_inactividad = 'Mantenimiento'", 'Inactiva' => "a.activa = 0 AND a.tipo_inactividad = 'General'"];
        if (isset($estados[$filtros['estado']])) {
            $condiciones[] = '(' . $estados[$filtros['estado']] . ')';
        } else {
            $filtros['estado'] = '';
        }
        $select = "a.*, CASE WHEN a.activa = 0 THEN IF(a.tipo_inactividad = 'Mantenimiento', 'Mantenimiento', 'Inactiva')
            WHEN ocupadas.id_aula IS NOT NULL THEN 'Ocupada' ELSE 'Disponible' END AS estado_visible";
        $lista = ListadoAdmin::pagina($this->pdo, $select, $from, $condiciones ? ' WHERE ' . implode(' AND ', $condiciones) : '', $params, 'a.nombre, a.id_aula', $f);
        foreach ($lista['filas'] as &$fila) {
            $fila['agenda'] = $this->agenda((int) $fila['id_aula'], true);
        }
        unset($fila);
        return $lista + ['filtros' => $filtros, 'consulta' => $ahora->format('d/m/Y H:i'), 'zona' => $ahora->getTimezone()->getName()];
    }

    public function buscar(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM aula WHERE id_aula = :id');
        $stmt->execute(['id' => $id]);
        $aula = $stmt->fetch();
        if ($aula) {
            $aula['estado'] = $aula['activa'] ? 'Activa' : ($aula['tipo_inactividad'] === 'Mantenimiento' ? 'Mantenimiento' : 'Inactiva');
        }
        return $aula ?: null;
    }

    public function agenda(int $id, bool $soloAbiertos = false): array
    {
        $stmt = $this->pdo->prepare("SELECT g.*, c.nombre AS curso, CONCAT(i.nombre, ' ', i.apellido) AS instructor
            FROM grupo g JOIN curso c ON c.id_curso = g.id_curso JOIN instructor i ON i.id_instructor = g.id_instructor
            WHERE g.id_aula = :id" . ($soloAbiertos ? " AND g.estado IN ('activo','planeado')" : '') . ' ORDER BY g.fecha_inicio DESC, g.id_grupo DESC');
        $stmt->execute(['id' => $id]);
        $agenda = $stmt->fetchAll();
        $horarios = new GruposService($this->pdo);
        foreach ($agenda as &$grupo) {
            $grupo['horarios'] = $horarios->horarios((int) $grupo['id_grupo']);
        }
        unset($grupo);
        return $agenda;
    }

    public function guardar(array $datos, int $id = 0): array
    {
        $v = [];
        $errores = [];
        foreach (['nombre', 'capacidad', 'recursos', 'ubicacion'] as $campo) {
            $v[$campo] = GestionAcademicaService::texto($datos, $campo);
        }
        if ($v['nombre'] === '' || mb_strlen($v['nombre']) > 50) {
            $errores['nombre'] = 'Indica un nombre de hasta 50 caracteres.';
        }
        if (!preg_match('/^[1-9][0-9]{0,2}$/D', $v['capacidad']) || (int) $v['capacidad'] > 255) {
            $errores['capacidad'] = 'La capacidad debe ser un entero entre 1 y 255.';
        }
        foreach (['recursos' => 255, 'ubicacion' => 150] as $campo => $maximo) {
            if (mb_strlen($v[$campo]) > $maximo) {
                $errores[$campo] = "Máximo $maximo caracteres.";
            }
        }
        $r = ['valores' => $v, 'errores' => $errores, 'id' => $id];
        if ($errores) {
            return $r;
        }
        try {
            $this->pdo->beginTransaction();
            if ($id) {
                $this->bloquear($id);
                $stmt = $this->pdo->prepare("SELECT MAX(capacidad_maxima) FROM grupo WHERE id_aula = :id AND estado IN ('activo','planeado')");
                $stmt->execute(['id' => $id]);
                $minimo = (int) $stmt->fetchColumn();
                if ((int) $v['capacidad'] < $minimo) {
                    throw new DomainException('Sus grupos abiertos requieren una capacidad de al menos ' . $minimo . '. Revisa los grupos antes de reducirla.');
                }
            }
            $campos = $v;
            $campos['recursos'] = $campos['recursos'] ?: null;
            $campos['ubicacion'] = $campos['ubicacion'] ?: null;
            if ($id) {
                $sql = 'UPDATE aula SET nombre = :nombre, capacidad = :capacidad, recursos = :recursos, ubicacion = :ubicacion WHERE id_aula = :id';
                $campos['id'] = $id;
            } else {
                $sql = 'INSERT INTO aula (nombre, capacidad, recursos, ubicacion) VALUES (:nombre, :capacidad, :recursos, :ubicacion)';
            }
            $this->pdo->prepare($sql)->execute($campos);
            $id = $id ?: (int) $this->pdo->lastInsertId();
            $this->pdo->commit();
            $r['id'] = $id;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($e instanceof DomainException) {
                $r['errores']['general'] = $e->getMessage();
            } elseif ($e instanceof PDOException && (string) $e->getCode() === '40001') {
                $r['errores']['general'] = 'Hubo un cambio simultáneo. No se guardaron cambios; recarga e inténtalo de nuevo.';
            } else {
                throw $e;
            }
        }
        return $r;
    }

    private function bloquear(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT activa, tipo_inactividad FROM aula WHERE id_aula = :id FOR UPDATE');
        $stmt->execute(['id' => $id]);
        $aula = $stmt->fetch();
        if (!$aula) {
            throw new DomainException('El aula ya no existe.');
        }
        return $aula;
    }

    public function cambiarEstado(int $id, string $estado, bool $confirmado): array
    {
        if (!$confirmado || !in_array($estado, self::ESTADOS, true)) {
            return ['tipo' => 'error', 'mensaje' => 'Confirma un estado válido. La ocupación se calcula automáticamente, no se edita.'];
        }
        try {
            $this->pdo->beginTransaction();
            $aula = $this->bloquear($id);
            $anterior = $aula['activa'] ? 'Activa' : ($aula['tipo_inactividad'] === 'Mantenimiento' ? 'Mantenimiento' : 'Inactiva');
            if ($anterior === $estado) {
                $this->pdo->commit();
                return ['tipo' => 'exito', 'mensaje' => 'El aula ya tenía ese estado. No se realizaron cambios.'];
            }
            if ($estado !== 'Activa') {
                $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM grupo WHERE id_aula = :id AND estado IN ('activo','planeado')");
                $stmt->execute(['id' => $id]);
                if ((int) $stmt->fetchColumn()) {
                    throw new DomainException('No puedes deshabilitar ni poner en mantenimiento un aula con grupos activos o planeados. Reasígnalos o ciérralos primero.');
                }
            }
            $this->pdo->prepare('UPDATE aula SET activa = :activa, tipo_inactividad = :tipo WHERE id_aula = :id')
                ->execute(['id' => $id, 'activa' => $estado === 'Activa' ? 1 : 0, 'tipo' => $estado === 'Mantenimiento' ? 'Mantenimiento' : 'General']);
            $this->pdo->commit();
            return ['tipo' => 'exito', 'mensaje' => 'Estado del aula actualizado. No se modificaron grupos ni matrículas.'];
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
