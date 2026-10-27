<?php

declare(strict_types=1);

namespace App\Application\Auth;

use PDO;

class CuentaService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function vigente(array $sesion): bool
    {
        if ((int) ($sesion['id'] ?? 0) < 1) {
            return false;
        }
        $stmt = $this->pdo->prepare('SELECT u.rol, u.activo, u.version_sesion, u.id_estudiante, u.id_instructor, e.estado, i.activo AS instructor_activo
            FROM usuario u LEFT JOIN estudiante e ON e.id_estudiante = u.id_estudiante
            LEFT JOIN instructor i ON i.id_instructor = u.id_instructor WHERE u.id_usuario = :id');
        $stmt->execute(['id' => $sesion['id']]);
        $cuenta = $stmt->fetch();
        if (!$cuenta || !(int) $cuenta['activo'] || $cuenta['rol'] !== ($sesion['rol'] ?? '')
            || (int) $cuenta['version_sesion'] !== (int) ($sesion['version_sesion'] ?? 0)) {
            return false;
        }
        if ($cuenta['rol'] === 'estudiante') {
            return $cuenta['estado'] === 'Activo' && (int) $cuenta['id_estudiante'] === (int) ($sesion['id_referencia'] ?? 0);
        }
        return $cuenta['rol'] !== 'instructor' || ((int) $cuenta['instructor_activo'] === 1
            && (int) $cuenta['id_instructor'] === (int) ($sesion['id_referencia'] ?? 0));
    }
}
