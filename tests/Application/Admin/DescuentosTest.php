<?php

declare(strict_types=1);

namespace Tests\Application\Admin;

use App\Application\Academico\MatriculasService;
use App\Application\Descuentos\DescuentosService;
use App\Application\Security\Csrf;

/**
 * Códigos de descuento: solo el administrador los crea desde /admin/descuentos
 * y se aplican al solicitar la matrícula (el precio congelado ya sale con
 * el descuento; los pagos no cambian).
 */
final class DescuentosTest extends GestionOperativaTestCase
{
    private DescuentosService $descuentos;
    private MatriculasService $matriculas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->descuentos = new DescuentosService($this->pdo);
        $this->matriculas = new MatriculasService($this->pdo);
    }

    /** @return array<string, string> */
    private function datos(array $cambios = []): array
    {
        return array_merge([
            'codigo' => 'PROMO-TEST', 'descripcion' => 'De prueba',
            'tipo' => 'monto', 'monto_fijo' => '30', 'porcentaje' => '',
            'fecha_inicio' => '2000-01-01', 'fecha_fin' => '2099-12-31',
            'id_curso' => '0', 'sin_limite' => '1', 'activo' => '1',
        ], $cambios);
    }

    private function crear(array $cambios = []): int
    {
        $r = $this->descuentos->guardar($this->datos($cambios), 0);
        $this->assertSame([], $r['errores'], json_encode($r['errores']));
        $this->assertGreaterThan(0, $r['id']);
        return $r['id'];
    }

    public function testAdminCreaEditaYDesactivaCodigos(): void
    {
        $id = $this->crear();
        $fila = $this->descuentos->buscar($id);
        $this->assertSame('PROMO-TEST', $fila['codigo']);
        $this->assertSame('30.00', (string) $fila['monto_fijo']);
        $this->assertNull($fila['porcentaje']);
        $this->assertSame(1, (int) $fila['activo']);

        // El código es único.
        $repetido = $this->descuentos->guardar($this->datos(['codigo' => 'PROMO-TEST']), 0);
        $this->assertNotEmpty($repetido['errores']);

        // Edición: se pasa a porcentaje y se acota a un curso.
        $editado = $this->descuentos->guardar($this->datos(['codigo' => 'PROMO-2', 'tipo' => 'porcentaje',
            'porcentaje' => '15', 'monto_fijo' => '', 'id_curso' => '2']), $id);
        $this->assertSame([], $editado['errores'], json_encode($editado['errores']));
        $fila = $this->descuentos->buscar($id);
        $this->assertSame('15.00', (string) $fila['porcentaje']);
        $this->assertNull($fila['monto_fijo']);
        $this->assertSame(2, (int) $fila['id_curso']);

        // Activar y desactivar: nunca se borra el cupón.
        $r = $this->descuentos->cambiarEstado($id, 0);
        $this->assertSame('exito', $r['tipo']);
        $this->assertSame(0, (int) $this->descuentos->buscar($id)['activo']);
        $this->assertSame(1, $this->descuentos->listar(['estado' => '0'])['total']);
        $this->assertSame(0, $this->descuentos->listar(['estado' => '1'])['total']);
        $this->descuentos->cambiarEstado($id, 1);
        $this->assertSame(1, $this->descuentos->listar(['estado' => '1'])['total']);
        $this->assertSame(1, $this->descuentos->listar(['q' => 'PROMO-2'])['total']);
    }

    public function testValidacionesDelFormulario(): void
    {
        $casos = [
            [['codigo' => 'ab'], 'codigo'],
            [['tipo' => 'porcentaje', 'monto_fijo' => '', 'porcentaje' => '0'], 'porcentaje'],
            [['tipo' => 'porcentaje', 'monto_fijo' => '', 'porcentaje' => '101'], 'porcentaje'],
            [['monto_fijo' => '-1'], 'monto_fijo'],
            [['tipo' => ''], 'tipo'],
            [['fecha_inicio' => 'no-fecha'], 'fecha_inicio'],
            [['fecha_fin' => '1999-12-31'], 'fecha_fin'],
        ];
        foreach ($casos as [$cambio, $campo]) {
            $r = $this->descuentos->guardar($this->datos($cambio), 0);
            $this->assertArrayHasKey($campo, $r['errores'], 'Debía fallar: ' . json_encode($cambio));
        }
        $this->assertNotEmpty($this->descuentos->guardar($this->datos(['sin_limite' => '0', 'usos_disponibles' => '0']), 0)['errores']);

        // No se pueden bajar los usos por debajo de los ya realizados.
        $id = $this->crear(['codigo' => 'USOS5', 'sin_limite' => '0', 'usos_disponibles' => '5']);
        $this->pdo->exec('UPDATE descuento SET usos_realizados = 5 WHERE id_descuento = ' . $id);
        $r = $this->descuentos->guardar($this->datos(['codigo' => 'USOS5', 'sin_limite' => '0', 'usos_disponibles' => '2']), $id);
        $this->assertNotEmpty($r['errores']);

        // Ninguna validación fallida escribió código alguno.
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM descuento')->fetchColumn());
    }

    public function testAlMatricularCongelaElPrecioConDescuentoYConsumeElUso(): void
    {
        $this->crear(['codigo' => 'BAJON30']);
        $g = $this->crearGrupo();

        $r = $this->matriculas->solicitar($g, 1, 2, true, ' bajon30 ');
        $this->assertTrue($r['ok']);
        $this->assertStringContainsString('Descuento BAJON30', $r['mensaje']);

        $inscripcion = $this->pdo->query('SELECT id_inscripcion, precio_inscripcion, id_descuento FROM inscripcion')->fetch();
        $this->assertEqualsWithDelta(19.95, (float) $inscripcion['precio_inscripcion'], 0.001);
        $this->assertNotNull($inscripcion['id_descuento']);
        $this->assertSame(1, (int) $this->pdo->query('SELECT usos_realizados FROM descuento')->fetchColumn());

        // El saldo del estudiante ya parte del precio con descuento.
        $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(monto_pagado),0) FROM pago WHERE id_inscripcion = :i AND estado = 'Pagado'");
        $stmt->execute(['i' => $inscripcion['id_inscripcion']]);
        $this->assertSame(0.0, (float) $stmt->fetchColumn());

        // El detalle administrativo muestra el cupón aplicado.
        $detalle = $this->matriculas->detalle((int) $inscripcion['id_inscripcion']);
        $this->assertSame('BAJON30', $detalle['descuento_codigo']);

        // Un cupón global por porcentaje también funciona (curso 2: $69.95).
        $this->crear(['codigo' => 'MEDIA10', 'tipo' => 'porcentaje', 'monto_fijo' => '', 'porcentaje' => '10']);
        $g2 = $this->crearGrupo(['id_curso' => '2', 'id_instructor' => '3', 'id_aula' => '2',
            'horarios' => [['dia_semana' => 'Martes', 'hora_inicio' => '10:00', 'hora_fin' => '11:00']]]);
        $this->matriculas->solicitar($g2, 1, 2, true, 'MEDIA10');
        $segunda = (int) $this->pdo->query('SELECT MAX(id_inscripcion) FROM inscripcion')->fetchColumn();
        $precio = (float) $this->pdo->query("SELECT precio_inscripcion FROM inscripcion WHERE id_inscripcion = $segunda")->fetchColumn();
        $this->assertEqualsWithDelta(69.95 * 0.9, $precio, 0.01);
        $this->assertSame(1, (int) $this->pdo->query("SELECT usos_realizados FROM descuento WHERE codigo = 'MEDIA10'")->fetchColumn());
    }

    public function testCodigoInvalidoNoImpideMatricularse(): void
    {
        $this->crear(['codigo' => 'VENCIDO', 'fecha_inicio' => '2000-01-01', 'fecha_fin' => '2000-01-02']);
        $this->crear(['codigo' => 'FUTURO', 'fecha_inicio' => '2099-01-01', 'fecha_fin' => '2099-12-31']);
        $this->crear(['codigo' => 'PIANO15', 'id_curso' => '2', 'tipo' => 'porcentaje',
            'monto_fijo' => '', 'porcentaje' => '15']);
        $this->crear(['codigo' => 'APAGADO', 'activo' => '0']);

        $g = $this->crearGrupo();
        foreach (['NO-EXISTE', 'VENCIDO', 'FUTURO', 'PIANO15', 'APAGADO'] as $codigo) {
            // Libera el curso entre intentos: una matrícula vigente por curso.
            $this->pdo->exec("UPDATE inscripcion SET estado = 'Cancelada'");
            $r = $this->matriculas->solicitar($g, 1, 2, true, $codigo);
            $this->assertTrue($r['ok'], "El código $codigo no debe impedir la matrícula.");
            $this->assertStringContainsString('sin descuento', $r['mensaje'], "Falta el aviso para $codigo");
            $ultima = (int) $this->pdo->query('SELECT MAX(id_inscripcion) FROM inscripcion')->fetchColumn();
            $this->assertNull($this->pdo->query("SELECT id_descuento FROM inscripcion WHERE id_inscripcion = $ultima")->fetchColumn());
            $this->assertEqualsWithDelta(49.95, (float) $this->pdo->query("SELECT precio_inscripcion FROM inscripcion WHERE id_inscripcion = $ultima")->fetchColumn(), 0.001);
        }

        $this->assertSame(0, (int) $this->pdo->query('SELECT SUM(usos_realizados) FROM descuento')->fetchColumn());
    }

    public function testLaPrevisualizacionNoConsumeUsos(): void
    {
        $this->crear(['codigo' => 'VISTA10', 'tipo' => 'porcentaje', 'monto_fijo' => '', 'porcentaje' => '10']);

        $ok = $this->matriculas->previsualizarDescuento(1, 49.95, 'vista10');
        $this->assertTrue($ok['ok']);
        $this->assertEqualsWithDelta(44.96, (float) $ok['precio_final'], 0.02);
        $this->assertSame(0, (int) $this->pdo->query('SELECT usos_realizados FROM descuento')->fetchColumn());
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM inscripcion')->fetchColumn());

        $mal = $this->matriculas->previsualizarDescuento(1, 49.95, 'NOEXISTE');
        $this->assertFalse($mal['ok']);
        $this->assertStringContainsString('no existe', $mal['mensaje']);
    }

    public function testHttpSoloElAdminCreaCodigosYElEstudianteVeLaPrevisualizacion(): void
    {
        $g = $this->crearGrupo();
        $app = $this->appAdmin();

        $r = $app->handle($this->createRequest('GET', '/admin/descuentos'));
        $this->assertSame(200, $r->getStatusCode());
        $this->assertStringContainsString('Códigos registrados', (string) $r->getBody());

        $r = $app->handle($this->createRequest('GET', '/admin/descuentos/nuevo'));
        $this->assertSame(200, $r->getStatusCode());
        $this->assertStringContainsString('PROMO-', (string) $r->getBody());

        $r = $app->handle($this->createRequest('POST', '/admin/descuentos')->withParsedBody([
            '_csrf' => Csrf::token(), 'codigo' => 'HTTP20', 'tipo' => 'monto', 'monto_fijo' => '20',
            'fecha_inicio' => '2000-01-01', 'fecha_fin' => '2099-12-31', 'id_curso' => '0',
            'sin_limite' => '1', 'activo' => '1',
        ]));
        $this->assertSame(303, $r->getStatusCode());
        $this->assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM descuento WHERE codigo = 'HTTP20'")->fetchColumn());

        // Errores de validación devuelven al formulario sin crear el cupón.
        $r = $app->handle($this->createRequest('POST', '/admin/descuentos')->withParsedBody([
            '_csrf' => Csrf::token(), 'codigo' => 'x', 'tipo' => 'monto', 'monto_fijo' => '20',
            'fecha_inicio' => '2000-01-01', 'fecha_fin' => '2099-12-31', 'id_curso' => '0', 'sin_limite' => '1',
        ]));
        $this->assertSame(303, $r->getStatusCode());
        $this->assertSame('/admin/descuentos/nuevo', $r->getHeaderLine('Location'));
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM descuento')->fetchColumn());

        $id = (int) $this->pdo->query("SELECT id_descuento FROM descuento WHERE codigo = 'HTTP20'")->fetchColumn();
        $r = $app->handle($this->createRequest('POST', '/admin/descuentos/' . $id . '/estado')
            ->withParsedBody(['_csrf' => Csrf::token(), 'activo' => '0']));
        $this->assertSame(303, $r->getStatusCode());
        $this->assertSame(0, (int) $this->pdo->query("SELECT activo FROM descuento WHERE codigo = 'HTTP20'")->fetchColumn());

        // El estudiante ve la previsualización del precio con descuento.
        $this->descuentos->cambiarEstado($id, 1);
        $_SESSION['usuario'] = ['id' => 2, 'rol' => 'estudiante', 'id_referencia' => 1];
        $peticion = $this->createRequest('GET', '/estudiante/matricular/' . $g);
        $r = $app->handle($peticion->withUri($peticion->getUri()->withQuery('codigo_descuento=HTTP20')));
        $this->assertSame(200, $r->getStatusCode());
        $cuerpo = (string) $r->getBody();
        $this->assertStringContainsString('Código <strong>HTTP20</strong> aplicado', $cuerpo);
        $this->assertStringContainsString('$ 29.95', $cuerpo);

        // Sin rol de admin no hay acceso al módulo.
        $_SESSION['usuario'] = ['id' => 2, 'rol' => 'estudiante', 'id_referencia' => 1];
        $this->assertSame(302, $app->handle($this->createRequest('GET', '/admin/descuentos'))->getStatusCode());
    }
}
