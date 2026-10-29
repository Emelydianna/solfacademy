<?php

declare(strict_types=1);

namespace App\Application\Imagen;

use DomainException;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Imágenes de referencia de los cursos (var/uploads/cursos).
 *
 * El directorio no está versionado (/.gitignore incluye /var/uploads/).
 * El nombre de fichero no lleva el id: se guarda antes del INSERT para
 * que la columna `curso.imagen` viaje en la misma escritura. El MIME se
 * comprueba con finfo sobre los primeros bytes (no por el nombre del
 * archivo) y la imagen se normaliza con GD a PNG.
 */
final class ImagenCursoStorage
{
    /** Límite del fichero y formatos admitidos (MIME real, no por nombre). */
    public const MAX_BYTES = 2 * 1024 * 1024;

    /** El SVG queda fuera: es HTML ejecutable y la ruta es pública (XSS). */
    public const MIME = [
        'image/png'  => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];

    /** Lado máximo del resultado (no agranda) y mínimos/máximos admitidos. */
    public const LIENZO = 1600;

    public const MINIMO = 200;

    public const MAXIMO = 4000;

    private const PREFIJO = 'curso_';

    private string $directorio;

    public function __construct(?string $directorio = null)
    {
        $this->directorio = $directorio ?? dirname(__DIR__, 3) . '/var/uploads/cursos';
    }

    /**
     * Valida, normaliza y escribe la imagen; devuelve su nombre de fichero.
     *
     * @throws DomainException con el mensaje apto para el usuario.
     */
    public function guardar(UploadedFileInterface $file): string
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new DomainException('No se pudo recibir el archivo. Inténtalo de nuevo.');
        }
        $tamanio = $file->getSize();
        if ($tamanio === null || $tamanio <= 0) {
            throw new DomainException('El archivo está vacío.');
        }
        if ($tamanio > self::MAX_BYTES) {
            throw new DomainException('La imagen no debe superar 2MB.');
        }

        $stream = $file->getStream();
        $stream->rewind();
        $cabecera = $stream->read(8192);
        $stream->rewind();

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo !== false ? (string) finfo_buffer($finfo, $cabecera) : '';
        if ($finfo !== false) {
            finfo_close($finfo);
        }
        if (!isset(self::MIME[$mime])) {
            throw new DomainException('Formato no válido: usa PNG, JPG o WEBP (no se aceptan SVG).');
        }

        if (!is_dir($this->directorio) && !@mkdir($this->directorio, 0755, true) && !is_dir($this->directorio)) {
            throw new DomainException('No se pudo crear la carpeta de imágenes de cursos.');
        }

        // Siempre extensión .png: la normalización reescribe como PNG. El
        // aleatorio largo evita colisiones entre subidas del mismo segundo.
        $nombre = self::PREFIJO . time() . '_' . bin2hex(random_bytes(8)) . '.png';
        $ruta = $this->directorio . '/' . $nombre;
        $file->moveTo($ruta);

        // Comprueba dimensiones, reduce hasta el lienzo y reescribe como
        // PNG conservando las proporciones originales.
        $error = ImagenNormalizador::normalizar($ruta, $mime, self::LIENZO, self::MINIMO, self::MAXIMO);
        if ($error !== null) {
            @unlink($ruta);
            throw new DomainException($error);
        }

        return $nombre;
    }

    /** Ruta absoluta del fichero si pertenece al directorio y existe. */
    public function ruta(?string $archivo): ?string
    {
        if ($archivo === null || $archivo === '') {
            return null;
        }
        $limpio = basename($archivo);
        $ruta = $this->directorio . '/' . $limpio;
        $real = realpath($ruta);
        $base = realpath($this->directorio);
        if ($real === false || $base === false || !str_starts_with($real, $base . DIRECTORY_SEPARATOR)) {
            return null;
        }
        return is_file($real) && !is_link($real) ? $real : null;
    }

    public function borrar(?string $archivo): void
    {
        $ruta = $this->ruta($archivo);
        if ($ruta !== null) {
            @unlink($ruta);
        }
    }
}
