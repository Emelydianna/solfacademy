<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Data\SampleData;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

/**
 * Pantalla 2 del prototipo: Catálogo de Cursos (GET /cursos).
 *
 * Los filtros (instrumento, nivel, horario), la búsqueda y el orden llegan
 * como parámetros GET; cuando haya BD se sustituyen SampleData::cursos()
 * por consultas sobre CURSO + INSTRUMENTO sin tocar la vista.
 */
class CourseCatalogController
{
    private PhpRenderer $view;

    public function __construct(PhpRenderer $view)
    {
        $this->view = $view;
    }

    public function catalog(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();

        $busqueda = trim((string) ($params['q'] ?? ''));
        $instrumentosSeleccionados = (array) ($params['instrumento'] ?? []);
        $nivelesSeleccionados = (array) ($params['nivel'] ?? []);
        $horariosSeleccionados = (array) ($params['horario'] ?? []);
        $orden = (string) ($params['orden'] ?? 'populares');

        $cursos = $this->filterCourses(
            SampleData::cursos(),
            $busqueda,
            $instrumentosSeleccionados,
            $nivelesSeleccionados,
            $horariosSeleccionados
        );

        $cursos = $this->sortCourses($cursos, $orden);

        $this->view->setLayout('web/layout.php');

        return $this->view->render($response, 'web/cursos/catalogo.php', [
            'tituloPagina' => 'Catálogo de Cursos — SolfAcademy',
            'paginaActiva' => 'cursos',
            'cursos' => $cursos,
            'totalCursos' => count(SampleData::cursos()),
            'instrumentos' => SampleData::instrumentos(),
            'niveles' => SampleData::niveles(),
            'horarios' => SampleData::horarios(),
            'filtros' => [
                'q' => $busqueda,
                'instrumento' => $instrumentosSeleccionados,
                'nivel' => $nivelesSeleccionados,
                'horario' => $horariosSeleccionados,
                'orden' => $orden,
            ],
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $cursos
     * @param array<int, string> $instrumentos
     * @param array<int, string> $niveles
     * @param array<int, string> $horarios
     * @return array<int, array<string, mixed>>
     */
    private function filterCourses(
        array $cursos,
        string $busqueda,
        array $instrumentos,
        array $niveles,
        array $horarios
    ): array {
        return array_values(array_filter(
            $cursos,
            static function (array $curso) use ($busqueda, $instrumentos, $niveles, $horarios): bool {
                if ($instrumentos !== [] && !in_array($curso['instrumento'], $instrumentos, true)) {
                    return false;
                }

                if ($niveles !== [] && !in_array($curso['nivel'], $niveles, true)) {
                    return false;
                }

                if ($horarios !== [] && !in_array($curso['horario'], $horarios, true)) {
                    return false;
                }

                if ($busqueda !== '') {
                    $texto = self::normalize(
                        (string) $curso['nombre'] . ' ' . $curso['instrumento'] . ' ' . $curso['instructor']
                    );
                    if (!str_contains($texto, self::normalize($busqueda))) {
                        return false;
                    }
                }

                return true;
            }
        ));
    }

    /**
     * Normaliza un texto a minúsculas y sin acentos para comparar búsquedas
     * ("violin" encuentra "Violín").
     */
    private static function normalize(string $texto): string
    {
        return strtr(mb_strtolower($texto), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $cursos
     * @return array<int, array<string, mixed>>
     */
    private function sortCourses(array $cursos, string $orden): array
    {
        switch ($orden) {
            case 'precio-asc':
                usort($cursos, static fn (array $a, array $b): int => $a['precio'] <=> $b['precio']);
                break;
            case 'precio-desc':
                usort($cursos, static fn (array $a, array $b): int => $b['precio'] <=> $a['precio']);
                break;
            case 'az':
                usort($cursos, static fn (array $a, array $b): int => strcmp((string) $a['nombre'], (string) $b['nombre']));
                break;
            default:
                // 'populares': se mantiene el orden por defecto de los datos
                break;
        }

        return $cursos;
    }
}
