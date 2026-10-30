<?php

declare(strict_types=1);

namespace App\Controllers\Instructor;

use App\Application\Instructor\InstructorService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

/**
 * Portal del instructor — calificaciones.
 *
 * IMPORTANTE: en la base de datos `evaluacion` guarda UNA FILA POR
 * INSCRIPCIÓN, no una por grupo. Para referirse a "la evaluación X de
 * este grupo" se usa el par (tipo, fecha_evaluacion), que es lo que
 * comparten todas las matrículas del mismo grupo.
 *
 * Ese par viaja codificado como `base64(tipo)|fecha` en el parámetro
 * `evaluacion` del formulario y del query string.
 */
class CalificacionesController
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

        $params = $request->getQueryParams();
        $idGrupo = isset($params['grupo']) ? (int) $params['grupo'] : 0;
        [$tipo, $fecha] = $this->parsearClave($params['evaluacion'] ?? '');

        $this->view->setLayout('instructor/layout.php');
        $data = $this->service->calificacionesPorGrupo((int) $usuario['id_referencia'], $idGrupo, $tipo, $fecha);

        $flash = $_SESSION['calificaciones_flash'] ?? null;
        unset($_SESSION['calificaciones_flash']);

        return $this->view->render($response, 'instructor/calificaciones/index.php', [
            'tituloPagina' => 'Ingresar Calificaciones',
            'paginaInstructor' => 'calificaciones',
            'grupos' => $data['grupos'],
            'grupo' => $data['grupo'],
            'alumnos' => $data['alumnos'],
            'evaluaciones' => $data['evaluaciones'],
            'idGrupo' => $data['idGrupo'],
            'claveEvaluacion' => $this->claveEvaluacion((string) $data['tipo'], $data['fecha']),
            'flash' => $flash,
        ]);
    }

    public function guardar(Request $request, Response $response): Response
    {
        $usuario = $_SESSION['usuario'] ?? [];

        $body = (array) $request->getParsedBody();
        $idGrupo = (int) ($body['id_grupo'] ?? 0);
        [$tipo, $fecha] = $this->parsearClave($body['evaluacion'] ?? '');

        $ok = $this->service->guardarCalificaciones(
            (int) $usuario['id_referencia'],
            $idGrupo,
            $tipo,
            $fecha,
            (array) ($body['calificacion'] ?? []),
            (array) ($body['observaciones'] ?? [])
        );

        $_SESSION['calificaciones_flash'] = ['tipo' => $ok ? 'exito' : 'error', 'mensaje' => $ok
            ? 'Calificaciones guardadas correctamente.'
            : 'No se pudieron guardar las calificaciones. Revisa que la evaluación exista en el grupo.'];

        return $response->withStatus(302)->withHeader(
            'Location',
            '/instructor/calificaciones?grupo=' . $idGrupo
            . '&evaluacion=' . urlencode($this->claveEvaluacion($tipo, $fecha))
        );
    }

    /**
     * Crea una evaluación para TODAS las inscripciones activas del grupo.
     */
    public function crear(Request $request, Response $response): Response
    {
        $usuario = $_SESSION['usuario'] ?? [];

        $body = (array) $request->getParsedBody();
        $idGrupo = (int) ($body['id_grupo'] ?? 0);

        $resultado = $this->service->crearEvaluacion((int) $usuario['id_referencia'], $idGrupo, [
            'tipo'             => (string) ($body['tipo'] ?? ''),
            'fecha_evaluacion' => (string) ($body['fecha_evaluacion'] ?? ''),
            'descripcion'      => (string) ($body['descripcion'] ?? ''),
        ]);

        $_SESSION['calificaciones_flash'] = ['tipo' => $resultado['ok'] ? 'exito' : 'error', 'mensaje' => $resultado['msg']];

        return $response->withStatus(302)->withHeader('Location', '/instructor/calificaciones?grupo=' . $idGrupo);
    }

    private function claveEvaluacion(string $tipo, ?string $fecha): string
    {
        if ($tipo === '') {
            return '';
        }

        return base64_encode($tipo) . '|' . ($fecha ?? '');
    }

    /**
     * Invierte claveEvaluacion(). Devuelve ['', null] si el valor no es válido.
     *
     * @return array{0: string, 1: string|null}
     */
    private function parsearClave(mixed $valor): array
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return ['', null];
        }

        $pos = strpos($valor, '|');
        if ($pos === false) {
            return ['', null];
        }

        $tipo = base64_decode(substr($valor, 0, $pos), true);
        if ($tipo === false || $tipo === '') {
            return ['', null];
        }

        $fecha = trim(substr($valor, $pos + 1));

        return [$tipo, $fecha !== '' ? $fecha : null];
    }
}
