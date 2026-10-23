<?php
/**
 * Genera los PDFs de comprobante de pago en /public/comprobantes.
 * Uso: php scripts/generar-comprobantes.php
 *
 * Crea un PDF de una página por cada pago que tenga comprobante_url,
 * con los datos reales de la base de datos (curso, concepto, importe,
 * referencia). Es solo un documento de demostración del portal.
 */
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$config = require __DIR__ . '/../config/db.php';

$pdo = new PDO(
    sprintf('mysql:host=%s;dbname=%s;charset=%s', $config['host'], $config['dbname'], $config['charset']),
    $config['user'],
    $config['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$pagos = $pdo->query(
    "SELECT p.id_pago, p.fecha_pago, p.monto_pagado, p.concepto, p.estado,
            p.referencia_pago, p.comprobante_url, c.nombre AS curso,
            CONCAT(e.nombre, ' ', e.apellido) AS estudiante
     FROM pago p
     JOIN inscripcion i ON i.id_inscripcion = p.id_inscripcion
     JOIN estudiante e ON e.id_estudiante = i.id_estudiante
     JOIN grupo g ON g.id_grupo = i.id_grupo
     JOIN curso c ON c.id_curso = g.id_curso
     WHERE p.comprobante_url IS NOT NULL
     ORDER BY p.id_pago"
)->fetchAll(PDO::FETCH_ASSOC);

$destino = __DIR__ . '/../public/comprobantes';
if (!is_dir($destino) && !mkdir($destino, 0775, true)) {
    fwrite(STDERR, "No se pudo crear $destino\n");
    exit(1);
}

/** Escapa texto para un literal de PDF. */
function pdfText(string $t): string
{
    // Caracteres tipográficos fuera de Latin-1 → equivalentes ASCII
    $t = str_replace(['—', '–', '·', '•'], ['-', '-', '-', '-'], $t);
    $t = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $t);
    // Quitar lo que quede fuera de Latin-1 para que Helvetica lo dibuje
    return iconv('UTF-8', 'ISO-8859-1//IGNORE', $t) ?: $t;
}

/** Construye un PDF de una página con las líneas dadas. */
function buildPdf(array $lineas): string
{
    // Tm fija la posición absoluta de cada línea, sin acumular desplazamientos
    $stream = "BT\n";
    $y = 790;
    foreach ($lineas as [$tam, $texto]) {
        $stream .= sprintf("/F1 %d Tf 1 0 0 1 60 %d Tm (%s) Tj\n", $tam, $y, pdfText($texto));
        $y -= (int) round($tam * 2.4) + 10;
    }
    $stream .= "ET\n";

    $objetos = [
        1 => "<< /Type /Catalog /Pages 2 0 R >>",
        2 => "<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
        3 => "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] "
           . "/Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>",
        4 => "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "endstream",
        5 => "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>",
    ];

    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objetos as $num => $cuerpo) {
        $offsets[$num] = strlen($pdf);
        $pdf .= "$num 0 obj\n$cuerpo\nendobj\n";
    }

    $xref = strlen($pdf);
    $pdf .= "xref\n0 6\n0000000000 65535 f \n";
    for ($i = 1; $i <= 5; $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    $pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";

    return $pdf;
}

$cargados = 0;
foreach ($pagos as $p) {
    $ruta = $destino . '/' . basename((string) $p['comprobante_url']);

    $lineas = [
        [20, 'SolfAcademy'],
        [12, 'COMPROBANTE DE PAGO'],
        [10, str_repeat('-', 68)],
        [12, 'Alumno/a:  ' . $p['estudiante']],
        [12, 'Curso:     ' . $p['curso']],
        [12, 'Concepto:  ' . $p['concepto']],
        [12, 'Fecha:     ' . date('d/m/Y', strtotime((string) $p['fecha_pago']))],
        [12, 'Importe:   ' . number_format((float) $p['monto_pagado'], 2, '.', ',') . ' USD'],
        [12, 'Estado:    ' . $p['estado']],
        [12, 'Referencia: ' . ($p['referencia_pago'] ?? '(sin referencia)')],
        [10, str_repeat('-', 68)],
        [11, 'Pago #' . $p['id_pago'] . ' — documento generado automáticamente'],
        [11, 'SolfAcademy · Escuela de música'],
    ];

    if (file_put_contents($ruta, buildPdf($lineas)) === false) {
        fwrite(STDERR, "No se pudo escribir $ruta\n");
        exit(1);
    }
    echo "✓ " . basename($ruta) . "\n";
    $cargados++;
}

echo "\n$cargados comprobante(s) generados en public/comprobantes/\n";
