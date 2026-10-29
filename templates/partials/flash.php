<?php
/**
 * Aviso flash (éxito/error) dejado por el controlador en $_SESSION.
 *
 * Uso: $flash = ['tipo' => 'exito'|'error', 'mensaje' => '...'].
 * Se pinta como toast de SweetAlert2; si no hay JS o no carga la
 * librería, queda un respaldo <noscript> con el alerta HTML.
 */
if (empty($flash) || trim((string) ($flash['mensaje'] ?? '')) === '') {
    return;
}
$tipoFlash = ($flash['tipo'] ?? 'exito') === 'error' ? 'error' : 'success';
$mensajeFlash = (string) $flash['mensaje'];
?>
<noscript>
    <div role="status" class="portal-alert portal-alert--<?= $tipoFlash === 'error' ? 'peligro' : 'exito' ?> p-3 mb-4">
        <?= htmlspecialchars($mensajeFlash) ?>
    </div>
</noscript>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.solfAviso) {
            solfAviso(<?= json_encode($tipoFlash) ?>, <?= json_encode($mensajeFlash, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);
        }
    });
</script>
