<?php

declare(strict_types=1);

namespace Tests\Application\Admin;

use App\Application\Academico\MatriculasService;
use App\Application\Academico\RequisitosService;
use App\Application\Admin\EstudiantesService;
use App\Application\Security\Csrf;
use DateTimeImmutable;

final class MatriculasTest extends GestionOperativaTestCase
{
    private MatriculasService $matriculas;
    private RequisitosService $requisitos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->matriculas = new MatriculasService($this->pdo);
        $this->requisitos = new RequisitosService($this->pdo);
    }

    public function testSolicitarReservaCupoCongelaPrecioYAuditoria(): void
    {
        $g = $this->crearGrupo();
        $r = $this->matriculas->solicitar($g, 1, 2, true);
        $this->assertTrue($r['ok']);
        $this->assertSame('Pendiente', $this->pdo->query('SELECT estado FROM inscripcion')->fetchColumn());
        $this->assertSame('49.95', $this->pdo->query('SELECT precio_inscripcion FROM inscripcion')->fetchColumn());
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM pago')->fetchColumn());
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM inscripcion_auditoria')->fetchColumn());
        $this->assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM inscripcion_auditoria WHERE estado_nuevo = 'Pendiente'")->fetchColumn());
        $this->assertSame(1, $this->matriculas->listarAdmin(['estado' => 'Pendiente'])['total']);
    }

    public function testSolicitudDuplicadaMismoCursoYCupoLleno(): void
    {
        $g1 = $this->crearGrupo();
        $g2 = $this->crearGrupo(['id_curso' => '1', 'id_instructor' => '3', 'id_aula' => '2', 'horarios' => [['dia_semana' => 'Martes', 'hora_inicio' => '10:00', 'hora_fin' => '11:00']]]);
        $g3 = $this->crearGrupo(['id_curso' => '2', 'id_instructor' => '1', 'id_aula' => '1', 'horarios' => [['dia_semana' => 'Martes', 'hora_inicio' => '10:00', 'hora_fin' => '11:00']]]);
        $this->matriculas->solicitar($g1, 1, 2, true);
        try {
            $this->matriculas->solicitar($g2, 1, 2, true);
            $this->fail('Debió impedir una segunda matrícula vigente para el mismo curso.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('solicitud', $e->getMessage());
        }
        $this->pdo->exec('UPDATE grupo SET capacidad_maxima = 1');
        $this->matriculas->solicitar($g3, 1, 2, true);
        try {
            $this->matriculas->solicitar($g3, 2, 1, true);
            $this->fail('Debió impedir superar la capacidad del grupo.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('lugares', $e->getMessage());
        }
        $this->assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM inscripcion')->fetchColumn());
    }

    public function testRequisitosDeEdadEnSolicitudYRevision(): void
    {
        $g = $this->crearGrupo();
        $this->requisitos->guardar(['edad_minima' => '14', 'edad_maxima' => '75'], 1);
        $r = $this->matriculas->solicitar($g, 1, 2, true);
        $this->assertTrue($r['ok']);
        $this->assertNotEmpty($this->matriculas->solicitar($this->crearGrupo(['id_curso' => '2', 'id_instructor' => '3', 'id_aula' => '2', 'horarios' => [['dia_semana' => 'Martes', 'hora_inicio' => '10:00', 'hora_fin' => '11:00']]]), 1, 2, false));
        $this->pdo->exec("UPDATE estudiante SET fecha_nacimiento = '2020-01-01'");
        $this->assertNotEmpty($this->matriculas->solicitar($this->crearGrupo(['id_curso' => '3', 'id_instructor' => '2', 'id_aula' => '1', 'horarios' => [['dia_semana' => 'Jueves', 'hora_inicio' => '10:00', 'hora_fin' => '11:00']]]), 1, 2, true));
        $this->pdo->exec('UPDATE inscripcion SET estado = "Cancelada"');
        $this->pdo->exec("UPDATE estudiante SET fecha_nacimiento = '1990-01-01'");
        $this->assertTrue($this->matriculas->solicitar($g, 1, 2, true)['ok']);
    }

    public function testAprobarRequiereConfirmarEdadManualYRevalidaConflictos(): void
    {
        $g = $this->crearGrupo();
        $this->requisitos->guardar(['edad_minima' => '14', 'edad_maxima' => '75'], 1);
        $id = $this->matriculas->solicitar($g, 1, 2, true)['id'];
        try {
            $this->matriculas->aprobar($id, 1, true, false, '');
            $this->fail('Debió exigir la confirmación manual de edad.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('edad', $e->getMessage());
        }
        $this->assertTrue($this->matriculas->aprobar($id, 1, true, true, 'Todo verificado')['ok']);
        $this->assertSame('Activa', $this->pdo->query("SELECT estado FROM inscripcion WHERE id_inscripcion = $id")->fetchColumn());
        $this->assertSame(2, (int) $this->pdo->query("SELECT COUNT(*) FROM inscripcion_auditoria WHERE id_inscripcion = $id")->fetchColumn());
        try {
            $this->matriculas->aprobar($id, 1, true, true, '');
            $this->fail('No debe aprobar dos veces la misma matrícula.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('pendiente', $e->getMessage());
        }
        $g2 = $this->crearGrupo(['id_curso' => '2', 'id_instructor' => '3', 'id_aula' => '2', 'horarios' => [['dia_semana' => 'Martes', 'hora_inicio' => '10:00', 'hora_fin' => '11:00']]]);
        $this->requisitos->guardar(['edad_minima' => '14', 'edad_maxima' => '75'], 2);
        $id2 = $this->matriculas->solicitar($g2, 1, 2, true)['id'];
        $this->pdo->exec("UPDATE estudiante SET fecha_nacimiento = '2020-01-01'");
        try {
            $this->matriculas->aprobar($id2, 1, true, true, '');
            $this->fail('Debió bloquear la aprobación por edad fuera de rango.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('edad', $e->getMessage());
        }
        $this->assertSame('Pendiente', $this->pdo->query("SELECT estado FROM inscripcion WHERE id_inscripcion = $id2")->fetchColumn());
    }

    public function testAprobarNoPermiteConflictosConOtraMatricula(): void
    {
        $g1 = $this->crearGrupo();
        $g2 = $this->crearGrupo(['id_curso' => '2', 'id_instructor' => '3', 'id_aula' => '2', 'horarios' => [['dia_semana' => 'Martes', 'hora_inicio' => '10:00', 'hora_fin' => '11:00']]]);
        $id1 = $this->matriculas->solicitar($g1, 1, 2, false)['id'];
        $id2 = $this->matriculas->solicitar($g2, 1, 1, false)['id'];
        $this->assertTrue($this->matriculas->aprobar($id1, 1, true, false, '')['ok']);
        $this->assertTrue($this->matriculas->aprobar($id2, 1, true, false, '')['ok']);
        $this->assertSame('Activa', $this->pdo->query("SELECT estado FROM inscripcion WHERE id_inscripcion = $id2")->fetchColumn());
    }

    public function testRechazarYCancelarPropiaLiberanCupoYAuditan(): void
    {
        $g = $this->crearGrupo();
        $id = $this->matriculas->solicitar($g, 1, 2, false)['id'];
        $this->assertNotEmpty($this->matriculas->rechazar($id, 1, ''));
        $this->assertTrue($this->matriculas->rechazar($id, 1, 'No cumple requisitos')['ok']);
        $this->assertSame('Cancelada', $this->pdo->query("SELECT estado FROM inscripcion WHERE id_inscripcion = $id")->fetchColumn());
        $this->assertSame(0, $this->matriculas->listarAdmin(['estado' => 'Pendiente'])['total']);
        $id2 = $this->matriculas->solicitar($g, 1, 2, false)['id'];
        $this->assertTrue($this->matriculas->cancelarPropia($id2, 1, 2)['ok']);
        $this->assertSame('Cancelada', $this->pdo->query("SELECT estado FROM inscripcion WHERE id_inscripcion = $id2")->fetchColumn());
        try {
            $this->matriculas->cancelarPropia($id2, 2, 2);
            $this->fail('No debe cancelar una matrícula ajena.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('no te pertenece', $e->getMessage());
        }
    }

    public function testHttpSolicitudPublicaLoginYConfirmacionConCsrf(): void
    {
        $g = $this->crearGrupo();
        $app = $this->appAdmin();
        $r = $app->handle($this->createRequest('GET', '/cursos/1'));
        $this->assertSame(200, $r->getStatusCode());
        $this->assertStringContainsString('SOLICITAR MATRÍCULA', (string) $r->getBody());
        $_SESSION = [];
        $r = $app->handle($this->createRequest('GET', '/estudiante/matricular/' . $g));
        $this->assertSame(302, $r->getStatusCode());
        $this->assertStringStartsWith('/login?next=', $r->getHeaderLine('Location'));
        $_SESSION['usuario'] = ['id' => 2, 'rol' => 'estudiante', 'id_referencia' => 1];
        $r = $app->handle($this->createRequest('GET', '/estudiante/matricular/' . $g));
        $this->assertSame(200, $r->getStatusCode());
        $this->assertStringContainsString('Solicitar Matrícula', (string) $r->getBody());
        $r = $app->handle($this->createRequest('POST', '/estudiante/matricular/' . $g)->withParsedBody(['_csrf' => Csrf::token(), 'confirmar_requisitos' => '1']));
        $this->assertSame(303, $r->getStatusCode());
        $this->assertSame('Pendiente', $this->pdo->query('SELECT estado FROM inscripcion')->fetchColumn());
        $this->assertSame(422, $app->handle($this->createRequest('POST', '/estudiante/matricular/' . $g)->withParsedBody(['_csrf' => Csrf::token()]))->getStatusCode());
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM inscripcion')->fetchColumn());
    }

    public function testHttpRevisarAprobarYRechazarComoAdmin(): void
    {
        $g = $this->crearGrupo();
        $id = $this->matriculas->solicitar($g, 1, 2, false)['id'];
        $app = $this->appAdmin();
        $r = $app->handle($this->createRequest('GET', '/admin/matriculas'));
        $this->assertSame(200, $r->getStatusCode());
        $this->assertStringContainsString('Pendiente', (string) $r->getBody());
        $r = $app->handle($this->createRequest('GET', '/admin/matriculas/' . $id));
        $this->assertSame(200, $r->getStatusCode());
        $r = $app->handle($this->createRequest('POST', '/admin/matriculas/' . $id . '/aprobar')->withParsedBody(['_csrf' => Csrf::token(), 'confirmar_requisitos' => '1']));
        $this->assertSame(303, $r->getStatusCode());
        $this->assertSame('Activa', $this->pdo->query("SELECT estado FROM inscripcion WHERE id_inscripcion = $id")->fetchColumn());
        $r = $app->handle($this->createRequest('POST', '/admin/matriculas/' . $id . '/rechazar')->withParsedBody(['_csrf' => Csrf::token(), 'motivo' => 'Revisión']));
        $this->assertSame(303, $r->getStatusCode());
        $this->assertSame('Activa', $this->pdo->query("SELECT estado FROM inscripcion WHERE id_inscripcion = $id")->fetchColumn());
    }
}
