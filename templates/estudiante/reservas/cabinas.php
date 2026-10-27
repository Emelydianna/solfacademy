<?php
$cabinas = $cabinas ?? [];
$mensaje = $mensaje ?? null;
$tipoMsg = $tipoMsg ?? null;
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="portal-title mb-1">Reservar Cabina de Piano</h1>
        <p class="portal-subtitle mb-0">Reserva una cabina para práctica extra.</p>
    </div>
    <a href="/estudiante/reservas" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Mis Reservas</a>
</div>

<?php if ($mensaje): ?>
    <div class="alert alert-<?= htmlspecialchars($tipoMsg === 'success' ? 'success' : 'danger') ?>"><?= htmlspecialchars($mensaje) ?></div>
<?php endif; ?>

<div class="portal-card p-3 p-md-4">
    <form method="post" action="/estudiante/reservas/cabinas">
        <?= \App\Application\Security\Csrf::campo() ?>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label portal-label">Cabina</label>
                <select name="id_cabina" class="form-select portal-input" required>
                    <option value="">Selecciona una cabina</option>
                    <?php foreach ($cabinas as $c): ?>
                        <option value="<?= (int)$c['id_cabina'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
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
                <label class="form-label portal-label">Motivo (opcional)</label>
                <input type="text" name="motivo" class="form-control portal-input" placeholder="Práctica, ensayo, etc.">
            </div>
        </div>
        <div class="mt-3">
            <button class="btn btn-accent fw-bold"><i class="bi bi-check-lg me-1"></i>Solicitar Reserva</button>
        </div>
    </form>
</div>
