<?php $config = $config ?? \App\Application\Config\ConfiguracionService::porDefecto(); ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(\App\Application\Config\ConfiguracionService::titulo($tituloPagina ?? '', $config)) ?></title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Estilos propios -->
    <link href="/css/style.css" rel="stylesheet">
    <!-- SweetAlert2 + helper de avisos y confirmaciones -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="/js/notificaciones.js"></script>
</head>
<body class="d-flex flex-column min-vh-100">

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark site-navbar sticky-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="/">
                <?php $modo = 'web'; require __DIR__ . '/../partials/marca.php'; ?>
                <span class="brand-text"><?= htmlspecialchars(mb_strtoupper((string) $config['nombre'])) ?></span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                    aria-controls="mainNav" aria-expanded="false" aria-label="Alternar navegación">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav mx-auto gap-lg-4">
                    <li class="nav-item">
                        <a class="nav-link <?= ($paginaActiva ?? '') === 'inicio' ? 'active' : '' ?>" href="/">Inicio</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($paginaActiva ?? '') === 'cursos' ? 'active' : '' ?>" href="/cursos">Cursos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($paginaActiva ?? '') === 'instructores' ? 'active' : '' ?>" href="/#instructores">Instructores</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/cursos">Precios</a>
                    </li>
                </ul>
                <?php if (!empty($usuarioSesion)): ?>
                    <div class="d-flex align-items-center gap-3">
                        <?php
                        // Cada rol tiene su portal; el enlace apunta al suyo
                        $portalRol = match (($usuarioSesion['rol'] ?? '')) {
                            'estudiante' => ['/estudiante/dashboard', 'Mi portal'],
                            'instructor' => ['/instructor/dashboard', 'Mi portal'],
                            'admin'      => ['/admin/dashboard', 'Panel admin'],
                            default      => null,
                        };
                        ?>
                        <?php if ($portalRol !== null): ?>
                            <a href="<?= $portalRol[0] ?>" class="nav-link text-white">
                                <i class="bi bi-box-arrow-in-right me-1"></i><?= $portalRol[1] ?>
                            </a>
                        <?php endif; ?>
                        <span class="d-flex align-items-center gap-2">
                            <i class="bi bi-person-circle fs-5"></i>
                            <span class="text-white small d-none d-lg-inline"><?= htmlspecialchars($usuarioSesion['nombre']) ?></span>
                            <span class="badge-rol"><?= ($usuarioSesion['rol'] ?? '') === 'estudiante' ? 'ALUMNO' : mb_strtoupper((string) ($usuarioSesion['rol'] ?? '')) ?></span>
                        </span>
                        <a href="/logout" class="nav-link text-white">Cerrar sesión</a>
                    </div>
                <?php else: ?>
                    <div class="d-flex align-items-center gap-3">
                        <a href="/login" class="nav-link text-white">Iniciar Sesión</a>
                        <a href="/registro" class="btn btn-accent btn-sm px-3 fw-semibold">REGISTRARSE</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <main class="flex-grow-1">
        <?= $content ?? '' ?>
    </main>

    <!-- Footer -->
    <footer class="site-footer pt-5">
        <div class="container">
            <div class="row g-4 pb-4">
                <div class="col-lg-4 col-md-6">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <?php $modo = 'web'; require __DIR__ . '/../partials/marca.php'; ?>
                        <span class="brand-text"><?= htmlspecialchars(mb_strtoupper((string) $config['nombre'])) ?></span>
                    </div>
                    <p class="footer-text mb-3">Transformamos tu pasión por la música en habilidades reales, con instructores expertos y acompañamiento internacional.</p>
                    <?php
                    $redesVisibles = array_filter([
                        'facebook'  => $config['facebook'] ?? null,
                        'instagram' => $config['instagram'] ?? null,
                        'youtube'   => $config['youtube'] ?? null,
                    ], static fn ($url): bool => is_string($url) && $url !== '');
                    $iconos = ['facebook' => 'bi-facebook', 'instagram' => 'bi-instagram', 'youtube' => 'bi-youtube'];
                    ?>
                    <?php if ($redesVisibles): ?>
                        <div class="d-flex gap-2">
                            <?php foreach ($redesVisibles as $red => $url): ?>
                                <a href="<?= htmlspecialchars($url) ?>" target="_blank" rel="noopener"
                                   class="social-icon" aria-label="<?= htmlspecialchars(ucfirst($red)) ?>">
                                    <i class="bi <?= $iconos[$red] ?>"></i>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-lg-2 col-md-6">
                    <h6 class="footer-heading">Explora</h6>
                    <ul class="list-unstyled footer-links">
                        <li><a href="/#instructores">Instructores</a></li>
                        <li><a href="/cursos">Cursos</a></li>
                        <li><a href="/cursos">Precios</a></li>
                    </ul>
                </div>
                <?php
                $contacto = array_filter([
                    'direccion' => ['bi-geo-alt', $config['direccion'] ?? null],
                    'telefono'  => ['bi-telephone', $config['telefono'] ?? null],
                    'correo'    => ['bi-envelope', $config['email_contacto'] ?? null],
                ], static fn ($dato): bool => is_string($dato[1]) && $dato[1] !== '');
                ?>
                <?php if ($contacto): ?>
                    <div class="col-lg-3 col-md-6">
                        <h6 class="footer-heading">Contacto</h6>
                        <ul class="list-unstyled footer-text">
                            <?php foreach ($contacto as $dato): ?>
                                <li class="mb-2"><i class="bi <?= $dato[0] ?> me-2"></i><?= htmlspecialchars($dato[1]) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <?php if (!empty($config['ciudad'])): ?>
                    <div class="col-lg-3 col-md-6">
                        <h6 class="footer-heading">Nuestra Sede</h6>
                        <div class="map-placeholder">
                            <i class="bi bi-geo-alt-fill"></i>
                            <span><?= htmlspecialchars((string) $config['ciudad']) ?></span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="footer-bottom py-3 d-flex flex-column flex-md-row justify-content-between gap-2">
                <span><?= htmlspecialchars(
                    !empty($config['pie_pagina'])
                        ? (string) $config['pie_pagina']
                        : '© ' . date('Y') . ' ' . $config['nombre'] . ' · Todos los derechos reservados.'
                ) ?></span>
                <div class="d-flex gap-3">
                    <a href="#">Términos y Condiciones</a>
                    <a href="#">Política de Privacidad</a>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
