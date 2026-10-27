<?php
$perfil  = $perfil ?? [];
$usuario = $usuario ?? ($_SESSION['usuario'] ?? []);
$usuarioSesion = $usuarioSesion ?? $usuario;
$valores = $valores ?? [];
$errores = $errores ?? [];
$flash   = $flash ?? null;

$err = static fn (string $campo): string => !empty($errores[$campo]) ? ' is-invalid' : '';

$nombreCompleto = trim(($perfil['nombre'] ?? '') . ' ' . ($perfil['apellido'] ?? ''));
$nombreCompleto = $nombreCompleto !== '' ? $nombreCompleto : ($usuarioSesion['nombre'] ?? 'Instructor');
$fotoUrl = $perfil['foto'] ?? ($fotoPerfilSidebar ?? '');
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="portal-title mb-1">Mi Perfil</h1>
        <p class="portal-subtitle mb-0">Consulta y actualiza tus datos personales.</p>
    </div>
    <button form="form-perfil-principal" type="submit" class="btn fw-bold" style="background:#ffc107;color:#000;">
        <i class="bi bi-check2 me-1"></i>GUARDAR CAMBIOS
    </button>
</div>

<?php if (is_array($flash) && ($flash['mensaje'] ?? '') !== ''): ?>
    <div class="portal-alert portal-alert--<?= ($flash['tipo'] ?? 'exito') === 'error' ? 'peligro' : 'exito' ?> d-flex align-items-center gap-2 mb-4" role="alert">
        <i class="bi bi-<?= ($flash['tipo'] ?? 'exito') === 'error' ? 'exclamation-circle' : 'check-circle' ?>-fill"></i>
        <span><?= htmlspecialchars($flash['mensaje']) ?></span>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Columna Izquierda -->
    <div class="col-lg-4">
        <div class="d-flex flex-column gap-4">
            <!-- Tarjeta Foto y Rol -->
            <div class="portal-card p-4 text-center h-100">
                <div class="mx-auto mb-3" style="width:140px;height:140px;border-radius:50%;border:2px solid #ffc107;overflow:hidden;background:rgba(255,255,255,0.05);display:flex;align-items:center;justify-content:center;">
                    <?php if ($fotoUrl): ?>
                        <img src="<?= htmlspecialchars($fotoUrl) ?>" alt="Foto perfil" style="width:100%;height:100%;object-fit:cover;">
                    <?php else: ?>
                        <span style="font-size:3rem;font-weight:bold;color:#fff;"><?= htmlspecialchars(mb_strtoupper(mb_substr($nombreCompleto, 0, 1))) ?></span>
                    <?php endif; ?>
                </div>
                <h2 class="portal-card-title mb-1" style="font-size:1.25rem;"><?= htmlspecialchars($nombreCompleto) ?></h2>
                <p class="mb-3" style="color:#94a3b8;">Instructor</p>
                <button type="button" class="btn btn-outline-light w-100" data-bs-toggle="modal" data-bs-target="#modalFotoInstructor">
                    <i class="bi bi-camera me-1"></i>Cambiar Foto
                </button>
            </div>

            <!-- Resumen -->
            <div class="portal-card p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="portal-card-title mb-0">Resumen</h3>
                </div>
                <div class="row text-center">
                    <div class="col-6">
                        <div class="fw-bold fs-5"><?= (int)($perfil['grupos_count'] ?? 0) ?></div>
                        <div class="small" style="color:#94a3b8;">Grupos</div>
                    </div>
                    <div class="col-6">
                        <div class="fw-bold fs-5"><?= (int)($perfil['alumnos_count'] ?? 0) ?></div>
                        <div class="small" style="color:#94a3b8;">Alumnos</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Columna Derecha -->
    <div class="col-lg-8">
        <div class="d-flex flex-column gap-4">
            <!-- Datos Personales -->
            <div class="portal-card p-3 p-md-4">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <h2 class="portal-card-title mb-0">Datos Personales</h2>
                </div>

                <form id="form-perfil-principal" method="post" action="/instructor/perfil" novalidate>
                    <?= \App\Application\Security\Csrf::campo() ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="nombre">Nombre</label>
                            <input type="text" class="form-control<?= $err('nombre') ?>" id="nombre" name="nombre"
                                   maxlength="100" required value="<?= htmlspecialchars($valores['nombre'] ?? ($perfil['nombre'] ?? '')) ?>">
                            <?php if (!empty($errores['nombre'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errores['nombre']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="apellido">Apellidos</label>
                            <input type="text" class="form-control<?= $err('apellido') ?>" id="apellido" name="apellido"
                                   maxlength="100" required value="<?= htmlspecialchars($valores['apellido'] ?? ($perfil['apellido'] ?? '')) ?>">
                            <?php if (!empty($errores['apellido'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errores['apellido']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="email">Correo Electrónico</label>
                            <input type="email" class="form-control<?= $err('email') ?>" id="email" name="email"
                                   maxlength="150" required value="<?= htmlspecialchars($valores['email'] ?? ($perfil['email'] ?? ($usuarioSesion['email'] ?? ''))) ?>">
                            <?php if (!empty($errores['email'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errores['email']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="telefono">Teléfono de Contacto</label>
                            <input type="tel" class="form-control<?= $err('telefono') ?>" id="telefono" name="telefono"
                                   maxlength="20" value="<?= htmlspecialchars($valores['telefono'] ?? ($perfil['telefono'] ?? '')) ?>">
                            <?php if (!empty($errores['telefono'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errores['telefono']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Cambiar contraseña -->
            <div class="portal-card p-3 p-md-4">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <h2 class="portal-card-title mb-0">Cambiar contraseña</h2>
                </div>

                <form method="post" action="/instructor/perfil/password" novalidate>
                    <?= \App\Application\Security\Csrf::campo() ?>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="password_actual">Contraseña actual</label>
                            <input type="password" class="form-control<?= $err('password_actual') ?>"
                                   id="password_actual" name="password_actual" autocomplete="current-password" required>
                            <?php if (!empty($errores['password_actual'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errores['password_actual']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="password_nueva">Nueva contraseña</label>
                            <input type="password" class="form-control<?= $err('password_nueva') ?>"
                                   id="password_nueva" name="password_nueva" minlength="8" autocomplete="new-password" required>
                            <?php if (!empty($errores['password_nueva'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errores['password_nueva']) ?></div>
                            <?php else: ?>
                                <div class="form-text" style="color: var(--portal-muted);">Mínimo 8 caracteres.</div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="password_confirmar">Repite la nueva contraseña</label>
                            <input type="password" class="form-control<?= $err('password_confirmar') ?>"
                                   id="password_confirmar" name="password_confirmar" autocomplete="new-password" required>
                            <?php if (!empty($errores['password_confirmar'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errores['password_confirmar']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end mt-3">
                        <button type="submit" class="btn btn-accent fw-semibold px-4">
                            <i class="bi bi-arrow-repeat me-1"></i>Actualizar contraseña
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Foto -->
<div class="modal fade" id="modalFotoInstructor" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content portal-modal">
            <form method="post" action="/instructor/perfil/foto" enctype="multipart/form-data">
                <?= \App\Application\Security\Csrf::campo() ?>
                <div class="modal-header border-0">
                    <h5 class="modal-title">Cambiar Foto de Perfil</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="file" name="foto" class="form-control" accept="image/*" required>
                    <div class="form-text" style="color: var(--portal-muted);">Formatos: JPG, PNG, GIF, WEBP. Máximo 2MB.</div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-accent">Subir Foto</button>
                </div>
            </form>
        </div>
    </div>
</div>
