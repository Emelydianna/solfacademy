<?php

declare(strict_types=1);

namespace Tests\Application\Admin;

use App\Application\Admin\ReportesService;
use Slim\Views\PhpRenderer;

/**
 * Métricas y reportes generales: KPIs, distribución por instrumento,
 * programas populares y exportación a PDF, todo con datos reales.
 */
final class ReportesTest extends GestionOperativaTestCase
{
    private ReportesService $reportes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reportes = new ReportesService($this->pdo);
    }

    /** Busca un KPI por su título. */
    private function kpi(array $datos, string $titulo): array
    {
        foreach ($datos['kpis'] as $kpi) {
            if ($kpi['titulo'] === $titulo) {
                return $kpi;
            }
        }
        $this->fail('No existe el KPI ' . $titulo);

        return [];
    }

    /** @return array<string, mixed>|null */
    private function instrumento(array $datos, string $nombre): ?array
    {
        foreach ($datos['distribucion'] as $fila) {
            if ($fila['nombre'] === $nombre) {
                return $fila;
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    private function programa(array $datos, string $fragmento): ?array
    {
        foreach ($datos['programas'] as $fila) {
            if (str_contains($fila['curso'], $fragmento)) {
                return $fila;
            }
        }

        return null;
    }

    public function testKpisSeCalculanDeLaBase(): void
    {
        // Base vacía: sin inventar datos.
        $vacio = $this->reportes->datos();
        $this->assertSame('$ 0.00', $this->kpi($vacio, 'Ingresos Totales')['valor']);
        $this->assertSame('Sin periodo anterior comparable', $this->kpi($vacio, 'Ingresos Totales')['sub']);
        $this->assertSame('2 Alumnos', $this->kpi($vacio, 'Alumnos Activos')['valor']);
        $this->assertSame('—', $this->kpi($vacio, 'Tasa de Retención')['valor']);
        $this->assertSame('—', $this->kpi($vacio, 'Tasa de Aprobación')['valor']);

        // Datos conocidos: 1 pago actual + 1 pago del año anterior.
        $g = $this->crearGrupo();
        $inscripcion = $this->matricular($g);
        $this->pdo->exec("INSERT INTO pago (id_inscripcion, fecha_pago, monto_pagado, estado)
            VALUES ($inscripcion, CURDATE(), 100.00, 'Pagado'),
                   ($inscripcion, DATE_SUB(CURDATE(), INTERVAL 13 MONTH), 40.00, 'Pagado')");
        // Una matrícula cancelada para la retención (2 en total).
        $this->pdo->exec("INSERT INTO inscripcion (id_estudiante, id_grupo, fecha, estado, precio_inscripcion)
            VALUES (2, $g, CURDATE(), 'Cancelada', 49.95)");
        // 3 evaluaciones: dos con nota ≥ 7 (7.5 y 9.0) y una con 6.0.
        $this->pdo->exec("INSERT INTO evaluacion (id_inscripcion, tipo, calificacion)
            VALUES ($inscripcion, 'Parcial', 7.50), ($inscripcion, 'Parcial', 6.00), ($inscripcion, 'Parcial', 9.00)");

        $datos = $this->reportes->datos();

        $ingresos = $this->kpi($datos, 'Ingresos Totales');
        $this->assertSame('$ 140.00', $ingresos['valor']);
        $this->assertSame('+150.0% vs año anterior', $ingresos['sub']);
        $this->assertTrue($ingresos['verde']);

        $this->assertSame('2 Alumnos', $this->kpi($datos, 'Alumnos Activos')['valor']);

        $retencion = $this->kpi($datos, 'Tasa de Retención');
        $this->assertSame('50.0 %', $retencion['valor']);
        $this->assertSame('1 de 2 matrículas no canceladas', $retencion['sub']);

        $aprobacion = $this->kpi($datos, 'Tasa de Aprobación');
        $this->assertSame('66.7 %', $aprobacion['valor']);
        $this->assertStringContainsString('2 de 3 evaluaciones', $aprobacion['sub']);
    }

    public function testDistribucionPorInstrumento(): void
    {
        $violines = $this->crearGrupo(); // curso de Violín (instrumento 1)
        // El instructor 2 es el único especializado en Piano (instrumento 2).
        $piano = $this->crearGrupo(['id_curso' => '3', 'id_instructor' => '2', 'id_aula' => '2',
            'horarios' => [['dia_semana' => 'Martes', 'hora_inicio' => '10:00', 'hora_fin' => '11:00']]]);

        $this->matricular($violines);            // estudiante 1, Activa
        $this->matricular($piano, 'Activa', 2);   // estudiante 2, Activa
        // Una cancelada no debe aparecer en la distribución.
        $this->matricular($piano, 'Cancelada', 1);

        $datos = $this->reportes->datos();
        $this->assertCount(2, $datos['distribucion']);

        $violin = $this->instrumento($datos, 'Violín');
        $this->assertNotNull($violin);
        $this->assertSame(1, $violin['alumnos']);
        $this->assertSame(50.0, $violin['porcentaje']);

        $pianoFila = $this->instrumento($datos, 'Piano');
        $this->assertNotNull($pianoFila);
        $this->assertSame(1, $pianoFila['alumnos']);
        $this->assertSame(50.0, $pianoFila['porcentaje']);

        // Colores de la paleta y distintos entre sí.
        $this->assertContains($violin['color'], ReportesService::COLORES);
        $this->assertNotSame($violin['color'], $pianoFila['color']);
    }

    public function testProgramasPopularesYTendenciaDe30Dias(): void
    {
        $g1 = $this->crearGrupo(); // Violín inicial
        $this->matricular($g1);    // matrícula de hoy → hay movimiento reciente

        $g2 = $this->crearGrupo(['id_curso' => '2', 'id_instructor' => '3', 'id_aula' => '2',
            'horarios' => [['dia_semana' => 'Martes', 'hora_inicio' => '10:00', 'hora_fin' => '11:00']]]);
        // Matrícula antigua (40 días): entra en los 30 anteriores, no en los últimos 30.
        $this->pdo->exec("INSERT INTO inscripcion (id_estudiante, id_grupo, fecha, estado, precio_inscripcion)
            VALUES (2, $g2, DATE_SUB(CURDATE(), INTERVAL 40 DAY), 'Activa', 49.95)");

        $datos = $this->reportes->datos();
        $this->assertCount(2, $datos['programas']);

        $reciente = $this->programa($datos, 'Violín inicial');
        $this->assertNotNull($reciente);
        $this->assertSame(1, $reciente['alumnos']);
        $this->assertSame('Cuerdas', $reciente['area']);
        $this->assertSame('+1 nueva', $reciente['tendencia']);
        $this->assertSame('sube', $reciente['direccion']);

        $antiguo = $this->programa($datos, 'Violín intermedio');
        $this->assertNotNull($antiguo);
        $this->assertSame('-100%', $antiguo['tendencia']);
        $this->assertSame('baja', $antiguo['direccion']);
    }

    public function testHttpListadoYExportacionPdf(): void
    {
        $app = $this->appAdmin();

        $r = $app->handle($this->createRequest('GET', '/admin/reportes'));
        $this->assertSame(200, $r->getStatusCode());
        $cuerpo = (string) $r->getBody();
        $this->assertStringContainsString('Métricas y Reportes Generales', $cuerpo);
        $this->assertStringContainsString('Exportar PDF', $cuerpo);
        $this->assertStringContainsString('Ingresos Totales', $cuerpo);
        $this->assertStringContainsString('Tasa de Retención', $cuerpo);
        $this->assertStringContainsString('Programas más Populares', $cuerpo);
        $this->assertStringContainsString('Distribución de Alumnos por Instrumento', $cuerpo);

        // El PDF lleva exactamente los mismos datos que la pantalla.
        $g = $this->crearGrupo();
        $inscripcion = $this->matricular($g);
        $this->pdo->exec("INSERT INTO pago (id_inscripcion, fecha_pago, monto_pagado, estado)
            VALUES ($inscripcion, CURDATE(), 140.00, 'Pagado')");
        $datos = $this->reportes->datos();

        $r = $app->handle($this->createRequest('GET', '/admin/reportes/exportar'));
        $this->assertSame(200, $r->getStatusCode());
        $this->assertSame('application/pdf', $r->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('reportes-generales-', $r->getHeaderLine('Content-Disposition'));

        $pdf = (string) $r->getBody();
        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringEndsWith("%%EOF\n", $pdf);
        $this->assertStringContainsString('METRICAS Y REPORTES GENERALES', $pdf);
        $this->assertStringContainsString('INDICADORES CLAVE', $pdf);
        $this->assertStringContainsString('DISTRIBUCION DE ALUMNOS POR INSTRUMENTO', $pdf);
        $this->assertStringContainsString('PROGRAMAS MAS POPULARES', $pdf);
        // El valor del KPI aparece en el documento.
        $this->assertStringContainsString($this->kpi($datos, 'Ingresos Totales')['valor'], $pdf);

        // Sin sesión no hay reportes.
        $_SESSION = [];
        $this->assertSame(302, $app->handle($this->createRequest('GET', '/admin/reportes'))->getStatusCode());
    }

    /** El renderer del panel es necesario para la vista HTML. */
    protected function appAdmin(): \Slim\App
    {
        $app = parent::appAdmin();
        $app->getContainer()->set(PhpRenderer::class, new PhpRenderer(__DIR__ . '/../../../templates'));
        $app->getContainer()->set(ReportesService::class, $this->reportes);
        return $app;
    }
}
