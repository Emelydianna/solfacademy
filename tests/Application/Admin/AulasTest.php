<?php

declare(strict_types=1);

namespace Tests\Application\Admin;

use App\Application\Admin\AulasService;
use App\Application\Admin\MigracionGestion;
use App\Application\Security\Csrf;
use DateTimeImmutable;

final class AulasTest extends GestionOperativaTestCase
{
    private AulasService $aulas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aulas = new AulasService($this->pdo);
    }

    private function datos(array $cambios = []): array
    {
        return array_merge(['nombre' => 'Salón de ensayo', 'capacidad' => '15', 'recursos' => 'Piano; Atriles; Sillas', 'ubicacion' => 'Primera planta'], $cambios);
    }

    public function testCrearEditarYNoCambiarEstadoMedianteAsignacionMasiva(): void
    {
        $r = $this->aulas->guardar($this->datos(['activa' => '0', 'estado' => 'Mantenimiento']));
        $this->assertSame([], $r['errores']);
        $id = $r['id'];
        $this->assertSame('Activa', $this->aulas->buscar($id)['estado']);
        $r = $this->aulas->guardar($this->datos(['nombre' => 'Salón editado', 'activa' => '0']), $id);
        $this->assertSame([], $r['errores']);
        $this->assertSame('Salón editado', $this->aulas->buscar($id)['nombre']);
        $this->assertSame('Activa', $this->aulas->buscar($id)['estado']);
        $this->assertSame(1, $this->aulas->listar(['q' => 'Atriles'])['total']);
    }

    public function testValidacionesCapacidadYLongitudes(): void
    {
        foreach (['0', '-1', '256', '12.5', 'abc'] as $capacidad) {
            $this->assertArrayHasKey('capacidad', $this->aulas->guardar($this->datos(['capacidad' => $capacidad]))['errores']);
        }
        $r = $this->aulas->guardar($this->datos(['nombre' => '', 'recursos' => str_repeat('a', 256), 'ubicacion' => str_repeat('a', 151)]));
        foreach (['nombre', 'recursos', 'ubicacion'] as $campo) {
            $this->assertArrayHasKey($campo, $r['errores']);
        }
        $this->assertSame(3, (int) $this->pdo->query('SELECT COUNT(*) FROM aula')->fetchColumn());
    }

    public function testOcupacionSeCalculaConDiasHorasFechasYSinDuplicados(): void
    {
        $g = $this->crearGrupo();
        $this->pdo->exec("INSERT INTO horario (id_grupo, dia_semana, hora_inicio, hora_fin) VALUES ($g, 'Lunes', '09:00', '10:00')");
        $ahora = new DateTimeImmutable('2030-01-07 09:30:00');
        $r = $this->aulas->listar(['estado' => 'Ocupada'], $ahora);
        $this->assertSame(1, $r['total']);
        $this->assertSame('Ocupada', $r['filas'][0]['estado_visible']);
        $this->assertSame(3, $this->aulas->listar([], $ahora)['total']);
        $this->assertSame(1, $this->aulas->listar(['estado' => 'Disponible'], $ahora)['total']);
        foreach (['2030-01-07 10:00:00', '2030-01-08 09:30:00', '2031-01-06 09:30:00'] as $momento) {
            $this->assertSame(0, $this->aulas->listar(['estado' => 'Ocupada'], new DateTimeImmutable($momento))['total']);
        }
        $this->pdo->exec("UPDATE grupo SET estado = 'cancelado'");
        $this->assertSame(0, $this->aulas->listar(['estado' => 'Ocupada'], $ahora)['total']);
    }

    public function testMantenimientoDeshabilitaAulaYBloqueaGruposNuevos(): void
    {
        $this->assertSame('error', $this->aulas->cambiarEstado(2, 'Mantenimiento', false)['tipo']);
        $this->assertSame('error', $this->aulas->cambiarEstado(2, 'Ocupada', true)['tipo']);
        $this->assertSame('exito', $this->aulas->cambiarEstado(2, 'Mantenimiento', true)['tipo']);
        $this->assertSame('Mantenimiento', $this->aulas->buscar(2)['estado']);
        $this->assertSame(0, (int) $this->aulas->buscar(2)['activa']);
        $this->assertSame(1, $this->aulas->listar(['estado' => 'Mantenimiento'])['total']);
        $this->assertSame(1, $this->aulas->listar(['estado' => 'Inactiva'])['total']);
        $this->assertNotEmpty($this->grupos->guardar($this->datosGrupo(['id_aula' => '2']))['errores']);
        $this->assertSame('exito', $this->aulas->cambiarEstado(2, 'Mantenimiento', true)['tipo']);
        $this->assertSame('exito', $this->aulas->cambiarEstado(2, 'Activa', true)['tipo']);
        $this->assertSame('General', $this->aulas->buscar(2)['tipo_inactividad']);
        $this->crearGrupo(['id_aula' => '2']);
    }

    public function testNoReducirCapacidadNiDeshabilitarConGruposAbiertos(): void
    {
        $g = $this->crearGrupo();
        $this->matricular($g);
        $r = $this->aulas->guardar($this->datos(['capacidad' => '11']), 1);
        $this->assertNotEmpty($r['errores']);
        $this->assertSame(12, (int) $this->aulas->buscar(1)['capacidad']);
        $this->assertSame('error', $this->aulas->cambiarEstado(1, 'Inactiva', true)['tipo']);
        $this->assertSame('error', $this->aulas->cambiarEstado(1, 'Mantenimiento', true)['tipo']);
        $this->assertSame('Activa', $this->pdo->query('SELECT estado FROM inscripcion')->fetchColumn());
        $this->pdo->exec("UPDATE inscripcion SET estado = 'Finalizada'");
        $this->assertSame([], $this->grupos->guardar($this->datosGrupo(['estado' => 'finalizado']), $g)['errores']);
        $this->assertSame([], $this->aulas->guardar($this->datos(['capacidad' => '6']), 1)['errores']);
        $this->assertSame('exito', $this->aulas->cambiarEstado(1, 'Mantenimiento', true)['tipo']);
        $this->assertCount(1, $this->aulas->agenda(1));
        $this->assertSame('Finalizada', $this->pdo->query('SELECT estado FROM inscripcion')->fetchColumn());
    }

    public function testPaginacionYBusquedaPorIdYUbicacion(): void
    {
        for ($n = 0; $n < 10; $n++) {
            $this->assertSame([], $this->aulas->guardar($this->datos(['nombre' => 'Aula prueba ' . $n]))['errores']);
        }
        $lista = $this->aulas->listar(['pagina' => '999']);
        $this->assertSame(2, $lista['pagina']);
        $this->assertSame(13, $lista['total']);
        $this->assertCount(3, $lista['filas']);
        $this->assertSame(10, $this->aulas->listar(['q' => 'Primera planta'])['total']);
        $this->assertSame(1, $this->aulas->listar(['q' => '#1'])['total']);
    }

    public function testMigracionIdempotenteNoInfiereMantenimientoDeAulasInactivas(): void
    {
        MigracionGestion::columnas($this->pdo);
        MigracionGestion::columnas($this->pdo);
        $this->assertSame('Inactiva', $this->aulas->buscar(3)['estado']);
        $this->assertSame('General', $this->aulas->buscar(3)['tipo_inactividad']);
        $this->assertSame(3, (int) $this->pdo->query('SELECT COUNT(*) FROM aula')->fetchColumn());
    }

    public function testFalloDeEscrituraNoDejaEdicionParcial(): void
    {
        $this->pdo->exec('ALTER TABLE aula ADD CONSTRAINT prueba_aula CHECK (capacidad <> 19)');
        try {
            $this->aulas->guardar($this->datos(['nombre' => 'NO GUARDAR', 'capacidad' => '19']), 2);
            $this->fail('Debió fallar al escribir la capacidad.');
        } catch (\PDOException $e) {
            $this->assertSame('Aula 2', $this->aulas->buscar(2)['nombre']);
            $this->assertSame(20, (int) $this->aulas->buscar(2)['capacidad']);
            $this->assertFalse($this->pdo->inTransaction());
        }
    }

    public function testHttpFormulariosCsrfEscapadoYConfirmacionDeEstado(): void
    {
        $this->aulas->guardar($this->datos(['nombre' => '<script>alert(1)</script>', 'recursos' => '<img src=x onerror=alert(1)>']), 2);
        $app = $this->appAdmin();
        foreach (['/admin/aulas', '/admin/aulas/nuevo', '/admin/aulas/2', '/admin/aulas/2/editar'] as $ruta) {
            $r = $app->handle($this->createRequest('GET', $ruta));
            $this->assertSame(200, $r->getStatusCode());
            $html = (string) $r->getBody();
            $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
            $this->assertStringNotContainsString('<img src=x', $html);
            $dom = new \DOMDocument();
            @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
            $xpath = new \DOMXPath($dom);
            foreach ($xpath->query('//form[@method="post"]') as $form) {
                $this->assertSame(1, $xpath->query('.//input[@name="_csrf"]', $form)->length);
            }
        }
        $r = $app->handle($this->createRequest('POST', '/admin/aulas')->withParsedBody($this->datos(['_csrf' => Csrf::token()])));
        $this->assertSame(303, $r->getStatusCode());
        $r = $app->handle($this->createRequest('POST', '/admin/aulas/2/estado')->withParsedBody(['_csrf' => Csrf::token(), 'confirmar' => '1', 'estado' => 'Mantenimiento']));
        $this->assertSame(303, $r->getStatusCode());
        $this->assertSame('Mantenimiento', $this->aulas->buscar(2)['estado']);
        $r = $app->handle($this->createRequest('POST', '/admin/aulas')->withParsedBody($this->datos(['_csrf' => Csrf::token(), 'capacidad' => '999'])));
        $this->assertSame(422, $r->getStatusCode());
        $this->assertStringContainsString('value="999"', (string) $r->getBody());
    }
}
