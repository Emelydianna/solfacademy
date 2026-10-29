<?php
/**
 * Marca de la academia (parcial compartido por los 4 layouts, login,
 * registro y la home): muestra el logo subido desde /admin/configuracion
 * o, si no hay, el icono musical de fábrica.
 *
 * Uso:
 *   $claseIcono = 'portal-brand-icon';   // o 'brand-icon' / 'brand-icon auth-brand'
 *   require __DIR__ . '/../partials/marca.php';
 *
 * Depende de `$config`, que inyecta ViewDataMiddleware en todas las vistas.
 */
$claseIcono = $claseIcono ?? 'portal-brand-icon';
$logoMarca = $config['logo'] ?? null;
$nombreMarca = trim((string) ($config['nombre'] ?? '')) !== '' ? (string) $config['nombre'] : 'SolfAcademy';
?>
<?php if (!empty($logoMarca)): ?>
    <span class="<?= htmlspecialchars($claseIcono) ?>" style="background:#fff;padding:3px;overflow:hidden">
        <img src="<?= htmlspecialchars($logoMarca) ?>" alt="<?= htmlspecialchars($nombreMarca) ?>"
             style="width:100%;height:100%;object-fit:contain">
    </span>
<?php else: ?>
    <span class="<?= htmlspecialchars($claseIcono) ?>"><i class="bi bi-music-note-beamed"></i></span>
<?php endif; ?>
