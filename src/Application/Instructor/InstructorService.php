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
                       (SELECT COALESCE(ROUND(AVG(ev.calificacion),1),0) FROM evaluacion ev WHERE ev.id_inscripcion = i.id_inscripcion AND ev.calificacion IS NOT NULL) AS promedio,
                       (SELECT f.ruta FROM estudiante_foto f WHERE f.id_estudiante = e.id_estudiante ORDER BY f.id_foto DESC LIMIT 1) AS foto
                FROM inscripcion i
                JOIN estudiante e ON e.id_estudiante = i.id_estudiante
                JOIN grupo g ON g.id_grupo = i.id_grupo
                WHERE g.id_instructor = :ins AND g.id_grupo = :g AND i.estado = 'Activa'
                ORDER BY e.apellido, e.nombre";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['ins' => $idInstructor, 'g' => $idGrupo]);
        $alumnos = $stmt->fetchAll();

        $sqlG = "SELECT g.id_grupo, c.nombre AS curso, c.nivel, a.nombre AS aula,
                       GROUP_CONCAT(DISTINCT h.dia_semana ORDER BY FIELD(h.dia_semana,'Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo') SEPARATOR ', ') AS dias,
                       MIN(h.hora_inicio) AS hora_inicio, MAX(h.hora_fin) AS hora_fin
                FROM grupo g
                JOIN curso c ON c.id_curso = g.id_curso
                JOIN aula a ON a.id_aula = g.id_aula
                LEFT JOIN horario h ON h.id_grupo = g.id_grupo
                WHERE g.id_instructor = :ins AND g.id_grupo = :g
                GROUP BY g.id_grupo, c.nombre, c.nivel, a.nombre";
        $stmt = $this->pdo->prepare($sqlG);
        $stmt->execute(['ins' => $idInstructor, 'g' => $idGrupo]);
        $grupo = $stmt->fetch();

        return ['grupo' => $grupo, 'alumnos' => $alumnos];
    }

    public function asistenciaPorGrupo(int $idInstructor, int $idGrupo, string $fecha): array
    {
        $sql = "SELECT e.id_estudiante, e.nombre, e.apellido, i.id_inscripcion,
                       a.presente, a.justificacion,
                       (SELECT f.ruta FROM estudiante_foto f WHERE f.id_estudiante = e.id_estudiante ORDER BY f.id_foto DESC LIMIT 1) AS foto
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

    public function perfilInstructor(int $idInstructor): array
    {
        $stmt = $this->pdo->prepare("SELECT i.* FROM instructor i WHERE i.id_instructor = :id");
        $stmt->execute(['id' => $idInstructor]);
        $perfil = $stmt->fetch() ?: [];

        $stmt = $this->pdo->prepare("SELECT COUNT(DISTINCT g.id_grupo) AS grupos FROM grupo g WHERE g.id_instructor = :id AND g.estado != 'cancelado'");
        $stmt->execute(['id' => $idInstructor]);
        $perfil['grupos_count'] = (int) $stmt->fetchColumn();

        $stmt = $this->pdo->prepare("SELECT COUNT(DISTINCT i2.id_estudiante) AS alumnos FROM inscripcion i2 JOIN grupo g ON g.id_grupo = i2.id_grupo WHERE g.id_instructor = :id AND i2.estado = 'Activa'");
        $stmt->execute(['id' => $idInstructor]);
        $perfil['alumnos_count'] = (int) $stmt->fetchColumn();

        return $perfil;
    }

    public function actualizarPerfilInstructor(int $idInstructor, array $datos): void
    {
        $sql = "UPDATE instructor SET nombre = :nombre, apellido = :apellido, email = :email, telefono = :telefono WHERE id_instructor = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'nombre' => trim((string) ($datos['nombre'] ?? '')),
            'apellido' => trim((string) ($datos['apellido'] ?? '')),
            'email' => trim((string) ($datos['email'] ?? '')) ?: null,
            'telefono' => trim((string) ($datos['telefono'] ?? '')) ?: null,
            'id' => $idInstructor,
        ]);

        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['usuario'])) {
            $nombre = trim(($datos['nombre'] ?? '') . ' ' . ($datos['apellido'] ?? ''));
            if ($nombre !== '') {
                $_SESSION['usuario']['nombre'] = $nombre;
            }
            if (isset($datos['email'])) {
                $_SESSION['usuario']['email'] = trim((string) $datos['email']);
            }
        }
    }

    public function calificacionesPorGrupo(int $idInstructor, int $idGrupo, ?int $idEvaluacion = null): array
    {
        $grupos = $this->misGrupos($idInstructor);

        if ($idGrupo <= 0 && !empty($grupos)) {
            $idGrupo = (int) $grupos[0]['id_grupo'];
        }

        $alumnos = [];
        $evaluaciones = [];
        $grupo = null;

        if ($idGrupo > 0) {
            $sqlE = "SELECT DISTINCT ev.id_evaluacion, ev.tipo, ev.fecha_evaluacion, ev.descripcion
                     FROM evaluacion ev
                     JOIN inscripcion i ON i.id_inscripcion = ev.id_inscripcion
                     JOIN grupo g ON g.id_grupo = i.id_grupo
                     WHERE g.id_instructor = :ins AND g.id_grupo = :g
                     ORDER BY ev.fecha_evaluacion DESC, ev.id_evaluacion DESC";
            $stmt = $this->pdo->prepare($sqlE);
            $stmt->execute(['ins' => $idInstructor, 'g' => $idGrupo]);
            $evaluaciones = $stmt->fetchAll();

            if ($idEvaluacion === null && !empty($evaluaciones)) {
                $idEvaluacion = (int) $evaluaciones[0]['id_evaluacion'];
            }

            $sql = "SELECT i.id_inscripcion, e.id_estudiante, e.nombre, e.apellido,
                           ev.id_evaluacion, ev.calificacion, ev.observaciones_profesor, ev.tipo,
                           (SELECT f.ruta FROM estudiante_foto f WHERE f.id_estudiante = e.id_estudiante ORDER BY f.id_foto DESC LIMIT 1) AS foto
                    FROM inscripcion i
                    JOIN estudiante e ON e.id_estudiante = i.id_estudiante
                    JOIN grupo g ON g.id_grupo = i.id_grupo
                    LEFT JOIN evaluacion ev ON ev.id_inscripcion = i.id_inscripcion" . ($idEvaluacion ? " AND ev.id_evaluacion = :idEv" : "") . "
                    WHERE g.id_instructor = :ins AND g.id_grupo = :g AND i.estado = 'Activa'
                    ORDER BY e.apellido, e.nombre";
            $stmt = $this->pdo->prepare($sql);
            if ($idEvaluacion) {
                $stmt->execute(['ins' => $idInstructor, 'g' => $idGrupo, 'idEv' => $idEvaluacion]);
            } else {
                $stmt->execute(['ins' => $idInstructor, 'g' => $idGrupo]);
            }
            $alumnos = $stmt->fetchAll();

            $sqlG = "SELECT g.id_grupo, c.nombre AS curso, c.nivel FROM grupo g JOIN curso c ON c.id_curso = g.id_curso WHERE g.id_instructor = :ins AND g.id_grupo = :g";
            $stmt = $this->pdo->prepare($sqlG);
            $stmt->execute(['ins' => $idInstructor, 'g' => $idGrupo]);
            $grupo = $stmt->fetch();
        }

        return ['grupos' => $grupos, 'grupo' => $grupo, 'alumnos' => $alumnos, 'evaluaciones' => $evaluaciones, 'idGrupo' => $idGrupo, 'idEvaluacion' => $idEvaluacion];
    }

    public function guardarCalificaciones(int $idInstructor, int $idGrupo, int $idEvaluacion, array $calificaciones, array $observaciones): void
    {
        if ($idEvaluacion <= 0) {
            return;
        }
        foreach ($calificaciones as $idInscripcion => $calif) {
            $califStr = trim((string) $calif);
            if ($califStr === '') {
                continue;
            }
            $califNum = (float) $califStr;
            if ($califNum < 0) {
                $califNum = 0;
            }
            if ($califNum > 100) {
                $califNum = 100;
            }
            $obs = isset($observaciones[$idInscripcion]) ? trim((string) $observaciones[$idInscripcion]) : null;

            $stmt = $this->pdo->prepare("UPDATE evaluacion SET calificacion = :c, observaciones_profesor = :o WHERE id_evaluacion = :idEv AND id_inscripcion = :idIns");
            $stmt->execute(['c' => $califNum / 10, 'o' => $obs !== '' ? $obs : null, 'idEv' => $idEvaluacion, 'idIns' => $idInscripcion]);
        }
    }

    public function reportesInstructor(int $idInstructor): array
    {
        $grupos = $this->misGrupos($idInstructor);

        $asistenciaPorGrupo = [];
        foreach ($grupos as $g) {
            $stmt = $this->pdo->prepare("SELECT COALESCE(ROUND(SUM(a.presente='S')/NULLIF(COUNT(*),0)*100,1),0) AS pct
                                         FROM asistencia a
                                         JOIN inscripcion i ON i.id_inscripcion = a.id_inscripcion
                                         WHERE i.id_grupo = :g AND i.estado = 'Activa'");
            $stmt->execute(['g' => $g['id_grupo']]);
            $asistenciaPorGrupo[] = [
                'grupo' => $g['curso'],
                'pct' => (float) $stmt->fetchColumn(),
            ];
        }

        $stmt = $this->pdo->prepare("SELECT 
                SUM(CASE WHEN e.calificacion >= 9 THEN 1 ELSE 0 END) AS excelente,
                SUM(CASE WHEN e.calificacion >= 7 AND e.calificacion < 9 THEN 1 ELSE 0 END) AS aprobado,
                SUM(CASE WHEN e.calificacion IS NOT NULL AND e.calificacion < 7 THEN 1 ELSE 0 END) AS reprobado
            FROM evaluacion e
            JOIN inscripcion i ON i.id_inscripcion = e.id_inscripcion
            JOIN grupo g ON g.id_grupo = i.id_grupo
            WHERE g.id_instructor = :id");
        $stmt->execute(['id' => $idInstructor]);
        $dist = $stmt->fetch() ?: ['excelente'=>0,'aprobado'=>0,'reprobado'=>0];

        $resumen = [];
        foreach ($grupos as $g) {
            $stmt = $this->pdo->prepare("SELECT COUNT(DISTINCT i.id_estudiante) AS alumnos,
                                               COALESCE(ROUND(AVG(ev.calificacion)*10,1),0) AS promedio_calif,
                                               COALESCE(ROUND(SUM(a.presente='S')/NULLIF(COUNT(DISTINCT a.id_asistencia),0)*100,1),0) AS promedio_asistencia
                                        FROM inscripcion i
                                        LEFT JOIN evaluacion ev ON ev.id_inscripcion = i.id_inscripcion AND ev.calificacion IS NOT NULL
                                        LEFT JOIN asistencia a ON a.id_inscripcion = i.id_inscripcion
                                        WHERE i.id_grupo = :g AND i.estado = 'Activa'");
            $stmt->execute(['g' => $g['id_grupo']]);
            $r = $stmt->fetch() ?: ['alumnos'=>0,'promedio_calif'=>0,'promedio_asistencia'=>0];
            $promCalif10 = (float) $r['promedio_calif'];
            if ($promCalif10 >= 90) {
                $estado = 'Excelente';
                $badge = 'success';
            } elseif ($promCalif10 >= 80) {
                $estado = 'Sobresaliente';
                $badge = 'success';
            } elseif ($promCalif10 >= 70) {
                $estado = 'Bueno';
                $badge = 'info';
            } else {
                $estado = 'Bajo Rendimiento';
                $badge = 'danger';
            }
            $resumen[] = [
                'grupo' => $g['curso'],
                'alumnos' => (int) $r['alumnos'],
                'asistencia' => round((float) $r['promedio_asistencia'], 1),
                'promedio' => round($promCalif10 / 10, 1),
                'estado' => $estado,
                'badge' => $badge,
            ];
        }

        $meses = ['Ago','Sep','Oct','Nov','Dic'];
        $evolucion = [];
        foreach ($meses as $m) {
            $evolucion[] = ['mes' => $m, 'promedio' => round(rand(82, 95) / 10, 1)];
        }

        return [
            'grupos' => $grupos,
            'asistenciaPorGrupo' => $asistenciaPorGrupo,
            'distribucion' => $dist,
            'resumen' => $resumen,
            'evolucion' => $evolucion,
        ];
    }

    public function actualizarFotoInstructor(int $idInstructor, string $foto): void
    {
        $stmt = $this->pdo->prepare("UPDATE instructor SET foto = :foto WHERE id_instructor = :id");
        $stmt->execute(['foto' => $foto, 'id' => $idInstructor]);
    }
}
