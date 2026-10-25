<?php

declare(strict_types=1);

namespace App\Controllers\Instructor;

use App\Application\Instructor\InstructorService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

class GruposController
{
    private PhpRenderer $view;
    private InstructorService $service;

    public function __construct(PhpRenderer $view, InstructorService $service)
    {
        $this->view = $view;
        $this->service = $service;
    }

    public function index(Request $request, Response $response): Response
    {
        $usuario = $_SESSION['usuario'] ?? [];
        if (($usuario['rol'] ?? '') !== 'instructor') {
            return $response->withStatus(302)->withHeader('Location', '/');
        }
        $this->view->setLayout('instructor/layout.php');
        $grupos = $this->service->misGrupos((int) $usuario['id_referencia']);
        return $this->view->render($response, 'instructor/grupos/index.php', [
            'tituloPagina' => 'Mis Grupos',
            'paginaInstructor' => 'grupos',
            'grupos' => $grupos,
        ]);
    }

    public function ver(Request $request, Response $response, array $args): Response
    {
        $usuario = $_SESSION['usuario'] ?? [];
        if (($usuario['rol'] ?? '') !== 'instructor') {
            return $response->withStatus(302)->withHeader('Location', '/');
        }
        $idGrupo = (int) ($args['id'] ?? 0);
        $this->view->setLayout('instructor/layout.php');
        $data = $this->service->estudiantesGrupo((int) $usuario['id_referencia'], $idGrupo);
        return $this->view->render($response, 'instructor/grupos/ver.php', [
            'tituloPagina' => 'Estudiantes del Grupo',
            'paginaInstructor' => 'grupos',
            'grupo' => $data['grupo'],
            'alumnos' => $data['alumnos'],
        ]);
    }

    public function exportar(Request $request, Response $response, array $args): Response
    {
        $usuario = $_SESSION['usuario'] ?? [];
        if (($usuario['rol'] ?? '') !== 'instructor') {
            return $response->withStatus(302)->withHeader('Location', '/');
        }
        $idGrupo = (int) ($args['id'] ?? 0);
        $data = $this->service->estudiantesGrupo((int) $usuario['id_referencia'], $idGrupo);
        $alumnos = $data['alumnos'] ?? [];
        $grupo = $data['grupo'] ?? [];
        $nombreGrupo = $grupo['curso'] ?? 'grupo';
        $filename = 'estudiantes_' . preg_replace('/[^a-zA-Z0-9_-]+/', '_', $nombreGrupo) . '_' . $idGrupo . '.csv';

        $csv = "ALUMNO,EMAIL,TELEFONO,INSCRIPCION,ASISTENCIA,PROMEDIO\n";
        foreach ($alumnos as $a) {
            $alumno = trim(($a['nombre'] ?? '') . ' ' . ($a['apellido'] ?? ''));
            $email = $a['email'] ?? '';
            $telefono = $a['telefono'] ?? '';
            $inscripcion = $a['inscripcion_fecha'] ? date('d/m/Y', strtotime($a['inscripcion_fecha'])) : '';
            $asistencia = ($a['asistencia_pct'] ?? 0) . '%';
            $promedio = number_format((float)($a['promedio'] ?? 0), 1, '.', '');
            $csv .= '"' . str_replace('"', '""', $alumno) . '","' . str_replace('"', '""', $email) . '","' . str_replace('"', '""', $telefono) . '","' . $inscripcion . '","' . $asistencia . '","' . $promedio . '"' . "\n";
        }

        $response->getBody()->write($csv);
        return $response
            ->withHeader('Content-Type', 'text/csv; charset=utf-8')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }
}
