<!-- Calendario de Clases -->
<?php
/**
 * Calendario (pantalla 3 del prototipo).
 * Datos: $calendario = EstudiantePortalService::calendario()
 *   - clases: [{idCurso, curso, categoria, aula, ubicacion, instructor,
 *               inicio, fin, horaInicio, horaFin, dias[], color, url}]
 *             dias[] usa el índice de Date.getDay(): 0=Dom ... 6=Sáb
 *   - leyenda: [{curso, color}]
 */
$calendario = $calendario ?? ['clases' => [], 'leyenda' => []];
$clases     = $calendario['clases'] ?? [];
$leyenda    = $calendario['leyenda'] ?? [];
$clasesJson = json_encode($clases, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="portal-title mb-1">Calendario de Clases</h1>
        <p class="portal-subtitle mb-0">Todas tus clases semanales en un solo lugar.</p>
    </div>
    <a href="/estudiante/reservas/extras" class="btn btn-accent fw-bold">
        <i class="bi bi-plus-lg me-1"></i>Reservar Clase Extra
    </a>
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

    <div class="row g-3">
        <!-- Calendario mensual -->
        <div class="col-12 col-xl-8">
            <div class="portal-card p-3 p-md-4 h-100">
                <div class="cal-toolbar">
                    <button type="button" class="cal-nav" id="cal-prev" aria-label="Mes anterior">
                        <i class="bi bi-chevron-left"></i>
                    </button>
                    <div class="cal-title" id="cal-titulo">—</div>
                    <button type="button" class="cal-nav" id="cal-next" aria-label="Mes siguiente">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" id="cal-hoy">Hoy</button>
                </div>

                <div class="cal-grid" id="cal-grid"></div>
            </div>
        </div>

        <!-- Clases del día + CTA -->
        <div class="col-12 col-xl-4">
            <div class="portal-card p-3 p-md-4 mb-3">
                <h2 class="cal-dia-titulo" id="cal-dia-titulo">—</h2>
                <div id="cal-dia-lista"></div>
            </div>

            <div class="portal-card p-3 p-md-4 cal-cta">
                <span class="portal-kpi-icon mb-3"><i class="bi bi-music-note-beamed"></i></span>
                <h3 class="h6 text-white fw-bold mb-1">Reserva cabinas de piano acústico</h3>
                <p class="small mb-3" style="color: var(--portal-muted);">
                    Cabinas gratuitas para practicar durante toda la semana.
                </p>
                <a href="/estudiante/reservas/cabinas" class="btn btn-accent w-100 fw-bold">
                    Reservar cabina <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Leyenda de cursos -->
    <div class="portal-card p-3 mt-3">
        <div class="d-flex flex-wrap gap-3">
            <?php foreach ($leyenda as $item): ?>
                <span class="d-flex align-items-center gap-2 small">
                    <span style="width:12px; height:12px; border-radius:3px; background:<?= htmlspecialchars($item['color']) ?>; display:inline-block;"></span>
                    <?= htmlspecialchars($item['curso']) ?>
                </span>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var clases = <?= $clasesJson ?>;

            var grid = document.getElementById('cal-grid');
            var elTitulo = document.getElementById('cal-titulo');
            var elDiaTitulo = document.getElementById('cal-dia-titulo');
            var elDiaLista = document.getElementById('cal-dia-lista');

            var DIAS_CORTOS = ['LUN', 'MAR', 'MIÉ', 'JUE', 'VIE', 'SÁB', 'DOM'];
            var hoy = new Date();
            hoy.setHours(0, 0, 0, 0);

            var mesActual = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
            var seleccion = new Date(hoy);

            var fmtMes = new Intl.DateTimeFormat('es-ES', { month: 'long', year: 'numeric' });
            var fmtDia = new Intl.DateTimeFormat('es-ES', { weekday: 'long', day: 'numeric', month: 'long' });

            function capitalizar(texto) {
                return texto.charAt(0).toUpperCase() + texto.slice(1);
            }

            function ymd(fecha) {
                return fecha.getFullYear() + '-'
                    + String(fecha.getMonth() + 1).padStart(2, '0') + '-'
                    + String(fecha.getDate()).padStart(2, '0');
            }

            function mismoDia(a, b) {
                return a.getFullYear() === b.getFullYear()
                    && a.getMonth() === b.getMonth()
                    && a.getDate() === b.getDate();
            }

            function esc(texto) {
                var div = document.createElement('div');
                div.textContent = texto == null ? '' : String(texto);
                return div.innerHTML;
            }

            function clasesDelDia(fecha) {
                var iso = ymd(fecha);
                var dia = fecha.getDay();
                return clases.filter(function (clase) {
                    if (clase.dias.indexOf(dia) === -1) return false;
                    if (clase.inicio && iso < clase.inicio) return false;
                    if (clase.fin && iso > clase.fin) return false;
                    return true;
                }).sort(function (a, b) {
                    return a.horaInicio.localeCompare(b.horaInicio);
                });
            }

            function renderDia() {
                elDiaTitulo.textContent = capitalizar(fmtDia.format(seleccion));
                var lista = clasesDelDia(seleccion);

                if (lista.length === 0) {
                    elDiaLista.innerHTML = '<div class="cal-vacio">'
                        + '<i class="bi bi-calendar-x"></i>'
                        + '<p class="small mb-0">Sin clases este día.</p>'
                        + '</div>';
                    return;
                }

                var html = '';
                lista.forEach(function (clase) {
                    html += '<a class="clase-item" href="' + esc(clase.url) + '">'
                        + '<div class="d-flex justify-content-between align-items-center gap-2 mb-1">'
                        + '<span class="clase-item-hora">' + esc(clase.horaInicio) + ' – ' + esc(clase.horaFin) + '</span>'
                        + '<span class="clase-item-punto" style="background:' + esc(clase.color) + '"></span>'
                        + '</div>'
                        + '<div class="clase-item-curso">' + esc(clase.curso) + '</div>'
                        + '<div class="clase-item-meta"><i class="bi bi-geo-alt"></i>'
                        + esc(clase.aula) + (clase.ubicacion ? ' · ' + esc(clase.ubicacion) : '')
                        + '</div>'
                        + '<div class="clase-item-meta"><i class="bi bi-person"></i>' + esc(clase.instructor) + '</div>'
                        + '</a>';
                });
                elDiaLista.innerHTML = html;
            }

            function render() {
                elTitulo.textContent = capitalizar(fmtMes.format(mesActual));

                var html = '';
                DIAS_CORTOS.forEach(function (dia) {
                    html += '<div class="cal-dow">' + dia + '</div>';
                });

                var primerDia = new Date(mesActual.getFullYear(), mesActual.getMonth(), 1);
                var offset = (primerDia.getDay() + 6) % 7; // lunes primero
                var diasMes = new Date(mesActual.getFullYear(), mesActual.getMonth() + 1, 0).getDate();
                var totalCeldas = Math.ceil((offset + diasMes) / 7) * 7;
                var inicio = new Date(mesActual.getFullYear(), mesActual.getMonth(), 1 - offset);

                for (var i = 0; i < totalCeldas; i++) {
                    var fecha = new Date(inicio.getFullYear(), inicio.getMonth(), inicio.getDate() + i);
                    var fuera = fecha.getMonth() !== mesActual.getMonth();
                    var delDia = clasesDelDia(fecha);

                    var clase = 'cal-cell';
                    if (fuera) clase += ' cal-cell--fuera';
                    if (mismoDia(fecha, hoy)) clase += ' cal-cell--hoy';
                    if (mismoDia(fecha, seleccion)) clase += ' cal-cell--sel';

                    var chips = '';
                    delDia.slice(0, 2).forEach(function (claseDia) {
                        var etiqueta = claseDia.categoria || claseDia.curso;
                        chips += '<span class="cal-chip" title="' + esc(claseDia.curso) + '"'
                            + ' style="background:' + esc(claseDia.color) + '">'
                            + esc(etiqueta) + '</span>';
                    });
                    if (delDia.length > 2) {
                        chips += '<span class="cal-chip cal-chip--mas">+' + (delDia.length - 2) + '</span>';
                    }

                    html += '<button type="button" class="' + clase + '" data-fecha="' + ymd(fecha) + '">'
                        + '<span class="cal-cell-num">' + fecha.getDate() + '</span>'
                        + '<span class="cal-cell-eventos">' + chips + '</span>'
                        + '</button>';
                }

                grid.innerHTML = html;
                renderDia();
            }

            grid.addEventListener('click', function (evento) {
                var celda = evento.target.closest('.cal-cell');
                if (!celda) return;

                var partes = celda.getAttribute('data-fecha').split('-');
                seleccion = new Date(Number(partes[0]), Number(partes[1]) - 1, Number(partes[2]));

                if (seleccion.getMonth() !== mesActual.getMonth()
                    || seleccion.getFullYear() !== mesActual.getFullYear()) {
                    mesActual = new Date(seleccion.getFullYear(), seleccion.getMonth(), 1);
                }

                render();
            });

            document.getElementById('cal-prev').addEventListener('click', function () {
                mesActual = new Date(mesActual.getFullYear(), mesActual.getMonth() - 1, 1);
                render();
            });

            document.getElementById('cal-next').addEventListener('click', function () {
                mesActual = new Date(mesActual.getFullYear(), mesActual.getMonth() + 1, 1);
                render();
            });

            document.getElementById('cal-hoy').addEventListener('click', function () {
                mesActual = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
                seleccion = new Date(hoy);
                render();
            });

            render();
        });
    </script>

    <!-- Modal reutilizable para funciones aún no disponibles -->
    <div class="modal fade" id="modalProximamente" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content portal-modal">
                <div class="modal-body text-center p-4">
                    <span class="portal-modal-icon"><i class="bi bi-stars"></i></span>
                    <h2 class="portal-card-title h5 mb-1" data-modal-titulo-texto>Próximamente</h2>
                    <p class="small mb-4" style="color: var(--portal-muted);" data-modal-mensaje-texto>
                        Esta función estará disponible muy pronto.
                    </p>
                    <button type="button" class="btn btn-accent fw-semibold px-4" data-bs-dismiss="modal">Entendido</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var modal = document.getElementById('modalProximamente');
            if (!modal) return;
            modal.addEventListener('show.bs.modal', function (event) {
                var boton = event.relatedTarget;
                if (!boton) return;
                var titulo = boton.getAttribute('data-modal-titulo');
                var mensaje = boton.getAttribute('data-modal-mensaje');
                var elTitulo = modal.querySelector('[data-modal-titulo-texto]');
                var elMensaje = modal.querySelector('[data-modal-mensaje-texto]');
                if (titulo) elTitulo.textContent = titulo;
                if (mensaje) elMensaje.textContent = mensaje;
            });
        });
    </script>

<?php endif; ?>
