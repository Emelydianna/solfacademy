<div class="row g-2 align-items-end mb-2" data-horario>
    <div class="col-12 col-sm-4"><label class="visually-hidden">Día</label><select name="horarios[<?= (int) $n ?>][dia_semana]" data-campo="dia_semana" class="form-select" aria-label="Día de la franja" required><?php foreach (\App\Application\Admin\GruposService::DIAS as $dia): ?><option value="<?= $e($dia) ?>" <?= ($h['dia_semana'] ?? '') === $dia ? 'selected' : '' ?>><?= $e($dia) ?></option><?php endforeach; ?></select></div>
    <div class="col-5 col-sm-3"><input type="time" name="horarios[<?= (int) $n ?>][hora_inicio]" data-campo="hora_inicio" class="form-control" aria-label="Hora inicial de la franja" value="<?= $e($h['hora_inicio'] ?? '') ?>" required></div>
    <div class="col-5 col-sm-3"><input type="time" name="horarios[<?= (int) $n ?>][hora_fin]" data-campo="hora_fin" class="form-control" aria-label="Hora final de la franja" value="<?= $e($h['hora_fin'] ?? '') ?>" required></div>
    <div class="col-2"><button type="button" class="btn admin-icon-btn admin-delete" data-quitar-horario aria-label="Quitar franja"><i class="bi bi-trash"></i></button></div>
</div>
