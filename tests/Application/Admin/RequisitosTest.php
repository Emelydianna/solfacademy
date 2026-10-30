<?php

declare(strict_types=1);

namespace Tests\Application\Admin;

use App\Application\Academico\RequisitosService;
use App\Application\Security\Csrf;

final class RequisitosTest extends GestionOperativaTestCase
{
    private RequisitosService $requisitos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requisitos = new RequisitosService($this->pdo);
    }

    private function datos(array $cambios = []): array
    {
        return array_merge(['nivel_minimo' => 'Curso inicial aprobado', 'edad_minima' => '14', 'edad_maxima' => '75',
            'conocimientos_previos' => 'Conocimientos básicos', 'necesita_instrumento' => '1', 'puede_alquilar' => '1',
            'descripcion_general' => 'Requisitos para ingresar al curso.'], $cambios);
    }

    public function testCrearEditarYMostrarEnDetallePublico(): void
    {
        $this->assertSame([], $this->requisitos->guardar($this->datos(), 1)['errores']);
        $r = $this->requisitos->guardar($this->datos(['nivel_minimo' => 'Intermedio']), 1);
        $this->assertSame([], $r['errores']);
        $this->assertSame('Intermedio', $this->requisitos->buscar(1)['nivel_minimo']);
        $this->assertSame(1, $this->requisitos->listar(['configurado' => '1'])['total']);
        $this->assertSame(2, $this->requisitos->listar(['configurado' => '0'])['total']);
    }

    public function testValidarEdadesYLongitudesSinEscribir(): void
    {
        foreach (['0', '256', 'x'] as $edad) {
            $this->assertNotEmpty($this->requisitos->guardar($this->datos(['edad_minima' => $edad]), 1)['errores']);
        }
        $r = $this->requisitos->guardar($this->datos(['edad_minima' => '30', 'edad_maxima' => '18']), 1);
        $this->assertArrayHasKey('edad_maxima', $r['errores']);
        $this->assertNotEmpty($this->requisitos->guardar($this->datos(['nivel_minimo' => str_repeat('a', 101)]), 1)['errores']);
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM requisito_curso')->fetchColumn());
        $this->assertNotEmpty($this->requisitos->guardar($this->datos(), 999)['errores']);
    }

    public function testHttpCsrfEscapadoYErroresConservados(): void
    {
        $app = $this->appAdmin();
        $r = $app->handle($this->createRequest('GET', '/admin/requisitos'));
        $this->assertSame(200, $r->getStatusCode());
        $r = $app->handle($this->createRequest('GET', '/admin/requisitos/1'));
        $this->assertSame(200, $r->getStatusCode());
        $r = $app->handle($this->createRequest('POST', '/admin/requisitos/1')->withParsedBody($this->datos(['_csrf' => Csrf::token(), 'edad_minima' => '500'])));
        $this->assertSame(303, $r->getStatusCode());
        $this->assertStringContainsString('aria-invalid="true"', (string) $app->handle($this->createRequest('GET', '/admin/requisitos/1'))->getBody());
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM requisito_curso')->fetchColumn());
    }

    public function testGuardarEmiteToastDeExitoEnElFormularioAlQueRedirige(): void
    {
        $app = $this->appAdmin();

        $r = $app->handle($this->createRequest('POST', '/admin/requisitos/1')->withParsedBody($this->datos(['_csrf' => Csrf::token()])));
        $this->assertSame(303, $r->getStatusCode());

        $html = (string) $app->handle($this->createRequest('GET', $r->getHeaderLine('Location')))->getBody();
        $this->assertStringContainsString('Requisitos guardados correctamente', $html, 'El mensaje debe mostrarse en la página destino.');
        $this->assertStringContainsString("solfAviso('success'", $html, 'El guardado debe emitir el toast de SweetAlert2.');
    }
}
