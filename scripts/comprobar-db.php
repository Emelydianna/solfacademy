<?php

/**
 * Comprueba la conexión a la base de datos de SolfAcademy.
 *
 * Uso (desde la raíz del proyecto):
 *     php scripts/comprobar-db.php
 *
 * Lee las credenciales de config/db.php e intenta conectarse.
 */

declare(strict_types=1);

$config = require __DIR__ . '/../config/db.php';

$dsn = sprintf(
    'mysql:host=%s;dbname=%s;charset=%s',
    $config['host'],
    $config['dbname'],
    $config['charset']
);

echo "Comprobando conexión a la base de datos...\n";
echo sprintf("  Host:   %s\n", $config['host']);
echo sprintf("  BD:     %s\n", $config['dbname']);
echo sprintf("  Usuario: %s\n", $config['user']);

try {
    $pdo = new PDO($dsn, $config['user'], $config['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $tablas = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

    echo "\nConexión correcta ✓\n";
    echo sprintf("Tablas encontradas: %d\n", count($tablas));

    if (count($tablas) === 0) {
        echo "Aviso: la base de datos está vacía.\n";
        echo "Importa el esquema con: mysql -u valet solfacademy < database/solfacademy.sql\n";
        exit(0);
    }

    // Conteo rápido de registros principales
    foreach (['categoria', 'instrumento', 'instructor', 'curso', 'usuario', 'inscripcion', 'pago'] as $tabla) {
        $total = $pdo->query("SELECT COUNT(*) FROM {$tabla}")->fetchColumn();
        echo sprintf("  %-12s %s registros\n", $tabla . ':', $total);
    }

    // Vistas y procedimientos almacenados del esquema
    $vistas = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.views WHERE table_schema = DATABASE()"
    )->fetchColumn();
    $procedimientos = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.routines
         WHERE routine_schema = DATABASE() AND routine_type = 'PROCEDURE'"
    )->fetchColumn();
    echo sprintf("Vistas: %d · Procedimientos almacenados: %d\n", $vistas, $procedimientos);

    exit(0);
} catch (PDOException $e) {
    fwrite(STDERR, "\nError de conexión: " . $e->getMessage() . "\n\n");
    fwrite(STDERR, "Revisa los datos de config/db.php y asegúrate de que el servidor\n");
    fwrite(STDERR, "MySQL/MariaDB está activo. Si aún no existe la base de datos:\n");
    fwrite(STDERR, "    mysql -u valet solfacademy < database/solfacademy.sql\n");
    exit(1);
}
