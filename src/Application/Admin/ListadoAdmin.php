<?php

declare(strict_types=1);

namespace App\Application\Admin;

use DateTimeImmutable;
use PDO;

final class ListadoAdmin
{
    public static function pagina(PDO $pdo, string $select, string $from, string $where, array $params, string $orden, array $filtros): array
    {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM $from$where");
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();
        $paginas = max(1, (int) ceil($total / 10));
        $pagina = min($paginas, max(1, (int) GestionAcademicaService::texto($filtros, 'pagina', '1')));
        $offset = ($pagina - 1) * 10;
        $stmt = $pdo->prepare("SELECT $select FROM $from$where ORDER BY $orden LIMIT 10 OFFSET $offset");
        $stmt->execute($params);
        return ['filas' => $stmt->fetchAll(), 'total' => $total, 'pagina' => $pagina, 'paginas' => $paginas,
            'desde' => $total ? $offset + 1 : 0, 'hasta' => min($offset + 10, $total)];
    }

    public static function fecha(string $fecha): bool
    {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
        return $d !== false && $d->format('Y-m-d') === $fecha;
    }
}
