<?php
$grupos = $grupos ?? [];
$mensaje = $mensaje ?? null;
$tipoMsg = $tipoMsg ?? null;
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="portal-title mb-1">Solicitar Clase Extra</h1>
        <p class="portal-subtitle mb-0">Solicita una clase extra con tu instructor.</p>
    </div>
    <a href="/estudiante/reservas" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Mis Reservas</a>
</div>

<?php if ($mensaje): ?>
    <div class="alert alert-<?= htmlspecialchars($tipoMsg === 'success' ? 'success' : 'danger') ?>"><?= htmlspecialchars($mensaje) ?></div>
<?php endif; ?>

<?php if (count($grupos) === 0): ?>
    <div class="portal-card p-4 text-center">
        <p class="mb-1 fw-semibold">No tienes cursos activos</p>
        <p class="small text-secondary mb-3">Necesitas estar inscrito activamente en algún curso para solicitar clases extras.</p>
        <a href="/estudiante/cursos" class="btn btn-accent fw-semibold">Ver Mis Cursos</a>
    </div>
<?php else: ?>
    <div class="portal-card p-3 p-md-4">
        <form method="post" action="/estudiante/reservas/extras">
            <?= \App\Application\Security\Csrf::campo() ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label portal-label">Curso / Grupo</label>
                    <select name="id_grupo" class="form-select portal-input" required>
                        <option value="">Selecciona un curso</option>
                        <?php foreach ($grupos as $g): ?>
                            <option value="<?= (int)$g['id_grupo'] ?>"><?= htmlspecialchars($g['curso']) ?> - <?= htmlspecialchars($g['instructor']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label portal-label">Fecha</label>
                    <input type="date" name="fecha" class="form-control portal-input" min="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label portal-label">Hora inicio</label>
                    <input type="time" name="hora_inicio" class="form-control portal-input" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label portal-label">Hora fin</label>
                    <input type="time" name="hora_fin" class="form-control portal-input" required>
                </div>
                <div class="col-12">
                    <label class="form-label portal-label">Motivo</label>
                    <input type="text" name="motivo" class="form-control portal-input" placeholder="Refuerzo, preparación para examen, etc." required>
                </div>
            </div>
            <div class="mt-3">
                <button class="btn btn-accent fw-bold"><i class="bi bi-check-lg me-1"></i>Solicitar Clase Extra</button>
            </div>
        </form>
    </div>
<?php endif; ?>
