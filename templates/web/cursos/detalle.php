<!-- ============================== -->
<!-- Cabecera del curso             -->
<!-- ============================== -->
<section class="course-detail-header">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb breadcrumb-dark mb-3">
                <li class="breadcrumb-item"><a href="/">Inicio</a></li>
                <li class="breadcrumb-item"><a href="/cursos">Cursos</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars((string) $curso['instrumento']) ?></li>
            </ol>
        </nav>
        <span class="nivel-badge nivel-<?= mb_strtolower((string) $curso['nivel']) ?> mb-3 d-inline-block">
            NIVEL <?= mb_strtoupper((string) $curso['nivel']) ?>
        </span>
        <h1 class="course-detail-title"><?= htmlspecialchars((string) $curso['nombre']) ?></h1>
        <div class="d-flex align-items-center gap-2 course-detail-instructor">
            <!-- TODO: sustituir por la foto real del instructor cuando haya assets -->
            <span class="instructor-avatar-sm">
                <?= htmlspecialchars(mb_strtoupper(mb_substr((string) $curso['instructor'], 0, 1))) ?>
            </span>
            Impartido por <strong><?= htmlspecialchars((string) $curso['instructor']) ?></strong>
        </div>
    </div>
</section>

<!-- ============================== -->
<!-- Contenido                      -->
<!-- ============================== -->
<section class="course-detail-body py-5">
    <div class="container">
        <div class="row g-4">

            <!-- Columna principal -->
            <div class="col-lg-8">

                <!-- Descripción -->
                <div class="card content-card mb-4">
                    <div class="card-body p-4">
                        <h4 class="content-card-title">Descripción del Curso</h4>
                        <p class="content-text mb-0"><?= htmlspecialchars((string) $curso['descripcion_larga']) ?></p>
                    </div>
                </div>

                <!-- Temario (acordeón) -->
                <div class="card content-card mb-4">
                    <div class="card-body p-4">
                        <h4 class="content-card-title">Temario del Curso</h4>
                        <div class="accordion" id="temarioAccordeon">
                            <?php foreach ($curso['temario'] ?? [] as $i => $modulo): ?>
                                <div class="accordion-item temario-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button temario-button <?= $i > 0 ? 'collapsed' : '' ?>"
                                                type="button" data-bs-toggle="collapse"
                                                data-bs-target="#modulo-<?= $i ?>"
                                                aria-expanded="<?= $i === 0 ? 'true' : 'false' ?>"
                                                aria-controls="modulo-<?= $i ?>">
                                            <span class="modulo-num">Módulo <?= $i + 1 ?></span>
                                            <span><?= htmlspecialchars((string) $modulo['titulo']) ?></span>
                                        </button>
                                    </h2>
                                    <div id="modulo-<?= $i ?>"
                                         class="accordion-collapse collapse <?= $i === 0 ? 'show' : '' ?>"
                                         data-bs-parent="#temarioAccordeon">
                                        <div class="accordion-body">
                                            <p class="content-text mb-2"><?= htmlspecialchars((string) $modulo['descripcion']) ?></p>
                                            <span class="modulo-duracion"><i class="bi bi-clock me-1"></i><?= htmlspecialchars((string) $modulo['duracion']) ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Reseñas -->
                <div class="card content-card">
                    <div class="card-body p-4">
                        <h4 class="content-card-title">Reseñas de Alumnos</h4>
                        <?php if (empty($curso['reseñas'])): ?>
                            <p class="content-text mb-0">Sé el primero en dejar una reseña de este curso.</p>
                        <?php else: ?>
                            <?php foreach ($curso['reseñas'] as $resena): ?>
                                <div class="review-item">
                                    <div class="d-flex align-items-center gap-3 mb-2">
                                        <span class="testimonial-avatar"><?= htmlspecialchars((string) $resena['iniciales']) ?></span>
                                        <div>
                                            <div class="review-author"><?= htmlspecialchars((string) $resena['autor']) ?></div>
                                            <div class="stars">
                                                <?php for ($s = 1; $s <= 5; $s++): ?>
                                                    <i class="bi <?= $s <= (int) $resena['valoracion'] ? 'bi-star-fill' : 'bi-star' ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="content-text review-text mb-0"><?= htmlspecialchars((string) $resena['texto']) ?></p>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <!-- Columna lateral -->
            <div class="col-lg-4">

                <!-- Precio -->
                <div class="card price-card mb-4">
                    <div class="card-body p-4 text-center">
                        <span class="price-label">Precio del curso</span>
                        <div class="price-big"><?= htmlspecialchars(number_format((float) $curso['precio'], 0, ',', '.')) ?>€<small>/mes</small></div>
                        <!-- Inscripción requiere sesión de usuario: fase 2 de la aplicación -->
                        <a href="#" class="btn btn-accent w-100 fw-semibold mt-3">INSCRIBIRSE AHORA</a>
                    </div>
                </div>

                <!-- Detalles clave -->
                <div class="card content-card">
                    <div class="card-body p-4">
                        <h5 class="content-card-title">Detalles Clave</h5>
                        <ul class="list-unstyled key-details mb-0">
                            <li>
                                <i class="bi bi-calendar-event"></i>
                                <div>
                                    <span>Horario</span>
                                    <strong><?= htmlspecialchars((string) $curso['grupo']['horario']) ?></strong>
                                </div>
                            </li>
                            <li>
                                <i class="bi bi-door-open"></i>
                                <div>
                                    <span>Aula</span>
                                    <strong><?= htmlspecialchars((string) $curso['grupo']['aula']) ?></strong>
                                </div>
                            </li>
                            <li>
                                <i class="bi bi-clock"></i>
                                <div>
                                    <span>Duración</span>
                                    <strong><?= htmlspecialchars((string) $curso['grupo']['duracion']) ?></strong>
                                </div>
                            </li>
                            <li class="mb-0">
                                <i class="bi bi-journal-check"></i>
                                <div>
                                    <span>Requisito</span>
                                    <strong><?= htmlspecialchars((string) $curso['requisito']['nivel_minimo']) ?> · Desde <?= (int) $curso['requisito']['edad_minima'] ?> años</strong>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>

            </div>

        </div>
    </div>
</section>
