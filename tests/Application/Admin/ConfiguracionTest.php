<?php

declare(strict_types=1);

namespace Tests\Application\Admin;

use App\Application\Config\ConfiguracionService;
use App\Application\Security\Csrf;
use Psr\Http\Message\UploadedFileInterface;
use Slim\Views\PhpRenderer;

/**
 * Configuración de la academia: datos, logo, su reflejo en portales/web/PDF
 * y el comportamiento de reserva cuando la tabla no existe.
 */
final class ConfiguracionTest extends GestionOperativaTestCase
{
    private ConfiguracionService $config;

    /** Logo real del entorno, apartado durante las pruebas y restaurado al final. */
    private ?string $logoRespaldo = null;

    /** @var list<string> */
    private array $archivosCreados = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->config = new ConfiguracionService($this->pdo);

        $original = ConfiguracionService::archivoLogo();
        if ($original !== null) {
            $this->logoRespaldo = $original . '.respaldo';
            @rename($original, $this->logoRespaldo);
        }
    }

    protected function tearDown(): void
    {
        // Solo los archivos que creó esta prueba: jamás se toca otro logo.
        foreach ($this->archivosCreados as $archivo) {
            if (is_file($archivo)) {
                @unlink($archivo);
            }
        }
        $this->archivosCreados = [];

        if ($this->logoRespaldo !== null && is_file($this->logoRespaldo)) {
            @rename($this->logoRespaldo, str_replace('.respaldo', '', $this->logoRespaldo));
            $this->logoRespaldo = null;
        }

        $directorio = ConfiguracionService::directorio();
        if (is_dir($directorio) && !(glob($directorio . '/*') ?: [])) {
            @rmdir($directorio);
        }

        parent::tearDown();
    }

    /** Archivo subido cuyo moveTo escribe de verdad (patrón de PagosTest). */
    private function archivo(string $contenido, string $nombre = 'logo.png'): UploadedFileInterface
    {
        $archivo = $this->createMock(UploadedFileInterface::class);
        $archivo->method('getError')->willReturn(UPLOAD_ERR_OK);
        $archivo->method('getSize')->willReturn(strlen($contenido));
        $archivo->method('getClientFilename')->willReturn($nombre);
        $archivo->method('getStream')->willReturn((new \Slim\Psr7\Factory\StreamFactory())->createStream($contenido));
        $archivo->method('moveTo')->willReturnCallback(function (string $ruta) use ($contenido): void {
            if (!is_dir(dirname($ruta))) {
                @mkdir(dirname($ruta), 0755, true);
            }
            file_put_contents($ruta, $contenido);
            $this->archivosCreados[] = $ruta;
        });

        return $archivo;
    }

    /** PNG de prueba con el tamaño indicado (mismo fondo que un logo real). */
    private function png(int $ancho, int $alto): string
    {
        $imagen = imagecreatetruecolor($ancho, $alto);
        imagefill($imagen, 0, 0, imagecolorallocate($imagen, 255, 193, 7));
        ob_start();
        imagepng($imagen);
        imagedestroy($imagen);

        return (string) ob_get_clean();
    }

    /** JPEG de prueba: debe salir convertido a PNG al subirlo. */
    private function jpg(int $ancho, int $alto): string
    {
        $imagen = imagecreatetruecolor($ancho, $alto);
        imagefill($imagen, 0, 0, imagecolorallocate($imagen, 255, 193, 7));
        ob_start();
        imagejpeg($imagen);
        imagedestroy($imagen);

        return (string) ob_get_clean();
    }

    /** @return array<string, string> */
    private function datos(array $cambios = []): array
    {
        return array_merge([
            'nombre' => 'Armonía Music', 'lema' => 'Escuela de Arte',
            'email_contacto' => 'hola@armoniamusic.test', 'telefono' => '+34 600 111 222',
            'direccion' => 'Calle Nueva 7', 'ciudad' => 'Valencia, España',
            'facebook' => 'https://facebook.com/armoniamusic', 'instagram' => '', 'youtube' => '',
            'pie_pagina' => '© 2026 Armonía Music · Todos los derechos reservados.', 'nota_pdf' => '',
        ], $cambios);
    }

    public function testGuardadoYValidaciones(): void
    {
        // De fábrica conserva lo que ya tenía la web.
        $defecto = $this->config->obtener();
        $this->assertSame('SolfAcademy', $defecto['nombre']);

        $r = $this->config->guardar($this->datos());
        $this->assertSame([], $r['errores'], json_encode($r['errores']));

        $guardado = $this->config->obtener();
        $this->assertSame('Armonía Music', $guardado['nombre']);
        $this->assertSame('Escuela de Arte', $guardado['lema']);
        $this->assertSame('Valencia, España', $guardado['ciudad']);
        $this->assertSame('https://facebook.com/armoniamusic', $guardado['facebook']);
        $this->assertNull($guardado['instagram']);
        $this->assertNotNull($guardado['actualizado_en']);

        // Validaciones: nada de lo inválido se escribe.
        $casos = [
            [['nombre' => 'ab'], 'nombre'],
            [['lema' => ''], 'lema'],
            [['email_contacto' => 'no-es-correo'], 'email_contacto'],
            [['facebook' => 'facebook.com/sin-https'], 'facebook'],
            [['youtube' => 'ftp://youtu'], 'youtube'],
            [['pie_pagina' => str_repeat('x', 181)], 'pie_pagina'],
            [['nota_pdf' => str_repeat('x', 181)], 'nota_pdf'],
            [['ciudad' => str_repeat('x', 121)], 'ciudad'],
        ];
        foreach ($casos as [$cambios, $campo]) {
            $r = $this->config->guardar($this->datos($cambios));
            $this->assertArrayHasKey($campo, $r['errores'], 'Debía fallar en ' . $campo);
        }

        // Ningún envío inválido cambió lo guardado.
        $this->assertSame('Armonía Music', $this->config->obtener()['nombre']);

        // Los espacios en blanco de las redes se guardan como null.
        $this->config->guardar($this->datos(['facebook' => '']));
        $this->assertNull($this->config->obtener()['facebook']);
    }

    public function testElTituloSustituyeElSufijoHistorico(): void
    {
        $config = ['nombre' => 'Armonía Music'];
        $this->assertSame('Iniciar Sesión — Armonía Music', ConfiguracionService::titulo('Iniciar Sesión — SolfAcademy', $config));
        $this->assertSame('Calificaciones — Portal del Estudiante — Armonía Music', ConfiguracionService::titulo('Calificaciones — Portal del Estudiante', $config));
        $this->assertSame('Armonía Music', ConfiguracionService::titulo('', $config));
        $this->assertSame('SolfAcademy', ConfiguracionService::titulo('', ['nombre' => '']));
    }

    public function testElLogoSeSubeSeSirveYSeQuita(): void
    {
        $this->assertNull(ConfiguracionService::archivoLogo(), 'No debe haber logo al empezar');

        // Rechazos: peso, formato, tipo real de archivo y dimensiones.
        $grande = str_repeat('A', ConfiguracionService::LOGO_MAX_BYTES + 1);
        $this->assertFalse($this->config->guardarLogo($this->archivo($grande))['ok']);
        $this->assertFalse($this->config->guardarLogo($this->archivo('<svg onload="alert(1)"></svg>', 'logo.svg'))['ok']);
        $this->assertFalse($this->config->guardarLogo($this->archivo('soy texto', 'logo.png'))['ok']);
        $this->assertFalse($this->config->guardarLogo(null)['ok']);
        $this->assertFalse($this->config->guardarLogo($this->archivo($this->png(1, 1)))['ok'], '1px es demasiado pequeño');
        $this->assertFalse($this->config->guardarLogo($this->archivo($this->png(4100, 100)))['ok'], '4100px supera el máximo');
        $this->assertNull(ConfiguracionService::archivoLogo());

        // Subida correcta: formato estándar (PNG, proporciones intactas).
        $r = $this->config->guardarLogo($this->archivo($this->png(160, 64), 'mi-logo.png'));
        $this->assertTrue($r['ok'], $r['mensaje']);

        $archivo = ConfiguracionService::archivoLogo();
        $this->assertNotNull($archivo);
        $this->assertStringEndsWith('.png', $archivo);
        $datos = getimagesize($archivo);
        $this->assertSame('image/png', $datos['mime']);
        $this->assertSame([160, 64], [$datos[0], $datos[1]], 'Por debajo del lienzo no se toca');
        $this->assertSame('/marca/logo', ConfiguracionService::logoPublico($this->config->obtener()['logo_url']));

        // Imagen grande: se reduce hasta el lienzo conservando la proporción.
        $this->assertTrue($this->config->guardarLogo($this->archivo($this->png(1000, 400), 'grande.png'))['ok']);
        $reducido = getimagesize(ConfiguracionService::archivoLogo());
        $this->assertSame('image/png', $reducido['mime']);
        $this->assertSame(ConfiguracionService::LOGO_LIENZO, max($reducido[0], $reducido[1]));
        $this->assertEqualsWithDelta(1000 / 400, $reducido[0] / $reducido[1], 0.05, 'La proporción se conserva');

        // JPEG de entrada → PNG de salida (formato único de logo).
        $this->assertTrue($this->config->guardarLogo($this->archivo($this->jpg(300, 120), 'foto.jpg'))['ok']);
        $this->assertStringEndsWith('.png', (string) ConfiguracionService::archivoLogo());
        $this->assertSame('image/png', getimagesize((string) ConfiguracionService::archivoLogo())['mime']);

        // Quitar: desaparece el archivo y vuelve el icono.
        $this->assertTrue($this->config->quitarLogo()['ok']);
        $this->assertNull(ConfiguracionService::archivoLogo());
        $this->assertNull($this->config->obtener()['logo_url']);
        $this->assertNull(ConfiguracionService::logoPublico(null));
    }

    public function testHttpConfiguracionSuReflejoEnWebPortalYPdf(): void
    {
        $app = $this->appAdmin();

        // Sin logo, la ruta pública no encuentra nada.
        $this->assertSame(404, $app->handle($this->createRequest('GET', '/marca/logo'))->getStatusCode());

        $r = $app->handle($this->createRequest('GET', '/admin/configuracion'));
        $this->assertSame(200, $r->getStatusCode());
        $pagina = (string) $r->getBody();
        $this->assertStringContainsString('Configuración de la academia', $pagina);
        // Esta página no carga style.css: la vista previa debe usar la clase
        // de admin-academica.css o el <img> se pinta a tamaño natural.
        $this->assertStringContainsString('admin-logo-preview', $pagina);
        $this->assertStringNotContainsString('auth-brand-logo', $pagina);

        // Guardar con CSRF.
        $r = $app->handle($this->createRequest('POST', '/admin/configuracion')
            ->withParsedBody(['_csrf' => Csrf::token()] + $this->datos()));
        $this->assertSame(303, $r->getStatusCode());
        $this->assertSame('Armonía Music', $this->config->obtener()['nombre']);

        // Sin token no se guarda nada.
        $this->assertSame(403, $app->handle($this->createRequest('POST', '/admin/configuracion')
            ->withParsedBody($this->datos(['nombre' => 'Hackeada'])))->getStatusCode());
        $this->assertSame('Armonía Music', $this->config->obtener()['nombre']);

        // Subida del logo por HTTP y lectura pública (la web y el login no llevan sesión).
        $r = $app->handle($this->createRequest('POST', '/admin/configuracion/logo')
            ->withParsedBody(['_csrf' => Csrf::token()])
            ->withUploadedFiles(['logo' => $this->archivo($this->png(160, 64), 'web.png')]));
        $this->assertSame(303, $r->getStatusCode());
        $logo = $app->handle($this->createRequest('GET', '/marca/logo'));
        $this->assertSame(200, $logo->getStatusCode());
        $this->assertSame('image/png', $logo->getHeaderLine('Content-Type'));

        // Login (público): título y subtítulo con la nueva marca.
        $_SESSION = [];
        $login = (string) $app->handle($this->createRequest('GET', '/login'))->getBody();
        $this->assertStringContainsString('<title>Iniciar Sesión — Armonía Music</title>', $login);
        $this->assertStringContainsString('Bienvenido de nuevo a Armonía Music', $login);
        $registro = $app->handle($this->createRequest('GET', '/registro'));
        $this->assertStringContainsString('Únete a la comunidad de Armonía Music', (string) $registro->getBody());

        // Web pública: navbar, pie y contacto.
        $respuesta = $app->handle($this->createRequest('GET', '/'));
        $this->assertSame(200, $respuesta->getStatusCode(), 'HOME: ' . substr((string) $respuesta->getBody(), 0, 600));
        $home = (string) $respuesta->getBody();
        $this->assertStringContainsString('ARMONÍA MUSIC', $home);
        $this->assertStringContainsString('Calle Nueva 7', $home);
        $this->assertStringContainsString('Valencia, España', $home);
        $this->assertStringContainsString('https://facebook.com/armoniamusic', $home);

        // Portal admin: sidebar y pie (hace falta volver a abrir sesión de admin).
        $this->appAdmin();
        $dashboard = (string) $app->handle($this->createRequest('GET', '/admin/dashboard'))->getBody();
        $this->assertStringContainsString('ARMONÍA MUSIC', $dashboard);
        $this->assertStringContainsString('© ' . date('Y') . ' Armonía Music Escuela de Arte', $dashboard);

        // PDF de reportes: cabecera y nota con la marca configurada.
        $pdf = (string) $app->handle($this->createRequest('GET', '/admin/reportes/exportar'))->getBody();
        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringContainsString('ARMON', $pdf);
        $this->assertStringContainsString('Escuela de Arte', $pdf);

        // Rol sin permisos.
        $_SESSION = ['id' => 2, 'rol' => 'estudiante', 'id_referencia' => 1];
        $this->assertSame(302, $app->handle($this->createRequest('GET', '/admin/configuracion'))->getStatusCode());
    }

    public function testSinTablaSeUsanLosValoresDeFabrica(): void
    {
        $this->pdo->exec('DROP TEMPORARY TABLE configuracion');

        $defecto = $this->config->obtener();
        $this->assertSame('SolfAcademy', $defecto['nombre']);
        $this->assertSame('Academia de Música', $defecto['lema']);

        // La web sigue funcionando sin la tabla.
        $app = $this->appAdmin();
        $_SESSION = [];
        $r = $app->handle($this->createRequest('GET', '/login'));
        $this->assertSame(200, $r->getStatusCode());
        $this->assertStringContainsString('SolfAcademy', (string) $r->getBody());
    }

    /** El renderer del panel es necesario para las vistas HTML. */
    protected function appAdmin(): \Slim\App
    {
        $app = parent::appAdmin();
        $app->getContainer()->set(PhpRenderer::class, new PhpRenderer(__DIR__ . '/../../../templates'));
        $app->getContainer()->set(ConfiguracionService::class, $this->config);
        return $app;
    }
}
