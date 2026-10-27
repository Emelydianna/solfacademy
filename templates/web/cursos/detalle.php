<?php
$e = static fn ($valor): string => htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES, 'UTF-8');
?>
<section class="course-detail-header">
    <div class="container">
        <nav aria-label="breadcrumb"><ol class="breadcrumb breadcrumb-dark mb-3">
            <li class="breadcrumb-item"><a href="/">Inicio</a></li>
            <li class="breadcrumb-item"><a href="/cursos">Cursos</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= $e($curso['instrumento']) ?></li>
        </ol></nav>
        <span class="nivel-badge nivel-<?= $e(mb_strtolower($curso['nivel'])) ?> mb-3 d-inline-block">NIVEL <?= $e(mb_strtoupper($curso['nivel'])) ?></span>
        <h1 class="course-detail-title"><?= $e($curso['nombre']) ?></h1>
        <p class="course-detail-instructor mb-0"><i class="bi bi-person me-2"></i>Instructores: <?= $e($curso['instructor']) ?></p>
    </div>
</section>
<section class="course-detail-body py-5">
    <div class="container"><div class="row g-4">
        <div class="col-lg-8">
            <div class="card content-card mb-4"><div class="card-body p-4">
                <h2 class="content-card-title h4">Descripción del Curso</h2>
                <p class="content-text mb-0"><?= nl2br($e($curso['descripcion'] ?: 'Descripción pendiente de publicación.')) ?></p>
            </div></div>
            <div class="card content-card"><div class="card-body p-4">
                <h2 class="content-card-title h4">Grupos y horarios</h2>
                <?php if (!$curso['grupos']): ?>
                    <p class="content-text mb-0">Este curso aún no tiene grupos activos o planeados. Consulta en administración para conocer las próximas fechas.</p>
                <?php else: ?>
                    <?php foreach ($curso['grupos'] as $grupo): ?>
                        <section class="mb-4">
                            <h3 class="h6 text-white">Grupo #<?= (int) $grupo['id_grupo'] ?> · <?= $e($grupo['instructor']) ?></h3>
                            <p class="content-text mb-1"><?= $e($grupo['aula']) ?> · <?= $grupo['estado'] === 'planeado' ? 'Planeado' : 'Activo' ?></p>
                            <p class="content-text mb-1">Inicio: <?= $e($grupo['fecha_inicio']) ?><?= $grupo['fecha_fin'] ? ' · Fin: ' . $e($grupo['fecha_fin']) : '' ?></p>
                            <?php if ($grupo['horarios']): ?>
                                <ul class="content-text mb-0"><?php foreach ($grupo['horarios'] as $horario): ?><li><?= $e($horario) ?></li><?php endforeach; ?></ul>
                            <?php else: ?><p class="content-text mb-0">Horario por definir.</p><?php endif; ?>
                        </section>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div></div>
        </div>
        <div class="col-lg-4">
            <div class="card price-card mb-4"><div class="card-body p-4 text-center">
                <span class="price-label">Precio del curso (USD)</span>
                <div class="price-big"><?= $e(\App\Application\Formato\Dinero::monto((float) $curso['precio'])) ?></div>
                <p class="content-text mt-3 mb-0">Consulta en administración para gestionar tu matrícula.</p>
            </div></div>
            <div class="card content-card mb-4"><div class="card-body p-4">
                <h2 class="content-card-title h5">Detalles del programa</h2>
                <ul class="list-unstyled key-details mb-0">
                    <li><i class="bi bi-bookmark-star"></i><div><span>Categoría</span><strong><?= $e($curso['categoria']) ?></strong></div></li>
                    <li><i class="bi bi-music-note"></i><div><span>Instrumento</span><strong><?= $e($curso['instrumento']) ?></strong></div></li>
                    <li class="mb-0"><i class="bi bi-clock"></i><div><span>Duración</span><strong><?= $curso['duracion_semanas'] ? (int) $curso['duracion_semanas'] . ' semanas' : 'Por definir' ?></strong></div></li>
                </ul>
            </div></div>
            <div class="card content-card"><div class="card-body p-4">
                <h2 class="content-card-title h5">Requisitos</h2>
                <?php $requisito = $curso['requisito']; if ($requisito): ?>
                    <?php if ($requisito['nivel_minimo']): ?><p class="content-text"><?= $e($requisito['nivel_minimo']) ?></p><?php endif; ?>
                    <p class="content-text">Edad: <?= (int) $requisito['edad_minima'] ?>–<?= (int) $requisito['edad_maxima'] ?> años.</p>
                    <?php if ($requisito['conocimientos_previos']): ?><p class="content-text"><?= $e($requisito['conocimientos_previos']) ?></p><?php endif; ?>
                    <?php if ($requisito['descripcion_general']): ?><p class="content-text"><?= nl2br($e($requisito['descripcion_general'])) ?></p><?php endif; ?>
                    <?php if ($requisito['necesita_instrumento']): ?><p class="content-text mb-0">Requiere instrumento propio<?= $requisito['puede_alquilar'] ? ' o de alquiler' : '' ?>.</p><?php endif; ?>
                <?php else: ?><p class="content-text mb-0">Requisitos pendientes de publicación. Consulta con administración.</p><?php endif; ?>
            </div></div>
        </div>
    </div></div>
</section>
