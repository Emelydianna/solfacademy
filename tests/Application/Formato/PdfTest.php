<?php

declare(strict_types=1);

namespace Tests\Application\Formato;

use App\Application\Formato\Pdf;
use PHPUnit\Framework\TestCase;

/**
 * Motor de PDF del proyecto (sin dependencias): texto, barras de color y
 * paginación automática.
 */
final class PdfTest extends TestCase
{
    public function testDocumentoBasicoEsValidoYContieneElTexto(): void
    {
        $pdf = new Pdf();
        $pdf->texto(18, 'SOLFACADEMY', Pdf::DORADO);
        $pdf->texto(11, 'Ingresos Totales: $ 140.00');
        $pdf->texto(10, 'Acentos: Distribución · ≥ 7 · Añadido', Pdf::TENUE);
        $pdf->separador();
        $pdf->barra(200, 12, [0.204, 0.827, 0.600]);
        $pdf->espacio(10);
        $pdf->texto(10, 'Pie del documento');

        $documento = $pdf->construir();

        $this->assertStringStartsWith('%PDF-1.4', $documento);
        $this->assertStringEndsWith("%%EOF\n", $documento);
        $this->assertStringContainsString('SOLFACADEMY', $documento);
        $this->assertStringContainsString('Ingresos Totales', $documento);
        // ≥ se escribe como >= para que Helvetica pueda dibujarlo.
        $this->assertStringContainsString('Distribuci', $documento);
        $this->assertStringContainsString('>= 7', $documento);
        // Barra de color: operador "re f" con el rgb indicado.
        $this->assertStringContainsString('0.204 0.827 0.600 rg', $documento);
        $this->assertStringContainsString(' re f', $documento);
        // Una sola página cuando el contenido cabe.
        $this->assertStringContainsString('/Count 1', $documento);
    }

    public function testLosParentesisYTensionesNoRompenElLiteral(): void
    {
        $pdf = new Pdf();
        $pdf->texto(11, 'Ruta (principal) y 100% \\ final');

        $documento = $pdf->construir();

        // Escapados de PDF: los paréntesis y la barra invertida van con \.
        $this->assertStringContainsString('\(principal\)', $documento);
        $this->assertStringContainsString('\\ final', $documento);
    }

    public function testColumnasDeTablaCompartenLaMismaLinea(): void
    {
        $pdf = new Pdf();
        $pdf->texto(13, 'PROGRAMAS MAS POPULARES');
        $pdf->fila([
            [9, 'CURSO', Pdf::TENUE],
            [9, 'ALUMNOS', Pdf::TENUE, 395],
        ]);
        $pdf->fila([
            [10, 'Violin inicial'],
            [10, '1', null, 412],
            [10, '-100%', [0.8, 0.22, 0.22], 460],
        ]);
        $documento = $pdf->construir();

        $this->assertStringStartsWith('%PDF-1.4', $documento);
        $this->assertStringContainsString('Violin inicial', $documento);
        // Las tres columnas de la fila comparten la coordenada Y.
        $this->assertSame(1, preg_match_all('/1 0 0 1 \d+ (\d+) Tm \(Violin inicial\)/', $documento, $m));
        $this->assertSame(1, preg_match_all('/1 0 0 1 412 (\d+) Tm \(1\)/', $documento, $m2));
        $this->assertSame($m[1][0], $m2[1][0], 'Las columnas deben estar a la misma altura');
        $this->assertStringContainsString('(-100%) Tj', $documento);
        $this->assertStringContainsString('/Count 1', $documento);
    }

    public function testPaginaAutomaticaCuandoElContenidoNoCabe(): void
    {
        $pdf = new Pdf();
        for ($i = 0; $i < 120; $i++) {
            $pdf->texto(11, 'Linea ' . $i . ' del informe largo.');
        }
        $documento = $pdf->construir();

        $this->assertSame(1, preg_match('#/Count (\d+)#', $documento, $m));
        $paginas = (int) $m[1];
        $this->assertGreaterThan(1, $paginas, '120 líneas no caben en una página A4');
        $this->assertSame($paginas, substr_count($documento, '/Type /Page '), 'Un objeto /Page por página');
        // Catálogo + Páginas + Fuente + (página + contenido) por cada una.
        $this->assertStringContainsString('/Size ' . (3 + 2 * $paginas + 1), $documento);
        $this->assertStringContainsString('Linea 0 ', $documento);
        $this->assertStringContainsString('Linea 119 ', $documento);
    }
}
