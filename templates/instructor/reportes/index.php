<?php
$asistenciaPorGrupo = $asistenciaPorGrupo ?? [];
$distribucion = $distribucion ?? ['excelente'=>0,'aprobado'=>0,'reprobado'=>0];
$resumen = $resumen ?? [];
$evolucion = $evolucion ?? [];
$totalDist = max(1, (int)($distribucion['excelente']??0) + (int)($distribucion['aprobado']??0) + (int)($distribucion['reprobado']??0));
?>
<div class="mb-4">
    <h1 class="portal-title mb-1">Reportes del Instructor</h1>
    <p class="portal-subtitle mb-0">Analíticas de rendimiento estudiantil, asistencia y evolución académica</p>
</div>

<div class="d-flex flex-wrap gap-2 align-items-center mb-4">
    <select class="form-select" style="max-width:220px;background:#0B0F19;color:#fff;border:1px solid var(--portal-border);">
        <option>Todos los Grupos</option>
        <?php foreach ($grupos ?? [] as $g): ?>
            <option><?= htmlspecialchars($g['curso']) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="form-select" style="max-width:200px;background:#0B0F19;color:#fff;border:1px solid var(--portal-border);">
        <option>Ciclo Octubre 2026</option>
    </select>
    <button class="btn btn-warning fw-bold">FILTRAR</button>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-4">
        <div class="portal-card p-3 h-100">
            <h6 class="mb-3">Asistencia Promedio por Grupo (%)</h6>
            <div class="d-flex flex-column gap-2">
                <?php foreach ($asistenciaPorGrupo as $a): ?>
                    <div>
                        <div class="d-flex justify-content-between small mb-1">
                            <span><?= htmlspecialchars($a['grupo']) ?></span>
                            <span><?= number_format((float)$a['pct'], 0) ?>%</span>
                        </div>
                        <div class="progress" style="height:8px;background:#1e293b;">
                            <div class="progress-bar bg-warning" style="width:<?= min(100,(float)$a['pct']) ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="portal-card p-3 h-100">
            <h6 class="mb-3">Distribución de Calificaciones</h6>
            <div class="d-flex align-items-center gap-3">
                <div style="width:120px;height:120px;border-radius:50%;background:conic-gradient(#22c55e 0 <?= ($distribucion['excelente']/$totalDist)*100 ?>%, #06b6d4 <?= ($distribucion['excelente']/$totalDist)*100 ?>% <?= (($distribucion['excelente']+$distribucion['aprobado'])/$totalDist)*100 ?>%, #ef4444 <?= (($distribucion['excelente']+$distribucion['aprobado'])/$totalDist)*100 ?>% 100%);"></div>
                <div class="small d-flex flex-column gap-2">
                    <div><span class="badge" style="background:#22c55e;">&nbsp;</span> Excelente (90-100): <strong><?= (int)$distribucion['excelente'] ?></strong> Alumnos</div>
                    <div><span class="badge" style="background:#06b6d4;">&nbsp;</span> Aprobado (70-89): <strong><?= (int)$distribucion['aprobado'] ?></strong> Alumnos</div>
                    <div><span class="badge" style="background:#ef4444;">&nbsp;</span> Reprobado (&lt;70): <strong><?= (int)$distribucion['reprobado'] ?></strong> Alumnos</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="portal-card p-3 h-100">
            <h6 class="mb-3">Evolución del Promedio Semestral</h6>
            <svg viewBox="0 0 300 120" width="100%" height="120">
                <polyline fill="none" stroke="#ffc107" stroke-width="2" points="20,90 70,70 120,60 170,50 220,40" />
                <?php $pts = [[20,90],[70,70],[120,60],[170,50],[220,40]]; foreach($pts as $i=>$p): ?>
                    <circle cx="<?= $p[0] ?>" cy="<?= $p[1] ?>" r="4" fill="#ffc107" />
                <?php endforeach; ?>
            </svg>
            <div class="d-flex justify-content-between small mt-2" style="color:#94a3b8;">
                <?php foreach ($evolucion as $e): ?><span><?= $e['mes'] ?></span><?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div class="portal-card p-3 p-md-4">
    <h5 class="mb-3">Resumen de Rendimiento Académico por Grupo</h5>
    <div class="table-responsive">
        <table class="table table-dark table-borderless align-middle mb-0">
            <thead>
                <tr style="border-bottom:1px solid var(--portal-border);">
                    <th>GRUPO / CURSO</th>
                    <th>Nº ALUMNOS</th>
                    <th>PROMEDIO ASISTENCIA</th>
                    <th>PROMEDIO CALIFICACIÓN</th>
                    <th>ESTADO GENERAL</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($resumen as $r): ?>
                    <tr style="border-bottom:1px solid rgba(255,255,255,0.03);">
                        <td class="fw-semibold"><?= htmlspecialchars($r['grupo']) ?></td>
                        <td><?= (int)$r['alumnos'] ?> Alumnos</td>
                        <td><?= number_format((float)$r['asistencia'], 1) ?>%</td>
                        <td class="fw-bold"><?= number_format((float)$r['promedio'], 1) ?> / 10</td>
                        <td><span class="badge bg-<?= $r['badge'] ?>"><?= htmlspecialchars($r['estado']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
