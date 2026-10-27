<?php
$e = static fn ($v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$id = (int) $aula['id_aula'];
$abiertos = array_filter($agenda, static fn ($g): bool => in_array($g['estado'], ['activo', 'planeado'], true));
?>
<div class="admin-academica">
    <div class="d-flex flex-wrap justify-content-between gap-3 mb-4"><div><h1 class="portal-title mb-1">#<?= $id ?> · <?= $e($aula['nombre']) ?></h1><p class="portal-subtitle mb-0">Agenda del aula · <?= $e($aula['estado']) ?> · <?= (int) $aula['capacidad'] ?> alumnos</p></div><div class="d-flex gap-2 align-self-start"><a href="/admin/aulas" class="btn admin-btn-outline">Volver</a><a href="/admin/aulas/<?= $id ?>/editar" class="btn admin-btn-primary">Editar aula</a></div></div>
    <?php if ($flash): ?><div role="status" class="portal-alert portal-alert--<?= $flash['tipo'] === 'error' ? 'peligro' : 'exito' ?> p-3 mb-4"><?= $e($flash['mensaje']) ?></div><?php endif; ?>
    <div class="row g-4 mb-4"><div class="col-lg-5"><section class="portal-card p-4 h-100"><h2 class="portal-card-title">Ubicación y equipamiento</h2><p><?= $e($aula['ubicacion'] ?: 'Ubicación sin definir') ?></p><p class="mb-0"><?= nl2br($e($aula['recursos'] ?: 'Sin equipamiento registrado')) ?></p></section></div><div class="col-lg-7"><section class="portal-card p-4 h-100" id="estado-aula"><h2 class="portal-card-title">Habilitación y mantenimiento</h2><p>Grupos activos o planeados: <strong><?= count($abiertos) ?></strong>. Para deshabilitar o poner en mantenimiento primero deben reasignarse o cerrarse.</p><p class="small admin-muted">No se moverán clases ni se cancelarán matrículas automáticamente. «Ocupada» se calcula desde la programación, no se guarda como estado manual.</p>
        <form action="/admin/aulas/<?= $id ?>/estado" method="post" data-confirm="¿Confirmar el cambio de habilitación del aula? No se permitirá deshabilitarla ni ponerla en mantenimiento mientras tenga grupos abiertos. No se modificarán grupos ni matrículas.">
            <?= \App\Application\Security\Csrf::campo() ?><input type="hidden" name="confirmar" value="1"><label for="estado" class="form-label">Habilitación</label><div class="d-flex flex-wrap gap-2"><select name="estado" id="estado" class="form-select admin-state-select" required><?php foreach (\App\Application\Admin\AulasService::ESTADOS as $estado): ?><option value="<?= $estado ?>" <?= $aula['estado'] === $estado ? 'selected' : '' ?>><?= $estado === 'Activa' ? 'Habilitada' : $estado ?></option><?php endforeach; ?></select><button class="btn admin-btn-primary">CONFIRMAR ESTADO</button></div>
        </form>
    </section></div></div>
    <section class="portal-card overflow-hidden"><div class="p-4"><h2 class="portal-card-title mb-1">Agenda de grupos e historial</h2><p class="small admin-muted mb-0">Franjas programadas y fechas de cada grupo. No representan un horario de apertura.</p></div><?php $grupos = $agenda; $columnaRecurso = 'instructor'; require __DIR__ . '/../partials/grupos-recursos.php'; ?></section>
    <?php require __DIR__ . '/../partials/confirmacion.php'; ?>
</div>
<script src="/js/admin-academica.js" defer></script>
