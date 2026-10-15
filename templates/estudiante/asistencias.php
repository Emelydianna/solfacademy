<!-- Asistencias e Historial -->
<?php
/**
 * Asistencias (pantalla 5 del prototipo).
 * Datos: $datos = EstudiantePortalService::asistencias($id, $filtros)
 *   - resumen: total_clases, presentes, ausentes, justificadas,
 *              injustificadas, porcentaje
 *   - cursos:  [{id_curso, curso}]
 *   - detalle: [{id_asistencia, fecha_clase, presente, justificacion,
 *                observaciones, id_curso, curso, estado_inscripcion}]
 * $filtros: {curso, estado, desde, hasta}
 */
$datos   = $datos ?? ['resumen' => [], 'cursos' => [], 'detalle' => []];
$filtros = $filtros ?? ['curso' => '', 'estado' => '', 'desde' => '', 'hasta' => ''];
$resumen = $datos['resumen'] ?? [];

$total   = (int) ($resumen['total_clases'] ?? 0);
$pct     = (float) ($resumen['porcentaje'] ?? 0.0);

// Color del porcentaje según el umbral
$clasePct = $pct >= 90 ? 'portal-pill--verde' : ($pct >= 75 ? 'portal-pill--dorado' : 'portal-pill--rojo');

$hayFiltro = ($filtros['curso'] ?? '') !== '' || ($filtros['estado'] ?? '') !== ''
    || ($filtros['desde'] ?? '') !== '' || ($filtros['hasta'] ?? '') !== '';
?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="portal-title mb-1">Asistencias</h1>
        <p class="portal-subtitle mb-0">Registro detallado de tu puntualidad y presencia en las clases.</p>
    </div>
    <span class="portal-pill <?= $clasePct ?>"><?= number_format($pct, 1, ',', '.') ?>% asistencia</span>
</div>

<?php if ($total === 0): ?>
    <div class="portal-card p-4 text-center">
        <i class="bi bi-check2-square fs-1 d-block mb-2" style="color: var(--portal-muted);"></i>
        <p class="mb-1 fw-semibold">Aún no hay registros de asistencia</p>
        <p class="small text-secondary mb-0">
            Tu instructor irá registrando tu presencia a medida que avancen las clases.
        </p>
    </div>

<?php else: ?>

    <!-- Resumen -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <div class="portal-card portal-kpi h-100 p-3">
                <div class="d-flex justify-content-between align-items-start">
                    <span class="portal-label">Clases totales</span>
                    <span class="portal-kpi-icon"><i class="bi bi-calendar-check"></i></span>
                </div>
                <div class="portal-kpi-value"><?= $total ?></div>
                <span class="portal-kpi-note">Registradas por tu instructor</span>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="portal-card portal-kpi h-100 p-3">
                <div class="d-flex justify-content-between align-items-start">
                    <span class="portal-label">Asistencias</span>
                    <span class="portal-kpi-icon portal-kpi-icon--verde"><i class="bi bi-check2-circle"></i></span>
                </div>
                <div class="portal-kpi-value"><?= (int) ($resumen['presentes'] ?? 0) ?></div>
                <span class="portal-kpi-note">Clases presentadas</span>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="portal-card portal-kpi h-100 p-3">
                <div class="d-flex justify-content-between align-items-start">
                    <span class="portal-label">Justificadas</span>
                    <span class="portal-kpi-icon portal-kpi-icon--azul"><i class="bi bi-file-earmark-text"></i></span>
                </div>
                <div class="portal-kpi-value"><?= (int) ($resumen['justificadas'] ?? 0) ?></div>
                <span class="portal-kpi-note">Ausencias con motivo</span>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="portal-card portal-kpi h-100 p-3">
                <div class="d-flex justify-content-between align-items-start">
                    <span class="portal-label">Sin justificar</span>
                    <span class="portal-kpi-icon portal-kpi-icon--rosa"><i class="bi bi-x-octagon"></i></span>
                </div>
                <div class="portal-kpi-value"><?= (int) ($resumen['injustificadas'] ?? 0) ?></div>
                <span class="portal-kpi-note">Ausencias no avisadas</span>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="portal-card p-3 p-md-4 mb-3">
        <form method="get" action="/estudiante/asistencias" class="row g-3 align-items-end">
            <div class="col-12 col-md-3">
                <label class="portal-label d-block mb-1" for="filtroCurso">Curso</label>
                <select class="form-select portal-input" id="filtroCurso" name="curso">
                    <option value="">Todos los cursos</option>
                    <?php foreach ($datos['cursos'] as $curso): ?>
                        <option value="<?= (int) $curso['id_curso'] ?>"
                            <?= (string) $filtros['curso'] === (string) $curso['id_curso'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($curso['curso']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 col-md-2">
                <label class="portal-label d-block mb-1" for="filtroEstado">Estado</label>
                <select class="form-select portal-input" id="filtroEstado" name="estado">
                    <option value="">Todos</option>
                    <option value="presente" <?= $filtros['estado'] === 'presente' ? 'selected' : '' ?>>Presente</option>
                    <option value="ausente" <?= $filtros['estado'] === 'ausente' ? 'selected' : '' ?>>Ausente</option>
                </select>
            </div>

            <div class="col-6 col-md-2">
                <label class="portal-label d-block mb-1" for="filtroDesde">Desde</label>
                <input type="date" class="form-control portal-input" id="filtroDesde" name="desde"
                       value="<?= htmlspecialchars($filtros['desde']) ?>">
            </div>

            <div class="col-6 col-md-2">
                <label class="portal-label d-block mb-1" for="filtroHasta">Hasta</label>
                <input type="date" class="form-control portal-input" id="filtroHasta" name="hasta"
                       value="<?= htmlspecialchars($filtros['hasta']) ?>">
            </div>

            <div class="col-12 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-accent fw-semibold flex-grow-1">
                    <i class="bi bi-funnel me-1"></i>Filtrar
                </button>
                <?php if ($hayFiltro): ?>
                    <a href="/estudiante/asistencias" class="btn btn-outline-secondary">Limpiar</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Historial -->
    <div class="portal-card p-3 p-md-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="portal-card-title mb-0">Historial de asistencias</h2>
            <span class="portal-pill"><?= count($datos['detalle']) ?> registro(s)</span>
        </div>

        <?php if (count($datos['detalle']) === 0): ?>
            <p class="small text-secondary mb-0 text-center py-3">
                No hay registros que coincidan con los filtros seleccionados.
            </p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle portal-tabla mb-0">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Curso</th>
                            <th>Estado</th>
                            <th>Justificación</th>
                            <th>Observaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($datos['detalle'] as $fila): ?>
                            <?php
                            $presente = $fila['presente'] === 'S';
                            $justificada = !$presente && !empty($fila['justificacion']);
                            ?>
                            <tr>
                                <td class="text-nowrap">
                                    <?= date('d/m/Y', strtotime((string) $fila['fecha_clase'])) ?>
                                </td>
                                <td><?= htmlspecialchars($fila['curso']) ?></td>
                                <td>
                                    <?php if ($presente): ?>
                                        <span class="portal-pill portal-pill--verde">Presente</span>
                                    <?php elseif ($justificada): ?>
                                        <span class="portal-pill portal-pill--dorado">Justificado</span>
                                    <?php else: ?>
                                        <span class="portal-pill portal-pill--rojo">Ausente</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small" style="color: var(--portal-muted);">
                                    <?= !empty($fila['justificacion']) ? htmlspecialchars($fila['justificacion']) : '—' ?>
                                </td>
                                <td class="small" style="color: var(--portal-muted);">
                                    <?= !empty($fila['observaciones']) ? htmlspecialchars($fila['observaciones']) : '—' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

<?php endif; ?>
