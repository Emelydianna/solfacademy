<?php

declare(strict_types=1);

namespace App\Controllers\Estudiante;

use App\Application\Estudiante\EstudiantePortalService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

/**
 * Pantalla 5 del portal: Calificaciones y Evaluaciones (/estudiante/calificaciones).
 */
class CalificacionesController
{
    private PhpRenderer $view;
    private EstudiantePortalService $portal;

    public function __construct(PhpRenderer $view, EstudiantePortalService $portal)
    {
        $this->view = $view;
        $this->portal = $portal;
    }

    public function index(Request $request, Response $response): Response
    {
        $usuario = $_SESSION['usuario'] ?? [];

        // El portal es solo para alumnos (instructores/admin: fase futura)
        if (($usuario['rol'] ?? '') !== 'estudiante') {
            return $response->withStatus(302)->withHeader('Location', '/');
        }

        $this->view->setLayout('estudiante/layout.php');

        return $this->view->render($response, 'estudiante/calificaciones.php', [
            'tituloPagina' => 'Calificaciones — Portal del Estudiante',
            'paginaPortal' => 'calificaciones',
            'datos' => $this->portal->calificaciones((int) $usuario['id_referencia']),
        ]);
    }

    public function descargarBoletin(Request $request, Response $response): Response
    {
        $usuario = $_SESSION['usuario'] ?? [];
        if (($usuario['rol'] ?? '') !== 'estudiante') {
            return $response->withStatus(302)->withHeader('Location', '/');
        }

        $idEstudiante = (int) $usuario['id_referencia'];
        $params = $request->getQueryParams();
        $scope = $params['scope'] ?? 'general';
        $idCurso = isset($params['id_curso']) ? (int) $params['id_curso'] : null;

        $datos = $this->portal->calificaciones($idEstudiante);
        $perfil = $this->portal->perfil($idEstudiante);

        $nombreEstudiante = trim(($perfil['nombre'] ?? '') . ' ' . ($perfil['apellido'] ?? ''));
        $emailEstudiante = $perfil['email'] ?? '';

        $lineas = [];
        $lineas[] = [20, 'SOLFACADEMY'];
        $lineas[] = [14, 'BOLETIN DE CALIFICACIONES'];
        $lineas[] = [10, str_repeat('-', 70)];
        $lineas[] = [12, 'Estudiante: ' . $nombreEstudiante];
        $lineas[] = [12, 'Correo:     ' . $emailEstudiante];
        $lineas[] = [12, 'Fecha:      ' . date('d/m/Y')];
        $lineas[] = [10, str_repeat('-', 70)];
        $lineas[] = [0, ''];

        if ($scope === 'curso' && $idCurso !== null) {
            // Boletin por curso
            $grupoObj = null;
            foreach (($datos['grupos'] ?? []) as $g) {
                if ((int) ($g['id_curso'] ?? 0) === $idCurso) {
                    $grupoObj = $g;
                    break;
                }
            }
            $cursoNombre = $grupoObj['curso'] ?? 'Curso';
            $tutor = $grupoObj['instructor'] ?? '—';
            $promedioCurso = 0.0;
            $totalNotas = 0;
            $suma = 0.0;
            $evaluaciones = [];
            foreach (($datos['detalle'] ?? []) as $e) {
                if ((int) ($e['id_curso'] ?? 0) === $idCurso) {
                    $evaluaciones[] = $e;
                    if ($e['calificacion'] !== null) {
                        $nota = (float) $e['calificacion'];
                        $suma += $nota;
                        $totalNotas++;
                    }
                }
            }
            if ($totalNotas > 0) {
                $promedioCurso = round($suma / $totalNotas, 2);
            }

            $lineas[] = [14, 'BOLETIN POR CURSO'];
            $lineas[] = [12, 'Curso: ' . $cursoNombre];
            $lineas[] = [12, 'Tutor/Instructor: ' . $tutor];
            $lineas[] = [0, ''];
            $lineas[] = [12, 'EVALUACIONES'];
            $lineas[] = [10, str_repeat('-', 70)];
            if (count($evaluaciones) === 0) {
                $lineas[] = [11, 'Sin evaluaciones registradas.'];
            } else {
                foreach ($evaluaciones as $ev) {
                    $notaTxt = $ev['calificacion'] !== null ? number_format((float) $ev['calificacion'], 1) : 'Sin nota';
                    $fechaEv = $ev['fecha_evaluacion'] ? date('d/m/Y', strtotime((string) $ev['fecha_evaluacion'])) : '—';
                    $lineas[] = [11, '- ' . ($ev['tipo'] ?? 'Evaluacion') . ' | ' . $fechaEv . ' | Calificacion: ' . $notaTxt];
                    if (!empty($ev['observaciones_profesor'])) {
                        $lineas[] = [10, '  Obs: ' . (string) $ev['observaciones_profesor']];
                    }
                }
            }
            $lineas[] = [0, ''];
            $lineas[] = [10, str_repeat('-', 70)];
            $lineas[] = [12, 'Promedio del curso: ' . number_format($promedioCurso, 2) . ' / 10'];
            if ($promedioCurso >= 5.0 && $totalNotas > 0) {
                $lineas[] = [12, 'Estado: APROBADO'];
            } elseif ($totalNotas > 0) {
                $lineas[] = [12, 'Estado: REPROBADO'];
            } else {
                $lineas[] = [12, 'Estado: EN PROGRESO'];
            }
        } else {
            // Boletin general
            $resumen = $datos['resumen'] ?? [];
            $lineas[] = [12, 'RESUMEN DEL SEMESTRE'];
            $lineas[] = [10, str_repeat('-', 70)];
            $lineas[] = [11, 'Promedio del semestre: ' . number_format((float) ($resumen['promedio_global'] ?? 0), 2) . ' / 10'];
            $lineas[] = [11, 'Cursos evaluados: ' . (int) ($resumen['cursos_evaluados'] ?? 0)];
            $lineas[] = [11, 'Evaluaciones totales: ' . (int) ($resumen['evaluaciones_total'] ?? 0)];
            $lineas[] = [11, 'Cursos aprobados: ' . (int) ($resumen['cursos_aprobados'] ?? 0)];
            $lineas[] = [11, 'Evaluaciones pendientes: ' . (int) ($resumen['evaluaciones_pendientes'] ?? 0)];
            $lineas[] = [0, ''];
            $lineas[] = [12, 'DETALLE POR CURSO'];
            $lineas[] = [10, str_repeat('-', 70)];
            foreach (($datos['grupos'] ?? []) as $g) {
                $prom = (float) ($g['promedio'] ?? 0);
                $lineas[] = [12, 'Curso: ' . ($g['curso'] ?? '') . ' | Tutor: ' . ($g['instructor'] ?? '')];
                $lineas[] = [11, '  Promedio del curso: ' . ($prom > 0 ? number_format($prom, 2) : '—') . ' / 10 | Evaluaciones: ' . (int) ($g['total_evaluaciones'] ?? 0)];
                // Detalle de evaluaciones de este curso
                foreach (($datos['detalle'] ?? []) as $e) {
                    if ((int) ($e['id_inscripcion'] ?? 0) === (int) ($g['id_inscripcion'] ?? 0)) {
                        $notaTxt = $e['calificacion'] !== null ? number_format((float) $e['calificacion'], 1) : 'Sin nota';
                        $fechaEv = $e['fecha_evaluacion'] ? date('d/m/Y', strtotime((string) $e['fecha_evaluacion'])) : '—';
                        $lineas[] = [10, '    - ' . ($e['tipo'] ?? 'Evaluacion') . ' | ' . $fechaEv . ' | ' . $notaTxt . ($e['observaciones_profesor'] ? ' | Obs: ' . $e['observaciones_profesor'] : '')];
                    }
                }
                $lineas[] = [0, ''];
            }
        }

        $lineas[] = [0, ''];
        $lineas[] = [10, str_repeat('-', 70)];
        $lineas[] = [10, 'Emitido por SolfAcademy - Portal del Estudiante'];

        $pdf = $this->buildBoletinPdf($lineas);
        $filename = $scope === 'curso' && $idCurso ? 'boletin-curso-' . $idCurso . '-' . date('Ymd') . '.pdf' : 'boletin-general-' . date('Ymd') . '.pdf';

        $response->getBody()->write($pdf);
        return $response
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    private function buildBoletinPdf(array $lineas): string
    {
        $stream = "BT\n";
        $y = 790;
        foreach ($lineas as $linea) {
            if (!isset($linea[0]) || !isset($linea[1])) {
                continue;
            }
            $tam = (int) $linea[0];
            $texto = (string) $linea[1];
            if ($tam === 0 || $texto === '') {
                $y -= 8;
                continue;
            }
            $textoEsc = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $texto);
            $textoEsc = iconv('UTF-8', 'ISO-8859-1//IGNORE', $textoEsc) ?: $textoEsc;
            $stream .= sprintf("/F1 %d Tf 1 0 0 1 40 %d Tm (%s) Tj\n", $tam, $y, $textoEsc);
            $y -= (int) round($tam * 2.2) + 8;
            if ($y < 40) {
                break;
            }
        }
        $stream .= "ET\n";

        $objetos = [
            1 => "<< /Type /Catalog /Pages 2 0 R >>",
            2 => "<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
            3 => "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>",
            4 => "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "endstream",
            5 => "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objetos as $num => $cuerpo) {
            $offsets[$num] = strlen($pdf);
            $pdf .= "$num 0 obj\n$cuerpo\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 6\n0000000000 65535 f \n";
        for ($i = 1; $i <= 5; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
        return $pdf;
    }
}
