<?php

declare(strict_types=1);

namespace Tests\Application\Formato;

use App\Application\Formato\Fecha;
use PHPUnit\Framework\TestCase;

final class FechaTest extends TestCase
{
    public function testFechaLargaEnEspanolSinDependerDelLocale(): void
    {
        $this->assertSame('Miércoles, 28 Octubre 2026', Fecha::larga('2026-10-28 10:00:00'));
        $this->assertSame('Jueves, 1 Enero 2026', Fecha::larga('2026-01-01 00:00:00'));
        // Cadena vacía o inválida → hoy, nunca un error.
        $this->assertMatchesRegularExpression('/^\p{Lu}\w+,\s\d{1,2}\s\p{Lu}\w+\s\d{4}$/u', Fecha::larga(''));
        $this->assertMatchesRegularExpression('/^\p{Lu}\w+,\s\d{1,2}\s\p{Lu}\w+\s\d{4}$/u', Fecha::larga('no-es-fecha'));
    }

    public function testUltimoAccesoRelativo(): void
    {
        $this->assertSame('Nunca ha entrado', Fecha::relativo(null));
        $this->assertSame('Nunca ha entrado', Fecha::relativo(''));
        $this->assertSame('Nunca ha entrado', Fecha::relativo(' basura '));
        $this->assertSame('Activo ahora', Fecha::relativo(date('Y-m-d H:i:s')));
        $this->assertSame('Activo ahora', Fecha::relativo(date('Y-m-d H:i:s', time() + 3600)));
        $this->assertSame('Hace 12 min', Fecha::relativo(date('Y-m-d H:i:s', time() - 12 * 60)));
        $this->assertSame('Hace 3 h', Fecha::relativo(date('Y-m-d H:i:s', time() - 3 * 3600)));
        $this->assertSame('Ayer', Fecha::relativo(date('Y-m-d', strtotime('-1 day')) . ' 00:00:00'));
        $this->assertSame('Hace 6 días', Fecha::relativo(date('Y-m-d', strtotime('-6 days')) . ' 00:00:00'));
        $this->assertSame('12/01/2026', Fecha::relativo('2026-01-12 10:00:00'));
    }

    public function testFechaCorta(): void
    {
        $this->assertSame('—', Fecha::corta(null));
        $this->assertSame('28/10/2026 10:00', Fecha::corta('2026-10-28 10:00:00'));
    }
}
