<?php

declare(strict_types=1);

namespace App\Application\Pagos;

use Psr\Http\Message\UploadedFileInterface;

/**
 * Comprobantes de pago subidos por el estudiante en var/uploads/comprobantes
 * (directorio no versionado en git: /var/uploads/ está en .gitignore).
 *
 * En la base de datos solo se guarda el identificador canónico
 * `/comprobante/<archivo>`. Cada portal lo sirve con su propia ruta
 * protegida (/estudiante/pagos/comprobante/... o /admin/pagos/comprobante/...),
 * de modo que el archivo nunca queda accesible sin sesión.
 *
 * El nombre de archivo es `comprobante_<id_pago>_<timestamp>.<ext>`, lo que
 * permite saber a qué pago pertenece sin consultar el directorio.
 */
final class ComprobanteStorage
{
    /** Prefijo canónico guardado en pago.comprobante_url. */
    public const URL = '/comprobante/';

    /** Tamaño máximo del archivo. */
    public const TAMANO_MAXIMO = 5 * 1024 * 1024;

    /** Extensiones aceptadas. */
    public const EXTENSIONES = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

    /** Prefijo del nombre de archivo en disco. */
    private const PREFIJO_NOMBRE = 'comprobante_';

    public static function directorio(): string
    {
        return dirname(__DIR__, 3) . '/var/uploads/comprobantes';
    }

    /** ¿La URL guardada corresponde a un archivo subido por el estudiante? */
    public static function esSubido(?string $url): bool
    {
        return is_string($url) && str_starts_with($url, self::URL) && !str_contains($url, '..');
    }

    /** Nombre de archivo de una URL canónica ('' si no es válida). */
    public static function archivoDe(?string $url): string
    {
        if (!self::esSubido($url)) {
            return '';
        }
        $archivo = basename((string) $url);
        return preg_match('/^' . preg_quote(self::PREFIJO_NOMBRE, '/') . '[1-9][0-9]*_[0-9]+\.[a-z0-9]+$/D', $archivo) === 1 ? $archivo : '';
    }

    /**
     * id_pago que posee un archivo, o 0 si el nombre no sigue el formato
     * `comprobante_<id_pago>_<timestamp>.<ext>`.
     */
    public static function idPagoDe(string $archivo): int
    {
        if (preg_match('/^' . preg_quote(self::PREFIJO_NOMBRE, '/') . '([1-9][0-9]*)_[0-9]+\.[a-z0-9]+$/D', $archivo, $m) !== 1) {
            return 0;
        }
        if (!in_array(strtolower(pathinfo($archivo, PATHINFO_EXTENSION)), self::EXTENSIONES, true)) {
            return 0;
        }
        return (int) $m[1];
    }

    public static function rutaAbsoluta(string $archivo): string
    {
        return self::directorio() . '/' . basename($archivo);
    }

    /** ¿Existe el archivo de la URL en disco? */
    public static function existe(?string $url): bool
    {
        $archivo = self::archivoDe($url);
        return $archivo !== '' && is_file(self::rutaAbsoluta($archivo));
    }

    /**
     * Devuelve el mensaje de error del archivo subido o null si es válido.
     * Se comprueba antes de escribir nada en la base de datos.
     */
    public static function validar(?UploadedFileInterface $file): ?string
    {
        if ($file === null) {
            return 'Selecciona el comprobante de pago.';
        }
        if ($file->getError() !== UPLOAD_ERR_OK) {
            return 'No se pudo recibir el archivo. Intenta subirlo de nuevo.';
        }
        $size = $file->getSize();
        if ($size === null || $size <= 0) {
            return 'El comprobante está vacío.';
        }
        if ($size > self::TAMANO_MAXIMO) {
            return 'El comprobante no debe superar 5MB.';
        }
        $ext = strtolower(pathinfo((string) $file->getClientFilename(), PATHINFO_EXTENSION));
        if (!in_array($ext, self::EXTENSIONES, true)) {
            return 'Formato no válido: usa JPG, PNG, WEBP o PDF.';
        }
        return null;
    }

    /**
     * Mueve el archivo al directorio de comprobantes. Devuelve la URL
     * canónica que se guarda en pago.comprobante_url.
     */
    public static function guardar(UploadedFileInterface $file, int $idPago): string
    {
        $ext = strtolower(pathinfo((string) $file->getClientFilename(), PATHINFO_EXTENSION));
        $archivo = self::PREFIJO_NOMBRE . $idPago . '_' . time() . '.' . $ext;
        $directorio = self::directorio();
        if (!is_dir($directorio)) {
            @mkdir($directorio, 0755, true);
        }
        $file->moveTo(self::rutaAbsoluta($archivo));
        return self::URL . $archivo;
    }

    /** Borra el archivo si el nombre es válido y pertenece al formato propio. */
    public static function borrar(?string $url): void
    {
        $archivo = self::archivoDe($url);
        if ($archivo === '') {
            return;
        }
        $ruta = self::rutaAbsoluta($archivo);
        if (is_file($ruta)) {
            @unlink($ruta);
        }
    }
}
