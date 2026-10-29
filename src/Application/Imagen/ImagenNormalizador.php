<?php

declare(strict_types=1);

namespace App\Application\Imagen;

/**
 * Normalización común de imágenes subidas con GD.
 *
 * Valida las dimensiones y reescala hacia abajo conservando la proporción
 * (nunca agranda), reescribiendo el fichero como PNG en su misma ruta.
 *
 * Lo comparten el logo de la academia y la imagen de referencia de los
 * cursos para no duplicar la lógica de GD.
 */
final class ImagenNormalizador
{
    /**
     * @param string $ruta  Ruta absoluta del fichero recién escrito.
     * @param string $mime  MIME real detectado con finfo (no el nombre).
     * @param int    $lienzo Máximo por lado del resultado (no agranda).
     * @param int    $minimo Mínimo por lado admitido.
     * @param int    $maximo Máximo por lado admitido.
     *
     * @return string|null Mensaje de error, o null si todo fue bien.
     */
    public static function normalizar(string $ruta, string $mime, int $lienzo, int $minimo, int $maximo): ?string
    {
        $info = @getimagesize($ruta);
        if ($info === false) {
            return 'El archivo no es una imagen válida.';
        }
        [$ancho, $alto] = [$info[0], $info[1]];

        if ($ancho < $minimo || $alto < $minimo) {
            return "La imagen es demasiada pequeña: mínimo {$minimo}px por lado.";
        }
        if ($ancho > $maximo || $alto > $maximo) {
            return "La imagen es demasiada grande: máximo {$maximo}px por lado.";
        }

        $origen = match ($mime) {
            'image/png'  => @imagecreatefrompng($ruta),
            'image/jpeg' => @imagecreatefromjpeg($ruta),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($ruta) : false,
            default      => false,
        };
        if ($origen === false) {
            return 'No se pudo leer la imagen. Prueba con otro archivo.';
        }

        // Reduce hasta el lienzo conservando proporciones (nunca agranda).
        $escala = min(1, $lienzo / max($ancho, $alto));
        $nuevoAncho = max(1, (int) round($ancho * $escala));
        $nuevoAlto = max(1, (int) round($alto * $escala));

        $destino = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
        imagealphablending($destino, false);
        imagesavealpha($destino, true);
        imagefilledrectangle($destino, 0, 0, $nuevoAncho, $nuevoAlto, imagecolorallocatealpha($destino, 0, 0, 0, 127));
        imagealphablending($destino, true);

        imagecopyresampled($destino, $origen, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);
        $guardado = imagepng($destino, $ruta, 6);

        imagedestroy($destino);
        imagedestroy($origen);

        if (!$guardado) {
            return 'No se pudo guardar la imagen normalizada.';
        }

        return null;
    }
}
