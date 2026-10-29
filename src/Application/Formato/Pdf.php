<?php

declare(strict_types=1);

namespace App\Application\Formato;

/**
 * Generador de PDF sin dependencias externas (misma técnica que el boletín
 * del estudiante y los comprobantes): Helvetica en Latin-1, texto con
 * operadores BT/Tj y rectángulos de color con "re f" para las barras de
 * los gráficos. Las páginas se cortan solas al llegar al margen inferior.
 *
 *     $pdf = new Pdf();
 *     $pdf->texto(18, 'Título');
 *     $pdf->barra(200, 12, [0.20, 0.82, 0.60]);
 *     $documento = $pdf->construir();
 */
final class Pdf
{
    /** A4 en puntos. */
    public const ANCHO = 595;
    public const ALTO = 842;

    private const MARGEN_IZQ = 48;
    private const MARGEN_ARRIBA = 792;
    private const MARGEN_ABAJO = 52;
    private const MARGEN_DERECHA = 547;

    /** Texto principal (gris muy oscuro, legible en pantalla y papel). */
    public const TEXTO = [0.10, 0.12, 0.17];
    /** Texto secundario. */
    public const TENUE = [0.42, 0.46, 0.53];
    /** Dorado de marca (títulos y remates). */
    public const DORADO = [0.75, 0.55, 0.03];

    /** @var list<string> */
    private array $paginas = [];

    private string $actual = '';

    private int $y = self::MARGEN_ARRIBA;

    public function __construct()
    {
        $this->nuevaPagina();
    }

    /**
     * Escribe una línea de texto y baja el cursor. Si no cabe en la página,
     * continúa en una nueva.
     *
     * @param array{0: float, 1: float, 2: float}|null $rgb Color 0–1.
     */
    public function texto(int $tam, string $contenido, ?array $rgb = null, int $x = self::MARGEN_IZQ): void
    {
        $alto = (int) round($tam * 2.2) + 6;
        if ($this->y - $alto < self::MARGEN_ABAJO) {
            $this->nuevaPagina();
        }
        $this->y -= $alto;

        [$r, $g, $b] = $rgb ?? self::TEXTO;
        $this->actual .= sprintf(
            "BT /F1 %d Tf %.3f %.3f %.3f rg 1 0 0 1 %d %d Tm (%s) Tj ET\n",
            $tam,
            $r,
            $g,
            $b,
            $x,
            $this->y,
            self::escapar($contenido)
        );
    }

    /**
     * Varios fragmentos en la misma línea vertical (columnas de tabla):
     * cada uno con su tamaño, color y posición X, y el cursor baja una vez.
     *
     * Cada columna es [tamaño, texto, rgb opcional, x opcional].
     *
     * @param array<int, array<int, mixed>> $columnas
     */
    public function fila(array $columnas): void
    {
        $alto = 20;
        foreach ($columnas as $columna) {
            $tam = (int) ($columna[0] ?? 10);
            $alto = max($alto, (int) round($tam * 2.2) + 6);
        }
        if ($this->y - $alto < self::MARGEN_ABAJO) {
            $this->nuevaPagina();
        }
        $this->y -= $alto;

        foreach ($columnas as $columna) {
            $tam = (int) ($columna[0] ?? 10);
            $texto = (string) ($columna[1] ?? '');
            if ($texto === '') {
                continue;
            }
            [$r, $g, $b] = $columna[2] ?? self::TEXTO;
            $x = (int) ($columna[3] ?? self::MARGEN_IZQ);
            $this->actual .= sprintf(
                "BT /F1 %d Tf %.3f %.3f %.3f rg 1 0 0 1 %d %d Tm (%s) Tj ET\n",
                $tam,
                $r,
                $g,
                $b,
                $x,
                $this->y,
                self::escapar($texto)
            );
        }
    }

    /**
     * Rectángulo relleno cuyo borde superior queda en la posición actual
     * (útil para barras de gráfico) y baja el cursor.
     *
     * @param array{0: float, 1: float, 2: float} $rgb
     */
    public function barra(int $ancho, int $alto, array $rgb, int $x = self::MARGEN_IZQ): void
    {
        if ($this->y - $alto < self::MARGEN_ABAJO) {
            $this->nuevaPagina();
        }
        $this->y -= $alto;

        $this->actual .= sprintf(
            "%.3f %.3f %.3f rg %d %d %d %d re f\n",
            $rgb[0],
            $rgb[1],
            $rgb[2],
            $x,
            $this->y,
            $ancho,
            $alto
        );
    }

    /** Rectángulo fino (línea separadora) bajo el cursor actual. */
    public function separador(int $grosor = 1, ?array $rgb = null): void
    {
        [$r, $g, $b] = $rgb ?? [0.85, 0.87, 0.90];
        $this->barra(self::MARGEN_DERECHA - self::MARGEN_IZQ, $grosor, [$r, $g, $b]);
        $this->y -= 4;
    }

    /** Hueco vertical entre bloques. */
    public function espacio(int $alto): void
    {
        $this->y -= $alto;
        if ($this->y < self::MARGEN_ABAJO) {
            $this->nuevaPagina();
        }
    }

    /**
     * Garantiza N puntos libres antes de escribir un bloque (para que una
     * tabla no se separe de su cabecera): si no caben, empieza página.
     */
    public function asegurarEspacio(int $alto): void
    {
        if ($this->y - $alto < self::MARGEN_ABAJO) {
            $this->nuevaPagina();
        }
    }

    /** Fuerza el inicio de una página nueva. */
    public function saltoPagina(): void
    {
        $this->nuevaPagina();
    }

    public function yActual(): int
    {
        return $this->y;
    }

    /** Ensambla el documento con todas las páginas generadas. */
    public function construir(): string
    {
        if ($this->actual !== '') {
            $this->paginas[] = $this->actual;
            $this->actual = '';
        }
        if (!$this->paginas) {
            $this->paginas[] = '';
        }

        // 1 = Catálogo, 2 = Páginas, 3 = Fuente; el resto: página + contenido.
        $objetos = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        ];

        $kids = [];
        $siguiente = 4;
        foreach ($this->paginas as $stream) {
            $pagina = $siguiente;
            $contenido = $siguiente + 1;
            $kids[] = $pagina . ' 0 R';
            $objetos[$pagina] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %d %d] /Resources << /Font << /F1 3 0 R >> >> /Contents %d 0 R >>',
                self::ANCHO,
                self::ALTO,
                $contenido
            );
            $objetos[$contenido] = '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . 'endstream';
            $siguiente += 2;
        }
        $objetos[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($this->paginas) . ' >>';
        ksort($objetos);

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objetos as $numero => $cuerpo) {
            $offsets[$numero] = strlen($pdf);
            $pdf .= $numero . " 0 obj\n" . $cuerpo . "\nendobj\n";
        }

        $total = count($objetos);
        $xref = strlen($pdf);
        $pdf .= 'xref' . "\n" . '0 ' . ($total + 1) . "\n" . '0000000000 65535 f ' . "\n";
        for ($i = 1; $i <= $total; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= 'trailer' . "\n" . '<< /Size ' . ($total + 1) . ' /Root 1 0 R >>' . "\n"
            . 'startxref' . "\n" . $xref . "\n" . "%%EOF\n";

        return $pdf;
    }

    private function nuevaPagina(): void
    {
        if ($this->actual !== '') {
            $this->paginas[] = $this->actual;
        }
        $this->actual = '';
        $this->y = self::MARGEN_ARRIBA;
    }

    /** Texto seguro para un literal PDF en Latin-1 (Helvetica). */
    private static function escapar(string $texto): string
    {
        // Tipografía fuera de Latin-1 → equivalentes ASCII
        $texto = str_replace(['—', '–', '…', '·', '•', '≥', '≤'], ['-', '-', '...', '-', '-', '>=', '<='], $texto);
        $texto = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $texto);

        return iconv('UTF-8', 'ISO-8859-1//IGNORE', $texto) ?: $texto;
    }
}
