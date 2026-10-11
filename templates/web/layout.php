<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($tituloPagina ?? 'SolfAcademy') ?></title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Estilos propios -->
    <link href="/css/style.css" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100">

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark site-navbar sticky-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="/">
                <span class="brand-icon"><i class="bi bi-music-note-beamed"></i></span>
                <span class="brand-text">SOLFACADEMY</span>
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
                        <a class="nav-link" href="#">Precios</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">Contacto</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center gap-3">
                    <a href="/login" class="nav-link text-white">Iniciar Sesión</a>
                    <a href="/registro" class="btn btn-accent btn-sm px-3 fw-semibold">REGISTRARSE</a>
                </div>
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
                        <span class="brand-icon"><i class="bi bi-music-note-beamed"></i></span>
                        <span class="brand-text">SOLFACADEMY</span>
                    </div>
                    <p class="footer-text mb-3">Transformamos tu pasión por la música en habilidades reales, con instructores expertos y acompañamiento internacional.</p>
                    <div class="d-flex gap-2">
                        <a href="#" class="social-icon" aria-label="Facebook"><i class="bi bi-facebook"></i></a>
                        <a href="#" class="social-icon" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
                        <a href="#" class="social-icon" aria-label="YouTube"><i class="bi bi-youtube"></i></a>
                    </div>
                </div>
                <div class="col-lg-2 col-md-6">
                    <h6 class="footer-heading">Explora</h6>
                    <ul class="list-unstyled footer-links">
                        <li><a href="/#instructores">Instructores</a></li>
                        <li><a href="/cursos">Cursos</a></li>
                        <li><a href="#">Precios</a></li>
                        <li><a href="#">Blog</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-6">
                    <h6 class="footer-heading">Contacto</h6>
                    <ul class="list-unstyled footer-text">
                        <li class="mb-2"><i class="bi bi-geo-alt me-2"></i>Calle de la Música 123, Madrid</li>
                        <li class="mb-2"><i class="bi bi-telephone me-2"></i>+34 912 345 678</li>
                        <li class="mb-2"><i class="bi bi-envelope me-2"></i>info@solfacademy.com</li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-6">
                    <h6 class="footer-heading">Nuestra Sede</h6>
                    <div class="map-placeholder">
                        <i class="bi bi-geo-alt-fill"></i>
                        <span>Madrid, España</span>
                    </div>
                </div>
            </div>
            <div class="footer-bottom py-3 d-flex flex-column flex-md-row justify-content-between gap-2">
                <span>© 2026 SolfAcademy · Todos los derechos reservados.</span>
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
