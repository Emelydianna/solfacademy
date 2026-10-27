<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$db = require __DIR__ . '/../config/db.php';
try {
    $pdo = new PDO("mysql:host={$db['host']};dbname={$db['dbname']};charset={$db['charset']}", $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    \App\Application\Admin\MigracionGestion::aplicar($pdo);
    echo "Migración de gestión administrativa aplicada. No se han eliminado ni actualizado registros existentes.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Error de migración: ' . $e->getMessage() . "\nPuede ejecutarse nuevamente para completar los pasos pendientes.\n");
    exit(1);
}
