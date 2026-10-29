<?php
use App\Application\Security\Csrf;
$e = static fn ($v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$id = (int) $matricula['id_inscripcion'];
$badge = ['Pendiente' => 'admin-badge-warning', 'Activa' => 'admin-badge-success', 'Cancelada' => 'admin-badge-danger', 'Finalizada' => 'admin-badge-success'];
$requisitos = ['nivel_minimo', 'edad_minima', 'edad_maxima', 'conocimientos_previos', 'necesita_instrumento', 'puede_alquilar', 'descripcion_general'];
$hayRequisitos = array_filter(array_intersect_key($matricula, array_flip($requisitos)), static fn ($v): bool => $v !== null && $v !== '' && $v !== '0');
$edadManual = $matricula['edad'] === null && $matricula['edad_minima'] !== null;
$edadCumple = $matricula['edad'] !== null && $matricula['edad_minima'] !== null
    && $matricula['edad'] >= (int) $matricula['edad_minima'] && $matricula['edad'] <= (int) $matricula['edad_maxima'];
$grupoAbierto = $matricula['grupo_estado'] === 'activo' && (int) $matricula['curso_activo'] === 1 && (int) $matricula['disponible'] === 1;
$capacidadValida = (int) $matricula['ocupados'] <= (int) $matricula['capacidad_maxima'];
?>
<div class="admin-academica">
    <div class="d-flex flex-wrap justify-content-between gap-3 mb-4"><div><h1 class="portal-title mb-1">Matrícula #<?= $id ?></h1><p class="portal-subtitle mb-0"><?= $e($matricula['estudiante']) ?> · <?= $e($matricula['curso']) ?> · Grupo #<?= (int) $matricula['id_grupo'] ?> · <span class="admin-badge <?= $badge[$matricula['estado']] ?>"><?= $e($matricula['estado']) ?></span></p></div><div class="d-flex gap-2 align-self-start"><a href="/admin/matriculas" class="btn admin-btn-outline">Volver</a><a href="/admin/estudiantes/<?= (int) $matricula['id_estudiante'] ?>" class="btn admin-btn-outline">Ficha estudiante</a></div></div>
    <?php require __DIR__ . '/../../partials/flash.php'; ?>
    <div class="row g-4 mb-4">
        <div class="col-lg-7"><section class="portal-card p-4 h-100">
            <h2 class="portal-card-title">Revisión de requisitos</h2>
            <ul class="list-unstyled mb-3">
                <li class="mb-2"><i class="bi <?= $matricula['estado'] !== 'Pendiente' || $edadCumple ? 'bi-check-circle-fill text-success' : ($edadManual ? 'bi-question-circle-fill text-warning' : 'bi-x-circle-fill text-danger') ?>"></i> Edad:
                    <?php if ($matricula['edad'] !== null): ?><?= (int) $matricula['edad'] ?> años; <?= $edadCumple ? 'cumple' : 'no cumple' ?> el rango de <?= (int) $matricula['edad_minima'] ?>–<?= (int) $matricula['edad_maxima'] ?>.
                    <?php elseif ($edadManual): ?>Sin fecha de nacimiento registrada; debe confirmarse manualmente.
                    <?php else: ?>Sin requisito de edad configurado.<?php endif; ?></li>
                <li class="mb-2"><i class="bi bi-person-check"></i> Nivel mínimo: <?= $e($matricula['nivel_minimo'] ?: 'No especificado; revisión manual') ?></li>
                <li class="mb-2"><i class="bi bi-journal-text"></i> Conocimientos previos: <?= $e($matricula['conocimientos_previos'] ?: 'No especificados; revisión manual') ?></li>
                <li class="mb-2"><i class="bi bi-music-note"></i> Instrumento: <?= $matricula['necesita_instrumento'] ? ($matricula['puede_alquilar'] ? 'Propio o de alquiler' : 'Propio') : 'No requerido' ?></li>
                <li class="mb-0"><i class="bi bi-calendar-check"></i> Horarios: <?= $matricula['conflicto_horario'] ? 'Detectado conflicto con otra matrícula vigente' : 'Sin conflictos detectados' ?></li>
            </ul>
            <?php if ($matricula['descripcion_general']): ?><p class="small admin-muted"><?= nl2br($e($matricula['descripcion_general'])) ?></p><?php endif; ?>
            <?php if ($matricula['estado'] === 'Pendiente'): ?>
                <?php if (!$grupoAbierto): ?><div class="portal-alert portal-alert--peligro p-3 mb-3">El curso o grupo ya no admite aprobaciones. La solicitud debe rechazarse o resolverse en Grupos.</div><?php endif; ?>
                <?php if (!$capacidadValida): ?><div class="portal-alert portal-alert--peligro p-3 mb-3">El grupo supera su capacidad. Revisa sus matrículas antes de aprobar.</div><?php endif; ?>
                <?php if ($matricula['conflicto_horario']): ?><div class="portal-alert portal-alert--peligro p-3 mb-3">Hay un conflicto de horario con otra matrícula vigente.</div><?php endif; ?>
                <?php if (($edadManual || !$edadCumple) && $matricula['edad_minima'] !== null): ?><div class="portal-alert portal-alert--peligro p-3 mb-3"><?= $edadManual ? 'No hay fecha de nacimiento: confirma manualmente la edad antes de aprobar.' : 'La edad registrada no cumple el rango configurado.' ?></div><?php endif; ?>
                <form action="/admin/matriculas/<?= $id ?>/aprobar" method="post" id="form-academico" data-confirm="¿Aprobar esta matrícula? Pasará a Activa, conservará el precio congelado y no registrará ningún pago automáticamente.">
                    <?= Csrf::campo() ?>
                    <div class="form-check mb-3"><input id="confirmar_requisitos" name="confirmar_requisitos" value="1" type="checkbox" class="form-check-input" required><label for="confirmar_requisitos" class="form-check-label small">Confirmo que revisé los requisitos configurados y la información del grupo.</label></div>
                    <?php if ($edadManual): ?><div class="form-check mb-3"><input id="confirmar_edad" name="confirmar_edad" value="1" type="checkbox" class="form-check-input" required><label for="confirmar_edad" class="form-check-label small">Confirmo que verifiqué manualmente la edad del estudiante.</label></div><?php endif; ?>
                    <label for="observaciones" class="form-label">Observaciones de la revisión</label><textarea id="observaciones" name="observaciones" rows="2" maxlength="255" class="form-control mb-3"></textarea>
                    <button class="btn admin-btn-primary" <?= (!$grupoAbierto || !$capacidadValida || $matricula['conflicto_horario'] || (!$edadManual && !$edadCumple && $matricula['edad_minima'] !== null)) ? 'disabled' : '' ?>>APROBAR Y ACTIVAR MATRÍCULA</button>
                </form>
            <?php elseif ($matricula['estado'] === 'Activa'): ?>
                <p class="small admin-muted">La aprobación no significa que la matrícula esté pagada. Consulta los saldos en el resumen.</p>
            <?php endif; ?>
        </section></div>
        <div class="col-lg-5"><section class="portal-card p-4 h-100">
            <h2 class="portal-card-title">Grupo y reserva de cupo</h2>
            <p><?= $e($matricula['instructor']) ?> · <?= $e($matricula['aula']) ?></p><p class="mb-1"><?= $e(ucfirst((string) $matricula['grupo_estado'])) ?> · Ocupación actual: <?= (int) $matricula['ocupados'] ?> de <?= (int) $matricula['capacidad_maxima'] ?></p>
            <p>Precio congelado: <strong><?= $matricula['precio_inscripcion'] !== null ? '$ ' . number_format((float) $matricula['precio_inscripcion'], 2) : 'Sin precio' ?></strong>
                <?php if (!empty($matricula['descuento_codigo'])): ?>
                    <i class="bi bi-ticket" title="Descuento aplicado al matricular"></i>
                    <small class="admin-muted d-block">Con descuento <strong><?= $e($matricula['descuento_codigo']) ?></strong>
                        (<?= $matricula['descuento_porcentaje'] !== null
                            ? $e((float) $matricula['descuento_porcentaje']) . '%'
                            : '$ ' . number_format((float) $matricula['descuento_monto_fijo'], 2) ?>)</small>
                <?php endif; ?>
            </p>
            <?php foreach ($matricula['horarios'] as $h): ?><p class="small admin-muted mb-1"><?= $e($h['dia_semana'] . ' ' . $h['hora_inicio'] . '–' . $h['hora_fin']) ?></p><?php endforeach; ?>
            <?php if ($matricula['estado'] === 'Pendiente'): ?>
                <hr class="admin-border">
                <form action="/admin/matriculas/<?= $id ?>/rechazar" method="post" id="form-rechazo" data-confirm="¿Rechazar esta solicitud? La matrícula quedará Cancelada y el cupo se liberará.">
                    <?= Csrf::campo() ?><label for="motivo" class="form-label">Motivo de rechazo *</label><textarea id="motivo" name="motivo" rows="2" maxlength="255" class="form-control mb-3" required></textarea><button class="btn admin-btn-outline admin-delete">RECHAZAR Y LIBERAR CUPO</button>
                </form>
            <?php endif; ?>
        </section></div>
    </div>
    <section class="portal-card p-4"><h2 class="portal-card-title">Historial de decisiones</h2><?php if (!$matricula['auditoria']): ?><p class="admin-muted mb-0">Sin historial registrado.</p><?php else: ?><div class="table-responsive"><table class="table admin-table align-middle mb-0"><thead><tr><th>FECHA</th><th>ESTADO</th><th>RESPONSABLE</th><th>MOTIVO</th></tr></thead><tbody><?php foreach ($matricula['auditoria'] as $a): ?><tr><td><?= $e($a['fecha']) ?></td><td><?= $e(($a['estado_anterior'] ? $a['estado_anterior'] . ' → ' : '') . $a['estado_nuevo']) ?></td><td><?= $e($a['responsable']) ?></td><td><?= $e($a['motivo']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
</div>
<script src="/js/admin-academica.js" defer></script>
