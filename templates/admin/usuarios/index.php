<?php
use App\Application\Formato\Fecha;
use App\Application\Security\Csrf;

$e = static fn ($v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$base = '/admin/usuarios';

$roles = ['' => 'Todos', 'admin' => 'Admin', 'instructor' => 'Instructor', 'estudiante' => 'Estudiante'];
$badgeRol = [
    'admin'       => 'admin-badge-rol-admin',
    'instructor'  => 'admin-badge-rol-instructor',
    'estudiante'  => 'admin-badge-rol-estudiante',
];
$etiquetaRol = ['admin' => 'Admin', 'instructor' => 'Instructor', 'estudiante' => 'Estudiante'];
?>
<div class="admin-academica">
    <!-- Cabecera de sección -->
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="portal-title mb-1">Usuarios del Sistema</h1>
            <p class="portal-subtitle mb-0">Administra las cuentas de acceso, roles asignados y seguridad del personal y estudiantes.</p>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-3">
            <span class="admin-fecha-actual"><i class="bi bi-calendar3 me-1"></i><?= $e(Fecha::larga()) ?></span>
            <a href="/admin/usuarios/nuevo" class="btn admin-btn-primary align-self-start">
                <i class="bi bi-plus-lg me-1"></i>Crear Usuario
            </a>
        </div>
    </div>

    <?php require __DIR__ . '/../../partials/flash.php'; ?>

    <!-- Píldoras de filtro por rol -->
    <nav class="d-flex flex-wrap gap-2 mb-4" aria-label="Filtrar por rol">
        <?php foreach ($roles as $clave => $etiqueta): ?>
            <?php $activa = $listado['filtros']['rol'] === $clave; ?>
            <a href="<?= $e($base . '?' . http_build_query(array_filter(['rol' => $clave, 'q' => $listado['filtros']['q'], 'estado' => $listado['filtros']['estado']], static fn ($v): bool => $v !== ''))) ?>"
               class="admin-rol-tab <?= $activa ? 'active' : '' ?>"
               <?= $activa ? 'aria-current="page"' : '' ?>><?= $e($etiqueta) ?></a>
        <?php endforeach; ?>
    </nav>

    <!-- Búsqueda y estado -->
    <form method="get" action="/admin/usuarios" class="portal-card p-3 mb-4" role="search">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-lg-5">
                <label for="q-usuarios" class="form-label">Buscar</label>
                <input id="q-usuarios" type="search" name="q" maxlength="150" class="form-control"
                       placeholder="Correo, nombre o #usuario…" value="<?= $e($listado['filtros']['q']) ?>">
            </div>
            <div class="col-6 col-lg-3">
                <label for="filtro-estado" class="form-label">Estado de la cuenta</label>
                <select id="filtro-estado" name="estado" class="form-select">
                    <option value="">Todos</option>
                    <option value="activo" <?= $listado['filtros']['estado'] === 'activo' ? 'selected' : '' ?>>Activo</option>
                    <option value="bloqueado" <?= $listado['filtros']['estado'] === 'bloqueado' ? 'selected' : '' ?>>Bloqueado</option>
                </select>
            </div>
            <div class="col-auto d-flex gap-2">
                <button class="btn admin-btn-primary">Filtrar</button>
                <a href="/admin/usuarios" class="btn admin-btn-outline">Limpiar</a>
            </div>
            <?php if ($listado['filtros']['rol'] !== ''): ?>
                <input type="hidden" name="rol" value="<?= $e($listado['filtros']['rol']) ?>">
            <?php endif; ?>
        </div>
    </form>

    <!-- Tabla -->
    <section class="portal-card overflow-hidden">
        <div class="p-4 d-flex justify-content-between">
            <h2 class="portal-card-title mb-0">Cuentas de acceso</h2>
            <span class="admin-count"><?= (int) $listado['total'] ?> usuarios</span>
        </div>
        <div class="table-responsive">
            <table class="table admin-table align-middle mb-0">
                <thead>
                    <tr>
                        <th>NOMBRE</th>
                        <th>EMAIL</th>
                        <th>ROL</th>
                        <th>ÚLTIMO ACCESO</th>
                        <th>ESTADO</th>
                        <th class="admin-actions">ACCIONES</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$listado['filas']): ?>
                        <tr><td colspan="6" class="text-center py-5">No se encontraron cuentas de acceso.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($listado['filas'] as $u): ?>
                        <?php
                        $esPropia = (int) $u['id_usuario'] === (int) $idSesion;
                        $bloqueada = (int) $u['activo'] !== 1;
                        $notaPersona = '';
                        if ($u['rol'] === 'estudiante' && $u['estudiante_estado'] !== null && $u['estudiante_estado'] !== 'Activo') {
                            $notaPersona = 'Estudiante suspendido';
                        } elseif ($u['rol'] === 'instructor' && $u['instructor_activo'] !== null && (int) $u['instructor_activo'] !== 1) {
                            $notaPersona = 'Instructor inactivo';
                        }
                        ?>
                        <tr>
                            <td class="admin-name-cell">
                                <span class="d-flex align-items-center gap-2">
                                    <span class="admin-student-avatar">
                                        <?php if (!empty($u['avatar'])): ?>
                                            <img src="<?= $e($u['avatar']) ?>" alt="Foto de <?= $e($u['nombre']) ?>" loading="lazy">
                                        <?php else: ?>
                                            <?= $e($u['iniciales']) ?>
                                        <?php endif; ?>
                                    </span>
                                    <span>
                                        <strong><?= $e($u['nombre']) ?></strong>
                                        <small class="d-block admin-muted">#<?= (int) $u['id_usuario'] ?><?= $u['persona_ficha'] ? '' : ' · cuenta administrativa' ?></small>
                                    </span>
                                </span>
                            </td>
                            <td><?= $e($u['email_usuario']) ?></td>
                            <td><span class="admin-badge <?= $badgeRol[$u['rol']] ?? 'admin-badge-warning' ?>"><?= $e($etiquetaRol[$u['rol']] ?? $u['rol']) ?></span></td>
                            <td class="admin-muted text-nowrap"><?= $e($u['ultimo_acceso_relativo']) ?></td>
                            <td class="text-nowrap">
                                <span class="admin-dot <?= $bloqueada ? 'admin-dot-bloqueado' : 'admin-dot-activo' ?> <?= $bloqueada ? 'text-danger' : 'text-success' ?>">
                                    <?= $e($u['estado_texto']) ?>
                                </span>
                                <?php if ($notaPersona !== ''): ?>
                                    <small class="d-block admin-muted"><?= $e($notaPersona) ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="admin-actions text-nowrap">
                                <?php if ($esPropia): ?>
                                    <span class="small admin-muted" title="No puedes bloquear tu propia cuenta">Tu cuenta</span>
                                <?php else: ?>
                                    <form action="/admin/usuarios/<?= (int) $u['id_usuario'] ?>/bloqueo" method="post" class="d-inline"
                                          data-confirm="<?= $bloqueada
                                              ? '¿Reactivar esta cuenta? La persona volverá a poder iniciar sesión.'
                                              : '¿Bloquear esta cuenta? Se cerrarán sus sesiones abiertas y no podrá iniciar sesión.' ?>">
                                        <?= Csrf::campo() ?>
                                        <input type="hidden" name="accion" value="<?= $bloqueada ? 'desbloquear' : 'bloquear' ?>">
                                        <button class="btn admin-icon-btn <?= $bloqueada ? 'admin-restore' : 'admin-delete' ?>"
                                                title="<?= $bloqueada ? 'Reactivar cuenta' : 'Bloquear cuenta' ?>"
                                                aria-label="<?= $bloqueada ? 'Reactivar' : 'Bloquear' ?> cuenta de <?= $e($u['nombre']) ?>">
                                            <i class="bi <?= $bloqueada ? 'bi-unlock' : 'bi-lock' ?>"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <a href="/admin/usuarios/<?= (int) $u['id_usuario'] ?>" class="btn admin-icon-btn admin-edit"
                                   title="Ver ficha del usuario" aria-label="Ver ficha de <?= $e($u['nombre']) ?>">
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
<script src="/js/admin-academica.js" defer></script>
