<?php

declare(strict_types=1);

namespace App\Application\Perfil;

use DomainException;
use PDO;
use Psr\Http\Message\UploadedFileInterface;

final class InstructorFotoService
{
    private PDO $pdo;
    private FotoInstructorStorage $fotos;

    public function __construct(PDO $pdo, ?FotoInstructorStorage $fotos = null)
    {
        $this->pdo = $pdo;
        $this->fotos = $fotos ?? new FotoInstructorStorage();
    }

    public function guardar(int $id, UploadedFileInterface $archivo): void
    {
        $nueva = null;
        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare('SELECT foto, activo FROM instructor WHERE id_instructor = :id FOR UPDATE');
            $stmt->execute(['id' => $id]);
            $instructor = $stmt->fetch();
            if (!$instructor || !$instructor['activo']) {
                throw new DomainException('El instructor no está activo.');
            }
            $nueva = $this->fotos->guardar($archivo, $id);
            $this->pdo->prepare('UPDATE instructor SET foto = :foto WHERE id_instructor = :id')->execute(['foto' => $nueva, 'id' => $id]);
            $this->pdo->commit();
            $this->fotos->borrar($instructor['foto'], $id);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->fotos->borrar($nueva, $id);
            throw $e;
        }
    }
}
