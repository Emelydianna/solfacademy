<?php $e = static fn ($v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8'); ?>
<div class="admin-academica">
    <div class="d-flex flex-wrap justify-content-between gap-3 mb-4"><div><h1 class="portal-title mb-1"><?= $idEdicion ? 'Editar Estudiante' : 'Agregar Estudiante' ?></h1><p class="portal-subtitle mb-0">Datos personales y cuenta de acceso opcional. El alta no crea matrículas.</p></div><a href="/admin/estudiantes" class="btn admin-btn-outline align-self-start">Volver al listado</a></div>
    <section class="portal-card p-3 p-md-4">
        <?php require __DIR__ . '/../partials/form-helpers.php'; ?>
        <form method="post" action="/admin/estudiantes<?= $idEdicion ? '/' . (int) $idEdicion : '' ?>" id="form-academico">
            <?= \App\Application\Security\Csrf::campo() ?>
            <div class="row g-4"><div class="col-lg-7">
                <h2 class="portal-card-title mb-4">Ficha personal</h2>
                <div class="row"><div class="col-md-6"><?php $input('nombre', 'Nombre *', 'text', 'required maxlength="100" autocomplete="given-name"'); ?></div><div class="col-md-6"><?php $input('apellido', 'Apellidos *', 'text', 'required maxlength="100" autocomplete="family-name"'); ?></div></div>
                <?php $input('email', 'Correo electrónico *', 'email', 'required maxlength="150" autocomplete="email"'); ?>
                <div class="row"><div class="col-md-4"><?php $input('telefono', 'Teléfono', 'tel', 'maxlength="20" autocomplete="tel"'); ?></div><div class="col-md-4"><?php $input('fecha_inscripcion', 'Fecha de registro', 'date', 'max="' . date('Y-m-d') . '"'); ?></div><div class="col-md-4"><?php $input('fecha_nacimiento', 'Fecha de nacimiento', 'date', 'max="' . date('Y-m-d') . '"'); ?></div></div>
                <?php $input('direccion', 'Dirección', 'text', 'maxlength="255" autocomplete="street-address"'); ?>
                <?php if ($idEdicion && !$valor('fecha_inscripcion')): ?><p class="small admin-muted">La ficha no tiene fecha de registro. Puedes conservarla vacía; no se inventará una fecha histórica.</p><?php endif; ?>
            </div><div class="col-lg-5">
                <h2 class="portal-card-title mb-4">Cuenta de acceso</h2>
                <?php if ($estudiante && $estudiante['estado'] === 'Suspendido'): ?>
                    <div class="portal-alert portal-alert--peligro p-3">Estudiante suspendido. Debes reactivarlo desde su ficha antes de habilitar o crear acceso.</div>
                <?php elseif ($estudiante && $estudiante['cuentas']): ?>
                    <p class="admin-muted small">El correo de acceso se actualizará junto con el correo de la ficha. No se modifica la contraseña.</p>
                    <fieldset><legend class="form-label">Estado del acceso</legend><?php foreach (['1' => 'Activo', '0' => 'Inactivo'] as $v => $label): ?><div class="form-check form-check-inline"><input id="acceso-<?= $v ?>" type="radio" name="acceso_activo" value="<?= $v ?>" class="form-check-input" <?= (string) $valor('acceso_activo') === (string) $v ? 'checked' : '' ?> required><label for="acceso-<?= $v ?>" class="form-check-label"><?= $label ?></label></div><?php endforeach; ?><?php $errorCampo('acceso_activo'); ?></fieldset>
                    <p class="small admin-muted mt-3">Desactivar solo el acceso no suspende al alumno ni libera cupos. Para retirarlo de la academia utiliza Suspender.</p>
                <?php else: ?>
                    <div class="form-check mb-3"><input id="crear_cuenta" name="crear_cuenta" value="1" type="checkbox" class="form-check-input" <?= $valor('crear_cuenta') === '1' ? 'checked' : '' ?> data-crear-cuenta><label for="crear_cuenta" class="form-check-label">Crear cuenta de acceso</label></div>
                    <p class="small admin-muted">Opcional. Se usará el email de la ficha y el rol Estudiante. El administrador mantiene su sesión.</p>
                    <div data-cuenta-campos><?php $input('password', 'Contraseña inicial', 'password', 'minlength="8" maxlength="72" autocomplete="new-password"'); $input('password_confirmar', 'Confirmar contraseña', 'password', 'minlength="8" maxlength="72" autocomplete="new-password"'); ?></div>
                <?php endif; ?>
            </div></div>
            <div class="d-flex justify-content-end flex-wrap gap-2 border-top admin-border pt-4 mt-3"><a href="<?= $idEdicion ? '/admin/estudiantes/' . (int) $idEdicion : '/admin/estudiantes' ?>" class="btn admin-btn-outline">CANCELAR</a><button class="btn admin-btn-primary">GUARDAR ESTUDIANTE</button></div>
        </form>
    </section>
</div>
<script src="/js/admin-academica.js" defer></script>
