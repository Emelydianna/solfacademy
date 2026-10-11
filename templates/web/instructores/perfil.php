<!-- ============================== -->
<!-- Perfil de instructor           -->
<!-- ============================== -->
<section class="instructor-profile-section py-5">
    <div class="container">
        <div class="row g-4">

            <!-- Columna lateral: foto y contacto -->
            <div class="col-lg-4">
                <div class="card content-card instructor-profile-card">
                    <div class="card-body p-4 text-center">
                        <!-- TODO: sustituir por la foto real del instructor cuando haya assets -->
                        <div class="instructor-photo-lg mx-auto mb-3">
                            <?= htmlspecialchars(mb_strtoupper(mb_substr((string) $instructor['nombre'], 0, 1) . mb_substr((string) $instructor['apellido'], 0, 1))) ?>
                        </div>
                        <h1 class="profile-name"><?= htmlspecialchars((string) $instructor['nombre'] . ' ' . $instructor['apellido']) ?></h1>
                        <p class="instructor-role mb-2"><?= htmlspecialchars((string) $instructor['rol']) ?></p>
                        <div class="stars mb-3" aria-label="Valoración de <?= htmlspecialchars((string) $instructor['valoracion']) ?> de 5">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="bi <?= $i <= (int) round((float) $instructor['valoracion']) ? 'bi-star-fill' : 'bi-star' ?>"></i>
                            <?php endfor; ?>
                            <span class="rating-number">
                                <?= htmlspecialchars((string) $instructor['valoracion']) ?> ·
                                <?= (int) $instructor['num_resenas'] ?> reseñas
                            </span>
                        </div>
                        <a href="mailto:<?= htmlspecialchars((string) $instructor['email']) ?>" class="btn btn-accent w-100 fw-semibold">CONTACTAR</a>
                    </div>
                </div>
            </div>

            <!-- Columna principal -->
            <div class="col-lg-8">

                <!-- Biografía -->
                <div class="card content-card mb-4">
                    <div class="card-body p-4">
                        <h4 class="content-card-title">Biografía</h4>
                        <p class="content-text mb-0"><?= htmlspecialchars((string) $instructor['biografia']) ?></p>
                    </div>
                </div>

                <!-- Especialidades y experiencia -->
                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="card content-card h-100">
                            <div class="card-body p-4">
                                <h4 class="content-card-title">Especialidades</h4>
                                <ul class="list-unstyled detail-list mb-0">
                                    <?php foreach ($instructor['especialidades'] ?? [] as $especialidad): ?>
                                        <li>
                                            <i class="bi bi-check-circle-fill"></i>
                                            <?= htmlspecialchars((string) $especialidad) ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card content-card h-100">
                            <div class="card-body p-4">
                                <h4 class="content-card-title">Experiencia</h4>
                                <ul class="list-unstyled detail-list mb-0">
                                    <?php foreach ($instructor['experiencia'] ?? [] as $experiencia): ?>
                                        <li>
                                            <i class="bi bi-briefcase-fill"></i>
                                            <?= htmlspecialchars((string) $experiencia) ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cursos que imparte -->
                <div class="card content-card">
                    <div class="card-body p-4">
                        <h4 class="content-card-title">Cursos que imparte</h4>
                        <?php if (empty($instructor['cursos'])): ?>
                            <p class="content-text mb-0">Este instructor aún no tiene cursos publicados en el catálogo.</p>
                        <?php else: ?>
                            <div class="row row-cols-1 row-cols-md-2 g-3">
                                <?php foreach ($instructor['cursos'] as $curso): ?>
                                    <div class="col">
                                        <a href="/cursos/<?= (int) $curso['id_curso'] ?>" class="course-mini-card d-flex text-decoration-none">
                                            <div class="course-mini-image">
                                                <i class="bi <?= htmlspecialchars((string) $curso['icono']) ?>"></i>
                                            </div>
                                            <div class="course-mini-body">
                                                <span class="mini-instrument"><?= htmlspecialchars((string) $curso['instrumento']) ?></span>
                                                <h6 class="mini-title mb-1"><?= htmlspecialchars((string) $curso['nombre']) ?></h6>
                                                <span class="mini-meta">
                                                    <?= htmlspecialchars((string) $curso['nivel']) ?> ·
                                                    <?= htmlspecialchars(number_format((float) $curso['precio'], 0, ',', '.')) ?>€/mes
                                                </span>
                                            </div>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

        </div>
    </div>
</section>
