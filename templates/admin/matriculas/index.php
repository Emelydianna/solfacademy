<?php
$e = static fn ($v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$base = '/admin/matriculas';
$badge = ['Pendiente' => 'admin-badge-warning', 'Activa' => 'admin-badge-success', 'Cancelada' => 'admin-badge-danger', 'Finalizada' => 'admin-badge-success'];
?>
<div class="admin-academica">
    <div class="d-flex flex-wrap justify-content-between gap-3 mb-4"><div><h1 class="portal-title mb-1">Gestión de Matrículas</h1><p class="portal-subtitle mb-0">Revisión de solicitudes, inscripciones activas e historial académico.</p></div></div>
    <form method="get" action="/admin/matriculas" class="portal-card p-3 mb-4" role="search"><div class="row g-2 align-items-end">
        <div class="col-12 col-lg-4"><label for="q-matriculas" class="form-label">Buscar</label><input id="q-matriculas" type="search" name="q" maxlength="150" class="form-control" placeholder="Estudiante, email, curso o #matrícula…" value="<?= $e($listado['filtros']['q']) ?>"></div>
        <div class="col-6 col-lg"><label for="filtro-curso" class="form-label">Curso</label><input id="filtro-curso" type="number" min="1" name="curso" class="form-control" placeholder="ID curso" value="<?= $e($listado['filtros']['curso']) ?>"></div>
        <div class="col-6 col-lg"><label for="filtro-estado" class="form-label">Estado</label><select id="filtro-estado" name="estado" class="form-select"><option value="">Todos</option><?php foreach (array_keys($badge) as $estado): ?><option value="<?= $estado ?>" <?= $listado['filtros']['estado'] === $estado ? 'selected' : '' ?>><?= $estado ?></option><?php endforeach; ?></select></div>
        <div class="col-auto d-flex gap-2"><button class="btn admin-btn-primary">Filtrar</button><a href="/admin/matriculas" class="btn admin-btn-outline">Limpiar</a></div>
    </div></form>
    <section class="portal-card overflow-hidden">
        <div class="p-4 d-flex justify-content-between"><h2 class="portal-card-title mb-0">Matrículas registradas</h2><span class="admin-count"><?= (int) $listado['total'] ?> matrículas</span></div>
        <div class="table-responsive"><table class="table admin-table align-middle mb-0"><thead><tr><th># / FECHA</th><th>ESTUDIANTE</th><th>CURSO / GRUPO</th><th>ESTADO</th><th>PRECIO</th><th>ANTIGÜEDAD</th><th class="admin-actions">ACCIONES</th></tr></thead><tbody>
            <?php if (!$listado['filas']): ?><tr><td colspan="7" class="text-center py-5">No se encontraron matrículas.</td></tr><?php endif; ?>
            <?php foreach ($listado['filas'] as $m): ?><tr><td><strong>#<?= (int) $m['id_inscripcion'] ?></strong><small class="d-block admin-muted"><?= $e($m['fecha']) ?></small></td><td class="admin-name-cell"><strong><?= $e($m['estudiante']) ?></strong><small class="d-block admin-muted"><?= $e($m['email']) ?></small></td><td class="admin-name-cell"><?= $e($m['curso']) ?><small class="d-block admin-muted">Grupo #<?= (int) $m['id_grupo'] ?> · <?= $e($m['instructor']) ?></small></td><td><span class="admin-badge <?= $badge[$m['estado']] ?>"><?= $e($m['estado']) ?></span></td><td><?= $m['precio_inscripcion'] !== null ? '$ ' . number_format((float) $m['precio_inscripcion'], 2) : 'Sin precio' ?></td><td><?= (int) $m['dias_antiguedad'] ?> días</td><td class="admin-actions"><a href="/admin/matriculas/<?= (int) $m['id_inscripcion'] ?>" class="btn admin-icon-btn admin-edit" title="Ver matrícula" aria-label="Ver matrícula #<?= (int) $m['id_inscripcion'] ?>"><i class="bi bi-eye"></i></a></td></tr><?php endforeach; ?>
        </tbody></table></div>
        <?php require __DIR__ . '/../partials/paginacion.php'; ?>
    </section>
</div>
