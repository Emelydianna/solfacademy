<?php
$e = static fn ($v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$base = '/admin/aulas';
$clases = ['Disponible' => 'admin-badge-success', 'Ocupada' => 'admin-badge-danger', 'Mantenimiento' => 'admin-badge-warning', 'Inactiva' => 'admin-badge-danger'];
?>
<div class="admin-academica">
    <div class="d-flex flex-wrap justify-content-between gap-3 mb-4"><div><h1 class="portal-title mb-1">Gestión de Aulas de Clase</h1><p class="portal-subtitle mb-0">Control de aforos, equipamiento musical y disponibilidad según agenda.</p></div><a href="/admin/aulas/nuevo" class="btn admin-btn-primary align-self-start"><i class="bi bi-plus-lg me-2"></i>AGREGAR AULA</a></div>
    <form action="/admin/aulas" method="get" class="portal-card p-3 mb-3" role="search"><div class="row g-2 align-items-end">
        <div class="col-12 col-lg-7"><label for="q-aulas" class="form-label">Buscar aulas</label><div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span><input type="search" id="q-aulas" name="q" maxlength="150" class="form-control" placeholder="ID, nombre, ubicación o equipamiento…" value="<?= $e($listado['filtros']['q']) ?>"></div></div>
        <div class="col col-lg"><label for="filtro-estado" class="form-label">Disponibilidad</label><select name="estado" id="filtro-estado" class="form-select"><option value="">Todos los estados</option><?php foreach (array_keys($clases) as $estado): ?><option value="<?= $estado ?>" <?= $listado['filtros']['estado'] === $estado ? 'selected' : '' ?>><?= $estado === 'Ocupada' ? 'Ocupada según agenda' : ($estado === 'Disponible' ? 'Disponible ahora' : $estado) ?></option><?php endforeach; ?></select></div>
        <div class="col-auto d-flex gap-2"><button class="btn admin-btn-primary">Filtrar / Actualizar</button><a href="/admin/aulas" class="btn admin-btn-outline">Limpiar</a></div>
    </div></form>
    <p class="small admin-muted mb-4">Consulta: <?= $e($listado['consulta']) ?> · <?= $e($listado['zona']) ?>. La disponibilidad se calcula al cargar o actualizar; no indica presencia física ni horario de apertura.</p>
    <section class="portal-card overflow-hidden"><div class="d-flex flex-wrap justify-content-between gap-3 p-4"><h2 class="portal-card-title mb-0">Espacios de Ensayo y Clases Teóricas</h2><span class="admin-count"><?= (int) $listado['total'] ?> aulas configuradas</span></div>
        <p class="small admin-muted px-4 d-lg-none">Desliza la tabla para ver todas las columnas.</p>
        <div class="table-responsive"><table class="table admin-table align-middle mb-0"><thead><tr><th>ID</th><th>NOMBRE DE AULA</th><th>CAPACIDAD</th><th>EQUIPAMIENTO DISPONIBLE</th><th>ESTADO</th><th>HORARIO DE USO</th><th class="admin-actions">ACCIONES</th></tr></thead><tbody>
            <?php if (!$listado['filas']): ?><tr><td colspan="7" class="text-center py-5">No se encontraron aulas.</td></tr><?php endif; ?>
            <?php foreach ($listado['filas'] as $a): $id = (int) $a['id_aula']; ?>
                <tr><td class="admin-muted">#<?= $id ?></td><td class="admin-name-cell"><strong><?= $e($a['nombre']) ?></strong><small class="d-block admin-muted mt-1"><?= $e($a['ubicacion'] ?: 'Ubicación sin definir') ?></small></td><td class="text-nowrap"><?= (int) $a['capacidad'] ?> alumnos</td>
                    <td class="admin-equipment"><?php $recursos = preg_split('/[,;\r\n]+/', $a['recursos'] ?? '', -1, PREG_SPLIT_NO_EMPTY); foreach ($recursos as $recurso): ?><span class="admin-badge admin-badge-warning m-1"><?= $e(trim($recurso)) ?></span><?php endforeach; ?><?= !$recursos ? 'Sin equipamiento registrado' : '' ?></td>
                    <td><span class="admin-badge <?= $clases[$a['estado_visible']] ?>"><?= $a['estado_visible'] === 'Disponible' ? 'Disponible ahora' : ($a['estado_visible'] === 'Ocupada' ? 'Ocupada según agenda' : $e($a['estado_visible'])) ?></span></td>
                    <td><?php if (!$a['agenda']): ?><span class="admin-muted">Sin grupos abiertos</span><?php else: ?><?php foreach (array_slice($a['agenda'], 0, 2) as $g): ?><small class="d-block admin-muted">Grupo #<?= (int) $g['id_grupo'] ?></small><?php foreach ($g['horarios'] as $h): ?><small class="d-block text-nowrap"><?= $e($h['dia_semana'] . ' ' . $h['hora_inicio'] . '–' . $h['hora_fin']) ?></small><?php endforeach; ?><?php endforeach; ?><a class="small admin-edit" href="/admin/aulas/<?= $id ?>">Ver agenda y fechas</a><?php endif; ?></td>
                    <td class="admin-actions"><div class="d-flex gap-1"><a href="/admin/aulas/<?= $id ?>" class="btn admin-icon-btn admin-edit" title="Ver agenda" aria-label="Ver agenda de <?= $e($a['nombre']) ?>"><i class="bi bi-eye"></i></a><a href="/admin/aulas/<?= $id ?>/editar" class="btn admin-icon-btn admin-edit" title="Editar" aria-label="Editar <?= $e($a['nombre']) ?>"><i class="bi bi-pencil-square"></i></a><a href="/admin/aulas/<?= $id ?>#estado-aula" class="btn admin-icon-btn <?= $a['activa'] ? 'admin-delete' : 'admin-restore' ?>" title="Habilitación y mantenimiento" aria-label="Cambiar habilitación de <?= $e($a['nombre']) ?>"><i class="bi bi-sliders"></i></a></div></td>
                </tr>
            <?php endforeach; ?>
        </tbody></table></div>
        <?php require __DIR__ . '/../partials/paginacion.php'; ?>
    </section>
</div>
