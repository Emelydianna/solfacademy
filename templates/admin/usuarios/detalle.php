<?php
use App\Application\Formato\Fecha;
use App\Application\Security\Csrf;

$e = static fn ($v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$id = (int) $usuario['id_usuario'];
$badgeRol = [
    'admin'      => 'admin-badge-rol-admin',
    'instructor' => 'admin-badge-rol-instructor',
    'estudiante' => 'admin-badge-rol-estudiante',
];
$etiquetaRol = ['admin' => 'Admin', 'instructor' => 'Instructor', 'estudiante' => 'Estudiante'];
$bloqueada = (int) $usuario['activo'] !== 1;
$bloqueable = (bool) $usuario['puede_bloquearse'];
$motivo = (string) ($usuario['motivo_bloqueo'] ?? '');
?>
<div class="admin-academica">
    <div class="d-flex flex-wrap justify-content-between gap-3 mb-4">
        <div>
            <h1 class="portal-title mb-1"><?= $e($usuario['nombre']) ?></h1>
            <p class="portal-subtitle mb-0">
                Cuenta #<?= $id ?> · <?= $e($usuario['email_usuario']) ?> ·
                <span class="admin-badge <?= $badgeRol[$usuario['rol']] ?? 'admin-badge-warning' ?>"><?= $e($etiquetaRol[$usuario['rol']] ?? $usuario['rol']) ?></span>
            </p>
        </div>
        <div class="d-flex gap-2 align-self-start flex-wrap">
            <a href="/admin/usuarios" class="btn admin-btn-outline">Volver</a>
            <?php if (!empty($usuario['persona_ficha'])): ?>
                <a href="<?= $e($usuario['persona_ficha']) ?>" class="btn admin-btn-outline">
                    Ficha de <?= $usuario['rol'] === 'estudiante' ? 'estudiante' : 'instructor' ?>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($usuario['nota_estado'])): ?>
        <div class="portal-alert portal-alert--peligro p-3 mb-4"><?= $e($usuario['nota_estado']) ?></div>
    <?php endif; ?>

    <div class="row g-4 mb-4">
        <!-- Cuenta -->
        <div class="col-lg-6">
            <section class="portal-card p-4 h-100">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <span class="admin-student-avatar admin-student-avatar-lg">
                        <?php if (!empty($usuario['avatar'])): ?>
                            <img src="<?= $e($usuario['avatar']) ?>" alt="Foto de <?= $e($usuario['nombre']) ?>">
                        <?php else: ?>
                            <?= $e($usuario['iniciales']) ?>
                        <?php endif; ?>
                    </span>
                    <div>
                        <h2 class="portal-card-title mb-1"><?= $e($usuario['nombre']) ?></h2>
                        <span class="admin-dot <?= $bloqueada ? 'admin-dot-bloqueado text-danger' : 'admin-dot-activo text-success' ?>">
                            <?= $e($usuario['estado_texto']) ?>
                        </span>
                    </div>
                </div>

                <ul class="list-unstyled mb-0">
                    <li class="mb-2"><i class="bi bi-envelope me-1"></i> Correo de acceso: <strong><?= $e($usuario['email_usuario']) ?></strong></li>
                    <li class="mb-2"><i class="bi bi-clock-history me-1"></i> Último acceso:
                        <strong><?= $e($usuario['ultimo_acceso_relativo']) ?></strong>
                        <span class="admin-muted">· <?= $e(Fecha::corta(isset($usuario['ultimo_acceso']) ? (string) $usuario['ultimo_acceso'] : null)) ?></span>
                    </li>
                    <li class="mb-2"><i class="bi bi-shield-lock me-1"></i> Versión de sesión:
                        <strong><?= (int) $usuario['version_sesion'] ?></strong>
                        <span class="admin-muted d-block small">Bloquear la cuenta la incrementa y cierra las sesiones abiertas.</span>
                    </li>
                    <?php if ($usuario['rol'] === 'estudiante' && $usuario['estudiante_estado'] !== null): ?>
                        <li class="mb-0"><i class="bi bi-mortarboard me-1"></i> Estado académico:
                            <span class="admin-badge <?= $usuario['estudiante_estado'] === 'Activo' ? 'admin-badge-success' : 'admin-badge-danger' ?>"><?= $e($usuario['estudiante_estado']) ?></span>
                        </li>
                    <?php elseif ($usuario['rol'] === 'instructor' && $usuario['instructor_activo'] !== null): ?>
                        <li class="mb-0"><i class="bi bi-person-workspace me-1"></i> Estado del instructor:
                            <span class="admin-badge <?= (int) $usuario['instructor_activo'] === 1 ? 'admin-badge-success' : 'admin-badge-danger' ?>"><?= (int) $usuario['instructor_activo'] === 1 ? 'Activo' : 'Inactivo' ?></span>
                        </li>
                    <?php endif; ?>
                </ul>
            </section>
        </div>

        <!-- Persona vinculada + acciones -->
        <div class="col-lg-6">
            <section class="portal-card p-4 h-100">
                <h2 class="portal-card-title mb-3">Persona vinculada</h2>

                <?php if (empty($usuario['persona_ficha'])): ?>
                    <p class="admin-muted mb-4">
                        Cuenta administrativa sin ficha de persona: solo existe para acceder al panel
                        (email y contraseña se gestionan aquí).
                    </p>
                <?php else: ?>
                    <p class="mb-1"><strong><?= $e($usuario['nombre']) ?></strong></p>
                    <p class="mb-1 admin-muted">
                        <?= $usuario['rol'] === 'estudiante' ? 'Estudiante' : 'Instructor' ?> ·
                        correo de ficha: <?= $e($usuario['rol'] === 'estudiante' ? $usuario['estudiante_email'] : $usuario['instructor_email']) ?>
                    </p>
                    <?php if ($usuario['rol'] === 'estudiante' && !empty($usuario['fecha_inscripcion'])): ?>
                        <p class="mb-1 admin-muted">Alta: <?= $e($usuario['fecha_inscripcion']) ?></p>
                    <?php elseif ($usuario['rol'] === 'instructor' && $usuario['instructor_activo'] !== null): ?>
                        <p class="mb-1 admin-muted"><?= (int) $usuario['anios_experiencia'] ?> años de experiencia</p>
                    <?php endif; ?>
                <?php endif; ?>

                <hr class="admin-border">
                <h2 class="portal-card-title mb-3">Seguridad</h2>

                <?php if (!$bloqueable): ?>
                    <div class="portal-alert portal-alert--peligro p-3 mb-0">
                        <?= $e($motivo !== '' ? $motivo : 'No puedes bloquear tu propia cuenta de administrador.') ?>
                    </div>
                <?php elseif ($motivo !== ''): ?>
                    <div class="portal-alert portal-alert--peligro p-3 mb-3"><?= $e($motivo) ?></div>
                    <button class="btn admin-btn-outline" disabled>
                        <i class="bi bi-lock me-1"></i><?= $bloqueada ? 'Reactivar cuenta' : 'Bloquear cuenta' ?>
                    </button>
                <?php else: ?>
                    <p class="small admin-muted mb-3">
                        <?= $bloqueada
                            ? 'La cuenta está bloqueada: la persona no puede iniciar sesión.'
                            : 'Al bloquear se cierra enseguida cualquier sesión abierta de esta persona.' ?>
                    </p>
                    <form action="/admin/usuarios/<?= $id ?>/bloqueo" method="post"
                          data-confirm="<?= $bloqueada
                              ? '¿Reactivar esta cuenta? La persona volverá a poder iniciar sesión.'
                              : '¿Bloquear esta cuenta? Se cerrarán sus sesiones abiertas y no podrá iniciar sesión.' ?>">
                        <?= Csrf::campo() ?>
                        <input type="hidden" name="accion" value="<?= $bloqueada ? 'desbloquear' : 'bloquear' ?>">
                        <button class="btn <?= $bloqueada ? 'admin-btn-primary' : 'admin-btn-outline admin-delete' ?>">
                            <i class="bi <?= $bloqueada ? 'bi-unlock' : 'bi-lock' ?> me-1"></i>
                            <?= $bloqueada ? 'REACTIVAR CUENTA' : 'BLOQUEAR CUENTA' ?>
                        </button>
                    </form>
                <?php endif; ?>
            </section>
        </div>
    </div>

    <?php require __DIR__ . '/../partials/confirmacion.php'; ?>
</div>
<script src="/js/admin-academica.js" defer></script>
