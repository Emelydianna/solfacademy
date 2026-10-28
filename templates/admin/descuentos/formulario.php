<?php
use App\Application\Descuentos\DescuentosService;
use App\Application\Security\Csrf;

$e = static fn ($v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$id = (int) ($descuento['id_descuento'] ?? 0);

/** Valor efectivo: lo que se envió > lo guardado > el por defecto. */
$valor = static function (string $campo, string $default = '') use ($valores, $descuento): string {
    if (is_array($valores) && array_key_exists($campo, $valores)) {
        return (string) $valores[$campo];
    }
    if ($descuento !== null && array_key_exists($campo, $descuento) && $descuento[$campo] !== null) {
        return (string) $descuento[$campo];
    }
    return $default;
};

$esEdicion = $id > 0;
$tipo = $valor('tipo', $descuento !== null && $descuento['porcentaje'] !== null ? 'porcentaje' : 'monto');
$sinLimite = is_array($valores)
    ? (($valores['sin_limite'] ?? '0') === '1')
    : (int) ($descuento['usos_disponibles'] ?? 1) === DescuentosService::USOS_SIN_LIMITE;
$activo = is_array($valores)
    ? (($valores['activo'] ?? '0') === '1')
    : ($descuento === null || (int) $descuento['activo'] === 1);
$fechaInicio = $valor('fecha_inicio', date('Y-m-d'));
$fechaFin = $valor('fecha_fin', date('Y-m-d', strtotime('+30 days')));
$curso = (int) $valor('id_curso', '0');
$errorDe = static fn (string $campo): string => isset($errores[$campo])
    ? '<div class="text-danger small mt-1">' . $e($errores[$campo]) . '</div>'
    : '';
?>
<div class="admin-academica">
    <div class="d-flex flex-wrap justify-content-between gap-3 mb-4">
        <div>
            <h1 class="portal-title mb-1"><?= $esEdicion ? 'Editar código' : 'Nuevo código de descuento' ?></h1>
            <p class="portal-subtitle mb-0">
                <?= $esEdicion ? 'Los cambios afectan a las matrículas futuras; las ya registradas conservan su precio.' : 'El estudiante lo introduce al solicitar la matrícula y el precio se congela con el descuento.' ?>
            </p>
        </div>
        <a href="/admin/descuentos" class="btn admin-btn-outline align-self-start">Volver</a>
    </div>

    <?php if (!empty($errores['general'])): ?>
        <div role="alert" class="portal-alert portal-alert--peligro p-3 mb-4"><?= $e($errores['general']) ?></div>
    <?php endif; ?>

    <section class="portal-card p-4">
        <form method="post" action="<?= $esEdicion ? '/admin/descuentos/' . $id : '/admin/descuentos' ?>">
            <?= Csrf::campo() ?>

            <div class="row g-3 mb-3">
                <div class="col-12 col-lg-6">
                    <label for="codigo" class="form-label">Código *</label>
                    <input id="codigo" type="text" name="codigo" maxlength="30" required
                           class="form-control <?= isset($errores['codigo']) ? 'is-invalid' : '' ?>"
                           value="<?= $e($valor('codigo', $sugerido ?? '')) ?>"
                           placeholder="Ej.: <?= $e($sugerido ?: 'PROMO-AB12CD') ?>">
                    <div class="form-text">Letras, números o guion (3–30 caracteres). Se usa en mayúsculas.</div>
                    <?= $errorDe('codigo') ?>
                </div>
                <div class="col-12 col-lg-6">
                    <label for="descripcion" class="form-label">Descripción</label>
                    <input id="descripcion" type="text" name="descripcion" maxlength="200"
                           class="form-control <?= isset($errores['descripcion']) ? 'is-invalid' : '' ?>"
                           value="<?= $e($valor('descripcion')) ?>" placeholder="Ej.: $30 de descuento en cualquier curso">
                    <?= $errorDe('descripcion') ?>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-12 col-lg-6">
                    <label class="form-label">Tipo de descuento *</label>
                    <div class="d-flex gap-4">
                        <div class="form-check">
                            <input id="tipo-porcentaje" type="radio" name="tipo" value="porcentaje" class="form-check-input"
                                <?= $tipo === 'porcentaje' ? 'checked' : '' ?>>
                            <label for="tipo-porcentaje" class="form-check-label">Porcentaje</label>
                        </div>
                        <div class="form-check">
                            <input id="tipo-monto" type="radio" name="tipo" value="monto" class="form-check-input"
                                <?= $tipo !== 'porcentaje' ? 'checked' : '' ?>>
                            <label for="tipo-monto" class="form-check-label">Monto fijo</label>
                        </div>
                    </div>
                    <?= $errorDe('tipo') ?>
                </div>
                <div class="col-6 col-lg-3">
                    <label for="porcentaje" class="form-label">% de descuento</label>
                    <input id="porcentaje" type="number" name="porcentaje" step="0.01" min="0.01" max="100"
                           class="form-control <?= isset($errores['porcentaje']) ? 'is-invalid' : '' ?>"
                           value="<?= $e($valor('porcentaje')) ?>" placeholder="Ej.: 15">
                    <?= $errorDe('porcentaje') ?>
                </div>
                <div class="col-6 col-lg-3">
                    <label for="monto_fijo" class="form-label">Monto fijo (USD)</label>
                    <input id="monto_fijo" type="number" name="monto_fijo" step="0.01" min="0.01"
                           class="form-control <?= isset($errores['monto_fijo']) ? 'is-invalid' : '' ?>"
                           value="<?= $e($valor('monto_fijo')) ?>" placeholder="Ej.: 30">
                    <?= $errorDe('monto_fijo') ?>
                </div>
            </div>
            <p class="small admin-muted mt-n2 mb-3">Completa solo el campo del tipo elegido: nunca se aplican los dos.</p>

            <div class="row g-3 mb-3">
                <div class="col-6 col-lg-3">
                    <label for="fecha_inicio" class="form-label">Vigente desde *</label>
                    <input id="fecha_inicio" type="date" name="fecha_inicio" required
                           class="form-control <?= isset($errores['fecha_inicio']) ? 'is-invalid' : '' ?>"
                           value="<?= $e($fechaInicio) ?>">
                    <?= $errorDe('fecha_inicio') ?>
                </div>
                <div class="col-6 col-lg-3">
                    <label for="fecha_fin" class="form-label">Vigente hasta *</label>
                    <input id="fecha_fin" type="date" name="fecha_fin" required
                           class="form-control <?= isset($errores['fecha_fin']) ? 'is-invalid' : '' ?>"
                           value="<?= $e($fechaFin) ?>">
                    <?= $errorDe('fecha_fin') ?>
                </div>
                <div class="col-12 col-lg-6">
                    <label for="id_curso" class="form-label">Alcance</label>
                    <select id="id_curso" name="id_curso" class="form-select <?= isset($errores['id_curso']) ? 'is-invalid' : '' ?>">
                        <option value="0" <?= $curso === 0 ? 'selected' : '' ?>>Todos los cursos</option>
                        <?php foreach ($cursos as $c): ?>
                            <option value="<?= (int) $c['id_curso'] ?>" <?= $curso === (int) $c['id_curso'] ? 'selected' : '' ?>>
                                <?= $e($c['nombre']) ?><?= (int) $c['activo'] === 1 ? '' : ' (inactivo)' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?= $errorDe('id_curso') ?>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-12 col-lg-6">
                    <label class="form-label">Usos disponibles</label>
                    <div class="form-check mb-2">
                        <input id="sin_limite" type="checkbox" name="sin_limite" value="1" class="form-check-input"
                            data-usos-limite <?= $sinLimite ? 'checked' : '' ?>>
                        <label for="sin_limite" class="form-check-label">Sin límite de usos</label>
                    </div>
                    <input id="usos_disponibles" type="number" name="usos_disponibles" min="1" step="1"
                           class="form-control <?= isset($errores['usos_disponibles']) ? 'is-invalid' : '' ?>"
                           value="<?= $e($sinLimite ? '' : $valor('usos_disponibles', '1')) ?>"
                           placeholder="Ej.: 50" <?= $sinLimite ? 'disabled' : '' ?>>
                    <?= $errorDe('usos_disponibles') ?>
                </div>
                <div class="col-12 col-lg-6 d-flex align-items-end">
                    <div class="form-check">
                        <input id="activo" type="checkbox" name="activo" value="1" class="form-check-input" <?= $activo ? 'checked' : '' ?>>
                        <label for="activo" class="form-check-label">Código activo (puede usarse al matricular)</label>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button class="btn admin-btn-primary"><?= $esEdicion ? 'GUARDAR CAMBIOS' : 'CREAR CÓDIGO' ?></button>
                <a href="/admin/descuentos" class="btn admin-btn-outline">Cancelar</a>
            </div>
        </form>
    </section>
</div>
<script>
    // "Sin límite" oculta los usos; el campo deshabilitado no viaja, así que
    // el servicio interpreta sin_limite=1 y usa -1 sin depender de este valor.
    (function () {
        const casilla = document.querySelector('[data-usos-limite]');
        const usos = document.getElementById('usos_disponibles');
        if (!casilla || !usos) return;
        const alternar = function () {
            usos.disabled = casilla.checked;
            if (casilla.checked) usos.value = '';
        };
        casilla.addEventListener('change', alternar);
        alternar();
    })();
</script>
