<?php

declare(strict_types=1);

namespace App\Application\Formato;

/**
 * Formato de dinero en dólares estadounidenses (USD).
 *
 * Convenio de la aplicación: símbolo delante con un espacio, separador de
 * miles "," y decimal "." — igual que la plantilla de pagos del estudiante:
 *
 *     Dinero::monto(15840)        → "$ 15,840.00"
 *     Dinero::monto(15840, 0)     → "$ 15,840"
 *     Dinero::porcentaje(12.4)    → "+12.4%"
 *     Dinero::porcentaje(-3.2)    → "-3.2%"
 */
final class Dinero
{
    public static function monto(float $cantidad, int $decimales = 2): string
    {
        return '$ ' . number_format($cantidad, $decimales, '.', ',');
    }

    /** Variación en porcentaje con formato estadounidense. */
    public static function porcentaje(float $valor, int $decimales = 1): string
    {
        $signo = $valor > 0 ? '+' : '';

        return $signo . number_format($valor, $decimales, '.', ',') . '%';
    }
}
