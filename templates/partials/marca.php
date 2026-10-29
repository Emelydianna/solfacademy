<?php
/**
 * Marca de la academia (parcial compartido por los 4 layouts, login,
 * registro, la home y la previsualización de Configuración): muestra el
 * logo subido desde /admin/configuracion con su proporción real o, si no
 * hay, el icono musical de fábrica.
 *
 * Uso:
 *   $modo = 'portal';   // sidebar: logo con nombre y lema debajo
 *   $modo = 'web';      // navbar y pie de la web pública
 *   $modo = 'auth';     // tarjeta de login/registro (centrada)
 *   $modo = 'preview';  // vista previa en /admin/configuracion
 *   require __DIR__ . '/../partials/marca.php';
 *
 * Depende de `$config`, que inyecta ViewDataMiddleware en todas las vistas.
 */
$modo = $modo ?? 'portal';
$logoMarca = $config['logo'] ?? null;
$nombreMarca = trim((string) ($config['nombre'] ?? '')) !== '' ? (string) $config['nombre'] : 'SolfAcademy';

if (empty($logoMarca)) {
    $iconos = [
        'portal'  => 'portal-brand-icon',
        'web'     => 'brand-icon',
        'auth'    => 'brand-icon auth-brand mx-auto mb-3',
        'preview' => 'admin-logo-preview-icono',
    ];
    $clase = $iconos[$modo] ?? 'portal-brand-icon';

    echo '<span class="' . htmlspecialchars($clase, ENT_QUOTES, 'UTF-8') . '">'
        . '<i class="bi bi-music-note-beamed"></i></span>';

    return;
}

$logos = ['portal' => 'marca-logo', 'web' => 'brand-logo', 'auth' => 'auth-brand-logo', 'preview' => 'admin-logo-preview'];
$claseLogo = $logos[$modo] ?? 'marca-logo';

echo '<img class="' . htmlspecialchars($claseLogo, ENT_QUOTES, 'UTF-8') . '" src="'
    . htmlspecialchars($logoMarca, ENT_QUOTES, 'UTF-8') . '" alt="'
    . htmlspecialchars($nombreMarca, ENT_QUOTES, 'UTF-8') . '">';
