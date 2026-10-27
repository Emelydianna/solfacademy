<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($tituloPagina ?? 'Portal del Instructor — SolfAcademy') ?></title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Estilos del portal (design system dark) -->
    <link href="/css/portal.css" rel="stylesheet">
</head>
<body class="portal-body">

    <div class="d-flex flex-lg-row align-items-stretch">

        <!-- Sidebar -->
        <aside class="portal-sidebar offcanvas-lg offcanvas-start flex-shrink-0" tabindex="-1" id="portalSidebar">
            <div class="offcanvas-header d-lg-none">
                <span class="portal-brand-name">SOLFACADEMY</span>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#portalSidebar" aria-label="Cerrar menú"></button>
            </div>

            <a class="portal-brand" href="/instructor/dashboard">
                <span class="portal-brand-icon"><i class="bi bi-music-note-beamed"></i></span>
                <span>
                    <span class="portal-brand-name d-block">SOLFACADEMY</span>
                    <span class="portal-brand-sub">Academia de Música</span>
                </span>
            </a>

            <!-- Perfil -->
            <?php
            // La foto la pasa el controlador cuando la conoce (p. ej. el
            // perfil); si no, se muestran las iniciales. Antes aquí había
            // dos consultas PDO incrustadas que se ejecutaban en cada render.
            $usuarioSesion = $usuarioSesion ?? ($usuario ?? []);
            $fotoPerfil = $fotoPerfil ?? ($perfil['foto'] ?? null);
            ?>
            <div class="portal-profile">
                <?php if (!empty($fotoPerfil)): ?>
                    <div class="portal-avatar" style="padding:0;overflow:hidden;">
                        <img src="<?= htmlspecialchars((string) $fotoPerfil) ?>" alt="Foto" style="width:100%;height:100%;object-fit:cover;">
                    </div>
                <?php else: ?>
                    <div class="portal-avatar">
                        <?= htmlspecialchars(mb_strtoupper(mb_substr(trim((string) ($usuarioSesion['nombre'] ?? 'I')), 0, 1))) ?>
                    </div>
                <?php endif; ?>
                <div class="portal-profile-name"><?= htmlspecialchars($usuarioSesion['nombre'] ?? 'Instructor') ?></div>
                <span class="portal-profile-badge" style="background:#ffc107;color:#000;">INSTRUCTOR</span>
            </div>

            <!-- Menú -->
            <nav class="portal-menu d-flex flex-column">
                <?php
                $menuPortal = [
                    'dashboard'      => ['/instructor/dashboard', 'bi-grid-1x2', 'Dashboard'],
                    'perfil'         => ['/instructor/perfil', 'bi-person', 'Mi Perfil'],
                    'grupos'         => ['/instructor/grupos', 'bi-people', 'Mis Grupos'],
                    'asistencias'    => ['/instructor/asistencias', 'bi-check2-square', 'Asistencias'],
                    'calificaciones' => ['/instructor/calificaciones', 'bi-clipboard-data', 'Calificaciones'],
                    'reportes'       => ['/instructor/reportes', 'bi-file-earmark-bar-graph', 'Reportes'],
                ];
                $paginaActiva = $paginaInstructor ?? ($paginaPortal ?? '');
                ?>
                <?php foreach ($menuPortal as $clave => [$url, $icono, $texto]): ?>
                    <a href="<?= $url ?>" class="portal-menu-link <?= $paginaActiva === $clave ? 'active' : '' ?>">
                        <i class="bi <?= $icono ?>"></i><?= $texto ?>
                    </a>
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
                    <a href="/instructor/perfil" class="portal-bell" aria-label="Mi Perfil">
                        <i class="bi bi-bell"></i>
                        <span class="portal-bell-dot"></span>
                    </a>
                    <span class="d-none d-md-inline small"><?= htmlspecialchars($usuarioSesion['nombre'] ?? '') ?></span>
                    <?php if (!empty($fotoPerfil)): ?>
                        <span class="portal-topbar-avatar d-none d-md-flex" style="padding:0;overflow:hidden;">
                            <img src="<?= htmlspecialchars((string) $fotoPerfil) ?>" alt="Foto" style="width:100%;height:100%;object-fit:cover;">
                        </span>
                    <?php else: ?>
                        <span class="portal-topbar-avatar d-none d-md-flex">
                            <?= htmlspecialchars(mb_strtoupper(mb_substr(trim((string) ($usuarioSesion['nombre'] ?? 'I')), 0, 1))) ?>
                        </span>
                    <?php endif; ?>
                    <span class="portal-profile-badge" style="background:#ffc107;color:#000;">INSTRUCTOR</span>
                </div>
            </header>

            <!-- Contenido -->
            <main class="portal-content">
                <?= $content ?? '' ?>
            </main>

            <!-- Footer -->
            <footer class="portal-footer">
                <span>© 2026 SolfAcademy Academia de Música. Portal del Instructor.</span>
                <div class="d-flex gap-3">
                    <a href="#">Soporte Académico</a>
                    <a href="#">Reglamento Interno</a>
                </div>
            </footer>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</body>
</html>
