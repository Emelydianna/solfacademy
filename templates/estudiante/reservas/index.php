<?php
$reservas = $reservas ?? [];
$flash = $flash ?? null;
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="portal-title mb-1">Mis Reservas</h1>
        <p class="portal-subtitle mb-0">Clases extras y reservas de cabinas de piano.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/estudiante/reservas/extras" class="btn btn-accent fw-bold"><i class="bi bi-plus-lg me-1"></i>Solicitar Clase Extra</a>
        <a href="/estudiante/reservas/cabinas" class="btn btn-outline-secondary fw-semibold"><i class="bi bi-music-note me-1"></i>Reservar Cabina</a>
    </div>
</div>

<?php require __DIR__ . '/../../partials/flash.php'; ?>

<?php if (count($reservas) === 0): ?>
    <div class="portal-card p-4 text-center">
        <i class="bi bi-calendar-plus fs-1 d-block mb-2" style="color: var(--portal-muted);"></i>
        <p class="mb-1 fw-semibold">Aún no tienes reservas</p>
        <p class="small text-secondary mb-3">Solicita una clase extra o reserva una cabina de piano.</p>
        <div class="d-flex justify-content-center gap-2">
            <a href="/estudiante/reservas/extras" class="btn btn-accent fw-semibold">Solicitar Clase Extra</a>
            <a href="/estudiante/reservas/cabinas" class="btn btn-outline-secondary">Reservar Cabina</a>
        </div>
    </div>
<?php else: ?>
    <div class="portal-card p-3 p-md-4">
        <div class="table-responsive">
            <table class="table align-middle portal-tabla mb-0">
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Detalle</th>
                        <th>Fecha</th>
                        <th>Horario</th>
                        <th>Motivo</th>
                        <th>Estado</th>
                        <th class="text-end">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reservas as $r): ?>
                        <tr>
                            <td><?= $r['tipo'] === 'cabina' ? 'Cabina' : 'Clase Extra' ?></td>
                            <td>
                                <?php if ($r['tipo'] === 'cabina'): ?>
                                    <?= htmlspecialchars($r['cabina']) ?>
                                <?php else: ?>
                                    <?= htmlspecialchars($r['curso']) ?> · <?= htmlspecialchars($r['instructor']) ?>
                                <?php endif; ?>
                            </td>
                            <td class="text-nowrap"><?= date('d/m/Y', strtotime($r['fecha'])) ?></td>
                            <td class="text-nowrap"><?= substr($r['hora_inicio'], 0, 5) ?> - <?= substr($r['hora_fin'], 0, 5) ?></td>
                            <td class="small"><?= htmlspecialchars($r['motivo'] ?? '—') ?></td>
                            <td>
                                <?php
                                $est = $r['estado'];
                                $cls = match($est) {
                                    'Confirmada', 'Asistida' => 'portal-pill--verde',
                                    'Pendiente' => 'portal-pill--dorado',
                                    'Cancelada', 'No asistió' => 'portal-pill--rojo',
                                    default => 'portal-pill--azul'
                                };
                                ?>
                                <span class="portal-pill <?= $cls ?>"><?= htmlspecialchars($est) ?></span>
                            </td>
                            <td class="text-end">
                                <?php if (in_array($r['estado'], ['Pendiente', 'Confirmada'], true)): ?>
                                    <form method="post" action="/estudiante/reservas/<?= (int)$r['id_reserva'] ?>/cancelar" style="display:inline" data-confirm="¿Cancelar esta reserva?">
                                        <?= \App\Application\Security\Csrf::campo() ?>
                                        <input type="hidden" name="tipo" value="<?= $r['tipo'] ?>">
                                        <button class="btn btn-sm btn-outline-secondary">Cancelar</button>
                                    </form>
                                <?php else: ?>
                                    <span style="color: var(--portal-muted);">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
