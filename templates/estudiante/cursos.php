<!-- Mis Cursos -->
<?php
/**
 * Mis Cursos (pantalla 2 del prototipo).
 * Datos: $cursos = EstudiantePortalService::misCursos()
 *   Cada fila: id_inscripcion, estado, fecha_inscripcion, precio_inscripcion,
 *   observaciones, id_grupo, fecha_inicio, fecha_fin, estado_grupo,
 *   capacidad_maxima, id_curso, curso, nivel, descripcion, duracion_semanas,
 *   precio, categoria, aula, ubicacion, instructor, instructor_email,
 *   avance_pct, clases_presentes, clases_registradas, horarios[]
 */
$cursos = $cursos ?? [];

$claseEstado = [
    'Activa'     => 'portal-pill--verde',
    'Pendiente'  => 'portal-pill--dorado',
    'Finalizada' => 'portal-pill--azul',
    'Cancelada'  => 'portal-pill--rojo',
];

$diasCortos = [
    'Lunes' => 'Lun', 'Martes' => 'Mar', 'Miércoles' => 'Mié', 'Jueves' => 'Jue',
    'Viernes' => 'Vie', 'Sábado' => 'Sáb', 'Domingo' => 'Dom',
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
            <?php
            $finalizado = in_array($curso['estado'], ['Finalizada', 'Cancelada'], true);
            $avance     = max(0, min(100, (int) ($curso['avance_pct'] ?? 0)));
            ?>
            <div class="col-12 col-md-6 col-xl-4">
                <div class="portal-card course-card h-100 d-flex flex-column">

                    <div class="course-card-body p-3 flex-grow-1">
                        <!-- Cabecera: categoría + estado -->
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                            <span class="course-card-cat"><?= htmlspecialchars($curso['categoria'] ?? 'Curso') ?></span>
                            <span class="portal-pill <?= $claseEstado[$curso['estado']] ?? 'portal-pill--azul' ?>">
                                <?= htmlspecialchars($curso['estado']) ?>
                            </span>
                        </div>

                        <!-- Título -->
                        <h2 class="portal-card-title course-card-title mb-2"><?= htmlspecialchars($curso['curso']) ?></h2>

                        <!-- Metadatos con iconos -->
                        <div class="course-card-meta">
                            <div class="course-card-row">
                                <i class="bi bi-person"></i>
                                <span><?= htmlspecialchars($curso['instructor']) ?></span>
                            </div>

                            <div class="course-card-row">
                                <i class="bi bi-clock"></i>
                                <span>
                                    <?php if (count($curso['horarios']) === 0): ?>
                                        Sin horario asignado
                                    <?php else: ?>
                                        <?php
                                        $partes = [];
                                        foreach ($curso['horarios'] as $horario) {
                                            $dia = $diasCortos[$horario['dia_semana']] ?? substr((string) $horario['dia_semana'], 0, 3);
                                            $partes[] = $dia . ' ' . substr((string) $horario['hora_inicio'], 0, 5)
                                                . '–' . substr((string) $horario['hora_fin'], 0, 5);
                                        }
                                        echo htmlspecialchars(implode(' · ', $partes));
                                        ?>
                                    <?php endif; ?>
                                </span>
                            </div>

                            <div class="course-card-row">
                                <i class="bi bi-geo-alt"></i>
                                <span>
                                    <?= htmlspecialchars($curso['aula']) ?>
                                    <?php if (!empty($curso['ubicacion'])): ?>
                                        <span class="course-card-muted"> · <?= htmlspecialchars($curso['ubicacion']) ?></span>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>

                        <!-- Progreso (% de asistencia) -->
                        <div class="course-card-progress mt-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="portal-label mb-0">Avance</span>
                                <span class="course-card-pct"><?= $avance ?>%</span>
                            </div>
                            <div class="progress" role="progressbar"
                                 aria-label="Avance del curso"
                                 aria-valuenow="<?= $avance ?>" aria-valuemin="0" aria-valuemax="100">
                                <div class="progress-bar" style="width: <?= $avance ?>%;"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Acción inferior -->
                    <div class="px-3 pb-3">
                        <?php if ($finalizado): ?>
                            <button type="button"
                                    class="btn btn-accent w-100 fw-bold"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalProximamente"
                                    data-modal-titulo="Certificado"
                                    data-modal-mensaje="La descarga de certificados estará disponible muy pronto.">
                                Ver Certificado <i class="bi bi-arrow-right ms-1"></i>
                            </button>
                        <?php else: ?>
                            <a href="/cursos/<?= (int) $curso['id_curso'] ?>" class="btn btn-accent w-100 fw-bold">
                                Ir al Curso <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

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
