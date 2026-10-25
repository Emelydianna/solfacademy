<?php
$grupos = $grupos ?? [];
$grupo = $grupo ?? [];
$alumnos = $alumnos ?? [];
$fecha = $fecha ?? date('Y-m-d');
$idGrupo = $idGrupo ?? 0;
$mensaje = $mensaje ?? null;
$tipoMsg = $tipoMsg ?? null;
?>
<div class="mb-4">
    <h1 class="portal-title mb-1">Registrar Asistencia</h1>
    <p class="portal-subtitle mb-0">Control diario de asistencia para clases individuales y de ensamble</p>
</div>

<?php if ($mensaje): ?>
    <div class="alert alert-<?= $tipoMsg === 'success' ? 'success' : 'danger' ?>"><?= htmlspecialchars($mensaje) ?></div>
<?php endif; ?>

<form method="get" action="/instructor/asistencias" class="row g-3 mb-4">
    <div class="col-md-6">
        <label class="form-label">GRUPO DE ESTUDIO</label>
        <select name="grupo" class="form-select" required>
            <option value="">Seleccionar grupo</option>
            <?php foreach ($grupos as $g): 
                $dias = $g['dias'] ?? '';
                $nivel = $g['nivel'] ?? '';
                $label = $g['curso'] ?? '';
                if ($nivel && stripos($label, $nivel) === false) {
                    $label .= ' - ' . $nivel;
                }
                if ($dias) {
                    $label .= ' (' . $dias . ')';
                }
            ?>
                <option value="<?= (int)$g['id_grupo'] ?>" <?= (int)$g['id_grupo'] === $idGrupo ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">FECHA DE CLASE</label>
        <input type="date" name="fecha" class="form-control" value="<?= htmlspecialchars($fecha) ?>" required>
    </div>
    <div class="col-md-2 d-flex align-items-end">
        <button class="btn btn-warning w-100">Cargar</button>
    </div>
</form>

<?php if ($idGrupo > 0 && count($alumnos) > 0): ?>
<form method="post" action="/instructor/asistencias/guardar">
    <input type="hidden" name="id_grupo" value="<?= $idGrupo ?>">
    <input type="hidden" name="fecha" value="<?= htmlspecialchars($fecha) ?>">
    <div class="portal-card p-3 p-md-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="portal-card-title mb-0">Estudiantes Matriculados</h2>
        </div>
        <div class="table-responsive">
            <table class="table table-dark table-borderless align-middle mb-0">
                <thead>
                    <tr style="border-bottom:1px solid var(--portal-border);">
                        <th>FOTO</th>
                        <th>NOMBRE COMPLETO</th>
                        <th>CÓDIGO</th>
                        <th>ESTADO DE ASISTENCIA</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($alumnos as $a): ?>
                        <tr style="border-bottom:1px solid rgba(255,255,255,0.03);">
                            <td>
                                <div class="portal-avatar" style="width:36px;height:36px;font-size:0.8rem;"><?= htmlspecialchars(mb_strtoupper(mb_substr($a['nombre'],0,1).mb_substr($a['apellido'],0,1))) ?></div>
                            </td>
                            <td class="fw-semibold"><?= htmlspecialchars($a['nombre'].' '.$a['apellido']) ?></td>
                            <td style="color:#94a3b8;">EST-<?= str_pad((string)$a['id_estudiante'], 6, '0', STR_PAD_LEFT) ?></td>
                            <td>
                                <div class="d-flex gap-2 flex-wrap">
                                    <input type="radio" class="btn-check" name="asistencia[<?= (int)$a['id_inscripcion'] ?>]" id="p<?= (int)$a['id_inscripcion'] ?>" value="presente" <?= ($a['presente'] ?? null) === 'S' && ($a['justificacion'] ?? null) === null ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-success btn-sm" for="p<?= (int)$a['id_inscripcion'] ?>">Presente</label>
                                    <input type="radio" class="btn-check" name="asistencia[<?= (int)$a['id_inscripcion'] ?>]" id="a<?= (int)$a['id_inscripcion'] ?>" value="ausente" <?= ($a['presente'] ?? null) === 'N' && ($a['justificacion'] ?? null) === null ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-danger btn-sm" for="a<?= (int)$a['id_inscripcion'] ?>">Ausente</label>
                                    <input type="radio" class="btn-check" name="asistencia[<?= (int)$a['id_inscripcion'] ?>]" id="j<?= (int)$a['id_inscripcion'] ?>" value="justificado" <?= ($a['justificacion'] ?? null) === 'Justificado' ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-warning btn-sm" for="j<?= (int)$a['id_inscripcion'] ?>">Justificado</label>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-end mt-3">
            <button class="btn btn-warning fw-bold px-4">GUARDAR REGISTRO DE ASISTENCIA</button>
        </div>
    </div>
</form>
<?php elseif ($idGrupo > 0): ?>
    <div class="portal-card p-4 text-center"><p class="mb-0">No hay alumnos inscritos en este grupo.</p></div>
<?php endif; ?>
