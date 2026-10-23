<!-- Asistencias e Historial -->
<?php
/**
 * Asistencias (pantalla 5 del prototipo).
 * Datos: $datos = EstudiantePortalService::asistencias($id, $filtros)
 *   - resumen: total_clases, presentes, ausentes, justificadas,
 *              injustificadas, porcentaje
 *   - cursos:  [{id_curso, curso}]
 *   - detalle: [{id_asistencia, fecha_clase, presente, justificacion,
 *                observaciones, id_curso, curso, instructor, estado_inscripcion}]
 * $filtros: {curso, estado, rango, desde, hasta}
 */
$datos   = $datos ?? ['resumen' => [], 'cursos' => [], 'detalle' => []];
$filtros = $filtros ?? ['curso' => '', 'estado' => '', 'rango' => '', 'desde' => '', 'hasta' => ''];
$resumen = $datos['resumen'] ?? [];

$total     = (int) ($resumen['total_clases'] ?? 0);
$presentes = (int) ($resumen['presentes'] ?? 0);
$pct       = (float) ($resumen['porcentaje'] ?? 0.0);

$hayFiltro = ($filtros['curso'] ?? '') !== '' || ($filtros['estado'] ?? '') !== ''
    || ($filtros['rango'] ?? '') !== '';

$rangos = [
    ''    => 'Todo el historial',
    '7'   => 'Últimos 7 días',
    '30'  => 'Últimos 30 días',
    '90'  => 'Últimos 90 días',
    '365' => 'Último año',
];
?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="portal-title mb-1">Asistencias e Historial</h1>
        <p class="portal-subtitle mb-0">Lleva el control detallado de tus asistencias a clases y justificaciones.</p>
    </div>

    <?php if ($total > 0): ?>
        <div class="d-flex flex-wrap gap-2">
            <div class="portal-card kpi-compact">
                <span class="portal-label">Asistencia total</span>
                <span class="kpi-compact-value kpi-compact-value--verde">
                    <?= number_format($pct, 1, ',', '.') ?>%
                </span>
            </div>
            <div class="portal-card kpi-compact">
                <span class="portal-label">Clases asistidas</span>
                <span class="kpi-compact-value">
                    <?= $presentes ?> <small>/ <?= $total ?></small>
                </span>
            </div>
        </div>
    <?php endif; ?>
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

    <!-- Barra de filtros -->
    <div class="portal-card p-3 p-md-4 mb-3">
        <form method="get" action="/estudiante/asistencias" class="row g-3 align-items-end">
            <div class="col-12 col-md-4">
                <label class="portal-label d-block mb-1" for="filtroCurso">Curso</label>
                <select class="form-select portal-input" id="filtroCurso" name="curso">
                    <option value="">Todos los Cursos</option>
                    <?php foreach ($datos['cursos'] as $curso): ?>
                        <option value="<?= (int) $curso['id_curso'] ?>"
                            <?= (string) $filtros['curso'] === (string) $curso['id_curso'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($curso['curso']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 col-md-3">
                <label class="portal-label d-block mb-1" for="filtroRango">Rango</label>
                <select class="form-select portal-input" id="filtroRango" name="rango">
                    <?php foreach ($rangos as $valor => $etiqueta): ?>
                        <option value="<?= htmlspecialchars($valor) ?>"
                            <?= (string) $filtros['rango'] === (string) $valor ? 'selected' : '' ?>>
                            <?= htmlspecialchars($etiqueta) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 col-md-3">
                <label class="portal-label d-block mb-1" for="filtroEstado">Estado</label>
                <select class="form-select portal-input" id="filtroEstado" name="estado">
                    <option value="">Cualquiera</option>
                    <option value="presente" <?= $filtros['estado'] === 'presente' ? 'selected' : '' ?>>Presente</option>
                    <option value="ausente" <?= $filtros['estado'] === 'ausente' ? 'selected' : '' ?>>Ausente</option>
                </select>
            </div>

            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-accent fw-semibold flex-grow-1">
                    <i class="bi bi-funnel me-1"></i>Filtrar
                </button>
                <?php if ($hayFiltro): ?>
                    <a href="/estudiante/asistencias" class="btn btn-outline-secondary" title="Limpiar filtros">
                        <i class="bi bi-x-lg"></i>
                    </a>
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
                            <th>Instructor</th>
                            <th>Estado</th>
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
                                <td><?= htmlspecialchars($fila['instructor']) ?></td>
                                <td>
                                    <?php if ($presente): ?>
                                        <span class="estado-badge estado-badge--presente">Presente</span>
                                    <?php elseif ($justificada): ?>
                                        <span class="estado-badge estado-badge--justificado">Justificado</span>
                                        <span class="d-block small mt-1" style="color: var(--portal-muted);">
                                            <?= htmlspecialchars($fila['justificacion']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="estado-badge estado-badge--ausente">Ausente</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

<?php endif; ?>
