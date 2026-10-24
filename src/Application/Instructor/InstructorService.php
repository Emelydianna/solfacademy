<?php

declare(strict_types=1);

namespace App\Application\Instructor;

use PDO;

final class InstructorService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function dashboard(int $idInstructor): array
    {
        // Grupos asignados
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM grupo WHERE id_instructor = :id AND estado != 'cancelado'");
        $stmt->execute(['id' => $idInstructor]);
        $gruposAsignados = (int) $stmt->fetchColumn();

        // Total alumnos
        $stmt = $this->pdo->prepare("SELECT COUNT(DISTINCT i.id_estudiante) FROM inscripcion i JOIN grupo g ON g.id_grupo = i.id_grupo WHERE g.id_instructor = :id AND i.estado = 'Activa'");
        $stmt->execute(['id' => $idInstructor]);
        $totalAlumnos = (int) $stmt->fetchColumn();

        // Clases esta semana
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM horario h JOIN grupo g ON g.id_grupo = h.id_grupo WHERE g.id_instructor = :id");
        $stmt->execute(['id' => $idInstructor]);
        $clasesSemana = (int) $stmt->fetchColumn();

        // Promedio
        $stmt = $this->pdo->prepare("SELECT COALESCE(AVG(e.calificacion), 0) FROM evaluacion e JOIN inscripcion i ON i.id_inscripcion = e.id_inscripcion JOIN grupo g ON g.id_grupo = i.id_grupo WHERE g.id_instructor = :id AND e.calificacion IS NOT NULL");
        $stmt->execute(['id' => $idInstructor]);
        $promedio = round((float) $stmt->fetchColumn(), 1);

        // Clases de hoy
        $dia = [0=>'Domingo',1=>'Lunes',2=>'Martes',3=>'Miércoles',4=>'Jueves',5=>'Viernes',6=>'Sábado'][(int)date('w')];
        $sqlHoy = "SELECT g.id_grupo, c.nombre AS curso, a.nombre AS aula, h.hora_inicio, h.hora_fin, COUNT(DISTINCT i.id_estudiante) AS alumnos
                   FROM horario h
                   JOIN grupo g ON g.id_grupo = h.id_grupo
                   JOIN curso c ON c.id_curso = g.id_curso
                   JOIN aula a ON a.id_aula = g.id_aula
                   LEFT JOIN inscripcion i ON i.id_grupo = g.id_grupo AND i.estado = 'Activa'
                   WHERE g.id_instructor = :id AND h.dia_semana = :dia AND g.estado != 'cancelado'
                   GROUP BY g.id_grupo, c.nombre, a.nombre, h.hora_inicio, h.hora_fin
                   ORDER BY h.hora_inicio";
        $stmt = $this->pdo->prepare($sqlHoy);
        $stmt->execute(['id' => $idInstructor, 'dia' => $dia]);
        $clasesHoy = $stmt->fetchAll();

        $avisos = [
            ['titulo' => 'Recitales de Fin de Año', 'texto' => 'Se acerca la fecha del recital anual. Revisar inscripciones.'],
            ['titulo' => 'Mantenimiento de Pianos', 'texto' => 'Programado mantenimiento preventivo esta semana.'],
        ];

        return [
            'kpis' => [
                ['titulo' => 'Grupos Asignados', 'valor' => $gruposAsignados . ' Grupos', 'sub' => 'Clásico, Jazz, Ensamble'],
                ['titulo' => 'Total Alumnos', 'valor' => $totalAlumnos . ' Alumnos', 'sub' => 'Todos Activos', 'verde' => true],
                ['titulo' => 'Clases esta Semana', 'valor' => $clasesSemana . ' Clases', 'sub' => 'Programadas'],
                ['titulo' => 'Calificación Promedio', 'valor' => $promedio . ' / 10', 'sub' => 'Rendimiento Excelente', 'verde' => true],
            ],
            'clasesHoy' => $clasesHoy,
            'diaHoy' => $dia,
            'avisos' => $avisos,
        ];
    }

    public function misGrupos(int $idInstructor): array
    {
        $sql = "SELECT g.id_grupo, c.nombre AS curso, cat.nombre AS categoria, c.nivel, a.nombre AS aula,
                       COUNT(DISTINCT i.id_estudiante) AS alumnos,
                       GROUP_CONCAT(DISTINCT h.dia_semana ORDER BY FIELD(h.dia_semana,'Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo') SEPARATOR ', ') AS dias,
                       MIN(h.hora_inicio) AS hora_inicio, MAX(h.hora_fin) AS hora_fin
                FROM grupo g
                JOIN curso c ON c.id_curso = g.id_curso
                JOIN instrumento ins ON ins.id_instrumento = c.id_instrumento
                JOIN categoria cat ON cat.id_categoria = ins.id_categoria
                JOIN aula a ON a.id_aula = g.id_aula
                LEFT JOIN horario h ON h.id_grupo = g.id_grupo
                LEFT JOIN inscripcion i ON i.id_grupo = g.id_grupo AND i.estado = 'Activa'
                WHERE g.id_instructor = :id AND g.estado != 'cancelado'
                GROUP BY g.id_grupo, c.nombre, cat.nombre, c.nivel, a.nombre
                ORDER BY c.nombre";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $idInstructor]);
        return $stmt->fetchAll();
    }

    public function estudiantesGrupo(int $idInstructor, int $idGrupo): array
    {
        $sql = "SELECT e.id_estudiante, e.nombre, e.apellido, e.email, e.telefono,
                       i.fecha AS inscripcion_fecha,
                       (SELECT COALESCE(ROUND(SUM(a.presente='S')/NULLIF(COUNT(*),0)*100,0),0) FROM asistencia a WHERE a.id_inscripcion = i.id_inscripcion) AS asistencia_pct,
                       (SELECT COALESCE(ROUND(AVG(ev.calificacion),1),0) FROM evaluacion ev WHERE ev.id_inscripcion = i.id_inscripcion AND ev.calificacion IS NOT NULL) AS promedio
                FROM inscripcion i
                JOIN estudiante e ON e.id_estudiante = i.id_estudiante
                JOIN grupo g ON g.id_grupo = i.id_grupo
                WHERE g.id_instructor = :ins AND g.id_grupo = :g AND i.estado = 'Activa'
                ORDER BY e.apellido, e.nombre";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['ins' => $idInstructor, 'g' => $idGrupo]);
        $alumnos = $stmt->fetchAll();

        $sqlG = "SELECT g.id_grupo, c.nombre AS curso FROM grupo g JOIN curso c ON c.id_curso = g.id_curso WHERE g.id_instructor = :ins AND g.id_grupo = :g";
        $stmt = $this->pdo->prepare($sqlG);
        $stmt->execute(['ins' => $idInstructor, 'g' => $idGrupo]);
        $grupo = $stmt->fetch();

        return ['grupo' => $grupo, 'alumnos' => $alumnos];
    }

    public function asistenciaPorGrupo(int $idInstructor, int $idGrupo, string $fecha): array
    {
        $sql = "SELECT e.id_estudiante, e.nombre, e.apellido, i.id_inscripcion,
                       a.presente, a.justificacion
                FROM inscripcion i
                JOIN estudiante e ON e.id_estudiante = i.id_estudiante
                JOIN grupo g ON g.id_grupo = i.id_grupo
                LEFT JOIN asistencia a ON a.id_inscripcion = i.id_inscripcion AND a.fecha_clase = :fecha
                WHERE g.id_instructor = :ins AND g.id_grupo = :g AND i.estado = 'Activa'
                ORDER BY e.apellido, e.nombre";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['ins' => $idInstructor, 'g' => $idGrupo, 'fecha' => $fecha]);
        $alumnos = $stmt->fetchAll();

        $sqlG = "SELECT g.id_grupo, c.nombre AS curso FROM grupo g JOIN curso c ON c.id_curso = g.id_curso WHERE g.id_instructor = :ins AND g.id_grupo = :g";
        $stmt = $this->pdo->prepare($sqlG);
        $stmt->execute(['ins' => $idInstructor, 'g' => $idGrupo]);
        $grupo = $stmt->fetch();

        $grupos = $this->misGrupos($idInstructor);

        return ['grupo' => $grupo, 'alumnos' => $alumnos, 'grupos' => $grupos];
    }

    public function guardarAsistencia(int $idInstructor, int $idGrupo, string $fecha, array $asistencias): void
    {
        foreach ($asistencias as $idInscripcion => $estado) {
            $presente = $estado === 'presente' ? 'S' : 'N';
            $justificacion = $estado === 'justificado' ? 'Justificado' : null;
            if ($estado === 'justificado') {
                $presente = 'N';
            }

            $stmt = $this->pdo->prepare("SELECT id_asistencia FROM asistencia WHERE id_inscripcion = :id AND fecha_clase = :fecha");
            $stmt->execute(['id' => $idInscripcion, 'fecha' => $fecha]);
            if ($stmt->fetch()) {
                $stmtU = $this->pdo->prepare("UPDATE asistencia SET presente = :p, justificacion = :j WHERE id_inscripcion = :id AND fecha_clase = :fecha");
                $stmtU->execute(['p' => $presente, 'j' => $justificacion, 'id' => $idInscripcion, 'fecha' => $fecha]);
            } else {
                $stmtI = $this->pdo->prepare("INSERT INTO asistencia (id_inscripcion, fecha_clase, presente, justificacion) VALUES (:id, :fecha, :p, :j)");
                $stmtI->execute(['id' => $idInscripcion, 'fecha' => $fecha, 'p' => $presente, 'j' => $justificacion]);
            }
        }
    }
}
