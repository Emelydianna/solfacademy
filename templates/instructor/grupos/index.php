<?php $grupos = $grupos ?? []; ?>
<div class="mb-4">
    <h1 class="portal-title mb-1">Mis Grupos Asignados</h1>
    <p class="portal-subtitle mb-0">Visualiza y gestiona las clases, horarios y listas de asistencia de cada grupo a tu cargo.</p>
</div>

<div class="row row-cols-1 row-cols-md-3 g-4">
    <?php foreach ($grupos as $g): ?>
        <div class="col">
            <div class="portal-card p-4 h-100 d-flex flex-column" style="border-radius:16px;">
                <div class="mb-2">
                    <span class="badge" style="background:rgba(255,193,7,0.15);color:#ffc107;"><?= htmlspecialchars($g['categoria']) ?> · <?= htmlspecialchars($g['nivel']) ?></span>
                </div>
                <h3 class="portal-card-title mb-2"><?= htmlspecialchars($g['curso']) ?></h3>
                <div class="small mb-1" style="color:#94a3b8;"><i class="bi bi-calendar-event me-1"></i><?= htmlspecialchars($g['dias'] ?: 'Sin horario') ?><?= $g['hora_inicio'] ? ', ' . substr($g['hora_inicio'],0,5) . ' - ' . substr($g['hora_fin'],0,5) : '' ?></div>
                <div class="small mb-1" style="color:#94a3b8;"><i class="bi bi-people me-1"></i><?= (int)$g['alumnos'] ?> Estudiantes</div>
                <div class="small mb-3" style="color:#94a3b8;"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($g['aula']) ?></div>
                <div class="mt-auto">
                    <a href="/instructor/grupos/<?= (int)$g['id_grupo'] ?>" class="btn btn-warning w-100 fw-bold">Ver Grupo</a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
