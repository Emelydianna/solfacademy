<?php

declare(strict_types=1);

namespace App\Application\Security;

/**
 * Token CSRF sincronizado con la sesión (patrón "synchronizer token").
 *
 * Uso en las plantillas:
 *
 *     <form method="post" action="/login">
 *         <?= \App\Application\Security\Csrf::campo() ?>
 *
 * Y validación automática en CsrfMiddleware para todo método no seguro.
 */
final class Csrf
{
    private const CLAVE = 'csrf_token';

    /**
     * Token de la sesión actual; se genera en la primera petición que lo
     * pida y se mantiene mientras viva la sesión.
     */
    public static function token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $token = $_SESSION[self::CLAVE] ?? null;

        if (!is_string($token) || strlen($token) < 32) {
            $token = bin2hex(random_bytes(32));
            $_SESSION[self::CLAVE] = $token;
        }

        return $token;
    }

    /** Campo oculto que hay que añadir a cada <form method="post">. */
    public static function campo(): string
    {
        return '<input type="hidden" name="_csrf" value="'
            . htmlspecialchars(self::token(), ENT_QUOTES)
            . '">';
    }

    /** Comparación en tiempo constante contra el token de la sesión. */
    public static function esValido(mixed $token): bool
    {
        return is_string($token) && $token !== '' && hash_equals(self::token(), $token);
    }

    /** Borra el token (se usa al cerrar sesión). */
    public static function olvidar(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            unset($_SESSION[self::CLAVE]);
        }
    }
}
