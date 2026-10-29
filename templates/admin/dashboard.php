<?php
/**
 * Panel de administración — vista principal (/admin/dashboard).
 *
 * Todos los datos llegan desde AdminDashboardService; aquí no se consulta
 * la BD ni se inventan cifras.
 */

$data = $data ?? [];
$kpis = $data['kpis'] ?? [];
$grafico = $data['grafico'] ?? ['titulo' => '', 'etiquetas' => [], 'valores' => [], 'acumulado' => 0, 'destacado' => -1];
$alertas = $data['alertas'] ?? [];
$actividad = $data['actividad'] ?? [];
$rangoActual = $data['rango'] ?? '5m';
$rangos = $data['rangos'] ?? [];
$notificaciones = (int) ($data['notificaciones'] ?? 0);

/** Severidad => [color del texto/borde, fondo de la tarjeta] */
$estiloAlerta = [
    'urgente'   => ['#f87171', 'rgba(248,113,113,0.07)'],
    'pendiente' => ['#ffc107', 'rgba(255,193,7,0.07)'],
    'info'      => ['#60a5fa', 'rgba(96,165,250,0.07)'],
];

/** Tipo de evento => color del badge */
$colorBadge = [
    'Alta'      => '#60a5fa',
    'Matrícula' => '#ffc107',
    'Pago'      => '#34d399',
    'Académico' => '#60a5fa',
];
?>

<!-- Cabecera del contenido -->
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="portal-title mb-1">Panel de Administración</h1>
        <p class="portal-subtitle mb-0">Monitoreo global de la academia, control financiero y de admisiones</p>
    </div>

    <div class="d-flex align-items-center gap-3">
        <!-- Notificaciones (pendientes reales) -->
        <a href="#alertas" class="position-relative text-decoration-none" aria-label="Alertas del sistema" style="color:var(--portal-muted);">
            <i class="bi bi-bell fs-5"></i>
            <?php if ($notificaciones > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill"
                      style="background:#f87171;color:#000;font-size:0.65rem;">
                    <?= $notificaciones > 99 ? '99+' : $notificaciones ?>
                </span>
            <?php endif; ?>
        </a>

        <!-- Fecha de hoy -->
        <span class="small d-none d-sm-inline" style="color:var(--portal-muted);">
            <i class="bi bi-calendar3 me-1"></i><?= htmlspecialchars($data['fechaTexto'] ?? '') ?>
        </span>

        <!-- Selector de periodo (recarga el panel) -->
        <form method="get" action="/admin/dashboard" class="m-0">
            <label class="visually-hidden" for="rango">Periodo del panel</label>
            <select id="rango" name="rango" onchange="this.form.submit()"
                    class="form-select form-select-sm"
                    style="background-color:var(--portal-surface-2);border:1px solid var(--portal-border);color:var(--portal-text);">
                <?php foreach ($rangos as $clave => $etiqueta): ?>
                    <option value="<?= htmlspecialchars($clave) ?>" <?= $clave === $rangoActual ? 'selected' : '' ?>>
                        <?= htmlspecialchars($etiqueta) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
</div>

<!-- Fila de métricas -->
<div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 g-3 mb-4">
    <?php foreach ($kpis as $k): ?>
        <div class="col">
            <div class="portal-card p-3 h-100">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                    <div class="portal-kpi-label pt-1"><?= htmlspecialchars($k['titulo']) ?></div>
                    <span class="portal-kpi-icon <?= htmlspecialchars($k['clase'] ?? '') ?>">
                        <i class="bi <?= htmlspecialchars($k['icono']) ?>"></i>
                    </span>
                </div>
                <div class="portal-kpi-value"><?= htmlspecialchars($k['valor']) ?></div>
                <div class="small mt-1" style="color:<?= !empty($k['verde']) ? '#34d399' : '#94a3b8' ?>;">
                    <i class="bi <?= !empty($k['verde']) ? 'bi-graph-up-arrow' : 'bi-dash-lg' ?> me-1"></i><?= htmlspecialchars($k['sub']) ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Gráfico + alertas -->
<div class="row g-4 mb-4">
    <div class="<?= empty($alertas) ? 'col-lg-12' : 'col-lg-8' ?>">
        <div class="portal-card p-3 p-md-4 h-100">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div>
                    <h2 class="portal-card-title mb-0"><?= htmlspecialchars($grafico['titulo']) ?></h2>
                    <div class="small" style="color:var(--portal-muted);"><?= htmlspecialchars($grafico['subtitulo'] ?? '') ?></div>
                </div>
                <span class="badge" style="background:rgba(255,193,7,0.14);color:#ffc107;">
                    TOTAL ACUMULADO: +<?= (int) $grafico['acumulado'] ?> MATRÍCULAS
                </span>
            </div>
            <div style="position:relative;height:270px;">
                <canvas id="chartInscripciones"></canvas>
            </div>
        </div>
    </div>

    <?php if (!empty($alertas)): ?>
        <div class="col-lg-4" id="alertas">
            <div class="portal-card p-3 p-md-4 h-100">
                <h2 class="portal-card-title mb-3">Alertas del Sistema</h2>
                <?php foreach ($alertas as $a):
                    [$color, $fondo] = $estiloAlerta[$a['severidad']] ?? $estiloAlerta['info'];
                ?>
                    <div class="p-3 mb-3 d-flex gap-3"
                         style="border:1px solid var(--portal-border);border-left:3px solid <?= $color ?>;border-radius:12px;background:<?= $fondo ?>;">
                        <i class="bi <?= htmlspecialchars($a['icono']) ?> fs-5" style="color:<?= $color ?>;"></i>
                        <div>
                            <div class="fw-semibold small"><?= htmlspecialchars($a['titulo']) ?></div>
                            <div class="small mb-0" style="color:var(--portal-muted);"><?= htmlspecialchars($a['texto']) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Actividad reciente -->
<div class="portal-card p-3 p-md-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="portal-card-title mb-0">Actividad Reciente del Sistema</h2>
        <span class="small" style="color:var(--portal-muted);"><?= count($actividad) ?> eventos</span>
    </div>

    <?php if (empty($actividad)): ?>
        <p class="mb-0" style="color:var(--portal-muted);">Todavía no hay actividad registrada.</p>
    <?php else: ?>
        <?php foreach ($actividad as $e): ?>
            <div class="d-flex flex-wrap align-items-center gap-3 py-2"
                 style="border-bottom:1px solid var(--portal-border);">
                <span style="width:9px;height:9px;border-radius:50%;background:#ffc107;flex-shrink:0;"></span>
                <div class="flex-grow-1" style="min-width:200px;">
                    <div class="small" style="color:var(--portal-text);"><?= htmlspecialchars($e['texto']) ?></div>
                    <div class="small" style="color:var(--portal-muted);">
                        <?= htmlspecialchars($e['hace']) ?> <span class="mx-1">·</span> Administración
                    </div>
                </div>
                <span class="badge" style="background:transparent;color:<?= $colorBadge[$e['tipo']] ?? '#94a3b8' ?>;border:1px solid <?= $colorBadge[$e['tipo']] ?? '#94a3b8' ?>;">
                    <?= htmlspecialchars($e['tipo']) ?>
                </span>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
(function () {
    const etiquetas = <?= json_encode($grafico['etiquetas']) ?>;
    const valores = <?= json_encode($grafico['valores']) ?>;
    const destacado = <?= (int) ($grafico['destacado'] ?? -1) ?>;

    // Dibuja el valor encima de cada barra (como en la maqueta)
    const valoresSobreBarra = {
        id: 'valoresSobreBarra',
        afterDatasetsDraw(chart) {
            const ctx = chart.ctx;
            chart.data.datasets.forEach((dataset, i) => {
                chart.getDatasetMeta(i).data.forEach((bar, j) => {
                    const v = dataset.data[j];
                    if (v === null || v === undefined) return;
                    ctx.save();
                    ctx.fillStyle = j === destacado ? '#ffc107' : '#94a3b8';
                    ctx.font = '600 11px system-ui, -apple-system, sans-serif';
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'bottom';
                    ctx.fillText(String(v), bar.x, bar.y - 6);
                    ctx.restore();
                });
            });
        }
    };

    new Chart(document.getElementById('chartInscripciones'), {
        type: 'bar',
        plugins: [valoresSobreBarra],
        data: {
            labels: etiquetas,
            datasets: [{
                data: valores,
                backgroundColor: valores.map((v, i) => i === destacado ? '#ffc107' : 'rgba(148,163,184,0.35)'),
                hoverBackgroundColor: valores.map((v, i) => i === destacado ? '#f0ad4e' : 'rgba(148,163,184,0.55)'),
                borderRadius: 6,
                maxBarThickness: 34
            }]
        },
        options: {
            layout: { padding: { top: 20, right: 8 } },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: c => c.parsed.y + (c.parsed.y === 1 ? ' matrícula' : ' matrículas')
                    }
                }
            },
            scales: {
                x: { ticks: { color: '#94a3b8' }, grid: { display: false } },
                y: {
                    beginAtZero: true,
                    ticks: { color: '#94a3b8', precision: 0, stepSize: 1 },
                    grid: { color: 'rgba(148,163,184,0.1)' }
                }
            }
        }
    });
})();
</script>
