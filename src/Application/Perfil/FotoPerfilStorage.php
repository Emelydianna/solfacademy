<?php

declare(strict_types=1);

namespace App\Application\Perfil;

/**
 * Fotos de perfil en var/uploads/perfiles (directorio no versionado en git).
 *
 * El nombre de archivo lo genera el controlador con el formato
 * `<prefijo>_<id>_<timestamp>.<ext>`, lo que permite identificar después qué
 * archivo pertenece a qué usuario.
 */
final class FotoPerfilStorage
{
    /** Prefijo de los archivos de un instructor concreto. */
    public static function prefijoInstructor(int $idInstructor): string
    {
        return 'perfil_instructor_' . $idInstructor . '_';
    }

    /** Prefijo de los archivos de un estudiante concreto. */
    public static function prefijoEstudiante(int $idEstudiante): string
    {
        return 'perfil_' . $idEstudiante . '_';
    }

    /**
     * Borra la foto anterior después de guardar la nueva.
     *
     * Solo acepta rutas públicas `/perfil/foto/<archivo>` cuyo nombre empiece
     * por el prefijo del usuario: con `basename()` se descartan directorios y
     * con el prefijo cualquier archivo ajeno, así que no se puede borrar nada
     * fuera de `var/uploads/perfiles` ni de otro usuario.
     */
    public static function borrarAnterior(?string $rutaPublica, string $prefijo): void
    {
        if ($rutaPublica === null || $rutaPublica === '') {
            return;
        }

        $archivo = basename($rutaPublica);
        if ($archivo === '' || !str_starts_with($archivo, $prefijo)) {
            return;
        }

        $ruta = self::directorio() . '/' . $archivo;
        if (is_file($ruta)) {
            @unlink($ruta);
        }
    }

    private static function directorio(): string
    {
        return dirname(__DIR__, 3) . '/var/uploads/perfiles';
    }
}
