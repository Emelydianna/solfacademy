<?php
// Parcial del formulario lateral: todas las modificaciones llevan CSRF.
$valor = static fn (string $campo, string $defecto = '') => $valores[$campo] ?? $defecto;
$invalido = static fn (string $campo): string => isset($errores[$campo]) ? ' is-invalid' : '';
$atributosError = static fn (string $campo): string => isset($errores[$campo]) ? ' aria-invalid="true" aria-describedby="error-' . $campo . '"' : '';
$error = static function (string $campo) use ($errores, $e): void {
    if (isset($errores[$campo])) {
        echo '<div class="invalid-feedback d-block" id="error-' . $campo . '">' . $e($errores[$campo]) . '</div>';
    }
};
$categoriaSeleccionada = $esInstrumento ? (string) $valor('id_categoria') : '';
if ($esCurso) {
    foreach ($opciones['instrumentos'] as $instrumento) {
        if ((string) $instrumento['id_instrumento'] === (string) $valor('id_instrumento')) {
            $categoriaSeleccionada = (string) $instrumento['id_categoria'];
        }
    }
}
?>
<section id="formulario-academico" class="portal-card p-3 p-md-4 admin-editor" aria-labelledby="titulo-formulario">
    <span class="admin-eyebrow"><?= $idEdicion ? 'EDITAR REGISTRO #' . (int) $idEdicion : 'NUEVO REGISTRO' ?></span>
    <h2 id="titulo-formulario" class="portal-card-title mt-2 mb-4"><?= $idEdicion ? 'Editar ' . $e($singular) : ($esCurso ? 'Nuevo programa' : 'Nueva ' . ($esInstrumento ? 'ficha de instrumento' : 'categoría')) ?></h2>

    <?php if ($errores): ?>
        <div class="portal-alert portal-alert--peligro mb-3" role="alert">
            <?= $e($errores['general'] ?? 'No se pudo guardar. Revisa los campos señalados.') ?>
        </div>
    <?php endif; ?>
    <?php require __DIR__ . '/../partials/errores-form.php'; ?>
    <form method="post" action="<?= $e($base . ($idEdicion ? '/' . $idEdicion : '')) ?>" id="form-academico">
        <?= \App\Application\Security\Csrf::campo() ?>
        <div class="mb-3">
            <label for="nombre" class="form-label">Nombre <?= $esCurso ? 'del curso' : ($esInstrumento ? 'del instrumento' : 'de la categoría') ?> <span aria-hidden="true">*</span></label>
            <input id="nombre" name="nombre" class="form-control<?= $invalido('nombre') ?>" maxlength="<?= $esCurso ? 150 : 100 ?>" required value="<?= $e($valor('nombre')) ?>"<?= $atributosError('nombre') ?>>
            <?php $error('nombre'); ?>
        </div>
        <div class="mb-3">
            <label for="descripcion" class="form-label">Descripción</label>
            <textarea id="descripcion" name="descripcion" rows="3" maxlength="<?= $seccion === 'categorias' ? 255 : 16000 ?>" class="form-control<?= $invalido('descripcion') ?>"<?= $atributosError('descripcion') ?>><?= $e($valor('descripcion')) ?></textarea>
            <?php $error('descripcion'); ?>
        </div>
        <?php if ($esCurso || $esInstrumento): ?>
            <div class="mb-3">
                <label for="id_categoria" class="form-label">Categoría<?= $esInstrumento ? ' *' : ' (filtrar instrumentos)' ?></label>
                <select id="id_categoria" <?= $esInstrumento ? 'name="id_categoria" required' : 'data-filtro-categoria' ?> class="form-select<?= $invalido('id_categoria') ?>"<?= $atributosError('id_categoria') ?>>
                    <option value=""><?= $esCurso ? 'Todas las categorías' : 'Selecciona una categoría' ?></option>
                    <?php foreach ($opciones['categorias'] as $categoria): ?>
                        <option value="<?= (int) $categoria['id_categoria'] ?>" <?= (string) $categoria['id_categoria'] === $categoriaSeleccionada ? 'selected' : '' ?>><?= $e($categoria['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php $error('id_categoria'); ?>
                <?php if (!$opciones['categorias']): ?><p class="small admin-muted mt-2">Primero <a href="/admin/categorias">crea una categoría</a>.</p><?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if ($esCurso): ?>
            <div class="mb-3">
                <label for="id_instrumento" class="form-label">Instrumento *</label>
                <select id="id_instrumento" name="id_instrumento" required class="form-select<?= $invalido('id_instrumento') ?>"<?= $atributosError('id_instrumento') ?>>
                    <option value="">Selecciona un instrumento</option>
                    <?php foreach ($opciones['instrumentos'] as $instrumento): ?>
                        <option value="<?= (int) $instrumento['id_instrumento'] ?>" data-categoria="<?= (int) $instrumento['id_categoria'] ?>" <?= (string) $instrumento['id_instrumento'] === (string) $valor('id_instrumento') ? 'selected' : '' ?>><?= $e($instrumento['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php $error('id_instrumento'); ?>
                <?php if (!$opciones['instrumentos']): ?><p class="small admin-muted mt-2">Primero <a href="/admin/instrumentos">crea un instrumento</a>.</p><?php endif; ?>
            </div>
            <div class="mb-3">
                <label for="nivel" class="form-label">Nivel *</label>
                <select id="nivel" name="nivel" required class="form-select<?= $invalido('nivel') ?>"<?= $atributosError('nivel') ?>>
                    <option value="">Selecciona un nivel</option>
                    <?php foreach ($opciones['niveles'] as $nivel): ?>
                        <option value="<?= $e($nivel) ?>" <?= $valor('nivel') === $nivel ? 'selected' : '' ?>><?= $e($nivel) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php $error('nivel'); ?>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-sm-6">
                    <label for="duracion_semanas" class="form-label">Duración (semanas)</label>
                    <input id="duracion_semanas" type="number" name="duracion_semanas" min="1" max="9999" step="1" class="form-control<?= $invalido('duracion_semanas') ?>" value="<?= $e($valor('duracion_semanas')) ?>"<?= $atributosError('duracion_semanas') ?>>
                    <?php $error('duracion_semanas'); ?>
                </div>
                <div class="col-sm-6">
                    <label for="max_grupos" class="form-label">Máximo de grupos *</label>
                    <input id="max_grupos" type="number" name="max_grupos" min="1" max="9999" step="1" required class="form-control<?= $invalido('max_grupos') ?>" value="<?= $e($valor('max_grupos', '2')) ?>"<?= $atributosError('max_grupos') ?>>
                    <?php $error('max_grupos'); ?>
                </div>
            </div>
        <?php endif; ?>
        <?php if ($esCurso || $esInstrumento): $campoPrecio = $esCurso ? 'precio' : 'precio_alquiler'; ?>
            <div class="mb-3">
                <label for="<?= $campoPrecio ?>" class="form-label"><?= $esCurso ? 'Precio del curso' : 'Precio de alquiler' ?> (USD) *</label>
                <input id="<?= $campoPrecio ?>" name="<?= $campoPrecio ?>" type="number" min="0" max="999999.99" step="0.01" required class="form-control<?= $invalido($campoPrecio) ?>" value="<?= $e($valor($campoPrecio, $esInstrumento ? '0.00' : '')) ?>"<?= $atributosError($campoPrecio) ?>>
                <?php $error($campoPrecio); ?>
            </div>
            <fieldset class="mb-3">
                <legend class="form-label">Disponibilidad *</legend>
                <?php foreach (['1' => 'Disponible', '0' => 'No disponible'] as $v => $etiqueta): ?>
                    <div class="form-check form-check-inline">
                        <input id="disponible-<?= $v ?>" class="form-check-input" type="radio" name="disponible" value="<?= $v ?>" required <?= (string) $valor('disponible', '1') === (string) $v ? 'checked' : '' ?><?= $atributosError('disponible') ?>>
                        <label class="form-check-label" for="disponible-<?= $v ?>"><?= $etiqueta ?></label>
                    </div>
                <?php endforeach; ?>
                <?php $error('disponible'); ?>
            </fieldset>
        <?php endif; ?>
        <?php if ($esCurso): ?>
            <fieldset class="mb-3">
                <legend class="form-label">Estado del curso *</legend>
                <?php foreach (['1' => 'Activo', '0' => 'Inactivo'] as $v => $etiqueta): ?>
                    <div class="form-check form-check-inline">
                        <input id="activo-<?= $v ?>" class="form-check-input" type="radio" name="activo" value="<?= $v ?>" required <?= (string) $valor('activo', '1') === (string) $v ? 'checked' : '' ?><?= $atributosError('activo') ?>>
                        <label class="form-check-label" for="activo-<?= $v ?>"><?= $etiqueta ?></label>
                    </div>
                <?php endforeach; ?>
                <?php $error('activo'); ?>
            </fieldset>
            <p class="small admin-muted">El instructor se asigna por grupo. Cambiar el precio no modifica el importe de matrículas existentes. Solo los cursos activos y disponibles aparecen en el catálogo público.</p>
        <?php endif; ?>
        <div class="d-flex flex-wrap gap-2 mt-4 pt-3 border-top admin-border">
            <a href="<?= $e($url()) ?>" class="btn admin-btn-outline">CANCELAR</a>
            <button type="submit" class="btn admin-btn-primary flex-grow-1">GUARDAR <?= $e(mb_strtoupper($singular)) ?></button>
        </div>
    </form>
</section>
