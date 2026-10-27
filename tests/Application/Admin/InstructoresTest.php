<?php

declare(strict_types=1);

namespace Tests\Application\Admin;

use App\Application\Admin\InstructoresService;
use App\Application\Auth\AuthService;
use App\Application\Auth\CuentaService;
use App\Application\Catalogo\InstructoresPublicosService;
use App\Application\Instructor\InstructorService;
use App\Application\Perfil\FotoInstructorStorage;
use App\Application\Perfil\InstructorFotoService;
use App\Application\Security\Csrf;
use Psr\Http\Message\UploadedFileInterface;
use Slim\Psr7\Factory\StreamFactory;

final class InstructoresTest extends GestionOperativaTestCase
{
    private InstructoresService $instructores;
    private FotoInstructorStorage $fotos;
    private ?string $directorio = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directorio = '/tmp/opencode/solfacademy-fotos-test-' . bin2hex(random_bytes(8));
        $this->fotos = new FotoInstructorStorage($this->directorio);
        $this->instructores = new InstructoresService($this->pdo, $this->fotos);
    }

    protected function tearDown(): void
    {
        if ($this->directorio && is_dir($this->directorio)) {
            foreach (glob($this->directorio . '/perfil_instructor_*') as $archivo) {
                unlink($archivo); // Solo los archivos creados por esta prueba.
            }
            rmdir($this->directorio);
        }
        parent::tearDown();
    }

    private function datos(array $cambios = []): array
    {
        return array_merge(['nombre' => 'Docente', 'apellido' => 'Real', 'email' => 'docente@prueba.test', 'telefono' => '+503 2222 1111',
            'anios_experiencia' => '12', 'especialidades' => [['id_instrumento' => '1', 'nivel_dominio' => 'avanzado', 'anios_experiencia_i' => '8']]], $cambios);
    }

    private function crearCuenta(int $id = 1): void
    {
        $r = $this->instructores->guardar($this->datos(['crear_cuenta' => '1', 'password' => 'DocenteClave123', 'password_confirmar' => 'DocenteClave123', 'rol' => 'admin']), $id);
        $this->assertSame([], $r['errores']);
    }

    private function archivo(string $contenido, string $nombre = 'foto.png'): UploadedFileInterface
    {
        $archivo = $this->createMock(UploadedFileInterface::class);
        $archivo->method('getError')->willReturn(UPLOAD_ERR_OK);
        $archivo->method('getSize')->willReturn(strlen($contenido));
        $archivo->method('getClientFilename')->willReturn($nombre);
        $archivo->method('getStream')->willReturn((new StreamFactory())->createStream($contenido));
        return $archivo;
    }

    private function imagen(): UploadedFileInterface
    {
        return $this->archivo(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='), 'nombre-no-confiable.php');
    }

    private function appConFotos(): \Slim\App
    {
        $app = $this->appAdmin();
        $app->getContainer()->set(InstructoresService::class, $this->instructores);
        $app->getContainer()->set(FotoInstructorStorage::class, $this->fotos);
        $app->getContainer()->set(InstructorFotoService::class, new InstructorFotoService($this->pdo, $this->fotos));
        return $app;
    }

    public function testCrearSinCuentaYConCuentaMantieneSesionAdmin(): void
    {
        $this->appAdmin();
        $sesion = $_SESSION['usuario'];
        $r = $this->instructores->guardar($this->datos(['email' => 'sin-cuenta-docente@prueba.test']));
        $this->assertSame([], $r['errores']);
        $this->assertSame([], $this->instructores->buscar($r['id'])['cuentas']);
        $this->assertSame('avanzado', $this->instructores->buscar($r['id'])['especialidades'][0]['nivel_dominio']);
        $this->crearCuenta();
        $cuenta = $this->pdo->query('SELECT rol, password_hash FROM usuario WHERE id_instructor = 1')->fetch();
        $this->assertSame('instructor', $cuenta['rol']);
        $this->assertTrue(password_verify('DocenteClave123', $cuenta['password_hash']));
        $this->assertSame($sesion, $_SESSION['usuario']);
        $this->assertArrayNotHasKey('password', $r['valores']);
    }

    public function testValidacionesEspecialidadesDuplicadasYCorreo(): void
    {
        $e = ['id_instrumento' => '1', 'nivel_dominio' => 'avanzado', 'anios_experiencia_i' => '8'];
        $r = $this->instructores->guardar($this->datos(['nombre' => '', 'anios_experiencia' => '256', 'especialidades' => [$e, $e], 'email' => 'no-email']));
        foreach (['nombre', 'anios_experiencia', 'especialidades', 'email'] as $campo) {
            $this->assertArrayHasKey($campo, $r['errores']);
        }
        $r = $this->instructores->guardar($this->datos(['email' => 'admin@prueba.test']));
        $this->assertArrayHasKey('email', $r['errores']);
        $this->assertSame(4, (int) $this->pdo->query('SELECT COUNT(*) FROM instructor')->fetchColumn());
        $r = $this->instructores->guardar($this->datos(['crear_cuenta' => '1', 'password' => "invalida\0clave", 'password_confirmar' => "invalida\0clave"]));
        $this->assertArrayHasKey('password', $r['errores']);
        $this->assertArrayNotHasKey('password', $r['valores']);
        $this->assertNotEmpty($this->instructores->guardar($this->datos(['especialidades' => [['id_instrumento' => '999', 'nivel_dominio' => 'avanzado', 'anios_experiencia_i' => '8']]]))['errores']);
        $this->assertFalse($this->pdo->inTransaction());
    }

    public function testEmailOpcionalSinCuentaPeroObligatorioAlCrearla(): void
    {
        $r = $this->instructores->guardar($this->datos(['email' => '']));
        $this->assertSame([], $r['errores']);
        $this->assertNull($this->instructores->buscar($r['id'])['email']);
        $this->crearCuenta();
        $this->assertArrayHasKey('email', $this->instructores->guardar($this->datos(['email' => '']), 1)['errores']);
    }

    public function testEditarAdminYPerfilPropioSincronizanEmailSinCambiarSesion(): void
    {
        $this->appAdmin();
        $sesion = $_SESSION['usuario'];
        $this->crearCuenta();
        $this->assertSame([], $this->instructores->guardar($this->datos(['email' => 'nuevo@prueba.test']), 1)['errores']);
        $this->assertSame('nuevo@prueba.test', $this->pdo->query('SELECT email_usuario FROM usuario WHERE id_instructor = 1')->fetchColumn());
        $perfil = new InstructorService($this->pdo);
        $perfil->actualizarPerfilInstructor(1, ['nombre' => 'Desde perfil', 'apellido' => 'Real', 'email' => 'perfil@prueba.test', 'telefono' => '2222']);
        $this->assertSame('perfil@prueba.test', $this->pdo->query('SELECT email_usuario FROM usuario WHERE id_instructor = 1')->fetchColumn());
        $this->assertSame('perfil@prueba.test', $this->instructores->buscar(1)['email']);
        $this->assertSame($sesion, $_SESSION['usuario']);
        try {
            $perfil->actualizarPerfilInstructor(1, ['nombre' => 'NO GUARDAR', 'apellido' => 'Real', 'email' => 'admin@prueba.test']);
            $this->fail('Debió rechazar el correo duplicado.');
        } catch (\DomainException $e) {
            $this->assertSame('Desde perfil', $this->instructores->buscar(1)['nombre']);
            $this->assertSame('perfil@prueba.test', $this->pdo->query('SELECT email_usuario FROM usuario WHERE id_instructor = 1')->fetchColumn());
        }
    }

    public function testBuscarPorEspecialidadYFiltrarSinDuplicados(): void
    {
        $this->pdo->exec('INSERT INTO instructor_instrumento (id_instructor, id_instrumento) VALUES (1, 2)');
        $r = $this->instructores->listar(['q' => 'Piano', 'instrumento' => '1']);
        $this->assertSame(1, $r['total']);
        $this->assertCount(1, $r['filas']);
        $this->assertSame(1, (int) $r['filas'][0]['id_instructor']);
        $this->assertCount(2, $r['filas'][0]['especialidades']);
        $this->assertSame(1, $this->instructores->listar(['estado' => '0'])['total']);
        $this->assertSame(3, $this->instructores->listar(['estado' => '1'])['total']);
    }

    public function testNoQuitarEspecialidadUsadaNiSuspenderConGruposAbiertos(): void
    {
        $g = $this->crearGrupo();
        $this->matricular($g);
        $r = $this->instructores->guardar($this->datos(['nombre' => 'NO GUARDAR', 'especialidades' => [['id_instrumento' => '2', 'nivel_dominio' => 'intermedio', 'anios_experiencia_i' => '1']]]), 1);
        $this->assertArrayHasKey('especialidades', $r['errores']);
        $this->assertSame('Ana', $this->instructores->buscar(1)['nombre']);
        $this->assertSame('error', $this->instructores->cambiarEstado(1, '0', true)['tipo']);
        $this->assertSame(1, (int) $this->instructores->buscar(1)['activo']);
        $this->assertSame('Activa', $this->pdo->query('SELECT estado FROM inscripcion')->fetchColumn());
    }

    public function testClasesExtrasFuturasImpidenSuspensionSinAlterarlas(): void
    {
        $g = $this->crearGrupo();
        $this->grupos->guardar($this->datosGrupo(['estado' => 'finalizado']), $g);
        $this->pdo->exec("INSERT INTO reserva_clase_extra (id_estudiante, id_grupo, id_instructor, fecha, hora_inicio, hora_fin, motivo) VALUES (1, $g, 1, '2030-01-07', '12:00', '13:00', 'Práctica')");
        $this->assertSame('error', $this->instructores->cambiarEstado(1, '0', true)['tipo']);
        $this->assertSame('Pendiente', $this->pdo->query('SELECT estado FROM reserva_clase_extra')->fetchColumn());
        $this->pdo->exec("UPDATE reserva_clase_extra SET estado = 'Cancelada'");
        $this->assertSame('exito', $this->instructores->cambiarEstado(1, '0', true)['tipo']);
        $this->assertCount(1, $this->instructores->grupos(1));
    }

    public function testSuspensionRevocaSesionesYReactivacionNoReviveSesionAnterior(): void
    {
        $this->crearCuenta();
        $auth = new AuthService($this->pdo);
        $sesion = $auth->intentarLogin('docente@prueba.test', 'DocenteClave123');
        $cuentas = new CuentaService($this->pdo);
        $this->assertTrue($cuentas->vigente($sesion));
        $this->assertSame('error', $this->instructores->cambiarEstado(1, '0', false)['tipo']);
        $this->assertSame('exito', $this->instructores->cambiarEstado(1, '0', true)['tipo']);
        $this->assertSame('exito', $this->instructores->cambiarEstado(1, '0', true)['tipo']);
        $this->assertFalse($cuentas->vigente($sesion));
        $this->pdo->exec("UPDATE usuario SET activo = 1 WHERE id_instructor = 1");
        $this->assertNull($auth->intentarLogin('docente@prueba.test', 'DocenteClave123'));
        $this->instructores->cambiarEstado(1, '1', true);
        $this->assertFalse($cuentas->vigente($sesion));
        $this->assertTrue($cuentas->vigente($auth->intentarLogin('docente@prueba.test', 'DocenteClave123')));
    }

    public function testAccesoPreviamenteInactivoNoSeRestauraYNoModificaMatriculas(): void
    {
        $this->crearCuenta();
        $g = $this->crearGrupo();
        $this->matricular($g);
        $this->assertSame([], $this->instructores->guardar($this->datos(['acceso_activo' => '0']), 1)['errores']);
        $this->assertSame('Activa', $this->pdo->query('SELECT estado FROM inscripcion')->fetchColumn());
        $this->assertSame(1, (int) $this->instructores->buscar(1)['activo']);
        // El retiro no cancela matrículas: aquí resolvemos el grupo por separado.
        $this->pdo->exec("UPDATE inscripcion SET estado = 'Finalizada'");
        $this->grupos->guardar($this->datosGrupo(['estado' => 'finalizado']), $g);
        $this->instructores->cambiarEstado(1, '0', true);
        $this->instructores->cambiarEstado(1, '1', true);
        $this->assertSame('0', $this->instructores->buscar(1)['acceso_activo']);
        $this->assertSame('Finalizada', $this->pdo->query('SELECT estado FROM inscripcion')->fetchColumn());
    }

    public function testFotoValidaUsaNombreSeguroYReemplazaSoloLaAnterior(): void
    {
        $r = $this->instructores->guardar($this->datos(), 1, $this->imagen());
        $this->assertSame([], $r['errores']);
        $foto = $this->instructores->buscar(1)['foto'];
        $ruta = $this->fotos->ruta($foto, 1);
        $this->assertNotNull($ruta);
        $this->assertStringEndsWith('.png', $foto);
        $this->assertStringNotContainsString('.php', $foto);
        $this->assertNull($this->fotos->ruta($foto, 2));
        $this->assertNull($this->fotos->ruta('/perfil/foto/../archivo.png', 1));
        (new InstructorFotoService($this->pdo, $this->fotos))->guardar(1, $this->imagen());
        $this->assertFileDoesNotExist($ruta);
        $this->assertNotSame($foto, $this->instructores->buscar(1)['foto']);
    }

    public function testFotoFalsaRevierteFichaEspecialidadesYCuenta(): void
    {
        $r = $this->instructores->guardar($this->datos(['crear_cuenta' => '1', 'password' => 'DocenteClave123', 'password_confirmar' => 'DocenteClave123']), 0, $this->archivo('<?php echo "no imagen";', 'foto.jpg'));
        $this->assertArrayHasKey('foto', $r['errores']);
        $this->assertSame(4, (int) $this->pdo->query('SELECT COUNT(*) FROM instructor')->fetchColumn());
        $this->assertSame(4, (int) $this->pdo->query('SELECT COUNT(*) FROM instructor_instrumento')->fetchColumn());
        $this->assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM usuario')->fetchColumn());
        $this->assertFalse($this->pdo->inTransaction());
        $r = $this->instructores->guardar($this->datos(), 1, $this->archivo(str_repeat('a', 2 * 1024 * 1024 + 1)));
        $this->assertArrayHasKey('foto', $r['errores']);
        $this->assertSame('Ana', $this->instructores->buscar(1)['nombre']);
    }

    public function testFalloDeBaseDeDatosRetiraLaFotoNuevaYRevierteLaFicha(): void
    {
        $this->pdo->exec('ALTER TABLE instructor ADD CONSTRAINT prueba_foto CHECK (foto IS NULL)');
        try {
            $this->instructores->guardar($this->datos(), 0, $this->imagen());
            $this->fail('Debió fallar al guardar la foto en BD.');
        } catch (\PDOException $e) {
            $this->assertSame(4, (int) $this->pdo->query('SELECT COUNT(*) FROM instructor')->fetchColumn());
            $this->assertSame([], glob($this->directorio . '/*'));
            $this->assertFalse($this->pdo->inTransaction());
        }
    }

    public function testPublicoLeeDatosRealesSinValoracionesYNoPublicaSuspendidos(): void
    {
        $publico = new InstructoresPublicosService($this->pdo);
        $this->assertCount(3, $publico->listar());
        $id = $this->crearGrupo();
        $detalle = $publico->detalle(1);
        $this->assertSame('Ana', $detalle['nombre']);
        $this->assertSame('Violín', $detalle['especialidades'][0]['nombre']);
        $this->assertCount(1, $detalle['cursos']);
        $this->assertArrayNotHasKey('valoracion', $detalle);
        $this->assertArrayNotHasKey('biografia', $detalle);
        $this->assertNull($publico->detalle(4));
        $app = $this->appConFotos();
        $r = $app->handle($this->createRequest('GET', '/instructores/1'));
        $this->assertSame(200, $r->getStatusCode());
        $this->assertStringContainsString('Sin valoraciones', (string) $r->getBody());
        $this->assertStringNotContainsString('/mes', (string) $r->getBody());
        $this->assertSame(404, $app->handle($this->createRequest('GET', '/instructores/4'))->getStatusCode());
        $this->grupos->guardar($this->datosGrupo(['estado' => 'finalizado']), $id);
        $this->instructores->cambiarEstado(1, '0', true);
        $this->assertNull($publico->detalle(1));
    }

    public function testFotoPublicaSoloDeInstructorActivoYFotosPrivadasSiguenProtegidas(): void
    {
        $this->assertSame([], $this->instructores->guardar($this->datos(), 1, $this->imagen())['errores']);
        $foto = $this->instructores->buscar(1)['foto'];
        $app = $this->appConFotos();
        $_SESSION = [];
        $r = $app->handle($this->createRequest('GET', '/instructores/1/foto'));
        $this->assertSame(200, $r->getStatusCode());
        $this->assertSame('image/png', $r->getHeaderLine('Content-Type'));
        $this->assertSame('nosniff', $r->getHeaderLine('X-Content-Type-Options'));
        $this->assertSame(302, $app->handle($this->createRequest('GET', $foto))->getStatusCode());
        $this->pdo->exec("UPDATE instructor SET foto = '/perfil/foto/perfil_1_estudiante.png' WHERE id_instructor = 1");
        $this->assertSame(404, $app->handle($this->createRequest('GET', '/instructores/1/foto'))->getStatusCode());
        $this->pdo->prepare('UPDATE instructor SET foto = :foto, activo = 0 WHERE id_instructor = 1')->execute(['foto' => $foto]);
        $this->assertSame(404, $app->handle($this->createRequest('GET', '/instructores/1/foto'))->getStatusCode());
    }

    public function testPantallasHttpCsrfEscapadoYGuardado(): void
    {
        $app = $this->appConFotos();
        $this->instructores->guardar($this->datos(['nombre' => '<script>alert(1)</script>']), 1);
        foreach (['/admin/instructores', '/admin/instructores/nuevo', '/admin/instructores/1', '/admin/instructores/1/editar', '/instructores/1', '/'] as $ruta) {
            $r = $app->handle($this->createRequest('GET', $ruta));
            $this->assertSame(200, $r->getStatusCode(), $ruta);
            $html = (string) $r->getBody();
            $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
            $dom = new \DOMDocument();
            @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
            $xpath = new \DOMXPath($dom);
            foreach ($xpath->query('//form[@method="post"]') as $form) {
                $this->assertSame(1, $xpath->query('.//input[@name="_csrf"]', $form)->length);
            }
        }
        $r = $app->handle($this->createRequest('POST', '/admin/instructores')->withParsedBody($this->datos(['email' => 'otro@prueba.test', '_csrf' => Csrf::token()])));
        $this->assertSame(303, $r->getStatusCode());
        $r = $app->handle($this->createRequest('POST', '/admin/instructores')->withParsedBody($this->datos(['_csrf' => Csrf::token(), 'nombre' => ''])));
        $this->assertSame(422, $r->getStatusCode());
        $this->assertStringContainsString('aria-invalid="true"', (string) $r->getBody());
    }

    public function testPortalDelInstructorSincronizaCorreoYSubeFotoValidada(): void
    {
        $app = $this->appConFotos();
        $this->crearCuenta();
        (new AuthService($this->pdo))->intentarLogin('docente@prueba.test', 'DocenteClave123');
        $r = $app->handle($this->createRequest('POST', '/instructor/perfil')->withParsedBody([
            '_csrf' => Csrf::token(), 'nombre' => 'Docente actualizado', 'apellido' => 'Real', 'email' => 'portal@prueba.test', 'telefono' => '2222',
        ]));
        $this->assertSame(302, $r->getStatusCode());
        $this->assertSame('portal@prueba.test', $this->pdo->query('SELECT email_usuario FROM usuario WHERE id_instructor = 1')->fetchColumn());
        $this->assertSame('portal@prueba.test', $_SESSION['usuario']['email']);
        $this->assertSame('instructor', $_SESSION['usuario']['rol']);
        $r = $app->handle($this->createRequest('POST', '/instructor/perfil/foto')->withParsedBody(['_csrf' => Csrf::token()])->withUploadedFiles(['foto' => $this->imagen()]));
        $this->assertSame(302, $r->getStatusCode());
        $this->assertNotNull($this->fotos->ruta($this->instructores->buscar(1)['foto'], 1));
        $_SESSION = [];
        $this->assertSame(200, $app->handle($this->createRequest('GET', '/instructores/1/foto'))->getStatusCode());
    }
}
