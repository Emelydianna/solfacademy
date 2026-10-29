<?php

declare(strict_types=1);

namespace App\Application\Config;

use App\Application\Admin\GestionAcademicaService;
use App\Application\Imagen\ImagenNormalizador;
use PDO;
use PDOException;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Datos de la academia que opera esta instalación (tabla `configuracion`,
 * una sola fila con id = 1).
 *
 * Lo que se configure aquí se refleja en los tres portales, en la web
 * pública, en el login y en los PDF (boletín, comprobantes y reportes),
 * gracias a ViewDataMiddleware, que la inyecta como `$config` en todas
 * las plantillas.
 */
final class ConfiguracionService
{
    /** Fila única. */
    public const ID = 1;

    /** Límite del logo y formatos admitidos (por MIME real, no por nombre). */
    public const LOGO_MAX_BYTES = 2 * 1024 * 1024;

    public const LOGO_PREFIJO = 'logo_';

    /**
     * El SVG queda fuera a propósito: es HTML ejecutable y la ruta del logo
     * es pública, así que permitirlo abriría una puerta a XSS.
     */
    public const LOGO_MIME = [
        'image/png'  => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];

    /**
     * Formato estándar del logo al subirlo (mismo trato que las fotos de
     * perfil): nunca se estira ni se recorta, pero el archivo se normaliza
     * a un lienzo máximo y se guarda siempre como PNG con proporciones
     * originales, para que se vea igual en todos los sitios.
     */
    public const LOGO_LIENZO = 512;

    /** Mínimo útil por lado: por debajo el logo se ve borroso. */
    public const LOGO_MINIMO = 64;

    /** Máximo por lado aceptado (criterio de prudencia como en las fotos). */
    public const LOGO_MAXIMO = 4000;

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // =========================================================
    // Lectura
    // =========================================================

    /**
     * Configuración vigente. Si la tabla todavía no existe (instalación sin
     * migrar) o la base de datos no responde, devuelve los valores de fábrica
     * para que ninguna página se rompa.
     *
     * @return array<string, mixed>
     */
    public function obtener(): array
    {
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM configuracion WHERE id_configuracion = :id');
            $stmt->execute(['id' => self::ID]);
            $fila = $stmt->fetch();
        } catch (\Throwable $e) {
            return self::porDefecto();
        }

        return $fila ? array_merge(self::porDefecto(), $fila) : self::porDefecto();
    }

    /** Valores de fábrica: los de la web tal y como se veía antes de este módulo. */
    public static function porDefecto(): array
    {
        return [
            'id_configuracion' => self::ID,
            'nombre'           => 'SolfAcademy',
            'lema'             => 'Academia de Música',
            'logo_url'         => null,
            'email_contacto'   => 'info@solfacademy.com',
            'telefono'         => '+34 912 345 678',
            'direccion'        => 'Calle de la Música 123, Madrid',
            'ciudad'           => 'Madrid, España',
            'facebook'         => null,
            'instagram'        => null,
            'youtube'          => null,
            'pie_pagina'       => '© 2026 SolfAcademy · Todos los derechos reservados.',
            'nota_pdf'         => null,
            'actualizado_en'   => null,
        ];
    }

    /**
     * Título de pestaña: retira el sufijo histórico "— SolfAcademy" que
     * pasan los controladores y añade el nombre configurado.
     */
    public static function titulo(string $pagina, array $config): string
    {
        $base = trim((string) preg_replace('/\s*[—-]\s*SolfAcademy\s*$/u', '', $pagina));
        $nombre = trim((string) ($config['nombre'] ?? '')) !== ''
            ? trim((string) $config['nombre'])
            : 'SolfAcademy';

        return $base !== '' ? $base . ' — ' . $nombre : $nombre;
    }

    /** Ruta pública del logo, o null si no hay logo configurado. */
    public static function logoPublico(?string $logoUrl): ?string
    {
        return $logoUrl !== null && $logoUrl !== '' ? '/marca/logo' : null;
    }

    public static function directorio(): string
    {
        return dirname(__DIR__, 3) . '/var/uploads/configuracion';
    }

    /** Archivo de logo en disco (solo existe uno a la vez), o null. */
    public static function archivoLogo(): ?string
    {
        $directorio = self::directorio();
        if (!is_dir($directorio)) {
            return null;
        }
        $candidatos = glob($directorio . '/' . self::LOGO_PREFIJO . '*') ?: [];
        foreach ($candidatos as $archivo) {
            if (preg_match('/^' . self::LOGO_PREFIJO . '[0-9]+\.(?:png|jpe?g|webp)$/i', basename($archivo)) === 1) {
                return $archivo;
            }
        }

        return null;
    }

    // =========================================================
    // Escritura
    // =========================================================

    /**
     * Guarda los datos de la academia con validación campo a campo.
     *
     * @param array<string, mixed> $datos
     * @return array{valores: array<string, string>, errores: array<string, string>}
     */
    public function guardar(array $datos): array
    {
        $v = [];
        $campos = ['nombre', 'lema', 'email_contacto', 'telefono', 'direccion', 'ciudad',
            'facebook', 'instagram', 'youtube', 'pie_pagina', 'nota_pdf'];
        foreach ($campos as $campo) {
            $v[$campo] = GestionAcademicaService::texto($datos, $campo);
        }

        $errores = [];
        if (mb_strlen($v['nombre']) < 3 || mb_strlen($v['nombre']) > 80) {
            $errores['nombre'] = 'El nombre de la academia debe tener entre 3 y 80 caracteres.';
        }
        if (mb_strlen($v['lema']) < 3 || mb_strlen($v['lema']) > 120) {
            $errores['lema'] = 'El lema debe tener entre 3 y 120 caracteres.';
        }
        if (
            $v['email_contacto'] !== ''
            && (mb_strlen($v['email_contacto']) > 150 || !filter_var($v['email_contacto'], FILTER_VALIDATE_EMAIL))
        ) {
            $errores['email_contacto'] = 'Indica un correo válido de hasta 150 caracteres.';
        }

        $longitudes = ['telefono' => 30, 'direccion' => 180, 'ciudad' => 120,
            'pie_pagina' => 180, 'nota_pdf' => 180];
        foreach ($longitudes as $campo => $max) {
            if (mb_strlen($v[$campo]) > $max) {
                $errores[$campo] = "Máximo $max caracteres.";
            }
        }

        foreach (['facebook', 'instagram', 'youtube'] as $red) {
            if ($v[$red] === '') {
                continue;
            }
            $valida = mb_strlen($v[$red]) <= 150
                && preg_match('#^https?://#i', $v[$red]) === 1
                && filter_var($v[$red], FILTER_VALIDATE_URL) !== false;
            if (!$valida) {
                $errores[$red] = 'Indica la URL completa (https://…) o déjalo vacío.';
            }
        }

        $resultado = ['valores' => $v, 'errores' => $errores];
        if ($errores) {
            return $resultado;
        }

        $sql = 'INSERT INTO configuracion
                    (id_configuracion, nombre, lema, email_contacto, telefono, direccion, ciudad,
                     facebook, instagram, youtube, pie_pagina, nota_pdf, actualizado_en)
                VALUES (:id, :nombre, :lema, :email, :telefono, :direccion, :ciudad,
                        :facebook, :instagram, :youtube, :pie, :nota, NOW())
                ON DUPLICATE KEY UPDATE
                    nombre = VALUES(nombre), lema = VALUES(lema), email_contacto = VALUES(email_contacto),
                    telefono = VALUES(telefono), direccion = VALUES(direccion), ciudad = VALUES(ciudad),
                    facebook = VALUES(facebook), instagram = VALUES(instagram), youtube = VALUES(youtube),
                    pie_pagina = VALUES(pie_pagina), nota_pdf = VALUES(nota_pdf), actualizado_en = NOW()';

        try {
            $this->pdo->prepare($sql)->execute([
                'id'        => self::ID,
                'nombre'    => $v['nombre'],
                'lema'      => $v['lema'],
                'email'     => $v['email_contacto'] !== '' ? $v['email_contacto'] : null,
                'telefono'  => $v['telefono'] !== '' ? $v['telefono'] : null,
                'direccion' => $v['direccion'] !== '' ? $v['direccion'] : null,
                'ciudad'    => $v['ciudad'] !== '' ? $v['ciudad'] : null,
                'facebook'  => $v['facebook'] !== '' ? $v['facebook'] : null,
                'instagram' => $v['instagram'] !== '' ? $v['instagram'] : null,
                'youtube'   => $v['youtube'] !== '' ? $v['youtube'] : null,
                'pie'       => $v['pie_pagina'] !== '' ? $v['pie_pagina'] : null,
                'nota'      => $v['nota_pdf'] !== '' ? $v['nota_pdf'] : null,
            ]);
        } catch (PDOException $e) {
            if ((string) $e->getCode() === '40001') {
                $resultado['errores'] = ['general' => 'Hubo un cambio simultáneo. Recarga e inténtalo de nuevo.'];
                return $resultado;
            }
            throw $e;
        }

        return $resultado;
    }

    /**
     * Sube el logo: se valida el MIME real (no el nombre del archivo), se
     * guarda un único fichero y se registra en la configuración.
     *
     * @return array{ok: bool, mensaje: string}
     */
    public function guardarLogo(?UploadedFileInterface $file): array
    {
        if ($file === null) {
            return ['ok' => false, 'mensaje' => 'Selecciona una imagen para el logo.'];
        }
        if ($file->getError() !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'mensaje' => 'No se pudo recibir el archivo. Inténtalo de nuevo.'];
        }

        $tamanio = $file->getSize();
        if ($tamanio === null || $tamanio <= 0) {
            return ['ok' => false, 'mensaje' => 'El archivo está vacío.'];
        }
        if ($tamanio > self::LOGO_MAX_BYTES) {
            return ['ok' => false, 'mensaje' => 'El logo no debe superar 2MB.'];
        }

        try {
            $stream = $file->getStream();
            $stream->rewind();
            $cabecera = $stream->read(8192);
            $stream->rewind();

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $finfo !== false ? (string) finfo_buffer($finfo, $cabecera) : '';
            if ($finfo !== false) {
                finfo_close($finfo);
            }
            if (!isset(self::LOGO_MIME[$mime])) {
                return ['ok' => false, 'mensaje' => 'Formato no válido: usa PNG, JPG o WEBP (no se aceptan SVG).'];
            }

            $directorio = self::directorio();
            if (!is_dir($directorio) && !@mkdir($directorio, 0755, true) && !is_dir($directorio)) {
                return ['ok' => false, 'mensaje' => 'No se pudo crear la carpeta de logos.'];
            }

            $anterior = self::archivoLogo();
            $nombre = self::LOGO_PREFIJO . time() . random_int(10, 99) . '.png';
            $ruta = $directorio . '/' . $nombre;
            $file->moveTo($ruta);

            // Formato estándar: comprueba dimensiones, reduce hasta el lienzo
            // y reescribe como PNG conservando las proporciones originales.
            $error = ImagenNormalizador::normalizar(
                $ruta,
                $mime,
                self::LOGO_LIENZO,
                self::LOGO_MINIMO,
                self::LOGO_MAXIMO
            );
            if ($error !== null) {
                @unlink($ruta);
                return ['ok' => false, 'mensaje' => $error];
            }

            $this->asegurarFila();
            $this->pdo->prepare('UPDATE configuracion SET logo_url = :logo, actualizado_en = NOW()
                WHERE id_configuracion = :id')
                ->execute(['logo' => $nombre, 'id' => self::ID]);

            if ($anterior !== null && is_file($anterior)) {
                @unlink($anterior);
            }
        } catch (\Throwable $e) {
            return ['ok' => false, 'mensaje' => 'No se pudo guardar el logo. Inténtalo de nuevo.'];
        }

        return ['ok' => true, 'mensaje' => 'Logo actualizado: se verá en los portales y en la web pública.'];
    }

    /** Quita el logo y vuelve al icono musical de fábrica. */
    public function quitarLogo(): array
    {
        $archivo = self::archivoLogo();
        if ($archivo !== null && is_file($archivo)) {
            @unlink($archivo);
        }

        $this->asegurarFila();
        $this->pdo->prepare('UPDATE configuracion SET logo_url = NULL, actualizado_en = NOW()
            WHERE id_configuracion = :id')
            ->execute(['id' => self::ID]);

        return ['ok' => true, 'mensaje' => 'Logo eliminado: se muestra de nuevo el icono musical.'];
    }

    /** Garantiza que exista la fila única antes de un UPDATE puntual. */
    private function asegurarFila(): void
    {
        $defecto = self::porDefecto();
        $this->pdo->prepare('INSERT IGNORE INTO configuracion (id_configuracion, nombre, lema)
            VALUES (:id, :nombre, :lema)')
            ->execute(['id' => self::ID, 'nombre' => $defecto['nombre'], 'lema' => $defecto['lema']]);
    }
}
