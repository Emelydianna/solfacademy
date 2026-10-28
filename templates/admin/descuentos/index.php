<?php
use App\Application\Descuentos\DescuentosService;
use App\Application\Formato\Dinero;
use App\Application\Security\Csrf;

$e = static fn ($v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$base = '/admin/descuentos';
$hoy = date('Y-m-d');

$valorDescuento = static fn (array $d): string => $d['porcentaje'] !== null
    ? $d['porcentaje'] . '%'
    : Dinero::monto((float) $d['monto_fijo']);
?>
<div class="admin-academica">
    <div class="d-flex flex-wrap justify-content-between gap-3 mb-4">
        <div>
            <h1 class="portal-title mb-1">Descuentos</h1>
            <p class="portal-subtitle mb-0">Códigos que el estudiante aplica al solicitar la matrícula. Solo el administrador los crea.</p>
        </div>
        <a href="/admin/descuentos/nuevo" class="btn admin-btn-primary align-self-start">
            <i class="bi bi-plus-lg me-1"></i>Nuevo código
        </a>
    </div>

    <?php if (!empty($flash)): ?>
        <div role="status" class="portal-alert portal-alert--<?= $flash['tipo'] === 'error' ? 'peligro' : 'exito' ?> p-3 mb-4">
            <?= $e($flash['mensaje']) ?>
        </div>
    <?php endif; ?>

    <form method="get" action="/admin/descuentos" class="portal-card p-3 mb-4" role="search">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-lg-5">
                <label for="q-descuentos" class="form-label">Buscar</label>
                <input id="q-descuentos" type="search" name="q" maxlength="150" class="form-control"
                       placeholder="Código, descripción o curso…" value="<?= $e($listado['filtros']['q']) ?>">
            </div>
            <div class="col-6 col-lg-3">
                <label for="filtro-estado" class="form-label">Estado</label>
                <select id="filtro-estado" name="estado" class="form-select">
                    <option value="">Todos</option>
                    <option value="1" <?= $listado['filtros']['estado'] === '1' ? 'selected' : '' ?>>Activos</option>
                    <option value="0" <?= $listado['filtros']['estado'] === '0' ? 'selected' : '' ?>>Inactivos</option>
                </select>
            </div>
            <div class="col-auto d-flex gap-2">
                <button class="btn admin-btn-primary">Filtrar</button>
                <a href="/admin/descuentos" class="btn admin-btn-outline">Limpiar</a>
            </div>
        </div>
    </form>

    <section class="portal-card overflow-hidden">
        <div class="p-4 d-flex justify-content-between">
            <h2 class="portal-card-title mb-0">Códigos registrados</h2>
            <span class="admin-count"><?= (int) $listado['total'] ?> códigos</span>
        </div>
        <div class="table-responsive">
            <table class="table admin-table align-middle mb-0">
                <thead>
                    <tr>
                        <th>CÓDIGO</th>
                        <th>DESCUENTO</th>
                        <th>ALCANCE</th>
                        <th>VIGENCIA</th>
                        <th>USOS</th>
                        <th>ESTADO</th>
                        <th class="admin-actions">ACCIONES</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$listado['filas']): ?>
                        <tr><td colspan="7" class="text-center py-5">No se encontraron códigos de descuento.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($listado['filas'] as $d): ?>
                        <?php
                        $vigente = (int) ($d['vigente'] ?? 0) === 1;
                        $activo = (int) $d['activo'] === 1;
                        $sinLimite = (int) $d['usos_disponibles'] === DescuentosService::USOS_SIN_LIMITE;
                        $agotado = !$sinLimite && (int) $d['usos_realizados'] >= (int) $d['usos_disponibles'];
                        ?>
                        <tr>
                            <td><strong><?= $e($d['codigo']) ?></strong>
                                <?php if (!empty($d['descripcion'])): ?>
                                    <small class="d-block admin-muted"><?= $e($d['descripcion']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= $e($valorDescuento($d)) ?></strong>
                                <small class="d-block admin-muted"><?= $d['porcentaje'] !== null ? 'Porcentaje' : 'Monto fijo' ?></small></td>
                            <td><?= $e($d['curso']) ?>
                                <small class="d-block admin-muted"><?= $d['id_curso'] !== null ? 'Solo este curso' : 'Cualquier curso' ?></small></td>
                            <td class="text-nowrap"><?= $e($d['fecha_inicio']) ?> → <?= $e($d['fecha_fin']) ?>
                                <small class="d-block admin-muted"><?= $vigente ? 'Vigente hoy' : ($hoy < (string) $d['fecha_inicio'] ? 'Sin iniciar' : 'Vencido') ?></small></td>
                            <td class="text-nowrap">
                                <?= $sinLimite ? 'Ilimitados' : (int) $d['usos_realizados'] . ' / ' . (int) $d['usos_disponibles'] ?>
                                <?php if ($agotado): ?>
                                    <span class="admin-badge admin-badge-danger d-block">Agotado</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="admin-badge <?= $activo ? 'admin-badge-success' : 'admin-badge-danger' ?>">
                                <?= $activo ? 'Activo' : 'Inactivo' ?></span></td>
                            <td class="admin-actions text-nowrap">
                                <a href="/admin/descuentos/<?= (int) $d['id_descuento'] ?>" class="btn admin-icon-btn admin-edit"
                                   title="Editar código" aria-label="Editar código <?= $e($d['codigo']) ?>">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form action="/admin/descuentos/<?= (int) $d['id_descuento'] ?>/estado" method="post"
                                      class="d-inline" data-confirm="<?= $activo ? '¿Desactivar el código? Dejará de poder usarse en nuevas matrículas.' : '¿Activar el código? Volverá a poder usarse mientras tenga vigencia y usos.' ?>">
                                    <?= Csrf::campo() ?>
                                    <input type="hidden" name="activo" value="<?= $activo ? '0' : '1' ?>">
                                    <button class="btn admin-icon-btn <?= $activo ? 'admin-delete' : 'admin-edit' ?>"
                                            title="<?= $activo ? 'Desactivar' : 'Activar' ?>"
                                            aria-label="<?= $activo ? 'Desactivar' : 'Activar' ?> código <?= $e($d['codigo']) ?>">
                                        <i class="bi <?= $activo ? 'bi-pause-circle' : 'bi-play-circle' ?>"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php require __DIR__ . '/../partials/paginacion.php'; ?>
    </section>

    <?php require __DIR__ . '/../partials/confirmacion.php'; ?>
</div>
<script src="/js/admin-academica.js" defer></script>
