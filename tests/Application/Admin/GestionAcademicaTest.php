<?php

declare(strict_types=1);

namespace Tests\Application\Admin;

use App\Application\Admin\GestionAcademicaService;
use App\Application\Catalogo\CatalogoService;
use PDO;
use Psr\Http\Message\UploadedFileInterface;
use Slim\Views\PhpRenderer;
use App\Application\Security\Csrf;
use Tests\TestCase;

/**
 * Integración MySQL opt-in: SOLO tablas TEMPORARY que ocultan las reales
 * en esta conexión. No importa SQL, no vacía tablas ni altera el esquema.
 * SOLFACADEMY_TEST_DB=1 vendor/bin/phpunit --filter GestionAcademicaTest
 */
final class GestionAcademicaTest extends TestCase
{
    private ?PDO $pdo = null;
    private GestionAcademicaService $service;
    private array $temporales = [];
    private array $sesionAnterior = [];
    private array $postAnterior = [];
    /** @var list<string> Ficheros creados por las pruebas de imagen. */
    private array $archivosCreados = [];
    private const TABLAS = ['categoria', 'instrumento', 'instructor', 'instructor_instrumento', 'curso', 'aula', 'grupo', 'horario', 'inscripcion', 'requisito_curso'];

    protected function setUp(): void
    {
        $this->sesionAnterior = $_SESSION;
        $this->postAnterior = $_POST;
        if (getenv('SOLFACADEMY_TEST_DB') !== '1') {
            $this->markTestSkipped('Activa SOLFACADEMY_TEST_DB=1 para probar con tablas MySQL temporales.');
        }
        $db = require __DIR__ . '/../../../config/db.php';
        $this->pdo = new PDO(
            "mysql:host={$db['host']};dbname={$db['dbname']};charset={$db['charset']}", $db['user'], $db['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
        );
        foreach (self::TABLAS as $tabla) {
            // MySQL no admite CREATE TEMPORARY TABLE x LIKE x. Un molde
            // temporal con otro nombre permite ocultar x sin tocarla.
            $this->pdo->exec("CREATE TEMPORARY TABLE academica_molde LIKE $tabla");
            $this->pdo->exec("CREATE TEMPORARY TABLE $tabla LIKE academica_molde");
            $this->pdo->exec('DROP TEMPORARY TABLE academica_molde');
            $this->temporales[] = $tabla;
        }
        $this->service = new GestionAcademicaService($this->pdo);
        $this->pdo->exec("INSERT INTO categoria (id_categoria, nombre) VALUES (1, 'Cuerdas'), (2, 'Teclados')");
        $this->pdo->exec("INSERT INTO instrumento (id_instrumento, id_categoria, nombre) VALUES (1, 1, 'Violín'), (2, 2, 'Piano')");
        $this->pdo->exec("INSERT INTO instructor (id_instructor, nombre, apellido) VALUES (1, 'Ana', 'López'), (2, 'Carlos', 'Mendoza')");
        $this->pdo->exec("INSERT INTO aula (id_aula, nombre, capacidad) VALUES (1, 'Aula central', 12)");
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->temporales) as $tabla) {
            $this->pdo->exec("DROP TEMPORARY TABLE IF EXISTS $tabla");
        }
        $this->pdo = null;
        $_SESSION = $this->sesionAnterior;
        $_POST = $this->postAnterior;

        // Solo los ficheros que creó esta prueba: jamás se toca otro.
        foreach ($this->archivosCreados as $archivo) {
            if (is_file($archivo)) {
                @unlink($archivo);
            }
        }
        $this->archivosCreados = [];
    }

    private function curso(array $cambios = []): array
    {
        return array_merge([
            'nombre' => 'Violín inicial', 'descripcion' => 'Curso real de prueba', 'id_instrumento' => '1',
            'nivel' => 'Principiante', 'duracion_semanas' => '12', 'precio' => '49.95',
            'max_grupos' => '2', 'activo' => '1', 'disponible' => '1',
        ], $cambios);
    }

    private function crearCurso(array $cambios = []): int
    {
        $resultado = $this->service->guardar('cursos', $this->curso($cambios));
        $this->assertSame([], $resultado['errores']);
        return $resultado['id'];
    }

    /** Archivo subido cuyo moveTo escribe de verdad (patrón de ConfiguracionTest). */
    private function archivo(string $contenido, string $nombre = 'curso.png'): UploadedFileInterface
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

    /** PNG de prueba con el tamaño indicado (la normalización exige un mínimo). */
    private function png(int $ancho, int $alto): string
    {
        $imagen = imagecreatetruecolor($ancho, $alto);
        imagefill($imagen, 0, 0, imagecolorallocate($imagen, 38, 49, 79));
        ob_start();
        imagepng($imagen);
        imagedestroy($imagen);

        return (string) ob_get_clean();
    }

    /** Ruta absoluta de la imagen guardada de un curso (null si no tiene). */
    private function rutaImagen(int $id): ?string
    {
        $archivo = $this->service->buscar('cursos', $id)['imagen'] ?? null;
        if ($archivo === null || $archivo === '') {
            return null;
        }
        return dirname(__DIR__, 3) . '/var/uploads/cursos/' . basename((string) $archivo);
    }

    private function grupos(int $id): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO grupo (id_grupo, id_curso, id_instructor, id_aula, fecha_inicio, estado) VALUES (1, :curso1, 1, 1, '2026-01-01', 'activo'), (2, :curso2, 2, 1, '2026-01-01', 'planeado')");
        $stmt->execute(['curso1' => $id, 'curso2' => $id]);
        $this->pdo->exec("INSERT INTO horario (id_grupo, dia_semana, hora_inicio, hora_fin) VALUES (1, 'Lunes', '09:00', '10:00'), (1, 'Miércoles', '09:00', '10:00'), (2, 'Martes', '18:00', '19:00')");
    }

    public function testCrearEditarYEliminarCategoriaSinDependencias(): void
    {
        $r = $this->service->guardar('categorias', ['nombre' => 'Viento', 'descripcion' => 'Instrumentos de viento']);
        $this->assertSame([], $r['errores']);
        $r = $this->service->guardar('categorias', ['nombre' => 'Vientos', 'descripcion' => 'Actualizado'], $r['id']);
        $this->assertSame('Vientos', $this->service->buscar('categorias', $r['id'])['nombre']);
        $this->assertSame('exito', $this->service->eliminar('categorias', $r['id'])['tipo']);
        $this->assertNull($this->service->buscar('categorias', $r['id']));
    }

    public function testValidarNombreDuplicadoYDependencias(): void
    {
        $r = $this->service->guardar('categorias', ['nombre' => 'Cuerdas']);
        $this->assertArrayHasKey('nombre', $r['errores']);
        $this->assertSame('error', $this->service->eliminar('categorias', 1)['tipo']);
        $this->crearCurso();
        $this->assertSame('error', $this->service->eliminar('instrumentos', 1)['tipo']);
        $this->assertNotNull($this->service->buscar('instrumentos', 1));
    }

    public function testInstrumentoVinculadoAInstructorNoSeElimina(): void
    {
        $this->pdo->exec('INSERT INTO instructor_instrumento (id_instructor, id_instrumento) VALUES (1, 2)');
        $this->assertSame('error', $this->service->eliminar('instrumentos', 2)['tipo']);
    }

    public function testCrearEditarYEliminarInstrumento(): void
    {
        $datos = ['nombre' => 'Viola', 'descripcion' => '', 'id_categoria' => '1', 'precio_alquiler' => '15.50', 'disponible' => '1'];
        $r = $this->service->guardar('instrumentos', $datos);
        $this->assertSame([], $r['errores']);
        $datos['disponible'] = '0';
        $this->assertSame([], $this->service->guardar('instrumentos', $datos, $r['id'])['errores']);
        $this->assertSame(0, (int) $this->service->buscar('instrumentos', $r['id'])['disponible']);
        $this->assertSame('exito', $this->service->eliminar('instrumentos', $r['id'])['tipo']);
    }

    public function testRechazarCamposInvalidosYConservarValores(): void
    {
        $datos = $this->curso(['nombre' => '', 'precio' => '-1', 'id_instrumento' => '999', 'nivel' => 'Otro', 'max_grupos' => '0', 'activo' => 'si']);
        $r = $this->service->guardar('cursos', $datos);
        foreach (['nombre', 'precio', 'id_instrumento', 'nivel', 'max_grupos', 'activo'] as $campo) {
            $this->assertArrayHasKey($campo, $r['errores']);
        }
        $this->assertSame('-1', $r['valores']['precio']);
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM curso')->fetchColumn());
    }

    public function testImportesFueraDeRangoODecimalesExcesivos(): void
    {
        foreach (['1000000', '12.345', 'NaN', '1e4'] as $precio) {
            $this->assertArrayHasKey('precio', $this->service->validar('cursos', $this->curso(['precio' => $precio]))['errores']);
        }
        $this->assertSame([], $this->service->validar('cursos', $this->curso(['precio' => '0', 'duracion_semanas' => '']))['errores']);
    }

    public function testCrearEditarDesactivarYReactivarCursoSinModificarMatricula(): void
    {
        $id = $this->crearCurso();
        $this->grupos($id);
        $this->pdo->exec("INSERT INTO inscripcion (id_estudiante, id_grupo, fecha, precio_inscripcion) VALUES (1, 1, '2026-01-01', 49.95)");
        $this->assertSame([], $this->service->guardar('cursos', $this->curso(['precio' => '99.99']), $id)['errores']);
        $this->assertSame('49.95', $this->pdo->query('SELECT precio_inscripcion FROM inscripcion')->fetchColumn());
        $this->assertSame('exito', $this->service->cambiarEstadoCurso($id, '0')['tipo']);
        $this->assertSame(0, (int) $this->service->buscar('cursos', $id)['activo']);
        $this->assertSame('exito', $this->service->cambiarEstadoCurso($id, '1')['tipo']);
        $this->assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM grupo')->fetchColumn());
    }

    public function testNoCambiarInstrumentoNiReducirGruposExistentes(): void
    {
        $id = $this->crearCurso();
        $this->grupos($id);
        $r = $this->service->guardar('cursos', $this->curso(['id_instrumento' => '2', 'max_grupos' => '1']), $id);
        $this->assertArrayHasKey('id_instrumento', $r['errores']);
        $this->assertArrayHasKey('max_grupos', $r['errores']);
        $this->assertSame(1, (int) $this->service->buscar('cursos', $id)['id_instrumento']);
    }

    public function testBusquedaPorInstructorSinDuplicarCursos(): void
    {
        $id = $this->crearCurso();
        $this->grupos($id);
        $lista = $this->service->listar('cursos', ['q' => 'Ana']);
        $this->assertSame(1, $lista['total']);
        $this->assertCount(1, $lista['filas']);
        $this->assertStringContainsString('Carlos Mendoza', $lista['filas'][0]['instructores']);
        $this->assertSame(0, $this->service->listar('cursos', ['estado' => '0'])['total']);
    }

    public function testPaginacionBusquedaYFiltros(): void
    {
        for ($i = 0; $i < 13; $i++) {
            $this->crearCurso(['nombre' => sprintf('Curso %02d', $i)]);
        }
        $lista = $this->service->listar('cursos', ['pagina' => '99999']);
        $this->assertSame(2, $lista['pagina']);
        $this->assertCount(3, $lista['filas']);
        $this->assertSame(11, $lista['desde']);
        $this->assertSame(13, $lista['hasta']);
        $this->assertSame(0, $this->service->listar('cursos', ['nivel' => 'Avanzado'])['total']);
        $this->assertSame(13, $this->service->listar('cursos', ['q' => 'Cuerdas'])['total']);
    }

    public function testCatalogoReflejaCambiosYNoInventaDatos(): void
    {
        $id = $this->crearCurso(['nombre' => '<script>Violín</script>', 'duracion_semanas' => '']);
        $catalogo = new CatalogoService($this->pdo);
        $detalle = $catalogo->detalle($id);
        $this->assertSame([], $detalle['grupos']);
        $this->assertNull($detalle['requisito']);
        $this->assertNull($detalle['duracion_semanas']);
        $this->assertSame('Sin asignar', $detalle['instructor']);
        $this->assertArrayNotHasKey('temario', $detalle);
        $this->service->cambiarEstadoCurso($id, '0');
        $this->assertNull($catalogo->detalle($id));
        $this->assertSame(0, $catalogo->catalogo([])['totalCursos']);
        $this->service->cambiarEstadoCurso($id, '1');
        $this->service->guardar('cursos', $this->curso(['disponible' => '0']), $id);
        $this->assertNull($catalogo->detalle($id));
    }

    public function testCatalogoAgrupaHorariosYFiltraPorFranjaYAcentos(): void
    {
        $id = $this->crearCurso();
        $this->grupos($id);
        $catalogo = new CatalogoService($this->pdo);
        $lista = $catalogo->catalogo(['q' => 'violin', 'horario' => ['Noche']]);
        $this->assertCount(1, $lista['cursos']);
        $this->assertCount(2, $lista['cursos'][0]['grupos']);
        $this->assertCount(2, $lista['cursos'][0]['grupos'][1]['horarios']);
        $this->assertCount(0, $catalogo->catalogo(['horario' => ['Tarde']])['cursos']);
        $this->assertSame('Ana López, Carlos Mendoza', $catalogo->detalle($id)['instructor']);
    }

    public function testSeccionesNoPermitidasYEntradasMalformadas(): void
    {
        $this->assertSame('', GestionAcademicaService::texto(['q' => ['no']], 'q'));
        $this->assertArrayHasKey('precio', $this->service->validar('cursos', $this->curso(['precio' => ['no']]))['errores']);
        $this->expectException(\InvalidArgumentException::class);
        $this->service->listar('usuario', []);
    }

    private function appAdmin(): \Slim\App
    {
        $_SESSION['usuario'] = ['id' => 1, 'nombre' => 'Administrador', 'rol' => 'admin', 'id_referencia' => 0];
        $app = $this->getAppInstance();
        $app->getContainer()->set(PDO::class, $this->pdo);
        $cuentas = $this->createMock(\App\Application\Auth\CuentaService::class);
        $cuentas->method('vigente')->willReturn(true); // Estos casos prueban gestión, no autenticación.
        $app->getContainer()->set(\App\Application\Auth\CuentaService::class, $cuentas);
        $app->getContainer()->set(PhpRenderer::class, new PhpRenderer(__DIR__ . '/../../../templates'));
        return $app;
    }

    public function testPantallasEscapanHtmlYTodosLosFormulariosPostTienenCsrf(): void
    {
        $this->crearCurso(['nombre' => '<script>alert(1)</script>']);
        $app = $this->appAdmin();
        foreach (['categorias', 'instrumentos', 'cursos'] as $seccion) {
            $response = $app->handle($this->createRequest('GET', '/admin/' . $seccion));
            $this->assertSame(200, $response->getStatusCode());
            $html = (string) $response->getBody();
            $dom = new \DOMDocument();
            @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
            $xpath = new \DOMXPath($dom);
            $forms = $xpath->query('//form[@method="post"]');
            $this->assertGreaterThan(0, $forms->length);
            foreach ($forms as $form) {
                $tokens = $xpath->query('.//input[@name="_csrf"]', $form);
                $this->assertSame(1, $tokens->length);
                $this->assertSame(Csrf::token(), $tokens->item(0)->getAttribute('value'));
            }
            $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        }
    }

    public function testPostValidoRedirigeYPostInvalidoConservaCampos(): void
    {
        $app = $this->appAdmin();
        $request = $this->createRequest('POST', '/admin/cursos')->withParsedBody($this->curso(['_csrf' => Csrf::token()]));
        $response = $app->handle($request);
        $this->assertSame(303, $response->getStatusCode());
        $this->assertStringStartsWith('/admin/cursos?editar=', $response->getHeaderLine('Location'));
        $request = $this->createRequest('POST', '/admin/cursos')->withParsedBody($this->curso(['_csrf' => Csrf::token(), 'precio' => '-10']));
        $response = $app->handle($request);
        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('value="-10"', (string) $response->getBody());
        $this->assertStringContainsString('aria-invalid="true"', (string) $response->getBody());
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM curso')->fetchColumn());
    }

    public function testTokenInvalidoImpideEscritura(): void
    {
        $app = $this->appAdmin();
        $request = $this->createRequest('POST', '/admin/cursos')->withParsedBody($this->curso(['_csrf' => 'incorrecto']));
        $this->assertSame(403, $app->handle($request)->getStatusCode());
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM curso')->fetchColumn());
    }

    public function testDetallePublicoEscapaDatosYCursoOcultoDa404(): void
    {
        $id = $this->crearCurso(['nombre' => '<script>alert(1)</script>']);
        $app = $this->appAdmin();
        $response = $app->handle($this->createRequest('GET', '/cursos/' . $id));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('&lt;script&gt;', (string) $response->getBody());
        $this->assertStringNotContainsString('<script>alert(1)</script>', (string) $response->getBody());
        $this->service->cambiarEstadoCurso($id, '0');
        $this->assertSame(404, $app->handle($this->createRequest('GET', '/cursos/' . $id))->getStatusCode());
    }

    public function testEdicionInexistenteDa404(): void
    {
        $app = $this->appAdmin();
        $this->expectException(\Slim\Exception\HttpNotFoundException::class);
        $app->handle($this->createRequest('GET', '/admin/cursos')->withQueryParams(['editar' => '9999']));
    }

    /**
     * Subida de imagen con una petición multipart fiel al navegador: los
     * campos van en $_POST (la middleware multipart los vuelca al body) y
     * el archivo en FILES. Cubre también que el público la sirva y que
     * "quitar imagen" limpie columna y disco.
     */
    public function testImagenDeReferenciaSeSubeSeSirveYSeQuita(): void
    {
        $id = $this->crearCurso();
        $app = $this->appAdmin();

        $_POST = ['_csrf' => Csrf::token()] + $this->curso();
        $peticion = $this->createRequest('POST', '/admin/cursos/' . $id, ['Content-Type' => 'multipart/form-data; boundary=----prueba'])
            ->withUploadedFiles(['imagen' => $this->archivo($this->png(800, 450))]);
        $this->assertSame(303, $app->handle($peticion)->getStatusCode());

        $imagen = (string) ($this->service->buscar('cursos', $id)['imagen'] ?? '');
        $ruta = dirname(__DIR__, 3) . '/var/uploads/cursos/' . $imagen;
        if ($imagen !== '') {
            $this->archivosCreados[] = $ruta;
        }
        $this->assertNotEmpty($imagen, 'La subida debe registrar la columna curso.imagen.');
        $this->assertFileExists($ruta);

        // El público la sirve con el MIME correcto.
        $img = $app->handle($this->createRequest('GET', '/cursos/' . $id . '/imagen'));
        $this->assertSame(200, $img->getStatusCode());
        $this->assertSame('image/png', $img->getHeaderLine('Content-Type'));

        // La tarjeta del catálogo y la cabecera del detalle la muestran.
        $catalogo = (string) $app->handle($this->createRequest('GET', '/cursos'))->getBody();
        $this->assertStringContainsString('catalog-card-img', $catalogo);
        $this->assertStringContainsString('/cursos/' . $id . '/imagen', $catalogo);
        $detalle = (string) $app->handle($this->createRequest('GET', '/cursos/' . $id))->getBody();
        $this->assertStringContainsString('course-detail-img', $detalle);

        // Quitar: columna a NULL, fichero fuera del disco y 404 público.
        $quitar = $app->handle($this->createRequest('POST', '/admin/cursos/' . $id . '/imagen/quitar')
            ->withParsedBody(['_csrf' => Csrf::token()]));
        $this->assertSame(303, $quitar->getStatusCode());
        $this->assertNull($this->service->buscar('cursos', $id)['imagen']);
        $this->assertFileDoesNotExist($ruta);
        $this->assertSame(404, $app->handle($this->createRequest('GET', '/cursos/' . $id . '/imagen'))->getStatusCode());

        // Sin imagen, la tarjeta vuelve al icono de respaldo.
        $sinImagen = (string) $app->handle($this->createRequest('GET', '/cursos'))->getBody();
        $this->assertStringNotContainsString('/cursos/' . $id . '/imagen', $sinImagen);
        $this->assertStringContainsString('bi-music-note-beamed', $sinImagen);
    }

    public function testEditarSinArchivoConservaLaImagenYConArchivoLaReemplaza(): void
    {
        $id = $this->crearCurso();

        $r = $this->service->guardar('cursos', $this->curso(['nombre' => 'Con imagen']), $id, $this->archivo($this->png(800, 450)));
        $this->assertSame([], $r['errores']);
        $primera = (string) $this->service->buscar('cursos', $id)['imagen'];
        $rutaPrimera = dirname(__DIR__, 3) . '/var/uploads/cursos/' . $primera;
        $this->archivosCreados[] = $rutaPrimera;
        $this->assertNotEmpty($primera);
        $this->assertFileExists($rutaPrimera);

        // Editar sin archivo nuevo: la imagen se conserva.
        $r2 = $this->service->guardar('cursos', $this->curso(['nombre' => 'Sin imagen nueva']), $id, null);
        $this->assertSame([], $r2['errores']);
        $this->assertSame($primera, $this->service->buscar('cursos', $id)['imagen'], 'Editar sin archivo no debe pisar la imagen.');

        // Editar con archivo nuevo: se sustituye y se retira el anterior.
        $r3 = $this->service->guardar('cursos', $this->curso(['nombre' => 'Con imagen nueva']), $id, $this->archivo($this->png(800, 450)));
        $this->assertSame([], $r3['errores']);
        $segunda = (string) $this->service->buscar('cursos', $id)['imagen'];
        $rutaSegunda = dirname(__DIR__, 3) . '/var/uploads/cursos/' . $segunda;
        $this->archivosCreados[] = $rutaSegunda;
        $this->assertNotSame($primera, $segunda, 'La imagen nueva debe ser distinta de la anterior.');
        $this->assertFileExists($rutaSegunda);
        $this->assertFileDoesNotExist($rutaPrimera, 'La imagen sustituida debe borrarse del disco.');
    }

    public function testImagenConFormatoNoValidoDevuelveErrorSinGuardar(): void
    {
        $id = $this->crearCurso();
        $app = $this->appAdmin();

        // SVG: MIME real fuera de la lista (y motivo de seguridad, XSS).
        $_POST = ['_csrf' => Csrf::token()] + $this->curso();
        $peticion = $this->createRequest('POST', '/admin/cursos/' . $id, ['Content-Type' => 'multipart/form-data; boundary=----prueba'])
            ->withUploadedFiles(['imagen' => $this->archivo('<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg"><rect/></svg>', 'x.svg')]);
        $respuesta = $app->handle($peticion);
        $html = (string) $respuesta->getBody();

        $this->assertSame(422, $respuesta->getStatusCode());
        $this->assertNull($this->service->buscar('cursos', $id)['imagen'], 'No debe guardarse una imagen rechazada.');
        $this->assertStringContainsString('Formato no válido', $html);
        // El error de validación se muestra también como toast de SweetAlert2.
        $this->assertStringContainsString("solfAviso('error'", $html);
    }
}
