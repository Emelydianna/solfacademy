<!-- Mis Cursos -->
<?php
/**
 * Mis Cursos (pantalla 2 del prototipo).
 * Datos: $cursos = EstudiantePortalService::misCursos()
 *   Cada fila: id_inscripcion, estado, fecha_inscripcion, precio_inscripcion,
 *   observaciones, id_grupo, fecha_inicio, fecha_fin, estado_grupo,
 *   capacidad_maxima, id_curso, curso, nivel, descripcion, duracion_semanas,
 *   precio, aula, ubicacion, instructor, instructor_email, horarios[]
 */
$cursos = $cursos ?? [];

$claseEstado = [
    'Activa'     => 'portal-pill--verde',
    'Pendiente'  => 'portal-pill--dorado',
    'Finalizada' => 'portal-pill--azul',
    'Cancelada'  => 'portal-pill--rojo',
];
?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="portal-title mb-1">Mis Cursos</h1>
        <p class="portal-subtitle mb-0">Administra tus clases inscritas y realiza un seguimiento de tu avance académico.</p>
    </div>
    <?php if (count($cursos) > 0): ?>
        <span class="portal-pill"><?= count($cursos) ?> inscripción(es)</span>
    <?php endif; ?>
</div>

<?php if (count($cursos) === 0): ?>
    <div class="portal-card p-4 text-center">
        <i class="bi bi-journal-x fs-1 d-block mb-2" style="color: var(--portal-muted);"></i>
        <p class="mb-1 fw-semibold">Aún no tienes cursos inscritos</p>
        <p class="small text-secondary mb-3">Explora el catálogo y apúntate a tu primera clase de música.</p>
        <a href="/cursos" class="btn btn-accent fw-semibold">Ver catálogo de cursos</a>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($cursos as $curso): ?>
            <div class="col-12">
                <div class="portal-card p-4 h-100 course-row">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                        <!-- Cabecera: curso + estado -->
                        <div class="flex-grow-1" style="min-width: 260px;">
                            <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                                <h2 class="portal-card-title mb-0"><?= htmlspecialchars($curso['curso']) ?></h2>
                                <span class="portal-pill <?= $claseEstado[$curso['estado']] ?? 'portal-pill--azul' ?>">
                                    <?= htmlspecialchars($curso['estado']) ?>
                                </span>
                            </div>
                            <p class="small mb-2" style="color: var(--portal-muted);">
                                <?= htmlspecialchars($curso['nivel']) ?>
                                <?php if (!empty($curso['duracion_semanas'])): ?>
                                    · <?= (int) $curso['duracion_semanas'] ?> semanas
                                <?php endif; ?>
                                <?php if (!empty($curso['fecha_inicio'])): ?>
                                    · <?= date('d/m/Y', strtotime((string) $curso['fecha_inicio'])) ?>
                                    <?= !empty($curso['fecha_fin']) ? ' — ' . date('d/m/Y', strtotime((string) $curso['fecha_fin'])) : '' ?>
                                <?php endif; ?>
                            </p>
                            <?php if (!empty($curso['descripcion'])): ?>
                                <p class="small mb-0" style="color: var(--portal-muted);"><?= htmlspecialchars($curso['descripcion']) ?></p>
                            <?php endif; ?>
                        </div>

                        <!-- Precio -->
                        <div class="text-end">
                            <?php if (!empty($curso['precio_inscripcion'])): ?>
                                <div class="portal-kpi-value portal-kpi-value--sm">
                                    $<?= number_format((float) $curso['precio_inscripcion'], 2) ?>
                                </div>
                                <span class="portal-label mb-0">Precio de matrícula</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <hr style="border-color: var(--portal-border); opacity: .6;">

                    <!-- Detalles: instructor, aula, horario -->
                    <div class="row g-3 small">
                        <div class="col-md-4">
                            <div class="portal-label mb-1"><i class="bi bi-person me-1"></i>Instructor</div>
                            <div><?= htmlspecialchars($curso['instructor']) ?></div>
                            <?php if (!empty($curso['instructor_email'])): ?>
                                <a class="small" style="color: var(--portal-blue);" href="mailto:<?= htmlspecialchars($curso['instructor_email']) ?>">
                                    <?= htmlspecialchars($curso['instructor_email']) ?>
                                </a>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-4">
                            <div class="portal-label mb-1"><i class="bi bi-door-closed me-1"></i>Aula</div>
                            <div><?= htmlspecialchars($curso['aula']) ?></div>
                            <?php if (!empty($curso['ubicacion'])): ?>
                                <span style="color: var(--portal-muted);"><?= htmlspecialchars($curso['ubicacion']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-4">
                            <div class="portal-label mb-1"><i class="bi bi-clock me-1"></i>Horario</div>
                            <?php if (count($curso['horarios']) === 0): ?>
                                <span style="color: var(--portal-muted);">Sin horario asignado</span>
                            <?php else: ?>
                                <?php foreach ($curso['horarios'] as $horario): ?>
                                    <div>
                                        <?= htmlspecialchars($horario['dia_semana']) ?>
                                        <?= substr((string) $horario['hora_inicio'], 0, 5) ?>–<?= substr((string) $horario['hora_fin'], 0, 5) ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Enlaces de acción -->
                    <div class="mt-3">
                        <a href="/cursos/<?= (int) $curso['id_curso'] ?>" class="portal-link">
                            Ver detalle del curso <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
