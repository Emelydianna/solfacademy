<?php
$e = static fn ($v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$base = '/admin/instructores';
?>
<div class="admin-academica">
    <div class="d-flex flex-wrap justify-content-between gap-3 mb-4"><div><h1 class="portal-title mb-1">Gestión de Instructores</h1><p class="portal-subtitle mb-0">Administración de especialidades, cargas grupales y estado del profesorado.</p></div><a href="/admin/instructores/nuevo" class="btn admin-btn-primary align-self-start"><i class="bi bi-plus-lg me-2"></i>AGREGAR INSTRUCTOR</a></div>
    <form action="/admin/instructores" method="get" class="portal-card p-3 mb-4" role="search"><div class="row g-2 align-items-end">
        <div class="col-12 col-lg-6"><label for="q-instructores" class="form-label">Buscar instructores</label><div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span><input type="search" id="q-instructores" name="q" maxlength="150" class="form-control" placeholder="Nombre, especialidad, email o teléfono…" value="<?= $e($listado['filtros']['q']) ?>"></div></div>
        <div class="col-6 col-lg"><label for="filtro-instrumento" class="form-label">Especialidad</label><select id="filtro-instrumento" name="instrumento" class="form-select"><option value="">Todas</option><?php foreach ($instrumentos as $instrumento): ?><option value="<?= (int) $instrumento['id_instrumento'] ?>" <?= (string) $listado['filtros']['instrumento'] === (string) $instrumento['id_instrumento'] ? 'selected' : '' ?>><?= $e($instrumento['nombre']) ?></option><?php endforeach; ?></select></div>
        <div class="col-6 col-lg"><label for="filtro-estado" class="form-label">Estado</label><select id="filtro-estado" name="estado" class="form-select"><option value="">Todos</option><option value="1" <?= $listado['filtros']['estado'] === '1' ? 'selected' : '' ?>>Activo</option><option value="0" <?= $listado['filtros']['estado'] === '0' ? 'selected' : '' ?>>Suspendido</option></select></div>
        <div class="col-auto d-flex gap-2"><button class="btn admin-btn-primary">Filtrar</button><a href="/admin/instructores" class="btn admin-btn-outline">Limpiar</a></div>
    </div></form>
    <section class="portal-card overflow-hidden"><div class="d-flex flex-wrap justify-content-between gap-3 p-4"><h2 class="portal-card-title mb-0">Cuerpo Docente de la Academia</h2><span class="admin-count"><?= (int) $listado['total'] ?> instructores registrados</span></div>
        <p class="small admin-muted px-4 d-lg-none">Desliza la tabla para ver todas las columnas.</p>
        <div class="table-responsive"><table class="table admin-table align-middle mb-0"><thead><tr><th>FOTO</th><th>NOMBRE</th><th>ESPECIALIDAD</th><th>EMAIL</th><th>TELÉFONO</th><th>GRUPOS</th><th>CALIFICACIÓN</th><th>ESTADO</th><th class="admin-actions">ACCIONES</th></tr></thead><tbody>
            <?php if (!$listado['filas']): ?><tr><td colspan="9" class="text-center py-5">No se encontraron instructores.</td></tr><?php endif; ?>
            <?php foreach ($listado['filas'] as $i): $id = (int) $i['id_instructor']; ?>
                <tr><td><span class="admin-student-avatar"><?php if ($i['foto']): ?><img src="<?= $e($i['foto']) ?>" alt="Foto de <?= $e($i['nombre']) ?>" loading="lazy"><?php else: ?><?= $e(mb_strtoupper(mb_substr($i['nombre'], 0, 1) . mb_substr($i['apellido'], 0, 1))) ?><?php endif; ?></span></td>
                    <td class="admin-name-cell"><strong><?= $e($i['nombre'] . ' ' . $i['apellido']) ?></strong><small class="d-block admin-muted">#<?= $id ?> · <?= (int) $i['anios_experiencia'] ?> años de experiencia</small></td>
                    <td><?php foreach ($i['especialidades'] as $esp): ?><span class="admin-badge admin-badge-warning m-1"><?= $e($esp['nombre']) ?></span><?php endforeach; ?><?= !$i['especialidades'] ? 'Sin especialidades' : '' ?></td>
                    <td><?= $e($i['email'] ?: 'Sin email') ?></td><td class="text-nowrap"><?= $e($i['telefono'] ?: 'Sin teléfono') ?></td>
                    <td class="text-nowrap"><?= (int) $i['grupos_count'] ?> <?= $i['grupos_count'] === 1 ? 'grupo' : 'grupos' ?><small class="d-block admin-muted">Activos y planeados</small></td>
                    <td class="admin-muted">Sin valoraciones</td><td><span class="admin-badge <?= $i['activo'] ? 'admin-badge-success' : 'admin-badge-warning' ?>"><?= $i['activo'] ? 'Activo' : 'Suspendido' ?></span></td>
                    <td class="admin-actions"><div class="d-flex gap-1"><a href="/admin/instructores/<?= $id ?>" class="btn admin-icon-btn admin-edit" title="Ver ficha" aria-label="Ver ficha de <?= $e($i['nombre']) ?>"><i class="bi bi-eye"></i></a><a href="/admin/instructores/<?= $id ?>/editar" class="btn admin-icon-btn admin-edit" title="Editar" aria-label="Editar <?= $e($i['nombre']) ?>"><i class="bi bi-pencil-square"></i></a><a href="/admin/instructores/<?= $id ?>#estado-instructor" class="btn admin-icon-btn <?= $i['activo'] ? 'admin-delete' : 'admin-restore' ?>" title="<?= $i['activo'] ? 'Suspender' : 'Reactivar' ?>" aria-label="<?= $i['activo'] ? 'Suspender' : 'Reactivar' ?> <?= $e($i['nombre']) ?>"><i class="bi <?= $i['activo'] ? 'bi-person-dash' : 'bi-arrow-counterclockwise' ?>"></i></a></div></td>
                </tr>
            <?php endforeach; ?>
        </tbody></table></div>
        <?php require __DIR__ . '/../partials/paginacion.php'; ?>
    </section>
</div>
