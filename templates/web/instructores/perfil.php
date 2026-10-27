<?php $e = static fn ($v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8'); ?>
<section class="instructor-profile-section py-5">
    <div class="container"><div class="row g-4">
        <div class="col-lg-4"><div class="card content-card instructor-profile-card"><div class="card-body p-4 text-center">
            <?php $claseAvatar = 'instructor-photo-lg'; require __DIR__ . '/avatar.php'; ?>
            <h1 class="profile-name"><?= $e($instructor['nombre'] . ' ' . $instructor['apellido']) ?></h1>
            <p class="instructor-role">Instructor de SolfAcademy</p>
            <p class="content-text small">Sin valoraciones</p>
            <?php if ($instructor['email']): ?><a href="mailto:<?= $e($instructor['email']) ?>" class="btn btn-accent w-100 fw-semibold">CONTACTAR</a><?php else: ?><p class="content-text mb-0">Contacto a través de administración.</p><?php endif; ?>
        </div></div></div>
        <div class="col-lg-8">
            <div class="card content-card mb-4"><div class="card-body p-4">
                <h2 class="content-card-title h4">Experiencia y especialidades</h2>
                <p class="content-text"><?= (int) $instructor['anios_experiencia'] ?> años de experiencia musical.</p>
                <?php if (!$instructor['especialidades']): ?><p class="content-text mb-0">Especialidades pendientes de publicación.</p><?php else: ?>
                    <ul class="list-unstyled detail-list mb-0"><?php foreach ($instructor['especialidades'] as $especialidad): ?><li><i class="bi bi-check-circle-fill"></i><?= $e($especialidad['nombre']) ?> · <?= $e(ucfirst($especialidad['nivel_dominio'])) ?> · <?= (int) $especialidad['anios_experiencia_i'] ?> años</li><?php endforeach; ?></ul>
                <?php endif; ?>
            </div></div>
            <div class="card content-card"><div class="card-body p-4">
                <h2 class="content-card-title h4">Cursos que imparte</h2>
                <?php if (!$instructor['cursos']): ?><p class="content-text mb-0">Este instructor aún no tiene cursos publicados con grupos activos o planeados.</p><?php else: ?>
                    <div class="row row-cols-1 row-cols-md-2 g-3"><?php foreach ($instructor['cursos'] as $curso): ?><div class="col">
                        <a href="/cursos/<?= (int) $curso['id_curso'] ?>" class="course-mini-card d-flex text-decoration-none">
                            <div class="course-mini-image"><i class="bi bi-music-note-beamed"></i></div><div class="course-mini-body">
                                <span class="mini-instrument"><?= $e($curso['instrumento']) ?></span><h3 class="mini-title h6 mb-1"><?= $e($curso['nombre']) ?></h3>
                                <span class="mini-meta"><?= $e($curso['nivel']) ?> · <?= $e(\App\Application\Formato\Dinero::monto((float) $curso['precio'])) ?></span>
                            </div>
                        </a>
                    </div><?php endforeach; ?></div>
                <?php endif; ?>
            </div></div>
        </div>
    </div></div>
</section>
