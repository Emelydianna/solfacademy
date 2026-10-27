<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($tituloPagina ?? 'Panel de Administración — SolfAcademy') ?></title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Estilos del portal (design system dark) -->
    <link href="/css/portal.css" rel="stylesheet">
    <link href="/css/admin-academica.css" rel="stylesheet">
    <!-- Chart.js: en el <head> para que los scripts en línea del contenido lo encuentren -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="portal-body">

    <?php
    // El rol admin no tiene nombre propio en la BD: si la sesión quedó con
    // el nombre vacío, se muestra "Administrador" en vez de un hueco.
    $usuarioSesion = $usuarioSesion ?? ($usuario ?? []);
    $nombreAdmin = trim((string) ($usuarioSesion['nombre'] ?? ''));
    if ($nombreAdmin === '') {
        $nombreAdmin = 'Administrador';
    }
    $inicialAdmin = mb_strtoupper(mb_substr($nombreAdmin, 0, 1));
    ?>

    <div class="d-flex flex-lg-row align-items-stretch">

        <!-- Sidebar -->
        <aside class="portal-sidebar offcanvas-lg offcanvas-start flex-shrink-0" tabindex="-1" id="portalSidebar">
            <div class="offcanvas-header d-lg-none">
                <span class="portal-brand-name">SOLFACADEMY</span>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#portalSidebar" aria-label="Cerrar menú"></button>
            </div>

            <a class="portal-brand" href="/admin/dashboard">
                <span class="portal-brand-icon"><i class="bi bi-music-note-beamed"></i></span>
                <span>
                    <span class="portal-brand-name d-block">SOLFACADEMY</span>
                    <span class="portal-brand-sub">Academia de Música</span>
                </span>
            </a>

            <!-- Perfil -->
            <div class="portal-profile">
                <div class="portal-avatar">
                    <?= htmlspecialchars($inicialAdmin) ?>
                </div>
                <div class="portal-profile-name"><?= htmlspecialchars($nombreAdmin) ?></div>
                <span class="portal-profile-badge" style="background:#ffc107;color:#000;">ADMINISTRADOR</span>
            </div>

            <!-- Menú -->
            <nav class="portal-menu d-flex flex-column">
                <?php
                // Se habilitan únicamente los módulos implementados.
                $menuPortal = [
                    ['dashboard',    '/admin/dashboard',      'bi-grid-1x2',           'Dashboard',       true],
                    ['cursos',       '/admin/cursos',         'bi-collection-play',    'Cursos',          true],
                    ['estudiantes',  '/admin/estudiantes',    'bi-people',             'Estudiantes',     true],
                    ['instructores', '/admin/instructores',   'bi-person-workspace',   'Instructores',    true],
                    ['categorias',   '/admin/categorias',     'bi-bookmark-star',      'Categorías',      true],
                    ['aulas',        '/admin/aulas',          'bi-door-open',          'Aulas',           true],
                    ['grupos',       '/admin/grupos',         'bi-diagram-3',          'Grupos',          true],
                    ['requisitos',   '#',                     'bi-clipboard-check',    'Requisitos',      false],
                    ['pagos',        '#',                     'bi-credit-card',        'Pagos',           false],
                    ['usuarios',     '#',                     'bi-person-badge',       'Usuarios',        false],
                    ['reportes',     '#',                     'bi-file-earmark-bar-graph', 'Reportes',    false],
                    ['config',       '#',                     'bi-sliders',            'Configuración',   false],
                ];
                $paginaActiva = $paginaAdmin ?? '';
                ?>
                <?php foreach ($menuPortal as [$clave, $url, $icono, $texto, $disponible]): ?>
                    <?php if ($disponible): ?>
                        <a href="<?= $url ?>" class="portal-menu-link <?= $paginaActiva === $clave ? 'active' : '' ?>">
                            <i class="bi <?= $icono ?>"></i><?= $texto ?>
                        </a>
                    <?php else: ?>
                        <a href="#" class="portal-menu-link" style="opacity:.5;" title="Sección próxima a habilitar">
                            <i class="bi <?= $icono ?>"></i><?= $texto ?>
                            <i class="bi bi-lock ms-auto" style="font-size:.8rem;"></i>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>

            <a href="/logout" class="portal-logout">
                <i class="bi bi-box-arrow-left"></i>Cerrar Sesión
            </a>
        </aside>

        <!-- Zona principal -->
        <div class="portal-main flex-grow-1 w-100">

            <!-- Topbar -->
            <header class="portal-topbar">
                <button class="btn btn-sm d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#portalSidebar" aria-controls="portalSidebar" style="background-color: var(--portal-surface); border: 1px solid var(--portal-border); color: var(--portal-muted);">
                    <i class="bi bi-list fs-4"></i>
                </button>
                <div class="ms-auto portal-topbar-user">
                    <span class="d-none d-md-inline small"><?= htmlspecialchars($nombreAdmin) ?></span>
                    <span class="portal-topbar-avatar d-none d-md-flex">
                        <?= htmlspecialchars($inicialAdmin) ?>
                    </span>
                    <span class="portal-profile-badge" style="background:#ffc107;color:#000;">ADMINISTRADOR</span>
                </div>
            </header>

            <!-- Contenido -->
            <main class="portal-content">
                <?= $content ?? '' ?>
            </main>

            <!-- Footer -->
            <footer class="portal-footer">
                <span>© 2026 SolfAcademy Academia de Música. Panel de Administración.</span>
                <div class="d-flex gap-3">
                    <a href="#">Soporte Técnico</a>
                    <a href="#">Reglamento Interno</a>
                </div>
            </footer>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
