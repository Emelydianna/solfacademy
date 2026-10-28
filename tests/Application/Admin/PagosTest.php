<?php

declare(strict_types=1);

namespace Tests\Application\Admin;

use App\Application\Admin\PagosService;
use App\Application\Estudiante\EstudiantePortalService;
use App\Application\Pagos\ComprobanteStorage;
use App\Application\Security\Csrf;
use Psr\Http\Message\UploadedFileInterface;
use Slim\Views\PhpRenderer;

/**
 * Verificación de comprobantes: el estudiante envía el archivo y el monto,
 * y solo el administrador puede acreditarlo o rechazarlo.
 */
final class PagosTest extends GestionOperativaTestCase
{
    private PagosService $pagos;
    private EstudiantePortalService $portal;

    /** @var list<string> */
    private array $archivosCreados = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->pagos = new PagosService($this->pdo);
        $this->portal = new EstudiantePortalService($this->pdo);
    }

    protected function tearDown(): void
    {
        foreach ($this->archivosCreados as $archivo) {
            if (is_file($archivo)) {
                @unlink($archivo);
            }
        }
        $this->archivosCreados = [];
        parent::tearDown();
    }

    /** Archivo subido cuyo moveTo escribe de verdad en disco. */
    private function archivo(string $contenido, string $nombre = 'recibo.pdf'): UploadedFileInterface
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

    /** @return array{ok: bool, mensaje: string} */
    private function enviar(int $idInscripcion, array $datos = [], ?UploadedFileInterface $file = null): array
    {
        return $this->portal->enviarComprobante(1, $idInscripcion, array_merge([
            'monto' => '49.95', 'metodo' => 'Transferencia bancaria', 'referencia' => 'TRF-0001',
        ], $datos), $file ?? $this->archivo('comprobante de pago'));
    }

    private function urlSubida(int $idPago): ?string
    {
        $stmt = $this->pdo->prepare('SELECT comprobante_url FROM pago WHERE id_pago = :id');
        $stmt->execute(['id' => $idPago]);
        $url = $stmt->fetchColumn();
        return $url === false ? null : (string) $url;
    }

    /**
     * [total pagado, saldo] de una matrícula calculados como lo hace
     * v_inscripciones_detalle (las vistas no ven las tablas temporales
     * de las pruebas, así que se replica la fórmula aquí).
     *
     * @return array{0: float, 1: float}
     */
    private function saldoDe(int $idInscripcion): array
    {
        $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(monto_pagado), 0) FROM pago
            WHERE id_inscripcion = :inscripcion AND estado = 'Pagado'");
        $stmt->execute(['inscripcion' => $idInscripcion]);
        $total = round((float) $stmt->fetchColumn(), 2);
        $stmt = $this->pdo->prepare('SELECT precio_inscripcion FROM inscripcion WHERE id_inscripcion = :inscripcion');
        $stmt->execute(['inscripcion' => $idInscripcion]);
        $precio = (float) $stmt->fetchColumn();
        return [$total, round($precio - $total, 2)];
    }

    public function testElComprobantePendienteNoDescuentaHastaQueElAdminLoAprueba(): void
    {
        $inscripcion = $this->matricular($this->crearGrupo());

        $r = $this->enviar($inscripcion);
        $this->assertTrue($r['ok'], $r['mensaje']);

        $idPago = (int) $this->pdo->query('SELECT MAX(id_pago) FROM pago')->fetchColumn();
        $this->assertSame('Pendiente', $this->pdo->query("SELECT estado FROM pago WHERE id_pago = $idPago")->fetchColumn());

        // El comprobante enviado todavía no toca el saldo.
        [$total, $saldo] = $this->saldoDe($inscripcion);
        $this->assertSame(0.0, $total);
        $this->assertEqualsWithDelta(49.95, $saldo, 0.001);

        $url = $this->urlSubida($idPago);
        $this->assertNotNull($url);
        $this->assertTrue(ComprobanteStorage::existe($url));
        $this->assertStringStartsWith('/comprobante/', (string) $url);

        // La cola de verificación lo muestra.
        $this->assertSame(1, $this->pagos->listar(['estado' => 'Pendiente'])['total']);
        $this->assertSame(1, $this->pagos->resumen()['por_verificar']);

        $r = $this->pagos->aprobar($idPago, 1);
        $this->assertTrue($r['ok'], $r['mensaje']);
        $this->assertSame('Pagado', $this->pdo->query("SELECT estado FROM pago WHERE id_pago = $idPago")->fetchColumn());
        $this->assertSame(1, (int) $this->pdo->query("SELECT verificado_por FROM pago WHERE id_pago = $idPago")->fetchColumn());
        $this->assertNotNull($this->pdo->query("SELECT fecha_verificacion FROM pago WHERE id_pago = $idPago")->fetchColumn());

        [$total, $saldo] = $this->saldoDe($inscripcion);
        $this->assertEqualsWithDelta(49.95, $total, 0.001);
        $this->assertSame(0.0, $saldo);
        $this->assertSame(0, $this->pagos->resumen()['por_verificar']);
    }

    public function testValidacionesDelEnvioDelEstudiante(): void
    {
        $otroGrupo = $this->crearGrupo(['id_curso' => '2', 'id_instructor' => '3', 'id_aula' => '2',
            'horarios' => [['dia_semana' => 'Martes', 'hora_inicio' => '10:00', 'hora_fin' => '11:00']]]);
        $inscripcion = $this->matricular($this->crearGrupo());
        $ajena = $this->matricular($otroGrupo, 'Activa', 2);

        $this->assertFalse($this->enviar($inscripcion, ['monto' => '0'])['ok']);
        $this->assertFalse($this->enviar($inscripcion, ['monto' => '50'])['ok'], 'No debe aceptar más del saldo pendiente.');
        $this->assertFalse($this->enviar($inscripcion, ['metodo' => 'Cripto'])['ok']);
        $this->assertFalse($this->enviar($ajena)['ok'], 'No debe enviar comprobantes de una matrícula ajena.');
        $this->assertFalse($this->enviar($inscripcion, [], $this->archivo('x', 'recibo.php'))['ok']);
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM pago')->fetchColumn());

        $this->assertTrue($this->enviar($inscripcion)['ok']);
        $this->assertFalse($this->enviar($inscripcion)['ok'], 'No debe haber dos comprobantes en verificación a la vez.');
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM pago')->fetchColumn());

        // Matrícula ya saldada: no hay nada que enviar.
        $this->pdo->exec("INSERT INTO pago (id_inscripcion, fecha_pago, monto_pagado, estado) VALUES ($inscripcion, CURDATE(), 49.95, 'Pagado')");
        $r = $this->enviar($inscripcion, ['monto' => '1']);
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('saldada', $r['mensaje']);
    }

    public function testRechazoConMotivoPermiteVolverAEnviar(): void
    {
        $inscripcion = $this->matricular($this->crearGrupo());
        $this->assertTrue($this->enviar($inscripcion)['ok']);
        $idPago = (int) $this->pdo->query('SELECT MAX(id_pago) FROM pago')->fetchColumn();

        $this->assertFalse($this->pagos->rechazar($idPago, 1, '   ')['ok']);

        $r = $this->pagos->rechazar($idPago, 1, 'La imagen no corresponde al monto declarado');
        $this->assertTrue($r['ok'], $r['mensaje']);
        $this->assertSame('Fallido', $this->pdo->query("SELECT estado FROM pago WHERE id_pago = $idPago")->fetchColumn());
        $motivo = (string) $this->pdo->query("SELECT motivo_verificacion FROM pago WHERE id_pago = $idPago")->fetchColumn();
        $this->assertSame('La imagen no corresponde al monto declarado', $motivo);

        // El alumno puede enviar otro comprobante y el saldo sigue intacto.
        $this->assertTrue($this->enviar($inscripcion)['ok']);
        $this->assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM pago')->fetchColumn());
        $this->assertSame(0.0, $this->saldoDe($inscripcion)[0], 'El pago rechazado no cuenta como saldo.');
        $this->assertSame(1, $this->pagos->listar(['estado' => 'Pendiente'])['total']);
    }

    public function testNoSeApruebaDosVecesNiSinArchivoDisponible(): void
    {
        $inscripcion = $this->matricular($this->crearGrupo());
        $this->assertTrue($this->enviar($inscripcion)['ok']);
        $idPago = (int) $this->pdo->query('SELECT MAX(id_pago) FROM pago')->fetchColumn();

        // Un pago sin comprobante no puede verificarse.
        $this->pdo->exec("INSERT INTO pago (id_inscripcion, fecha_pago, monto_pagado, estado)
            VALUES ($inscripcion, CURDATE(), 10.00, 'Pendiente')");
        $sinArchivo = (int) $this->pdo->query('SELECT MAX(id_pago) FROM pago')->fetchColumn();
        $this->assertFalse($this->pagos->aprobar($sinArchivo, 1)['ok']);

        // El archivo desapareció del servidor: se bloquea la aprobación.
        $this->assertTrue($this->pagos->aprobar($idPago, 1)['ok']);
        $r = $this->pagos->aprobar($idPago, 1);
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('pendiente', $r['mensaje']);

        $this->pdo->exec("UPDATE pago SET estado = 'Pendiente', fecha_verificacion = NULL WHERE id_pago = $idPago");
        $ruta = ComprobanteStorage::rutaAbsoluta(basename((string) $this->urlSubida($idPago)));
        $respaldo = (string) file_get_contents($ruta);
        unlink($ruta);
        $r = $this->pagos->aprobar($idPago, 1);
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('no está disponible', $r['mensaje']);
        file_put_contents($ruta, $respaldo);
        $this->archivosCreados[] = $ruta;
    }

    public function testApruebaConAvisoCuandoSeExcedeElPrecio(): void
    {
        $inscripcion = $this->matricular($this->crearGrupo());
        $this->assertTrue($this->enviar($inscripcion, ['monto' => '40'])['ok']);
        $idPago = (int) $this->pdo->query('SELECT MAX(id_pago) FROM pago')->fetchColumn();

        // El precio congelado queda por debajo de lo pagado (ajuste externo).
        $this->pdo->exec("UPDATE inscripcion SET precio_inscripcion = 30.00 WHERE id_inscripcion = $inscripcion");

        $r = $this->pagos->aprobar($idPago, 1);
        $this->assertTrue($r['ok']);
        $this->assertStringContainsString('supera el precio', $r['mensaje']);
        $this->assertSame('Pagado', $this->pdo->query("SELECT estado FROM pago WHERE id_pago = $idPago")->fetchColumn());
    }

    public function testHttpEnvioPorElEstudianteYVerificacionComoAdmin(): void
    {
        $inscripcion = $this->matricular($this->crearGrupo());
        $app = $this->appAdmin();

        // El estudiante envía su comprobante desde /estudiante/pagos.
        $_SESSION['usuario'] = ['id' => 2, 'rol' => 'estudiante', 'id_referencia' => 1];
        $r = $app->handle($this->createRequest('GET', '/estudiante/pagos'));
        $this->assertSame(200, $r->getStatusCode());
        $this->assertStringContainsString('Enviar comprobante', (string) $r->getBody());

        $r = $app->handle($this->createRequest('POST', '/estudiante/pagos/' . $inscripcion . '/comprobante')
            ->withParsedBody(['_csrf' => Csrf::token(), 'monto' => '49.95', 'metodo' => 'Efectivo'])
            ->withUploadedFiles(['comprobante' => $this->archivo('contenido')]));
        $this->assertSame(303, $r->getStatusCode());
        $this->assertSame('/estudiante/pagos', $r->getHeaderLine('Location'));
        $this->assertSame('Pendiente', $this->pdo->query('SELECT estado FROM pago')->fetchColumn());

        $idPago = (int) $this->pdo->query('SELECT MAX(id_pago) FROM pago')->fetchColumn();
        $archivo = basename((string) $this->urlSubida($idPago));
        $this->assertSame(200, $app->handle($this->createRequest('GET', '/estudiante/pagos/comprobante/' . $archivo))->getStatusCode());

        // El administrador revisa y acredita.
        $app = $this->appAdmin();
        $r = $app->handle($this->createRequest('GET', '/admin/pagos'));
        $this->assertSame(200, $r->getStatusCode());
        $this->assertStringContainsString('Por verificar', (string) $r->getBody());

        $r = $app->handle($this->createRequest('GET', '/admin/pagos/' . $idPago));
        $this->assertSame(200, $r->getStatusCode());
        $this->assertStringContainsString('APROBAR COMPROBANTE', (string) $r->getBody());
        $this->assertSame(200, $app->handle($this->createRequest('GET', '/admin/pagos/comprobante/' . $archivo))->getStatusCode());
        $this->assertSame(404, $app->handle($this->createRequest('GET', '/admin/pagos/comprobante/comprobante_999999_1.pdf'))->getStatusCode());

        $r = $app->handle($this->createRequest('POST', '/admin/pagos/' . $idPago . '/aprobar')->withParsedBody(['_csrf' => Csrf::token()]));
        $this->assertSame(303, $r->getStatusCode());
        $this->assertSame('Pagado', $this->pdo->query("SELECT estado FROM pago")->fetchColumn());

        $r = $app->handle($this->createRequest('POST', '/admin/pagos/' . $idPago . '/rechazar')->withParsedBody(['_csrf' => Csrf::token(), 'motivo' => 'x']));
        $this->assertSame(303, $r->getStatusCode());
        $this->assertSame('Pagado', $this->pdo->query("SELECT estado FROM pago")->fetchColumn(), 'Solo se verifica una vez.');

        // Un visitante sin rol de admin no llega a la sección.
        $_SESSION['usuario'] = ['id' => 2, 'rol' => 'estudiante', 'id_referencia' => 1];
        $r = $app->handle($this->createRequest('GET', '/admin/pagos'));
        $this->assertSame(302, $r->getStatusCode());
    }

    public function testLosArchivosSubidosNoEstanEnPublic(): void
    {
        $inscripcion = $this->matricular($this->crearGrupo());
        $this->assertTrue($this->enviar($inscripcion)['ok']);
        $idPago = (int) $this->pdo->query('SELECT MAX(id_pago) FROM pago')->fetchColumn();
        $url = $this->urlSubida($idPago);
        $archivo = basename((string) $url);

        $this->assertSame('/comprobante/' . $archivo, (string) $url);
        $this->assertFileExists(dirname(__DIR__, 3) . '/var/uploads/comprobantes/' . $archivo);
        $this->assertFileDoesNotExist(dirname(__DIR__, 3) . '/public/' . $archivo);
        $this->assertFileDoesNotExist(dirname(__DIR__, 3) . '/public/comprobantes/' . $archivo);
        // El nombre identifica al pago que lo subió.
        $this->assertSame($idPago, ComprobanteStorage::idPagoDe($archivo));
    }

    /** El renderer del panel es necesario para las rutas HTML del admin. */
    protected function appAdmin(): \Slim\App
    {
        $app = parent::appAdmin();
        $app->getContainer()->set(PhpRenderer::class, new PhpRenderer(__DIR__ . '/../../../templates'));
        $app->getContainer()->set(PagosService::class, $this->pagos);
        $app->getContainer()->set(EstudiantePortalService::class, $this->portal);
        return $app;
    }
}
