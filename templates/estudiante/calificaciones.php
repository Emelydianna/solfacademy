<!-- Calificaciones y Evaluaciones -->
<?php
/**
 * Calificaciones (pantalla 6 del prototipo).
 * Datos: $datos = EstudiantePortalService::calificaciones($id)
 *   - resumen: cursos_evaluados, evaluaciones_total, promedio_global,
 *              notas_evaluadas, cursos_aprobados, evaluaciones_pendientes
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
$aprobados      = (int) ($resumen['cursos_aprobados'] ?? 0);
$pendientes     = (int) ($resumen['evaluaciones_pendientes'] ?? 0);

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
?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="portal-title mb-1">Calificaciones y Evaluaciones</h1>
        <p class="portal-subtitle mb-0">Consulta el resultado de tus exámenes prácticos, teóricos y audiciones.</p>
    </div>
    <?php if (count($grupos) > 0): ?>
        <a href="/estudiante/calificaciones/boletin?scope=general" class="btn btn-outline-secondary fw-semibold">
            <i class="bi bi-download me-1"></i>Descargar Boletín
        </a>
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

    <!-- KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="portal-card portal-kpi h-100 p-3">
                <div class="d-flex justify-content-between align-items-start">
                    <span class="portal-label">Promedio del Semestre</span>
                    <span class="portal-kpi-icon portal-kpi-icon--verde"><i class="bi bi-graph-up-arrow"></i></span>
                </div>
                <div class="portal-kpi-value"><?= number_format($promedioGlobal, 1) ?><small>/10</small></div>
                <span class="portal-kpi-note"><?= htmlspecialchars($calificar($promedioGlobal)) ?></span>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <div class="portal-card portal-kpi h-100 p-3">
                <div class="d-flex justify-content-between align-items-start">
                    <span class="portal-label">Asignaturas Aprobadas</span>
                    <span class="portal-kpi-icon portal-kpi-icon--azul"><i class="bi bi-patch-check"></i></span>
                </div>
                <div class="portal-kpi-value"><?= $aprobados ?> <small>Curso(s)</small></div>
                <span class="portal-kpi-note">Con promedio igual o superior a 5,0</span>
            </div>
        </div>

        <div class="col-12 col-md-4">
            <div class="portal-card portal-kpi h-100 p-3">
                <div class="d-flex justify-content-between align-items-start">
                    <span class="portal-label">Evaluaciones Pendientes</span>
                    <span class="portal-kpi-icon portal-kpi-icon--rosa"><i class="bi bi-clock-history"></i></span>
                </div>
                <div class="portal-kpi-value"><?= $pendientes ?> <small>Examen(es)</small></div>
                <span class="portal-kpi-note">Pendientes de calificar por tu instructor</span>
            </div>
        </div>
    </div>

    <!-- Tabla de calificaciones -->
    <div class="portal-card p-3 p-md-4 mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="portal-card-title mb-0">Calificaciones</h2>
            <span class="portal-pill"><?= count($detalle) ?> evaluación(es)</span>
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
                            <th>Curso</th>
                            <th>Evaluación</th>
                            <th>Fecha</th>
                            <th>Calificación</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($detalle as $fila): ?>
                            <?php
                            $nota = $fila['calificacion'] !== null ? (float) $fila['calificacion'] : null;
                            $ancho = $nota !== null ? max(0.0, min(100.0, $nota / 10 * 100)) : 0.0;
                            $aprobado = $nota !== null && $nota >= 5.0;
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($fila['curso']) ?></td>
                                <td><?= htmlspecialchars($fila['tipo']) ?></td>
                                <td class="text-nowrap">
                                    <?= $fila['fecha_evaluacion'] !== null
                                        ? date('d/m/Y', strtotime((string) $fila['fecha_evaluacion']))
                                        : '—' ?>
                                </td>
                                <td class="nota-celda">
                                    <?php if ($nota !== null): ?>
                                        <span class="nota-valor"><?= number_format($nota, 1) ?></span>
                                        <div class="nota-barra" role="progressbar"
                                             aria-valuenow="<?= htmlspecialchars(number_format($nota, 1)) ?>"
                                             aria-valuemin="0" aria-valuemax="10">
                                            <span style="width: <?= number_format($ancho, 1, '.', '') ?>%;"></span>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: var(--portal-muted);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($nota === null): ?>
                                        <span class="estado-badge estado-badge--justificado">Pendiente</span>
                                    <?php elseif ($aprobado): ?>
                                        <span class="estado-badge estado-badge--presente">Aprobado</span>
                                    <?php else: ?>
                                        <span class="estado-badge estado-badge--ausente">Reprobado</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Boletín por curso -->
    <h2 class="portal-card-title mb-3">Boletín por curso</h2>
    <div class="row g-3">
        <?php foreach ($grupos as $grupo): ?>
            <div class="col-md-6 col-xl-4">
                <div class="portal-card p-4 h-100 d-flex flex-column">
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
                    <div class="mt-auto pt-3">
                        <a href="/estudiante/calificaciones/boletin?scope=curso&id_curso=<?= (int) $grupo['id_curso'] ?>" class="btn btn-sm btn-outline-secondary w-100">
                            <i class="bi bi-download me-1"></i>Descargar boletín del curso
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Modal reutilizable para funciones aún no disponibles -->
    <div class="modal fade" id="modalProximamente" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content portal-modal">
                <div class="modal-body text-center p-4">
                    <span class="portal-modal-icon"><i class="bi bi-stars"></i></span>
                    <h2 class="portal-card-title h5 mb-1" data-modal-titulo-texto>Próximamente</h2>
                    <p class="small mb-4" style="color: var(--portal-muted);" data-modal-mensaje-texto>
                        Esta función estará disponible muy pronto.
                    </p>
                    <button type="button" class="btn btn-accent fw-semibold px-4" data-bs-dismiss="modal">Entendido</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var modal = document.getElementById('modalProximamente');
            if (!modal) return;
            modal.addEventListener('show.bs.modal', function (event) {
                var boton = event.relatedTarget;
                if (!boton) return;
                var titulo = boton.getAttribute('data-modal-titulo');
                var mensaje = boton.getAttribute('data-modal-mensaje');
                var elTitulo = modal.querySelector('[data-modal-titulo-texto]');
                var elMensaje = modal.querySelector('[data-modal-mensaje-texto]');
                if (titulo) elTitulo.textContent = titulo;
                if (mensaje) elMensaje.textContent = mensaje;
            });
        });
    </script>

<?php endif; ?>
