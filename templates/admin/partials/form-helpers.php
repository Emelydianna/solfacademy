<?php
$e = static fn ($valor): string => htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES, 'UTF-8');
$valor = static fn (string $campo, $defecto = '') => $valores[$campo] ?? $defecto;
$err = static fn (string $campo): string => isset($errores[$campo]) ? ' is-invalid' : '';
$ariaError = static fn (string $campo): string => isset($errores[$campo]) ? ' aria-invalid="true" aria-describedby="error-' . $campo . '"' : '';
$errorCampo = static function (string $campo) use ($errores, $e): void {
    if (isset($errores[$campo])) {
        echo '<div class="invalid-feedback d-block" id="error-' . $campo . '">' . $e($errores[$campo]) . '</div>';
    }
};
$input = static function (string $campo, string $label, string $tipo = 'text', string $atributos = '') use ($e, $valor, $err, $ariaError, $errorCampo): void {
    echo '<div class="mb-3"><label for="' . $campo . '" class="form-label">' . $e($label) . '</label>';
    echo '<input id="' . $campo . '" name="' . $campo . '" type="' . $tipo . '" class="form-control' . $err($campo) . '" value="' . ($tipo === 'password' ? '' : $e($valor($campo))) . '" ' . $atributos . $ariaError($campo) . '>';
    $errorCampo($campo);
    echo '</div>';
};
?>
<?php if ($errores): ?><div role="alert" class="portal-alert portal-alert--peligro p-3 mb-3"><?= $e($errores['general'] ?? 'Revisa los campos señalados. No se guardaron cambios.') ?></div><?php endif; ?>
