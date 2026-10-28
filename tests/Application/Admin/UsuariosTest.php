<?php

declare(strict_types=1);

namespace Tests\Application\Admin;

use App\Application\Admin\UsuariosService;
use App\Application\Auth\CuentaService;
use App\Application\Security\Csrf;
use Slim\Views\PhpRenderer;

/**
 * Usuarios del sistema: listado con roles, bloqueo de cuentas y alta de
 * cuentas de acceso. Solo consulta y seguridad: sin edición de correo/rol.
 */
final class UsuariosTest extends GestionOperativaTestCase
{
    private UsuariosService $usuarios;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usuarios = new UsuariosService($this->pdo);
    }

    /** @return array<string, string> */
    private function datos(array $cambios = []): array
    {
        return array_merge([
            'rol' => 'admin',
            'id_persona' => '',
            'email' => 'nuevo.admin@prueba.test',
            'password' => 'Secreta123',
            'password_confirmar' => 'Secreta123',
        ], $cambios);
    }

    private function filaPorId(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM usuario WHERE id_usuario = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: [];
    }

    public function testListadoConFiltrosYDatosPresentados(): void
    {
        $listado = $this->usuarios->listar([]);
        $this->assertSame(2, $listado['total']);
        $this->assertSame('', $listado['filtros']['rol']);

        // La cuenta admin no tiene fila de persona: se presenta como "Administrador".
        $admin = null;
        foreach ($listado['filas'] as $fila) {
            if ($fila['rol'] === 'admin') {
                $admin = $fila;
            }
        }
        $this->assertNotNull($admin);
        $this->assertSame('Administrador', $admin['nombre']);
        $this->assertSame('A', $admin['iniciales']);
        $this->assertNull($admin['avatar']);
        $this->assertNull($admin['persona_ficha']);
        $this->assertSame('Nunca ha entrado', $admin['ultimo_acceso_relativo']);
        $this->assertSame('Activo', $admin['estado_texto']);

        // Filtro por rol (las píldoras) y por estado.
        $this->assertSame(1, $this->usuarios->listar(['rol' => 'admin'])['total']);
        $this->assertSame(0, $this->usuarios->listar(['rol' => 'instructor'])['total']);
        $this->assertSame(1, $this->usuarios->listar(['rol' => 'estudiante'])['total']);
        $this->assertSame(0, $this->usuarios->listar(['estado' => 'bloqueado'])['total']);
        $this->assertSame(2, $this->usuarios->listar(['estado' => 'activo'])['total']);

        // Estudiante: nombre real de la ficha y enlace a su perfil.
        $estudiantes = $this->usuarios->listar(['rol' => 'estudiante']);
        $this->assertSame('Sofía Alumno', $estudiantes['filas'][0]['nombre']);
        $this->assertSame('SA', $estudiantes['filas'][0]['iniciales']);
        $this->assertSame('/admin/estudiantes/1', $estudiantes['filas'][0]['persona_ficha']);

        // Búsqueda por correo, por nombre y por #id.
        $this->assertSame(1, $this->usuarios->listar(['q' => 'alumno'])['total']);
        $this->assertSame(1, $this->usuarios->listar(['q' => 'Sofía'])['total']);
        $this->assertSame(1, $this->usuarios->listar(['q' => '#2'])['total']);
        $this->assertSame(0, $this->usuarios->listar(['q' => 'no-existe@nada.test'])['total']);
    }

    public function testBloquearCierraSesionesYNoPermiteBloquearseAMismo(): void
    {
        $cuentas = new CuentaService($this->pdo);
        $sesion = ['id' => 2, 'rol' => 'estudiante', 'version_sesion' => 0, 'id_referencia' => 1];
        $this->assertTrue($cuentas->vigente($sesion));

        $r = $this->usuarios->bloquear(2, true, 1);
        $this->assertSame('exito', $r['tipo']);
        $cuenta = $this->filaPorId(2);
        $this->assertSame(0, (int) $cuenta['activo']);
        $this->assertSame(1, (int) $cuenta['version_sesion']);
        // La sesión abierta antes del bloqueo deja de servir.
        $this->assertFalse($cuentas->vigente($sesion));

        // No se puede bloquear la propia cuenta de administrador.
        $r = $this->usuarios->bloquear(1, true, 1);
        $this->assertSame('error', $r['tipo']);
        $this->assertStringContainsString('propia', $r['mensaje']);
        $this->assertSame(1, (int) $this->filaPorId(1)['activo']);

        // Bloquear dos veces no duplica el trabajo.
        $r = $this->usuarios->bloquear(2, true, 1);
        $this->assertSame('exito', $r['tipo']);
        $this->assertStringContainsString('ya estaba', $r['mensaje']);

        // Reactivar: incrementa otra vez la versión y la nueva sesión sí vale.
        $r = $this->usuarios->bloquear(2, false, 1);
        $this->assertSame('exito', $r['tipo']);
        $cuenta = $this->filaPorId(2);
        $this->assertSame(1, (int) $cuenta['activo']);
        $this->assertSame(2, (int) $cuenta['version_sesion']);
        $this->assertFalse($cuentas->vigente($sesion));
        $this->assertTrue($cuentas->vigente(['id' => 2, 'rol' => 'estudiante', 'version_sesion' => 2, 'id_referencia' => 1]));

        // Una cuenta inexistente se reporta sin romper nada.
        $this->assertSame('error', $this->usuarios->bloquear(9999, true, 1)['tipo']);
    }

    public function testNoSeReactivaLaCuentaDePersonaSuspendidaOInactiva(): void
    {
        $this->pdo->exec("UPDATE estudiante SET estado = 'Suspendido' WHERE id_estudiante = 1");
        $this->usuarios->bloquear(2, true, 1);

        $r = $this->usuarios->bloquear(2, false, 1);
        $this->assertSame('error', $r['tipo']);
        $this->assertStringContainsString('suspendido', $r['mensaje']);
        $this->assertSame(0, (int) $this->filaPorId(2)['activo']);

        // Al reactivar el estudiante, sí se puede habilitar la cuenta.
        $this->pdo->exec("UPDATE estudiante SET estado = 'Activo' WHERE id_estudiante = 1");
        $this->assertSame('exito', $this->usuarios->bloquear(2, false, 1)['tipo']);
        $this->assertSame(1, (int) $this->filaPorId(2)['activo']);

        // Igual para un instructor inactivo con cuenta propia.
        $r = $this->usuarios->crear($this->datos(['rol' => 'instructor', 'id_persona' => '1', 'email' => 'ana@prueba.test']));
        $this->assertSame([], $r['errores'], json_encode($r['errores']));
        $this->usuarios->bloquear($r['id'], true, 1);
        $this->pdo->exec('UPDATE instructor SET activo = 0 WHERE id_instructor = 1');
        $r = $this->usuarios->bloquear($r['id'], false, 1);
        $this->assertSame('error', $r['tipo']);
        $this->assertStringContainsString('instructor', $r['mensaje']);
    }

    public function testCrearCuentasDeAdministradorInstructorYEstudiante(): void
    {
        // Admin: sin persona asociada y con hash, nunca la contraseña en claro.
        $r = $this->usuarios->crear($this->datos());
        $this->assertSame([], $r['errores'], json_encode($r['errores']));
        $cuenta = $this->filaPorId($r['id']);
        $this->assertSame('admin', $cuenta['rol']);
        $this->assertSame(1, (int) $cuenta['activo']);
        $this->assertNull($cuenta['id_estudiante']);
        $this->assertNull($cuenta['id_instructor']);
        $this->assertNull($cuenta['ultimo_acceso']);
        $this->assertTrue(password_verify('Secreta123', (string) $cuenta['password_hash']));
        $this->assertStringNotContainsString('Secreta123', (string) $cuenta['password_hash']);

        // Instructor sin cuenta y sin correo en su ficha: se completa aquí.
        $r = $this->usuarios->crear($this->datos(['rol' => 'instructor', 'id_persona' => '1', 'email' => 'ana@prueba.test']));
        $this->assertSame([], $r['errores'], json_encode($r['errores']));
        $cuenta = $this->filaPorId($r['id']);
        $this->assertSame('instructor', $cuenta['rol']);
        $this->assertSame(1, (int) $cuenta['id_instructor']);
        $this->assertSame('ana@prueba.test', (string) $cuenta['email_usuario']);
        $this->assertSame('ana@prueba.test', (string) $this->pdo->query('SELECT email FROM instructor WHERE id_instructor = 1')->fetchColumn());

        // Estudiante sin cuenta: manda el correo de su ficha, no el tecleado.
        $r = $this->usuarios->crear($this->datos(['rol' => 'estudiante', 'id_persona' => '2', 'email' => 'otro@correo.test']));
        $this->assertSame([], $r['errores'], json_encode($r['errores']));
        $cuenta = $this->filaPorId($r['id']);
        $this->assertSame('estudiante', $cuenta['rol']);
        $this->assertSame(2, (int) $cuenta['id_estudiante']);
        $this->assertSame('sin@prueba.test', (string) $cuenta['email_usuario']);

        // Las candidatas ya no aparecen como "sin cuenta".
        $this->assertSame(0, count($this->usuarios->candidatos('estudiante')));
        $this->assertSame(2, count($this->usuarios->candidatos('instructor')));
        $this->assertSame([], $this->usuarios->candidatos('admin'));
    }

    public function testValidacionesDelAltaNoEscribenNada(): void
    {
        $casos = [
            [['rol' => '', 'id_persona' => ''], 'rol'],
            [['password' => 'corta', 'password_confirmar' => 'corta'], 'password'],
            [['password_confirmar' => 'Distinta123'], 'password_confirmar'],
            [['email' => 'no-es-correo', 'id_persona' => ''], 'email'],
            [['rol' => 'estudiante', 'id_persona' => ''], 'id_persona'],
            [['rol' => 'estudiante', 'id_persona' => '1'], 'id_persona'], // ya tiene cuenta
            [['rol' => 'estudiante', 'id_persona' => '999'], 'id_persona'], // no existe
            [['rol' => 'instructor', 'id_persona' => '4'], 'id_persona'], // instructor inactivo
            [['rol' => 'admin', 'email' => 'admin@prueba.test'], 'email'], // correo duplicado
        ];
        foreach ($casos as [$cambios, $campo]) {
            $r = $this->usuarios->crear($this->datos($cambios));
            $this->assertArrayHasKey($campo, $r['errores'], 'Debía fallar en ' . $campo . ': ' . json_encode($cambios));
        }

        // Ninguna de las validaciones creó una cuenta.
        $this->assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM usuario')->fetchColumn());
    }

    public function testHttpListadoFichaAltaYBloqueo(): void
    {
        $app = $this->appAdmin();

        $r = $app->handle($this->createRequest('GET', '/admin/usuarios'));
        $this->assertSame(200, $r->getStatusCode());
        $cuerpo = (string) $r->getBody();
        $this->assertStringContainsString('Usuarios del Sistema', $cuerpo);
        $this->assertStringContainsString('Crear Usuario', $cuerpo);
        $this->assertStringContainsString('Todos', $cuerpo);
        $this->assertStringContainsString('Administrador', $cuerpo);

        // La ficha distingue la cuenta propia.
        $fichaPropia = (string) $app->handle($this->createRequest('GET', '/admin/usuarios/1'))->getBody();
        $this->assertStringContainsString('No puedes bloquear', $fichaPropia);
        $fichaAjena = (string) $app->handle($this->createRequest('GET', '/admin/usuarios/2'))->getBody();
        $this->assertStringContainsString('BLOQUEAR CUENTA', $fichaAjena);

        $r = $app->handle($this->createRequest('GET', '/admin/usuarios/nuevo'));
        $this->assertSame(200, $r->getStatusCode());
        $this->assertStringContainsString('Crear usuario', (string) $r->getBody());

        // Alta con CSRF.
        $r = $app->handle($this->createRequest('POST', '/admin/usuarios')->withParsedBody([
            '_csrf' => Csrf::token(), 'rol' => 'admin', 'id_persona' => '',
            'email' => 'http.admin@prueba.test', 'password' => 'Secreta123', 'password_confirmar' => 'Secreta123',
        ]));
        $this->assertSame(303, $r->getStatusCode());
        $this->assertSame('/admin/usuarios', $r->getHeaderLine('Location'));
        $this->assertSame(3, (int) $this->pdo->query('SELECT COUNT(*) FROM usuario')->fetchColumn());

        // Sin token no se crea nada.
        $this->assertSame(403, $app->handle($this->createRequest('POST', '/admin/usuarios')->withParsedBody([
            'rol' => 'admin', 'email' => 'otro@prueba.test',
            'password' => 'Secreta123', 'password_confirmar' => 'Secreta123',
        ]))->getStatusCode());
        $this->assertSame(3, (int) $this->pdo->query('SELECT COUNT(*) FROM usuario')->fetchColumn());

        // Bloqueo desde la tabla.
        $r = $app->handle($this->createRequest('POST', '/admin/usuarios/2/bloqueo')
            ->withParsedBody(['_csrf' => Csrf::token(), 'accion' => 'bloquear']));
        $this->assertSame(303, $r->getStatusCode());
        $this->assertSame(0, (int) $this->filaPorId(2)['activo']);
        $peticion = $this->createRequest('GET', '/admin/usuarios');
        $bloqueados = $app->handle($peticion->withUri($peticion->getUri()->withQuery('estado=bloqueado')));
        $this->assertSame(200, $bloqueados->getStatusCode());
        $this->assertStringContainsString('Sofía Alumno', (string) $bloqueados->getBody());

        // Una acción sin acción válida no toca la cuenta.
        $r = $app->handle($this->createRequest('POST', '/admin/usuarios/2/bloqueo')
            ->withParsedBody(['_csrf' => Csrf::token(), 'accion' => 'otra']));
        $this->assertSame(303, $r->getStatusCode());
        $this->assertSame(0, (int) $this->filaPorId(2)['activo']);

        // Sin rol de admin no hay acceso al módulo.
        $_SESSION['usuario'] = ['id' => 2, 'rol' => 'estudiante', 'id_referencia' => 1];
        $this->assertSame(302, $app->handle($this->createRequest('GET', '/admin/usuarios'))->getStatusCode());
    }

    /** El renderer del panel es necesario para las vistas HTML del módulo. */
    protected function appAdmin(): \Slim\App
    {
        $app = parent::appAdmin();
        $app->getContainer()->set(PhpRenderer::class, new PhpRenderer(__DIR__ . '/../../../templates'));
        $app->getContainer()->set(UsuariosService::class, $this->usuarios);
        return $app;
    }
}
