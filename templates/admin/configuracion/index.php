<?php
/**
 * Configuración de la academia (/admin/configuracion).
 * $config: datos actuales (ViewDataMiddleware) con $config['logo'] = /marca/logo|null
 * $valores: valores rechazados en el último intento (errores de validación)
 * $errores: mensajes por campo · $flash: aviso · $hayLogo: hay archivo en disco
 */
use App\Application\Config\ConfiguracionService;
use App\Application\Security\Csrf;

$e = static fn ($v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
$config = $config ?? ConfiguracionService::porDefecto();
$errores = $errores ?? [];
$valores = $valores ?? null;

// En un envío con errores se muestran los valores rechazados, no los guardados.
$v = is_array($valores) && $valores ? array_merge($config, $valores) : $config;
$logo = $config['logo'] ?? null;

$errorDe = static fn (string $campo): string => isset($errores[$campo])
    ? '<div class="text-danger small mt-1">' . $e($errores[$campo]) . '</div>'
    : '';
$valor = static fn (string $campo): string => (string) ($v[$campo] ?? '');
$redes = ['facebook' => ['bi-facebook', 'Facebook'], 'instagram' => ['bi-instagram', 'Instagram'], 'youtube' => ['bi-youtube', 'YouTube']];
?>
<div class="admin-academica">
    <div class="d-flex flex-wrap justify-content-between gap-3 mb-4">
        <div>
            <h1 class="portal-title mb-1">Configuración de la academia</h1>
            <p class="portal-subtitle mb-0">Define cómo se presenta tu academia en los portales, la web pública y los PDF.</p>
        </div>
        <?php if (!empty($config['actualizado_en'])): ?>
            <span class="admin-fecha-actual"><i class="bi bi-clock-history me-1"></i>Última edición: <?= $e($config['actualizado_en']) ?></span>
        <?php endif; ?>
    </div>

    <?php require __DIR__ . '/../../partials/flash.php'; ?>
    <?php if (!empty($errores['general'])): ?>
        <div role="alert" class="portal-alert portal-alert--peligro p-3 mb-4"><?= $e($errores['general']) ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Formulario -->
        <div class="col-lg-8">
            <form method="post" action="/admin/configuracion">
                <?= Csrf::campo() ?>

                <section class="portal-card p-4 mb-4">
                    <h2 class="portal-card-title mb-3">Identidad</h2>
                    <div class="row g-3">
                        <div class="col-12 col-lg-6">
                            <label for="nombre" class="form-label">Nombre de la academia *</label>
                            <input id="nombre" type="text" name="nombre" minlength="3" maxlength="80" required
                                   class="form-control <?= isset($errores['nombre']) ? 'is-invalid' : '' ?>"
                                   value="<?= $e($valor('nombre')) ?>">
                            <div class="form-text">Aparece en los portales, la web, los títulos y los PDF.</div>
                            <?= $errorDe('nombre') ?>
                        </div>
                        <div class="col-12 col-lg-6">
                            <label for="lema" class="form-label">Lema o subtítulo *</label>
                            <input id="lema" type="text" name="lema" minlength="3" maxlength="120" required
                                   class="form-control <?= isset($errores['lema']) ? 'is-invalid' : '' ?>"
                                   value="<?= $e($valor('lema')) ?>" placeholder="Academia de Música">
                            <?= $errorDe('lema') ?>
                        </div>
                    </div>
                </section>

                <section class="portal-card p-4 mb-4">
                    <h2 class="portal-card-title mb-3">Contacto</h2>
                    <div class="row g-3">
                        <div class="col-12 col-lg-6">
                            <label for="email_contacto" class="form-label">Correo de contacto</label>
                            <input id="email_contacto" type="email" name="email_contacto" maxlength="150"
                                   class="form-control <?= isset($errores['email_contacto']) ? 'is-invalid' : '' ?>"
                                   value="<?= $e($valor('email_contacto')) ?>">
                            <?= $errorDe('email_contacto') ?>
                        </div>
                        <div class="col-12 col-lg-6">
                            <label for="telefono" class="form-label">Teléfono</label>
                            <input id="telefono" type="text" name="telefono" maxlength="30"
                                   class="form-control <?= isset($errores['telefono']) ? 'is-invalid' : '' ?>"
                                   value="<?= $e($valor('telefono')) ?>">
                            <?= $errorDe('telefono') ?>
                        </div>
                        <div class="col-12 col-lg-6">
                            <label for="direccion" class="form-label">Dirección</label>
                            <input id="direccion" type="text" name="direccion" maxlength="180"
                                   class="form-control <?= isset($errores['direccion']) ? 'is-invalid' : '' ?>"
                                   value="<?= $e($valor('direccion')) ?>">
                            <?= $errorDe('direccion') ?>
                        </div>
                        <div class="col-12 col-lg-6">
                            <label for="ciudad" class="form-label">Ciudad / sede</label>
                            <input id="ciudad" type="text" name="ciudad" maxlength="120"
                                   class="form-control <?= isset($errores['ciudad']) ? 'is-invalid' : '' ?>"
                                   value="<?= $e($valor('ciudad')) ?>">
                            <?= $errorDe('ciudad') ?>
                        </div>
                    </div>
                    <div class="form-text mt-2">Se muestran en el pie de la web pública. Lo que quedes en blanco no aparece.</div>
                </section>

                <section class="portal-card p-4 mb-4">
                    <h2 class="portal-card-title mb-3">Redes sociales</h2>
                    <div class="row g-3">
                        <?php foreach ($redes as $campo => [$icono, $etiqueta]): ?>
                            <div class="col-12 col-lg-4">
                                <label for="<?= $campo ?>" class="form-label"><i class="bi <?= $icono ?> me-1"></i><?= $etiqueta ?></label>
                                <input id="<?= $campo ?>" type="url" name="<?= $campo ?>" maxlength="150"
                                       class="form-control <?= isset($errores[$campo]) ? 'is-invalid' : '' ?>"
                                       value="<?= $e($valor($campo)) ?>" placeholder="https://…">
                                <?= $errorDe($campo) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="form-text mt-2">Vacío = el icono no se muestra en el pie.</div>
                </section>

                <section class="portal-card p-4 mb-4">
                    <h2 class="portal-card-title mb-3">Pie de página y documentos</h2>
                    <div class="mb-3">
                        <label for="pie_pagina" class="form-label">Texto del pie de la web</label>
                        <input id="pie_pagina" type="text" name="pie_pagina" maxlength="180"
                               class="form-control <?= isset($errores['pie_pagina']) ? 'is-invalid' : '' ?>"
                               value="<?= $e($valor('pie_pagina')) ?>">
                        <div class="form-text">Línea de copyright del pie de la web pública.</div>
                        <?= $errorDe('pie_pagina') ?>
                    </div>
                    <div class="mb-0">
                        <label for="nota_pdf" class="form-label">Nota al pie de los PDF</label>
                        <input id="nota_pdf" type="text" name="nota_pdf" maxlength="180"
                               class="form-control <?= isset($errores['nota_pdf']) ? 'is-invalid' : '' ?>"
                               value="<?= $e($valor('nota_pdf')) ?>" placeholder="Escuela de música desde 2010">
                        <div class="form-text">Aparece en el boletín de calificaciones, los comprobantes de pago y los reportes. Vacío = «{nombre} · {lema}».</div>
                        <?= $errorDe('nota_pdf') ?>
                    </div>
                </section>

                <div class="d-flex gap-2">
                    <button class="btn admin-btn-primary">GUARDAR CONFIGURACIÓN</button>
                    <a href="/admin/dashboard" class="btn admin-btn-outline">Cancelar</a>
                </div>
            </form>
        </div>

        <!-- Logo y previsualización -->
        <div class="col-lg-4">
            <section class="portal-card p-4 mb-4">
                <h2 class="portal-card-title mb-3">Logo</h2>

                <div class="d-flex align-items-center justify-content-center mb-3" style="min-height:104px;">
                    <?php $modo = 'preview'; require __DIR__ . '/../../partials/marca.php'; ?>
                </div>

                <form method="post" action="/admin/configuracion/logo" enctype="multipart/form-data" class="mb-3">
                    <?= Csrf::campo() ?>
                    <label for="logo" class="form-label">Cambiar logo</label>
                    <input id="logo" type="file" name="logo" class="form-control mb-2" accept="image/png,image/jpeg,image/webp">
                    <div class="form-text mb-2">PNG, JPG o WEBP · máximo 2MB. No se aceptan SVG. Se adapta solo: se reduce a 512px conservando sus proporciones y se muestra en un recuadro fijo, sin deformarse.</div>
                    <button class="btn admin-btn-primary w-100">SUBIR LOGO</button>
                </form>

                <?php if ($hayLogo): ?>
                    <form method="post" action="/admin/configuracion/logo/quitar" data-confirm="¿Quitar el logo? Se volverá a mostrar el icono musical.">
                        <?= Csrf::campo() ?>
                        <button class="btn admin-btn-outline admin-delete w-100">QUITAR LOGO</button>
                    </form>
                <?php endif; ?>
            </section>

            <section class="portal-card p-4">
                <h2 class="portal-card-title mb-3">Así se verá la marca</h2>
                <div class="p-3" style="background:var(--portal-bg);border:1px solid var(--portal-border);border-radius:.75rem;">
                    <div class="portal-brand<?= $logo ? ' portal-brand--logo' : '' ?>" style="padding:0;border:0;min-height:0;gap:.75rem;">
                        <?php $modo = 'portal'; require __DIR__ . '/../../partials/marca.php'; ?>
                        <span>
                            <span class="portal-brand-name d-block"><?= $e(mb_strtoupper($valor('nombre'))) ?></span>
                            <span class="portal-brand-sub"><?= $e($valor('lema')) ?></span>
                        </span>
                    </div>
                </div>
                <p class="small admin-muted mb-0 mt-3">
                    Con esto se ven el sidebar de los tres portales, la navbar de la web, el login y
                    la cabecera del boletín, los comprobantes y los reportes.
                </p>
            </section>
        </div>
    </div>

</div>
<script src="/js/admin-academica.js" defer></script>
