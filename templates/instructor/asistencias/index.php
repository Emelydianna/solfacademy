<?php
$grupos = $grupos ?? [];
$grupo = $grupo ?? [];
$alumnos = $alumnos ?? [];
$fecha = $fecha ?? date('Y-m-d');
$idGrupo = $idGrupo ?? 0;
$mensaje = $mensaje ?? null;
$tipoMsg = $tipoMsg ?? null;
$hoy = date('Y-m-d');
$esHoy = $fecha === $hoy;
?>
<div class="mb-4">
    <h1 class="portal-title mb-1">Registrar Asistencia</h1>
    <p class="portal-subtitle mb-0">Control diario de asistencia para clases individuales y de ensamble</p>
</div>

<?php if ($mensaje): ?>
    <div class="portal-alert portal-alert--<?= ($tipoMsg === 'success') ? 'exito' : 'peligro' ?> d-flex align-items-center gap-2 mb-4" role="alert">
        <i class="bi bi-<?= ($tipoMsg === 'success') ? 'check-circle' : 'exclamation-circle' ?>-fill"></i>
        <span><?= htmlspecialchars($mensaje) ?></span>
    </div>
<?php endif; ?>

<form method="get" action="/instructor/asistencias" class="row g-3 mb-4 align-items-end">
    <div class="col-lg-6">
        <label class="form-label text-uppercase small" style="color:#94a3b8;">Grupo de Estudio</label>
        <select name="grupo" class="form-select" style="background-color:#0f172a;border:1px solid var(--portal-border);color:#fff;" required>
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
    <div class="col-lg-4">
        <label class="form-label text-uppercase small" style="color:#94a3b8;">Fecha de Clase</label>
        <div class="d-flex align-items-center gap-2">
            <input type="date" name="fecha" class="form-control" style="background-color:#0f172a;border:1px solid var(--portal-border);color:#fff;" value="<?= htmlspecialchars($fecha) ?>" required>
            <?php if ($esHoy): ?>
                <span class="badge d-none d-md-inline-flex align-items-center gap-1" style="background:rgba(52,211,153,0.15);color:#34d399;border:1px solid rgba(52,211,153,0.4);">
                    <span style="width:6px;height:6px;border-radius:50%;background:#34d399;display:inline-block;"></span>
                    Clase en curso
                </span>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-2">
        <button class="btn btn-warning w-100 fw-bold">Cargar</button>
    </div>
</form>

<?php if ($idGrupo > 0 && count($alumnos) > 0): ?>
<form method="post" action="/instructor/asistencias/guardar">
    <input type="hidden" name="id_grupo" value="<?= $idGrupo ?>">
    <input type="hidden" name="fecha" value="<?= htmlspecialchars($fecha) ?>">
    <div class="portal-card p-3 p-md-4">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h2 class="portal-card-title mb-0">Listado de Alumnos</h2>
            <span class="small" style="color:#94a3b8;">Total: <?= count($alumnos) ?> alumnos inscritos</span>
        </div>
        <div class="table-responsive">
            <table class="table table-dark table-borderless align-middle mb-0">
                <thead>
                    <tr style="border-bottom:1px solid var(--portal-border);">
                        <th class="text-uppercase small" style="color:#94a3b8;">Foto</th>
                        <th class="text-uppercase small" style="color:#94a3b8;">Nombre Completo</th>
                        <th class="text-uppercase small" style="color:#94a3b8;">Código</th>
                        <th class="text-uppercase small" style="color:#94a3b8;">Estado de Asistencia</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($alumnos as $a): 
                        $idIns = (int)$a['id_inscripcion'];
                        $presente = $a['presente'] ?? null;
                        $justificacion = $a['justificacion'] ?? null;
                        $esPresente = $presente === 'S' && $justificacion === null;
                        $esAusente = $presente === 'N' && $justificacion === null;
                        $esJustificado = $justificacion === 'Justificado';
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
                            <td class="fw-semibold"><?= htmlspecialchars($a['nombre'].' '.$a['apellido']) ?></td>
                            <td style="color:#cbd5e1;">EST-<?= str_pad((string)$a['id_estudiante'], 6, '0', STR_PAD_LEFT) ?></td>
                            <td>
                                <div class="d-flex flex-wrap gap-2">
                                    <input type="radio" class="btn-check" name="asistencia[<?= $idIns ?>]" id="p<?= $idIns ?>" value="presente" <?= $esPresente ? 'checked' : '' ?>>
                                    <label class="btn btn-sm px-3 rounded-pill <?= $esPresente ? 'btn-success' : 'btn-outline-success' ?>" for="p<?= $idIns ?>">
                                        <span class="me-1">●</span>Presente
                                    </label>
                                    <input type="radio" class="btn-check" name="asistencia[<?= $idIns ?>]" id="a<?= $idIns ?>" value="ausente" <?= $esAusente ? 'checked' : '' ?>>
                                    <label class="btn btn-sm px-3 rounded-pill <?= $esAusente ? 'btn-danger' : 'btn-outline-danger' ?>" for="a<?= $idIns ?>">
                                        Ausente
                                    </label>
                                    <input type="radio" class="btn-check" name="asistencia[<?= $idIns ?>]" id="j<?= $idIns ?>" value="justificado" <?= $esJustificado ? 'checked' : '' ?>>
                                    <label class="btn btn-sm px-3 rounded-pill <?= $esJustificado ? 'btn-warning text-dark' : 'btn-outline-warning' ?>" for="j<?= $idIns ?>">
                                        Justificado
                                    </label>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-end mt-4">
            <button class="btn btn-warning fw-bold px-4 py-2">
                <i class="bi bi-save me-2"></i>GUARDAR REGISTRO DE ASISTENCIA
            </button>
        </div>
    </div>
</form>
<?php elseif ($idGrupo > 0): ?>
    <div class="portal-card p-4 text-center"><p class="mb-0" style="color:#94a3b8;">No hay alumnos inscritos en este grupo.</p></div>
<?php endif; ?>
