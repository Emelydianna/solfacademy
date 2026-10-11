<?php

/**
 * Configuración de la base de datos de SolfAcademy.
 *
 * Este archivo es la única fuente de credenciales de la conexión. Si vas a
 * desplegar en producción, edita estos valores (o sustitúyelos por variables
 * de entorno en app/settings.php según tu entorno).
 *
 * Los valores por defecto son los de desarrollo local:
 * MySQL/MariaDB en 127.0.0.1, usuario "valet" sin contraseña y base de
 * datos "solfacademy" (ver README.md para crearla e importar el esquema).
 */
return [
    'host'    => '127.0.0.1',
    'dbname'  => 'solfacademy',
    'user'    => 'valet',
    'pass'    => '',
    'charset' => 'utf8mb4',
];
