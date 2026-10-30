<?php

declare(strict_types=1);

namespace App\Controllers\Estudiante;

use App\Application\Config\ConfiguracionService;
use App\Application\Estudiante\EstudiantePortalService;
use App\Application\Formato\Pdf;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Certificado en PDF de una inscripción finalizada
 * (GET /estudiante/certificado/{id}).
 *
 * El PDF lleva el logo y los datos de la academia (sin teléfono), el
 * nombre del estudiante, el curso y la fecha en que obtuvo el certificado.
 */
class CertificadoController
{
    private EstudiantePortalService $portal;
    private ConfiguracionService $configuracion;

    public function __construct(EstudiantePortalService $portal, ConfiguracionService $configuracion)
    {
        $this->portal = $portal;
        $this->configuracion = $configuracion;
    }

    public function descargar(Request $request, Response $response, array $args): Response
    {
        $usuario = $_SESSION['usuario'] ?? [];
        $idEstudiante = (int) ($usuario['id_referencia'] ?? 0);
        $id = (int) ($args['id'] ?? 0);

        $datos = $id > 0 ? $this->portal->datosCertificado($id, $idEstudiante) : null;

        // Solo el dueño y solo inscripciones finalizadas: cualquier otro
        // caso vuelve a Mis Cursos sin filtrar la existencia del registro.
        if ($datos === null || $datos['estado'] !== 'Finalizada') {
            return $response->withStatus(302)->withHeader('Location', '/estudiante/cursos');
        }

        $pdf = new Pdf();
        $this->dibujar($pdf, $datos, $this->configuracion->obtener());
        $documento = $pdf->construir();

        $response->getBody()->write($documento);

        return $response
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', 'attachment; filename="certificado-' . $id . '-' . date('Ymd') . '.pdf"')
            ->withHeader('Cache-Control', 'no-store');
    }

    /**
     * @param array<string, mixed> $datos Fila de datosCertificado()
     * @param array<string, mixed> $config ConfiguracionService::obtener()
     */
    private function dibujar(Pdf $pdf, array $datos, array $config): void
    {
        // Marco doble (dorado + fino oscuro), como en un diploma
        $pdf->marco(24, 24, Pdf::ANCHO - 48, Pdf::ALTO - 48, 3, Pdf::DORADO);
        $pdf->marco(34, 34, Pdf::ANCHO - 68, Pdf::ALTO - 68, 1, Pdf::TEXTO);

        $pdf->espacio(24);

        // Logo de la academia (subido en /admin/configuracion)
        $logo = ConfiguracionService::archivoLogo();
        if ($logo !== null) {
            $anchoLogo = 96;
            $altoLogo = $pdf->imagen($logo, (int) round((Pdf::ANCHO - $anchoLogo) / 2), $pdf->yActual(), $anchoLogo);
            $pdf->espacio($altoLogo + 8);
        }

        $nombreAcademia = trim((string) $config['nombre']);
        $pdf->textoCentrado(24, mb_strtoupper($nombreAcademia), Pdf::DORADO, true);

        $lema = trim((string) ($config['lema'] ?? ''));
        if ($lema !== '') {
            $pdf->espacio(2);
            $pdf->textoCentrado(11, $lema, Pdf::TENUE);
        }

        $pdf->espacio(8);
        $pdf->barra(170, 2, Pdf::DORADO, (int) round((Pdf::ANCHO - 170) / 2));

        $pdf->espacio(34);
        $pdf->textoCentrado(16, 'CERTIFICADO DE FINALIZACIÓN', Pdf::TEXTO, true);

        $pdf->espacio(26);
        $pdf->textoCentrado(12, 'Se certifica que', Pdf::TENUE);

        $pdf->espacio(22);
        $pdf->textoCentrado(30, trim(((string) $datos['nombre']) . ' ' . ((string) $datos['apellido'])), Pdf::TEXTO, true);

        $pdf->espacio(20);
        $pdf->textoCentrado(12, 'ha completado satisfactoriamente el curso', Pdf::TENUE);

        $pdf->espacio(24);
        $pdf->textoCentrado(22, (string) $datos['curso'], Pdf::DORADO, true);

        $pdf->espacio(24);
        $pdf->textoCentrado(12, 'Fecha de obtención: ' . $this->fechaLarga($datos), Pdf::TEXTO);

        $this->pieAcademia($pdf, $config);
    }

    /**
     * Datos de la academia al pie del certificado: dirección, ciudad,
     * correo, redes y nota final. El teléfono queda fuera a propósito.
     *
     * @param array<string, mixed> $config
     */
    private function pieAcademia(Pdf $pdf, array $config): void
    {
        // Hueco fijo tras el cuerpo para separar el pie del contenido
        $pdf->espacio(26);
        $pdf->barra(170, 1, [0.85, 0.87, 0.90], (int) round((Pdf::ANCHO - 170) / 2));

        // Dirección y ciudad
        $contacto = trim(((string) ($config['direccion'] ?? '')) . ' · ' . ((string) ($config['ciudad'] ?? '')), ' ·');
        if ($contacto !== '') {
            $this->lineaPie($pdf, $contacto, 100);
        }

        // Correo y redes sociales (si están configuradas), nunca el teléfono
        $canales = array_filter([
            trim((string) ($config['email_contacto'] ?? '')),
            trim((string) ($config['facebook'] ?? '')),
            trim((string) ($config['instagram'] ?? '')),
            trim((string) ($config['youtube'] ?? '')),
        ], static fn (string $v): bool => $v !== '');
        if ($canales !== []) {
            $this->lineaPie($pdf, implode('  ·  ', $canales), 78);
        }

        $nota = trim((string) ($config['nota_pdf'] ?? '')) !== ''
            ? trim((string) $config['nota_pdf'])
            : trim((string) ($config['pie_pagina'] ?? ''));
        if ($nota !== '') {
            $this->lineaPie($pdf, $nota, 56);
        }
    }

    private function lineaPie(Pdf $pdf, string $texto, int $y): void
    {
        $pdf->textoAbsoluto(9, $texto, $pdf->xCentrado(9, $texto), $y, Pdf::TENUE);
    }

    /**
     * @param array<string, mixed> $datos
     */
    private function fechaLarga(array $datos): string
    {
        // Fecha en que obtuvo el certificado: revisión final, fin del grupo
        // o, en su defecto, la fecha de inscripción.
        $fecha = (string) ($datos['fecha_revision'] ?? $datos['fecha_fin'] ?? $datos['fecha_inscripcion'] ?? '');
        $timestamp = strtotime($fecha !== '' ? $fecha : 'now');
        if ($timestamp === false) {
            $timestamp = time();
        }

        $meses = [
            1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
            5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
            9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
        ];

        return (int) date('j', $timestamp) . ' de ' . $meses[(int) date('n', $timestamp)] . ' de ' . date('Y', $timestamp);
    }
}
