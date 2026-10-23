<?php

declare(strict_types=1);

namespace App\Application\Estudiante;

use PDO;

final class ReservaService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getCabinasActivas(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM cabina WHERE estado = 'Activa' ORDER BY nombre");
        return $stmt->fetchAll();
    }

    public function getGruposActivosParaEstudiante(int $idEstudiante): array
    {
        $sql = "SELECT i.id_inscripcion, i.id_grupo, g.id_instructor, c.nombre AS curso,
                       CONCAT(inst.nombre, ' ', inst.apellido) AS instructor, inst.id_instructor
                FROM inscripcion i
                JOIN grupo g ON g.id_grupo = i.id_grupo
                JOIN curso c ON c.id_curso = g.id_curso
                JOIN instructor inst ON inst.id_instructor = g.id_instructor
                WHERE i.id_estudiante = :id AND i.estado = 'Activa'
                ORDER BY c.nombre";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $idEstudiante]);
        return $stmt->fetchAll();
    }

    public function getReservasEstudiante(int $idEstudiante): array
    {
        $reservas = [];

        // Cabinas
        $sqlCab = "SELECT rc.id_reserva, rc.fecha, rc.hora_inicio, rc.hora_fin, rc.estado, rc.motivo,
                          c.nombre AS cabina, 'cabina' AS tipo
                   FROM reserva_cabina rc
                   JOIN cabina c ON c.id_cabina = rc.id_cabina
                   WHERE rc.id_estudiante = :id
                   ORDER BY rc.fecha DESC, rc.hora_inicio DESC";
        $stmt = $this->pdo->prepare($sqlCab);
        $stmt->execute(['id' => $idEstudiante]);
        foreach ($stmt->fetchAll() as $r) {
            $reservas[] = $r;
        }

        // Extras
        $sqlEx = "SELECT rce.id_reserva, rce.fecha, rce.hora_inicio, rce.hora_fin, rce.estado, rce.motivo,
                         c.nombre AS curso, CONCAT(inst.nombre, ' ', inst.apellido) AS instructor, 'extra' AS tipo
                  FROM reserva_clase_extra rce
                  JOIN grupo g ON g.id_grupo = rce.id_grupo
                  JOIN curso c ON c.id_curso = g.id_curso
                  JOIN instructor inst ON inst.id_instructor = rce.id_instructor
                  WHERE rce.id_estudiante = :id
                  ORDER BY rce.fecha DESC, rce.hora_inicio DESC";
        $stmt = $this->pdo->prepare($sqlEx);
        $stmt->execute(['id' => $idEstudiante]);
        foreach ($stmt->fetchAll() as $r) {
            $reservas[] = $r;
        }

        usort($reservas, function ($a, $b) {
            $da = $a['fecha'] . ' ' . $a['hora_inicio'];
            $db = $b['fecha'] . ' ' . $b['hora_inicio'];
            return strcmp($db, $da);
        });

        return $reservas;
    }

    public function reservarCabina(int $idEstudiante, array $data): array
    {
        $idCabina = (int) ($data['id_cabina'] ?? 0);
        $fecha = trim((string) ($data['fecha'] ?? ''));
        $horaInicio = trim((string) ($data['hora_inicio'] ?? ''));
        $horaFin = trim((string) ($data['hora_fin'] ?? ''));
        $motivo = trim((string) ($data['motivo'] ?? ''));

        if ($idCabina === 0 || $fecha === '' || $horaInicio === '' || $horaFin === '') {
            return ['ok' => false, 'msg' => 'Completa todos los campos obligatorios.'];
        }
        if ($horaFin <= $horaInicio) {
            return ['ok' => false, 'msg' => 'La hora de fin debe ser posterior a la hora de inicio.'];
        }
        if ($fecha < date('Y-m-d')) {
            return ['ok' => false, 'msg' => 'No puedes reservar en una fecha pasada.'];
        }

        // Verificar disponibilidad
        $sql = "SELECT COUNT(*) FROM reserva_cabina 
                WHERE id_cabina = :cab AND fecha = :fecha AND estado IN ('Pendiente','Confirmada')
                AND NOT (hora_fin <= :ini OR hora_inicio >= :fin)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['cab' => $idCabina, 'fecha' => $fecha, 'ini' => $horaInicio, 'fin' => $horaFin]);
        if ((int) $stmt->fetchColumn() > 0) {
            return ['ok' => false, 'msg' => 'La cabina ya está reservada en ese horario.'];
        }

        $sqlIns = "INSERT INTO reserva_cabina (id_estudiante, id_cabina, fecha, hora_inicio, hora_fin, estado, motivo)
                   VALUES (:est, :cab, :fecha, :ini, :fin, 'Pendiente', :motivo)";
        $stmt = $this->pdo->prepare($sqlIns);
        $stmt->execute([
            'est' => $idEstudiante,
            'cab' => $idCabina,
            'fecha' => $fecha,
            'ini' => $horaInicio,
            'fin' => $horaFin,
            'motivo' => $motivo !== '' ? $motivo : null,
        ]);
        return ['ok' => true, 'msg' => 'Solicitud de reserva de cabina enviada. Quedará pendiente de confirmación.'];
    }

    public function reservarClaseExtra(int $idEstudiante, array $data): array
    {
        $idGrupo = (int) ($data['id_grupo'] ?? 0);
        $fecha = trim((string) ($data['fecha'] ?? ''));
        $horaInicio = trim((string) ($data['hora_inicio'] ?? ''));
        $horaFin = trim((string) ($data['hora_fin'] ?? ''));
        $motivo = trim((string) ($data['motivo'] ?? ''));

        if ($idGrupo === 0 || $fecha === '' || $horaInicio === '' || $horaFin === '' || $motivo === '') {
            return ['ok' => false, 'msg' => 'Completa todos los campos obligatorios.'];
        }
        if ($horaFin <= $horaInicio) {
            return ['ok' => false, 'msg' => 'La hora de fin debe ser posterior a la hora de inicio.'];
        }
        if ($fecha < date('Y-m-d')) {
            return ['ok' => false, 'msg' => 'No puedes reservar en una fecha pasada.'];
        }

        // Obtener instructor del grupo
        $stmt = $this->pdo->prepare("SELECT id_instructor FROM grupo WHERE id_grupo = :g");
        $stmt->execute(['g' => $idGrupo]);
        $idInstructor = (int) $stmt->fetchColumn();
        if ($idInstructor === 0) {
            return ['ok' => false, 'msg' => 'Grupo inválido.'];
        }

        // Verificar que el alumno esté inscrito activo en ese grupo
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM inscripcion WHERE id_estudiante = :e AND id_grupo = :g AND estado = 'Activa'");
        $stmt->execute(['e' => $idEstudiante, 'g' => $idGrupo]);
        if ((int) $stmt->fetchColumn() === 0) {
            return ['ok' => false, 'msg' => 'Solo puedes solicitar clases extras para cursos en los que estás inscrito activamente.'];
        }

        // Verificar disponibilidad instructor
        $sql = "SELECT COUNT(*) FROM reserva_clase_extra
                WHERE id_instructor = :ins AND fecha = :fecha AND estado IN ('Pendiente','Confirmada')
                AND NOT (hora_fin <= :ini OR hora_inicio >= :fin)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['ins' => $idInstructor, 'fecha' => $fecha, 'ini' => $horaInicio, 'fin' => $horaFin]);
        if ((int) $stmt->fetchColumn() > 0) {
            return ['ok' => false, 'msg' => 'El instructor ya tiene una reserva en ese horario.'];
        }

        $sqlIns = "INSERT INTO reserva_clase_extra (id_estudiante, id_grupo, id_instructor, fecha, hora_inicio, hora_fin, estado, motivo)
                   VALUES (:est, :g, :ins, :fecha, :ini, :fin, 'Pendiente', :motivo)";
        $stmt = $this->pdo->prepare($sqlIns);
        $stmt->execute([
            'est' => $idEstudiante,
            'g' => $idGrupo,
            'ins' => $idInstructor,
            'fecha' => $fecha,
            'ini' => $horaInicio,
            'fin' => $horaFin,
            'motivo' => $motivo,
        ]);
        return ['ok' => true, 'msg' => 'Solicitud de clase extra enviada. Quedará pendiente de confirmación.'];
    }

    public function cancelarReserva(int $idEstudiante, int $idReserva, string $tipo): array
    {
        if ($tipo === 'cabina') {
            $stmt = $this->pdo->prepare("SELECT estado FROM reserva_cabina WHERE id_reserva = :id AND id_estudiante = :e");
            $stmt->execute(['id' => $idReserva, 'e' => $idEstudiante]);
            $estado = $stmt->fetchColumn();
            if (!$estado) {
                return ['ok' => false, 'msg' => 'Reserva no encontrada.'];
            }
            if (!in_array($estado, ['Pendiente', 'Confirmada'], true)) {
                return ['ok' => false, 'msg' => 'Solo se pueden cancelar reservas pendientes o confirmadas.'];
            }
            $stmt = $this->pdo->prepare("UPDATE reserva_cabina SET estado = 'Cancelada' WHERE id_reserva = :id AND id_estudiante = :e");
            $stmt->execute(['id' => $idReserva, 'e' => $idEstudiante]);
            return ['ok' => true, 'msg' => 'Reserva de cabina cancelada.'];
        }

        if ($tipo === 'extra') {
            $stmt = $this->pdo->prepare("SELECT estado FROM reserva_clase_extra WHERE id_reserva = :id AND id_estudiante = :e");
            $stmt->execute(['id' => $idReserva, 'e' => $idEstudiante]);
            $estado = $stmt->fetchColumn();
            if (!$estado) {
                return ['ok' => false, 'msg' => 'Reserva no encontrada.'];
            }
            if (!in_array($estado, ['Pendiente', 'Confirmada'], true)) {
                return ['ok' => false, 'msg' => 'Solo se pueden cancelar reservas pendientes o confirmadas.'];
            }
            $stmt = $this->pdo->prepare("UPDATE reserva_clase_extra SET estado = 'Cancelada' WHERE id_reserva = :id AND id_estudiante = :e");
            $stmt->execute(['id' => $idReserva, 'e' => $idEstudiante]);
            return ['ok' => true, 'msg' => 'Solicitud de clase extra cancelada.'];
        }

        return ['ok' => false, 'msg' => 'Tipo de reserva inválido.'];
    }
}
