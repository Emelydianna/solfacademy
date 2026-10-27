<?php

declare(strict_types=1);

namespace App\Application\Instructor;

use DomainException;
use PDO;

/** Persistencia compartida por admin y perfil propio, sin modificar sesiones. */
final class InstructorContactoService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function cuentas(int $id): array
    {
        $stmt = $this->pdo->prepare("SELECT id_usuario, activo FROM usuario WHERE id_instructor = :id AND rol = 'instructor' ORDER BY id_usuario FOR UPDATE");
        $stmt->execute(['id' => $id]);
        $cuentas = $stmt->fetchAll();
        if (count($cuentas) > 1) {
            throw new DomainException('Hay varias cuentas asociadas al instructor. Deben revisarse antes de editar sus datos.');
        }
        return $cuentas;
    }

    public function validarCorreo(string $email, int $id, bool $requiereCuenta): void
    {
        if ($email === '' && !$requiereCuenta) {
            return;
        }
        if (mb_strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('Indica un email válido. Es obligatorio cuando hay cuenta de acceso.', 1);
        }
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM instructor WHERE email = :email AND id_instructor <> :id');
        $stmt->execute(['email' => $email, 'id' => $id]);
        $duplicado = (int) $stmt->fetchColumn() > 0;
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM usuario WHERE email_usuario = :email AND (id_instructor IS NULL OR id_instructor <> :id OR rol <> 'instructor')");
        $stmt->execute(['email' => $email, 'id' => $id]);
        if ($duplicado || (int) $stmt->fetchColumn()) {
            throw new DomainException('Ya existe una ficha o cuenta con este correo electrónico.', 1);
        }
    }

    public function sincronizarCorreo(int $id, string $email): void
    {
        $this->pdo->prepare("UPDATE usuario SET email_usuario = :email WHERE id_instructor = :id AND rol = 'instructor'")->execute(['email' => $email, 'id' => $id]);
    }

    public function actualizar(int $id, array $datos): void
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT activo FROM instructor WHERE id_instructor = :id FOR UPDATE');
            $stmt->execute(['id' => $id]);
            if ((int) $stmt->fetchColumn() !== 1) {
                throw new DomainException('El instructor no está activo.');
            }
            $cuentas = $this->cuentas($id);
            $email = trim((string) ($datos['email'] ?? ''));
            $this->validarCorreo($email, $id, (bool) $cuentas);
            $stmt = $this->pdo->prepare('UPDATE instructor SET nombre = :nombre, apellido = :apellido, email = :email, telefono = :telefono WHERE id_instructor = :id');
            $stmt->execute(['id' => $id, 'nombre' => trim((string) $datos['nombre']), 'apellido' => trim((string) $datos['apellido']),
                'email' => $email ?: null, 'telefono' => trim((string) ($datos['telefono'] ?? '')) ?: null]);
            $this->sincronizarCorreo($id, $email);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
