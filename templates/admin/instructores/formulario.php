<?php $e = static fn ($v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8'); ?>
<div class="admin-academica">
    <div class="d-flex flex-wrap justify-content-between gap-3 mb-4"><div><h1 class="portal-title mb-1"><?= $idEdicion ? 'Editar Instructor' : 'Agregar Instructor' ?></h1><p class="portal-subtitle mb-0">Datos personales, especialidades y cuenta de acceso opcional.</p></div><a href="/admin/instructores" class="btn admin-btn-outline align-self-start">Volver al listado</a></div>
    <section class="portal-card p-3 p-md-4">
        <?php require __DIR__ . '/../partials/form-helpers.php'; ?>
        <form method="post" action="/admin/instructores<?= $idEdicion ? '/' . (int) $idEdicion : '' ?>" enctype="multipart/form-data" id="form-academico">
            <?= \App\Application\Security\Csrf::campo() ?>
            <div class="row g-4"><div class="col-lg-8">
                <h2 class="portal-card-title mb-4">Ficha del instructor</h2>
                <div class="row"><div class="col-md-6"><?php $input('nombre', 'Nombre *', 'text', 'required maxlength="100" autocomplete="given-name"'); ?></div><div class="col-md-6"><?php $input('apellido', 'Apellidos *', 'text', 'required maxlength="100" autocomplete="family-name"'); ?></div></div>
                <div class="row"><div class="col-md-6"><?php $input('email', 'Email (obligatorio con cuenta)', 'email', 'maxlength="150" autocomplete="email"'); ?></div><div class="col-md-6"><?php $input('telefono', 'Teléfono', 'tel', 'maxlength="20" autocomplete="tel"'); ?></div></div>
                <?php $input('anios_experiencia', 'Años de experiencia musical *', 'number', 'min="0" max="255" step="1" required'); ?>
                <div class="mb-4"><label for="foto" class="form-label">Foto opcional</label><input type="file" id="foto" name="foto" class="form-control<?= $err('foto') ?>" accept="image/jpeg,image/png,image/gif,image/webp"<?= $ariaError('foto') ?>><?php $errorCampo('foto'); ?><p class="small admin-muted mt-2">JPEG, PNG, GIF o WebP de hasta 2 MB. Si editas y no adjuntas imagen, se conserva la foto actual.</p></div>
                <fieldset class="mb-4"><legend class="portal-card-title">Especialidades *</legend><p class="small admin-muted">Selecciona los instrumentos registrados en la academia. No pueden repetirse.</p>
                    <div data-especialidades><?php $filas = $valor('especialidades', []) ?: [['id_instrumento' => '', 'nivel_dominio' => 'intermedio', 'anios_experiencia_i' => '0']]; foreach ($filas as $n => $esp): require __DIR__ . '/especialidad.php'; endforeach; ?></div>
                    <?php $errorCampo('especialidades'); ?><button type="button" class="btn admin-btn-outline btn-sm" data-agregar-especialidad><i class="bi bi-plus-lg me-1"></i>Agregar instrumento</button>
                    <?php if (!$instrumentos): ?><p class="small admin-muted mt-2">Primero <a href="/admin/instrumentos">registra un instrumento</a>.</p><?php endif; ?>
                </fieldset>
            </div><div class="col-lg-4">
                <h2 class="portal-card-title mb-4">Cuenta de acceso</h2>
                <?php if ($instructor && !$instructor['activo']): ?><div class="portal-alert portal-alert--peligro p-3">Instructor suspendido. Reactívalo desde su ficha antes de habilitar o crear acceso.</div>
                <?php elseif ($instructor && $instructor['cuentas']): ?>
                    <p class="small admin-muted">El correo de la ficha y el de acceso se actualizan juntos. No se cambia la contraseña.</p>
                    <fieldset><legend class="form-label">Estado del acceso</legend><?php foreach (['1' => 'Activo', '0' => 'Inactivo'] as $v => $label): ?><div class="form-check form-check-inline"><input id="acceso-<?= $v ?>" type="radio" name="acceso_activo" value="<?= $v ?>" class="form-check-input" <?= (string) $valor('acceso_activo') === (string) $v ? 'checked' : '' ?> required><label for="acceso-<?= $v ?>" class="form-check-label"><?= $label ?></label></div><?php endforeach; ?><?php $errorCampo('acceso_activo'); ?></fieldset>
                    <p class="small admin-muted mt-3">Desactivar solo el acceso no suspende al instructor ni modifica sus grupos. Para retirarlo utiliza Suspender.</p>
                <?php else: ?>
                    <div class="form-check mb-3"><input id="crear_cuenta" name="crear_cuenta" value="1" type="checkbox" class="form-check-input" <?= $valor('crear_cuenta') === '1' ? 'checked' : '' ?> data-crear-cuenta><label for="crear_cuenta" class="form-check-label">Crear cuenta de acceso</label></div>
                    <p class="small admin-muted">Se usará el email de la ficha y el rol Instructor. El administrador mantendrá su sesión.</p>
                    <div data-cuenta-campos><?php $input('password', 'Contraseña inicial', 'password', 'minlength="8" maxlength="72" autocomplete="new-password"'); $input('password_confirmar', 'Confirmar contraseña', 'password', 'minlength="8" maxlength="72" autocomplete="new-password"'); ?></div>
                <?php endif; ?>
                <p class="small admin-muted mt-4">El instructor se asigna a los grupos del curso. Las valoraciones aún no están disponibles: no se usarán las notas de los alumnos como puntuación del profesor.</p>
            </div></div>
            <div class="d-flex justify-content-end flex-wrap gap-2 border-top admin-border pt-4"><a href="<?= $idEdicion ? '/admin/instructores/' . (int) $idEdicion : '/admin/instructores' ?>" class="btn admin-btn-outline">CANCELAR</a><button class="btn admin-btn-primary">GUARDAR INSTRUCTOR</button></div>
        </form>
        <template id="plantilla-especialidad"><?php $n = 0; $esp = ['id_instrumento' => '', 'nivel_dominio' => 'intermedio', 'anios_experiencia_i' => '0']; require __DIR__ . '/especialidad.php'; ?></template>
    </section>
</div>
<script src="/js/admin-academica.js" defer></script>
