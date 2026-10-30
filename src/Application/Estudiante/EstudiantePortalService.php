<?php

declare(strict_types=1);

namespace App\Application\Estudiante;

use App\Application\Formato\Dinero;
use App\Application\Pagos\ComprobanteStorage;
use PDO;
use PDOException;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Consultas del Portal del Estudiante contra la base de datos real.
 * Cada método alimenta una pantalla del prototipo; cuando se añada una
 * funcionalidad nueva se agrega su consulta aquí.
 */
final class EstudiantePortalService
{
    /** Métodos de pago aceptados al enviar un comprobante. */
    public const METODOS_PAGO = ['Efectivo', 'Transferencia bancaria', 'Tarjeta de crédito', 'PayPal'];

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
     * Mis cursos (/estudiante/cursos): todas las inscripciones del
     * estudiante con curso, categoría, instructor, aula, fechas, horario
     * semanal y porcentaje de avance (% de asistencia).
     *
     * @return array<int, array<string, mixed>>
     */
    public function misCursos(int $idEstudiante): array
    {
        $sql = "SELECT i.id_inscripcion,
                       i.estado,
                       i.fecha AS fecha_inscripcion,
                       i.precio_inscripcion,
                       i.observaciones,
                       i.motivo_revision,
                       g.id_grupo,
                       g.fecha_inicio,
                       g.fecha_fin,
                       g.estado AS estado_grupo,
                       g.capacidad_maxima,
                       c.id_curso,
                       c.nombre AS curso,
                       c.nivel,
                       c.descripcion,
                       c.duracion_semanas,
                       c.precio,
                       cat.nombre AS categoria,
                       a.nombre AS aula,
                       a.ubicacion,
                       CONCAT(inst.nombre, ' ', inst.apellido) AS instructor,
                       inst.email AS instructor_email
                FROM inscripcion i
                JOIN grupo g ON g.id_grupo = i.id_grupo
                JOIN curso c ON c.id_curso = g.id_curso
                JOIN instrumento ins ON ins.id_instrumento = c.id_instrumento
                JOIN categoria cat ON cat.id_categoria = ins.id_categoria
                JOIN aula a ON a.id_aula = g.id_aula
                JOIN instructor inst ON inst.id_instructor = g.id_instructor
                WHERE i.id_estudiante = :id
                ORDER BY FIELD(i.estado, 'Activa', 'Pendiente', 'Finalizada', 'Cancelada'),
                         g.fecha_inicio DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $idEstudiante]);
        $inscripciones = $stmt->fetchAll();

        if ($inscripciones === []) {
            return [];
        }

        // Horarios de todos los grupos involucrados, en una sola consulta
        $idsGrupo = array_map(static fn (array $fila): int => (int) $fila['id_grupo'], $inscripciones);
        $placeholders = implode(',', array_fill(0, count($idsGrupo), '?'));

        $stmtH = $this->pdo->prepare(
            "SELECT id_grupo, dia_semana, hora_inicio, hora_fin
             FROM horario
             WHERE id_grupo IN ($placeholders)
             ORDER BY FIELD(dia_semana, 'Lunes', 'Martes', 'Miércoles', 'Jueves',
                            'Viernes', 'Sábado', 'Domingo'), hora_inicio"
        );
        $stmtH->execute($idsGrupo);

        $horarios = [];
        foreach ($stmtH->fetchAll() as $fila) {
            $horarios[(int) $fila['id_grupo']][] = $fila;
        }

        // Asistencia por inscripción -> porcentaje de avance de la tarjeta
        // (la vista v_asistencia_estudiante ya agrupa por inscripción).
        $idsInscripcion = array_map(static fn (array $fila): int => (int) $fila['id_inscripcion'], $inscripciones);
        $placeholdersIns = implode(',', array_fill(0, count($idsInscripcion), '?'));

        $stmtA = $this->pdo->prepare(
            "SELECT id_inscripcion,
                    total_clases AS clases_registradas,
                    presentes AS clases_presentes
             FROM v_asistencia_estudiante
             WHERE id_inscripcion IN ($placeholdersIns)"
        );
        $stmtA->execute($idsInscripcion);

        $asistencias = [];
        foreach ($stmtA->fetchAll() as $fila) {
            $asistencias[(int) $fila['id_inscripcion']] = $fila;
        }

        foreach ($inscripciones as &$inscripcion) {
            $inscripcion['horarios'] = $horarios[(int) $inscripcion['id_grupo']] ?? [];

            $asis = $asistencias[(int) $inscripcion['id_inscripcion']] ?? null;
            $total = $asis === null ? 0 : (int) $asis['clases_registradas'];
            $presentes = $asis === null ? 0 : (int) $asis['clases_presentes'];

            $inscripcion['clases_registradas'] = $total;
            $inscripcion['clases_presentes'] = $presentes;
            $inscripcion['avance_pct'] = $total > 0 ? (int) round($presentes / $total * 100) : 0;
        }
        unset($inscripcion);

        return $inscripciones;
    }

    /**
     * Datos del certificado de una inscripción (solo del estudiante dueño):
     * nombre del estudiante, curso y fechas para fechar la emisión.
     *
     * @return array<string, mixed>|null null si la inscripción no existe o es de otro estudiante
     */
    public function datosCertificado(int $idInscripcion, int $idEstudiante): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT i.id_inscripcion, i.estado, i.fecha AS fecha_inscripcion,
                    i.fecha_revision, g.fecha_fin, c.nombre AS curso,
                    e.nombre, e.apellido
             FROM inscripcion i
             JOIN grupo g ON g.id_grupo = i.id_grupo
             JOIN curso c ON c.id_curso = g.id_curso
             JOIN estudiante e ON e.id_estudiante = i.id_estudiante
             WHERE i.id_inscripcion = :id AND i.id_estudiante = :estudiante'
        );
        $stmt->execute(['id' => $idInscripcion, 'estudiante' => $idEstudiante]);
        $fila = $stmt->fetch();

        return $fila === false ? null : $fila;
    }

    /**
     * Calendario (/estudiante/calendario): clases recurrentes de las
     * inscripciones vigentes, listas para pintar la cuadrícula mensual
     * y el panel "Clases del Día" del portal.
     *
     * Se agrupa por grupo + franja horaria, de modo que Lunes y Miércoles
     * 17:00-18:30 sean UNA sola clase recurrente con dos días.
     *
     * @return array{clases: array<int, array<string, mixed>>, leyenda: array<int, array<string, string>>}
     */
    public function calendario(int $idEstudiante): array
    {
        $sql = "SELECT g.id_grupo,
                       g.fecha_inicio,
                       g.fecha_fin,
                       c.id_curso,
                       c.nombre AS curso,
                       cat.nombre AS categoria,
                       a.nombre AS aula,
                       a.ubicacion,
                       CONCAT(inst.nombre, ' ', inst.apellido) AS instructor,
                       h.dia_semana,
                       h.hora_inicio,
                       h.hora_fin
                FROM inscripcion i
                JOIN grupo g ON g.id_grupo = i.id_grupo
                JOIN curso c ON c.id_curso = g.id_curso
                JOIN instrumento ins ON ins.id_instrumento = c.id_instrumento
                JOIN categoria cat ON cat.id_categoria = ins.id_categoria
                JOIN aula a ON a.id_aula = g.id_aula
                JOIN instructor inst ON inst.id_instructor = g.id_instructor
                JOIN horario h ON h.id_grupo = g.id_grupo
                WHERE i.id_estudiante = :id
                  AND i.estado IN ('Activa', 'Pendiente')
                  AND g.estado <> 'cancelado'
                  AND (g.fecha_fin IS NULL OR g.fecha_fin >= CURDATE())
                ORDER BY c.nombre, h.hora_inicio";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $idEstudiante]);
        $filas = $stmt->fetchAll();

        if ($filas === []) {
            return ['clases' => [], 'leyenda' => []];
        }

        // Días en el índice de Date.getDay() de JS: 0 = Domingo ... 6 = Sábado
        $mapaDias = [
            'Lunes' => 1, 'Martes' => 2, 'Miércoles' => 3, 'Jueves' => 4,
            'Viernes' => 5, 'Sábado' => 6, 'Domingo' => 0,
        ];

        $franjas = [];
        foreach ($filas as $fila) {
            $clave = $fila['id_grupo'] . '|' . $fila['hora_inicio'] . '|' . $fila['hora_fin'];

            $franjas[$clave] ??= [
                'id_curso'   => (int) $fila['id_curso'],
                'curso'      => $fila['curso'],
                'categoria'  => $fila['categoria'],
                'aula'       => $fila['aula'],
                'ubicacion'  => $fila['ubicacion'],
                'instructor' => $fila['instructor'],
                'inicio'     => $fila['fecha_inicio'],
                'fin'        => $fila['fecha_fin'],
                'hora_ini'   => $fila['hora_inicio'],
                'hora_fin'   => $fila['hora_fin'],
                'dias'       => [],
            ];

            $dia = $mapaDias[$fila['dia_semana']] ?? null;
            if ($dia !== null && !in_array($dia, $franjas[$clave]['dias'], true)) {
                $franjas[$clave]['dias'][] = $dia;
            }
        }

        // Paleta: un color por curso (se asigna en orden de aparición)
        $paleta = ['#ffc107', '#34d399', '#60a5fa', '#f87171', '#a78bfa', '#f472b6', '#22d3ee', '#fbbf24'];
        $colorPorCurso = [];
        $leyenda = [];

        $clases = [];
        foreach ($franjas as $franja) {
            if ($franja['dias'] === []) {
                continue; // grupo sin horario asignado
            }
            sort($franja['dias']);

            $idCurso = $franja['id_curso'];
            if (!isset($colorPorCurso[$idCurso])) {
                $indice = count($colorPorCurso);
                $colorPorCurso[$idCurso] = $paleta[$indice % count($paleta)];
                $leyenda[] = [
                    'curso' => $franja['curso'],
                    'color' => $colorPorCurso[$idCurso],
                ];
            }

            $clases[] = [
                'idCurso'    => $idCurso,
                'curso'      => $franja['curso'],
                'categoria'  => $franja['categoria'],
                'aula'       => $franja['aula'],
                'ubicacion'  => $franja['ubicacion'],
                'instructor' => $franja['instructor'],
                'inicio'     => $franja['inicio'],
                'fin'        => $franja['fin'],
                'horaInicio' => substr((string) $franja['hora_ini'], 0, 5),
                'horaFin'    => substr((string) $franja['hora_fin'], 0, 5),
                'dias'       => $franja['dias'],
                'color'      => $colorPorCurso[$idCurso],
                'url'        => '/cursos/' . $idCurso,
            ];
        }

        return ['clases' => $clases, 'leyenda' => $leyenda];
    }

    /**
     * Asistencias (/estudiante/asistencias): resumen global (% de
     * asistencia, totales) y detalle de cada registro con filtros.
     *
     * @param array{curso?: int|string|null, estado?: string|null, desde?: string|null, hasta?: string|null} $filtros
     * @return array{resumen: array<string, mixed>, detalle: array<int, array<string, mixed>>, cursos: array<int, array<string, mixed>>}
     */
    public function asistencias(int $idEstudiante, array $filtros = []): array
    {
        // ---- Resumen global (todas sus inscripciones) ----
        $sqlResumen = "SELECT COUNT(*) AS total_clases,
                              COALESCE(SUM(a.presente = 'S'), 0) AS presentes,
                              COALESCE(SUM(a.presente = 'N'), 0) AS ausentes,
                              COALESCE(SUM(a.presente = 'N' AND a.justificacion IS NOT NULL AND a.justificacion <> ''), 0) AS justificadas
                       FROM asistencia a
                       JOIN inscripcion i ON i.id_inscripcion = a.id_inscripcion
                       WHERE i.id_estudiante = :id";
        $stmt = $this->pdo->prepare($sqlResumen);
        $stmt->execute(['id' => $idEstudiante]);
        $r = $stmt->fetch() ?: [];

        $total = (int) ($r['total_clases'] ?? 0);
        $presentes = (int) ($r['presentes'] ?? 0);
        $ausentes = (int) ($r['ausentes'] ?? 0);
        $justificadas = (int) ($r['justificadas'] ?? 0);

        $resumen = [
            'total_clases'  => $total,
            'presentes'     => $presentes,
            'ausentes'      => $ausentes,
            'justificadas'  => $justificadas,
            'injustificadas'=> $ausentes - $justificadas,
            'porcentaje'    => $total > 0 ? round($presentes / $total * 100, 1) : 0.0,
        ];

        // ---- Catálogo de cursos del estudiante (para el filtro) ----
        $stmt = $this->pdo->prepare(
            "SELECT DISTINCT c.id_curso, c.nombre AS curso
             FROM inscripcion i
             JOIN grupo g ON g.id_grupo = i.id_grupo
             JOIN curso c ON c.id_curso = g.id_curso
             WHERE i.id_estudiante = :id
             ORDER BY c.nombre"
        );
        $stmt->execute(['id' => $idEstudiante]);
        $cursos = $stmt->fetchAll();

        // ---- Detalle con filtros opcionales ----
        $sql = "SELECT a.id_asistencia,
                       a.fecha_clase,
                       a.presente,
                       a.justificacion,
                       a.observaciones,
                       c.id_curso,
                       c.nombre AS curso,
                       CONCAT(inst.nombre, ' ', inst.apellido) AS instructor,
                       i.estado AS estado_inscripcion
                FROM asistencia a
                JOIN inscripcion i ON i.id_inscripcion = a.id_inscripcion
                JOIN grupo g ON g.id_grupo = i.id_grupo
                JOIN curso c ON c.id_curso = g.id_curso
                JOIN instructor inst ON inst.id_instructor = g.id_instructor
                WHERE i.id_estudiante = :id";
        $params = ['id' => $idEstudiante];

        $idCurso = $filtros['curso'] ?? null;
        if ($idCurso !== null && $idCurso !== '' && $idCurso !== '0') {
            $sql .= ' AND c.id_curso = :curso';
            $params['curso'] = (int) $idCurso;
        }

        $estado = $filtros['estado'] ?? '';
        if ($estado === 'presente' || $estado === 'ausente') {
            $sql .= " AND a.presente = :presente";
            $params['presente'] = $estado === 'presente' ? 'S' : 'N';
        }

        if (!empty($filtros['desde'])) {
            $sql .= ' AND a.fecha_clase >= :desde';
            $params['desde'] = $filtros['desde'];
        }
        if (!empty($filtros['hasta'])) {
            $sql .= ' AND a.fecha_clase <= :hasta';
            $params['hasta'] = $filtros['hasta'];
        }

        $sql .= ' ORDER BY a.fecha_clase DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $detalle = $stmt->fetchAll();

        return [
            'resumen' => $resumen,
            'cursos'  => $cursos,
            'detalle' => $detalle,
        ];
    }

    /**
     * Calificaciones (/estudiante/calificaciones): evaluaciones
     * agrupadas por inscripción (curso), con promedio calculado en
     * PHP a partir de las notas en escala 0-10.
     *
     * @return array{resumen: array<string, mixed>, grupos: array<int, array<string, mixed>>, detalle: array<int, array<string, mixed>>}
     */    public function calificaciones(int $idEstudiante): array
    {
        $sql = "SELECT e.id_evaluacion,
                       e.id_inscripcion,
                       e.tipo,
                       e.fecha_evaluacion,
                       e.calificacion,
                       e.descripcion,
                       e.observaciones_profesor,
                       i.estado AS estado_inscripcion,
                       i.fecha AS fecha_inscripcion,
                       g.fecha_fin,
                       c.id_curso,
                       c.nombre AS curso,
                       CONCAT(inst.nombre, ' ', inst.apellido) AS instructor
                FROM evaluacion e
                JOIN inscripcion i ON i.id_inscripcion = e.id_inscripcion
                JOIN grupo g ON g.id_grupo = i.id_grupo
                JOIN curso c ON c.id_curso = g.id_curso
                JOIN instructor inst ON inst.id_instructor = g.id_instructor
                WHERE i.id_estudiante = :id
                ORDER BY c.nombre, e.fecha_evaluacion DESC, e.id_evaluacion DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $idEstudiante]);
        $evaluaciones = $stmt->fetchAll();

        // ---- Agrupación por inscripción (un grupo por curso cursado) ----
        $grupos = [];
        foreach ($evaluaciones as $evaluacion) {
            $idIns = (int) $evaluacion['id_inscripcion'];

            $grupos[$idIns] ??= [
                'id_inscripcion'      => $idIns,
                'id_curso'            => (int) $evaluacion['id_curso'],
                'curso'               => $evaluacion['curso'],
                'instructor'          => $evaluacion['instructor'],
                'estado'              => $evaluacion['estado_inscripcion'],
                'fecha_fin'           => $evaluacion['fecha_fin'],
                'notas'               => [],
                'total_evaluaciones'  => 0,
                'suma'                => 0.0,
                'suma_notas'          => 0.0,
                'notas_puestas'       => 0,
                'promedio'            => 0.0,
                'minima'              => null,
                'maxima'              => null,
                'sin_nota'            => 0,
            ];

            $grupos[$idIns]['total_evaluaciones']++;

            if ($evaluacion['calificacion'] !== null) {
                $nota = (float) $evaluacion['calificacion'];
                $grupos[$idIns]['notas'][] = $nota;
                $grupos[$idIns]['suma'] += $nota;
                $grupos[$idIns]['suma_notas'] += $nota;
                $grupos[$idIns]['notas_puestas']++;
                $grupos[$idIns]['minima'] = $grupos[$idIns]['minima'] === null
                    ? $nota
                    : min($grupos[$idIns]['minima'], $nota);
                $grupos[$idIns]['maxima'] = $grupos[$idIns]['maxima'] === null
                    ? $nota
                    : max($grupos[$idIns]['maxima'], $nota);
            } else {
                $grupos[$idIns]['sin_nota']++;
            }
        }

        $cursosAprobados = 0;
        foreach ($grupos as &$grupo) {
            $conNota = count($grupo['notas']);
            $grupo['promedio'] = $conNota > 0 ? round($grupo['suma'] / $conNota, 2) : 0.0;

            if ($conNota > 0 && $grupo['promedio'] >= 5.0) {
                $cursosAprobados++;
            }

            unset($grupo['notas'], $grupo['suma']);
        }
        unset($grupo);

        $grupos = array_values($grupos);

        // ---- Resumen global ----
        // Promedio de TODAS las notas (igual que el KPI del dashboard),
        // no la media de medias: evita que los dos números discrepen.
        $sumaNotas = 0.0;
        $totalNotas = 0;
        foreach ($grupos as $grupo) {
            $sumaNotas += (float) $grupo['suma_notas'];
            $totalNotas += (int) $grupo['notas_puestas'];
        }

        $resumen = [
            'cursos_evaluados'       => count($grupos),
            'evaluaciones_total'     => count($evaluaciones),
            'promedio_global'        => $totalNotas > 0 ? round($sumaNotas / $totalNotas, 2) : 0.0,
            'notas_evaluadas'        => $totalNotas,
            'cursos_aprobados'       => $cursosAprobados,
            'evaluaciones_pendientes'=> count($evaluaciones) - $totalNotas,
        ];

        return [
            'resumen' => $resumen,
            'grupos'  => $grupos,
            'detalle' => $evaluaciones,
        ];
    }

    /**
     * Pagos (/estudiante/pagos): estado de cuenta por inscripción e
     * historial de movimientos. Solo comprobante y referencia — sin
     * pasarelas de pago.
     *
     * El saldo se deriva de la vista v_inscripciones_detalle:
     * saldo = precio_inscripcion (congelado) - SUM(pago.monto_pagado).
     * Un comprobante recién enviado queda en 'Pendiente' y no descuenta
     * hasta que el administrador lo verifique.
     *
     * @param array{estado?: string|null} $filtros
     * @return array{resumen: array<string, mixed>, saldos: array<int, array<string, mixed>>, historial: array<int, array<string, mixed>>}
     */
    public function pagos(int $idEstudiante, array $filtros = []): array
    {
        // ---- Estado de cuenta por inscripción (vista) ----
        // comprobante_pendiente: ya hay un comprobante enviado esperando
        // verificación; mientras tanto no descuenta del saldo.
        $stmt = $this->pdo->prepare(
            "SELECT v.id_inscripcion, v.curso, v.fecha, v.estado,
                    v.precio_inscripcion, v.total_pagado, v.saldo_pendiente,
                    EXISTS (SELECT 1 FROM pago p
                            WHERE p.id_inscripcion = v.id_inscripcion
                              AND p.estado = 'Pendiente'
                              AND p.comprobante_url IS NOT NULL) AS comprobante_pendiente
             FROM v_inscripciones_detalle v
             JOIN inscripcion i ON i.id_inscripcion = v.id_inscripcion
             WHERE i.id_estudiante = :id
             ORDER BY v.fecha DESC"
        );
        $stmt->execute(['id' => $idEstudiante]);
        $saldos = $stmt->fetchAll();

        // ---- Historial de movimientos ----
        $sql = "SELECT p.id_pago,
                       p.fecha_pago,
                       p.monto_pagado,
                       p.concepto,
                       p.estado,
                       p.referencia_pago,
                       p.comprobante_url,
                       p.observaciones,
                       p.fecha_verificacion,
                       p.motivo_verificacion,
                       c.nombre AS curso
                FROM pago p
                JOIN inscripcion i ON i.id_inscripcion = p.id_inscripcion
                JOIN grupo g ON g.id_grupo = i.id_grupo
                JOIN curso c ON c.id_curso = g.id_curso
                WHERE i.id_estudiante = :id";

        $params = ['id' => $idEstudiante];

        $estado = $filtros['estado'] ?? '';
        if (in_array($estado, ['Pagado', 'Pendiente', 'Fallido'], true)) {
            $sql .= ' AND p.estado = :estado';
            $params['estado'] = $estado;
        }

        $sql .= ' ORDER BY p.fecha_pago DESC, p.id_pago DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $historial = $stmt->fetchAll();

        // ¿Existe el archivo del comprobante? Puede ser un PDF generado
        // en /public (registros antiguos) o un archivo subido por el
        // estudiante, servido desde una ruta protegida por sesión.
        $raizPublic = dirname(__DIR__, 3) . '/public';
        foreach ($historial as &$mov) {
            $url = is_string($mov['comprobante_url']) ? (string) $mov['comprobante_url'] : '';
            if (ComprobanteStorage::esSubido($url)) {
                $archivo = ComprobanteStorage::archivoDe($url);
                $mov['comprobante_subido'] = $archivo !== '';
                $mov['comprobante_enlace'] = $archivo !== ''
                    ? '/estudiante/pagos/comprobante/' . rawurlencode($archivo)
                    : '';
                $mov['comprobante_disponible'] = $mov['comprobante_subido']
                    && is_file(ComprobanteStorage::rutaAbsoluta($archivo));
            } else {
                $mov['comprobante_subido'] = false;
                $mov['comprobante_enlace'] = '';
                $mov['comprobante_disponible'] = $url !== ''
                    && !str_contains($url, '..')
                    && is_file($raizPublic . $url);
            }
        }
        unset($mov);

        // ---- Resumen ----
        $pagado = 0.0;
        $saldo = 0.0;
        foreach ($saldos as $fila) {
            $pagado += (float) $fila['total_pagado'];
            $saldo += (float) $fila['saldo_pendiente'];
        }

        $pendiente = 0.0;
        $numPendientes = 0;
        foreach ($historial as $mov) {
            if ($mov['estado'] === 'Pendiente') {
                $pendiente += (float) $mov['monto_pagado'];
                $numPendientes++;
            }
        }

        $ultimo = null;
        foreach ($historial as $mov) {
            if ($mov['estado'] === 'Pagado') {
                $ultimo = $mov;
                break;
            }
        }

        $resumen = [
            'total_pagado'       => round($pagado, 2),
            'saldo_pendiente'    => round($saldo, 2),
            'importe_pendiente'  => round($pendiente, 2),
            'pagos_pendientes'   => $numPendientes,
            'total_movimientos'  => count($historial),
            'ultimo_pago'        => $ultimo,
            'al_dia'             => $saldo <= 0.0,
        ];

        return [
            'resumen'   => $resumen,
            'saldos'    => $saldos,
            'historial' => $historial,
        ];
    }

    /**
     * Envía un comprobante de pago desde el portal del estudiante.
     *
     * Crea el movimiento en estado 'Pendiente' —no descuenta saldo— y
     * guarda el archivo en var/uploads/comprobantes. Administración lo
     * verifica desde /admin/pagos (aprobar → 'Pagado', rechazar → 'Fallido').
     *
     * @param array{monto?: mixed, metodo?: mixed, referencia?: mixed} $datos
     * @return array{ok: bool, mensaje: string}
     */
    public function enviarComprobante(int $idEstudiante, int $idInscripcion, array $datos, ?UploadedFileInterface $file): array
    {
        $error = ComprobanteStorage::validar($file);
        if ($error !== null) {
            return ['ok' => false, 'mensaje' => $error];
        }

        $monto = trim((string) ($datos['monto'] ?? ''));
        if ($monto === '' || !is_numeric($monto) || (float) $monto <= 0) {
            return ['ok' => false, 'mensaje' => 'Indica el monto pagado (mayor a cero).'];
        }
        $monto = round((float) $monto, 2);

        $metodo = trim((string) ($datos['metodo'] ?? ''));
        if (!in_array($metodo, self::METODOS_PAGO, true)) {
            return ['ok' => false, 'mensaje' => 'Selecciona el método de pago.'];
        }

        $referencia = trim((string) ($datos['referencia'] ?? ''));
        if (mb_strlen($referencia) > 100) {
            return ['ok' => false, 'mensaje' => 'La referencia no puede superar 100 caracteres.'];
        }

        $url = '';
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                "SELECT i.id_inscripcion, i.estado, i.precio_inscripcion,
                        COALESCE((SELECT SUM(p.monto_pagado) FROM pago p
                                  WHERE p.id_inscripcion = i.id_inscripcion
                                    AND p.estado = 'Pagado'), 0) AS total_pagado
                 FROM inscripcion i
                 WHERE i.id_inscripcion = :id AND i.id_estudiante = :estudiante
                 FOR UPDATE"
            );
            $stmt->execute(['id' => $idInscripcion, 'estudiante' => $idEstudiante]);
            $inscripcion = $stmt->fetch();

            if (!$inscripcion) {
                $this->pdo->rollBack();
                return ['ok' => false, 'mensaje' => 'La matrícula no existe o no te pertenece.'];
            }
            if (!in_array($inscripcion['estado'], ['Activa', 'Pendiente'], true)) {
                $this->pdo->rollBack();
                return ['ok' => false, 'mensaje' => 'Solo puedes enviar comprobantes de matrículas activas o pendientes.'];
            }
            if ($inscripcion['precio_inscripcion'] === null) {
                $this->pdo->rollBack();
                return ['ok' => false, 'mensaje' => 'Esta matrícula no tiene precio configurado; contacta a administración.'];
            }

            $saldo = round((float) $inscripcion['precio_inscripcion'] - (float) $inscripcion['total_pagado'], 2);
            if ($saldo <= 0) {
                $this->pdo->rollBack();
                return ['ok' => false, 'mensaje' => 'Esta matrícula ya está saldada; no tiene saldo por pagar.'];
            }
            if ($monto > $saldo) {
                $this->pdo->rollBack();
                return ['ok' => false, 'mensaje' => 'El monto supera el saldo pendiente de esta matrícula (' . Dinero::monto($saldo) . ').'];
            }

            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM pago
                 WHERE id_inscripcion = :inscripcion AND estado = 'Pendiente' AND comprobante_url IS NOT NULL"
            );
            $stmt->execute(['inscripcion' => $idInscripcion]);
            if ((int) $stmt->fetchColumn() > 0) {
                $this->pdo->rollBack();
                return ['ok' => false, 'mensaje' => 'Ya tienes un comprobante en verificación para esta matrícula.'];
            }

            // Primer pago de la matrícula → Inscripción; a partir de ahí, Mensualidad.
            $concepto = (float) $inscripcion['total_pagado'] > 0 ? 'Mensualidad' : 'Inscripción';

            $this->pdo->prepare(
                'INSERT INTO pago (id_inscripcion, fecha_pago, monto_pagado, concepto, metodo,
                                   estado, referencia_pago, observaciones)
                 VALUES (:inscripcion, CURDATE(), :monto, :concepto, :metodo,
                         \'Pendiente\', :referencia, :observaciones)'
            )->execute([
                'inscripcion' => $idInscripcion,
                'monto'       => $monto,
                'concepto'    => $concepto,
                'metodo'      => $metodo,
                'referencia'  => $referencia !== '' ? $referencia : null,
                'observaciones' => 'Comprobante enviado desde el portal del estudiante',
            ]);
            $idPago = (int) $this->pdo->lastInsertId();

            /** @var UploadedFileInterface $file */
            $url = ComprobanteStorage::guardar($file, $idPago);
            $this->pdo->prepare('UPDATE pago SET comprobante_url = :url WHERE id_pago = :id')
                ->execute(['url' => $url, 'id' => $idPago]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            // El archivo ya se movió pero la fila no quedó confirmada.
            ComprobanteStorage::borrar($url !== '' ? $url : null);
            if ($e instanceof PDOException && (string) $e->getCode() === '40001') {
                return ['ok' => false, 'mensaje' => 'Hubo una operación simultánea. Recarga e inténtalo de nuevo.'];
            }
            throw $e;
        }

        return ['ok' => true, 'mensaje' => 'Comprobante enviado. Administración lo verificará y el saldo se actualizará al aprobarlo.'];
    }

    /**
     * ¿El archivo del comprobante pertenece a este estudiante?
     * Se usa al servirlo desde /estudiante/pagos/comprobante/{archivo}.
     */
    public function comprobantePropio(int $idEstudiante, string $archivo): bool
    {
        $idPago = ComprobanteStorage::idPagoDe($archivo);
        if ($idPago < 1) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM pago p
             JOIN inscripcion i ON i.id_inscripcion = p.id_inscripcion
             WHERE p.id_pago = :pago AND i.id_estudiante = :estudiante'
        );
        $stmt->execute(['pago' => $idPago, 'estudiante' => $idEstudiante]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Perfil (/estudiante/perfil): ficha personal del estudiante
     * junto con los datos de su cuenta de acceso.
     *
     * @return array<string, mixed>|null null si el estudiante no existe
     */
    public function perfil(int $idEstudiante): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT e.id_estudiante, e.nombre, e.apellido, e.email, e.telefono,
                    e.direccion, e.fecha_inscripcion, e.fecha_nacimiento,
                    u.id_usuario, u.email_usuario, u.ultimo_acceso, u.activo
             FROM estudiante e
             LEFT JOIN usuario u ON u.id_estudiante = e.id_estudiante AND u.rol = \'estudiante\'
             WHERE e.id_estudiante = :id'
        );
        $stmt->execute(['id' => $idEstudiante]);
        $fila = $stmt->fetch();
        if ($fila === false) {
            return null;
        }

        $stmtF = $this->pdo->prepare('SELECT ruta FROM estudiante_foto WHERE id_estudiante = :id');
        $stmtF->execute(['id' => $idEstudiante]);
        $foto = $stmtF->fetchColumn();
        $fila['foto_url'] = $foto ?: null;

        $stmtP = $this->pdo->prepare('SELECT generos_favoritos, objetivo_academia FROM estudiante_preferencias WHERE id_estudiante = :id');
        $stmtP->execute(['id' => $idEstudiante]);
        $pref = $stmtP->fetch();
        $fila['generos_favoritos'] = $pref['generos_favoritos'] ?? null;
        $fila['objetivo_academia'] = $pref['objetivo_academia'] ?? null;

        $sqlHist = "SELECT ins.nombre AS instrumento,
                           CONCAT(DATE_FORMAT(MIN(g.fecha_inicio), '%Y'), ' - ', COALESCE(DATE_FORMAT(MAX(g.fecha_fin), '%Y'), 'Presente')) AS periodo,
                           COALESCE(MAX(c.nivel), 'Finalizado') AS nivel
                    FROM inscripcion i
                    JOIN grupo g ON g.id_grupo = i.id_grupo
                    JOIN curso c ON c.id_curso = g.id_curso
                    JOIN instrumento ins ON ins.id_instrumento = c.id_instrumento
                    WHERE i.id_estudiante = :id AND i.estado IN ('Finalizada','Cancelada')
                    GROUP BY ins.id_instrumento, ins.nombre
                    ORDER BY MAX(g.fecha_fin) DESC, MIN(g.fecha_inicio) DESC";
        $stmtH = $this->pdo->prepare($sqlHist);
        $stmtH->execute(['id' => $idEstudiante]);
        $hist = $stmtH->fetchAll();
        if (empty($hist)) {
            $sqlHist2 = "SELECT ins.nombre AS instrumento,
                                CONCAT(DATE_FORMAT(MIN(g.fecha_inicio), '%Y'), ' - ', COALESCE(DATE_FORMAT(MAX(g.fecha_fin), '%Y'), 'Presente')) AS periodo,
                                COALESCE(MAX(c.nivel), '') AS nivel
                         FROM inscripcion i
                         JOIN grupo g ON g.id_grupo = i.id_grupo
                         JOIN curso c ON c.id_curso = g.id_curso
                         JOIN instrumento ins ON ins.id_instrumento = c.id_instrumento
                         WHERE i.id_estudiante = :id
                         GROUP BY ins.id_instrumento, ins.nombre
                         ORDER BY MAX(g.fecha_inicio) DESC";
            $stmtH2 = $this->pdo->prepare($sqlHist2);
            $stmtH2->execute(['id' => $idEstudiante]);
            $hist = $stmtH2->fetchAll();
        }
        $fila['historial_instrumentos'] = $hist;

        return $fila;
    }

    /**
     * Actualiza la ficha personal. El correo se mantiene sincronizado
     * con la cuenta de acceso (usuario.email_usuario) en la misma
     * transacción, para que el login y la ficha nunca se desincronicen.
     *
     * Lanza PDOException (código 23000) si el correo ya está en uso.
     *
     * @param array{nombre: string, apellido: string, email: string, telefono: string, direccion: string, fecha_nacimiento?: string} $datos
     */
    public function actualizarPerfil(int $idEstudiante, int $idUsuario, array $datos): void
    {
        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare(
                'UPDATE estudiante
                 SET nombre = :nombre, apellido = :apellido, email = :email,
                     telefono = :telefono, direccion = :direccion,
                     fecha_nacimiento = :fecha_nacimiento
                 WHERE id_estudiante = :id'
            );
            $stmt->execute([
                'nombre'    => $datos['nombre'],
                'apellido'  => $datos['apellido'],
                'email'     => $datos['email'],
                'telefono'  => $datos['telefono'] !== '' ? $datos['telefono'] : null,
                'direccion' => $datos['direccion'] !== '' ? $datos['direccion'] : null,
                'fecha_nacimiento' => ($datos['fecha_nacimiento'] ?? '') !== '' ? $datos['fecha_nacimiento'] : null,
                'id'        => $idEstudiante,
            ]);

            // La cuenta de acceso usa el mismo correo que la ficha.
            // El filtro id_estudiante evita tocar cuentas ajenas.
            $stmt = $this->pdo->prepare(
                'UPDATE usuario SET email_usuario = :email
                 WHERE id_usuario = :id AND id_estudiante = :idEstudiante'
            );
            $stmt->execute([
                'email'       => $datos['email'],
                'id'          => $idUsuario,
                'idEstudiante'=> $idEstudiante,
            ]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
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
                        CONCAT('Pago registrado: ', p.concepto, ' — $ ', FORMAT(p.monto_pagado, 2)) AS detalle
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
                    . ' · ' . Dinero::monto((float) $pago['monto_pagado']) . ' — ' . $pago['curso'],
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
