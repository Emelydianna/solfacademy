<?php
use App\Application\Admin\GruposService;
use App\Application\Security\Csrf;
$e = static fn ($v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$base = '/admin/grupos';
?>
<div class="admin-academica">
    <div class="d-flex flex-wrap justify-content-between gap-3 mb-4">
        <div><h1 class="portal-title mb-1">Gestión de Grupos</h1><p class="portal-subtitle mb-0">Asignación de instructores, aulas, horarios y cupos por curso.</p></div>
        <a href="/admin/grupos<?= $listado['filtros']['curso'] ? '?curso=' . (int) $listado['filtros']['curso'] : '' ?>#formulario-grupo" class="btn admin-btn-primary align-self-start"><i class="bi bi-plus-lg me-2"></i>CREAR GRUPO</a>
    </div>
    <?php if ($flash): ?><div role="status" class="portal-alert portal-alert--exito p-3 mb-4"><?= $e($flash['mensaje']) ?></div><?php endif; ?>
    <form method="get" class="portal-card p-3 mb-4" action="/admin/grupos" role="search">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-lg-4"><label class="form-label" for="q-grupos">Buscar</label><input id="q-grupos" name="q" type="search" class="form-control" maxlength="150" placeholder="Curso, instructor, aula o #grupo…" value="<?= $e($listado['filtros']['q']) ?>"></div>
            <?php foreach (['curso' => ['Curso', 'cursos', 'id_curso'], 'instructor' => ['Instructor', 'instructores', 'id_instructor']] as $campo => [$label, $origen, $idCampo]): ?>
                <div class="col-6 col-lg"><label class="form-label" for="filtro-<?= $campo ?>"><?= $label ?></label><select id="filtro-<?= $campo ?>" name="<?= $campo ?>" class="form-select"><option value="">Todos</option>
                    <?php foreach ($opciones[$origen] as $o): ?><option value="<?= (int) $o[$idCampo] ?>" <?= (string) $listado['filtros'][$campo] === (string) $o[$idCampo] ? 'selected' : '' ?>><?= $e($o['nombre'] . (isset($o['apellido']) ? ' ' . $o['apellido'] : '')) ?></option><?php endforeach; ?>
                </select></div>
            <?php endforeach; ?>
            <div class="col-6 col-lg"><label class="form-label" for="filtro-estado">Estado</label><select id="filtro-estado" name="estado" class="form-select"><option value="">Todos</option><?php foreach (GruposService::ESTADOS as $estado): ?><option value="<?= $estado ?>" <?= $listado['filtros']['estado'] === $estado ? 'selected' : '' ?>><?= ucfirst($estado) ?></option><?php endforeach; ?></select></div>
            <div class="col-auto d-flex gap-2"><button class="btn admin-btn-primary">Filtrar</button><a href="/admin/grupos" class="btn admin-btn-outline">Limpiar</a></div>
        </div>
    </form>
    <div class="row g-4 align-items-start">
        <div class="col-12 col-xxl-7">
            <section class="portal-card overflow-hidden">
                <div class="p-3 d-flex justify-content-between gap-2"><h2 class="portal-card-title mb-0">Grupos del curso</h2><span class="admin-count"><?= (int) $listado['total'] ?> registros</span></div>
                <div class="table-responsive"><table class="table admin-table align-middle mb-0"><thead><tr><th>GRUPO / CURSO</th><th>INSTRUCTOR / AULA</th><th>HORARIOS</th><th>CUPO</th><th>ESTADO</th><th class="admin-actions">ACCIONES</th></tr></thead><tbody>
                    <?php if (!$listado['filas']): ?><tr><td colspan="6" class="text-center py-5">No se encontraron grupos.</td></tr><?php endif; ?>
                    <?php foreach ($listado['filas'] as $fila): ?>
                        <tr><td class="admin-name-cell"><strong>#<?= (int) $fila['id_grupo'] ?> · <?= $e($fila['curso']) ?></strong><small class="d-block admin-muted mt-1"><?= $e($fila['fecha_inicio']) ?> → <?= $e($fila['fecha_fin'] ?: 'Sin fin definido') ?></small></td>
                            <td><?= $e($fila['instructor']) ?><small class="d-block admin-muted mt-1"><?= $e($fila['aula']) ?></small></td>
                            <td><?php foreach ($fila['horarios'] as $h): ?><small class="d-block text-nowrap"><?= $e($h['dia_semana'] . ' ' . $h['hora_inicio'] . '–' . $h['hora_fin']) ?></small><?php endforeach; ?><?= !$fila['horarios'] ? 'Por definir' : '' ?></td>
                            <td class="text-nowrap"><?= (int) $fila['inscritos'] ?> / <?= (int) $fila['capacidad_maxima'] ?></td>
                            <td><span class="admin-badge <?= $fila['estado'] === 'activo' ? 'admin-badge-success' : ($fila['estado'] === 'cancelado' ? 'admin-badge-danger' : 'admin-badge-warning') ?>"><?= $e(ucfirst($fila['estado'])) ?></span></td>
                            <td class="text-nowrap admin-actions"><a class="btn admin-icon-btn admin-edit" href="/admin/grupos/<?= (int) $fila['id_grupo'] ?>" title="Ver grupo" aria-label="Ver grupo #<?= (int) $fila['id_grupo'] ?>"><i class="bi bi-eye"></i></a><a class="btn admin-icon-btn admin-edit" href="/admin/grupos?editar=<?= (int) $fila['id_grupo'] ?>#formulario-grupo" title="Editar" aria-label="Editar grupo #<?= (int) $fila['id_grupo'] ?>"><i class="bi bi-pencil-square"></i></a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody></table></div>
                <?php require __DIR__ . '/../partials/paginacion.php'; ?>
            </section>
        </div>
        <div class="col-12 col-xxl-5"><section id="formulario-grupo" class="portal-card p-3 p-md-4">
            <span class="admin-eyebrow"><?= $idEdicion ? 'EDITAR GRUPO #' . (int) $idEdicion : 'NUEVO GRUPO' ?></span>
            <h2 class="portal-card-title mt-2 mb-4">Curso, instructor y horarios</h2>
            <?php require __DIR__ . '/../partials/form-helpers.php'; ?>
            <form id="form-academico" action="/admin/grupos<?= $idEdicion ? '/' . (int) $idEdicion : '' ?>" method="post">
                <?= Csrf::campo() ?>
                <div class="mb-3"><label for="id_curso" class="form-label">Curso *</label><select id="id_curso" name="id_curso" required class="form-select<?= $err('id_curso') ?>" data-grupo-curso data-original="<?= (int) ($grupoOriginal['id_curso'] ?? 0) ?>"<?= $ariaError('id_curso') ?>><option value="">Selecciona un curso</option>
                    <?php foreach ($opciones['cursos'] as $o): ?><option value="<?= (int) $o['id_curso'] ?>" data-instrumento="<?= (int) $o['id_instrumento'] ?>" <?= (string) $valor('id_curso') === (string) $o['id_curso'] ? 'selected' : '' ?>><?= $e($o['nombre']) ?><?= !$o['activo'] || !$o['disponible'] ? ' (no disponible)' : '' ?></option><?php endforeach; ?>
                </select><?php $errorCampo('id_curso'); ?></div>
                <div class="mb-3"><label for="id_instructor" class="form-label">Instructor *</label><select id="id_instructor" name="id_instructor" required class="form-select<?= $err('id_instructor') ?>" data-grupo-instructor data-original="<?= (int) ($grupoOriginal['id_instructor'] ?? 0) ?>"<?= $ariaError('id_instructor') ?>><option value="">Selecciona un instructor</option>
                    <?php foreach ($opciones['instructores'] as $o):
                        $especialidades = array_column(array_filter($opciones['especialidades'], static fn ($s): bool => (int) $s['id_instructor'] === (int) $o['id_instructor']), 'id_instrumento'); ?>
                        <option value="<?= (int) $o['id_instructor'] ?>" data-activo="<?= (int) $o['activo'] ?>" data-instrumentos="<?= $e(implode(',', $especialidades)) ?>" <?= (string) $valor('id_instructor') === (string) $o['id_instructor'] ? 'selected' : '' ?>><?= $e($o['nombre'] . ' ' . $o['apellido']) ?><?= !$o['activo'] ? ' (inactivo)' : '' ?></option>
                    <?php endforeach; ?>
                </select><?php $errorCampo('id_instructor'); ?><p class="small admin-muted mt-2">Se filtran por instrumento y actividad. Las asignaciones anteriores se conservan para su revisión.</p></div>
                <div class="mb-3"><label for="id_aula" class="form-label">Aula *</label><select id="id_aula" name="id_aula" required class="form-select<?= $err('id_aula') ?>"<?= $ariaError('id_aula') ?>><option value="">Selecciona un aula</option><?php foreach ($opciones['aulas'] as $o): ?><option value="<?= (int) $o['id_aula'] ?>" <?= (string) $valor('id_aula') === (string) $o['id_aula'] ? 'selected' : '' ?>><?= $e($o['nombre']) ?> · <?= (int) $o['capacidad'] ?> lugares<?= !$o['activa'] ? ($o['tipo_inactividad'] === 'Mantenimiento' ? ' (mantenimiento)' : ' (inactiva)') : '' ?></option><?php endforeach; ?></select><?php $errorCampo('id_aula'); ?></div>
                <?php $input('capacidad_maxima', 'Capacidad máxima *', 'number', 'min="1" max="255" step="1" required'); ?>
                <div class="row"><div class="col-sm-6"><?php $input('fecha_inicio', 'Fecha de inicio *', 'date', 'required'); ?></div><div class="col-sm-6"><?php $input('fecha_fin', 'Fecha de fin', 'date'); ?></div></div>
                <div class="mb-3"><label for="estado" class="form-label">Estado *</label><select id="estado" name="estado" required class="form-select<?= $err('estado') ?>"<?= $ariaError('estado') ?>><?php foreach (GruposService::ESTADOS as $estado): ?><option value="<?= $estado ?>" <?= $valor('estado') === $estado ? 'selected' : '' ?>><?= ucfirst($estado) ?></option><?php endforeach; ?></select><?php $errorCampo('estado'); ?></div>
                <fieldset class="mb-3"><legend class="form-label">Horarios semanales *</legend>
                    <div data-horarios><?php foreach ($valor('horarios', []) as $n => $h): require __DIR__ . '/horario.php'; endforeach; ?></div>
                    <?php $errorCampo('horarios'); ?>
                    <button type="button" class="btn admin-btn-outline btn-sm mt-2" data-agregar-horario><i class="bi bi-plus-lg"></i> Agregar franja</button>
                </fieldset>
                <?php if ($idEdicion): ?><div class="form-check mb-3"><input id="confirmar_reasignacion" name="confirmar_reasignacion" value="1" type="checkbox" class="form-check-input" <?= $valor('confirmar_reasignacion') === '1' ? 'checked' : '' ?>><label for="confirmar_reasignacion" class="form-check-label small">Si cambio de instructor, confirmo que el nuevo tendrá acceso al historial académico del grupo. Las clases extras futuras deben resolverse antes.</label></div><?php endif; ?>
                <div class="d-flex gap-2 flex-wrap pt-3 border-top admin-border"><a href="/admin/grupos" class="btn admin-btn-outline">CANCELAR</a><button class="btn admin-btn-primary flex-grow-1">GUARDAR GRUPO</button></div>
            </form>
            <template id="plantilla-horario"><?php $n = 0; $h = ['dia_semana' => 'Lunes', 'hora_inicio' => '', 'hora_fin' => '']; require __DIR__ . '/horario.php'; ?></template>
        </section></div>
    </div>
</div>
<script src="/js/admin-academica.js" defer></script>
