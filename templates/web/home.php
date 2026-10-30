<!-- ============================== -->
<!-- Hero                          -->
<!-- ============================== -->
<section class="hero-section">
    <div class="container">
        <div class="row align-items-center gy-4">
            <div class="col-lg-6">
                <span class="section-eyebrow">FORMACIÓN LAS 24 HORAS</span>
                <h1 class="hero-title">Aprende de los Mejores Músicos en <?= htmlspecialchars((string) ($config['nombre'] ?? 'SolfAcademy')) ?></h1>
                <p class="hero-text">Desde principiante hasta entusiasta. Ofrecemos clases personalizadas con instructores expertos, horarios flexibles y una comunidad vibrante. ¡Comienza tu viaje musical hoy!</p>
                <div class="d-flex flex-wrap gap-3 mt-4">
                    <a href="/registro" class="btn btn-accent px-4 fw-semibold">REGISTRARME AHORA</a>
                    <a href="/cursos" class="btn btn-outline-light px-4">VER CURSOS</a>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="hero-image-placeholder">
                    <img src="/img/aula_orquesta.png" alt="Aula de orquesta de la academia"
                         width="980" height="657">
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================== -->
<!-- Nuestros Cursos (CATEGORÍA)   -->
<!-- ============================== -->
<section class="courses-section py-5">
    <div class="container">
        <div class="text-center mb-5">
            <span class="section-eyebrow">PROGRAMAS DE ESTUDIO</span>
            <h2 class="section-title">Nuestros Cursos</h2>
        </div>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 justify-content-center">
            <?php foreach ($categorias ?? [] as $categoria): ?>
                <div class="col">
                    <div class="card course-card h-100">
                        <div class="card-body p-4">
                            <div class="course-icon mb-3">
                                <i class="bi <?= htmlspecialchars((string) $categoria['icono']) ?>"></i>
                            </div>
                            <h5 class="card-title text-white mb-2"><?= htmlspecialchars((string) $categoria['nombre']) ?></h5>
                            <p class="card-text course-card-text mb-0"><?= htmlspecialchars((string) $categoria['descripcion']) ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== -->
<!-- Nuestros Instructores         -->
<!-- ============================== -->
<section id="instructores" class="instructors-section py-5">
    <div class="container">
        <div class="text-center mb-5">
            <span class="section-eyebrow">APRENDE DE EXPERTOS</span>
            <h2 class="section-title">Nuestros Instructores</h2>
        </div>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 justify-content-center">
            <?php foreach ($instructores ?? [] as $instructor): ?>
                <div class="col">
                    <div class="card instructor-card h-100 text-center">
                        <a href="/instructores/<?= (int) $instructor['id_instructor'] ?>" class="instructor-card-link">
                            <div class="card-body p-4">
                            <?php $claseAvatar = 'instructor-photo'; require __DIR__ . '/instructores/avatar.php'; ?>
                            <h5 class="card-title mb-1"><?= htmlspecialchars((string) $instructor['nombre'] . ' ' . $instructor['apellido']) ?></h5>
                            <p class="instructor-role"><?= htmlspecialchars(implode(', ', array_column($instructor['especialidades'], 'nombre')) ?: 'Especialidades por definir') ?></p>
                            <p class="instructor-bio mb-2"><?= (int) $instructor['anios_experiencia'] ?> años de experiencia musical</p>
                            <small class="text-secondary">Sin valoraciones</small>
                            </div>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== -->
<!-- Testimonios                   -->
<!-- ============================== -->
<section class="testimonials-section py-5">
    <div class="container">
        <div class="text-center mb-5">
            <span class="section-eyebrow">EXPERIENCIAS REALES</span>
            <h2 class="testimonials-title">Lo que dicen nuestros alumnos</h2>
        </div>
        <div class="row row-cols-1 row-cols-lg-2 g-4 justify-content-center">
            <?php foreach ($testimonios ?? [] as $testimonio): ?>
                <div class="col">
                    <div class="card testimonial-card h-100">
                        <div class="card-body p-4">
                            <div class="testimonial-quote mb-3">
                                <i class="bi bi-quote"></i>
                            </div>
                            <p class="testimonial-text mb-4"><?= htmlspecialchars((string) $testimonio['texto']) ?></p>
                            <div class="d-flex align-items-center gap-3">
                                <span class="testimonial-avatar"><?= htmlspecialchars((string) $testimonio['iniciales']) ?></span>
                                <div>
                                    <div class="testimonial-author"><?= htmlspecialchars((string) $testimonio['autor']) ?></div>
                                    <div class="stars">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="bi <?= $i <= (int) $testimonio['valoracion'] ? 'bi-star-fill' : 'bi-star' ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
