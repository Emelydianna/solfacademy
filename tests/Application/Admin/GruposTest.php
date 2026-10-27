<?php

declare(strict_types=1);

namespace Tests\Application\Admin;

use App\Application\Admin\GruposService;
use App\Application\Estudiante\ReservaService;
use App\Application\Security\Csrf;

final class GruposTest extends GestionOperativaTestCase
{
    public function testCrearEditarGrupoYHorarios(): void
    {
        $id = $this->crearGrupo();
        $datos = $this->datosGrupo(['horarios' => [['dia_semana' => 'Martes', 'hora_inicio' => '10:00', 'hora_fin' => '11:00'], ['dia_semana' => 'Jueves', 'hora_inicio' => '12:00', 'hora_fin' => '13:00']]]);
        $r = $this->grupos->guardar($datos, $id);
        $this->assertSame([], $r['errores']);
        $this->assertCount(2, $this->grupos->buscar($id)['horarios']);
        $this->assertSame('Martes', $this->grupos->buscar($id)['horarios'][0]['dia_semana']);
        $this->assertSame(1, $this->grupos->listar(['q' => 'Ana', 'curso' => '1', 'estado' => 'activo'])['total']);
    }

    public function testRechazarFechasHorasYFranjasSuperpuestas(): void
    {
        $r = $this->grupos->guardar($this->datosGrupo(['fecha_inicio' => '2030-02-30', 'horarios' => [
            ['dia_semana' => 'Lunes', 'hora_inicio' => '09:00', 'hora_fin' => '10:00'],
            ['dia_semana' => 'Lunes', 'hora_inicio' => '09:30', 'hora_fin' => '11:00']]]));
        $this->assertArrayHasKey('fecha_inicio', $r['errores']);
        $this->assertArrayHasKey('horarios', $r['errores']);
        $r = $this->grupos->guardar($this->datosGrupo(['horarios' => [['dia_semana' => 'Lunes', 'hora_inicio' => '25:00', 'hora_fin' => '26:00']]]));
        $this->assertArrayHasKey('horarios', $r['errores']);
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM grupo')->fetchColumn());
    }

    public function testInstructorEspecializadoActivoYAulaCapaz(): void
    {
        foreach ([['id_instructor' => '2'], ['id_instructor' => '4'], ['id_aula' => '3'], ['capacidad_maxima' => '13'], ['id_curso' => '999']] as $cambio) {
            $this->assertNotEmpty($this->grupos->guardar($this->datosGrupo($cambio))['errores']);
        }
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM grupo')->fetchColumn());
    }

    public function testCruceInstructorOAulaYHorariosContiguos(): void
    {
        $this->crearGrupo();
        $r = $this->grupos->guardar($this->datosGrupo(['id_curso' => '2', 'id_aula' => '2']));
        $this->assertStringContainsString('Cruce', $r['errores']['general']);
        $r = $this->grupos->guardar($this->datosGrupo(['id_instructor' => '3']));
        $this->assertStringContainsString('Cruce', $r['errores']['general']);
        $this->crearGrupo(['horarios' => [['dia_semana' => 'Lunes', 'hora_inicio' => '10:00', 'hora_fin' => '11:00']]]);
        $this->crearGrupo(['fecha_inicio' => '2031-01-01', 'fecha_fin' => '2031-12-31']);
    }

    public function testFechasSolapadasSinOcurrenciaDelDiaNoGeneranFalsoCruce(): void
    {
        $this->assertFalse(GruposService::coincideDia('2030-01-08', '2030-01-09', 'Lunes'));
        $this->assertTrue(GruposService::coincideDia('2030-01-07', '2030-01-07', 'Lunes'));
        $this->crearGrupo(['fecha_inicio' => '2030-01-07', 'fecha_fin' => '2030-01-08']);
        $this->crearGrupo(['fecha_inicio' => '2030-01-08', 'fecha_fin' => '2030-01-14']);
    }

    public function testMaximoDeGruposYGruposCerradosNoOcupanLimite(): void
    {
        $this->pdo->exec('UPDATE curso SET max_grupos = 1 WHERE id_curso = 1');
        $id = $this->crearGrupo();
        $r = $this->grupos->guardar($this->datosGrupo(['id_instructor' => '3', 'id_aula' => '2']));
        $this->assertStringContainsString('máximo', $r['errores']['general']);
        $this->assertSame([], $this->grupos->guardar($this->datosGrupo(['estado' => 'cancelado']), $id)['errores']);
        $this->crearGrupo();
    }

    public function testNoReducirCupoCerrarOCambiarCursoConMatriculas(): void
    {
        $id = $this->crearGrupo();
        $this->matricular($id);
        $this->matricular($id, 'Pendiente', 2);
        foreach ([['capacidad_maxima' => '1'], ['estado' => 'cancelado'], ['estado' => 'finalizado'], ['estado' => 'planeado'], ['id_curso' => '2']] as $cambio) {
            $this->assertNotEmpty($this->grupos->guardar($this->datosGrupo($cambio), $id)['errores']);
        }
        $this->assertSame('activo', $this->grupos->buscar($id)['estado']);
        $this->assertCount(2, $this->grupos->alumnos($id));
    }

    public function testReasignacionRequiereConfirmacionYNoMueveClasesExtras(): void
    {
        $id = $this->crearGrupo();
        $r = $this->grupos->guardar($this->datosGrupo(['id_instructor' => '3']), $id);
        $this->assertStringContainsString('Confirma', $r['errores']['general']);
        $this->pdo->exec("INSERT INTO reserva_clase_extra (id_estudiante, id_grupo, id_instructor, fecha, hora_inicio, hora_fin, motivo) VALUES (1, $id, 1, '2030-01-07', '12:00', '13:00', 'Práctica')");
        $r = $this->grupos->guardar($this->datosGrupo(['id_instructor' => '3', 'confirmar_reasignacion' => '1']), $id);
        $this->assertStringContainsString('extras', $r['errores']['general']);
        $this->pdo->exec("UPDATE reserva_clase_extra SET estado = 'Cancelada'");
        $this->assertSame([], $this->grupos->guardar($this->datosGrupo(['id_instructor' => '3', 'confirmar_reasignacion' => '1']), $id)['errores']);
        $this->assertSame(3, (int) $this->grupos->buscar($id)['id_instructor']);
        $this->assertSame(1, (int) $this->pdo->query('SELECT id_instructor FROM reserva_clase_extra')->fetchColumn());
    }

    public function testConflictoConClaseExtraRevierteEdicionDeHorarios(): void
    {
        $id = $this->crearGrupo();
        $this->pdo->exec("INSERT INTO reserva_clase_extra (id_estudiante, id_grupo, id_instructor, fecha, hora_inicio, hora_fin, motivo) VALUES (1, $id, 1, '2030-01-07', '12:00', '13:00', 'Práctica')");
        $r = $this->grupos->guardar($this->datosGrupo(['horarios' => [['dia_semana' => 'Lunes', 'hora_inicio' => '12:30', 'hora_fin' => '13:30']]]), $id);
        $this->assertStringContainsString('extra', $r['errores']['general']);
        $this->assertSame('09:00', $this->grupos->buscar($id)['horarios'][0]['hora_inicio']);
    }

    public function testClaseExtraRespetaGruposYNoDejaTransaccionAbierta(): void
    {
        $id = $this->crearGrupo();
        $this->matricular($id);
        $reservas = new ReservaService($this->pdo);
        $datos = ['id_grupo' => (string) $id, 'fecha' => '2030-01-07', 'hora_inicio' => '09:30', 'hora_fin' => '10:30', 'motivo' => 'Práctica'];
        $this->assertFalse($reservas->reservarClaseExtra(1, $datos)['ok']);
        $this->assertFalse($this->pdo->inTransaction());
        $datos['hora_inicio'] = '12:00';
        $datos['hora_fin'] = '13:00';
        $this->assertTrue($reservas->reservarClaseExtra(1, $datos)['ok']);
        $this->assertFalse($this->pdo->inTransaction());
    }

    public function testHttpCrearEditarDetalleYErrores(): void
    {
        $app = $this->appAdmin();
        $r = $app->handle($this->createRequest('POST', '/admin/grupos')->withParsedBody($this->datosGrupo(['_csrf' => Csrf::token()])));
        $this->assertSame(303, $r->getStatusCode());
        $this->assertSame(200, $app->handle($this->createRequest('GET', '/admin/grupos'))->getStatusCode());
        $this->assertSame(200, $app->handle($this->createRequest('GET', '/admin/grupos/1'))->getStatusCode());
        $r = $app->handle($this->createRequest('POST', '/admin/grupos/1')->withParsedBody($this->datosGrupo(['_csrf' => Csrf::token(), 'capacidad_maxima' => '99'])));
        $this->assertSame(422, $r->getStatusCode());
        $this->assertStringContainsString('value="99"', (string) $r->getBody());
    }

    public function testFalloAlGuardarHorarioRevierteGrupoYHorariosPrevios(): void
    {
        $id = $this->crearGrupo();
        $this->pdo->exec("ALTER TABLE horario ADD CONSTRAINT prueba_horario CHECK (hora_inicio < '12:00:00')");
        try {
            $this->grupos->guardar($this->datosGrupo(['capacidad_maxima' => '10', 'horarios' => [['dia_semana' => 'Lunes', 'hora_inicio' => '13:00', 'hora_fin' => '14:00']]]), $id);
            $this->fail('Debió fallar la escritura del horario.');
        } catch (\PDOException $e) {
            $grupo = $this->grupos->buscar($id);
            $this->assertSame(12, (int) $grupo['capacidad_maxima']);
            $this->assertSame('09:00', $grupo['horarios'][0]['hora_inicio']);
            $this->assertFalse($this->pdo->inTransaction());
        }
    }
}
