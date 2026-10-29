<?php

declare(strict_types=1);

namespace Tests\Application\Admin;

use App\Application\Security\Csrf;

/**
 * La edición de grupos y los formularios admin muestran los errores de
 * validación como toast de SweetAlert2 (más el div inline, que se mantiene
 * por accesibilidad). El formulario vive en una columna lateral, así que sin
 * el toast un 422 se percibía como "solo recargó".
 */
final class SweetAlertErroresTest extends GestionOperativaTestCase
{
    public function testCambioDeInstructorGuardaYEmiteToastDeExito(): void
    {
        $id = $this->crearGrupo(); // curso Violín (instrumento 1), instructor 1 (Ana, también Violín)
        $app = $this->appAdmin();

        $datos = $this->datosGrupo(['_csrf' => Csrf::token(), 'id_instructor' => '3', 'confirmar_reasignacion' => '1']);
        $r = $app->handle($this->createRequest('POST', '/admin/grupos/' . $id)->withParsedBody($datos));

        $this->assertSame(303, $r->getStatusCode());
        $this->assertSame(3, (int) $this->grupos->buscar($id)['id_instructor'], 'El cambio de instructor debe persistir.');

        // Tras el redirect, la página destino emite el toast de éxito (flash).
        [$ruta, $qs] = array_pad(explode('?', $r->getHeaderLine('Location'), 2), 2, '');
        $peticion = $this->createRequest('GET', $ruta);
        if ($qs !== '') {
            $peticion = $peticion->withUri($peticion->getUri()->withQuery($qs));
        }
        $html = (string) $app->handle($peticion)->getBody();
        $this->assertStringContainsString('solfAviso(', $html, 'El flash de éxito debe emitir el toast.');
        $this->assertStringContainsString('sweetalert2@11', $html);
        $this->assertStringContainsString('/js/notificaciones.js', $html);
    }

    /**
     * @dataProvider proveedorErroresDeValidacion
     */
    public function testErrorDeValidacionEmiteToastSweetAlert(array $cambio, string $esperado): void
    {
        $id = $this->crearGrupo();
        $app = $this->appAdmin();

        $datos = $this->datosGrupo(array_merge(['_csrf' => Csrf::token()], $cambio));
        $r = $app->handle($this->createRequest('POST', '/admin/grupos/' . $id)->withParsedBody($datos));
        $html = (string) $r->getBody();

        $this->assertSame(422, $r->getStatusCode());
        $this->assertSame(1, (int) $this->grupos->buscar($id)['id_instructor'], 'La validación debe impedir el guardado.');
        $this->assertStringContainsString($esperado, $html, 'Debe mostrarse el motivo del rechazo.');
        $this->assertStringContainsString("solfAviso('error'", $html, 'El error de validación debe emitir el toast de SweetAlert2.');
    }

    /** @return array<string, array{0: array<string, string>, 1: string}> */
    public function proveedorErroresDeValidacion(): array
    {
        return [
            'sin confirmar reasignación' => [['id_instructor' => '3'], 'Confirma'],
            'instructor no especializado' => [['id_instructor' => '2', 'confirmar_reasignacion' => '1'], 'especializado'],
        ];
    }

    public function testCruceDeHorarioDelInstructorEmiteToast(): void
    {
        // Grupo A: instructor 3, aula 1, Lunes 09:00-10:00.
        $this->crearGrupo(['id_instructor' => '3']);
        // Grupo B: instructor 1, aula 2, mismo Lunes 09:00-10:00 (sin cruce al crear).
        $idB = $this->crearGrupo(['id_aula' => '2']);
        $app = $this->appAdmin();

        // Cambiar B: instructor 1→3, pero el 3 ya imparte A el Lunes 09:00-10:00.
        $datos = $this->datosGrupo(['_csrf' => Csrf::token(), 'id_aula' => '2', 'id_instructor' => '3', 'confirmar_reasignacion' => '1']);
        $r = $app->handle($this->createRequest('POST', '/admin/grupos/' . $idB)->withParsedBody($datos));
        $html = (string) $r->getBody();

        $this->assertSame(422, $r->getStatusCode());
        $this->assertSame(1, (int) $this->grupos->buscar($idB)['id_instructor'], 'El cruce debe impedir el guardado.');
        $this->assertStringContainsString('Cruce', $html);
        $this->assertStringContainsString("solfAviso('error'", $html, 'El cruce debe emitir el toast de SweetAlert2.');
    }

    public function testOtroFormularioAdminTambienEmiteToastDeError(): void
    {
        $app = $this->appAdmin();
        // Código demasiado corto en descuentos: error de validación en form.
        $r = $app->handle($this->createRequest('POST', '/admin/descuentos')->withParsedBody([
            '_csrf' => Csrf::token(), 'codigo' => 'x', 'tipo' => 'monto', 'monto_fijo' => '20',
            'fecha_inicio' => '2000-01-01', 'fecha_fin' => '2099-12-31', 'id_curso' => '0', 'sin_limite' => '1',
        ]));

        $this->assertSame(303, $r->getStatusCode(), 'Los errores de campo reenvían al formulario.');
        $html = (string) $app->handle($this->createRequest('GET', $r->getHeaderLine('Location')))->getBody();
        $this->assertStringContainsString("solfAviso('error'", $html, 'El formulario de descuentos también debe tostar el error.');
    }
}
