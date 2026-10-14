<?php

declare(strict_types=1);

namespace App\Application\Estudiante;

use PDO;

/**
 * Consultas del Portal del Estudiante contra la base de datos real.
 * Cada método alimenta una pantalla del prototipo; cuando se añada una
 * funcionalidad nueva se agrega su consulta aquí.
 */
final class EstudiantePortalService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Datos del Dashboard (/estudiante/dashboard):
     * KPIs, práctica semanal, actividades recientes y recordatorios.
     *
     * @return array<string, mixed>
     */
    public function dashboard(int $idEstudiante): array
    {
        $diaHoy = self::diasSemana()[(int) date('w')];

        return [
            'saludo' => $this->resumenDelDia($idEstudiante, $diaHoy),
            'kpis' => $this->kpisDashboard($idEstudiante, $diaHoy),
            'practica' => $this->practicaSemanal($idEstudiante),
            'actividades' => $this->actividadesRecientes($idEstudiante),
            'recordatorios' => $this->recordatorios($idEstudiante),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resumenDelDia(int $idEstudiante, string $diaHoy): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) AS clases
             FROM inscripcion i
             JOIN grupo g ON g.id_grupo = i.id_grupo
             JOIN horario h ON h.id_grupo = g.id_grupo
             WHERE i.id_estudiante = :id AND i.estado = 'Activa' AND h.dia_semana = :dia"
        );
        $stmt->execute(['id' => $idEstudiante, 'dia' => $diaHoy]);
        $clases = (int) $stmt->fetchColumn();

        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM pago p
             JOIN inscripcion i ON i.id_inscripcion = p.id_inscripcion
             WHERE i.id_estudiante = :id AND p.estado = 'Pendiente'"
        );
        $stmt->execute(['id' => $idEstudiante]);
        $pagosPendientes = (int) $stmt->fetchColumn();

        return [
            'clases_hoy' => $clases,
            'pagos_pendientes' => $pagosPendientes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function kpisDashboard(int $idEstudiante, string $diaHoy): array
    {
        // 1. Cursos inscritos (activos)
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) AS activos,
                    COALESCE(SUM(i.fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)), 0) AS este_mes
             FROM inscripcion i
             WHERE i.id_estudiante = :id AND i.estado = 'Activa'"
        );
        $stmt->execute(['id' => $idEstudiante]);
        $inscritos = $stmt->fetch() ?: ['activos' => 0, 'este_mes' => 0];

        // 2. Próxima clase (la de hoy más temprana, si no hay: null)
        $stmt = $this->pdo->prepare(
            "SELECT c.nombre AS curso, a.nombre AS aula, h.hora_inicio
             FROM inscripcion i
             JOIN grupo g ON g.id_grupo = i.id_grupo
             JOIN curso c ON c.id_curso = g.id_curso
             JOIN aula a ON a.id_aula = g.id_aula
             JOIN horario h ON h.id_grupo = g.id_grupo
             WHERE i.id_estudiante = :id AND i.estado = 'Activa' AND h.dia_semana = :dia
             ORDER BY h.hora_inicio
             LIMIT 1"
        );
        $stmt->execute(['id' => $idEstudiante, 'dia' => $diaHoy]);
        $proximaClase = $stmt->fetch() ?: null;

        // 3. Horas completadas (clases con asistencia "Presente")
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(hor.dur) / 3600, 0) AS horas, COUNT(*) AS clases
             FROM asistencia ase
             JOIN inscripcion i ON i.id_inscripcion = ase.id_inscripcion
             JOIN grupo g ON g.id_grupo = i.id_grupo
             JOIN (SELECT id_grupo, MIN(TIME_TO_SEC(hora_fin) - TIME_TO_SEC(hora_inicio)) AS dur
                   FROM horario GROUP BY id_grupo) hor ON hor.id_grupo = g.id_grupo
             WHERE i.id_estudiante = :id AND ase.presente = 'S'"
        );
        $stmt->execute(['id' => $idEstudiante]);
        $horas = $stmt->fetch() ?: ['horas' => 0, 'clases' => 0];

        // 4. Promedio general de evaluaciones
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(AVG(e.calificacion), 0) AS promedio, COUNT(*) AS total
             FROM evaluacion e
             JOIN inscripcion i ON i.id_inscripcion = e.id_inscripcion
             WHERE i.id_estudiante = :id"
        );
        $stmt->execute(['id' => $idEstudiante]);
        $promedio = $stmt->fetch() ?: ['promedio' => 0, 'total' => 0];

        return [
            'cursos_inscritos' => (int) $inscritos['activos'],
            'cursos_este_mes' => (int) $inscritos['este_mes'],
            'proxima_clase' => $proximaClase,
            'horas_completadas' => (float) $horas['horas'],
            'clases_asistidas' => (int) $horas['clases'],
            'promedio' => (float) $promedio['promedio'],
            'evaluaciones_totales' => (int) $promedio['total'],
        ];
    }

    /**
     * Práctica individual de los últimos 7 días, Lun..Dom.
     *
     * @return array<int, array<string, mixed>>
     */
    private function practicaSemanal(int $idEstudiante): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT fecha, horas FROM practica_estudiante
             WHERE id_estudiante = :id AND fecha >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)'
        );
        $stmt->execute(['id' => $idEstudiante]);

        $porFecha = [];
        foreach ($stmt->fetchAll() as $fila) {
            $porFecha[$fila['fecha']] = (float) $fila['horas'];
        }

        $dias = self::diasSemana();
        $semana = [];

        for ($i = 6; $i >= 0; $i--) {
            $fecha = date('Y-m-d', strtotime("-{$i} days"));
            $dia = $dias[(int) date('w', strtotime($fecha))];

            $semana[] = [
                'dia' => mb_substr($dia, 0, 3),
                'horas' => $porFecha[$fecha] ?? 0.0,
            ];
        }

        return $semana;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function actividadesRecientes(int $idEstudiante): array
    {
        $sql = "(SELECT 'pago' AS tipo, p.fecha_pago AS fecha,
                        CONCAT('Pago registrado: ', p.concepto, ' — ', p.monto_pagado, '€') AS detalle
                 FROM pago p
                 JOIN inscripcion i ON i.id_inscripcion = p.id_inscripcion
                 WHERE i.id_estudiante = :id_pagos AND p.estado = 'Pagado'
                 ORDER BY p.fecha_pago DESC LIMIT 2)
                UNION ALL
                (SELECT 'asistencia', ase.fecha_clase,
                        CONCAT('Asistencia ', IF(ase.presente = 'S', 'confirmada', 'ausente'), ': ', c.nombre)
                 FROM asistencia ase
                 JOIN inscripcion i ON i.id_inscripcion = ase.id_inscripcion
                 JOIN grupo g ON g.id_grupo = i.id_grupo
                 JOIN curso c ON c.id_curso = g.id_curso
                 WHERE i.id_estudiante = :id_asistencias
                 ORDER BY ase.fecha_clase DESC LIMIT 3)
                UNION ALL
                (SELECT 'inscripcion', i.fecha, CONCAT('Nueva inscripción: ', c.nombre)
                 FROM inscripcion i
                 JOIN grupo g ON g.id_grupo = i.id_grupo
                 JOIN curso c ON c.id_curso = g.id_curso
                 WHERE i.id_estudiante = :id_inscripciones
                 ORDER BY i.fecha DESC LIMIT 1)
                ORDER BY fecha DESC
                LIMIT 5";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'id_pagos' => $idEstudiante,
            'id_asistencias' => $idEstudiante,
            'id_inscripciones' => $idEstudiante,
        ]);

        return $stmt->fetchAll();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recordatorios(int $idEstudiante): array
    {
        $recordatorios = [];

        // Próximo pago pendiente
        $stmt = $this->pdo->prepare(
            "SELECT p.concepto, p.fecha_pago, p.monto_pagado, c.nombre AS curso
             FROM pago p
             JOIN inscripcion i ON i.id_inscripcion = p.id_inscripcion
             JOIN grupo g ON g.id_grupo = i.id_grupo
             JOIN curso c ON c.id_curso = g.id_curso
             WHERE i.id_estudiante = :id AND p.estado = 'Pendiente'
             ORDER BY p.fecha_pago
             LIMIT 1"
        );
        $stmt->execute(['id' => $idEstudiante]);

        if ($pago = $stmt->fetch()) {
            $fechaPago = strtotime((string) $pago['fecha_pago']);
            $verbo = $fechaPago < strtotime('today') ? 'Venció el' : 'Vence el';

            $recordatorios[] = [
                'icono' => 'bi-credit-card',
                'titulo' => $pago['concepto'],
                'texto' => $verbo . ' ' . date('d/m', $fechaPago)
                    . ' · ' . number_format((float) $pago['monto_pagado'], 2, ',', '.') . '€ — ' . $pago['curso'],
            ];
        }

        // Próxima finalización de curso activo
        $stmt = $this->pdo->prepare(
            "SELECT c.nombre, g.fecha_fin
             FROM inscripcion i
             JOIN grupo g ON g.id_grupo = i.id_grupo
             JOIN curso c ON c.id_curso = g.id_curso
             WHERE i.id_estudiante = :id AND i.estado = 'Activa' AND g.fecha_fin >= CURDATE()
             ORDER BY g.fecha_fin
             LIMIT 1"
        );
        $stmt->execute(['id' => $idEstudiante]);

        if ($curso = $stmt->fetch()) {
            $recordatorios[] = [
                'icono' => 'bi-music-note-beamed',
                'titulo' => 'Finaliza: ' . $curso['nombre'],
                'texto' => 'Presentación final el ' . date('d/m/Y', strtotime((string) $curso['fecha_fin'])),
            ];
        }

        return $recordatorios;
    }

    /**
     * Nombres de días en español indexados por date('w') (0 = domingo).
     *
     * @return array<int, string>
     */
    private static function diasSemana(): array
    {
        return [
            0 => 'Domingo',
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
        ];
    }
}
