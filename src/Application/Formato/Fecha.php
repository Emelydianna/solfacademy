<?php

declare(strict_types=1);

namespace App\Application\Formato;

/**
 * Fechas en español para las vistas, sin depender de setlocale() del
 * servidor (que casi nunca trae los paquetes de idioma instalados).
 *
 *     Fecha::larga('2026-10-28 09:15:00') → "Martes, 28 Octubre 2026"
 *     Fecha::relativo(...)                → "Activo ahora" / "Hace 12 min" / "Ayer"
 */
final class Fecha
{
    /** date('N'): 1 = lunes … 7 = domingo. */
    private const DIAS = [
        1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves',
        5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo',
    ];

    private const MESES = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];

    /**
     * Fecha completa con día y mes en mayúscula inicial:
     * "Lunes, 28 Octubre 2026". Sin hora.
     */
    public static function larga(?string $fecha = null): string
    {
        $ts = $fecha === null || $fecha === '' ? time() : strtotime($fecha);
        if ($ts === false) {
            $ts = time();
        }

        return self::DIAS[(int) date('N', $ts)] . ', '
            . (int) date('j', $ts) . ' '
            . self::MESES[(int) date('n', $ts)] . ' '
            . date('Y', $ts);
    }

    /**
     * "Último acceso" legible a partir de un DATETIME.
     *
     *  - vacío            → Nunca ha entrado
     *  - menos de 5 min   → Activo ahora
     *  - menos de 1 h     → Hace 12 min
     *  - menos de 24 h    → Hace 3 h (aunque cruce la medianoche)
     *  - ayer             → Ayer
     *  - menos de 30 días → Hace 6 días
     *  - más antiguo      → 12/01/2026
     */
    public static function relativo(?string $fecha): string
    {
        if ($fecha === null || $fecha === '') {
            return 'Nunca ha entrado';
        }
        $ts = strtotime($fecha);
        if ($ts === false) {
            return 'Nunca ha entrado';
        }

        // Relojes desfasados (o datos futuros): se trata como "ahora mismo".
        $minutos = max(0, (time() - $ts) / 60);

        if ($minutos < 5) {
            return 'Activo ahora';
        }
        if ($minutos < 60) {
            return 'Hace ' . (int) $minutos . ' min';
        }
        if ($minutos < 60 * 24) {
            return 'Hace ' . (int) ($minutos / 60) . ' h';
        }
        if (date('Y-m-d', $ts) === date('Y-m-d', strtotime('-1 day'))) {
            return 'Ayer';
        }
        if ($minutos < 60 * 24 * 30) {
            return 'Hace ' . (int) ($minutos / (60 * 24)) . ' días';
        }

        return date('d/m/Y', $ts);
    }

    /** Fecha y hora absolutas para la ficha: "28/10/2026 09:15". */
    public static function corta(?string $fecha): string
    {
        if ($fecha === null || $fecha === '') {
            return '—';
        }
        $ts = strtotime($fecha);

        return $ts === false ? '—' : date('d/m/Y H:i', $ts);
    }
}
