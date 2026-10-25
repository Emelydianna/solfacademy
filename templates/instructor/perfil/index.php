<?php
$perfil = $perfil ?? [];
$usuario = $usuario ?? [];
$flash = $flash ?? null;
$fotoUrl = $perfil['foto'] ?? '';
?>
<div class="mb-4">
    <h1 class="portal-title mb-1">Mi Perfil</h1>
    <p class="portal-subtitle mb-0">Información personal y configuración del instructor</p>
</div>

<?php if (is_array($flash) && ($flash['mensaje'] ?? '') !== ''): ?>
    <div class="alert alert-<?= ($flash['tipo'] ?? 'exito') === 'error' ? 'danger' : 'success' ?> d-flex align-items-center gap-2 mb-4" role="alert">
        <i class="bi bi-<?= ($flash['tipo'] ?? 'exito') === 'error' ? 'exclamation-circle' : 'check-circle' ?>-fill"></i>
        <span><?= htmlspecialchars($flash['mensaje']) ?></span>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="portal-card p-4 text-center">
            <div class="portal-avatar mx-auto mb-3" style="width:80px;height:80px;font-size:2rem;overflow:hidden;">
                <?php if ($fotoUrl): ?>
                    <img src="<?= htmlspecialchars($fotoUrl) ?>" alt="Foto perfil" style="width:100%;height:100%;object-fit:cover;">
                <?php else: ?>
                    <?= htmlspecialchars(mb_strtoupper(mb_substr($perfil['nombre'] ?? ($usuario['nombre'] ?? 'I'), 0, 1).mb_substr($perfil['apellido'] ?? '', 0, 1))) ?>
                <?php endif; ?>
            </div>
            <h4 class="mb-1"><?= htmlspecialchars(trim(($perfil['nombre'] ?? '').' '.($perfil['apellido'] ?? ''))) ?: htmlspecialchars($usuario['nombre'] ?? 'Instructor') ?></h4>
            <span class="portal-profile-badge mb-3" style="background:#ffc107;color:#000;">INSTRUCTOR</span>
            <button type="button" class="btn btn-outline-warning btn-sm mb-3" data-bs-toggle="modal" data-bs-target="#modalFotoInstructor"><i class="bi bi-camera me-1"></i>Cambiar Foto</button>
            <div class="row text-center mt-3">
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
    <div class="col-lg-8">
        <div class="portal-card p-4">
            <h5 class="mb-3">Datos Personales</h5>
            <form method="post" action="/instructor/perfil">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Nombre</label>
                        <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($perfil['nombre'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Apellido</label>
                        <input type="text" name="apellido" class="form-control" value="<?= htmlspecialchars($perfil['apellido'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Correo Electrónico</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($perfil['email'] ?? ($usuario['email'] ?? '')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Teléfono</label>
                        <input type="text" name="telefono" class="form-control" value="<?= htmlspecialchars($perfil['telefono'] ?? '') ?>">
                    </div>
                    <div class="col-12 d-flex justify-content-end">
                        <button class="btn btn-warning fw-bold">Guardar Cambios</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalFotoInstructor" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background:#0f172a;color:#fff;border:1px solid var(--portal-border);">
            <form method="post" action="/instructor/perfil/foto" enctype="multipart/form-data">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Cambiar Foto de Perfil</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="file" name="foto" class="form-control" accept="image/*" required>
                    <div class="form-text" style="color:#94a3b8;">Formatos: JPG, PNG, GIF, WEBP. Máximo 2MB.</div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning text-dark">Subir Foto</button>
                </div>
            </form>
        </div>
    </div>
</div>
