<!-- Mi Perfil -->
<?php
/**
 * Mi Perfil (pantalla 8 del prototipo).
 *
 * $perfil  = EstudiantePortalService::perfil($id)  → fila de estudiante + cuenta
 * $valores = valores a mostrar en los inputs (los últimos escritos o los de BD)
 * $errores = ['campo' => 'mensaje'] → marca el input y muestra el mensaje
 * $flash   = ['tipo' => 'exito'|'error', 'mensaje' => '...'] | null
 */
$perfil  = $perfil ?? [];
$valores = $valores ?? [];
$errores = $errores ?? [];
$flash   = $flash ?? null;

/** Clase de error de un campo (Bootstrap) */
$err = static fn (string $campo): string => !empty($errores[$campo]) ? ' is-invalid' : '';

$fechaInscripcion = !empty($perfil['fecha_inscripcion'])
    ? date('d/m/Y', strtotime((string) $perfil['fecha_inscripcion']))
    : '—';

$ultimoAcceso = !empty($perfil['ultimo_acceso'])
    ? date('d/m/Y H:i', strtotime((string) $perfil['ultimo_acceso']))
    : 'Primera sesión';
?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="portal-title mb-1">Mi Perfil</h1>
        <p class="portal-subtitle mb-0">Consulta y actualiza tus datos personales y tu contraseña.</p>
    </div>
    <span class="portal-pill portal-pill--azul">
        <i class="bi bi-mortarboard me-1"></i>Estudiante
    </span>
</div>

<?php if (is_array($flash) && ($flash['mensaje'] ?? '') !== ''): ?>
    <div class="portal-alert portal-alert--exito d-flex align-items-center gap-2 mb-4" role="alert">
        <i class="bi bi-check-circle-fill"></i>
        <span><?= htmlspecialchars($flash['mensaje']) ?></span>
    </div>
<?php endif; ?>

<div class="row g-4">

    <!-- ================= Datos personales ================= -->
    <div class="col-lg-7">
        <div class="portal-card p-3 p-md-4 h-100">
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="portal-kpi-icon"><i class="bi bi-person-vcard"></i></span>
                <h2 class="portal-card-title mb-0">Datos personales</h2>
            </div>

            <form method="post" action="/estudiante/perfil" novalidate>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="nombre">Nombre</label>
                        <input type="text" class="form-control<?= $err('nombre') ?>" id="nombre" name="nombre"
                               maxlength="100" required value="<?= htmlspecialchars($valores['nombre'] ?? '') ?>">
                        <?php if (!empty($errores['nombre'])): ?>
                            <div class="invalid-feedback"><?= htmlspecialchars($errores['nombre']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="apellido">Apellidos</label>
                        <input type="text" class="form-control<?= $err('apellido') ?>" id="apellido" name="apellido"
                               maxlength="100" required value="<?= htmlspecialchars($valores['apellido'] ?? '') ?>">
                        <?php if (!empty($errores['apellido'])): ?>
                            <div class="invalid-feedback"><?= htmlspecialchars($errores['apellido']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="email">Correo electrónico</label>
                        <input type="email" class="form-control<?= $err('email') ?>" id="email" name="email"
                               maxlength="150" required value="<?= htmlspecialchars($valores['email'] ?? '') ?>">
                        <?php if (!empty($errores['email'])): ?>
                            <div class="invalid-feedback"><?= htmlspecialchars($errores['email']) ?></div>
                        <?php else: ?>
                            <div class="form-text" style="color: var(--portal-muted);">
                                Es también tu correo de acceso: se usará para iniciar sesión.
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="telefono">Teléfono</label>
                        <input type="tel" class="form-control<?= $err('telefono') ?>" id="telefono" name="telefono"
                               maxlength="20" placeholder="600 123 456" value="<?= htmlspecialchars($valores['telefono'] ?? '') ?>">
                        <?php if (!empty($errores['telefono'])): ?>
                            <div class="invalid-feedback"><?= htmlspecialchars($errores['telefono']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="direccion">Dirección</label>
                        <input type="text" class="form-control<?= $err('direccion') ?>" id="direccion" name="direccion"
                               maxlength="255" placeholder="Calle, número, ciudad" value="<?= htmlspecialchars($valores['direccion'] ?? '') ?>">
                        <?php if (!empty($errores['direccion'])): ?>
                            <div class="invalid-feedback"><?= htmlspecialchars($errores['direccion']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="d-flex justify-content-end mt-4">
                    <button type="submit" class="btn btn-accent fw-semibold px-4">
                        <i class="bi bi-check2 me-1"></i>Guardar cambios
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ================= Cuenta + contraseña ================= -->
    <div class="col-lg-5">

        <!-- Cuenta (solo lectura) -->
        <div class="portal-card p-3 p-md-4 mb-4">
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="portal-kpi-icon portal-kpi-icon--azul"><i class="bi bi-shield-lock"></i></span>
                <h2 class="portal-card-title mb-0">Cuenta</h2>
            </div>

            <dl class="mb-0">
                <div class="portal-dato">
                    <dt>Correo de acceso</dt>
                    <dd><?= htmlspecialchars((string) ($perfil['email_usuario'] ?? '')) ?></dd>
                </div>
                <div class="portal-dato">
                    <dt>Miembro desde</dt>
                    <dd><?= $fechaInscripcion ?></dd>
                </div>
                <div class="portal-dato">
                    <dt>Último acceso</dt>
                    <dd><?= $ultimoAcceso ?></dd>
                </div>
                <div class="portal-dato">
                    <dt>Estado de la cuenta</dt>
                    <dd>
                        <?php if (!empty($perfil['activo'])): ?>
                            <span class="portal-pill portal-pill--verde">Activa</span>
                        <?php else: ?>
                            <span class="portal-pill portal-pill--rojo">Suspendida</span>
                        <?php endif; ?>
                    </dd>
                </div>
            </dl>
        </div>

        <!-- Cambiar contraseña -->
        <div class="portal-card p-3 p-md-4">
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="portal-kpi-icon portal-kpi-icon--rosa"><i class="bi bi-key"></i></span>
                <h2 class="portal-card-title mb-0">Cambiar contraseña</h2>
            </div>

            <form method="post" action="/estudiante/perfil/password" novalidate>
                <div class="mb-3">
                    <label class="form-label" for="password_actual">Contraseña actual</label>
                    <input type="password" class="form-control<?= $err('password_actual') ?>"
                           id="password_actual" name="password_actual" autocomplete="current-password" required>
                    <?php if (!empty($errores['password_actual'])): ?>
                        <div class="invalid-feedback"><?= htmlspecialchars($errores['password_actual']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="password_nueva">Nueva contraseña</label>
                    <input type="password" class="form-control<?= $err('password_nueva') ?>"
                           id="password_nueva" name="password_nueva" minlength="8" autocomplete="new-password" required>
                    <?php if (!empty($errores['password_nueva'])): ?>
                        <div class="invalid-feedback"><?= htmlspecialchars($errores['password_nueva']) ?></div>
                    <?php else: ?>
                        <div class="form-text" style="color: var(--portal-muted);">
                            Mínimo 8 caracteres.
                        </div>
                    <?php endif; ?>
                </div>

                <div class="mb-4">
                    <label class="form-label" for="password_confirmar">Repite la nueva contraseña</label>
                    <input type="password" class="form-control<?= $err('password_confirmar') ?>"
                           id="password_confirmar" name="password_confirmar" autocomplete="new-password" required>
                    <?php if (!empty($errores['password_confirmar'])): ?>
                        <div class="invalid-feedback"><?= htmlspecialchars($errores['password_confirmar']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-accent fw-semibold px-4">
                        <i class="bi bi-arrow-repeat me-1"></i>Actualizar contraseña
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
