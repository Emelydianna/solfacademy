<!-- Mi Perfil -->
<?php
/**
 * Mi Perfil
 */
$perfil  = $perfil ?? [];
$valores = $valores ?? [];
$errores = $errores ?? [];
$flash   = $flash ?? null;

$err = static fn (string $campo): string => !empty($errores[$campo]) ? ' is-invalid' : '';

$fechaInscripcion = !empty($perfil['fecha_inscripcion'])
    ? date('d/m/Y', strtotime((string) $perfil['fecha_inscripcion']))
    : '—';

$nombreCompleto = trim(($perfil['nombre'] ?? '') . ' ' . ($perfil['apellido'] ?? ''));
$nombreCompleto = $nombreCompleto !== '' ? $nombreCompleto : 'Estudiante';
$fotoUrl = $perfil['foto_url'] ?? '';
$generos = $perfil['generos_favoritos'] ?? '';
$objetivo = $perfil['objetivo_academia'] ?? '';
$historial = $perfil['historial_instrumentos'] ?? [];
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="portal-title mb-1">Mi Perfil</h1>
        <p class="portal-subtitle mb-0">Consulta y actualiza tus datos personales.</p>
    </div>
    <button form="form-perfil-principal" type="submit" class="btn fw-bold" style="background:#ffc107;color:#000;">
        <i class="bi bi-check2 me-1"></i>GUARDAR CAMBIOS
    </button>
</div>

<?php if (is_array($flash) && ($flash['mensaje'] ?? '') !== ''): ?>
    <div class="portal-alert portal-alert--exito d-flex align-items-center gap-2 mb-4" role="alert">
        <i class="bi bi-check-circle-fill"></i>
        <span><?= htmlspecialchars($flash['mensaje']) ?></span>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Columna Izquierda -->
    <div class="col-lg-4">
        <div class="d-flex flex-column gap-4">
            <!-- Tarjeta Foto y Rol -->
            <div class="portal-card p-4 text-center h-100">
                <div class="mx-auto mb-3" style="width:140px;height:140px;border-radius:50%;border:2px solid #ffc107;overflow:hidden;background:rgba(255,255,255,0.05);display:flex;align-items:center;justify-content:center;">
                    <?php if ($fotoUrl): ?>
                        <img src="<?= htmlspecialchars($fotoUrl) ?>" alt="Foto perfil" style="width:100%;height:100%;object-fit:cover;">
                    <?php else: ?>
                        <span style="font-size:3rem;font-weight:bold;color:#fff;"><?= htmlspecialchars(mb_strtoupper(mb_substr($nombreCompleto,0,1))) ?></span>
                    <?php endif; ?>
                </div>
                <h2 class="portal-card-title mb-1" style="font-size:1.25rem;"><?= htmlspecialchars($nombreCompleto) ?></h2>
                <p class="mb-3" style="color:#94a3b8;">Estudiante</p>
                <button type="button" class="btn btn-outline-light w-100" data-bs-toggle="modal" data-bs-target="#modalProximamente" data-modal-titulo="Cambiar Foto" data-modal-mensaje="La carga de foto de perfil estará disponible muy pronto.">
                    <i class="bi bi-camera me-1"></i>Cambiar Foto
                </button>
            </div>

            <!-- Preferencias Musicales -->
            <div class="portal-card p-4">
                <h3 class="portal-card-title mb-3">Preferencias Musicales</h3>
                <div class="mb-3">
                    <div class="small mb-1" style="color:#94a3b8;">Géneros Favoritos</div>
                    <div style="color:#fff;"><?= $generos !== '' ? htmlspecialchars($generos) : '—' ?></div>
                </div>
                <div>
                    <div class="small mb-1" style="color:#94a3b8;">Objetivo en la Academia</div>
                    <div style="color:#fff;"><?= $objetivo !== '' ? htmlspecialchars($objetivo) : '—' ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Columna Derecha -->
    <div class="col-lg-8">
        <div class="d-flex flex-column gap-4">
            <!-- Datos Personales -->
            <div class="portal-card p-3 p-md-4">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <h2 class="portal-card-title mb-0">Datos Personales</h2>
                </div>

                <form id="form-perfil-principal" method="post" action="/estudiante/perfil" novalidate>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="nombre">Nombre</label>
                            <input type="text" class="form-control<?= $err('nombre') ?>" id="nombre" name="nombre"
                                   maxlength="100" required value="<?= htmlspecialchars($valores['nombre'] ?? ($perfil['nombre'] ?? '')) ?>">
                            <?php if (!empty($errores['nombre'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errores['nombre']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="apellido">Apellidos</label>
                            <input type="text" class="form-control<?= $err('apellido') ?>" id="apellido" name="apellido"
                                   maxlength="100" required value="<?= htmlspecialchars($valores['apellido'] ?? ($perfil['apellido'] ?? '')) ?>">
                            <?php if (!empty($errores['apellido'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errores['apellido']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="email">Correo Electrónico</label>
                            <input type="email" class="form-control<?= $err('email') ?>" id="email" name="email"
                                   maxlength="150" required value="<?= htmlspecialchars($valores['email'] ?? ($perfil['email'] ?? '')) ?>">
                            <?php if (!empty($errores['email'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errores['email']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="telefono">Teléfono de Contacto</label>
                            <input type="tel" class="form-control<?= $err('telefono') ?>" id="telefono" name="telefono"
                                   maxlength="20" value="<?= htmlspecialchars($valores['telefono'] ?? ($perfil['telefono'] ?? '')) ?>">
                            <?php if (!empty($errores['telefono'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errores['telefono']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="direccion">Dirección de Residencia</label>
                            <input type="text" class="form-control<?= $err('direccion') ?>" id="direccion" name="direccion"
                                   maxlength="255" value="<?= htmlspecialchars($valores['direccion'] ?? ($perfil['direccion'] ?? '')) ?>">
                            <?php if (!empty($errores['direccion'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errores['direccion']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Historial de Instrumentos Estudiados -->
            <div class="portal-card p-3 p-md-4">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <h2 class="portal-card-title mb-0">Historial de Instrumentos Estudiados</h2>
                </div>
                <?php if (count($historial) === 0): ?>
                    <p class="mb-0" style="color:#94a3b8;">Sin registros.</p>
                <?php else: ?>
                    <div class="row row-cols-1 row-cols-md-3 g-3">
                        <?php foreach ($historial as $h): ?>
                            <div class="col">
                                <div class="p-3 h-100" style="border:1px solid var(--portal-border);border-radius:10px;background:rgba(255,255,255,0.02);">
                                    <div class="fw-bold mb-1" style="color:#ffc107;"><?= htmlspecialchars($h['instrumento']) ?></div>
                                    <div class="small" style="color:#94a3b8;">Periodo: <?= htmlspecialchars($h['periodo']) ?></div>
                                    <div class="small" style="color:#94a3b8;">Nivel: <?= htmlspecialchars($h['nivel']) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Cambiar contraseña -->
            <div class="portal-card p-3 p-md-4">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <h2 class="portal-card-title mb-0">Cambiar contraseña</h2>
                </div>

                <form method="post" action="/estudiante/perfil/password" novalidate>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="password_actual">Contraseña actual</label>
                            <input type="password" class="form-control<?= $err('password_actual') ?>"
                                   id="password_actual" name="password_actual" autocomplete="current-password" required>
                            <?php if (!empty($errores['password_actual'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errores['password_actual']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="password_nueva">Nueva contraseña</label>
                            <input type="password" class="form-control<?= $err('password_nueva') ?>"
                                   id="password_nueva" name="password_nueva" minlength="8" autocomplete="new-password" required>
                            <?php if (!empty($errores['password_nueva'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errores['password_nueva']) ?></div>
                            <?php else: ?>
                                <div class="form-text" style="color: var(--portal-muted);">Mínimo 8 caracteres.</div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="password_confirmar">Repite la nueva contraseña</label>
                            <input type="password" class="form-control<?= $err('password_confirmar') ?>"
                                   id="password_confirmar" name="password_confirmar" autocomplete="new-password" required>
                            <?php if (!empty($errores['password_confirmar'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errores['password_confirmar']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end mt-3">
                        <button type="submit" class="btn btn-accent fw-semibold px-4">
                            <i class="bi bi-arrow-repeat me-1"></i>Actualizar contraseña
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal reutilizable -->
<div class="modal fade" id="modalProximamente" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content portal-modal">
            <div class="modal-body text-center p-4">
                <span class="portal-modal-icon"><i class="bi bi-stars"></i></span>
                <h2 class="portal-card-title h5 mb-1" data-modal-titulo-texto>Próximamente</h2>
                <p class="small mb-4" style="color: var(--portal-muted);" data-modal-mensaje-texto>Esta función estará disponible muy pronto.</p>
                <button type="button" class="btn btn-accent fw-semibold px-4" data-bs-dismiss="modal">Entendido</button>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('modalProximamente');
    if (!modal) return;
    modal.addEventListener('show.bs.modal', function (event) {
        var boton = event.relatedTarget;
        if (!boton) return;
        var titulo = boton.getAttribute('data-modal-titulo');
        var mensaje = boton.getAttribute('data-modal-mensaje');
        var elTitulo = modal.querySelector('[data-modal-titulo-texto]');
        var elMensaje = modal.querySelector('[data-modal-mensaje-texto]');
        if (titulo) elTitulo.textContent = titulo;
        if (mensaje) elMensaje.textContent = mensaje;
    });
});
</script>
