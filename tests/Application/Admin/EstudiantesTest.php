<?php

declare(strict_types=1);

namespace Tests\Application\Admin;

use App\Application\Auth\AuthService;
use App\Application\Auth\CuentaService;
use App\Application\Security\Csrf;

final class EstudiantesTest extends GestionOperativaTestCase
{
    public function testCrearFichaSinCuentaYConCuentaSinCambiarSesionAdmin(): void
    {
        $this->appAdmin();
        $sesion = $_SESSION['usuario'];
        $r = $this->estudiantes->guardar($this->datosEstudiante());
        $this->assertSame([], $r['errores']);
        $this->assertSame([], $this->estudiantes->buscar($r['id'])['cuentas']);
        $r = $this->estudiantes->guardar($this->datosEstudiante(['email' => 'cuenta@prueba.test', 'crear_cuenta' => '1', 'password' => 'NuevaClave123', 'password_confirmar' => 'NuevaClave123']));
        $this->assertSame([], $r['errores']);
        $stmt = $this->pdo->prepare('SELECT rol, password_hash FROM usuario WHERE id_estudiante = :id');
        $stmt->execute(['id' => $r['id']]);
        $usuario = $stmt->fetch();
        $this->assertSame('estudiante', $usuario['rol']);
        $this->assertTrue(password_verify('NuevaClave123', $usuario['password_hash']));
        $this->assertSame($sesion, $_SESSION['usuario']);
        $this->assertArrayNotHasKey('password', $r['valores']);
    }

    public function testValidacionesCorreoDuplicadoYPasswordNoSeRefleja(): void
    {
        foreach (['alumno@prueba.test', 'admin@prueba.test'] as $email) {
            $r = $this->estudiantes->guardar($this->datosEstudiante(['email' => $email]));
            $this->assertNotEmpty($r['errores']);
        }
        $r = $this->estudiantes->guardar($this->datosEstudiante(['nombre' => '', 'email' => 'no-email', 'fecha_inscripcion' => '2099-01-01', 'crear_cuenta' => '1', 'password' => '123', 'password_confirmar' => 'otra']));
        foreach (['nombre', 'email', 'fecha_inscripcion', 'password', 'password_confirmar'] as $campo) {
            $this->assertArrayHasKey($campo, $r['errores']);
        }
        $this->assertArrayNotHasKey('password', $r['valores']);
        $this->assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM estudiante')->fetchColumn());
        $r = $this->estudiantes->guardar($this->datosEstudiante(['crear_cuenta' => '1', 'password' => "clave\0invalida", 'password_confirmar' => "clave\0invalida"]));
        $this->assertArrayHasKey('password', $r['errores']);
        $this->assertFalse($this->pdo->inTransaction());
    }

    public function testEditarSincronizaEmailYConservaFechaDesconocida(): void
    {
        $r = $this->estudiantes->guardar($this->datosEstudiante(['email' => 'cambio@prueba.test', 'acceso_activo' => '1']), 1);
        $this->assertSame([], $r['errores']);
        $this->assertSame('cambio@prueba.test', $this->pdo->query('SELECT email_usuario FROM usuario WHERE id_usuario = 2')->fetchColumn());
        $this->assertSame([], $this->estudiantes->guardar($this->datosEstudiante(['email' => 'sin@prueba.test', 'fecha_inscripcion' => '']), 2)['errores']);
        $this->assertNull($this->estudiantes->buscar(2)['fecha_inscripcion']);
    }

    public function testSuspenderLiberaCuposConservaHistorialYReactivaSinMatricular(): void
    {
        $g = $this->crearGrupo();
        $m = $this->matricular($g);
        $this->matricular($g, 'Pendiente');
        $this->matricular($g, 'Finalizada');
        $this->pdo->exec("INSERT INTO pago (id_inscripcion, fecha_pago, monto_pagado) VALUES ($m, '2026-01-01', 20)");
        $this->pdo->exec("INSERT INTO evaluacion (id_inscripcion, tipo, calificacion) VALUES ($m, 'Examen', 8.5)");
        $this->pdo->exec("INSERT INTO asistencia (id_inscripcion, fecha_clase, presente) VALUES ($m, '2026-01-01', 'S')");
        $r = $this->estudiantes->cambiarEstado(1, 'Suspendido', 'Ya no continúa', $this->estudiantes->revision(1), true);
        $this->assertSame('exito', $r['tipo']);
        $this->assertSame('Suspendido', $this->estudiantes->buscar(1)['estado']);
        $this->assertSame('Ya no continúa', $this->estudiantes->buscar(1)['suspension_motivo']);
        $this->assertNotNull($this->estudiantes->buscar(1)['suspension_fecha']);
        $this->assertSame(2, (int) $this->pdo->query("SELECT COUNT(*) FROM inscripcion WHERE estado = 'Cancelada'")->fetchColumn());
        $this->assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM inscripcion WHERE estado = 'Finalizada'")->fetchColumn());
        $this->assertSame(0, $this->grupos->listar([])['filas'][0]['inscritos']);
        foreach (['pago', 'evaluacion', 'asistencia'] as $tabla) {
            $this->assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM $tabla")->fetchColumn());
        }
        $this->assertSame('exito', $this->estudiantes->cambiarEstado(1, 'Activo', '', '', true)['tipo']);
        $this->assertSame(1, (int) $this->estudiantes->buscar(1)['cuentas'][0]['activo']);
        $this->assertSame(2, (int) $this->pdo->query("SELECT COUNT(*) FROM inscripcion WHERE estado = 'Cancelada'")->fetchColumn());
    }

    public function testSuspensionRechazaRevisionAntiguaOMotivoVacio(): void
    {
        $g = $this->crearGrupo();
        $revision = $this->estudiantes->revision(1);
        $this->matricular($g);
        $this->assertSame('error', $this->estudiantes->cambiarEstado(1, 'Suspendido', 'Retiro', $revision, true)['tipo']);
        $this->assertSame('error', $this->estudiantes->cambiarEstado(1, 'Suspendido', '', $this->estudiantes->revision(1), true)['tipo']);
        $this->assertSame('error', $this->estudiantes->cambiarEstado(1, 'Suspendido', 'Retiro', $this->estudiantes->revision(1), false)['tipo']);
        $this->assertSame('Activo', $this->estudiantes->buscar(1)['estado']);
        $this->assertSame('Activa', $this->pdo->query('SELECT estado FROM inscripcion')->fetchColumn());
    }

    public function testSuspensionIdempotenteNoPierdeAccesoAnterior(): void
    {
        $revision = $this->estudiantes->revision(1);
        $this->assertSame('exito', $this->estudiantes->cambiarEstado(1, 'Suspendido', 'Retiro', $revision, true)['tipo']);
        $this->assertSame('exito', $this->estudiantes->cambiarEstado(1, 'Suspendido', 'Otro', $revision, true)['tipo']);
        $this->estudiantes->cambiarEstado(1, 'Activo', '', '', true);
        $this->assertSame('1', $this->estudiantes->buscar(1)['acceso_activo']);
        $this->assertSame('Retiro', $this->estudiantes->buscar(1)['suspension_motivo']);
    }

    public function testNoHabilitarCuentaAntesInactivaNiCrearCuentaSuspendida(): void
    {
        $this->pdo->exec('UPDATE usuario SET activo = 0 WHERE id_usuario = 2');
        $this->estudiantes->cambiarEstado(1, 'Suspendido', 'Retiro', $this->estudiantes->revision(1), true);
        $this->assertNotEmpty($this->estudiantes->guardar($this->datosEstudiante(['email' => 'alumno@prueba.test', 'acceso_activo' => '1']), 1)['errores']);
        $this->estudiantes->cambiarEstado(1, 'Activo', '', '', true);
        $this->assertSame('0', $this->estudiantes->buscar(1)['acceso_activo']);
        $this->estudiantes->cambiarEstado(2, 'Suspendido', 'Retiro', $this->estudiantes->revision(2), true);
        $this->assertNotEmpty($this->estudiantes->guardar($this->datosEstudiante(['email' => 'sin@prueba.test', 'crear_cuenta' => '1', 'password' => 'NuevaClave123', 'password_confirmar' => 'NuevaClave123']), 2)['errores']);
    }

    public function testSuspensionRevierteTodoSiFallaUnaEscritura(): void
    {
        $g = $this->crearGrupo();
        $this->matricular($g);
        $this->pdo->exec('ALTER TABLE usuario ADD CONSTRAINT prueba_version CHECK (version_sesion = 0)');
        try {
            $this->estudiantes->cambiarEstado(1, 'Suspendido', 'Retiro', $this->estudiantes->revision(1), true);
            $this->fail('Debió fallar al actualizar la versión de sesión.');
        } catch (\PDOException $e) {
            $this->assertSame('Activo', $this->estudiantes->buscar(1)['estado']);
            $this->assertSame('Activa', $this->pdo->query('SELECT estado FROM inscripcion')->fetchColumn());
            $this->assertSame('1', $this->estudiantes->buscar(1)['acceso_activo']);
            $this->assertFalse($this->pdo->inTransaction());
        }
    }

    public function testSesionAnteriorNoReviveTrasReactivarYLoginSuspendidoFalla(): void
    {
        $auth = new AuthService($this->pdo);
        $sesion = $auth->intentarLogin('alumno@prueba.test', 'PruebaSegura123');
        $cuentas = new CuentaService($this->pdo);
        $this->assertTrue($cuentas->vigente($sesion));
        $this->estudiantes->cambiarEstado(1, 'Suspendido', 'Retiro', $this->estudiantes->revision(1), true);
        $this->assertFalse($cuentas->vigente($sesion));
        $this->assertNull($auth->intentarLogin('alumno@prueba.test', 'PruebaSegura123'));
        $this->estudiantes->cambiarEstado(1, 'Activo', '', '', true);
        $this->assertFalse($cuentas->vigente($sesion));
        $nueva = $auth->intentarLogin('alumno@prueba.test', 'PruebaSegura123');
        $this->assertTrue($cuentas->vigente($nueva));
    }

    public function testMiddlewareCierraSesionSuspendidaAntesDeLeerOPublicar(): void
    {
        $app = $this->appAdmin();
        $sesion = (new AuthService($this->pdo))->intentarLogin('alumno@prueba.test', 'PruebaSegura123');
        $this->estudiantes->cambiarEstado(1, 'Suspendido', 'Retiro', $this->estudiantes->revision(1), true);
        $_SESSION['usuario'] = $sesion;
        $r = $app->handle($this->createRequest('GET', '/estudiante/dashboard'));
        $this->assertSame(302, $r->getStatusCode());
        $this->assertSame('/login?next=%2Festudiante%2Fdashboard', $r->getHeaderLine('Location'));
        $this->assertArrayNotHasKey('usuario', $_SESSION);
        $_SESSION['usuario'] = $sesion;
        $token = Csrf::token();
        $r = $app->handle($this->createRequest('POST', '/estudiante/reservas/extras')->withParsedBody(['_csrf' => $token]));
        $this->assertSame('/login?next=%2Festudiante%2Freservas%2Fextras', $r->getHeaderLine('Location'));
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM reserva_clase_extra')->fetchColumn());
    }

    public function testDesactivarSoloAccesoRevocaSesionSinLiberarCupos(): void
    {
        $g = $this->crearGrupo();
        $this->matricular($g);
        $sesion = (new AuthService($this->pdo))->intentarLogin('alumno@prueba.test', 'PruebaSegura123');
        $r = $this->estudiantes->guardar($this->datosEstudiante(['email' => 'alumno@prueba.test', 'acceso_activo' => '0']), 1);
        $this->assertSame([], $r['errores']);
        $this->assertFalse((new CuentaService($this->pdo))->vigente($sesion));
        $this->assertSame('Activo', $this->estudiantes->buscar(1)['estado']);
        $this->assertSame('Activa', $this->pdo->query('SELECT estado FROM inscripcion')->fetchColumn());
    }

    public function testListadoBusquedaYFiltrosNoDuplicanEstudiante(): void
    {
        $g = $this->crearGrupo();
        $this->matricular($g);
        $this->matricular($g, 'Pendiente');
        $r = $this->estudiantes->listar(['q' => 'Violín']);
        $this->assertSame(1, $r['total']);
        $this->assertSame(['Violín'], $r['filas'][0]['instrumentos']);
        $this->assertSame(1, $this->estudiantes->listar(['acceso' => 'sin-cuenta'])['total']);
        $this->assertSame(1, $this->estudiantes->listar(['acceso' => 'activo'])['total']);
        $this->assertSame(0, $this->estudiantes->listar(['estado' => 'Suspendido'])['total']);
    }

    public function testHttpPantallasCsrfYEscapado(): void
    {
        $app = $this->appAdmin();
        $this->estudiantes->guardar($this->datosEstudiante(['nombre' => '<script>alert(1)</script>']), 1);
        foreach (['/admin/estudiantes', '/admin/estudiantes/nuevo', '/admin/estudiantes/1', '/admin/estudiantes/1/editar', '/admin/grupos'] as $ruta) {
            $r = $app->handle($this->createRequest('GET', $ruta));
            $this->assertSame(200, $r->getStatusCode());
            $html = (string) $r->getBody();
            $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
            $dom = new \DOMDocument();
            @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
            $xpath = new \DOMXPath($dom);
            foreach ($xpath->query('//form[@method="post"]') as $form) {
                $this->assertSame(1, $xpath->query('.//input[@name="_csrf"]', $form)->length);
            }
        }
    }

    public function testHttpCrearSuspenderYReactivar(): void
    {
        $app = $this->appAdmin();
        $g = $this->crearGrupo();
        $this->matricular($g);
        $r = $app->handle($this->createRequest('POST', '/admin/estudiantes')->withParsedBody($this->datosEstudiante(['_csrf' => Csrf::token()])));
        $this->assertSame(303, $r->getStatusCode());
        $r = $app->handle($this->createRequest('POST', '/admin/estudiantes/1/estado')->withParsedBody(['_csrf' => Csrf::token(), 'confirmar' => '1', 'estado' => 'Suspendido', 'motivo' => 'Retiro', 'revision' => $this->estudiantes->revision(1)]));
        $this->assertSame(303, $r->getStatusCode());
        $this->assertSame('Cancelada', $this->pdo->query('SELECT estado FROM inscripcion')->fetchColumn());
        $this->assertSame('Suspendido', $this->estudiantes->buscar(1)['estado']);
        $this->assertSame('admin', $_SESSION['usuario']['rol']);
    }

    public function testFalloAlCrearCuentaRevierteTambienLaFicha(): void
    {
        $this->pdo->exec("ALTER TABLE usuario ADD CONSTRAINT prueba_cuenta CHECK (email_usuario <> 'nuevo@prueba.test')");
        try {
            $this->estudiantes->guardar($this->datosEstudiante(['crear_cuenta' => '1', 'password' => 'NuevaClave123', 'password_confirmar' => 'NuevaClave123']));
            $this->fail('Debió fallar la escritura de la cuenta.');
        } catch (\PDOException $e) {
            $this->assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM estudiante')->fetchColumn());
            $this->assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM usuario')->fetchColumn());
            $this->assertFalse($this->pdo->inTransaction());
        }
    }

    public function testPaginacionDeEstudiantesYFiltrosVacios(): void
    {
        for ($n = 0; $n < 10; $n++) {
            $this->assertSame([], $this->estudiantes->guardar($this->datosEstudiante(['email' => "nuevo$n@prueba.test"]))['errores']);
        }
        $lista = $this->estudiantes->listar(['pagina' => '999', 'estado' => ['malformado']]);
        $this->assertSame(2, $lista['pagina']);
        $this->assertSame(12, $lista['total']);
        $this->assertCount(2, $lista['filas']);
        $this->assertSame(11, $lista['desde']);
    }
}
