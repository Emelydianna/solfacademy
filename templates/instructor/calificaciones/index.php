<?php
$grupos = $grupos ?? [];
$grupo = $grupo ?? [];
$alumnos = $alumnos ?? [];
$evaluaciones = $evaluaciones ?? [];
$idGrupo = $idGrupo ?? 0;
$idEvaluacion = $idEvaluacion ?? 0;
$mensaje = $mensaje ?? null;
$tipoMsg = $tipoMsg ?? null;
?>
<div class="mb-4">
    <h1 class="portal-title mb-1">Ingresar Calificaciones</h1>
    <p class="portal-subtitle mb-0">Registro de evaluaciones, exámenes técnicos y recitales</p>
</div>

<?php if ($mensaje): ?>
    <div class="portal-alert portal-alert--<?= ($tipoMsg === 'success') ? 'exito' : 'peligro' ?> d-flex align-items-center gap-2 mb-4" role="alert">
        <i class="bi bi-<?= ($tipoMsg === 'success') ? 'check-circle' : 'exclamation-circle' ?>-fill"></i>
        <span><?= htmlspecialchars($mensaje) ?></span>
    </div>
<?php endif; ?>

<form method="get" action="/instructor/calificaciones" class="row g-3 mb-4">
    <div class="col-md-5">
        <label class="form-label text-uppercase small" style="color:#94a3b8;">Grupo de Estudio</label>
        <select name="grupo" class="form-select" style="background-color:#0f172a;border:1px solid var(--portal-border);color:#fff;" required>
            <option value="">Seleccionar grupo</option>
            <?php foreach ($grupos as $g): ?>
                <option value="<?= (int)$g['id_grupo'] ?>" <?= (int)$g['id_grupo'] === $idGrupo ? 'selected' : '' ?>><?= htmlspecialchars($g['curso']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-5">
        <label class="form-label text-uppercase small" style="color:#94a3b8;">Evaluación / Examen</label>
        <select name="evaluacion" class="form-select" style="background-color:#0f172a;border:1px solid var(--portal-border);color:#fff;" required>
            <option value="">Seleccionar evaluación</option>
            <?php foreach ($evaluaciones as $ev): ?>
                <option value="<?= (int)$ev['id_evaluacion'] ?>" <?= (int)$ev['id_evaluacion'] === $idEvaluacion ? 'selected' : '' ?>><?= htmlspecialchars($ev['tipo']) ?><?= $ev['fecha_evaluacion'] ? ' (' . date('d/m/Y', strtotime($ev['fecha_evaluacion'])) . ')' : '' ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2 d-flex align-items-end">
        <button class="btn btn-warning w-100 fw-bold">Cargar</button>
    </div>
</form>

<?php if ($idGrupo > 0 && $idEvaluacion > 0 && count($alumnos) > 0): ?>
<form method="post" action="/instructor/calificaciones/guardar">
    <input type="hidden" name="id_grupo" value="<?= (int)$idGrupo ?>">
    <input type="hidden" name="id_evaluacion" value="<?= (int)$idEvaluacion ?>">
    <div class="portal-card p-3 p-md-4">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h2 class="portal-card-title mb-0">Evaluación de Estudiantes</h2>
            <span class="small" style="color:#94a3b8;">Rango de Calificación: 0 - 100 ptos (Mín. Aprobatoria: 70 ptos)</span>
        </div>
        <div class="table-responsive">
            <table class="table table-dark table-borderless align-middle mb-0">
                <thead>
                    <tr style="border-bottom:1px solid var(--portal-border);">
                        <th class="text-uppercase small" style="color:#94a3b8;">Foto</th>
                        <th class="text-uppercase small" style="color:#94a3b8;">Estudiante</th>
                        <th class="text-uppercase small" style="color:#94a3b8;width:160px;">Calificación (0-100)</th>
                        <th class="text-uppercase small" style="color:#94a3b8;">Observaciones / Comentarios</th>
                        <th class="text-uppercase small" style="color:#94a3b8;">Estado Automático</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($alumnos as $a): 
                        $nota10 = $a['calificacion'] !== null ? (float)$a['calificacion'] : null;
                        $nota100 = $nota10 !== null ? round($nota10 * 10) : '';
                        $aprobado = $nota10 !== null && $nota10 >= 7;
                    ?>
                        <tr style="border-bottom:1px solid rgba(255,255,255,0.03);">
                            <td>
                                <div class="portal-avatar" style="width:36px;height:36px;font-size:0.8rem;overflow:hidden;">
                                    <?php if (!empty($a['foto'])): ?>
                                        <img src="<?= htmlspecialchars($a['foto']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                                    <?php else: ?>
                                        <?= htmlspecialchars(mb_strtoupper(mb_substr($a['nombre'],0,1).mb_substr($a['apellido'],0,1))) ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="fw-semibold text-white"><?= htmlspecialchars($a['nombre'].' '.$a['apellido']) ?></td>
                            <td>
                                <input type="number" min="0" max="100" step="1" name="calificacion[<?= (int)$a['id_inscripcion'] ?>]" class="form-control text-center" style="background:#0B0F19;color:#fff;border:1px solid var(--portal-border);" value="<?= htmlspecialchars((string)$nota100) ?>">
                            </td>
                            <td>
                                <input type="text" name="observaciones[<?= (int)$a['id_inscripcion'] ?>]" class="form-control" style="background:#0B0F19;color:#fff;border:1px solid var(--portal-border);" placeholder="Agregar observación..." value="<?= htmlspecialchars($a['observaciones_profesor'] ?? '') ?>">
                            </td>
                            <td>
                                <?php if ($nota10 !== null): ?>
                                    <span class="badge <?= $aprobado ? 'bg-success' : 'bg-danger' ?>"><?= $aprobado ? 'Aprobado' : 'Reprobado' ?></span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Sin calificar</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-end mt-3">
            <button class="btn btn-warning text-dark fw-bold px-4">
                <i class="bi bi-save me-2"></i>GUARDAR CALIFICACIONES
            </button>
        </div>
    </div>
</form>
<?php elseif ($idGrupo > 0): ?>
    <div class="portal-card p-4 text-center"><p class="mb-0" style="color:#94a3b8;">Selecciona una evaluación para registrar calificaciones.</p></div>
<?php endif; ?>
