<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Application\Admin\ReportesService;
use App\Application\Formato\Pdf;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

/**
 * Métricas y Reportes Generales (/admin/reportes): indicadores, distribución
 * de alumnos por instrumento, programas más populares y exportación a PDF.
 */
final class ReportesController
{
    private PhpRenderer $view;
    private ReportesService $service;

    public function __construct(PhpRenderer $view, ReportesService $service)
    {
        $this->view = $view;
        $this->service = $service;
    }

    public function index(Request $request, Response $response): Response
    {
        $this->view->setLayout('admin/layout.php');
        return $this->view->render($response, 'admin/reportes/index.php', [
            'tituloPagina' => 'Métricas y Reportes — SolfAcademy',
            'paginaAdmin'  => 'reportes',
            'datos'        => $this->service->datos(),
        ]);
    }

    /**
     * GET /admin/reportes/exportar → descarga directa del PDF del reporte.
     */
    public function exportar(Request $request, Response $response): Response
    {
        $pdf = new Pdf();
        $this->service->dibujarPdf($pdf, $this->service->datos());
        $documento = $pdf->construir();

        $response->getBody()->write($documento);

        return $response
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', 'attachment; filename="reportes-generales-' . date('Ymd') . '.pdf"')
            ->withHeader('Cache-Control', 'no-store');
    }
}
