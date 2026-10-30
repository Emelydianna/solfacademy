<?php
$grupos = $grupos ?? [];
$grupo = $grupo ?? [];
$alumnos = $alumnos ?? [];
$evaluaciones = $evaluaciones ?? [];
$idGrupo = $idGrupo ?? 0;
$claveEvaluacion = $claveEvaluacion ?? '';
$flash = $flash ?? null;

/** Etiqueta legible de una evaluación (tipo + fecha). */
$etiquetaEvaluacion = static function (array $ev): string {
    $fecha = $ev['fecha_evaluacion'] ?? null;
    $label = (string) $ev['tipo'];
    if ($fecha) {
        $ts = strtotime((string) $fecha);
        $label .= ' — ' . ($ts ? date('d/m/Y', $ts) : (string) $fecha);
    }
    return $label;
};

// Clave de la evaluación seleccionada (para mantenerla tras guardar)
$claveActual = $claveEvaluacion;
if ($claveActual === '' && $evaluaciones !== []) {
    $primera = $evaluaciones[0];
    $claveActual = base64_encode((string) $primera['tipo']) . '|' . ($primera['fecha_evaluacion'] ?? '');
}

// Notas ya cargadas en la evaluación actual (para el resumen del formulario)
$calificadas = 0;
foreach ($alumnos as $a) {
    if ($a['calificacion'] !== null) {
        $calificadas++;
    }
}
$totalAlumnos = count($alumnos);
$hayGrupo = $idGrupo > 0 && $grupo !== [];
$puedeCrear = $hayGrupo && $totalAlumnos > 0;
?>
<div class="mb-4">
    <h1 class="portal-title mb-1">Ingresar Calificaciones</h1>
    <p class="portal-subtitle mb-0">Registro de evaluaciones, exámenes técnicos y recitales</p>
</div>

<?php require __DIR__ . '/../../partials/flash.php'; ?>

<form method="get" action="/instructor/calificaciones" class="row g-3 mb-4 align-items-end">
    <div class="col-md-5">
        <label class="form-label text-uppercase small" style="color:#94a3b8;">Grupo de Estudio</label>
        <select name="grupo" id="selectGrupo" class="form-select" style="background-color:#0f172a;border:1px solid var(--portal-border);color:#fff;" required>
            <option value="">Seleccionar grupo</option>
            <?php foreach ($grupos as $g): ?>
                <option value="<?= (int)$g['id_grupo'] ?>" <?= (int)$g['id_grupo'] === $idGrupo ? 'selected' : '' ?>><?= htmlspecialchars($g['curso']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label text-uppercase small" style="color:#94a3b8;">Evaluación / Examen</label>
        <select name="evaluacion" class="form-select" style="background-color:#0f172a;border:1px solid var(--portal-border);color:#fff;" <?= $evaluaciones === [] ? 'disabled' : '' ?>>
            <?php if ($evaluaciones === []): ?>
                <option value="">Sin evaluaciones</option>
            <?php else: ?>
                <?php foreach ($evaluaciones as $ev):
                    $clave = base64_encode((string) $ev['tipo']) . '|' . ($ev['fecha_evaluacion'] ?? '');
                ?>
                    <option value="<?= htmlspecialchars($clave) ?>" <?= $clave === $claveActual ? 'selected' : '' ?>>
                        <?= htmlspecialchars($etiquetaEvaluacion($ev)) ?>
                    </option>
                <?php endforeach; ?>
            <?php endif; ?>
        </select>
    </div>
    <div class="col-md-3 d-flex gap-2">
        <button class="btn btn-warning fw-bold flex-grow-1" <?= $evaluaciones === [] ? 'disabled' : '' ?>>Cargar</button>
        <button type="button" class="btn btn-outline-warning fw-bold" data-bs-toggle="modal" data-bs-target="#modalNuevaEvaluacion" <?= $puedeCrear ? '' : 'disabled' ?>>
            <i class="bi bi-plus-lg"></i>
        </button>
    </div>
</form>

<?php if ($hayGrupo && $evaluaciones === []): ?>
    <div class="portal-card p-4 text-center mb-4">
        <p class="mb-2" style="color:#94a3b8;">Este grupo todavía no tiene evaluaciones creadas.</p>
        <?php if ($puedeCrear): ?>
            <button class="btn btn-warning fw-bold" data-bs-toggle="modal" data-bs-target="#modalNuevaEvaluacion">
                <i class="bi bi-plus-lg me-1"></i>Crear la primera evaluación
            </button>
        <?php else: ?>
            <p class="mb-0 small" style="color:#94a3b8;">No hay alumnos activos en el grupo, no se pueden crear evaluaciones.</p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($hayGrupo && $evaluaciones !== [] && $totalAlumnos > 0): ?>
<form method="post" action="/instructor/calificaciones/guardar">
    <?= \App\Application\Security\Csrf::campo() ?>
    <input type="hidden" name="id_grupo" value="<?= (int)$idGrupo ?>">
    <input type="hidden" name="evaluacion" value="<?= htmlspecialchars($claveActual) ?>">
    <div class="portal-card p-3 p-md-4">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h2 class="portal-card-title mb-0">Evaluación de Estudiantes</h2>
            <span class="small" style="color:#94a3b8;">
                Rango de Calificación: 0 - 100 ptos (Mín. Aprobatoria: 70 ptos)
                · <?= $calificadas ?>/<?= $totalAlumnos ?> calificada(s)
            </span>
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
                            <td>
                                <a href="/instructor/alumnos/<?= (int)$a['id_estudiante'] ?>/perfil"
                                   class="fw-semibold text-white text-decoration-none">
                                    <?= htmlspecialchars($a['nombre'].' '.$a['apellido']) ?>
                                    <i class="bi bi-box-arrow-up-right small ms-1" style="color:#94a3b8;"></i>
                                </a>
                            </td>
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
<?php elseif ($hayGrupo && $evaluaciones !== []): ?>
    <div class="portal-card p-4 text-center"><p class="mb-0" style="color:#94a3b8;">No hay alumnos activos en este grupo.</p></div>
<?php endif; ?>

<!-- Modal: nueva evaluación para todo el grupo -->
<div class="modal fade" id="modalNuevaEvaluacion" tabindex="-1" aria-labelledby="modalNuevaEvaluacionLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" action="/instructor/calificaciones/crear" class="modal-content" style="background:#0f172a;border:1px solid var(--portal-border);color:#fff;">
            <?= \App\Application\Security\Csrf::campo() ?>
            <div class="modal-header" style="border-bottom:1px solid var(--portal-border);">
                <h2 class="modal-title fs-5" id="modalNuevaEvaluacionLabel">Nueva evaluación</h2>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id_grupo" value="<?= (int)$idGrupo ?>">

                <div class="mb-3">
                    <label class="form-label text-uppercase small" style="color:#94a3b8;">Grupo</label>
                    <input type="text" class="form-control" style="background:#0B0F19;color:#fff;border:1px solid var(--portal-border);"
                           value="<?= htmlspecialchars((string)($grupo['curso'] ?? '')) ?>" disabled>
                    <div class="form-text" style="color:#94a3b8;">Se creará para los <?= $totalAlumnos ?> alumno(s) activo(s) del grupo.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label text-uppercase small" style="color:#94a3b8;">Tipo de evaluación *</label>
                    <input type="text" class="form-control" name="tipo" maxlength="50" required
                           style="background:#0B0F19;color:#fff;border:1px solid var(--portal-border);"
                           placeholder="Parcial de Solfeo, Práctica de Repertorio..." autofocus>
                </div>

                <div class="mb-3">
                    <label class="form-label text-uppercase small" style="color:#94a3b8;">Fecha</label>
                    <input type="date" class="form-control" name="fecha_evaluacion"
                           style="background:#0B0F19;color:#fff;border:1px solid var(--portal-border);"
                           value="<?= date('Y-m-d') ?>">
                    <div class="form-text" style="color:#94a3b8;">Usa la misma fecha si vas a repetir el nombre de una evaluación.</div>
                </div>

                <div class="mb-0">
                    <label class="form-label text-uppercase small" style="color:#94a3b8;">Descripción</label>
                    <input type="text" class="form-control" name="descripcion" maxlength="255"
                           style="background:#0B0F19;color:#fff;border:1px solid var(--portal-border);"
                           placeholder="Contenido, alcance o indicaciones...">
                </div>
            </div>
            <div class="modal-footer" style="border-top:1px solid var(--portal-border);">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-warning text-dark fw-bold">Crear evaluación</button>
            </div>
        </form>
    </div>
</div>
