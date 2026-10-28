<?php

declare(strict_types=1);

namespace App\Application\Admin;

use App\Application\Formato\Dinero;
use DateTimeImmutable;
use PDO;

/**
 * Datos reales del panel de administración (/admin/dashboard).
 *
 * Nada de cifras de maqueta: cada tarjeta, el gráfico, las alertas y la
 * actividad salen de la BD. Como no existe tabla de auditoría, la actividad
 * reciente se reconstruye con las fechas que ya hay (altas, matrículas,
 * pagos y evaluaciones).
 */
final class AdminDashboardService
{
    /** Selector del header: clave => etiqueta visible. El primero es el defecto. */
    public const RANGOS = [
        '5m'  => 'Últimos 5 meses',
        '12m' => 'Últimos 12 meses',
        'ytd' => 'Año en curso',
        'mes' => 'Este mes',
    ];

    private const DIAS_LARGO = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    private const MESES_CORTO = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    private const MESES_LARGO = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @param string $rango Clave de self::RANGOS; cualquier otra cae en '5m'.
     * @return array<string, mixed>
     */
    public function dashboard(string $rango): array
    {
        $clave = array_key_exists($rango, self::RANGOS) ? $rango : '5m';
        $periodo = $this->periodo($clave);

        return [
            'rango'           => $clave,
            'rangos'          => self::RANGOS,
            'fechaTexto'      => $this->fechaTexto(),
            // El cuarto KPI (ingresos) depende del periodo elegido
            'kpis'            => array_merge($this->kpis(), [$this->ingresos($periodo)]),
            'notificaciones'  => $this->notificaciones(),
            'grafico'         => $this->grafico($periodo),
            'alertas'         => $this->alertas(),
            'actividad'       => $this->actividad(),
        ];
    }

    // =========================================================
    // Periodo seleccionado
    // =========================================================

    /**
     * Límites del periodo y del periodo inmediatamente anterior (para el
     * porcentaje "vs periodo anterior").
     *
     * @return array<string, mixed>
     */
    private function periodo(string $clave): array
    {
        $hoy = new DateTimeImmutable('today');

        switch ($clave) {
            case 'mes':
                $inicio = $hoy->modify('first day of this month');
                $fin = $inicio->modify('first day of next month');
                $antInicio = $inicio->modify('first day of previous month');
                $antFin = $inicio;
                $agrupacion = 'dia';
                $tituloIngresos = 'Ingresos del Mes';
                $comparacion = 'vs mes anterior';
                break;

            case '12m':
            case '5m':
                $meses = $clave === '12m' ? 12 : 5;
                $inicio = $hoy->modify(sprintf('-%d months', $meses - 1))->modify('first day of this month');
                $fin = $hoy->modify('first day of next month');
                $antInicio = $inicio->modify(sprintf('-%d months', $meses));
                $antFin = $inicio;
                $agrupacion = 'mes';
                $tituloIngresos = 'Ingresos (' . $meses . ' meses)';
                $comparacion = 'vs ' . $meses . ' meses anteriores';
                break;

            case 'ytd':
            default:
                $inicio = new DateTimeImmutable(sprintf('%04d-01-01', (int) $hoy->format('Y')));
                $fin = $hoy->modify('first day of next month');
                $antInicio = $inicio->modify('-1 year');
                $antFin = $fin->modify('-1 year');
                $agrupacion = 'mes';
                $tituloIngresos = 'Ingresos del Año';
                $comparacion = 'vs año anterior';
                break;
        }

        return [
            'clave'           => $clave,
            'inicio'          => $inicio,
            'fin'             => $fin,
            'antInicio'       => $antInicio,
            'antFin'          => $antFin,
            'agrupacion'      => $agrupacion,
            'tituloIngresos'  => $tituloIngresos,
            'comparacion'     => $comparacion,
        ];
    }

    // =========================================================
    // Fila de KPIs
    // =========================================================

    /** @return array<int, array<string, mixed>> */
    private function kpis(): array
    {
        $hoy = new DateTimeImmutable('today');
        $mesIni = $hoy->modify('first day of this month')->format('Y-m-d');
        $mesFin = $hoy->modify('first day of next month')->format('Y-m-d');

        $estudiantes = (int) $this->uno('SELECT COUNT(*) FROM estudiante');
        $altasMes = (int) $this->uno(
            'SELECT COUNT(*) FROM estudiante WHERE fecha_inscripcion >= :ini AND fecha_inscripcion < :fin',
            ['ini' => $mesIni, 'fin' => $mesFin]
        );

        $instructores = (int) $this->uno('SELECT COUNT(*) FROM instructor');
        $activos = (int) $this->uno('SELECT COUNT(*) FROM instructor WHERE activo = 1');

        $cursos = (int) $this->uno('SELECT COUNT(*) FROM curso WHERE activo = 1');
        $areas = (int) $this->uno(
            'SELECT COUNT(DISTINCT cat.id_categoria)
               FROM categoria cat
               JOIN instrumento ins ON ins.id_categoria = cat.id_categoria
               JOIN curso c ON c.id_instrumento = ins.id_instrumento
              WHERE c.activo = 1'
        );

        return [
            [
                'titulo' => 'Total Estudiantes',
                'valor'  => $estudiantes . ' Alumnos',
                'sub'    => $altasMes . ' este mes',
                'verde'  => $altasMes > 0,
                'icono'  => 'bi-people-fill',
                'clase'  => '',
            ],
            [
                'titulo' => 'Total Instructores',
                'valor'  => $instructores . ' Profesores',
                'sub'    => $activos === $instructores
                    ? 'Todos activos'
                    : $activos . ' de ' . $instructores . ' activos',
                'verde'  => $activos === $instructores,
                'icono'  => 'bi-person-workspace',
                'clase'  => 'portal-kpi-icon--azul',
            ],
            [
                'titulo' => 'Cursos Activos',
                'valor'  => $cursos . ' Programas',
                'sub'    => $areas . ($areas === 1 ? ' área de estudio' : ' áreas de estudio'),
                'verde'  => $cursos > 0,
                'icono'  => 'bi-collection-play',
                'clase'  => 'portal-kpi-icon--verde',
            ],
        ];
    }

    // =========================================================
    // Ingresos (siguen el periodo elegido)
    // =========================================================

    /** @param array<string, mixed> $p */
    private function ingresos(array $p): array
    {
        $actual = $this->sumaPagos($p['inicio'], $p['fin']);
        $anterior = $this->sumaPagos($p['antInicio'], $p['antFin']);

        if ($anterior > 0) {
            $pct = round((($actual - $anterior) / $anterior) * 100, 1);
            $sub = Dinero::porcentaje($pct) . ' ' . $p['comparacion'];
            $verde = $pct >= 0;
        } else {
            $sub = 'Sin periodo anterior comparable';
            $verde = $actual > 0;
        }

        return [
            'titulo' => $p['tituloIngresos'],
            'valor'  => Dinero::monto($actual),
            'sub'    => $sub,
            'verde'  => $verde,
            'icono'  => 'bi-credit-card-fill',
            'clase'  => 'portal-kpi-icon--rosa',
        ];
    }

    private function sumaPagos(DateTimeImmutable $ini, DateTimeImmutable $fin): float
    {
        return (float) $this->uno(
            "SELECT COALESCE(SUM(monto_pagado), 0) FROM pago
              WHERE estado = 'Pagado' AND fecha_pago >= :ini AND fecha_pago < :fin",
            ['ini' => $ini->format('Y-m-d'), 'fin' => $fin->format('Y-m-d')]
        );
    }

    // =========================================================
    // Badge de notificaciones
    // =========================================================

    private function notificaciones(): int
    {
        // Solo lo que está en cola de verificación para administración:
        // los comprobantes rechazados los retoma el estudiante.
        $pagos = (int) $this->uno("SELECT COUNT(*) FROM pago
            WHERE estado = 'Pendiente' AND comprobante_url IS NOT NULL");
        $matriculas = (int) $this->uno("SELECT COUNT(*) FROM inscripcion WHERE estado = 'Pendiente'");

        return $pagos + $matriculas;
    }

    // =========================================================
    // Gráfico de inscripciones
    // =========================================================

    /** @param array<string, mixed> $p
     *  @return array<string, mixed>
     */
    private function grafico(array $p): array
    {
        $porDia = $p['agrupacion'] === 'dia';

        $stmt = $this->pdo->prepare(
            'SELECT DATE_FORMAT(fecha, \'%Y-%m-%d\') AS clave, COUNT(*) AS total
               FROM inscripcion
              WHERE fecha >= :ini AND fecha < :fin
              GROUP BY clave'
        );
        $stmt->execute([
            'ini' => $p['inicio']->format('Y-m-d'),
            'fin' => $p['fin']->format('Y-m-d'),
        ]);

        $conteos = [];
        foreach ($stmt->fetchAll() as $fila) {
            $clave = (string) $fila['clave'];
            // La consulta devuelve el día completo; para las cubetas mensuales
            // solo cuenta el año-mes.
            if (!$porDia) {
                $clave = substr($clave, 0, 7);
            }
            $conteos[$clave] = ($conteos[$clave] ?? 0) + (int) $fila['total'];
        }

        $etiquetas = [];
        $valores = [];
        $acumulado = 0;

        foreach ($this->cubetas($p) as $cubeta) {
            $etiquetas[] = $cubeta['etiqueta'];
            $total = $conteos[$cubeta['clave']] ?? 0;
            $valores[] = $total;
            $acumulado += $total;
        }

        return [
            'titulo'     => $porDia ? 'Inscripciones del Mes' : 'Inscripciones Mensuales',
            'subtitulo'  => $porDia ? 'Matrículas por día' : 'Matrículas registradas por mes',
            'etiquetas'  => $etiquetas,
            'valores'    => $valores,
            'acumulado'  => $acumulado,
            // La última barra (la más reciente) se destaca en dorado
            'destacado'  => count($valores) - 1,
        ];
    }

    /**
     * Cubetas del periodo (días si es "este mes", meses en caso contrario),
     * incluidas las que no tienen datos.
     *
     * @param array<string, mixed> $p
     * @return array<int, array{clave: string, etiqueta: string}>
     */
    private function cubetas(array $p): array
    {
        $cubetas = [];

        if ($p['agrupacion'] === 'dia') {
            for ($d = $p['inicio']; $d < $p['fin']; $d = $d->modify('+1 day')) {
                $cubetas[] = [
                    'clave'    => $d->format('Y-m-d'),
                    'etiqueta' => (string) (int) $d->format('j'),
                ];
            }

            return $cubetas;
        }

        for ($m = $p['inicio']; $m < $p['fin']; $m = $m->modify('+1 month')) {
            $cubetas[] = [
                'clave'    => $m->format('Y-m'),
                'etiqueta' => self::MESES_CORTO[(int) $m->format('n') - 1],
            ];
        }

        return $cubetas;
    }

    // =========================================================
    // Alertas del sistema
    // =========================================================

    /** @return array<int, array<string, string>> */
    private function alertas(): array
    {
        $alertas = [];

        $fallidos = (int) $this->uno("SELECT COUNT(*) FROM pago WHERE estado = 'Fallido'");
        if ($fallidos > 0) {
            $alertas[] = [
                'severidad' => 'pendiente',
                'titulo'    => $fallidos . ($fallidos === 1 ? ' comprobante rechazado' : ' comprobantes rechazados'),
                'texto'     => 'Esperando que el estudiante envíe otro comprobante',
                'icono'     => 'bi-exclamation-octagon-fill',
            ];
        }

        $pendientes = (int) $this->uno("SELECT COUNT(*) FROM pago
            WHERE estado = 'Pendiente' AND comprobante_url IS NOT NULL");
        if ($pendientes > 0) {
            $montoPendiente = (float) $this->uno("SELECT COALESCE(SUM(monto_pagado), 0) FROM pago
                WHERE estado = 'Pendiente' AND comprobante_url IS NOT NULL");
            $alertas[] = [
                'severidad' => 'pendiente',
                'titulo'    => $pendientes . ($pendientes === 1 ? ' comprobante por verificar' : ' comprobantes por verificar'),
                'texto'     => Dinero::monto($montoPendiente) . ' esperando verificación en Pagos',
                'icono'     => 'bi-clock-history',
            ];
        }

        $matriculas = (int) $this->uno("SELECT COUNT(*) FROM inscripcion WHERE estado = 'Pendiente'");
        if ($matriculas > 0) {
            $alertas[] = [
                'severidad' => 'pendiente',
                'titulo'    => $matriculas . ($matriculas === 1 ? ' matrícula pendiente' : ' matrículas pendientes'),
                'texto'     => 'Esperando aprobación del administrador',
                'icono'     => 'bi-hourglass-split',
            ];
        }

        $sinGrupo = (int) $this->uno(
            'SELECT COUNT(*) FROM curso c
              WHERE c.activo = 1
                AND NOT EXISTS (SELECT 1 FROM grupo g WHERE g.id_curso = c.id_curso AND g.estado != \'cancelado\')'
        );
        if ($sinGrupo > 0) {
            $alertas[] = [
                'severidad' => 'pendiente',
                'titulo'    => $sinGrupo . ($sinGrupo === 1 ? ' curso sin grupo abierto' : ' cursos sin grupo abierto'),
                'texto'     => 'Programas activos aún no asignados a un grupo',
                'icono'     => 'bi-journal-x',
            ];
        }

        $sinAlumnos = (int) $this->uno(
            "SELECT COUNT(*) FROM grupo g
              WHERE g.estado = 'activo'
                AND NOT EXISTS (SELECT 1 FROM inscripcion i WHERE i.id_grupo = g.id_grupo AND i.estado IN ('Activa', 'Pendiente'))"
        );
        if ($sinAlumnos > 0) {
            $alertas[] = [
                'severidad' => 'info',
                'titulo'    => $sinAlumnos . ($sinAlumnos === 1 ? ' grupo sin alumnos' : ' grupos sin alumnos'),
                'texto'     => 'Grupos activos sin ninguna matrícula',
                'icono'     => 'bi-people',
            ];
        }

        $sinCalificar = (int) $this->uno('SELECT COUNT(*) FROM evaluacion WHERE calificacion IS NULL');
        if ($sinCalificar > 0) {
            $alertas[] = [
                'severidad' => 'info',
                'titulo'    => $sinCalificar . ($sinCalificar === 1 ? ' evaluación sin calificar' : ' evaluaciones sin calificar'),
                'texto'     => 'Notas pendientes por cargar en los grupos',
                'icono'     => 'bi-clipboard-data',
            ];
        }

        // Prioridad: urgente > pendiente > información
        $orden = ['urgente' => 0, 'pendiente' => 1, 'info' => 2];
        usort($alertas, fn (array $a, array $b) => $orden[$a['severidad']] <=> $orden[$b['severidad']]);

        return array_slice($alertas, 0, 4);
    }

    // =========================================================
    // Actividad reciente (derivada de fechas existentes)
    // =========================================================

    /** @return array<int, array<string, string>> */
    private function actividad(): array
    {
        $eventos = [];

        $altas = $this->consultar(
            'SELECT fecha_inscripcion AS fecha, CONCAT(nombre, \' \', apellido) AS quien
               FROM estudiante
              WHERE fecha_inscripcion IS NOT NULL
              ORDER BY fecha_inscripcion DESC
              LIMIT 4'
        );
        foreach ($altas as $f) {
            $eventos[] = [
                'fecha' => (string) $f['fecha'],
                'texto' => 'Estudiante ' . $f['quien'] . ' dado de alta en la academia',
                'tipo'  => 'Alta',
            ];
        }

        $matriculas = $this->consultar(
            "SELECT i.fecha, CONCAT(e.nombre, ' ', e.apellido) AS quien, c.nombre AS curso
               FROM inscripcion i
               JOIN estudiante e ON e.id_estudiante = i.id_estudiante
               JOIN grupo g ON g.id_grupo = i.id_grupo
               JOIN curso c ON c.id_curso = g.id_curso
              ORDER BY i.fecha DESC
              LIMIT 5"
        );
        foreach ($matriculas as $f) {
            $eventos[] = [
                'fecha' => (string) $f['fecha'],
                'texto' => $f['quien'] . " inscrito en '" . $f['curso'] . "'",
                'tipo'  => 'Matrícula',
            ];
        }

        $pagos = $this->consultar(
            "SELECT p.fecha_pago AS fecha, CONCAT(e.nombre, ' ', e.apellido) AS quien,
                    p.concepto, p.monto_pagado, p.estado
               FROM pago p
               JOIN inscripcion i ON i.id_inscripcion = p.id_inscripcion
               JOIN estudiante e ON e.id_estudiante = i.id_estudiante
              ORDER BY p.fecha_pago DESC
              LIMIT 5"
        );
        foreach ($pagos as $f) {
            $eventos[] = [
                'fecha' => (string) $f['fecha'],
                'texto' => 'Pago ' . strtolower((string) $f['estado']) . ' de ' . $f['quien']
                    . ' — ' . $f['concepto'] . ' (' . Dinero::monto((float) $f['monto_pagado']) . ')',
                'tipo'  => 'Pago',
            ];
        }

        $evaluaciones = $this->consultar(
            "SELECT ev.fecha_evaluacion AS fecha, ev.tipo AS tipo_eval, c.nombre AS curso
               FROM evaluacion ev
               JOIN inscripcion i ON i.id_inscripcion = ev.id_inscripcion
               JOIN grupo g ON g.id_grupo = i.id_grupo
               JOIN curso c ON c.id_curso = g.id_curso
              WHERE ev.fecha_evaluacion IS NOT NULL
              ORDER BY ev.fecha_evaluacion DESC
              LIMIT 5"
        );
        foreach ($evaluaciones as $f) {
            $eventos[] = [
                'fecha' => (string) $f['fecha'],
                'texto' => "Evaluación '" . $f['tipo_eval'] . "' registrada en '" . $f['curso'] . "'",
                'tipo'  => 'Académico',
            ];
        }

        usort($eventos, fn (array $a, array $b) => strcmp($b['fecha'], $a['fecha']));

        $eventos = array_slice($eventos, 0, 8);
        foreach ($eventos as &$evento) {
            $evento['hace'] = $this->hace($evento['fecha']);
        }
        unset($evento);

        return $eventos;
    }

    // =========================================================
    // Utilidades
    // =========================================================

    /** @return array<int, array<string, mixed>> */
    private function consultar(string $sql): array
    {
        $stmt = $this->pdo->query($sql);

        return $stmt ? $stmt->fetchAll() : [];
    }

    /** @param array<string, mixed> $params */
    private function uno(string $sql, array $params = []): mixed
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchColumn();
    }

    private function fechaTexto(): string
    {
        $hoy = new DateTimeImmutable('today');

        return self::DIAS_LARGO[(int) $hoy->format('w')]
            . ', ' . (int) $hoy->format('j') . ' '
            . self::MESES_LARGO[(int) $hoy->format('n') - 1] . ' '
            . $hoy->format('Y');
    }

    private function hace(string $fecha): string
    {
        if ($fecha === '') {
            return '';
        }

        $dias = (new DateTimeImmutable($fecha))->setTime(0, 0)
            ->diff(new DateTimeImmutable('today'))
            ->days;

        return match (true) {
            $dias <= 0 => 'Hoy',
            $dias === 1 => 'Ayer',
            $dias < 30 => 'Hace ' . $dias . ' días',
            $dias < 60 => 'Hace 1 mes',
            $dias < 365 => 'Hace ' . (int) floor($dias / 30) . ' meses',
            default => 'Hace ' . (int) floor($dias / 365) . ' años',
        };
    }
}