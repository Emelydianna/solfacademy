<?php

declare(strict_types=1);

namespace App\Application\Catalogo;

use App\Application\Admin\GestionAcademicaService;
use PDO;

/** Catálogo público basado exclusivamente en las tablas existentes. */
final class CatalogoService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function categorias(): array
    {
        $filas = $this->pdo->query('SELECT id_categoria, nombre, descripcion FROM categoria ORDER BY nombre')->fetchAll();
        foreach ($filas as &$fila) {
            $fila['icono'] = 'bi-music-note-beamed';
        }
        unset($fila);
        return $filas;
    }

    /** Una fila por curso, aunque haya varios grupos, instructores y horarios. */
    private function cursos(): array
    {
        $filas = $this->pdo->query("SELECT c.*, ins.nombre AS instrumento, cat.nombre AS categoria,
            (SELECT COUNT(*) FROM inscripcion m JOIN grupo gm ON gm.id_grupo = m.id_grupo
              WHERE gm.id_curso = c.id_curso AND m.estado IN ('Activa', 'Pendiente')) AS inscritos
            FROM curso c JOIN instrumento ins ON ins.id_instrumento = c.id_instrumento
            JOIN categoria cat ON cat.id_categoria = ins.id_categoria
            WHERE c.activo = 1 AND c.disponible = 1 ORDER BY c.nombre, c.id_curso")->fetchAll();
        $cursos = [];
        foreach ($filas as $fila) {
            $fila['icono'] = 'bi-music-note-beamed';
            $fila['instructores'] = [];
            $fila['franjas'] = [];
            $fila['grupos'] = [];
            $cursos[(int) $fila['id_curso']] = $fila;
        }
        $grupos = $this->pdo->query("SELECT g.id_grupo, g.id_curso, g.estado, g.fecha_inicio, g.fecha_fin,
                CONCAT(i.nombre, ' ', i.apellido) AS instructor, a.nombre AS aula,
                h.dia_semana, h.hora_inicio, h.hora_fin
            FROM grupo g JOIN curso c ON c.id_curso = g.id_curso
            JOIN instructor i ON i.id_instructor = g.id_instructor
            JOIN aula a ON a.id_aula = g.id_aula
            LEFT JOIN horario h ON h.id_grupo = g.id_grupo
            WHERE c.activo = 1 AND c.disponible = 1 AND g.estado IN ('activo', 'planeado')
            ORDER BY g.fecha_inicio, g.id_grupo,
                FIELD(h.dia_semana, 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'), h.hora_inicio")->fetchAll();
        foreach ($grupos as $grupo) {
            $id = (int) $grupo['id_curso'];
            $idGrupo = (int) $grupo['id_grupo'];
            if (!isset($cursos[$id])) {
                continue;
            }
            $curso = &$cursos[$id];
            $curso['instructores'][$grupo['instructor']] = $grupo['instructor'];
            if (!isset($curso['grupos'][$idGrupo])) {
                $curso['grupos'][$idGrupo] = $grupo;
                $curso['grupos'][$idGrupo]['horarios'] = [];
            }
            if ($grupo['hora_inicio'] !== null) {
                $hora = (int) substr($grupo['hora_inicio'], 0, 2);
                $franja = $hora < 12 ? 'Mañana' : ($hora < 18 ? 'Tarde' : 'Noche');
                $curso['franjas'][$franja] = $franja;
                $curso['grupos'][$idGrupo]['horarios'][] = $grupo['dia_semana'] . ' · '
                    . substr($grupo['hora_inicio'], 0, 5) . '–' . substr($grupo['hora_fin'], 0, 5);
            }
            unset($curso);
        }
        foreach ($cursos as &$curso) {
            $curso['instructor'] = implode(', ', $curso['instructores']) ?: 'Sin asignar';
            $curso['horario'] = implode(', ', $curso['franjas']) ?: 'Por definir';
        }
        unset($curso);
        return array_values($cursos);
    }

    public function catalogo(array $params): array
    {
        $todos = $this->cursos();
        $seleccion = static function (string $clave) use ($params): array {
            $valores = $params[$clave] ?? [];
            return is_array($valores) ? array_values(array_filter($valores, 'is_string')) : [];
        };
        $filtros = [
            'q' => mb_substr(GestionAcademicaService::texto($params, 'q'), 0, 150),
            'instrumento' => $seleccion('instrumento'), 'nivel' => $seleccion('nivel'),
            'horario' => $seleccion('horario'), 'orden' => GestionAcademicaService::texto($params, 'orden', 'populares'),
        ];
        if (!in_array($filtros['orden'], ['populares', 'precio-asc', 'precio-desc', 'az'], true)) {
            $filtros['orden'] = 'populares';
        }
        $cursos = array_values(array_filter($todos, static function (array $curso) use ($filtros): bool {
            if ($filtros['instrumento'] && !in_array($curso['instrumento'], $filtros['instrumento'], true)) {
                return false;
            }
            if ($filtros['nivel'] && !in_array($curso['nivel'], $filtros['nivel'], true)) {
                return false;
            }
            if ($filtros['horario'] && !array_intersect($curso['franjas'], $filtros['horario'])) {
                return false;
            }
            $texto = self::normalizar($curso['nombre'] . ' ' . $curso['instrumento'] . ' ' . $curso['categoria'] . ' ' . $curso['instructor']);
            return $filtros['q'] === '' || str_contains($texto, self::normalizar($filtros['q']));
        }));
        usort($cursos, static function (array $a, array $b) use ($filtros): int {
            switch ($filtros['orden']) {
                case 'precio-asc': $orden = (float) $a['precio'] <=> (float) $b['precio']; break;
                case 'precio-desc': $orden = (float) $b['precio'] <=> (float) $a['precio']; break;
                case 'az': $orden = strnatcasecmp($a['nombre'], $b['nombre']); break;
                default: $orden = (int) $b['inscritos'] <=> (int) $a['inscritos'];
            }
            return $orden ?: ((int) $a['id_curso'] <=> (int) $b['id_curso']);
        });
        $instrumentos = array_values(array_unique(array_column($todos, 'instrumento')));
        sort($instrumentos, SORT_NATURAL | SORT_FLAG_CASE);
        $horarios = [];
        foreach ($todos as $curso) {
            $horarios = array_merge($horarios, array_values($curso['franjas']));
        }
        return [
            'cursos' => $cursos, 'totalCursos' => count($todos), 'filtros' => $filtros,
            'instrumentos' => $instrumentos, 'niveles' => GestionAcademicaService::NIVELES,
            'horarios' => array_values(array_intersect(['Mañana', 'Tarde', 'Noche'], $horarios)),
        ];
    }

    public function detalle(int $id): ?array
    {
        foreach ($this->cursos() as $curso) {
            if ((int) $curso['id_curso'] === $id) {
                $stmt = $this->pdo->prepare('SELECT * FROM requisito_curso WHERE id_curso = :id');
                $stmt->execute(['id' => $id]);
                $curso['requisito'] = $stmt->fetch() ?: null;
                return $curso;
            }
        }
        return null;
    }

    private static function normalizar(string $texto): string
    {
        return strtr(mb_strtolower($texto), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u']);
    }
}
