<?php
$usuarioSesion = $usuarioSesion ?? ($usuario ?? []);
$nombre = explode(' ', trim((string) ($usuarioSesion['nombre'] ?? 'Administración')))[0];

// Módulos previstos para la fase de admin, según el esquema actual.
$modulos = [
    ['titulo' => 'Usuarios y roles',      'icono' => 'bi-people-fill',      'texto' => 'Alta, baja y permisos de estudiantes, instructores y administración.', 'estado' => 'Pendiente'],
    ['titulo' => 'Instructores',          'icono' => 'bi-person-workspace', 'texto' => 'Fichas, instrumentos que dominan y asignación a grupos.',               'estado' => 'Pendiente'],
    ['titulo' => 'Cursos y catálogo',     'icono' => 'bi-collection-play',  'texto' => 'Cursos, requisitos, precios y descuentos vigentes.',                   'estado' => 'Pendiente'],
    ['titulo' => 'Grupos y horarios',     'icono' => 'bi-calendar3',        'texto' => 'Creación de grupos, aulas, horarios y asignación de profesores.',      'estado' => 'Pendiente'],
    ['titulo' => 'Inscripciones',         'icono' => 'bi-journal-check',    'texto' => 'Matrículas, cupos y estados (pendiente, activa, cancelada).',          'estado' => 'Pendiente'],
    ['titulo' => 'Pagos y comprobantes',  'icono' => 'bi-credit-card',      'texto' => 'Cobros, saldos pendientes y comprobantes.',                           'estado' => 'Pendiente'],
    ['titulo' => 'Reservas',              'icono' => 'bi-door-open',        'texto' => 'Cabinas de práctica y clases extras a confirmar.',                    'estado' => 'Pendiente'],
    ['titulo' => 'Reportes globales',     'icono' => 'bi-bar-chart-line',   'texto' => 'Asistencia, rendimiento y evolución de toda la academia.',            'estado' => 'Pendiente'],
];
?>
<div class="mb-4">
    <h1 class="portal-title mb-1">¡Hola, <?= htmlspecialchars($nombre) ?>! 👋</h1>
    <p class="portal-subtitle mb-0">Panel de administración de SolfAcademy</p>
</div>

<div class="portal-card p-3 p-md-4 mb-4">
    <div class="d-flex align-items-start gap-3">
        <i class="bi bi-tools fs-2" style="color:#38bdf8;"></i>
        <div>
            <h2 class="portal-card-title mb-1">Panel en construcción</h2>
            <p class="mb-0" style="color:#94a3b8;">
                La base del panel ya está lista: la sesión, los roles y el acceso están cableados.
                Lo que falta es desarrollar los módulos de gestión que verás abajo.
            </p>
        </div>
    </div>
</div>

<div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 g-3">
    <?php foreach ($modulos as $m): ?>
        <div class="col">
            <div class="portal-card p-3 h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <i class="bi <?= $m['icono'] ?> fs-4" style="color:#38bdf8;"></i>
                    <span class="badge bg-secondary"><?= htmlspecialchars($m['estado']) ?></span>
                </div>
                <div class="fw-semibold mb-1"><?= htmlspecialchars($m['titulo']) ?></div>
                <div class="small mb-0" style="color:#94a3b8;"><?= htmlspecialchars($m['texto']) ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
