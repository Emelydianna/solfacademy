<?php
use App\Application\Formato\Dinero;
use App\Application\Security\Csrf;

$e = static fn ($valor): string => htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES, 'UTF-8');
$esCurso = $seccion === 'cursos';
$esInstrumento = $seccion === 'instrumentos';
$singular = ['cursos' => 'curso', 'categorias' => 'categoría', 'instrumentos' => 'instrumento'][$seccion];
$titulos = ['cursos' => 'Gestión de Cursos', 'categorias' => 'Gestión de Categorías', 'instrumentos' => 'Gestión de Instrumentos'];
$subtitulos = [
    'cursos' => 'Administración de programas de estudio, tarifas e instructores.',
    'categorias' => 'Organiza las áreas de estudio y sus instrumentos.',
    'instrumentos' => 'Administra los instrumentos, su categoría y disponibilidad.',
];
$base = '/admin/' . $seccion;
$url = static function (array $cambios = []) use ($base, $listado): string {
    $params = array_merge($listado['filtros'], ['pagina' => $listado['pagina']], $cambios);
    return $base . '?' . http_build_query(array_filter($params, static fn ($v): bool => $v !== '' && $v !== null));
};
$claveId = ['cursos' => 'id_curso', 'categorias' => 'id_categoria', 'instrumentos' => 'id_instrumento'][$seccion];
?>
<div class="admin-academica">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="portal-title mb-1"><?= $e($titulos[$seccion]) ?></h1>
            <p class="portal-subtitle mb-0"><?= $e($subtitulos[$seccion]) ?></p>
        </div>
        <a href="<?= $e($url()) ?>#formulario-academico" class="btn admin-btn-primary">
            <i class="bi bi-plus-lg me-2"></i>AGREGAR <?= $esCurso ? 'NUEVO CURSO' : ($esInstrumento ? 'INSTRUMENTO' : 'CATEGORÍA') ?>
        </a>
    </div>

    <?php if (!$esCurso): ?>
        <nav class="nav admin-tabs mb-4" aria-label="Catálogos académicos">
            <a href="/admin/categorias" class="nav-link <?= !$esInstrumento ? 'active' : '' ?>" <?= !$esInstrumento ? 'aria-current="page"' : '' ?>>Categorías</a>
            <a href="/admin/instrumentos" class="nav-link <?= $esInstrumento ? 'active' : '' ?>" <?= $esInstrumento ? 'aria-current="page"' : '' ?>>Instrumentos</a>
        </nav>
    <?php endif; ?>

    <?php require __DIR__ . '/../../partials/flash.php'; ?>

    <form method="get" action="<?= $e($base) ?>" class="portal-card p-3 mb-4" role="search">
        <div class="row g-2 align-items-end">
            <div class="col-12 <?= $esCurso ? 'col-xl-6' : 'col-md' ?>">
                <label for="busqueda-academica" class="form-label">Buscar <?= $e($seccion) ?></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
                    <input id="busqueda-academica" type="search" name="q" maxlength="150" class="form-control" value="<?= $e($listado['filtros']['q']) ?>" placeholder="<?= $esCurso ? 'Nombre, instructor, instrumento o categoría…' : 'Nombre o descripción…' ?>">
                </div>
            </div>
            <?php if ($esCurso): ?>
                <div class="col-6 col-xl">
                    <label for="filtro-nivel" class="form-label">Nivel</label>
                    <select id="filtro-nivel" name="nivel" class="form-select">
                        <option value="">Todos los niveles</option>
                        <?php foreach ($opciones['niveles'] as $nivel): ?>
                            <option value="<?= $e($nivel) ?>" <?= $listado['filtros']['nivel'] === $nivel ? 'selected' : '' ?>><?= $e($nivel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <?php if ($esCurso || $esInstrumento): ?>
                <div class="col-6 col-md">
                    <label for="filtro-estado" class="form-label"><?= $esCurso ? 'Estado' : 'Disponibilidad' ?></label>
                    <select id="filtro-estado" name="estado" class="form-select">
                        <option value="">Todos</option>
                        <option value="1" <?= $listado['filtros']['estado'] === '1' ? 'selected' : '' ?>><?= $esCurso ? 'Activo' : 'Disponible' ?></option>
                        <option value="0" <?= $listado['filtros']['estado'] === '0' ? 'selected' : '' ?>><?= $esCurso ? 'Inactivo' : 'No disponible' ?></option>
                    </select>
                </div>
            <?php endif; ?>
            <div class="col-auto d-flex gap-2">
                <button class="btn admin-btn-primary" type="submit">Filtrar</button>
                <a class="btn admin-btn-outline" href="<?= $e($base) ?>">Limpiar</a>
            </div>
        </div>
    </form>

    <div class="row g-4 align-items-start">
        <div class="col-12 col-xl-8">
            <section class="portal-card overflow-hidden" aria-labelledby="titulo-listado">
                <div class="p-3 p-md-4 d-flex justify-content-between align-items-center gap-2">
                    <h2 id="titulo-listado" class="portal-card-title mb-0"><?= $esCurso ? 'Programas de estudio' : ($esInstrumento ? 'Instrumentos registrados' : 'Categorías de estudio') ?></h2>
                    <span class="admin-count"><?= (int) $listado['total'] ?> registros</span>
                </div>
                <p class="small admin-muted px-3 d-md-none">Desliza la tabla para ver todas las columnas y acciones.</p>
                <div class="table-responsive">
                    <table class="table admin-table align-middle mb-0">
                        <caption class="visually-hidden">Listado de <?= $e($seccion) ?></caption>
                        <thead><tr>
                            <th scope="col">ID</th><th scope="col"><?= $e(mb_strtoupper($singular)) ?></th>
                            <?php if ($esCurso): ?>
                                <th scope="col">INSTRUCTORES</th><th scope="col">PRECIO</th><th scope="col">ESTADO</th>
                            <?php elseif ($esInstrumento): ?>
                                <th scope="col">CATEGORÍA</th><th scope="col">ALQUILER</th><th scope="col">DISPONIBILIDAD</th>
                            <?php else: ?>
                                <th scope="col">INSTRUMENTOS</th>
                            <?php endif; ?>
                            <th scope="col" class="text-end">ACCIONES</th>
                        </tr></thead>
                        <tbody>
                        <?php if (!$listado['filas']): ?>
                            <tr><td colspan="<?= $esCurso || $esInstrumento ? 6 : 4 ?>" class="text-center py-5">
                                <i class="bi bi-search fs-3 d-block mb-2"></i>No se encontraron registros. Prueba otros filtros o agrega uno nuevo.
                            </td></tr>
                        <?php endif; ?>
                        <?php foreach ($listado['filas'] as $fila): $id = (int) $fila[$claveId]; ?>
                            <tr class="<?= $id === $idEdicion ? 'admin-row-selected' : '' ?>">
                                <td class="admin-muted">#<?= $id ?></td>
                                <td class="admin-name-cell">
                                    <strong><?= $e($fila['nombre']) ?></strong>
                                    <small class="d-block admin-muted mt-1"><?= $e($esCurso ? $fila['instrumento'] . ' · ' . $fila['categoria'] . ' · ' . $fila['nivel'] : ($fila['descripcion'] ?: 'Sin descripción')) ?></small>
                                </td>
                                <?php if ($esCurso): ?>
                                    <td><?= $e($fila['instructores'] ?: 'Sin asignar') ?></td>
                                    <td class="text-nowrap"><?= $e(Dinero::monto((float) $fila['precio'])) ?></td>
                                    <td>
                                        <span class="admin-badge <?= $fila['activo'] ? 'admin-badge-success' : 'admin-badge-danger' ?>"><?= $fila['activo'] ? 'Activo' : 'Inactivo' ?></span>
                                        <?php if (!$fila['disponible']): ?><small class="d-block admin-muted mt-1">No disponible</small><?php endif; ?>
                                    </td>
                                <?php elseif ($esInstrumento): ?>
                                    <td><?= $e($fila['categoria']) ?></td>
                                    <td class="text-nowrap"><?= $e(Dinero::monto((float) $fila['precio_alquiler'])) ?></td>
                                    <td><span class="admin-badge <?= $fila['disponible'] ? 'admin-badge-success' : 'admin-badge-warning' ?>"><?= $fila['disponible'] ? 'Disponible' : 'No disponible' ?></span></td>
                                <?php else: ?>
                                    <td><?= (int) $fila['instrumentos_count'] ?></td>
                                <?php endif; ?>
                                <td>
                                    <div class="d-flex gap-1 justify-content-end">
                                        <?php if ($esCurso): ?>
                                            <a href="/admin/grupos?curso=<?= $id ?>" class="btn admin-icon-btn admin-edit" aria-label="Ver grupos de <?= $e($fila['nombre']) ?>" title="Ver grupos"><i class="bi bi-diagram-3" aria-hidden="true"></i></a>
                                            <a href="/admin/grupos?curso=<?= $id ?>#formulario-grupo" class="btn admin-icon-btn admin-edit" aria-label="Crear grupo para <?= $e($fila['nombre']) ?>" title="Crear grupo"><i class="bi bi-plus-circle" aria-hidden="true"></i></a>
                                        <?php endif; ?>
                                        <a href="<?= $e($url(['editar' => $id])) ?>#formulario-academico" class="btn admin-icon-btn admin-edit" aria-label="Editar <?= $e($fila['nombre']) ?>" title="Editar"><i class="bi bi-pencil-square" aria-hidden="true"></i></a>
                                        <form method="post" action="<?= $e($base . '/' . $id . ($esCurso ? '/estado' : '/eliminar')) ?>" data-confirm="<?= $e($esCurso ? ($fila['activo'] ? '¿Desactivar este curso? Dejará de aparecer en el catálogo público. Se conservarán sus grupos, matrículas y pagos.' : '¿Reactivar este curso? Volverá al catálogo público si está disponible.') : '¿Eliminar este registro? Solo se permitirá si no tiene registros relacionados.') ?>">
                                            <?= Csrf::campo() ?>
                                            <?php if ($esCurso): ?><input type="hidden" name="activo" value="<?= $fila['activo'] ? '0' : '1' ?>"><?php endif; ?>
                                            <button type="submit" class="btn admin-icon-btn <?= $esCurso && !$fila['activo'] ? 'admin-restore' : 'admin-delete' ?>" title="<?= $esCurso ? ($fila['activo'] ? 'Desactivar' : 'Reactivar') : 'Eliminar' ?>" aria-label="<?= $e(($esCurso ? ($fila['activo'] ? 'Desactivar ' : 'Reactivar ') : 'Eliminar ') . $fila['nombre']) ?>"><i class="bi <?= $esCurso ? ($fila['activo'] ? 'bi-pause-circle' : 'bi-arrow-counterclockwise') : 'bi-trash' ?>" aria-hidden="true"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="p-3 d-flex flex-wrap justify-content-between align-items-center gap-3 border-top admin-border">
                    <span class="small admin-muted">Mostrando <?= (int) $listado['desde'] ?>–<?= (int) $listado['hasta'] ?> de <?= (int) $listado['total'] ?> <?= $e($seccion) ?></span>
                    <nav aria-label="Paginación"><ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= $listado['pagina'] === 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= $e($url(['pagina' => max(1, $listado['pagina'] - 1)])) ?>" <?= $listado['pagina'] === 1 ? 'tabindex="-1" aria-disabled="true"' : '' ?>>Anterior</a></li>
                        <?php for ($p = max(1, $listado['pagina'] - 2); $p <= min($listado['paginas'], $listado['pagina'] + 2); $p++): ?>
                            <li class="page-item <?= $p === $listado['pagina'] ? 'active' : '' ?>"><a class="page-link" href="<?= $e($url(['pagina' => $p])) ?>" <?= $p === $listado['pagina'] ? 'aria-current="page"' : '' ?>><?= $p ?></a></li>
                        <?php endfor; ?>
                        <li class="page-item <?= $listado['pagina'] === $listado['paginas'] ? 'disabled' : '' ?>"><a class="page-link" href="<?= $e($url(['pagina' => min($listado['paginas'], $listado['pagina'] + 1)])) ?>" <?= $listado['pagina'] === $listado['paginas'] ? 'tabindex="-1" aria-disabled="true"' : '' ?>>Siguiente</a></li>
                    </ul></nav>
                </div>
            </section>
        </div>
        <div class="col-12 col-xl-4">
            <?php require __DIR__ . '/formulario.php'; ?>
        </div>
    </div>
</div>
<script src="/js/admin-academica.js" defer></script>
