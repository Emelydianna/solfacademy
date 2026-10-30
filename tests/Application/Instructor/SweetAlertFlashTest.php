<?php

declare(strict_types=1);

namespace Tests\Application\Instructor;

use App\Application\Security\Csrf;
use PDO;
use Slim\Views\PhpRenderer;
use Tests\Application\Admin\GestionOperativaTestCase;

/**
 * Los avisos post-redirect-get del portal del instructor (asistencia y
 * calificaciones) se emiten como toast de SweetAlert2, igual que el resto
 * del sistema, y no como un div inline de HTML.
 */
final class SweetAlertFlashTest extends GestionOperativaTestCase
{
    private int $idGrupo = 0;
    private int $idInscripcion = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->idGrupo = $this->crearGrupo(); // instructor 1 (Ana)
        $this->idInscripcion = $this->matricular($this->idGrupo); // estudiante 1, Activa
        $this->pdo->exec(
            "INSERT INTO usuario (id_usuario, email_usuario, password_hash, rol, id_instructor)
             VALUES (3, 'ana@prueba.test', '', 'instructor', 1)"
        );
    }

    public function testAsistenciaGuardadaEmiteToastDeExito(): void
    {
        $app = $this->appInstructor();

        $r = $app->handle($this->createRequest('POST', '/instructor/asistencias/guardar')->withParsedBody([
            '_csrf' => Csrf::token(),
            'id_grupo' => (string) $this->idGrupo,
            'fecha' => '2026-10-29',
            'asistencia' => [(string) $this->idInscripcion => 'presente'],
        ]));
        $this->assertSame(302, $r->getStatusCode());

        $html = $this->seguir($app, $r->getHeaderLine('Location'));
        $this->assertStringContainsString("solfAviso('success'", $html, 'La asistencia debe emitir el toast de éxito.');
        $this->assertStringContainsString('Registro de asistencia guardado correctamente', $html);
        $this->assertStringContainsString('sweetalert2@11', $html);
        $this->assertStringContainsString('/js/notificaciones.js', $html);
    }

    public function testEvaluacionCreadaEmiteToastDeExito(): void
    {
        $app = $this->appInstructor();

        $r = $app->handle($this->createRequest('POST', '/instructor/calificaciones/crear')->withParsedBody([
            '_csrf' => Csrf::token(),
            'id_grupo' => (string) $this->idGrupo,
            'tipo' => 'Examen',
            'fecha_evaluacion' => '2026-10-15',
            'descripcion' => 'Prueba técnica',
        ]));
        $this->assertSame(302, $r->getStatusCode());
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM evaluacion')->fetchColumn());

        $html = $this->seguir($app, $r->getHeaderLine('Location'));
        $this->assertStringContainsString("solfAviso('success'", $html, 'La evaluación creada debe emitir el toast.');
        // Mensaje json_encodeado: se comprueba un tramo sin acentos.
        $this->assertStringContainsString('creada para 1 alumno', $html);
    }

    public function testRechazoDeEvaluacionEmiteToastDeError(): void
    {
        $app = $this->appInstructor();

        // Tipo vacío → el service devuelve ok=false con su motivo.
        $r = $app->handle($this->createRequest('POST', '/instructor/calificaciones/crear')->withParsedBody([
            '_csrf' => Csrf::token(),
            'id_grupo' => (string) $this->idGrupo,
            'tipo' => '',
        ]));
        $this->assertSame(302, $r->getStatusCode());

        $html = $this->seguir($app, $r->getHeaderLine('Location'));
        $this->assertStringContainsString("solfAviso('error'", $html, 'El rechazo debe emitir el toast de error.');
        // El mensaje viaja json_encodeado (acentos como \u00f3): se busca un tramo ASCII.
        $this->assertStringContainsString('El tipo de evaluaci', $html);
    }

    private function appInstructor(): \Slim\App
    {
        $_SESSION['usuario'] = ['id' => 3, 'rol' => 'instructor', 'nombre' => 'Ana López', 'id_referencia' => 1, 'version_sesion' => 0];
        $app = $this->getAppInstance();
        $app->getContainer()->set(PDO::class, $this->pdo);
        $app->getContainer()->set(PhpRenderer::class, new PhpRenderer(__DIR__ . '/../../../templates'));

        return $app;
    }

    /** Sigue el redirect (Location puede traer query string). */
    private function seguir(\Slim\App $app, string $location): string
    {
        [$ruta, $qs] = array_pad(explode('?', $location, 2), 2, '');
        $peticion = $this->createRequest('GET', $ruta);
        if ($qs !== '') {
            $peticion = $peticion->withUri($peticion->getUri()->withQuery($qs));
        }

        return (string) $app->handle($peticion)->getBody();
    }
}
