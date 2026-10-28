<?php
use App\Application\Formato\Dinero;
use App\Application\Security\Csrf;
$e = static fn ($v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$id = (int) $estudiante['id_estudiante'];
$vigentes = array_filter($matriculas, static fn ($m): bool => in_array($m['estado'], ['Activa', 'Pendiente'], true));
$totalPagado = array_sum(array_column($matriculas, 'total_pagado'));
?>
<div class="admin-academica">
    <div class="d-flex flex-wrap justify-content-between gap-3 mb-4"><div><h1 class="portal-title mb-1"><?= $e($estudiante['nombre'] . ' ' . $estudiante['apellido']) ?></h1><p class="portal-subtitle mb-0">Ficha del estudiante #<?= $id ?> · <span class="admin-badge <?= $estudiante['estado'] === 'Activo' ? 'admin-badge-success' : 'admin-badge-warning' ?>"><?= $e($estudiante['estado']) ?></span></p></div><div class="d-flex gap-2 align-self-start"><a href="/admin/estudiantes" class="btn admin-btn-outline">Volver</a><a href="/admin/estudiantes/<?= $id ?>/editar" class="btn admin-btn-primary">Editar ficha</a></div></div>
    <?php if ($flash): ?><div role="status" class="portal-alert portal-alert--<?= $flash['tipo'] === 'error' ? 'peligro' : 'exito' ?> p-3 mb-4"><?= $e($flash['mensaje']) ?></div><?php endif; ?>
    <div class="row g-4 mb-4"><div class="col-lg-4"><section class="portal-card p-4 h-100">
        <div class="d-flex gap-3 align-items-center mb-3"><span class="admin-student-avatar admin-student-avatar-lg"><?php if ($estudiante['foto']): ?><img src="<?= $e($estudiante['foto']) ?>" alt="Foto del estudiante"><?php else: ?><?= $e(mb_strtoupper(mb_substr($estudiante['nombre'], 0, 1) . mb_substr($estudiante['apellido'], 0, 1))) ?><?php endif; ?></span><h2 class="portal-card-title mb-0">Datos personales</h2></div>
        <dl class="mb-0"><dt class="admin-muted small">Email</dt><dd class="text-break"><?= $e($estudiante['email']) ?></dd><dt class="admin-muted small">Teléfono</dt><dd><?= $e($estudiante['telefono'] ?: 'Sin teléfono') ?></dd><dt class="admin-muted small">Dirección</dt><dd class="text-break"><?= $e($estudiante['direccion'] ?: 'Sin dirección') ?></dd><dt class="admin-muted small">Registro</dt><dd><?= $e($estudiante['fecha_inscripcion'] ?: 'Sin fecha registrada') ?></dd><dt class="admin-muted small">Nacimiento</dt><dd><?= $e($estudiante['fecha_nacimiento'] ?: 'Sin fecha registrada') ?></dd><dt class="admin-muted small">Acceso</dt><dd class="mb-0"><?= !$estudiante['cuentas'] ? 'Sin cuenta de acceso' : ($estudiante['acceso_activo'] === '1' ? 'Activo' : 'Inactivo') ?></dd></dl>
    </section></div><div class="col-lg-8"><section id="estado-estudiante" class="portal-card p-4 h-100">
        <h2 class="portal-card-title">Estado académico</h2>
        <?php if ($estudiante['suspension_fecha']): ?><p class="small admin-muted">Última suspensión: <?= $e($estudiante['suspension_fecha']) ?> · <?= $e($estudiante['suspension_motivo']) ?></p><?php endif; ?>
        <?php if ($estudiante['estado'] === 'Activo'): ?>
            <p>Al suspender se cancelarán <strong><?= count($vigentes) ?> matrículas activas o pendientes</strong>, liberando sus lugares. Se bloqueará el acceso, incluidas las sesiones abiertas.</p>
            <?php if ($vigentes): ?><ul class="small"><?php foreach ($vigentes as $m): ?><li>Grupo #<?= (int) $m['id_grupo'] ?> · <?= $e($m['curso']) ?> · <?= $e($m['estado']) ?></li><?php endforeach; ?></ul><?php endif; ?>
            <p class="small admin-muted">No se borran notas, asistencias, pagos ni matrículas históricas. No se generan devoluciones ni se modifican reservas automáticamente.</p>
            <form action="/admin/estudiantes/<?= $id ?>/estado" method="post" data-confirm="¿Suspender a este estudiante? Se cancelarán <?= count($vigentes) ?> matrículas vigentes para liberar cupos y se bloqueará su acceso. Los pagos e historial se conservarán.">
                <?= Csrf::campo() ?><input type="hidden" name="estado" value="Suspendido"><input type="hidden" name="confirmar" value="1"><input type="hidden" name="revision" value="<?= $e($revision) ?>">
                <label for="motivo" class="form-label">Motivo de suspensión *</label><textarea id="motivo" name="motivo" class="form-control mb-3" rows="2" maxlength="255" required></textarea>
                <button class="btn admin-btn-outline admin-delete"><i class="bi bi-person-dash me-2"></i>SUSPENDER Y LIBERAR CUPOS</button>
            </form>
        <?php else: ?>
            <p>Al reactivar se restablecerá únicamente el acceso que estaba habilitado antes de la suspensión.</p><p class="small admin-muted">Las matrículas canceladas no se restauran. Será necesario matricular nuevamente según disponibilidad.</p>
            <form action="/admin/estudiantes/<?= $id ?>/estado" method="post" data-confirm="¿Reactivar al estudiante? Las matrículas canceladas no se restaurarán automáticamente."><?= Csrf::campo() ?><input type="hidden" name="estado" value="Activo"><input type="hidden" name="confirmar" value="1"><button class="btn admin-btn-primary"><i class="bi bi-arrow-counterclockwise me-2"></i>REACTIVAR ESTUDIANTE</button></form>
        <?php endif; ?>
    </section></div></div>
    <section class="portal-card overflow-hidden">
        <div class="p-4 d-flex flex-wrap justify-content-between gap-2"><h2 class="portal-card-title mb-0">Cursos, matrículas y seguimiento</h2><span class="admin-count">Total pagado: <?= $e(Dinero::monto((float) $totalPagado)) ?></span></div>
        <div class="table-responsive"><table class="table admin-table align-middle mb-0"><thead><tr><th>CURSO / GRUPO</th><th>FECHA</th><th>MATRÍCULA</th><th>ASISTENCIA</th><th>PROMEDIO</th><th>PAGADO</th><th>SALDO</th></tr></thead><tbody>
            <?php if (!$matriculas): ?><tr><td colspan="7" class="text-center py-5">El estudiante todavía no tiene matrículas.</td></tr><?php endif; ?>
            <?php foreach ($matriculas as $m): ?><tr><td class="admin-name-cell"><strong><?= $e($m['curso']) ?></strong><small class="d-block"><a href="/admin/grupos/<?= (int) $m['id_grupo'] ?>" class="admin-edit">Grupo #<?= (int) $m['id_grupo'] ?></a> · <?= $e($m['instrumento']) ?></small></td><td><?= $e($m['fecha']) ?></td><td><?= $e($m['estado']) ?></td><td><?= $m['asistencia'] !== null ? $e($m['asistencia']) . '%' : 'Sin registros' ?></td><td><?= $m['promedio'] !== null ? $e($m['promedio']) . ' / 10' : 'Sin notas' ?></td><td class="text-nowrap"><?= $e(Dinero::monto((float) $m['total_pagado'])) ?></td><td class="text-nowrap"><?= $m['precio_inscripcion'] !== null ? $e(Dinero::monto((float) $m['precio_inscripcion'] - (float) $m['total_pagado'])) : 'Sin precio definido' ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
    </section>
    <?php require __DIR__ . '/../partials/confirmacion.php'; ?>
</div>
<script src="/js/admin-academica.js" defer></script>
