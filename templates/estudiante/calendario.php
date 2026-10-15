<!-- Calendario de Clases -->
<?php
/**
 * Calendario (pantalla 3 del prototipo).
 * Datos: $calendario = EstudiantePortalService::calendario()
 *   - eventos: [{title, daysOfWeek[], startTime, endTime, startRecur,
 *                endRecur, color, idCurso, url}]
 *   - leyenda: [{curso, color}]
 */
$calendario   = $calendario ?? ['eventos' => [], 'leyenda' => []];
$eventosJson  = json_encode($calendario['eventos'] ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
$leyenda      = $calendario['leyenda'] ?? [];
?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="portal-title mb-1">Calendario</h1>
        <p class="portal-subtitle mb-0">Todas tus clases semanales en un solo lugar.</p>
    </div>
    <?php if (count($leyenda) > 0): ?>
        <span class="portal-pill"><?= count($leyenda) ?> curso(s) activo(s)</span>
    <?php endif; ?>
</div>

<?php if (count($leyenda) === 0): ?>
    <div class="portal-card p-4 text-center">
        <i class="bi bi-calendar3 fs-1 d-block mb-2" style="color: var(--portal-muted);"></i>
        <p class="mb-1 fw-semibold">No hay clases para mostrar</p>
        <p class="small text-secondary mb-3">
            Cuando te inscribas a un curso con horario asignado, aparecerá aquí.
        </p>
        <a href="/cursos" class="btn btn-accent fw-semibold">Ver catálogo de cursos</a>
    </div>
<?php else: ?>

    <!-- Leyenda de cursos -->
    <div class="portal-card p-3 mb-3">
        <div class="d-flex flex-wrap gap-3">
            <?php foreach ($leyenda as $item): ?>
                <span class="d-flex align-items-center gap-2 small">
                    <span style="width:12px; height:12px; border-radius:3px; background:<?= htmlspecialchars($item['color']) ?>; display:inline-block;"></span>
                    <?= htmlspecialchars($item['curso']) ?>
                </span>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Calendario -->
    <div class="portal-card p-3 p-md-4">
        <div id="calendario-solfacademy"></div>
    </div>

    <!-- FullCalendar v6 — versión GRATUITA (bundle global con estilos incluidos) -->
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var calendario = new FullCalendar.Calendar(
                document.getElementById('calendario-solfacademy'),
                {
                    locale: 'es',
                    initialView: 'timeGridWeek',   // vista semanal por defecto
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'dayGridMonth,timeGridWeek,listWeek'
                    },
                    buttonText: { today: 'Hoy', month: 'Mes', week: 'Semana', list: 'Lista' },
                    height: 'auto',
                    allDaySlot: false,
                    firstDay: 1,                   // la semana empieza en Lunes
                    slotMinTime: '06:00:00',       // franja visible razonable
                    slotMaxTime: '23:00:00',
                    scrollTime: '08:00:00',
                    nowIndicator: true,
                    navLinks: true,
                    dayMaxEvents: 3,
                    eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
                    events: <?= $eventosJson ?>,
                    // Al pulsar una clase se abre el detalle del curso
                    eventClick: function (info) {
                        if (info.event.url) {
                            info.jsEvent.preventDefault();
                            window.location.href = info.event.url;
                        }
                    },
                    // Tooltip al pasar el ratón sobre una clase
                    eventDidMount: function (info) {
                        info.el.title = info.event.title;
                    }
                }
            );

            calendario.render();
        });
    </script>

    <style class="portal-cal-estilos">
        /* Ajustes del calendario al design system dark del portal */
        .fc {
            --fc-border-color: #1f293d;
            --fc-page-bg-color: transparent;
            --fc-neutral-bg-color: #131b2e;
            --fc-neutral-text-color: #8b93a7;
            --fc-today-bg-color: rgba(255, 193, 7, 0.06);
            --fc-now-indicator-color: #ffc107;
            color: var(--portal-text);
        }
        .fc .fc-toolbar-title { font-size: 1.15rem; color: var(--portal-text); }
        .fc .fc-button-primary {
            background-color: #182138;
            border-color: #1f293d;
            color: #e5e7eb;
        }
        .fc .fc-button-primary:hover { background-color: #1f293d; border-color: #2b3852; }
        .fc .fc-button-primary:not(:disabled).fc-button-active,
        .fc .fc-button-primary:not(:disabled):active {
            background-color: var(--portal-gold);
            border-color: var(--portal-gold);
            color: #0b0f19;
        }
        .fc .fc-col-header-cell-cushion,
        .fc .fc-daygrid-day-number,
        .fc .fc-list-day-text,
        .fc .fc-list-day-side-text { color: var(--portal-text); text-decoration: none; }
        .fc .fc-timegrid-slot-label-cushion,
        .fc .fc-list-day-cushion { color: var(--portal-muted); text-decoration: none; }
        .fc .fc-daygrid-day.fc-day-today .fc-daygrid-day-number { color: var(--portal-gold); font-weight: 700; }
        .fc .fc-event { border-radius: 4px; border: none; cursor: pointer; font-size: .78rem; }
        .fc .fc-list { background: transparent; }
        .fc .fc-list-event:hover td { background-color: #182138; }
        .fc .fc-scrollgrid { border-color: #1f293d; }
        .fc .fc-timegrid-body, .fc .fc-daygrid-body { border-color: #1f293d; }
        .fc .fc-timegrid-slot, .fc .fc-daygrid-day { border-color: #1f293d; }
        .fc .fc-highlight { background-color: rgba(255, 193, 7, 0.12); }
        .fc .fc-list-table td { border-color: #1f293d; }
        .fc-direction-ltr .fc-daygrid-event.fc-event-end,
        .fc-direction-ltr .fc-daygrid-event.fc-event-start { margin: 1px 2px 0; }
    </style>

<?php endif; ?>
