<?php
/**
 * Métricas y Reportes Generales (/admin/reportes).
 * Datos: $datos = ReportesService::datos()
 *   - fecha:        "Lunes, 28 Octubre 2026"
 *   - kpis:         [{titulo, valor, sub, verde, icono, clase}] ×4
 *   - distribucion: [{nombre, alumnos, porcentaje, color}] (matrículas vigentes)
 *   - programas:    [{id_curso, curso, area, alumnos, tendencia, direccion}]
 * La exportación a PDF usa exactamente los mismos datos.
 */
$e = static fn ($v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');

$kpis = $datos['kpis'] ?? [];
$distribucion = $datos['distribucion'] ?? [];
$programas = $datos['programas'] ?? [];

$etiquetas = array_column($distribucion, 'nombre');
$alumnos = array_column($distribucion, 'alumnos');
$colores = array_column($distribucion, 'color');
$claseTendencia = ['sube' => 'text-success', 'baja' => 'text-danger', 'igual' => 'admin-muted', 'neutra' => 'admin-muted'];
$iconoTendencia = ['sube' => 'bi-arrow-up-right', 'baja' => 'bi-arrow-down-right', 'igual' => 'bi-dash-lg', 'neutra' => 'bi-dash-lg'];
?>
<div class="admin-academica">
    <!-- Cabecera -->
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="portal-title mb-1">Métricas y Reportes Generales</h1>
            <p class="portal-subtitle mb-0">Visualiza el estado de las finanzas, admisiones y rendimiento académico.</p>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-3">
            <span class="admin-fecha-actual"><i class="bi bi-calendar3 me-1"></i><?= $e($datos['fecha']) ?></span>
            <a href="/admin/reportes/exportar" class="btn admin-btn-primary align-self-start">
                <i class="bi bi-file-earmark-arrow-down me-1"></i>Exportar PDF
            </a>
        </div>
    </div>

    <!-- KPIs -->
    <div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 g-3 mb-4">
        <?php foreach ($kpis as $kpi): ?>
            <div class="col">
                <div class="portal-card p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                        <div class="portal-kpi-label pt-1"><?= $e($kpi['titulo']) ?></div>
                        <span class="portal-kpi-icon <?= $e($kpi['clase']) ?>"><i class="bi <?= $e($kpi['icono']) ?>"></i></span>
                    </div>
                    <div class="portal-kpi-value"><?= $e($kpi['valor']) ?></div>
                    <div class="small mt-1" style="color:<?= !empty($kpi['verde']) ? '#34d399' : '#94a3b8' ?>;">
                        <i class="bi <?= !empty($kpi['verde']) ? 'bi-arrow-trend-up' : 'bi-dash-lg' ?> me-1"></i><?= $e($kpi['sub']) ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Gráficos y tabla -->
    <div class="row g-4">
        <!-- Distribución por instrumento -->
        <div class="col-lg-5">
            <section class="portal-card p-4 h-100">
                <h2 class="portal-card-title mb-3">Distribución de Alumnos por Instrumento</h2>

                <?php if (!$distribucion): ?>
                    <p class="small text-secondary mb-0 text-center py-4">
                        No hay matrículas vigentes para agrupar por instrumento.
                    </p>
                <?php else: ?>
                    <div class="d-flex flex-wrap align-items-center gap-4">
                        <div style="width: 190px; height: 190px; flex-shrink: 0;">
                            <canvas id="chartInstrumentos" aria-label="Distribución de alumnos por instrumento" role="img"></canvas>
                        </div>
                        <ul class="list-unstyled mb-0 flex-grow-1" style="min-width: 180px;">
                            <?php foreach ($distribucion as $item): ?>
                                <li class="d-flex align-items-center gap-2 mb-2">
                                    <span style="width:10px;height:10px;border-radius:50%;background:<?= $e($item['color']) ?>;display:inline-block;flex-shrink:0;"></span>
                                    <span class="flex-grow-1"><?= $e($item['nombre']) ?></span>
                                    <span class="admin-muted small"><?= (int) $item['alumnos'] ?> alumno<?= (int) $item['alumnos'] === 1 ? '' : 's' ?></span>
                                    <strong class="small"><?= $e(number_format((float) $item['porcentaje'], 1)) ?>%</strong>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <p class="small admin-muted mb-0 mt-3">
                        Alumnos distintos con matrícula vigente (Activa o Pendiente) en cursos de cada instrumento.
                    </p>
                <?php endif; ?>
            </section>
        </div>

        <!-- Programas más populares -->
        <div class="col-lg-7">
            <section class="portal-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3 gap-2">
                    <h2 class="portal-card-title mb-0">Programas más Populares</h2>
                    <span class="admin-count"><?= count($programas) ?> cursos</span>
                </div>

                <?php if (!$programas): ?>
                    <p class="small text-secondary mb-0 text-center py-4">
                        No hay matrículas vigentes registradas todavía.
                    </p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table admin-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>CURSO</th>
                                    <th>ÁREA</th>
                                    <th class="text-end">ALUMNOS</th>
                                    <th>TENDENCIA</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($programas as $curso): ?>
                                    <?php $direccion = (string) $curso['direccion']; ?>
                                    <tr>
                                        <td class="admin-name-cell"><strong><?= $e($curso['curso']) ?></strong></td>
                                        <td><?= $e($curso['area']) ?></td>
                                        <td class="text-end text-nowrap"><?= (int) $curso['alumnos'] ?></td>
                                        <td class="text-nowrap">
                                            <span class="<?= $claseTendencia[$direccion] ?? 'admin-muted' ?> fw-semibold">
                                                <i class="bi <?= $iconoTendencia[$direccion] ?? 'bi-dash-lg' ?> me-1"></i><?= $e($curso['tendencia']) ?>
                                            </span>
                                            <small class="d-block admin-muted">últimos 30 días</small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="small admin-muted mb-0 mt-3">
                        Variación de matrículas de los últimos 30 días frente a los 30 anteriores; «—» cuando no hay periodo comparable.
                    </p>
                <?php endif; ?>
            </section>
        </div>
    </div>
</div>

<?php if ($distribucion): ?>
    <script>
        (function () {
            const lienzo = document.getElementById('chartInstrumentos');
            if (!lienzo || typeof Chart === 'undefined') return;
            new Chart(lienzo, {
                type: 'doughnut',
                data: {
                    labels: <?= json_encode($etiquetas, JSON_UNESCAPED_UNICODE) ?>,
                    datasets: [{
                        data: <?= json_encode($alumnos) ?>,
                        backgroundColor: <?= json_encode($colores) ?>,
                        borderColor: 'transparent',
                        hoverOffset: 6
                    }]
                },
                options: {
                    cutout: '68%',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function (c) {
                                    const total = c.dataset.data.reduce((a, b) => a + b, 0);
                                    const pct = total ? ((c.parsed / total) * 100).toFixed(1) : '0.0';
                                    return c.label + ': ' + c.parsed + ' alumnos (' + pct + '%)';
                                }
                            }
                        }
                    }
                }
            });
        })();
    </script>
<?php endif; ?>
