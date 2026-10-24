<?php
$data = $data ?? ['kpis'=>[], 'clasesHoy'=>[], 'diaHoy'=>date('l'), 'avisos'=>[]];
$usuario = $usuario ?? [];
$nombre = explode(' ', $usuario['nombre'] ?? 'Carlos')[0];
?>
<div class="mb-4">
    <h1 class="portal-title mb-1">¡Hola de nuevo, <?= htmlspecialchars($nombre) ?>! 👋</h1>
    <p class="portal-subtitle mb-0">Panel de Instructor. Tienes <?= count($data['clasesHoy']) ?> clases programadas para hoy.</p>
</div>

<div class="row row-cols-1 row-cols-md-4 g-3 mb-4">
    <?php foreach ($data['kpis'] as $k): ?>
        <div class="col">
            <div class="portal-card p-3 h-100">
                <div class="portal-label mb-1"><?= htmlspecialchars($k['titulo']) ?></div>
                <div class="portal-kpi-value" style="font-size:1.8rem;"><?= htmlspecialchars($k['valor']) ?></div>
                <div class="small" style="color:<?= !empty($k['verde']) ? '#34d399' : '#94a3b8' ?>;"><?= htmlspecialchars($k['sub']) ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="portal-card p-3 p-md-4">
            <h2 class="portal-card-title mb-3">Clases de Hoy (<?= htmlspecialchars($data['diaHoy']) ?>)</h2>
            <?php if (empty($data['clasesHoy'])): ?>
                <p class="mb-0" style="color:#94a3b8;">No hay clases programadas para hoy.</p>
            <?php else: ?>
                <?php foreach ($data['clasesHoy'] as $c): ?>
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 p-3 mb-2" style="border:1px solid var(--portal-border);border-radius:12px;background:rgba(255,255,255,0.01);">
                        <div>
                            <span class="badge" style="background:#ffc107;color:#000;"><?= substr($c['hora_inicio'],0,5) ?> - <?= substr($c['hora_fin'],0,5) ?></span>
                            <div class="fw-semibold mt-2"><?= htmlspecialchars($c['curso']) ?></div>
                            <div class="small" style="color:#94a3b8;"><?= htmlspecialchars($c['aula']) ?> · <?= (int)$c['alumnos'] ?> Alumnos</div>
                        </div>
                        <a href="/instructor/asistencias?grupo=<?= (int)$c['id_grupo'] ?>&fecha=<?= date('Y-m-d') ?>" class="btn btn-warning fw-bold">Iniciar Sesión</a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="portal-card p-3 p-md-4 h-100">
            <h2 class="portal-card-title mb-3">Avisos Administrativos</h2>
            <?php foreach ($data['avisos'] as $a): ?>
                <div class="mb-3 pb-3" style="border-bottom:1px solid var(--portal-border);">
                    <div class="fw-semibold"><?= htmlspecialchars($a['titulo']) ?></div>
                    <div class="small" style="color:#94a3b8;"><?= htmlspecialchars($a['texto']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
