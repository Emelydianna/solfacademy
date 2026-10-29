<?php

declare(strict_types=1);

namespace App\Application\Admin;

use App\Application\Config\ConfiguracionService;
use App\Application\Formato\Dinero;
use App\Application\Formato\Fecha;
use App\Application\Formato\Pdf;
use PDO;

/**
 * Métricas y reportes generales (/admin/reportes).
 *
 * Todos los números salen de la base de datos real: ingresos de la tabla
 * `pago`, matrículas de `inscripcion`, notas de `evaluacion` (escala 0–10,
 * aprobado ≥ 7 como en el informe del instructor) y distribución de
 * alumnos por instrumento a partir de las matrículas vigentes.
 */
final class ReportesService
{
    /** Aprobado en la escala 0–10 (misma regla que InstructorService). */
    private const UMBRAL_APROBACION = 7.0;

    /** Máximo de instrumentos en la dona antes de agruparlos en "Otros". */
    private const TOP_INSTRUMENTOS = 5;

    /** Máximo de cursos en la tabla de popularidad. */
    private const TOP_CURSOS = 10;

    /** Paleta compartida: leyenda de la dona, barras y PDF. */
    public const COLORES = [
        '#34d399', // verde
        '#38bdf8', // azul
        '#fb923c', // naranja
        '#fbbf24', // amarillo
        '#a78bfa', // violeta
        '#f472b6', // rosa
    ];

    private PDO $pdo;
    private ConfiguracionService $configuracion;

    /** El servicio de configuración es opcional para no romper las pruebas. */
    public function __construct(PDO $pdo, ?ConfiguracionService $configuracion = null)
    {
        $this->pdo = $pdo;
        $this->configuracion = $configuracion ?? new ConfiguracionService($pdo);
    }

    /**
     * Todo lo que muestra la pantalla y el PDF (misma fuente de verdad).
     *
     * @return array{fecha: string, kpis: array<int, array<string, mixed>>, distribucion: array<int, array<string, mixed>>, programas: array<int, array<string, mixed>>}
     */
    public function datos(): array
    {
        return [
            'fecha'        => Fecha::larga(),
            'kpis'         => $this->kpis(),
            'distribucion' => $this->distribucion(),
            'programas'    => $this->programas(),
        ];
    }

    // =========================================================
    // KPIs
    // =========================================================

    /** @return array<int, array<string, mixed>> */
    private function kpis(): array
    {
        return [
            $this->ingresos(),
            $this->alumnosActivos(),
            $this->retencion(),
            $this->aprobacion(),
        ];
    }

    /** @return array<string, mixed> */
    private function ingresos(): array
    {
        $total = (float) $this->uno("SELECT COALESCE(SUM(monto_pagado), 0) FROM pago WHERE estado = 'Pagado'");

        // Últimos 12 meses frente a los 12 anteriores ("vs año anterior").
        $hoy = new \DateTimeImmutable('today');
        $actIni = $hoy->modify('-12 months');
        $act = $this->sumaPagos($actIni, $hoy->modify('+1 day'));
        $ant = $this->sumaPagos($actIni->modify('-12 months'), $actIni);

        if ($ant > 0) {
            $pct = round((($act - $ant) / $ant) * 100, 1);
            $sub = ($pct >= 0 ? '+' : '') . number_format($pct, 1) . '% vs año anterior';
            $verde = $pct >= 0;
        } else {
            $sub = 'Sin periodo anterior comparable';
            $verde = $act > 0;
        }

        return [
            'titulo' => 'Ingresos Totales',
            'valor'  => Dinero::monto($total),
            'sub'    => $sub,
            'verde'  => $verde,
            'icono'  => 'bi-credit-card-fill',
            'clase'  => 'portal-kpi-icon--rosa',
        ];
    }

    /** @return array<string, mixed> */
    private function alumnosActivos(): array
    {
        $activos = (int) $this->uno("SELECT COUNT(*) FROM estudiante WHERE estado = 'Activo'");
        $total = (int) $this->uno('SELECT COUNT(*) FROM estudiante');

        return [
            'titulo' => 'Alumnos Activos',
            'valor'  => $activos . ' Alumnos',
            'sub'    => 'Todos los programas · ' . $total . ' fichas en total',
            'verde'  => $activos > 0,
            'icono'  => 'bi-people-fill',
            'clase'  => 'portal-kpi-icon--azul',
        ];
    }

    /** Retención = matrículas que no se cancelaron ÷ matrículas totales. */
    private function retencion(): array
    {
        $fila = $this->uno('SELECT COUNT(*) AS total, COALESCE(SUM(estado <> \'Cancelada\'), 0) AS retenidas FROM inscripcion', true);
        $total = (int) ($fila['total'] ?? 0);
        $retenidas = (int) ($fila['retenidas'] ?? 0);
        $pct = $total > 0 ? round($retenidas / $total * 100, 1) : null;

        return [
            'titulo' => 'Tasa de Retención',
            'valor'  => $pct === null ? '—' : number_format($pct, 1) . ' %',
            'sub'    => $total > 0
                ? $retenidas . ' de ' . $total . ' matrículas no canceladas'
                : 'Sin matrículas registradas',
            'verde'  => $pct !== null && $pct >= 50,
            'icono'  => 'bi-graph-up-arrow',
            'clase'  => 'portal-kpi-icon--verde',
        ];
    }

    /** Aprobación = evaluaciones con nota ≥ 7 sobre las evaluaciones con nota. */
    private function aprobacion(): array
    {
        $fila = $this->uno('SELECT COUNT(*) AS total,
                    COALESCE(SUM(calificacion >= ' . self::UMBRAL_APROBACION . '), 0) AS aprobadas
                 FROM evaluacion WHERE calificacion IS NOT NULL', true);
        $total = (int) ($fila['total'] ?? 0);
        $aprobadas = (int) ($fila['aprobadas'] ?? 0);
        $pct = $total > 0 ? round($aprobadas / $total * 100, 1) : null;

        return [
            'titulo' => 'Tasa de Aprobación',
            'valor'  => $pct === null ? '—' : number_format($pct, 1) . ' %',
            'sub'    => $total > 0
                ? $aprobadas . ' de ' . $total . ' evaluaciones con nota ≥ ' . (int) self::UMBRAL_APROBACION
                : 'Aún no hay evaluaciones con nota',
            'verde'  => $pct !== null && $pct >= 50,
            'icono'  => 'bi-award-fill',
            'clase'  => '',
        ];
    }

    // =========================================================
    // Distribución por instrumento
    // =========================================================

    /**
     * Alumnos distintos con matrícula vigente (Activa/Pendiente) por
     * instrumento del curso, con su porcentaje sobre el total.
     *
     * @return array<int, array<string, mixed>>
     */
    private function distribucion(): array
    {
        $stmt = $this->pdo->query(
            "SELECT ins.nombre AS instrumento, COUNT(DISTINCT i.id_estudiante) AS alumnos
             FROM inscripcion i
             JOIN grupo g ON g.id_grupo = i.id_grupo
             JOIN curso c ON c.id_curso = g.id_curso
             JOIN instrumento ins ON ins.id_instrumento = c.id_instrumento
             WHERE i.estado IN ('Activa', 'Pendiente')
             GROUP BY ins.id_instrumento, ins.nombre
             ORDER BY alumnos DESC, ins.nombre"
        );
        $filas = $stmt->fetchAll();

        $total = 0;
        foreach ($filas as $fila) {
            $total += (int) $fila['alumnos'];
        }
        if ($total === 0) {
            return [];
        }

        // Top N; el resto se agrupa en "Otros" para que la dona no se llene.
        $recortar = count($filas) > self::TOP_INSTRUMENTOS;
        $otros = 0;
        $salida = [];
        foreach ($filas as $indice => $fila) {
            $alumnos = (int) $fila['alumnos'];
            if ($recortar && $indice >= self::TOP_INSTRUMENTOS) {
                $otros += $alumnos;
                continue;
            }
            $salida[] = [
                'nombre'     => (string) $fila['instrumento'],
                'alumnos'    => $alumnos,
                'porcentaje' => round($alumnos / $total * 100, 1),
            ];
        }
        if ($otros > 0) {
            $salida[] = [
                'nombre'     => 'Otros',
                'alumnos'    => $otros,
                'porcentaje' => round($otros / $total * 100, 1),
            ];
        }

        foreach ($salida as $indice => &$item) {
            $item['color'] = self::COLORES[$indice % count(self::COLORES)];
        }
        unset($item);

        return $salida;
    }

    // =========================================================
    // Programas más populares
    // =========================================================

    /**
     * Cursos con más matrículas vigentes y su tendencia: variación de
     * matrículas de los últimos 30 días frente a los 30 anteriores.
     *
     * @return array<int, array<string, mixed>>
     */
    private function programas(): array
    {
        $stmt = $this->pdo->query(
            "SELECT c.id_curso, c.nombre, cat.nombre AS area,
                    COUNT(i.id_inscripcion) AS alumnos
             FROM curso c
             JOIN instrumento ins ON ins.id_instrumento = c.id_instrumento
             JOIN categoria cat ON cat.id_categoria = ins.id_categoria
             LEFT JOIN grupo g ON g.id_curso = c.id_curso
             LEFT JOIN inscripcion i ON i.id_grupo = g.id_grupo
                                      AND i.estado IN ('Activa', 'Pendiente')
             GROUP BY c.id_curso, c.nombre, cat.nombre
             HAVING alumnos > 0
             ORDER BY alumnos DESC, c.nombre
             LIMIT " . self::TOP_CURSOS
        );
        $filas = $stmt->fetchAll();
        if (!$filas) {
            return [];
        }

        $hoy = new \DateTimeImmutable('today');
        $actIni = $hoy->modify('-30 days')->format('Y-m-d');
        $actFin = $hoy->modify('+1 day')->format('Y-m-d');
        $antIni = $hoy->modify('-60 days')->format('Y-m-d');

        $stmt = $this->pdo->prepare(
            'SELECT g.id_curso,
                    COALESCE(SUM(i.fecha >= :a1 AND i.fecha < :a2), 0) AS recientes,
                    COALESCE(SUM(i.fecha >= :b1 AND i.fecha < :b2), 0) AS anteriores
             FROM inscripcion i
             JOIN grupo g ON g.id_grupo = i.id_grupo
             WHERE i.fecha >= :b3
             GROUP BY g.id_curso'
        );
        $stmt->execute(['a1' => $actIni, 'a2' => $actFin, 'b1' => $antIni, 'b2' => $actIni, 'b3' => $antIni]);
        $movimiento = [];
        foreach ($stmt->fetchAll() as $fila) {
            $movimiento[(int) $fila['id_curso']] = [
                'recientes'  => (int) $fila['recientes'],
                'anteriores' => (int) $fila['anteriores'],
            ];
        }

        $salida = [];
        foreach ($filas as $fila) {
            $idCurso = (int) $fila['id_curso'];
            $act = $movimiento[$idCurso]['recientes'] ?? 0;
            $ant = $movimiento[$idCurso]['anteriores'] ?? 0;
            [$texto, $tendencia] = $this->tendencia($act, $ant);

            $salida[] = [
                'id_curso'  => $idCurso,
                'curso'     => (string) $fila['nombre'],
                'area'      => (string) $fila['area'],
                'alumnos'   => (int) $fila['alumnos'],
                'tendencia' => $texto,
                'direccion' => $tendencia,
            ];
        }

        return $salida;
    }

    /** @return array{0: string, 1: string} texto y dirección (sube/baja/igual/neutra) */
    private function tendencia(int $actual, int $anterior): array
    {
        if ($anterior > 0) {
            $pct = (int) round(($actual - $anterior) / $anterior * 100);
            if ($pct > 0) {
                return ['+' . $pct . '%', 'sube'];
            }
            if ($pct < 0) {
                return [$pct . '%', 'baja'];
            }
            return ['0%', 'igual'];
        }
        if ($actual > 0) {
            return ['+' . $actual . ($actual === 1 ? ' nueva' : ' nuevas'), 'sube'];
        }

        return ['—', 'neutra'];
    }

    // =========================================================
    // PDF
    // =========================================================

    /** Dibuja el reporte completo (una o varias páginas) en el PDF dado. */
    public function dibujarPdf(Pdf $pdf, array $datos): void
    {
        // Marca de la academia (configurada en /admin/configuracion).
        $config = $this->configuracion->obtener();
        $nombre = (string) $config['nombre'];
        $nota = trim((string) ($config['nota_pdf'] ?? ''));
        if ($nota === '') {
            $nota = $nombre . ' · ' . (string) $config['lema'];
        }

        $pdf->texto(20, mb_strtoupper($nombre), Pdf::DORADO);
        $pdf->texto(13, 'METRICAS Y REPORTES GENERALES');
        $pdf->texto(10, (string) $datos['fecha'] . '   ·   Documento generado automáticamente', Pdf::TENUE);
        $pdf->separador(2, Pdf::DORADO);

        // KPIs
        $pdf->texto(11, 'INDICADORES CLAVE');
        foreach ($datos['kpis'] as $kpi) {
            $pdf->texto(12, $kpi['titulo'] . ':  ' . $kpi['valor']);
            $pdf->texto(9, '      ' . $kpi['sub'], Pdf::TENUE);
        }
        $pdf->espacio(8);
        $pdf->separador();

        // Distribución por instrumento (barras con los mismos colores que la dona)
        $pdf->asegurarEspacio(110);
        $pdf->texto(13, 'DISTRIBUCION DE ALUMNOS POR INSTRUMENTO');
        if (!$datos['distribucion']) {
            $pdf->texto(10, 'Sin matriculas vigentes para agrupar por instrumento.', Pdf::TENUE);
        }
        foreach ($datos['distribucion'] as $item) {
            $pdf->texto(10, $item['nombre'] . ' — ' . $item['alumnos']
                . ($item['alumnos'] === 1 ? ' alumno (' : ' alumnos (')
                . number_format((float) $item['porcentaje'], 1) . '%)');
            $ancho = max(6, (int) round((float) $item['porcentaje'] / 100 * 320));
            $pdf->barra($ancho, 11, self::rgb($item['color']));
            $pdf->espacio(4);
        }
        $pdf->espacio(8);
        $pdf->asegurarEspacio(140);
        $pdf->separador();

        // Programas más populares (cabecera y primeras filas en la misma página)
        $pdf->texto(13, 'PROGRAMAS MAS POPULARES');
        if (!$datos['programas']) {
            $pdf->texto(10, 'Sin matriculas vigentes registradas.', Pdf::TENUE);
        } else {
            $pdf->fila([
                [9, 'CURSO', Pdf::TENUE],
                [9, 'AREA', Pdf::TENUE, 310],
                [9, 'ALUMNOS', Pdf::TENUE, 395],
                [9, 'TENDENCIA', Pdf::TENUE, 460],
            ]);
            foreach ($datos['programas'] as $curso) {
                $color = $curso['direccion'] === 'sube' ? [0.10, 0.65, 0.35]
                    : ($curso['direccion'] === 'baja' ? [0.80, 0.22, 0.22] : Pdf::TENUE);
                $pdf->fila([
                    [10, self::cortar($curso['curso'], 46)],
                    [10, self::cortar($curso['area'], 16), Pdf::TENUE, 310],
                    [10, (string) $curso['alumnos'], null, 412],
                    [10, $curso['tendencia'], $color, 460],
                ]);
            }
        }

        $pdf->espacio(14);
        $pdf->texto(9, 'Generado el ' . date('d/m/Y H:i') . ' · ' . $nota, Pdf::TENUE);
    }

    // =========================================================
    // Auxiliares
    // =========================================================

    private function sumaPagos(\DateTimeImmutable $ini, \DateTimeImmutable $fin): float
    {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(monto_pagado), 0) FROM pago
             WHERE estado = 'Pagado' AND fecha_pago >= :ini AND fecha_pago < :fin"
        );
        $stmt->execute(['ini' => $ini->format('Y-m-d'), 'fin' => $fin->format('Y-m-d')]);

        return (float) $stmt->fetchColumn();
    }

    /** @return array<string, mixed>|scalar */
    private function uno(string $sql, bool $fila = false): mixed
    {
        $resultado = $this->pdo->query($sql)->fetch();
        if ($fila) {
            return $resultado ?: [];
        }
        if ($resultado === false) {
            return 0;
        }
        // La conexión trabaja en modo FETCH_ASSOC: el valor va en la primera clave.
        return array_values($resultado)[0] ?? 0;
    }

    /** "#34d399" → [0.204, 0.827, 0.600] para los operadores de color. */
    private static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            round((int) hexdec(substr($hex, 0, 2)) / 255, 3),
            round((int) hexdec(substr($hex, 2, 2)) / 255, 3),
            round((int) hexdec(substr($hex, 4, 2)) / 255, 3),
        ];
    }

    private static function cortar(string $texto, int $max): string
    {
        if (mb_strlen($texto) <= $max) {
            return $texto;
        }

        return rtrim(mb_substr($texto, 0, $max - 1)) . '…';
    }
}
