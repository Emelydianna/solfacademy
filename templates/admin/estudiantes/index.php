<?php
$e = static fn ($v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$base = '/admin/estudiantes';
?>
<div class="admin-academica">
    <div class="d-flex flex-wrap justify-content-between gap-3 mb-4">
        <div><h1 class="portal-title mb-1">Gestión de Estudiantes</h1><p class="portal-subtitle mb-0">Directorio de alumnos, fichas académicas y control de acceso.</p></div>
        <a href="/admin/estudiantes/nuevo" class="btn admin-btn-primary align-self-start"><i class="bi bi-plus-lg me-2"></i>AGREGAR ESTUDIANTE</a>
    </div>
    <form action="/admin/estudiantes" method="get" class="portal-card p-3 mb-4" role="search"><div class="row g-2 align-items-end">
        <div class="col-12 col-lg-6"><label for="q-estudiantes" class="form-label">Buscar estudiantes</label><div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span><input type="search" id="q-estudiantes" name="q" maxlength="150" class="form-control" placeholder="Nombre, email, teléfono o instrumento…" value="<?= $e($listado['filtros']['q']) ?>"></div></div>
        <div class="col-6 col-lg"><label for="filtro-estado" class="form-label">Estado académico</label><select name="estado" id="filtro-estado" class="form-select"><option value="">Todos</option><?php foreach (['Activo', 'Suspendido'] as $estado): ?><option value="<?= $estado ?>" <?= $listado['filtros']['estado'] === $estado ? 'selected' : '' ?>><?= $estado ?></option><?php endforeach; ?></select></div>
        <div class="col-6 col-lg"><label for="filtro-acceso" class="form-label">Acceso</label><select name="acceso" id="filtro-acceso" class="form-select"><option value="">Todos</option><?php foreach (['activo' => 'Activo', 'inactivo' => 'Inactivo', 'sin-cuenta' => 'Sin cuenta'] as $v => $label): ?><option value="<?= $v ?>" <?= $listado['filtros']['acceso'] === $v ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
        <div class="col-auto d-flex gap-2"><button class="btn admin-btn-primary">Filtrar</button><a href="/admin/estudiantes" class="btn admin-btn-outline">Limpiar</a></div>
    </div></form>
    <section class="portal-card overflow-hidden">
        <div class="d-flex justify-content-between gap-3 p-4"><h2 class="portal-card-title mb-0">Base de alumnos inscritos</h2><span class="admin-count"><?= (int) $listado['total'] ?> estudiantes</span></div>
        <p class="small admin-muted px-4 d-lg-none">Desliza la tabla para ver todas las columnas y acciones.</p>
        <div class="table-responsive"><table class="table admin-table align-middle mb-0"><thead><tr><th>FOTO</th><th>NOMBRE</th><th>EMAIL</th><th>TELÉFONO</th><th>INSTRUMENTOS</th><th>REGISTRO</th><th>ESTADO / ACCESO</th><th>ACCIONES</th></tr></thead><tbody>
            <?php if (!$listado['filas']): ?><tr><td colspan="8" class="text-center py-5">No se encontraron estudiantes.</td></tr><?php endif; ?>
            <?php foreach ($listado['filas'] as $fila): $id = (int) $fila['id_estudiante']; ?>
                <tr><td><span class="admin-student-avatar"><?php if ($fila['foto']): ?><img src="<?= $e($fila['foto']) ?>" alt="Foto de <?= $e($fila['nombre']) ?>" loading="lazy"><?php else: ?><?= $e(mb_strtoupper(mb_substr($fila['nombre'], 0, 1) . mb_substr($fila['apellido'], 0, 1))) ?><?php endif; ?></span></td>
                    <td class="admin-name-cell"><strong><?= $e($fila['nombre'] . ' ' . $fila['apellido']) ?></strong><small class="d-block admin-muted">#<?= $id ?></small></td><td><?= $e($fila['email']) ?></td><td class="text-nowrap"><?= $e($fila['telefono'] ?: 'Sin teléfono') ?></td>
                    <td><?= $e(implode(', ', $fila['instrumentos']) ?: 'Sin matrículas vigentes') ?></td><td class="text-nowrap"><?= $e($fila['fecha_inscripcion'] ?: 'Sin fecha registrada') ?></td>
                    <td><span class="admin-badge <?= $fila['estado'] === 'Activo' ? 'admin-badge-success' : 'admin-badge-warning' ?>"><?= $e($fila['estado']) ?></span><small class="d-block admin-muted mt-1"><?= !$fila['cuentas'] ? 'Sin cuenta' : ($fila['acceso'] ? 'Acceso activo' : 'Acceso inactivo') ?></small></td>
                    <td><div class="d-flex gap-1"><a class="btn admin-icon-btn admin-edit" href="/admin/estudiantes/<?= $id ?>" title="Ver ficha" aria-label="Ver ficha de <?= $e($fila['nombre']) ?>"><i class="bi bi-eye"></i></a><a class="btn admin-icon-btn admin-edit" href="/admin/estudiantes/<?= $id ?>/editar" title="Editar" aria-label="Editar <?= $e($fila['nombre']) ?>"><i class="bi bi-pencil-square"></i></a><a class="btn admin-icon-btn <?= $fila['estado'] === 'Activo' ? 'admin-delete' : 'admin-restore' ?>" href="/admin/estudiantes/<?= $id ?>#estado-estudiante" title="<?= $fila['estado'] === 'Activo' ? 'Suspender' : 'Reactivar' ?>" aria-label="<?= $fila['estado'] === 'Activo' ? 'Suspender' : 'Reactivar' ?> <?= $e($fila['nombre']) ?>"><i class="bi <?= $fila['estado'] === 'Activo' ? 'bi-person-dash' : 'bi-arrow-counterclockwise' ?>"></i></a></div></td>
                </tr>
            <?php endforeach; ?>
        </tbody></table></div>
        <?php require __DIR__ . '/../partials/paginacion.php'; ?>
    </section>
</div>
