<?php
$asistenciaPorGrupo = $asistenciaPorGrupo ?? [];
$distribucion = $distribucion ?? ['excelente'=>0,'aprobado'=>0,'reprobado'=>0];
$resumen = $resumen ?? [];
$evolucion = $evolucion ?? [];
$grupos = $grupos ?? [];
$totalDist = max(1, (int)($distribucion['excelente']??0) + (int)($distribucion['aprobado']??0) + (int)($distribucion['reprobado']??0));
?>
<div class="mb-4">
    <h1 class="portal-title mb-1">Reportes del Instructor</h1>
    <p class="portal-subtitle mb-0">Analíticas de rendimiento estudiantil, asistencia y evolución académica</p>
</div>

<div class="portal-card p-3 p-md-4 mb-4">
    <div class="d-flex align-items-center gap-2 mb-3">
        <span class="text-uppercase small fw-bold" style="color:#ffc107;">Filtros</span>
    </div>
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <select class="form-select" style="max-width:260px;background:#0B0F19;color:#fff;border:1px solid var(--portal-border);">
            <option>Todos los Grupos</option>
            <?php foreach ($grupos as $g): ?>
                <option><?= htmlspecialchars($g['curso'] ?? '') ?></option>
            <?php endforeach; ?>
        </select>
        <select class="form-select" style="max-width:220px;background:#0B0F19;color:#fff;border:1px solid var(--portal-border);">
            <option>Ciclo Octubre 2026</option>
        </select>
        <button class="btn btn-warning fw-bold px-4">FILTRAR</button>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-4">
        <div class="portal-card p-3 p-md-4 h-100">
            <h6 class="mb-3 text-white">Asistencia Promedio por Grupo (%)</h6>
            <canvas id="chartAsistencia" height="220"></canvas>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="portal-card p-3 p-md-4 h-100">
            <h6 class="mb-3 text-white">Distribución de Calificaciones</h6>
            <div class="d-flex align-items-center gap-3">
                <canvas id="chartDistribucion" width="140" height="140"></canvas>
                <div class="d-flex flex-column gap-2 small">
                    <div class="d-flex align-items-center gap-2">
                        <span style="width:10px;height:10px;border-radius:50%;background:#22c55e;"></span>
                        <span style="color:#94a3b8;">Excelente (90-100)</span>
                        <span class="ms-auto text-white fw-semibold"><?= (int)($distribucion['excelente']??0) ?></span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span style="width:10px;height:10px;border-radius:50%;background:#ffc107;"></span>
                        <span style="color:#94a3b8;">Aprobado (70-89)</span>
                        <span class="ms-auto text-white fw-semibold"><?= (int)($distribucion['aprobado']??0) ?></span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span style="width:10px;height:10px;border-radius:50%;background:#ef4444;"></span>
                        <span style="color:#94a3b8;">Reprobado (&lt;70)</span>
                        <span class="ms-auto text-white fw-semibold"><?= (int)($distribucion['reprobado']??0) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="portal-card p-3 p-md-4 h-100">
            <h6 class="mb-3 text-white">Evolución del Promedio Semestral</h6>
            <canvas id="chartEvolucion" height="220"></canvas>
        </div>
    </div>
</div>

<div class="portal-card p-3 p-md-4">
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <h2 class="portal-card-title mb-0">Resumen de Rendimiento Académico por Grupo</h2>
    </div>
    <div class="table-responsive">
        <table class="table table-dark table-borderless align-middle mb-0">
            <thead>
                <tr style="border-bottom:1px solid var(--portal-border);">
                    <th class="text-uppercase small" style="color:#94a3b8;">Grupo / Curso</th>
                    <th class="text-uppercase small" style="color:#94a3b8;">Nº Alumnos</th>
                    <th class="text-uppercase small" style="color:#94a3b8;">Promedio Asistencia</th>
                    <th class="text-uppercase small" style="color:#94a3b8;">Promedio Calificación</th>
                    <th class="text-uppercase small" style="color:#94a3b8;">Estado General</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($resumen as $r): ?>
                    <tr style="border-bottom:1px solid rgba(255,255,255,0.03);">
                        <td class="fw-semibold text-white"><?= htmlspecialchars($r['grupo']) ?></td>
                        <td style="color:#cbd5e1;"><?= (int)$r['alumnos'] ?> Alumnos</td>
                        <td style="color:#cbd5e1;"><?= number_format((float)$r['asistencia'], 1) ?>%</td>
                        <td style="color:#ffc107;font-weight:600;"><?= number_format((float)$r['promedio'], 1) ?> / 10</td>
                        <td>
                            <?php if (($r['badge'] ?? '') === 'success'): ?>
                                <span class="badge" style="background:rgba(34,197,94,0.15);color:#22c55e;border:1px solid rgba(34,197,94,0.4);">• <?= htmlspecialchars($r['estado']) ?></span>
                            <?php elseif (($r['badge'] ?? '') === 'info'): ?>
                                <span class="badge" style="background:rgba(56,189,248,0.15);color:#38bdf8;border:1px solid rgba(56,189,248,0.4);">• <?= htmlspecialchars($r['estado']) ?></span>
                            <?php else: ?>
                                <span class="badge" style="background:rgba(239,68,68,0.15);color:#ef4444;border:1px solid rgba(239,68,68,0.4);">• <?= htmlspecialchars($r['estado']) ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
const asistenciaLabels = <?= json_encode(array_map(fn($x)=>$x['grupo'], $asistenciaPorGrupo)) ?>;
const asistenciaData = <?= json_encode(array_map(fn($x)=>(float)$x['pct'], $asistenciaPorGrupo)) ?>;
const distData = [<?= (int)($distribucion['excelente']??0) ?>, <?= (int)($distribucion['aprobado']??0) ?>, <?= (int)($distribucion['reprobado']??0) ?>];
const evoLabels = <?= json_encode(array_map(fn($x)=>$x['mes'], $evolucion)) ?>;
const evoData = <?= json_encode(array_map(fn($x)=>(float)$x['promedio'], $evolucion)) ?>;

new Chart(document.getElementById('chartAsistencia'), {
    type: 'bar',
    data: {
        labels: asistenciaLabels,
        datasets: [{
            data: asistenciaData,
            backgroundColor: asistenciaData.map((v,i)=> i===0 ? '#ffc107' : 'rgba(148,163,184,0.4)'),
            borderRadius: 6,
            maxBarThickness: 28
        }]
    },
    options: {
        plugins: { legend: { display: false } },
        scales: {
            x: { ticks: { color: '#94a3b8' }, grid: { display: false } },
            y: { beginAtZero: true, max: 100, ticks: { color: '#94a3b8', callback: v=>v+'%' }, grid: { color: 'rgba(148,163,184,0.1)' } }
        }
    }
});

new Chart(document.getElementById('chartDistribucion'), {
    type: 'doughnut',
    data: {
        labels: ['Excelente','Aprobado','Reprobado'],
        datasets: [{
            data: distData,
            backgroundColor: ['#22c55e','#ffc107','#ef4444'],
            borderWidth: 0
        }]
    },
    options: {
        cutout: '70%',
        plugins: { legend: { display: false } }
    }
});

new Chart(document.getElementById('chartEvolucion'), {
    type: 'line',
    data: {
        labels: evoLabels,
        datasets: [{
            data: evoData,
            borderColor: '#ffc107',
            backgroundColor: 'rgba(255,193,7,0.2)',
            tension: 0.4,
            pointBackgroundColor: '#fff',
            pointBorderColor: '#ffc107',
            pointRadius: 4,
            fill: false
        }]
    },
    options: {
        plugins: { legend: { display: false } },
        scales: {
            x: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(148,163,184,0.08)' } },
            y: { min: 7, max: 10, ticks: { color: '#94a3b8' }, grid: { color: 'rgba(148,163,184,0.08)' } }
        }
    }
});
</script>
