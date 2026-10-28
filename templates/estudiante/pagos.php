<!-- Pagos y Facturación -->
<?php
/**
 * Pagos (pantalla 7 del prototipo).
 * Datos: $datos = EstudiantePortalService::pagos($id, $filtros)
 *   - resumen: total_pagado, saldo_pendiente, importe_pendiente,
 *              pagos_pendientes, total_movimientos, ultimo_pago, al_dia
 *   - saldos:  [{id_inscripcion, curso, fecha, estado, precio_inscripcion,
 *                total_pagado, saldo_pendiente, comprobante_pendiente}]
 *   - historial: [{id_pago, fecha_pago, monto_pagado, concepto, estado,
 *                  referencia_pago, comprobante_url, observaciones, curso,
 *                  comprobante_disponible, comprobante_subido,
 *                  comprobante_enlace, motivo_verificacion}]
 * $filtros: {estado} · $flash: aviso de la última acción · $metodos: array
 *
 * Solo estado de cuenta, referencia y comprobante: sin pasarelas.
 * El comprobante enviado queda 'Pendiente' hasta que administración lo
 * verifique; solo entonces descuenta del saldo.
 */
$datos   = $datos ?? ['resumen' => [], 'saldos' => [], 'historial' => []];
$filtros = $filtros ?? ['estado' => ''];
$flash   = $flash ?? null;
$metodos = $metodos ?? [];
$resumen = $datos['resumen'] ?? [];
$saldos  = $datos['saldos'] ?? [];
$historial = $datos['historial'] ?? [];

$dolares = static fn (float $n): string => \App\Application\Formato\Dinero::monto($n);

$claseEstado = [
    'Pagado'   => 'portal-pill--verde',
    'Pendiente'=> 'portal-pill--dorado',
    'Fallido'  => 'portal-pill--rojo',
];

// Estados de inscripción (no confundir con los de pago)
$claseInscripcion = [
    'Activa'     => 'portal-pill--verde',
    'Pendiente'  => 'portal-pill--dorado',
    'Finalizada' => 'portal-pill--azul',
    'Cancelada'  => 'portal-pill--rojo',
];

$saldo = (float) ($resumen['saldo_pendiente'] ?? 0.0);
$hayFiltro = ($filtros['estado'] ?? '') !== '';
$vacio = count($saldos) === 0 && count($historial) === 0;
?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="portal-title mb-1">Pagos</h1>
        <p class="portal-subtitle mb-0">Envía tu comprobante de pago y consulta el estado de cuenta de cada movimiento.</p>
    </div>
    <?php if (!$vacio): ?>
        <?php if ($saldo > 0): ?>
            <span class="portal-pill portal-pill--dorado">Saldo pendiente: <?= $dolares($saldo) ?></span>
        <?php else: ?>
            <span class="portal-pill portal-pill--verde">Al día</span>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php if (!empty($flash)): ?>
    <div role="status" class="portal-alert portal-alert--<?= $flash['tipo'] === 'error' ? 'peligro' : 'exito' ?> mb-4">
        <?= htmlspecialchars($flash['mensaje']) ?>
    </div>
<?php endif; ?>

<?php if ($vacio): ?>
    <div class="portal-card p-4 text-center">
        <i class="bi bi-credit-card fs-1 d-block mb-2" style="color: var(--portal-muted);"></i>
        <p class="mb-1 fw-semibold">Todavía no hay movimientos de pago</p>
        <p class="small text-secondary mb-0">
            Cuando realices tu primer pago verás aquí la referencia y el comprobante para descargar.
        </p>
    </div>

<?php else: ?>

    <!-- Resumen -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <div class="portal-card portal-kpi h-100 p-3">
                <div class="d-flex justify-content-between align-items-start">
                    <span class="portal-label">Total pagado</span>
                    <span class="portal-kpi-icon portal-kpi-icon--verde"><i class="bi bi-wallet2"></i></span>
                </div>
                <div class="portal-kpi-value"><?= $dolares((float) ($resumen['total_pagado'] ?? 0.0)) ?></div>
                <span class="portal-kpi-note">Suma de comprobantes válidos</span>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="portal-card portal-kpi h-100 p-3">
                <div class="d-flex justify-content-between align-items-start">
                    <span class="portal-label">Saldo por pagar</span>
                    <span class="portal-kpi-icon portal-kpi-icon--rosa"><i class="bi bi-exclamation-circle"></i></span>
                </div>
                <div class="portal-kpi-value"><?= $dolares($saldo) ?></div>
                <span class="portal-kpi-note">
                    <?= $saldo > 0 ? 'Pendiente de saldar en tus inscripciones' : 'Tus inscripciones están saldadas' ?>
                </span>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="portal-card portal-kpi h-100 p-3">
                <div class="d-flex justify-content-between align-items-start">
                    <span class="portal-label">Pagos pendientes</span>
                    <span class="portal-kpi-icon portal-kpi-icon--azul"><i class="bi bi-hourglass-split"></i></span>
                </div>
                <div class="portal-kpi-value"><?= (int) ($resumen['pagos_pendientes'] ?? 0) ?></div>
                <span class="portal-kpi-note">
                    <?= (int) ($resumen['pagos_pendientes'] ?? 0) > 0
                        ? $dolares((float) ($resumen['importe_pendiente'] ?? 0.0)) . ' por abonar'
                        : 'No tienes pagos por abonar' ?>
                </span>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="portal-card portal-kpi h-100 p-3">
                <div class="d-flex justify-content-between align-items-start">
                    <span class="portal-label">Fecha de pago</span>
                    <span class="portal-kpi-icon"><i class="bi bi-calendar-check"></i></span>
                </div>
                <div class="portal-kpi-value" style="font-size:1.6rem;">
                    <?php
                    $ultimoPago = $resumen['ultimo_pago'] ?? null;
                    if ($ultimoPago && !empty($ultimoPago['fecha_pago'])) {
                        echo date('d/m/Y', strtotime((string) $ultimoPago['fecha_pago']));
                    } else {
                        echo '—';
                    }
                    ?>
                </div>
                <span class="portal-kpi-note">
                    <?php if ($ultimoPago && !empty($ultimoPago['concepto'])): ?>
                        <?= htmlspecialchars($ultimoPago['concepto']) ?><?= !empty($ultimoPago['monto_pagado']) ? ' · ' . $dolares((float)$ultimoPago['monto_pagado']) : '' ?>
                    <?php else: ?>
                        Sin pagos registrados
                    <?php endif; ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Estado de cuenta -->
    <div class="portal-card p-3 p-md-4 mb-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="portal-card-title mb-0">Estado de cuenta</h2>
            <span class="portal-pill"><?= count($saldos) ?> inscripción(es)</span>
        </div>

        <?php if (count($saldos) === 0): ?>
            <p class="small text-secondary mb-0 text-center py-3">
                No tienes inscripciones registradas.
            </p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle portal-tabla mb-0">
                    <thead>
                        <tr>
                            <th>Curso</th>
                            <th>Inscripción</th>
                            <th class="text-end">Precio</th>
                            <th class="text-end">Pagado</th>
                            <th class="text-end">Saldo</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($saldos as $fila): ?>
                            <?php
                            $debe = (float) $fila['saldo_pendiente'];
                            $estadoIns = (string) $fila['estado'];
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($fila['curso']) ?></td>
                                <td class="text-nowrap">
                                    <?= $fila['fecha'] !== null ? date('d/m/Y', strtotime((string) $fila['fecha'])) : '—' ?>
                                </td>
                                <td class="text-end text-nowrap">
                                    <?= $fila['precio_inscripcion'] !== null ? $dolares((float) $fila['precio_inscripcion']) : '—' ?>
                                </td>
                                <td class="text-end text-nowrap"><?= $dolares((float) $fila['total_pagado']) ?></td>
                                <td class="text-end text-nowrap">
                                    <?php if ($debe > 0): ?>
                                        <span class="portal-pill portal-pill--rojo"><?= $dolares($debe) ?></span>
                                    <?php else: ?>
                                        <span class="portal-pill portal-pill--verde">$ 0.00</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="portal-pill <?= $claseInscripcion[$estadoIns] ?? 'portal-pill--azul' ?>">
                                        <?= htmlspecialchars($estadoIns) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Envío de comprobante -->
    <?php
    $porEnviar = array_filter($saldos, static fn ($f): bool => (float) ($f['saldo_pendiente'] ?? 0) > 0
        && empty($f['comprobante_pendiente'])
        && in_array((string) ($f['estado'] ?? ''), ['Activa', 'Pendiente'], true));
    $enVerificacion = array_filter($saldos, static fn ($f): bool => !empty($f['comprobante_pendiente']));
    ?>
    <div class="portal-card p-3 p-md-4 mb-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
            <h2 class="portal-card-title mb-0">Enviar comprobante de pago</h2>
            <span class="portal-pill"><?= count($porEnviar) ?> por enviar</span>
        </div>

        <?php if (!$porEnviar): ?>
            <p class="small text-secondary mb-0 text-center py-3">
                <?php if ($enVerificacion): ?>
                    Ya tienes un comprobante en verificación. El saldo se actualizará cuando administración lo apruebe.
                <?php else: ?>
                    No tienes saldo pendiente por pagar: no hay nada que enviar.
                <?php endif; ?>
            </p>
        <?php else: ?>
            <?php foreach ($porEnviar as $fila): ?>
                <?php
                $idIns = (int) $fila['id_inscripcion'];
                $saldoFila = round((float) $fila['saldo_pendiente'], 2);
                ?>
                <form method="post" action="/estudiante/pagos/<?= $idIns ?>/comprobante"
                      enctype="multipart/form-data" class="pb-3 mb-3 border-bottom">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                        <div>
                            <strong><?= htmlspecialchars($fila['curso']) ?></strong>
                            <span class="small" style="color: var(--portal-muted);">
                                · matrícula #<?= $idIns ?><?= !empty($fila['fecha']) ? ' · ' . date('d/m/Y', strtotime((string) $fila['fecha'])) : '' ?>
                            </span>
                        </div>
                        <span class="portal-pill portal-pill--dorado">Saldo: <?= $dolares($saldoFila) ?></span>
                    </div>

                    <?= \App\Application\Security\Csrf::campo() ?>

                    <div class="row g-2 align-items-end">
                        <div class="col-6 col-lg-2">
                            <label class="form-label small mb-1" for="monto-<?= $idIns ?>">Monto pagado (USD)</label>
                            <input id="monto-<?= $idIns ?>" type="number" name="monto" step="0.01" min="0.01"
                                   max="<?= number_format($saldoFila, 2, '.', '') ?>"
                                   value="<?= number_format($saldoFila, 2, '.', '') ?>"
                                   class="form-control portal-input" required>
                        </div>
                        <div class="col-6 col-lg-3">
                            <label class="form-label small mb-1" for="metodo-<?= $idIns ?>">Método de pago</label>
                            <select id="metodo-<?= $idIns ?>" name="metodo" class="form-select portal-input" required>
                                <?php foreach ($metodos as $metodo): ?>
                                    <option value="<?= htmlspecialchars($metodo) ?>"><?= htmlspecialchars($metodo) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-lg-3">
                            <label class="form-label small mb-1" for="referencia-<?= $idIns ?>">Referencia (opcional)</label>
                            <input id="referencia-<?= $idIns ?>" type="text" name="referencia" maxlength="100"
                                   class="form-control portal-input" placeholder="N.º de transferencia o recibo">
                        </div>
                        <div class="col-12 col-lg-3">
                            <label class="form-label small mb-1" for="comprobante-<?= $idIns ?>">Comprobante (JPG, PNG o PDF · máx. 5MB)</label>
                            <input id="comprobante-<?= $idIns ?>" type="file" name="comprobante"
                                   accept=".jpg,.jpeg,.png,.webp,.pdf" class="form-control portal-input" required>
                        </div>
                        <div class="col-12 col-lg-1 d-grid">
                            <button type="submit" class="btn btn-accent fw-semibold">Enviar</button>
                        </div>
                    </div>
                    <p class="small mb-0 mt-2" style="color: var(--portal-muted);">
                        Queda en verificación: el saldo no cambia hasta que administración lo apruebe.
                    </p>
                </form>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Historial -->
    <div class="portal-card p-3 p-md-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
            <h2 class="portal-card-title mb-0">Historial de pagos</h2>

            <form method="get" action="/estudiante/pagos" class="d-flex gap-2 align-items-center">
                <label class="portal-label mb-0" for="filtroEstado">Estado</label>
                <select class="form-select portal-input" id="filtroEstado" name="estado" style="width: auto;">
                    <option value="">Todos</option>
                    <option value="Pagado" <?= $filtros['estado'] === 'Pagado' ? 'selected' : '' ?>>Pagado</option>
                    <option value="Pendiente" <?= $filtros['estado'] === 'Pendiente' ? 'selected' : '' ?>>Pendiente</option>
                    <option value="Fallido" <?= $filtros['estado'] === 'Fallido' ? 'selected' : '' ?>>Fallido</option>
                </select>
                <button type="submit" class="btn btn-accent fw-semibold">
                    <i class="bi bi-funnel me-1"></i>Filtrar
                </button>
                <?php if ($hayFiltro): ?>
                    <a href="/estudiante/pagos" class="btn btn-outline-secondary">Limpiar</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (count($historial) === 0): ?>
            <p class="small text-secondary mb-0 text-center py-3">
                No hay movimientos<?= $hayFiltro ? ' que coincidan con el filtro seleccionado' : '' ?>.
            </p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle portal-tabla mb-0">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Curso</th>
                            <th>Concepto</th>
                            <th class="text-end">Importe</th>
                            <th>Estado</th>
                            <th>Referencia</th>
                            <th>Comprobante</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historial as $mov): ?>
                            <tr>
                                <td class="text-nowrap">
                                    <?= date('d/m/Y', strtotime((string) $mov['fecha_pago'])) ?>
                                </td>
                                <td><?= htmlspecialchars($mov['curso']) ?></td>
                                <td class="small">
                                    <?= htmlspecialchars($mov['concepto']) ?>
                                    <?php if (!empty($mov['observaciones'])): ?>
                                        <div style="color: var(--portal-muted);"><?= htmlspecialchars($mov['observaciones']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end text-nowrap fw-semibold">
                                    <?= $dolares((float) $mov['monto_pagado']) ?>
                                </td>
                                <td>
                                    <span class="portal-pill <?= $claseEstado[$mov['estado']] ?? 'portal-pill--azul' ?>">
                                        <?= htmlspecialchars($mov['estado']) ?>
                                    </span>
                                    <?php if ($mov['estado'] === 'Pendiente' && !empty($mov['comprobante_subido'])): ?>
                                        <div class="small" style="color: var(--portal-muted);">En verificación</div>
                                    <?php elseif ($mov['estado'] === 'Fallido' && !empty($mov['motivo_verificacion'])): ?>
                                        <div class="small" style="color: var(--portal-muted);">
                                            Rechazado: <?= htmlspecialchars($mov['motivo_verificacion']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-nowrap">
                                    <?php if (!empty($mov['referencia_pago'])): ?>
                                        <code><?= htmlspecialchars($mov['referencia_pago']) ?></code>
                                    <?php else: ?>
                                        <span style="color: var(--portal-muted);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-nowrap">
                                    <?php $enlace = !empty($mov['comprobante_enlace']) ? (string) $mov['comprobante_enlace'] : ''; ?>
                                    <?php if ($enlace !== '' && !empty($mov['comprobante_disponible'])): ?>
                                        <a href="<?= htmlspecialchars($enlace) ?>"
                                           target="_blank" rel="noopener"
                                           class="btn btn-sm btn-accent fw-semibold">
                                            <i class="bi bi-eye me-1"></i>Ver comprobante
                                        </a>
                                    <?php elseif (!empty($mov['comprobante_url'])): ?>
                                        <span class="small" style="color: var(--portal-muted);">No disponible</span>
                                    <?php else: ?>
                                        <span class="small" style="color: var(--portal-muted);">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

<?php endif; ?>
