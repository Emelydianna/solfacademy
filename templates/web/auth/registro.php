<!-- ============================== -->
<!-- Crear cuenta                  -->
<!-- ============================== -->
<section class="auth-section">
    <div class="container">
        <div class="auth-card">
            <div class="text-center mb-4">
                <?php $modo = 'auth'; require __DIR__ . '/../../partials/marca.php'; ?>
                <h1 class="auth-title">Crear Cuenta</h1>
                <p class="auth-subtitle">Únete a la comunidad de <?= htmlspecialchars((string) ($config['nombre'] ?? 'SolfAcademy')) ?></p>
            </div>

            <!-- Registro real: crea ESTUDIANTE + USUARIO y abre sesión (fase 2) -->
            <form method="post" action="/registro">
                <?= \App\Application\Security\Csrf::campo() ?>
                <?php if (!empty($errorRegistro)): ?>
                    <div class="alert alert-danger py-2 small mb-3" role="alert">
                        <i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($errorRegistro) ?>
                    </div>
                <?php endif; ?>

                <div class="row g-3 mb-3">
                    <div class="col-sm-6">
                        <label for="nombre" class="form-label">Nombre</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" class="form-control" id="nombre" name="nombre"
                                   placeholder="Tu nombre" value="<?= htmlspecialchars($datosAnteriores['nombre'] ?? '') ?>" required>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <label for="apellido" class="form-label">Apellido</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" class="form-control" id="apellido" name="apellido"
                                   placeholder="Tu apellido" value="<?= htmlspecialchars($datosAnteriores['apellido'] ?? '') ?>" required>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Correo electrónico</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" class="form-control" id="email" name="email"
                               placeholder="tu@correo.com" value="<?= htmlspecialchars($datosAnteriores['email'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Contraseña</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password"
                               placeholder="Mínimo 8 caracteres" minlength="8" required>
                        <button class="input-group-text auth-toggle" type="button" data-password-target="password"
                                aria-label="Mostrar u ocultar contraseña">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="password2" class="form-label">Confirmar contraseña</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" id="password2" name="password2"
                               placeholder="Repite tu contraseña" minlength="8" required>
                        <button class="input-group-text auth-toggle" type="button" data-password-target="password2"
                                aria-label="Mostrar u ocultar contraseña">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" id="terminos" name="terminos" required>
                    <label class="form-check-label small" for="terminos">
                        Acepto los <a href="#" class="auth-forgot">Términos y Condiciones</a>
                    </label>
                </div>

                <button type="submit" class="btn btn-accent w-100 fw-semibold">CREAR CUENTA</button>
            </form>

            <p class="auth-links mt-4 mb-0">
                ¿Ya tienes cuenta? <a href="/login">Inicia sesión</a>
            </p>
        </div>
    </div>
</section>

<script>
    // Mostrar u ocultar las contraseñas
    document.querySelectorAll('[data-password-target]').forEach(function (boton) {
        boton.addEventListener('click', function () {
            const campo = document.getElementById(this.dataset.passwordTarget);
            if (!campo) {
                return;
            }
            const oculta = campo.type === 'password';
            campo.type = oculta ? 'text' : 'password';
            this.querySelector('i').className = oculta ? 'bi bi-eye-slash' : 'bi bi-eye';
        });
    });
</script>
