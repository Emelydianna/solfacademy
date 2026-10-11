<?php

use Slim\Views\PhpRenderer;

/*$containerBuilder = new ContainerBuilder();

$containerBuilder->addDefinitions([
    // 1. Configuración de PDO (Base de Datos)
    PDO::class => function () {
        $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
        $db   = $_ENV['DB_NAME'] ?? 'mi_base_datos';
        $user = $_ENV['DB_USER'] ?? 'root';
        $pass = $_ENV['DB_PASS'] ?? '';
        $charset = 'utf8mb4';

        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        return new PDO($dsn, $user, $pass, $options);
    },

   
]);*/
return [
    PhpRenderer::class => function () {
        return new PhpRenderer(__DIR__ . '/../templates');
    },
];


