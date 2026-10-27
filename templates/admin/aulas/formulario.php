<?php $e = static fn ($v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8'); ?>
<div class="admin-academica">
    <div class="d-flex flex-wrap justify-content-between gap-3 mb-4"><div><h1 class="portal-title mb-1"><?= $idEdicion ? 'Editar Aula' : 'Agregar Aula' ?></h1><p class="portal-subtitle mb-0">Aforo, ubicación y equipamiento del espacio físico.</p></div><a href="/admin/aulas" class="btn admin-btn-outline align-self-start">Volver al listado</a></div>
    <section class="portal-card p-3 p-md-4">
        <?php require __DIR__ . '/../partials/form-helpers.php'; ?>
        <form action="/admin/aulas<?= $idEdicion ? '/' . (int) $idEdicion : '' ?>" method="post" id="form-academico">
            <?= \App\Application\Security\Csrf::campo() ?>
            <div class="row g-4"><div class="col-lg-7">
                <?php $input('nombre', 'Nombre del aula *', 'text', 'required maxlength="50"'); ?>
                <div class="row"><div class="col-md-4"><?php $input('capacidad', 'Capacidad (alumnos) *', 'number', 'min="1" max="255" step="1" required'); ?></div><div class="col-md-8"><?php $input('ubicacion', 'Ubicación', 'text', 'maxlength="150"'); ?></div></div>
                <div class="mb-3"><label for="recursos" class="form-label">Equipamiento disponible</label><textarea id="recursos" name="recursos" rows="4" maxlength="255" class="form-control<?= $err('recursos') ?>"<?= $ariaError('recursos') ?>><?= $e($valor('recursos')) ?></textarea><?php $errorCampo('recursos'); ?><p class="small admin-muted mt-2">Puedes separar los recursos por comas, punto y coma o saltos de línea. Máximo 255 caracteres.</p></div>
            </div><div class="col-lg-5"><h2 class="portal-card-title mb-3">Habilitación y disponibilidad</h2><p class="admin-muted small"><?= $idEdicion ? 'La habilitación y el mantenimiento se administran desde la agenda del aula, con confirmación. Editar estos datos no cambia su estado.' : 'El aula se crea habilitada. Después puedes deshabilitarla o ponerla en mantenimiento desde su agenda.' ?></p><p class="small admin-muted">No se puede reducir la capacidad por debajo de la configurada en sus grupos activos o planeados. La ocupación se calcula desde los horarios: no se introduce manualmente.</p></div></div>
            <div class="d-flex justify-content-end flex-wrap gap-2 border-top admin-border pt-4"><a href="<?= $idEdicion ? '/admin/aulas/' . (int) $idEdicion : '/admin/aulas' ?>" class="btn admin-btn-outline">CANCELAR</a><button class="btn admin-btn-primary">GUARDAR AULA</button></div>
        </form>
    </section>
</div>
<script src="/js/admin-academica.js" defer></script>
