<?php
use App\Application\Security\Csrf;
$e = static fn ($v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$valores = $valores ?? null;
$errores = $errores ?? [];
$val = static fn (string $k, $d = '') => $valores[$k] ?? $curso[$k] ?? $d;
$err = static fn (string $k): string => isset($errores[$k]) ? ' is-invalid' : '';
?>
<div class="admin-academica">
    <div class="d-flex flex-wrap justify-content-between gap-3 mb-4"><div><h1 class="portal-title mb-1">Requisitos del Curso</h1><p class="portal-subtitle mb-0"><?= $e($curso['nombre']) ?> · <?= $e($curso['categoria']) ?> · <?= $e($curso['nivel']) ?></p></div><a href="/admin/requisitos" class="btn admin-btn-outline">Volver</a></div>
    <section class="portal-card p-3 p-md-4">
        <?php if ($errores): ?><div role="alert" class="portal-alert portal-alert--peligro p-3 mb-3"><?= $e($errores['general'] ?? 'Revisa los campos señalados.') ?></div><?php endif; ?>
        <form action="/admin/requisitos/<?= (int) $curso['id_curso'] ?>" method="post" id="form-academico">
            <?= Csrf::campo() ?>
            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="mb-3"><label for="nivel_minimo" class="form-label">Nivel mínimo</label><input id="nivel_minimo" name="nivel_minimo" maxlength="100" class="form-control<?= $err('nivel_minimo') ?>" value="<?= $e($val('nivel_minimo')) ?>"><div class="form-text">Texto mostrado al alumno; puede quedar vacío.</div></div>
                    <div class="row"><div class="col-6"><label for="edad_minima" class="form-label">Edad mínima *</label><input id="edad_minima" type="number" min="1" max="255" name="edad_minima" required class="form-control<?= $err('edad_minima') ?>" aria-invalid="<?= isset($errores['edad_minima']) ? 'true' : 'false' ?>" value="<?= $e($val('edad_minima', '14')) ?>"></div><div class="col-6"><label for="edad_maxima" class="form-label">Edad máxima *</label><input id="edad_maxima" type="number" min="1" max="255" name="edad_maxima" required class="form-control<?= $err('edad_maxima') ?>" aria-invalid="<?= isset($errores['edad_maxima']) ? 'true' : 'false' ?>" value="<?= $e($val('edad_maxima', '99')) ?>"></div></div>
                    <?php if (isset($errores['edad_maxima'])): ?><div class="invalid-feedback d-block"><?= $e($errores['edad_maxima']) ?></div><?php endif; ?>
                    <div class="mb-3 mt-3"><label for="conocimientos_previos" class="form-label">Conocimientos previos</label><input id="conocimientos_previos" name="conocimientos_previos" maxlength="255" class="form-control<?= $err('conocimientos_previos') ?>" value="<?= $e($val('conocimientos_previos')) ?>"></div>
                    <div class="mb-3"><label for="descripcion_general" class="form-label">Descripción general</label><textarea id="descripcion_general" name="descripcion_general" rows="5" maxlength="16000" class="form-control<?= $err('descripcion_general') ?>"><?= $e($val('descripcion_general')) ?></textarea></div>
                </div>
                <div class="col-lg-5">
                    <fieldset class="mb-4"><legend class="form-label">Instrumento</legend>
                        <div class="form-check"><input id="necesita_instrumento" name="necesita_instrumento" value="1" type="checkbox" class="form-check-input" <?= $val('necesita_instrumento') ? 'checked' : '' ?>><label for="necesita_instrumento" class="form-check-label">El alumno necesita instrumento propio</label></div>
                        <div class="form-check"><input id="puede_alquilar" name="puede_alquilar" value="1" type="checkbox" class="form-check-input" <?= $val('puede_alquilar') ? 'checked' : '' ?>><label for="puede_alquilar" class="form-check-label">Se permite instrumento de alquiler</label></div>
                    </fieldset>
                    <p class="small admin-muted">La edad se verifica automáticamente cuando el estudiante tenga fecha de nacimiento registrada. Si falta, administración deberá confirmarla manualmente al aprobar. Los conocimientos y el instrumento requieren revisión explícita.</p>
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 border-top admin-border pt-4"><a href="/admin/requisitos" class="btn admin-btn-outline">CANCELAR</a><button class="btn admin-btn-primary">GUARDAR REQUISITOS</button></div>
        </form>
    </section>
</div>
<script src="/js/admin-academica.js" defer></script>
