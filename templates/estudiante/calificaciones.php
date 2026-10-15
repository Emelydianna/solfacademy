<!-- Calificaciones y Evaluaciones -->
<?php
/**
 * Calificaciones (pantalla 6 del prototipo).
 * Datos: $datos = EstudiantePortalService::calificaciones($id)
 *   - resumen: cursos_evaluados, evaluaciones_total, promedio_global, notas_evaluadas
 *   - grupos:  [{id_inscripcion, id_curso, curso, instructor, estado, fecha_fin,
 *                total_evaluaciones, promedio, minima, maxima, sin_nota}]
 *   - detalle: [{id_evaluacion, id_inscripcion, tipo, fecha_evaluacion,
 *                calificacion, descripcion, observaciones_profesor, curso, ...}]
 */
$datos   = $datos ?? ['resumen' => [], 'grupos' => [], 'detalle' => []];
$resumen = $datos['resumen'] ?? [];
$grupos  = $datos['grupos'] ?? [];
$detalle = $datos['detalle'] ?? [];

$promedioGlobal = (float) ($resumen['promedio_global'] ?? 0.0);

/** Calificación textual a partir de la escala 0-10 */
$calificar = static function (?float $nota): string {
    if ($nota === null) {
        return 'Sin nota';
    }
    if ($nota >= 9) {
        return 'Sobresaliente';
    }
    if ($nota >= 7) {
        return 'Notable';
    }
    if ($nota >= 5) {
        return 'Aprobado';
    }
    return 'En progreso';
};

/** Clase de color según la nota (verde / dorado / rojo) */
$colorNota = static function (?float $nota): string {
    if ($nota === null) {
        return 'portal-pill--azul';
    }
    if ($nota >= 7) {
        return 'portal-pill--verde';
    }
    if ($nota >= 5) {
        return 'portal-pill--dorado';
    }
    return 'portal-pill--rojo';
};

/** Agrupa el detalle por inscripción para el desplegable de cada curso */
$porInscripcion = [];
foreach ($detalle as $fila) {
    $porInscripcion[(int) $fila['id_inscripcion']][] = $fila;
}
?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="portal-title mb-1">Calificaciones</h1>
        <p class="portal-subtitle mb-0">Notas, promedios y observaciones de tus evaluaciones.</p>
    </div>
    <?php if ((int) ($resumen['evaluaciones_total'] ?? 0) > 0): ?>
        <span class="portal-pill portal-pill--dorado">
            Promedio global: <?= number_format($promedioGlobal, 2, ',', '.') ?>/10
        </span>
    <?php endif; ?>
</div>

<?php if (count($grupos) === 0): ?>
    <div class="portal-card p-4 text-center">
        <i class="bi bi-clipboard-data fs-1 d-block mb-2" style="color: var(--portal-muted);"></i>
        <p class="mb-1 fw-semibold">Aún no tienes evaluaciones registradas</p>
        <p class="small text-secondary mb-0">
            Tus calificaciones aparecerán aquí cuando tu instructor registre las primeras notas.
        </p>
    </div>

<?php else: ?>

    <!-- Resumen -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <div class="portal-card portal-kpi h-100 p-3">
                <div class="d-flex justify-content-between align-items-start">
                    <span class="portal-label">Promedio general</span>
                    <span class="portal-kpi-icon portal-kpi-icon--rosa"><i class="bi bi-trophy"></i></span>
                </div>
                <div class="portal-kpi-value"><?= number_format($promedioGlobal, 1) ?><small>/10</small></div>
                <span class="portal-kpi-note"><?= htmlspecialchars($calificar($promedioGlobal)) ?></span>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="portal-card portal-kpi h-100 p-3">
                <div class="d-flex justify-content-between align-items-start">
                    <span class="portal-label">Cursos evaluados</span>
                    <span class="portal-kpi-icon portal-kpi-icon--verde"><i class="bi bi-journal-check"></i></span>
                </div>
                <div class="portal-kpi-value"><?= (int) ($resumen['cursos_evaluados'] ?? 0) ?></div>
                <span class="portal-kpi-note">Con al menos una nota</span>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="portal-card portal-kpi h-100 p-3">
                <div class="d-flex justify-content-between align-items-start">
                    <span class="portal-label">Evaluaciones</span>
                    <span class="portal-kpi-icon portal-kpi-icon--azul"><i class="bi bi-clipboard-check"></i></span>
                </div>
                <div class="portal-kpi-value"><?= (int) ($resumen['evaluaciones_total'] ?? 0) ?></div>
                <span class="portal-kpi-note">Registradas por tus instructores</span>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="portal-card portal-kpi h-100 p-3">
                <div class="d-flex justify-content-between align-items-start">
                    <span class="portal-label">Notas puestas</span>
                    <span class="portal-kpi-icon"><i class="bi bi-123"></i></span>
                </div>
                <div class="portal-kpi-value"><?= (int) ($resumen['notas_evaluadas'] ?? 0) ?></div>
                <span class="portal-kpi-note">Resto pendiente de calificar</span>
            </div>
        </div>
    </div>

    <!-- Boletín por curso -->
    <h2 class="portal-card-title mb-3">Boletín por curso</h2>
    <div class="row g-3 mb-4">
        <?php foreach ($grupos as $grupo): ?>
            <div class="col-md-6 col-xl-4">
                <div class="portal-card p-4 h-100">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <div class="portal-label mb-1"><?= htmlspecialchars($grupo['instructor']) ?></div>
                            <h3 class="portal-card-title mb-0" style="font-size: 1rem;"><?= htmlspecialchars($grupo['curso']) ?></h3>
                        </div>
                        <span class="portal-pill <?= $colorNota($grupo['promedio'] > 0 ? $grupo['promedio'] : null) ?>">
                            <?= htmlspecialchars($calificar($grupo['promedio'] > 0 ? $grupo['promedio'] : null)) ?>
                        </span>
                    </div>

                    <div class="d-flex align-items-baseline gap-2 my-3">
                        <span class="portal-kpi-value" style="font-size: 2.2rem;">
                            <?= $grupo['total_evaluaciones'] > 0 && $grupo['sin_nota'] < $grupo['total_evaluaciones']
                                ? number_format($grupo['promedio'], 1)
                                : '—' ?>
                            <small style="font-size: 1rem;">/10</small>
                        </span>
                        <span class="portal-label mb-0">
                            <?= (int) $grupo['total_evaluaciones'] ?> evaluación(es)
                        </span>
                    </div>

                    <div class="d-flex justify-content-between small" style="color: var(--portal-muted);">
                        <span>Mínima: <?= $grupo['minima'] !== null ? number_format((float) $grupo['minima'], 1) : '—' ?></span>
                        <span>Máxima: <?= $grupo['maxima'] !== null ? number_format((float) $grupo['maxima'], 1) : '—' ?></span>
                    </div>

                    <?php if ((int) $grupo['sin_nota'] > 0): ?>
                        <div class="small mt-2" style="color: var(--portal-muted);">
                            <i class="bi bi-hourglass-split me-1"></i>
                            <?= (int) $grupo['sin_nota'] ?> pendiente(s) de calificar
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Historial de evaluaciones -->
    <div class="portal-card p-3 p-md-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="portal-card-title mb-0">Historial de evaluaciones</h2>
            <span class="portal-pill"><?= count($detalle) ?> registro(s)</span>
        </div>

        <?php if (count($detalle) === 0): ?>
            <p class="small text-secondary mb-0 text-center py-3">
                Todavía no hay evaluaciones registradas.
            </p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle portal-tabla mb-0">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Curso</th>
                            <th>Tipo</th>
                            <th>Nota</th>
                            <th>Descripción</th>
                            <th>Observaciones del profesor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($detalle as $fila): ?>
                            <?php $nota = $fila['calificacion'] !== null ? (float) $fila['calificacion'] : null; ?>
                            <tr>
                                <td class="text-nowrap">
                                    <?= $fila['fecha_evaluacion'] !== null
                                        ? date('d/m/Y', strtotime((string) $fila['fecha_evaluacion']))
                                        : '—' ?>
                                </td>
                                <td><?= htmlspecialchars($fila['curso']) ?></td>
                                <td class="text-nowrap"><?= htmlspecialchars($fila['tipo']) ?></td>
                                <td class="text-nowrap">
                                    <span class="portal-pill <?= $colorNota($nota) ?>">
                                        <?= $nota !== null ? number_format($nota, 1) . '/10' : 'Pendiente' ?>
                                    </span>
                                </td>
                                <td class="small" style="color: var(--portal-muted);">
                                    <?= !empty($fila['descripcion']) ? htmlspecialchars($fila['descripcion']) : '—' ?>
                                </td>
                                <td class="small" style="color: var(--portal-muted);">
                                    <?= !empty($fila['observaciones_profesor']) ? htmlspecialchars($fila['observaciones_profesor']) : '—' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

<?php endif; ?>
