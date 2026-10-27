<?php

require __DIR__ . '/../vendor/autoload.php';

// Los portales usan sesión nativa. Abrirla antes de que PHPUnit imprima
// su cabecera evita "headers already sent" en las pruebas HTTP.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
