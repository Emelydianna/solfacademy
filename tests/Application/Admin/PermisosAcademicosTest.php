<?php

declare(strict_types=1);

namespace Tests\Application\Admin;

use App\Application\Security\Csrf;
use Tests\TestCase;

final class PermisosAcademicosTest extends TestCase
{
    private array $sesionAnterior;

    protected function getAppInstance(): \Slim\App
    {
        $app = parent::getAppInstance();
        $cuentas = $this->createMock(\App\Application\Auth\CuentaService::class);
        $cuentas->method('vigente')->willReturn(true); // Aislar la comprobación de rol.
        $app->getContainer()->set(\App\Application\Auth\CuentaService::class, $cuentas);
        return $app;
    }

    protected function setUp(): void
    {
        $this->sesionAnterior = $_SESSION;
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->sesionAnterior;
    }

    public static function accesos(): array
    {
        $casos = [];
        foreach (['categorias', 'instrumentos', 'cursos', 'grupos', 'estudiantes'] as $seccion) {
            foreach (['', 'estudiante', 'instructor'] as $rol) {
                $casos[$seccion . '-' . ($rol ?: 'anonimo')] = [$seccion, $rol];
            }
        }
        return $casos;
    }

    /** @dataProvider accesos */
    public function testLecturaExigeAdmin(string $seccion, string $rol): void
    {
        if ($rol !== '') {
            $_SESSION['usuario'] = ['rol' => $rol, 'id_referencia' => 0];
        }
        $response = $this->getAppInstance()->handle($this->createRequest('GET', '/admin/' . $seccion));
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame($rol === '' ? '/login' : '/' . $rol . '/dashboard', $response->getHeaderLine('Location'));
    }

    public static function mutaciones(): array
    {
        return array_map(static fn ($ruta): array => [$ruta], [
            '/admin/categorias', '/admin/instrumentos', '/admin/cursos',
            '/admin/categorias/1', '/admin/instrumentos/1', '/admin/cursos/1',
            '/admin/categorias/1/eliminar', '/admin/instrumentos/1/eliminar', '/admin/cursos/1/estado',
            '/admin/grupos', '/admin/grupos/1', '/admin/estudiantes', '/admin/estudiantes/1', '/admin/estudiantes/1/estado',
        ]);
    }

    /** @dataProvider mutaciones */
    public function testCadaMutacionRechazaCsrfFaltante(string $ruta): void
    {
        $_SESSION['usuario'] = ['rol' => 'admin', 'id_referencia' => 0];
        $response = $this->getAppInstance()->handle($this->createRequest('POST', $ruta));
        $this->assertSame(403, $response->getStatusCode());
    }

    /** @dataProvider mutaciones */
    public function testTokenValidoNoAutorizaOtroRol(string $ruta): void
    {
        foreach (['estudiante', 'instructor'] as $rol) {
            $_SESSION['usuario'] = ['rol' => $rol, 'id_referencia' => 0];
            $request = $this->createRequest('POST', $ruta)->withParsedBody(['_csrf' => Csrf::token()]);
            $response = $this->getAppInstance()->handle($request);
            $this->assertSame(302, $response->getStatusCode());
            $this->assertSame('/' . $rol . '/dashboard', $response->getHeaderLine('Location'));
        }
    }
}
