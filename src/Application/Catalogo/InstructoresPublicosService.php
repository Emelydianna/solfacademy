<?php

declare(strict_types=1);

namespace App\Application\Catalogo;

use PDO;

/** No expone usuarios, alumnos, cargas privadas ni valoraciones ficticias. */
final class InstructoresPublicosService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function listar(): array
    {
        $filas = $this->pdo->query('SELECT id_instructor, nombre, apellido, foto, anios_experiencia FROM instructor WHERE activo = 1 ORDER BY apellido, nombre, id_instructor')->fetchAll();
        foreach ($filas as &$fila) {
            $fila['especialidades'] = $this->especialidades((int) $fila['id_instructor']);
        }
        unset($fila);
        return $filas;
    }

    private function especialidades(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT ins.nombre, ii.nivel_dominio, ii.anios_experiencia_i FROM instructor_instrumento ii
            JOIN instrumento ins ON ins.id_instrumento = ii.id_instrumento WHERE ii.id_instructor = :id ORDER BY ins.nombre');
        $stmt->execute(['id' => $id]);
        return $stmt->fetchAll();
    }

    public function detalle(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id_instructor, nombre, apellido, email, foto, anios_experiencia FROM instructor WHERE id_instructor = :id AND activo = 1');
        $stmt->execute(['id' => $id]);
        $instructor = $stmt->fetch();
        if (!$instructor) {
            return null;
        }
        $instructor['especialidades'] = $this->especialidades($id);
        $stmt = $this->pdo->prepare("SELECT DISTINCT c.id_curso, c.nombre, c.nivel, c.precio, ins.nombre AS instrumento
            FROM grupo g JOIN curso c ON c.id_curso = g.id_curso JOIN instrumento ins ON ins.id_instrumento = c.id_instrumento
            WHERE g.id_instructor = :id AND g.estado IN ('activo','planeado') AND c.activo = 1 AND c.disponible = 1 ORDER BY c.nombre, c.id_curso");
        $stmt->execute(['id' => $id]);
        $instructor['cursos'] = $stmt->fetchAll();
        return $instructor;
    }

    public function foto(int $id): ?string
    {
        $stmt = $this->pdo->prepare('SELECT foto FROM instructor WHERE id_instructor = :id AND activo = 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetchColumn() ?: null;
    }
}
