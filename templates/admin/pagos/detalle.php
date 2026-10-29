<?php
use App\Application\Formato\Dinero;
use App\Application\Security\Csrf;

$e = static fn ($v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$dinero = static fn ($v): string => Dinero::monto((float) ($v ?? 0));

$id = (int) $pago['id_pago'];
$badgePago = ['Pendiente' => 'admin-badge-warning', 'Pagado' => 'admin-badge-success', 'Fallido' => 'admin-badge-danger'];
$badgeInscripcion = ['Pendiente' => 'admin-badge-warning', 'Activa' => 'admin-badge-success', 'Cancelada' => 'admin-badge-danger', 'Finalizada' => 'admin-badge-success'];
$pendiente = $pago['estado'] === 'Pendiente';
$conComprobante = !empty($pago['comprobante_url']);
$src = !empty($pago['comprobante_enlace']) ? (string) $pago['comprobante_enlace'] : (string) $pago['comprobante_url'];
?>
<div class="admin-academica">
    <div class="d-flex flex-wrap justify-content-between gap-3 mb-4">
        <div>
            <h1 class="portal-title mb-1">Pago #<?= $id ?></h1>
            <p class="portal-subtitle mb-0">
                <?= $e($pago['estudiante']) ?> · <?= $e($pago['curso']) ?> · Grupo #<?= (int) $pago['id_grupo'] ?> ·
                <span class="admin-badge <?= $badgePago[$pago['estado']] ?? 'admin-badge-warning' ?>"><?= $e($pago['estado']) ?></span>
            </p>
        </div>
        <div class="d-flex gap-2 align-self-start flex-wrap">
            <a href="/admin/pagos" class="btn admin-btn-outline">Volver</a>
            <a href="/admin/estudiantes/<?= (int) $pago['id_estudiante'] ?>" class="btn admin-btn-outline">Ficha estudiante</a>
            <a href="/admin/matriculas/<?= (int) $pago['id_inscripcion'] ?>" class="btn admin-btn-outline">Matrícula #<?= (int) $pago['id_inscripcion'] ?></a>
        </div>
    </div>

    <?php require __DIR__ . '/../../partials/flash.php'; ?>

    <?php if ($pendiente && !$conComprobante): ?>
        <div class="portal-alert portal-alert--peligro p-3 mb-4">Este movimiento no tiene comprobante asociado: no puede verificarse hasta que el estudiante envíe uno.</div>
    <?php elseif ($pendiente && !$pago['comprobante_disponible']): ?>
        <div class="portal-alert portal-alert--peligro p-3 mb-4">El archivo del comprobante no está disponible en el servidor. Solicita al estudiante que lo envíe de nuevo.</div>
    <?php endif; ?>

    <div class="row g-4 mb-4">
        <!-- Comprobante -->
        <div class="col-lg-7">
            <section class="portal-card p-4 h-100">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <h2 class="portal-card-title mb-0">Comprobante de pago</h2>
                    <?php if ($conComprobante): ?>
                        <?php if ($pago['comprobante_disponible']): ?>
                            <a href="<?= $e($src) ?>" target="_blank" rel="noopener" class="btn admin-btn-outline btn-sm">
                                <i class="bi bi-box-arrow-up-right me-1"></i>Abrir en pestaña
                            </a>
                        <?php else: ?>
                            <span class="admin-badge admin-badge-danger">Archivo no disponible</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="admin-badge admin-badge-warning">Sin comprobante</span>
                    <?php endif; ?>
                </div>

                <?php if ($conComprobante && $pago['comprobante_disponible']): ?>
                    <div class="ratio ratio-4x3 border admin-border rounded overflow-hidden">
                        <iframe src="<?= $e($src) ?>" title="Comprobante de pago #<?= $id ?>" style="background:#fff;"></iframe>
                    </div>
                    <p class="small admin-muted mb-0 mt-2">
                        Revisa que el comprobante corresponda al monto y al método declarados antes de acreditarlo.
                    </p>
                <?php elseif ($conComprobante): ?>
                    <p class="admin-muted mb-0">No se puede mostrar el archivo del comprobante.</p>
                <?php else: ?>
                    <p class="admin-muted mb-0">El estudiante aún no ha adjuntado un comprobante para este movimiento.</p>
                <?php endif; ?>
            </section>
        </div>

        <!-- Datos + verificación -->
        <div class="col-lg-5">
            <section class="portal-card p-4 h-100">
                <h2 class="portal-card-title">Datos del movimiento</h2>
                <ul class="list-unstyled mb-3">
                    <li class="mb-2"><i class="bi bi-calendar-event me-1"></i> Fecha declarada: <strong><?= $e($pago['fecha_pago']) ?></strong></li>
                    <li class="mb-2"><i class="bi bi-cash me-1"></i> Monto: <strong><?= $e($dinero($pago['monto_pagado'])) ?></strong></li>
                    <li class="mb-2"><i class="bi bi-tag me-1"></i> Concepto: <?= $e($pago['concepto']) ?></li>
                    <li class="mb-2"><i class="bi bi-credit-card me-1"></i> Método: <?= $e($pago['metodo'] ?: 'Sin especificar') ?></li>
                    <li class="mb-2"><i class="bi bi-upc-scan me-1"></i> Referencia:
                        <?php if (!empty($pago['referencia_pago'])): ?><code><?= $e($pago['referencia_pago']) ?></code>
                        <?php else: ?><span class="admin-muted">Sin referencia</span><?php endif; ?>
                    </li>
                    <?php if (!empty($pago['observaciones'])): ?>
                        <li class="mb-0"><i class="bi bi-sticky me-1"></i> Observaciones: <?= nl2br($e($pago['observaciones'])) ?></li>
                    <?php endif; ?>
                </ul>

                <hr class="admin-border">
                <h2 class="portal-card-title">Estado de la matrícula</h2>
                <p class="mb-1">
                    Precio congelado:
                    <strong><?= $pago['precio_inscripcion'] !== null ? $e($dinero($pago['precio_inscripcion'])) : 'Sin precio' ?></strong>
                    · <span class="admin-badge <?= $badgeInscripcion[$pago['inscripcion_estado']] ?? 'admin-badge-warning' ?>"><?= $e($pago['inscripcion_estado']) ?></span>
                </p>
                <p class="mb-1">Pagado hasta ahora: <strong><?= $e($dinero($pago['total_pagado'])) ?></strong></p>
                <p class="mb-1">Saldo pendiente:
                    <strong><?= $pago['saldo'] === null ? '—' : $e($dinero($pago['saldo'])) ?></strong>
                </p>
                <?php if ($pendiente): ?>
                    <p class="mb-0">Si se aprueba, el total pagado quedará en:
                        <strong><?= $e($dinero($pago['total_tras_aprobar'])) ?></strong>
                    </p>
                    <?php if ($pago['excede_precio']): ?>
                        <div class="portal-alert portal-alert--peligro p-3 mt-3 mb-0">
                            Al aprobar, el total pagado superaría el precio de la matrícula. Aun así se acredita y se advierte en la confirmación.
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        </div>
    </div>

    <!-- Acciones de verificación -->
    <section class="portal-card p-4 mb-4">
        <h2 class="portal-card-title mb-3">Verificación</h2>

        <?php if ($pendiente): ?>
            <div class="row g-4">
                <div class="col-lg-7">
                    <form action="/admin/pagos/<?= $id ?>/aprobar" method="post"
                          data-confirm="¿Acreditar este pago? El monto pasará a contar como saldo pagado de la matrícula.">
                        <?= Csrf::campo() ?>
                        <p class="small admin-muted mb-2">
                            Aprueba solo si el comprobante corresponde al estudiante, al importe y al método declarados.
                        </p>
                        <button class="btn admin-btn-primary" <?= !$pago['comprobante_disponible'] ? 'disabled' : '' ?>>
                            <i class="bi bi-check2-circle me-1"></i>APROBAR COMPROBANTE
                        </button>
                    </form>
                </div>
                <div class="col-lg-5">
                    <form action="/admin/pagos/<?= $id ?>/rechazar" method="post"
                          data-confirm="¿Rechazar este comprobante? El estudiante podrá enviar otro desde su portal.">
                        <?= Csrf::campo() ?>
                        <label for="motivo" class="form-label">Motivo de rechazo *</label>
                        <textarea id="motivo" name="motivo" rows="2" maxlength="255" class="form-control mb-3" required
                                  placeholder="Ej.: La imagen no corresponde al monto declarado."></textarea>
                        <button class="btn admin-btn-outline admin-delete">RECHAZAR COMPROBANTE</button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <p class="mb-1">
                <?= $pago['estado'] === 'Pagado' ? 'Comprobante acreditado.' : 'Comprobante rechazado.' ?>
                <?php if (!empty($pago['fecha_verificacion'])): ?>
                    · <?= $e($pago['fecha_verificacion']) ?>
                <?php endif; ?>
                <?php if (!empty($pago['verificado_por_email'])): ?>
                    · Verificado por <strong><?= $e($pago['verificado_por_email']) ?></strong>
                <?php endif; ?>
            </p>
            <?php if (!empty($pago['motivo_verificacion'])): ?>
                <p class="mb-0">Motivo del rechazo: <em><?= $e($pago['motivo_verificacion']) ?></em></p>
            <?php endif; ?>
            <?php if ($pago['estado'] === 'Pagado' && $pago['excede_precio']): ?>
                <div class="portal-alert portal-alert--peligro p-3 mt-3 mb-0">
                    El total pagado (<?= $e($dinero($pago['total_pagado'])) ?>) supera el precio de la matrícula (<?= $e($dinero($pago['precio_inscripcion'])) ?>).
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <!-- Historial de la matrícula -->
    <section class="portal-card p-4">
        <h2 class="portal-card-title mb-3">Pagos de esta matrícula</h2>
        <?php if (!$pago['pagos_matricula']): ?>
            <p class="admin-muted mb-0">Sin movimientos registrados.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table admin-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>#</th><th>FECHA</th><th>CONCEPTO</th><th>MÉTODO</th>
                            <th class="text-end">IMPORTE</th><th>ESTADO</th><th>COMPROBANTE</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pago['pagos_matricula'] as $mov): ?>
                            <tr<?= (int) $mov['id_pago'] === $id ? ' class="table-active"' : '' ?>>
                                <td><strong>#<?= (int) $mov['id_pago'] ?></strong></td>
                                <td><?= $e($mov['fecha_pago']) ?></td>
                                <td><?= $e($mov['concepto']) ?></td>
                                <td><?= $e($mov['metodo'] ?: '—') ?></td>
                                <td class="text-end"><?= $e($dinero($mov['monto_pagado'])) ?></td>
                                <td><span class="admin-badge <?= $badgePago[$mov['estado']] ?? 'admin-badge-warning' ?>"><?= $e($mov['estado']) ?></span></td>
                                <td class="text-nowrap">
                                    <?php if (empty($mov['comprobante_url'])): ?>
                                        <span class="admin-muted">—</span>
                                    <?php elseif (!empty($mov['comprobante_disponible'])): ?>
                                        <a href="<?= $e($mov['comprobante_enlace'] ?: $mov['comprobante_url']) ?>" target="_blank" rel="noopener"
                                           class="btn btn-sm admin-btn-outline"><i class="bi bi-eye me-1"></i>Ver</a>
                                    <?php else: ?>
                                        <span class="small admin-muted">No disponible</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

</div>
<script src="/js/admin-academica.js" defer></script>
