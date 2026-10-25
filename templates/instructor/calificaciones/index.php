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
    <div class="alert alert-<?= $tipoMsg === 'success' ? 'success' : 'danger' ?>"><?= htmlspecialchars($mensaje) ?></div>
<?php endif; ?>

<form method="get" action="/instructor/calificaciones" class="row g-3 mb-4">
    <div class="col-md-5">
        <label class="form-label">GRUPO DE ESTUDIO</label>
        <select name="grupo" class="form-select" required>
            <option value="">Seleccionar grupo</option>
            <?php foreach ($grupos as $g): ?>
                <option value="<?= (int)$g['id_grupo'] ?>" <?= (int)$g['id_grupo'] === $idGrupo ? 'selected' : '' ?>><?= htmlspecialchars($g['curso']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-5">
        <label class="form-label">EVALUACIÓN / EXAMEN</label>
        <select name="evaluacion" class="form-select" required>
            <option value="">Seleccionar evaluación</option>
            <?php foreach ($evaluaciones as $ev): ?>
                <option value="<?= (int)$ev['id_evaluacion'] ?>" <?= (int)$ev['id_evaluacion'] === $idEvaluacion ? 'selected' : '' ?>><?= htmlspecialchars($ev['tipo']) ?><?= $ev['fecha_evaluacion'] ? ' (' . date('d/m/Y', strtotime($ev['fecha_evaluacion'])) . ')' : '' ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2 d-flex align-items-end">
        <button class="btn btn-warning w-100">Cargar</button>
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
                        <th>FOTO</th>
                        <th>ESTUDIANTE</th>
                        <th style="width:160px;">CALIFICACIÓN (0-100)</th>
                        <th>OBSERVACIONES / COMENTARIOS</th>
                        <th>ESTADO AUTOMÁTICO</th>
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
                                <div class="portal-avatar" style="width:36px;height:36px;font-size:0.8rem;"><?= htmlspecialchars(mb_strtoupper(mb_substr($a['nombre'],0,1).mb_substr($a['apellido'],0,1))) ?></div>
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
            <button class="btn btn-warning text-dark fw-bold px-4">GUARDAR CALIFICACIONES</button>
        </div>
    </div>
</form>
<?php elseif ($idGrupo > 0): ?>
    <div class="portal-card p-4 text-center"><p class="mb-0">Selecciona una evaluación para registrar calificaciones.</p></div>
<?php endif; ?>
