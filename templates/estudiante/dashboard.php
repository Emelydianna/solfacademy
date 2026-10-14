<?php
/**
 * Dashboard del Estudiante (pantalla 1 del prototipo).
 * Datos: $datos = EstudiantePortalService::dashboard()
 *   - saludo: clases_hoy, pagos_pendientes
 *   - kpis: cursos_inscritos, cursos_este_mes, proxima_clase, horas_completadas,
     * clases_asistidas, promedio, evaluaciones_totales
 *   - practica: [{dia, horas}] Lun..Dom
 *   - actividades: [{tipo, fecha, detalle}]
 *   - recordatorios: [{icono, titulo, texto}]
 */
$kpis = $datos['kpis'] ?? [];
$practica = $datos['practica'] ?? [];
$saludo = $datos['saludo'] ?? [];
$nombre = explode(' ', trim((string) ($usuarioSesion['nombre'] ?? 'Estudiante')))[0];
$proxima = $kpis['proxima_clase'] ?? null;

$maxPractica = 1.0;
foreach ($practica as $punto) {
    $maxPractica = max($maxPractica, (float) $punto['horas']);
}
?>

<!-- Saludo del día -->
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="portal-title mb-1">¡Hola de nuevo, <?= htmlspecialchars($nombre) ?>! 👋</h1>
        <p class="portal-subtitle mb-0">
            Tienes <?= (int) ($saludo['clases_hoy'] ?? 0) ?> clase(s) programada(s) para hoy y
            <?= (int) ($saludo['pagos_pendientes'] ?? 0) ?> pago(s) pendiente(s).
        </p>
    </div>
</div>

<!-- KPIs -->
<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <div class="portal-card portal-kpi h-100 p-3">
            <div class="d-flex justify-content-between align-items-start">
                <span class="portal-label">Cursos inscritos</span>
                <span class="portal-kpi-icon"><i class="bi bi-journal-bookmark"></i></span>
            </div>
            <div class="portal-kpi-value"><?= (int) ($kpis['cursos_inscritos'] ?? 0) ?></div>
            <span class="portal-kpi-note">+<?= (int) ($kpis['cursos_este_mes'] ?? 0) ?> este mes</span>
        </div>
    </div>

    <div class="col-6 col-xl-3">
        <div class="portal-card portal-kpi h-100 p-3">
            <div class="d-flex justify-content-between align-items-start">
                <span class="portal-label">Próxima clase</span>
                <span class="portal-kpi-icon portal-kpi-icon--verde"><i class="bi bi-clock"></i></span>
            </div>
            <?php if ($proxima !== null): ?>
                <div class="portal-kpi-value portal-kpi-value--sm">Hoy, <?= htmlspecialchars(substr((string) $proxima['hora_inicio'], 0, 5)) ?></div>
                <span class="portal-kpi-note"><?= htmlspecialchars($proxima['curso']) ?> · <?= htmlspecialchars($proxima['aula']) ?></span>
            <?php else: ?>
                <div class="portal-kpi-value portal-kpi-value--sm">—</div>
                <span class="portal-kpi-note">Sin clases hoy</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-6 col-xl-3">
        <div class="portal-card portal-kpi h-100 p-3">
            <div class="d-flex justify-content-between align-items-start">
                <span class="portal-label">Horas completadas</span>
                <span class="portal-kpi-icon portal-kpi-icon--azul"><i class="bi bi-hourglass-split"></i></span>
            </div>
            <div class="portal-kpi-value"><?= number_format((float) ($kpis['horas_completadas'] ?? 0), 0) ?></div>
            <span class="portal-kpi-note">De <?= (int) ($kpis['clases_asistidas'] ?? 0) ?> clases asistidas</span>
        </div>
    </div>

    <div class="col-6 col-xl-3">
        <div class="portal-card portal-kpi h-100 p-3">
            <div class="d-flex justify-content-between align-items-start">
                <span class="portal-label">Promedio general</span>
                <span class="portal-kpi-icon portal-kpi-icon--rosa"><i class="bi bi-trophy"></i></span>
            </div>
            <div class="portal-kpi-value">
                <?= number_format((float) ($kpis['promedio'] ?? 0), 1) ?><small>/10</small>
            </div>
            <span class="portal-kpi-note">Rendimiento:
                <?php
                $promedio = (float) ($kpis['promedio'] ?? 0);
                echo $promedio >= 9 ? 'Sobresaliente' : ($promedio >= 7 ? 'Notable' : 'En progreso');
                ?>
            </span>
        </div>
    </div>
</div>

<!-- Práctica + Actividades -->
<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="portal-card p-4 h-100">
            <div class="d-flex justify-content-between align-items-start mb-1">
                <h2 class="portal-card-title mb-0">Práctica Semanal</h2>
                <span class="portal-pill">Esta semana</span>
            </div>

            <div class="text-end mb-3">
                <span class="portal-chart-total">
                    <?= number_format(array_sum(array_column($practica, 'horas')), 1, ',', '.') ?> horas
                </span>
                <div class="portal-label mb-0">Total acumulado</div>
            </div>

            <div class="chart-bars mb-2">
                <?php foreach ($practica as $punto): ?>
                    <?php
                    $horas = (float) $punto['horas'];
                    $alto = $horas > 0 ? max(6, (int) round($horas / $maxPractica * 100)) : 2;
                    ?>
                    <div class="chart-col">
                        <div class="chart-hour"><?= $horas > 0 ? rtrim(rtrim(number_format($horas, 1, ',', '.'), '0'), ',') : '' ?></div>
                        <div class="chart-bar <?= $horas >= $maxPractica ? 'chart-bar--hoy' : '' ?>" style="height: <?= $alto ?>%"></div>
                        <div class="chart-day"><?= htmlspecialchars($punto['dia']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <p class="small text-secondary mb-0">
                Has acumulado
                <?= number_format(array_sum(array_column($practica, 'horas')), 1, ',', '.') ?> horas
                de práctica individual esta semana. ¡Sigue así!
            </p>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="portal-card p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="portal-card-title mb-0">Actividades Recientes</h2>
                <a href="/estudiante/asistencias" class="portal-link">Ver todo</a>
            </div>

            <?php if (empty($datos['actividades'])): ?>
                <p class="small text-secondary mb-0">Todavía no hay actividad registrada.</p>
            <?php endif; ?>

            <?php foreach ($datos['actividades'] as $actividad): ?>
                <div class="activity-item activity-item--<?= htmlspecialchars($actividad['tipo']) ?>">
                    <div class="activity-text"><?= htmlspecialchars($actividad['detalle']) ?></div>
                    <div class="activity-date">
                        <?= date('d/m/Y', strtotime((string) $actividad['fecha'])) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Recordatorios -->
<?php if (!empty($datos['recordatorios'])): ?>
    <div class="portal-card p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="portal-card-title mb-0">Recordatorios Próximos</h2>
            <span class="portal-pill portal-pill--dorado">Próximos</span>
        </div>

        <div class="row g-3">
            <?php foreach ($datos['recordatorios'] as $recordatorio): ?>
                <div class="col-md-6">
                    <div class="reminder-item">
                        <span class="reminder-icon"><i class="bi <?= htmlspecialchars($recordatorio['icono']) ?>"></i></span>
                        <div>
                            <div class="reminder-title"><?= htmlspecialchars($recordatorio['titulo']) ?></div>
                            <div class="reminder-text"><?= htmlspecialchars($recordatorio['texto']) ?></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
