<?php
$e = static fn ($v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$base = '/admin/requisitos';
?>
<div class="admin-academica">
    <div class="mb-4"><h1 class="portal-title mb-1">Gestión de Requisitos</h1><p class="portal-subtitle mb-0">Configura las condiciones de admisión de cada programa de estudio.</p></div>
    <?php if (!empty($flash)): ?><div role="status" class="portal-alert portal-alert--exito p-3 mb-4"><?= $e($flash['mensaje']) ?></div><?php endif; ?>
    <form method="get" action="/admin/requisitos" class="portal-card p-3 mb-4" role="search">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md"><label for="q-requisitos" class="form-label">Buscar cursos</label><div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span><input id="q-requisitos" type="search" name="q" maxlength="150" class="form-control" placeholder="Curso, categoría o instrumento…" value="<?= $e($listado['filtros']['q']) ?>"></div></div>
            <div class="col-6 col-md"><label for="filtro-configurado" class="form-label">Configuración</label><select id="filtro-configurado" name="configurado" class="form-select"><option value="">Todos</option><option value="1" <?= $listado['filtros']['configurado'] === '1' ? 'selected' : '' ?>>Configurados</option><option value="0" <?= $listado['filtros']['configurado'] === '0' ? 'selected' : '' ?>>Sin configurar</option></select></div>
            <div class="col-auto d-flex gap-2"><button class="btn admin-btn-primary">Filtrar</button><a href="/admin/requisitos" class="btn admin-btn-outline">Limpiar</a></div>
        </div>
    </form>
    <section class="portal-card overflow-hidden">
        <div class="p-4 d-flex justify-content-between"><h2 class="portal-card-title mb-0">Cursos y condiciones de ingreso</h2><span class="admin-count"><?= (int) $listado['total'] ?> cursos</span></div>
        <div class="table-responsive"><table class="table admin-table align-middle mb-0"><thead><tr><th>ID / CURSO</th><th>CATEGORÍA</th><th>NIVEL MÍNIMO</th><th>EDAD</th><th>INSTRUMENTO</th><th class="admin-actions">ACCIONES</th></tr></thead><tbody>
            <?php if (!$listado['filas']): ?><tr><td colspan="6" class="text-center py-5">No se encontraron cursos.</td></tr><?php endif; ?>
            <?php foreach ($listado['filas'] as $c): ?>
                <tr><td class="admin-name-cell"><strong>#<?= (int) $c['id_curso'] ?> · <?= $e($c['nombre']) ?></strong><small class="d-block admin-muted mt-1"><?= $c['id_requisito'] ? 'Requisitos configurados' : 'Sin requisitos configurados' ?></small></td><td><?= $e($c['categoria']) ?></td><td><?= $e($c['nivel_minimo'] ?: 'No especificado') ?></td><td><?= (int) $c['edad_minima'] ?>–<?= (int) $c['edad_maxima'] ?> años</td><td><?= $c['necesita_instrumento'] ? ($c['puede_alquilar'] ? 'Propio o alquiler' : 'Propio') : 'No requerido' ?></td><td class="admin-actions"><a class="btn admin-icon-btn admin-edit" href="/admin/requisitos/<?= (int) $c['id_curso'] ?>" title="Editar requisitos" aria-label="Editar requisitos de <?= $e($c['nombre']) ?>"><i class="bi bi-pencil-square"></i></a></td></tr>
            <?php endforeach; ?>
        </tbody></table></div>
        <?php require __DIR__ . '/../partials/paginacion.php'; ?>
    </section>
</div>
