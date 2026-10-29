<!-- ============================== -->
<!-- Iniciar sesión                -->
<!-- ============================== -->
<section class="auth-section">
    <div class="container">
        <div class="auth-card">
            <div class="text-center mb-4">
                <?php $claseIcono = 'brand-icon auth-brand mx-auto mb-3'; require __DIR__ . '/../../partials/marca.php'; ?>
                <h1 class="auth-title">Iniciar Sesión</h1>
                <p class="auth-subtitle">Bienvenido de nuevo a <?= htmlspecialchars((string) ($config['nombre'] ?? 'SolfAcademy')) ?></p>
            </div>

            <!-- Autenticación real contra la tabla usuario (fase 2) -->
            <form method="post" action="/login">
                <?= \App\Application\Security\Csrf::campo() ?>
                <?php if (!empty($next)): ?><input type="hidden" name="next" value="<?= htmlspecialchars($next) ?>"><?php endif; ?>
                <?php if (!empty($errorLogin)): ?>
                    <div class="alert alert-danger py-2 small mb-3" role="alert">
                        <i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($errorLogin) ?>
                    </div>
                <?php endif; ?>

                <div class="mb-3">
                    <label for="email" class="form-label">Correo electrónico</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" class="form-control" id="email" name="email"
                               placeholder="tu@correo.com" value="<?= htmlspecialchars($emailAnterior ?? '') ?>" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Contraseña</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password"
                               placeholder="••••••••" required>
                        <button class="input-group-text auth-toggle" type="button" id="togglePassword"
                                aria-label="Mostrar u ocultar contraseña">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="recordarme" name="recordarme">
                        <label class="form-check-label small" for="recordarme">Recordarme</label>
                    </div>
                    <!-- TODO: recuperación de contraseña (fase 2) -->
                    <a href="#" class="small auth-forgot">¿Olvidaste tu contraseña?</a>
                </div>

                <button type="submit" class="btn btn-accent w-100 fw-semibold">INICIAR SESIÓN</button>
            </form>

            <p class="auth-links mt-4 mb-0">
                ¿No tienes cuenta? <a href="/registro">Regístrate</a>
            </p>
        </div>
    </div>
</section>

<script>
    // Mostrar u ocultar la contraseña
    (function () {
        const boton = document.getElementById('togglePassword');
        const campo = document.getElementById('password');
        if (boton && campo) {
            boton.addEventListener('click', function () {
                const oculta = campo.type === 'password';
                campo.type = oculta ? 'text' : 'password';
                this.querySelector('i').className = oculta ? 'bi bi-eye-slash' : 'bi bi-eye';
            });
        }
    })();
</script>
