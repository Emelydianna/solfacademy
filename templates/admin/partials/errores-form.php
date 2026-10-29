<?php
/**
 * Error de validación de formulario admin como toast de SweetAlert2.
 *
 * Uso: se incluye donde se pintan $errores (form-helpers.php o el propio
 * template). Mantiene el div inline para accesibilidad y contexto junto al
 * campo; el toast es el resumen visible que faltaba tras un 422, ya que el
 * formulario de gestión vive en una columna lateral y el div es fácil de
 * no ver tras el reload.
 *
 * Espera $errores (array) en ámbito. No hace nada si está vacío.
 */
if (empty($errores) || !is_array($errores)) {
    return;
}
$mensajeError = (string) ($errores['general'] ?? 'Revisa los campos señalados. No se guardaron cambios.');
?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.solfAviso) {
            solfAviso('error', <?= json_encode($mensajeError, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);
        }
    });
</script>
