<?php
$ficha = $ficha ?? [];
$e = $ficha['estudiante'] ?? [];
$matriculas = $ficha['matriculas'] ?? [];
$evaluaciones = $ficha['evaluaciones'] ?? [];
$asistencias = $ficha['asistencias'] ?? [];
$r = $ficha['resumen'] ?? [];

$badgeEstado = static function (string $estado): string {
    return match ($estado) {
        'Activa'     => 'bg-success',
        'Pendiente'  => 'bg-warning text-dark',
        'Cancelada'  => 'bg-danger',
        'Finalizada' => 'bg-secondary',
        default      => 'bg-secondary',
    };
};

$nombreCompleto = trim(($e['nombre'] ?? '') . ' ' . ($e['apellido'] ?? ''));
$iniciales = mb_strtoupper(mb_substr($e['nombre'] ?? '', 0, 1) . mb_substr($e['apellido'] ?? '', 0, 1));
$promedioGeneral = $r['promedio_general'] ?? null;
?>
<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-2">
            <li class="breadcrumb-item"><a href="/instructor/grupos" style="color:#94a3b8;">Mis Grupos</a></li>
            <li class="breadcrumb-item active" style="color:#fff;">Perfil Académico</li>
        </ol>
    </nav>
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h1 class="portal-title mb-0">Perfil Académico del Alumno</h1>
        <a href="/instructor/grupos" class="btn btn-outline-warning btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Volver a Mis Grupos
        </a>
    </div>
    <p class="portal-subtitle mb-0">Ficha académica, asistencias, calificaciones y progreso.</p>
</div>

<!-- Ficha del alumno -->
<div class="portal-card p-3 p-md-4 mb-4">
    <div class="d-flex flex-wrap align-items-center gap-3">
        <div class="portal-avatar" style="width:72px;height:72px;font-size:1.4rem;overflow:hidden;">
            <?php if (!empty($e['foto'])): ?>
                <img src="<?= htmlspecialchars((string)$e['foto']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
            <?php else: ?>
                <?= htmlspecialchars($iniciales) ?>
            <?php endif; ?>
        </div>
        <div class="flex-grow-1">
            <h2 class="portal-card-title mb-1"><?= htmlspecialchars($nombreCompleto) ?></h2>
            <div class="small" style="color:#94a3b8;">
                <i class="bi bi-envelope me-1"></i><?= htmlspecialchars($e['email'] ?? '—') ?>
                <span class="mx-2">·</span>
                <i class="bi bi-telephone me-1"></i><?= htmlspecialchars($e['telefono'] ?? '—') ?>
                <?php if (!empty($e['direccion'])): ?>
                    <span class="mx-2">·</span>
                    <i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($e['direccion']) ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- KPIs -->
<div class="row row-cols-1 row-cols-md-4 g-3 mb-4">
    <div class="col">
        <div class="portal-card p-3 h-100">
            <div class="portal-label mb-1">Grupos activos</div>
            <div class="portal-kpi-value" style="font-size:1.8rem;"><?= (int)($r['grupos_activos'] ?? 0) ?></div>
            <div class="small" style="color:#94a3b8;">de <?= (int)($r['grupos_totales'] ?? 0) ?> con este instructor</div>
        </div>
    </div>
    <div class="col">
        <div class="portal-card p-3 h-100">
            <div class="portal-label mb-1">Promedio general</div>
            <div class="portal-kpi-value" style="font-size:1.8rem;">
                <?= $promedioGeneral !== null ? number_format((float)$promedioGeneral, 1) : '—' ?>
            </div>
            <div class="small" style="color:#94a3b8;">escala 0 a 10</div>
        </div>
    </div>
    <div class="col">
        <div class="portal-card p-3 h-100">
            <div class="portal-label mb-1">Evaluaciones</div>
            <div class="portal-kpi-value" style="font-size:1.8rem;"><?= (int)($r['evaluaciones_totales'] ?? 0) ?></div>
            <div class="small" style="color:#94a3b8;">
                <?php $pend = (int)($r['evaluaciones_pendientes'] ?? 0); ?>
                <?= $pend > 0 ? $pend . ' sin calificar' : 'todas calificadas' ?>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="portal-card p-3 h-100">
            <div class="portal-label mb-1">Alumno desde</div>
            <div class="portal-kpi-value" style="font-size:1.8rem;">
                <?php
                $ts = !empty($e['fecha_inscripcion']) ? strtotime((string)$e['fecha_inscripcion']) : false;
                echo $ts ? date('d/m/Y', $ts) : '—';
                ?>
            </div>
            <div class="small" style="color:#94a3b8;">fecha de registro</div>
        </div>
    </div>
</div>

<!-- Matrículas con este instructor -->
<div class="portal-card p-3 p-md-4 mb-4">
    <h2 class="portal-card-title mb-3">Cursos con este instructor</h2>
    <?php if ($matriculas === []): ?>
        <p class="mb-0" style="color:#94a3b8;">Sin matrículas registradas.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-dark table-borderless align-middle mb-0">
                <thead>
                    <tr style="border-bottom:1px solid var(--portal-border);">
                        <th class="text-uppercase small" style="color:#94a3b8;">Curso</th>
                        <th class="text-uppercase small" style="color:#94a3b8;">Aula</th>
                        <th class="text-uppercase small" style="color:#94a3b8;">Matrícula</th>
                        <th class="text-uppercase small" style="color:#94a3b8;">Estado</th>
                        <th class="text-uppercase small" style="color:#94a3b8;">Asistencia</th>
                        <th class="text-uppercase small" style="color:#94a3b8;">Promedio</th>
                        <th class="text-uppercase small" style="color:#94a3b8;"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($matriculas as $m): ?>
                        <tr style="border-bottom:1px solid rgba(255,255,255,0.03);">
                            <td class="fw-semibold text-white">
                                <?= htmlspecialchars($m['curso']) ?>
                                <?php if (!empty($m['nivel'])): ?>
                                    <div class="small fw-normal" style="color:#94a3b8;"><?= htmlspecialchars($m['nivel']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td style="color:#94a3b8;"><?= htmlspecialchars($m['aula'] ?? '—') ?></td>
                            <td style="color:#94a3b8;">
                                <?php $ts = !empty($m['fecha_inscripcion']) ? strtotime((string)$m['fecha_inscripcion']) : false; ?>
                                <?= $ts ? date('d/M/Y', $ts) : '—' ?>
                            </td>
                            <td><span class="badge <?= $badgeEstado((string)$m['estado']) ?>"><?= htmlspecialchars($m['estado']) ?></span></td>
                            <td class="fw-bold"><?= (int)$m['asistencia_pct'] ?>%
                                <div class="small fw-normal" style="color:#94a3b8;"><?= (int)$m['asistencias_total'] ?> clase(s)</div>
                            </td>
                            <td class="fw-bold" style="color:#ffc107;"><?= (float)$m['promedio'] > 0 ? number_format((float)$m['promedio'], 1) : '—' ?></td>
                            <td class="text-end">
                                <a href="/instructor/calificaciones?grupo=<?= (int)$m['id_grupo'] ?>" class="btn btn-sm btn-outline-warning">
                                    <i class="bi bi-clipboard-data me-1"></i>Calificar
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="row g-4">
    <!-- Últimas calificaciones -->
    <div class="col-lg-7">
        <div class="portal-card p-3 p-md-4 h-100">
            <h2 class="portal-card-title mb-3">Últimas calificaciones</h2>
            <?php if ($evaluaciones === []): ?>
                <p class="mb-0" style="color:#94a3b8;">Todavía no hay evaluaciones registradas.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-dark table-borderless align-middle mb-0">
                        <thead>
                            <tr style="border-bottom:1px solid var(--portal-border);">
                                <th class="text-uppercase small" style="color:#94a3b8;">Evaluación</th>
                                <th class="text-uppercase small" style="color:#94a3b8;">Curso</th>
                                <th class="text-uppercase small" style="color:#94a3b8;">Fecha</th>
                                <th class="text-uppercase small" style="color:#94a3b8;">Nota</th>
                                <th class="text-uppercase small" style="color:#94a3b8;">Observaciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($evaluaciones as $ev):
                                $nota = $ev['calificacion'] !== null ? (float)$ev['calificacion'] : null;
                                $aprobado = $nota !== null && $nota >= 7;
                            ?>
                                <tr style="border-bottom:1px solid rgba(255,255,255,0.03);">
                                    <td class="fw-semibold text-white"><?= htmlspecialchars($ev['tipo']) ?></td>
                                    <td style="color:#94a3b8;"><?= htmlspecialchars($ev['curso']) ?></td>
                                    <td style="color:#94a3b8;">
                                        <?php $ts = !empty($ev['fecha_evaluacion']) ? strtotime((string)$ev['fecha_evaluacion']) : false; ?>
                                        <?= $ts ? date('d/m/Y', $ts) : '—' ?>
                                    </td>
                                    <td>
                                        <?php if ($nota !== null): ?>
                                            <span class="badge <?= $aprobado ? 'bg-success' : 'bg-danger' ?>"><?= number_format($nota, 1) ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Sin calificar</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="color:#94a3b8;"><?= htmlspecialchars($ev['observaciones_profesor'] ?? '—') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Últimos registros de asistencia -->
    <div class="col-lg-5">
        <div class="portal-card p-3 p-md-4 h-100">
            <h2 class="portal-card-title mb-3">Últimos registros de asistencia</h2>
            <?php if ($asistencias === []): ?>
                <p class="mb-0" style="color:#94a3b8;">Sin registros de asistencia.</p>
            <?php else: ?>
                <?php foreach ($asistencias as $a):
                    $presente = $a['presente'] === 'S';
                    $justificado = !empty($a['justificacion']);
                ?>
                    <div class="d-flex align-items-center justify-content-between gap-2 p-2 mb-2" style="border:1px solid var(--portal-border);border-radius:10px;background:rgba(255,255,255,0.01);">
                        <div>
                            <div class="fw-semibold small">
                                <?php $ts = strtotime((string)$a['fecha_clase']); ?>
                                <?= $ts ? date('d/m/Y', $ts) : htmlspecialchars((string)$a['fecha_clase']) ?>
                            </div>
                            <div class="small" style="color:#94a3b8;"><?= htmlspecialchars($a['curso']) ?></div>
                        </div>
                        <?php if ($justificado): ?>
                            <span class="badge bg-warning text-dark">Justificado</span>
                        <?php elseif ($presente): ?>
                            <span class="badge bg-success">Presente</span>
                        <?php else: ?>
                            <span class="badge bg-danger">Ausente</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
