<?php
use App\Application\Security\Csrf;

$e = static fn ($v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$valores = $valores ?? null;
$errores = $errores ?? [];

$valor = static function (string $campo, string $default = '') use ($valores): string {
    return is_array($valores) && array_key_exists($campo, $valores) ? (string) $valores[$campo] : $default;
};
$rol = $valor('rol', 'estudiante');
if (!in_array($rol, ['admin', 'instructor', 'estudiante'], true)) {
    $rol = 'estudiante';
}
$etiquetas = ['admin' => 'Administrador', 'instructor' => 'Instructor', 'estudiante' => 'Estudiante'];
$errorDe = static fn (string $campo): string => isset($errores[$campo])
    ? '<div class="text-danger small mt-1">' . $e($errores[$campo]) . '</div>'
    : '';
?>
<div class="admin-academica">
    <div class="d-flex flex-wrap justify-content-between gap-3 mb-4">
        <div>
            <h1 class="portal-title mb-1">Crear usuario</h1>
            <p class="portal-subtitle mb-0">Alta de cuenta de acceso con rol y contraseña inicial. La contraseña solo se guarda cifrada.</p>
        </div>
        <a href="/admin/usuarios" class="btn admin-btn-outline align-self-start">Volver</a>
    </div>

    <?php if (!empty($errores['general'])): ?>
        <div role="alert" class="portal-alert portal-alert--peligro p-3 mb-4"><?= $e($errores['general']) ?></div>
    <?php endif; ?>
    <?php require __DIR__ . '/../partials/errores-form.php'; ?>

    <section class="portal-card p-4">
        <form method="post" action="/admin/usuarios" id="form-usuario">
            <?= Csrf::campo() ?>

            <!-- Rol -->
            <fieldset class="border-0 p-0 mb-4">
                <legend class="form-label">Rol de la cuenta *</legend>
                <div class="d-flex flex-wrap gap-4">
                    <?php foreach ($etiquetas as $clave => $etiqueta): ?>
                        <div class="form-check">
                            <input id="rol-<?= $clave ?>" type="radio" name="rol" value="<?= $clave ?>"
                                   class="form-check-input" data-rol="<?= $clave ?>" <?= $rol === $clave ? 'checked' : '' ?>>
                            <label for="rol-<?= $clave ?>" class="form-check-label"><?= $etiqueta ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?= $errorDe('rol') ?>
            </fieldset>

            <!-- Personas sin cuenta (según el rol) -->
            <?php foreach (['instructor', 'estudiante'] as $clave): ?>
                <div class="mb-3" data-bloque-persona="<?= $clave ?>" <?= $rol === $clave ? '' : 'hidden' ?>>
                    <label for="persona-<?= $clave ?>" class="form-label">
                        <?= $clave === 'instructor' ? 'Instructor sin cuenta' : 'Estudiante sin cuenta' ?> *
                    </label>
                    <select id="persona-<?= $clave ?>" name="id_persona" class="form-select"
                            data-persona="<?= $clave ?>" <?= $rol === $clave ? '' : 'disabled' ?>>
                        <option value="">Selecciona…</option>
                        <?php foreach ($candidatos[$clave] as $persona): ?>
                            <option value="<?= (int) $persona['id'] ?>"
                                data-email="<?= $e($persona['email'] ?? '') ?>"
                                <?= $valor('id_persona') === (string) $persona['id'] ? 'selected' : '' ?>>
                                <?= $e($persona['nombre']) ?><?= !empty($persona['email']) ? ' — ' . $e($persona['email']) : ' (sin correo en la ficha)' ?>
                            </option>
                        <?php endforeach; ?>
                        <?php if (!$candidatos[$clave]): ?>
                            <option value="" disabled selected>
                                No hay <?= $clave === 'instructor' ? 'instructores' : 'estudiantes' ?> sin cuenta
                            </option>
                        <?php endif; ?>
                    </select>
                    <?php if (!$candidatos[$clave]): ?>
                        <div class="form-text">
                            Crea primero la ficha desde
                            <a href="<?= $clave === 'instructor' ? '/admin/instructores/nuevo' : '/admin/estudiantes/nuevo' ?>">
                                <?= $clave === 'instructor' ? 'Instructores' : 'Estudiantes' ?>
                            </a> marcando «crear cuenta».
                        </div>
                    <?php endif; ?>
                    <?= $errorDe('id_persona') ?>
                </div>
            <?php endforeach; ?>

            <!-- Contraseña y correo -->
            <div class="row g-3 mb-4">
                <div class="col-12 col-lg-6">
                    <label for="email" class="form-label">Correo de acceso *</label>
                    <input id="email" type="email" name="email" maxlength="150" required
                           class="form-control <?= isset($errores['email']) ? 'is-invalid' : '' ?>"
                           value="<?= $e($valor('email')) ?>" placeholder="usuario@ejemplo.com"
                           autocomplete="off" data-email-cuenta>
                    <div class="form-text">Al elegir una persona se rellena con el correo de su ficha y queda bloqueado.</div>
                    <?= $errorDe('email') ?>
                </div>
                <div class="col-6 col-lg-3">
                    <label for="password" class="form-label">Contraseña inicial *</label>
                    <input id="password" type="password" name="password" minlength="8" maxlength="72" required
                           class="form-control <?= isset($errores['password']) ? 'is-invalid' : '' ?>"
                           value="<?= $e($valor('password')) ?>" autocomplete="new-password">
                    <?= $errorDe('password') ?>
                </div>
                <div class="col-6 col-lg-3">
                    <label for="password_confirmar" class="form-label">Repetir contraseña *</label>
                    <input id="password_confirmar" type="password" name="password_confirmar" minlength="8" maxlength="72" required
                           class="form-control <?= isset($errores['password_confirmar']) ? 'is-invalid' : '' ?>"
                           value="<?= $e($valor('password_confirmar')) ?>" autocomplete="new-password">
                    <?= $errorDe('password_confirmar') ?>
                </div>
            </div>

            <p class="small admin-muted mb-4">
                La cuenta nace activa y sin accesos previos. El correo y los datos personales se editan
                después desde la ficha de Estudiantes o Instructores.
            </p>

            <div class="d-flex gap-2">
                <button class="btn admin-btn-primary">CREAR USUARIO</button>
                <a href="/admin/usuarios" class="btn admin-btn-outline">Cancelar</a>
            </div>
        </form>
    </section>
</div>
<script>
    // El select de persona corresponde al rol elegido; el correo se toma de
    // la ficha (y se bloquea) salvo que la persona no tenga aún ninguno.
    (function () {
        const form = document.getElementById('form-usuario');
        if (!form) return;
        const email = document.querySelector('[data-email-cuenta]');
        const bloques = Array.from(form.querySelectorAll('[data-bloque-persona]'));

        const actualizar = function () {
            const rol = (form.querySelector('input[name="rol"]:checked') || {}).value || 'estudiante';
            let selectVisible = null;

            bloques.forEach(function (bloque) {
                const activo = bloque.dataset.bloquePersona === rol;
                bloque.hidden = !activo;
                const select = bloque.querySelector('select');
                if (select) select.disabled = !activo;
                if (activo) selectVisible = select;
            });

            if (!selectVisible) {
                email.readOnly = false;
                email.required = true;
                return;
            }

            const opcion = selectVisible.selectedOptions[0];
            const correoFicha = opcion ? (opcion.dataset.email || '') : '';
            email.required = true;
            if (correoFicha !== '') {
                email.value = correoFicha;
                email.readOnly = true;
            } else {
                email.readOnly = false;
            }
        };

        form.querySelectorAll('input[name="rol"]').forEach(function (radio) {
            radio.addEventListener('change', actualizar);
        });
        bloques.forEach(function (bloque) {
            const select = bloque.querySelector('select');
            if (select) select.addEventListener('change', actualizar);
        });
        actualizar();
    })();
</script>
