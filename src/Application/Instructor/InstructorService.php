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

        // Cursos distintos que imparte (subtítulo del KPI)
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(DISTINCT g.id_curso) FROM grupo g
             WHERE g.id_instructor = :id AND g.estado != \'cancelado\''
        );
        $stmt->execute(['id' => $idInstructor]);
        $cursosImpartidos = (int) $stmt->fetchColumn();

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

        // Evaluaciones creadas que aún no tienen nota
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM evaluacion e
             JOIN inscripcion i ON i.id_inscripcion = e.id_inscripcion
             JOIN grupo g ON g.id_grupo = i.id_grupo
             WHERE g.id_instructor = :id AND e.calificacion IS NULL'
        );
        $stmt->execute(['id' => $idInstructor]);
        $sinCalificar = (int) $stmt->fetchColumn();

        $subPromedio = match (true) {
            $sinCalificar > 0 => $sinCalificar . ' evaluación(es) sin calificar',
            $promedio >= 9    => 'Rendimiento excelente',
            $promedio >= 7    => 'Rendimiento bueno',
            $promedio > 0     => 'Rendimiento por debajo',
            default           => 'Sin evaluaciones calificadas',
        };

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

        $avisos = [];

        return [
            'kpis' => [
                [
                    'titulo' => 'Grupos Asignados',
                    'valor'  => $gruposAsignados . ' Grupos',
                    'sub'    => $cursosImpartidos . ($cursosImpartidos === 1 ? ' Curso' : ' Cursos'),
                ],
                [
                    'titulo' => 'Total Alumnos',
                    'valor'  => $totalAlumnos . ' Alumnos',
                    'sub'    => 'Con inscripción activa',
                    'verde'  => $totalAlumnos > 0,
                ],
                [
                    'titulo' => 'Clases esta Semana',
                    'valor'  => $clasesSemana . ' Clases',
                    'sub'    => 'Franjas programadas',
                ],
                [
                    'titulo' => 'Calificación Promedio',
                    'valor'  => $promedio . ' / 10',
                    'sub'    => $subPromedio,
                    'verde'  => $promedio >= 7,
                ],
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

    /**
     * Ficha académica completa de un alumno VISTO DESDE el instructor.
     *
     * Devuelve null si el alumno no está inscrito en ningún grupo del
     * instructor (evita que un profesor consulte fichas ajenas).
     *
     * @return array<string, mixed>|null
     */
    public function fichaAlumno(int $idInstructor, int $idEstudiante): ?array
    {
        if ($idInstructor <= 0 || $idEstudiante <= 0) {
            return null;
        }

        // 1) Datos básicos, solo si el alumno es de algún grupo del instructor
        $sql = "SELECT e.id_estudiante, e.nombre, e.apellido, e.email, e.telefono,
                       e.direccion, e.fecha_inscripcion,
                       (SELECT f.ruta FROM estudiante_foto f WHERE f.id_estudiante = e.id_estudiante ORDER BY f.id_foto DESC LIMIT 1) AS foto
                FROM estudiante e
                WHERE e.id_estudiante = :e
                  AND EXISTS (SELECT 1 FROM inscripcion i
                              JOIN grupo g ON g.id_grupo = i.id_grupo
                              WHERE i.id_estudiante = e.id_estudiante AND g.id_instructor = :i)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['e' => $idEstudiante, 'i' => $idInstructor]);
        $estudiante = $stmt->fetch();

        if ($estudiante === false) {
            return null;
        }

        // 2) Matrículas del alumno en MIS grupos (con % de asistencia y promedio)
        $sqlM = "SELECT i.id_inscripcion, i.fecha AS fecha_inscripcion, i.estado,
                        i.precio_inscripcion, i.observaciones,
                        g.id_grupo, c.nombre AS curso, c.nivel, a.nombre AS aula,
                        g.fecha_inicio, g.fecha_fin,
                        (SELECT COALESCE(ROUND(SUM(x.presente = 'S') / NULLIF(COUNT(*), 0) * 100, 0), 0)
                         FROM asistencia x WHERE x.id_inscripcion = i.id_inscripcion) AS asistencia_pct,
                        (SELECT COUNT(*) FROM asistencia x WHERE x.id_inscripcion = i.id_inscripcion) AS asistencias_total,
                        (SELECT COALESCE(ROUND(AVG(ev.calificacion), 1), 0)
                         FROM evaluacion ev WHERE ev.id_inscripcion = i.id_inscripcion AND ev.calificacion IS NOT NULL) AS promedio,
                        (SELECT COUNT(*) FROM evaluacion ev WHERE ev.id_inscripcion = i.id_inscripcion AND ev.calificacion IS NULL) AS sin_nota,
                        (SELECT COUNT(*) FROM evaluacion ev WHERE ev.id_inscripcion = i.id_inscripcion) AS evaluaciones_total
                 FROM inscripcion i
                 JOIN grupo g ON g.id_grupo = i.id_grupo
                 JOIN curso c ON c.id_curso = g.id_curso
                 LEFT JOIN aula a ON a.id_aula = g.id_aula
                 WHERE i.id_estudiante = :e AND g.id_instructor = :i
                 ORDER BY (i.estado = 'Activa') DESC, i.fecha DESC";
        $stmt = $this->pdo->prepare($sqlM);
        $stmt->execute(['e' => $idEstudiante, 'i' => $idInstructor]);
        $matriculas = $stmt->fetchAll();

        // 3) Últimas evaluaciones
        $sqlE = "SELECT ev.tipo, ev.fecha_evaluacion, ev.calificacion, ev.descripcion,
                        ev.observaciones_profesor, c.nombre AS curso
                 FROM evaluacion ev
                 JOIN inscripcion i ON i.id_inscripcion = ev.id_inscripcion
                 JOIN grupo g ON g.id_grupo = i.id_grupo
                 JOIN curso c ON c.id_curso = g.id_curso
                 WHERE i.id_estudiante = :e AND g.id_instructor = :i
                 ORDER BY ev.fecha_evaluacion IS NULL, ev.fecha_evaluacion DESC, ev.id_evaluacion DESC
                 LIMIT 15";
        $stmt = $this->pdo->prepare($sqlE);
        $stmt->execute(['e' => $idEstudiante, 'i' => $idInstructor]);
        $evaluaciones = $stmt->fetchAll();

        // 4) Últimos registros de asistencia
        $sqlA = "SELECT x.fecha_clase, x.presente, x.justificacion, c.nombre AS curso
                 FROM asistencia x
                 JOIN inscripcion i ON i.id_inscripcion = x.id_inscripcion
                 JOIN grupo g ON g.id_grupo = i.id_grupo
                 JOIN curso c ON c.id_curso = g.id_curso
                 WHERE i.id_estudiante = :e AND g.id_instructor = :i
                 ORDER BY x.fecha_clase DESC
                 LIMIT 10";
        $stmt = $this->pdo->prepare($sqlA);
        $stmt->execute(['e' => $idEstudiante, 'i' => $idInstructor]);
        $asistencias = $stmt->fetchAll();

        // 5) Resumen general (solo matrículas activas)
        $activas = array_filter($matriculas, static fn (array $m): bool => $m['estado'] === 'Activa');

        $sumaPromedios = 0.0;
        $conNota = 0;
        foreach ($activas as $m) {
            if ((float) $m['promedio'] > 0) {
                $sumaPromedios += (float) $m['promedio'];
                $conNota++;
            }
        }

        $totalEvaluaciones = array_sum(array_column($activas, 'evaluaciones_total'));
        $totalSinNota = array_sum(array_column($activas, 'sin_nota'));

        return [
            'estudiante'  => $estudiante,
            'matriculas'  => $matriculas,
            'evaluaciones'=> $evaluaciones,
            'asistencias' => $asistencias,
            'resumen'     => [
                'grupos_activos'        => count($activas),
                'grupos_totales'        => count($matriculas),
                'promedio_general'      => $conNota > 0 ? round($sumaPromedios / $conNota, 1) : null,
                'evaluaciones_pendientes'=> $totalSinNota,
                'evaluaciones_totales'  => $totalEvaluaciones,
            ],
        ];
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

    /**
     * Guarda la asistencia del día para un grupo del instructor.
     *
     * Se valida que el grupo sea del instructor Y que cada inscripción
     * enviada pertenezca a ese grupo: sin eso, un profesor podría
     * modificar asistencias de matrículas ajenas.
     */
    public function guardarAsistencia(int $idInstructor, int $idGrupo, string $fecha, array $asistencias): void
    {
        if (!$this->grupoPerteneceInstructor($idInstructor, $idGrupo)) {
            return;
        }

        // Inscripciones realmente válidas de ese grupo
        $stmt = $this->pdo->prepare(
            "SELECT id_inscripcion FROM inscripcion WHERE id_grupo = :g AND estado = 'Activa'"
        );
        $stmt->execute(['g' => $idGrupo]);
        $validas = array_flip(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)));

        foreach ($asistencias as $idInscripcion => $estado) {
            $idIns = (int) $idInscripcion;
            if (!isset($validas[$idIns])) {
                continue;
            }

            $presente = $estado === 'presente' ? 'S' : 'N';
            $justificacion = $estado === 'justificado' ? 'Justificado' : null;
            if ($estado === 'justificado') {
                $presente = 'N';
            }

            $stmt = $this->pdo->prepare("SELECT id_asistencia FROM asistencia WHERE id_inscripcion = :id AND fecha_clase = :fecha");
            $stmt->execute(['id' => $idIns, 'fecha' => $fecha]);
            if ($stmt->fetch()) {
                $stmtU = $this->pdo->prepare("UPDATE asistencia SET presente = :p, justificacion = :j WHERE id_inscripcion = :id AND fecha_clase = :fecha");
                $stmtU->execute(['p' => $presente, 'j' => $justificacion, 'id' => $idIns, 'fecha' => $fecha]);
            } else {
                $stmtI = $this->pdo->prepare("INSERT INTO asistencia (id_inscripcion, fecha_clase, presente, justificacion) VALUES (:id, :fecha, :p, :j)");
                $stmtI->execute(['id' => $idIns, 'fecha' => $fecha, 'p' => $presente, 'j' => $justificacion]);
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

    /**
     * Verifica que un grupo concreto pertenezca al instructor.
     * Se usa como guardia de seguridad en todas las operaciones sobre
     * un id de grupo recibido por URL o formulario.
     */
    public function grupoPerteneceInstructor(int $idInstructor, int $idGrupo): bool
    {
        if ($idInstructor <= 0 || $idGrupo <= 0) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM grupo WHERE id_grupo = :g AND id_instructor = :i'
        );
        $stmt->execute(['g' => $idGrupo, 'i' => $idInstructor]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Evaluaciones distintas de un grupo.
     *
     * OJO: `evaluacion` guarda una fila POR INSCRIPCIÓN, no por grupo.
     * Por eso se agrupa por el par (tipo, fecha_evaluacion): es lo que
     * identifica "la misma evaluación" para todos los alumnos del grupo.
     *
     * @return array<int, array<string, mixed>>
     */
    public function evaluacionesGrupo(int $idInstructor, int $idGrupo): array
    {
        $sql = "SELECT ev.tipo, ev.fecha_evaluacion
                FROM evaluacion ev
                JOIN inscripcion i ON i.id_inscripcion = ev.id_inscripcion
                JOIN grupo g ON g.id_grupo = i.id_grupo
                WHERE g.id_instructor = :ins AND g.id_grupo = :g
                GROUP BY ev.tipo, ev.fecha_evaluacion
                ORDER BY ev.fecha_evaluacion IS NULL, ev.fecha_evaluacion DESC, ev.tipo";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['ins' => $idInstructor, 'g' => $idGrupo]);
        return $stmt->fetchAll();
    }

    /**
     * Crea una evaluación para TODAS las inscripciones activas de un grupo.
     *
     * Se inserta una fila por matrícula con `calificacion = NULL`
     * (la columna es anulable a propósito: la crea el profesor y nota
     * después). Devuelve ['ok' => bool, 'msg' => string].
     *
     * @param array<string, mixed> $datos tipo, fecha_evaluacion, descripcion
     * @return array{ok: bool, msg: string}
     */
    public function crearEvaluacion(int $idInstructor, int $idGrupo, array $datos): array
    {
        $tipo = trim((string) ($datos['tipo'] ?? ''));
        $fecha = trim((string) ($datos['fecha_evaluacion'] ?? ''));
        $fecha = $fecha !== '' ? $fecha : null;
        $descripcion = trim((string) ($datos['descripcion'] ?? ''));
        $descripcion = $descripcion !== '' ? $descripcion : null;

        if ($tipo === '' || mb_strlen($tipo) > 50) {
            return ['ok' => false, 'msg' => 'El tipo de evaluación es obligatorio (máx. 50 caracteres).'];
        }

        if ($fecha !== null && !$this->esFechaValida($fecha)) {
            return ['ok' => false, 'msg' => 'La fecha de evaluación no es válida.'];
        }

        if (!$this->grupoPerteneceInstructor($idInstructor, $idGrupo)) {
            return ['ok' => false, 'msg' => 'Ese grupo no pertenece a tu cuenta.'];
        }

        // Inscripciones activas del grupo: sobre ellas se crea la evaluación
        $stmt = $this->pdo->prepare(
            "SELECT id_inscripcion FROM inscripcion WHERE id_grupo = :g AND estado = 'Activa' ORDER BY id_inscripcion"
        );
        $stmt->execute(['g' => $idGrupo]);
        $inscripciones = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if ($inscripciones === []) {
            return ['ok' => false, 'msg' => 'El grupo no tiene inscripciones activas.'];
        }

        // Un grupo no puede tener dos veces el mismo (tipo, fecha)
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM evaluacion ev
             JOIN inscripcion i ON i.id_inscripcion = ev.id_inscripcion
             WHERE i.id_grupo = :g AND ev.tipo = :tipo AND (ev.fecha_evaluacion <=> :fecha)'
        );
        $stmt->execute(['g' => $idGrupo, 'tipo' => $tipo, 'fecha' => $fecha]);
        if ((int) $stmt->fetchColumn() > 0) {
            return ['ok' => false, 'msg' => 'Ya existe una evaluación con ese tipo y fecha en este grupo.'];
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO evaluacion (id_inscripcion, tipo, fecha_evaluacion, descripcion)
             VALUES (:id, :tipo, :fecha, :descripcion)'
        );

        $this->pdo->beginTransaction();
        try {
            foreach ($inscripciones as $idInscripcion) {
                $insert->execute([
                    'id'          => (int) $idInscripcion,
                    'tipo'        => $tipo,
                    'fecha'       => $fecha,
                    'descripcion' => $descripcion,
                ]);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        $total = count($inscripciones);

        return [
            'ok'  => true,
            'msg' => sprintf('Evaluación «%s» creada para %d alumno(s). Ya puedes registrar las notas.', $tipo, $total),
        ];
    }

    /**
     * Notas de una evaluación concreta de un grupo, identificada por
     * (tipo, fecha). El parámetro $idGrupo puede ser 0 para tomar el
     * primero de los grupos del instructor.
     *
     * @return array{grupos: array, grupo: array|null, alumnos: array, evaluaciones: array, idGrupo: int, tipo: string|null, fecha: string|null}
     */
    public function calificacionesPorGrupo(int $idInstructor, int $idGrupo, ?string $tipo = null, ?string $fecha = null): array
    {
        $grupos = $this->misGrupos($idInstructor);

        if ($idGrupo <= 0 && $grupos !== []) {
            $idGrupo = (int) $grupos[0]['id_grupo'];
        }

        $tipo = $tipo !== null ? trim($tipo) : null;
        $tipo = $tipo === '' ? null : $tipo;
        $fecha = $fecha !== null ? trim($fecha) : null;
        $fecha = $fecha === '' ? null : $fecha;

        $alumnos = [];
        $evaluaciones = [];
        $grupo = null;

        if ($idGrupo > 0) {
            if (!$this->grupoPerteneceInstructor($idInstructor, $idGrupo)) {
                return [
                    'grupos' => $grupos, 'grupo' => null, 'alumnos' => [],
                    'evaluaciones' => [], 'idGrupo' => 0, 'tipo' => null, 'fecha' => null,
                ];
            }

            $evaluaciones = $this->evaluacionesGrupo($idInstructor, $idGrupo);

            // Si no viene evaluación (o ya no existe en el grupo), tomamos la primera
            $coincide = $tipo !== null && $this->existeEvaluacion($evaluaciones, $tipo, $fecha);
            if (!$coincide) {
                $primera = $evaluaciones[0] ?? null;
                $tipo = $primera['tipo'] ?? null;
                $fecha = $primera['fecha_evaluacion'] ?? null;
            }

            if ($tipo !== null) {
                $sql = "SELECT i.id_inscripcion, e.id_estudiante, e.nombre, e.apellido,
                               ev.id_evaluacion, ev.calificacion, ev.observaciones_profesor, ev.tipo,
                               (SELECT f.ruta FROM estudiante_foto f WHERE f.id_estudiante = e.id_estudiante ORDER BY f.id_foto DESC LIMIT 1) AS foto
                        FROM inscripcion i
                        JOIN estudiante e ON e.id_estudiante = i.id_estudiante
                        JOIN grupo g ON g.id_grupo = i.id_grupo
                        LEFT JOIN evaluacion ev ON ev.id_inscripcion = i.id_inscripcion
                                                  AND ev.tipo = :tipo AND (ev.fecha_evaluacion <=> :fecha)
                        WHERE g.id_instructor = :ins AND g.id_grupo = :g AND i.estado = 'Activa'
                        ORDER BY e.apellido, e.nombre";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute(['ins' => $idInstructor, 'g' => $idGrupo, 'tipo' => $tipo, 'fecha' => $fecha]);
                $alumnos = $stmt->fetchAll();
            }

            $sqlG = "SELECT g.id_grupo, c.nombre AS curso, c.nivel FROM grupo g JOIN curso c ON c.id_curso = g.id_curso WHERE g.id_instructor = :ins AND g.id_grupo = :g";
            $stmt = $this->pdo->prepare($sqlG);
            $stmt->execute(['ins' => $idInstructor, 'g' => $idGrupo]);
            $grupo = $stmt->fetch() ?: null;
        }

        return [
            'grupos'       => $grupos,
            'grupo'        => $grupo,
            'alumnos'      => $alumnos,
            'evaluaciones' => $evaluaciones,
            'idGrupo'      => $idGrupo,
            'tipo'         => $tipo,
            'fecha'        => $fecha,
        ];
    }

    /**
     * Guarda las notas de la evaluación (tipo, fecha) de un grupo.
     *
     * Recibe notas en escala 0-100 (como las muestra el formulario) y las
     * convierte a la escala 0-10 de la base de datos. Solo se actualizan
     * inscripciones cuya evaluación exista realmente para ese (tipo, fecha),
     * de modo que no se pierden datos en silencio.
     *
     * @param array<string, mixed> $calificaciones   id_inscripcion => nota 0-100
     * @param array<string, mixed> $observaciones    id_inscripcion => texto
     */
    public function guardarCalificaciones(int $idInstructor, int $idGrupo, string $tipo, ?string $fecha, array $calificaciones, array $observaciones): bool
    {
        $tipo = trim($tipo);
        if ($tipo === '' || !$this->grupoPerteneceInstructor($idInstructor, $idGrupo)) {
            return false;
        }

        // Mapa id_inscripcion => id_evaluacion de la evaluación concreta
        $sql = 'SELECT i.id_inscripcion, ev.id_evaluacion
                FROM evaluacion ev
                JOIN inscripcion i ON i.id_inscripcion = ev.id_inscripcion
                WHERE i.id_grupo = :g AND ev.tipo = :tipo AND (ev.fecha_evaluacion <=> :fecha)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['g' => $idGrupo, 'tipo' => $tipo, 'fecha' => $fecha]);
        $mapa = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        if ($mapa === []) {
            return false;
        }

        $update = $this->pdo->prepare(
            'UPDATE evaluacion SET calificacion = :c, observaciones_profesor = :o WHERE id_evaluacion = :idEv'
        );

        foreach ($calificaciones as $idInscripcion => $calif) {
            $idIns = (int) $idInscripcion;

            if (!isset($mapa[$idIns])) {
                continue; // inscripción ajena a este grupo/evaluación
            }

            $califStr = trim((string) $calif);
            if ($califStr === '') {
                continue; // vacío = no tocar la nota existente
            }

            if (!is_numeric($califStr)) {
                continue;
            }

            $califNum = (float) $califStr;
            $califNum = max(0.0, min(100.0, $califNum));

            $obs = isset($observaciones[$idIns]) ? trim((string) $observaciones[$idIns]) : null;

            $update->execute([
                'c'     => $califNum / 10,
                'o'     => $obs !== '' ? $obs : null,
                'idEv'  => $mapa[$idIns],
            ]);
        }

        return true;
    }

    /**
     * ¿Existe la evaluación (tipo, fecha) en la lista del grupo?
     *
     * @param array<int, array<string, mixed>> $evaluaciones
     */
    private function existeEvaluacion(array $evaluaciones, string $tipo, ?string $fecha): bool
    {
        foreach ($evaluaciones as $ev) {
            if ((string) $ev['tipo'] !== $tipo) {
                continue;
            }
            $f = $ev['fecha_evaluacion'] !== null ? (string) $ev['fecha_evaluacion'] : null;
            if ($f === $fecha) {
                return true;
            }
        }

        return false;
    }

    private function esFechaValida(string $fecha): bool
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) !== 1) {
            return false;
        }

        [$anio, $mes, $dia] = array_map('intval', explode('-', $fecha));

        return checkdate($mes, $dia, $anio);
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

        // Evolución real de los últimos 5 meses (promedio de las notas
        // con fecha de evaluación de los grupos del instructor).
        $mesesNombres = [
            1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr', 5 => 'May', 6 => 'Jun',
            7 => 'Jul', 8 => 'Ago', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic',
        ];

        $sqlMes = "SELECT YEAR(ev.fecha_evaluacion) AS anio,
                          MONTH(ev.fecha_evaluacion) AS mes,
                          ROUND(AVG(ev.calificacion) * 10, 1) AS promedio
                   FROM evaluacion ev
                   JOIN inscripcion i ON i.id_inscripcion = ev.id_inscripcion
                   JOIN grupo g ON g.id_grupo = i.id_grupo
                   WHERE g.id_instructor = :id
                     AND ev.calificacion IS NOT NULL
                     AND ev.fecha_evaluacion IS NOT NULL
                     AND ev.fecha_evaluacion >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 4 MONTH), '%Y-%m-01')
                   GROUP BY YEAR(ev.fecha_evaluacion), MONTH(ev.fecha_evaluacion)
                   ORDER BY anio, mes";
        $stmt = $this->pdo->prepare($sqlMes);
        $stmt->execute(['id' => $idInstructor]);
        $porMes = [];
        foreach ($stmt->fetchAll() as $fila) {
            $porMes[(int) $fila['anio'] . '-' . (int) $fila['mes']] = (float) $fila['promedio'];
        }

        // Serie continua de los últimos 5 meses; los meses sin datos
        // quedan en null para que la gráfica los omita.
        $evolucion = [];
        for ($i = 4; $i >= 0; $i--) {
            $ts = date_create('first day of this month');
            $ts->modify('-' . $i . ' months');
            $clave = $ts->format('Y') . '-' . (int) $ts->format('n');
            $evolucion[] = [
                'mes'      => $mesesNombres[(int) $ts->format('n')],
                'promedio' => $porMes[$clave] ?? null,
            ];
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

    /** Ruta pública de la foto del instructor; null si todavía no tiene. */
    public function fotoInstructor(int $idInstructor): ?string
    {
        $stmt = $this->pdo->prepare("SELECT foto FROM instructor WHERE id_instructor = :id");
        $stmt->execute(['id' => $idInstructor]);
        $foto = (string) ($stmt->fetchColumn() ?: '');

        return $foto !== '' ? $foto : null;
    }
}
