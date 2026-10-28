<?php
use App\Application\Formato\Dinero;

$e = static fn ($v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$base = '/admin/pagos';
$badge = ['Pendiente' => 'admin-badge-warning', 'Pagado' => 'admin-badge-success', 'Fallido' => 'admin-badge-danger'];
$dinero = static fn ($v): string => Dinero::monto((float) ($v ?? 0));
?>
<div class="admin-academica">
    <div class="d-flex flex-wrap justify-content-between gap-3 mb-4">
        <div>
            <h1 class="portal-title mb-1">Pagos</h1>
            <p class="portal-subtitle mb-0">Verificación de los comprobantes enviados desde el portal del estudiante.</p>
        </div>
        <a href="/admin/pagos?estado=Pendiente" class="btn admin-btn-outline align-self-start">
            <i class="bi bi-hourglass-split me-1"></i>Por verificar
        </a>
    </div>

    <!-- Indicadores -->
    <div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 g-3 mb-4">
        <div class="col">
            <div class="portal-card p-3 h-100">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                    <div class="portal-kpi-label pt-1">Por verificar</div>
                    <span class="portal-kpi-icon"><i class="bi bi-hourglass-split"></i></span>
                </div>
                <div class="portal-kpi-value"><?= (int) $resumen['por_verificar'] ?></div>
                <div class="small mt-1" style="color:#94a3b8;">
                    <i class="bi bi-envelope-paper me-1"></i>Comprobantes esperando revisión
                </div>
            </div>
        </div>
        <div class="col">
            <div class="portal-card p-3 h-100">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                    <div class="portal-kpi-label pt-1">Monto en espera</div>
                    <span class="portal-kpi-icon"><i class="bi bi-cash-stack"></i></span>
                </div>
                <div class="portal-kpi-value"><?= $e($dinero($resumen['en_espera'])) ?></div>
                <div class="small mt-1" style="color:#94a3b8;">
                    <i class="bi bi-dash-lg me-1"></i>No cuenta como saldo hasta acreditarlo
                </div>
            </div>
        </div>
        <div class="col">
            <div class="portal-card p-3 h-100">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                    <div class="portal-kpi-label pt-1">Verificados hoy</div>
                    <span class="portal-kpi-icon portal-kpi-icon--azul"><i class="bi bi-patch-check"></i></span>
                </div>
                <div class="portal-kpi-value"><?= (int) $resumen['verificados_hoy'] ?></div>
                <div class="small mt-1" style="color:#94a3b8;">
                    <i class="bi bi-calendar-check me-1"></i>Comprobantes acreditados hoy
                </div>
            </div>
        </div>
        <div class="col">
            <div class="portal-card p-3 h-100">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                    <div class="portal-kpi-label pt-1">Total acreditado</div>
                    <span class="portal-kpi-icon portal-kpi-icon--verde"><i class="bi bi-wallet2"></i></span>
                </div>
                <div class="portal-kpi-value"><?= $e($dinero($resumen['acreditado'])) ?></div>
                <div class="small mt-1" style="color:#94a3b8;">
                    <i class="bi bi-graph-up-arrow me-1"></i>Suma de pagos verificados
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <form method="get" action="/admin/pagos" class="portal-card p-3 mb-4" role="search">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-lg-5">
                <label for="q-pagos" class="form-label">Buscar</label>
                <input id="q-pagos" type="search" name="q" maxlength="150" class="form-control"
                       placeholder="Estudiante, email, curso, referencia o #pago…" value="<?= $e($listado['filtros']['q']) ?>">
            </div>
            <div class="col-6 col-lg-3">
                <label for="filtro-estado" class="form-label">Estado</label>
                <select id="filtro-estado" name="estado" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach (array_keys($badge) as $estado): ?>
                        <option value="<?= $estado ?>" <?= $listado['filtros']['estado'] === $estado ? 'selected' : '' ?>><?= $estado ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto d-flex gap-2">
                <button class="btn admin-btn-primary">Filtrar</button>
                <a href="/admin/pagos" class="btn admin-btn-outline">Limpiar</a>
            </div>
        </div>
    </form>

    <!-- Listado -->
    <section class="portal-card overflow-hidden">
        <div class="p-4 d-flex justify-content-between">
            <h2 class="portal-card-title mb-0">Movimientos de pago</h2>
            <span class="admin-count"><?= (int) $listado['total'] ?> pagos</span>
        </div>
        <div class="table-responsive">
            <table class="table admin-table align-middle mb-0">
                <thead>
                    <tr>
                        <th># / FECHA</th>
                        <th>ESTUDIANTE</th>
                        <th>CURSO / GRUPO</th>
                        <th class="text-end">IMPORTE</th>
                        <th class="text-end">SALDO MATRÍCULA</th>
                        <th>ESTADO</th>
                        <th>COMPROBANTE</th>
                        <th class="admin-actions">ACCIONES</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$listado['filas']): ?>
                        <tr><td colspan="8" class="text-center py-5">No se encontraron pagos.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($listado['filas'] as $p): ?>
                        <?php
                        $saldo = $p['saldo'] ?? null;
                        $conComprobante = !empty($p['comprobante_url']);
                        ?>
                        <tr>
                            <td><strong>#<?= (int) $p['id_pago'] ?></strong>
                                <small class="d-block admin-muted"><?= $e($p['fecha_pago']) ?></small></td>
                            <td class="admin-name-cell"><strong><?= $e($p['estudiante']) ?></strong>
                                <small class="d-block admin-muted"><?= $e($p['email']) ?></small></td>
                            <td class="admin-name-cell"><?= $e($p['curso']) ?>
                                <small class="d-block admin-muted">Matrícula #<?= (int) $p['id_inscripcion'] ?> · Grupo #<?= (int) $p['id_grupo'] ?></small></td>
                            <td class="text-end"><strong><?= $e($dinero($p['monto_pagado'])) ?></strong>
                                <small class="d-block admin-muted"><?= $e($p['concepto']) ?><?= $p['metodo'] ? ' · ' . $e($p['metodo']) : '' ?></small></td>
                            <td class="text-end">
                                <?php if ($saldo === null): ?>
                                    <span class="admin-muted">—</span>
                                <?php elseif ((float) $saldo > 0): ?>
                                    <?= $e($dinero($saldo)) ?>
                                <?php else: ?>
                                    <span class="text-success">$ 0.00</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="admin-badge <?= $badge[$p['estado']] ?? 'admin-badge-warning' ?>"><?= $e($p['estado']) ?></span></td>
                            <td class="text-nowrap">
                                <?php if (!$conComprobante): ?>
                                    <span class="admin-muted">—</span>
                                <?php elseif (!empty($p['comprobante_disponible'])): ?>
                                    <a href="<?= $e($p['comprobante_enlace']) ?>" target="_blank" rel="noopener"
                                       class="btn btn-sm admin-btn-outline"><i class="bi bi-eye me-1"></i>Ver</a>
                                <?php else: ?>
                                    <span class="small admin-muted">No disponible</span>
                                <?php endif; ?>
                            </td>
                            <td class="admin-actions">
                                <a href="/admin/pagos/<?= (int) $p['id_pago'] ?>" class="btn admin-icon-btn admin-edit"
                                   title="<?= $p['estado'] === 'Pendiente' ? 'Verificar comprobante' : 'Ver pago' ?>"
                                   aria-label="Ver pago #<?= (int) $p['id_pago'] ?>">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php require __DIR__ . '/../partials/paginacion.php'; ?>
    </section>
</div>
