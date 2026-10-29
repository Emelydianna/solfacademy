<!-- ============================== -->
<!-- Cabecera de página             -->
<!-- ============================== -->
<section class="page-header">
    <div class="container">
        <h1 class="page-header-title">Catálogo de Cursos</h1>
        <p class="page-header-text">Explora nuestra variedad de clases y encuentra tu camino ideal.</p>
    </div>
</section>

<!-- ============================== -->
<!-- Catálogo: filtros + resultados -->
<!-- ============================== -->
<section class="catalog-section py-5">
    <div class="container">
        <form method="get" action="/cursos">
            <div class="row g-4">

                <!-- Filtros laterales -->
                <div class="col-lg-3">
                    <div class="filter-panel">
                        <h5 class="filter-title mb-4">Filtros</h5>

                        <h6 class="filter-group-title">INSTRUMENTO</h6>
                        <?php foreach ($instrumentos ?? [] as $i => $instrumento): ?>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="instrumento[]"
                                       value="<?= htmlspecialchars($instrumento) ?>"
                                       id="instrumento-<?= $i ?>"
                                       <?= in_array($instrumento, $filtros['instrumento'] ?? [], true) ? 'checked' : '' ?>>
                                <label class="form-check-label filter-label" for="instrumento-<?= $i ?>">
                                    <?= htmlspecialchars($instrumento) ?>
                                </label>
                            </div>
                        <?php endforeach; ?>

                        <h6 class="filter-group-title">NIVEL</h6>
                        <?php foreach ($niveles ?? [] as $i => $nivel): ?>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="nivel[]"
                                       value="<?= htmlspecialchars($nivel) ?>"
                                       id="nivel-<?= $i ?>"
                                       <?= in_array($nivel, $filtros['nivel'] ?? [], true) ? 'checked' : '' ?>>
                                <label class="form-check-label filter-label" for="nivel-<?= $i ?>">
                                    <?= htmlspecialchars($nivel) ?>
                                </label>
                            </div>
                        <?php endforeach; ?>

                        <h6 class="filter-group-title">HORARIO</h6>
                        <?php foreach ($horarios ?? [] as $i => $horario): ?>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="horario[]"
                                       value="<?= htmlspecialchars($horario) ?>"
                                       id="horario-<?= $i ?>"
                                       <?= in_array($horario, $filtros['horario'] ?? [], true) ? 'checked' : '' ?>>
                                <label class="form-check-label filter-label" for="horario-<?= $i ?>">
                                    <?= htmlspecialchars($horario) ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Resultados -->
                <div class="col-lg-9">
                    <div class="row g-3 align-items-center mb-3">
                        <div class="col-md-7">
                            <div class="input-group">
                                <span class="input-group-text search-icon bg-white border-end-0">
                                    <i class="bi bi-search"></i>
                                </span>
                                <input type="search" name="q" class="form-control border-start-0" maxlength="150" aria-label="Buscar cursos"
                                       placeholder="Buscar por instrumento o profesor..."
                                       value="<?= htmlspecialchars($filtros['q'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="d-flex justify-content-md-end align-items-center gap-2">
                                <label for="orden" class="text-nowrap small text-muted">Ordenar por</label>
                                <select name="orden" id="orden" class="form-select w-auto" onchange="this.form.submit()">
                                    <option value="populares" <?= ($filtros['orden'] ?? 'populares') === 'populares' ? 'selected' : '' ?>>Más populares</option>
                                    <option value="precio-asc" <?= ($filtros['orden'] ?? '') === 'precio-asc' ? 'selected' : '' ?>>Precio: menor a mayor</option>
                                    <option value="precio-desc" <?= ($filtros['orden'] ?? '') === 'precio-desc' ? 'selected' : '' ?>>Precio: mayor a menor</option>
                                    <option value="az" <?= ($filtros['orden'] ?? '') === 'az' ? 'selected' : '' ?>>Alfabético (A-Z)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-accent btn-sm mb-3">APLICAR FILTROS</button>
                    <p class="catalog-count mb-4">
                        Mostrando <strong><?= count($cursos ?? []) ?></strong> de <strong><?= (int) ($totalCursos ?? 0) ?></strong> cursos
                    </p>

                    <?php if (empty($cursos)): ?>
                        <div class="empty-state text-center py-5">
                            <i class="bi bi-search mb-3"></i>
                            <h5 class="filter-title">No se encontraron cursos</h5>
                            <p class="text-muted mb-3">Prueba con otros filtros o vuelve a ver todos los cursos.</p>
                            <a href="/cursos" class="btn btn-accent px-4 fw-semibold">LIMPIAR FILTROS</a>
                        </div>
                    <?php else: ?>
                        <div class="row row-cols-1 row-cols-md-2 g-4">
                            <?php foreach ($cursos as $curso): ?>
                                <div class="col">
                                    <div class="card catalog-card h-100">
                                        <div class="catalog-card-image">
                                            <?php if (!empty($curso['imagen'])): ?>
                                                <img src="/cursos/<?= (int) $curso['id_curso'] ?>/imagen" alt="Imagen de referencia de <?= htmlspecialchars((string) $curso['nombre']) ?>" loading="lazy" class="catalog-card-img">
                                            <?php else: ?>
                                                <i class="bi <?= htmlspecialchars((string) $curso['icono']) ?>"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                                <span class="instrument-badge"><?= htmlspecialchars((string) $curso['instrumento']) ?></span>
                                                <span class="nivel-badge nivel-<?= htmlspecialchars(mb_strtolower((string) $curso['nivel'])) ?>">
                                                    <?= htmlspecialchars((string) $curso['nivel']) ?>
                                                </span>
                                            </div>
                                            <h5 class="catalog-card-title"><?= htmlspecialchars((string) $curso['nombre']) ?></h5>
                                            <p class="catalog-card-text"><?= htmlspecialchars((string) $curso['descripcion']) ?></p>
                                            <div class="instructor-line mb-0">
                                                <i class="bi bi-person me-1"></i>Impartido por <?= htmlspecialchars((string) $curso['instructor']) ?>
                                            </div>
                                        </div>
                                        <div class="card-footer catalog-card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
                                            <span class="price-tag"><?= htmlspecialchars(\App\Application\Formato\Dinero::monto((float) $curso['precio'])) ?></span>
                                            <span class="schedule-line"><i class="bi bi-clock me-1"></i><?= htmlspecialchars((string) $curso['horario']) ?></span>
                                            <!-- Inscripción requiere sesión: fase 2 de la aplicación -->
                                            <a href="/cursos/<?= (int) $curso['id_curso'] ?>" class="btn btn-accent btn-sm px-3 fw-semibold">VER CURSO</a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </form>
    </div>
</section>

<script>
    // Autoenvío del formulario al marcar o desmarcar un filtro
    document.querySelectorAll('.filter-panel input[type="checkbox"]').forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            this.form.submit();
        });
    });
</script>
