<?php

declare(strict_types=1);

namespace App\Application\Academico;

use App\Application\Admin\GestionAcademicaService;
use App\Application\Admin\ListadoAdmin;
use DomainException;
use PDO;
use PDOException;

final class RequisitosService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function listar(array $f): array
    {
        $q = mb_substr(GestionAcademicaService::texto($f, 'q'), 0, 150);
        $configurado = GestionAcademicaService::texto($f, 'configurado');
        $where = [];
        $params = [];
        if ($q !== '') {
            $where[] = '(c.nombre LIKE :q OR cat.nombre LIKE :q2)';
            $params['q'] = '%' . $q . '%';
            $params['q2'] = '%' . $q . '%';
        }
        if (in_array($configurado, ['1', '0'], true)) {
            $where[] = $configurado === '1' ? 'r.id_requisito IS NOT NULL' : 'r.id_requisito IS NULL';
        } else {
            $configurado = '';
        }
        $lista = ListadoAdmin::pagina($this->pdo,
            'c.*, cat.nombre AS categoria, ins.nombre AS instrumento, r.id_requisito, r.nivel_minimo,
                r.edad_minima, r.edad_maxima, r.conocimientos_previos, r.necesita_instrumento,
                r.puede_alquilar, r.descripcion_general',
            'curso c JOIN instrumento ins ON ins.id_instrumento = c.id_instrumento
                JOIN categoria cat ON cat.id_categoria = ins.id_categoria
                LEFT JOIN (SELECT * FROM requisito_curso) r ON r.id_curso = c.id_curso',
            $where ? ' WHERE ' . implode(' AND ', $where) : '', $params, 'c.nombre, c.id_curso', $f);
        return $lista + ['filtros' => ['q' => $q, 'configurado' => $configurado]];
    }

    public function buscar(int $idCurso): ?array
    {
        $stmt = $this->pdo->prepare('SELECT c.id_curso, c.nombre, c.nivel, c.activo, c.disponible,
            cat.nombre AS categoria, ins.nombre AS instrumento, r.*
            FROM curso c JOIN instrumento ins ON ins.id_instrumento = c.id_instrumento
            JOIN categoria cat ON cat.id_categoria = ins.id_categoria
            LEFT JOIN requisito_curso r ON r.id_curso = c.id_curso
            WHERE c.id_curso = :id');
        $stmt->execute(['id' => $idCurso]);
        $curso = $stmt->fetch();
        if (!$curso) {
            return null;
        }
        $curso['existe'] = $curso['id_requisito'] !== null;
        return $curso;
    }

    public function guardar(array $datos, int $idCurso): array
    {
        $v = [];
        $errores = [];
        foreach (['nivel_minimo', 'edad_minima', 'edad_maxima', 'conocimientos_previos', 'descripcion_general'] as $campo) {
            $v[$campo] = GestionAcademicaService::texto($datos, $campo);
        }
        foreach (['necesita_instrumento', 'puede_alquilar'] as $campo) {
            $v[$campo] = isset($datos[$campo]) && $datos[$campo] === '1' ? '1' : '0';
        }
        if (mb_strlen($v['nivel_minimo']) > 100 || mb_strlen($v['conocimientos_previos']) > 255 || mb_strlen($v['descripcion_general']) > 16000) {
            $errores['general'] = 'Revisa las longitudes: nivel máximo 100, conocimientos 255 y descripción 16000 caracteres.';
        }
        foreach (['edad_minima' => 'Edad mínima', 'edad_maxima' => 'Edad máxima'] as $campo => $label) {
            if (!preg_match('/^(?:1[0-9]|[1-9][0-9]?|25[0-5])$/D', $v[$campo])) {
                $errores[$campo] = "$label debe estar entre 1 y 255.";
            }
        }
        if (!$errores && (int) $v['edad_minima'] > (int) $v['edad_maxima']) {
            $errores['edad_maxima'] = 'La edad máxima no puede ser menor que la mínima.';
        }
        $r = ['valores' => $v, 'errores' => $errores, 'id_curso' => $idCurso];
        if ($errores) {
            return $r;
        }
        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare('SELECT id_curso FROM curso WHERE id_curso = :id FOR UPDATE');
            $stmt->execute(['id' => $idCurso]);
            if (!$stmt->fetchColumn()) {
                throw new DomainException('El curso ya no existe.');
            }
            $campos = [
                'nivel_minimo' => $v['nivel_minimo'] ?: null,
                'edad_minima' => (int) $v['edad_minima'],
                'edad_maxima' => (int) $v['edad_maxima'],
                'conocimientos_previos' => $v['conocimientos_previos'] ?: null,
                'necesita_instrumento' => (int) $v['necesita_instrumento'],
                'puede_alquilar' => (int) $v['puede_alquilar'],
                'descripcion_general' => $v['descripcion_general'] ?: null,
            ];
            $actual = $this->buscar($idCurso);
            if ($actual['existe']) {
                $parametros = $campos;
                $parametros['id_set'] = $idCurso;
                $sql = 'UPDATE requisito_curso SET ' . implode(', ', array_map(static fn ($c): string => "$c = :$c", array_keys($campos))) . ' WHERE id_curso = :id_set';
            } else {
                $parametros = $campos + ['id_curso' => $idCurso];
                $sql = 'INSERT INTO requisito_curso (id_curso, ' . implode(', ', array_keys($campos)) . ') VALUES (:id_curso, :' . implode(', :', array_keys($campos)) . ')';
            }
            $this->pdo->prepare($sql)->execute($parametros);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($e instanceof DomainException) {
                $r['errores']['general'] = $e->getMessage();
            } elseif ($e instanceof PDOException && (string) $e->getCode() === '40001') {
                $r['errores']['general'] = 'Hubo un cambio simultáneo. Recarga e inténtalo de nuevo.';
            } else {
                throw $e;
            }
        }
        return $r;
    }
}
