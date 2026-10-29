/**
 * Avisos y confirmaciones con SweetAlert2 para todo el proyecto.
 *
 * - solfAviso(tipo, mensaje): toast de 'success', 'error', 'warning' o
 *   'info'. Si falta la librería (CDN caído), usa alert() como respaldo.
 * - solfConfirmar(mensaje, opciones): devuelve Promise<boolean>. Si falta
 *   la librería, usa confirm() como respaldo.
 *
 * Los formularios con data-confirm="..." se confirman solos antes de
 * enviarse (ver más abajo).
 */
(function () {
    'use strict';

    var TIPOS_VALIDOS = { success: true, error: true, warning: true, info: true };

    function haySwal() {
        return typeof window.Swal === 'function';
    }

    window.solfAviso = function (tipo, mensaje) {
        tipo = TIPOS_VALIDOS[tipo] ? tipo : 'info';
        mensaje = String(mensaje || '');
        if (!mensaje) {
            return;
        }
        if (!haySwal()) {
            window.alert(mensaje);
            return;
        }
        window.Swal.fire({
            toast: true,
            position: 'top-end',
            icon: tipo,
            title: mensaje,
            showConfirmButton: false,
            timer: tipo === 'error' ? 6000 : 4000,
            timerProgressBar: true
        });
    };

    window.solfConfirmar = function (mensaje, opciones) {
        opciones = opciones || {};
        mensaje = String(mensaje || '');
        if (!haySwal()) {
            return Promise.resolve(window.confirm(mensaje));
        }
        return window.Swal.fire({
            title: opciones.titulo || 'Confirmar acción',
            text: mensaje,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: opciones.confirmar || 'Confirmar',
            cancelButtonText: opciones.cancelar || 'Cancelar',
            reverseButtons: true
        }).then(function (resultado) {
            return !!resultado.isConfirmed;
        });
    };

    // Formularios con data-confirm: preguntan antes de enviarse. Tras
    // confirmar se marca data-confirmado para que el segundo envío pase.
    document.addEventListener('submit', function (evento) {
        var formulario = evento.target;
        if (!formulario || !formulario.matches || !formulario.matches('form[data-confirm]')) {
            return;
        }
        if (formulario.dataset.confirmado === '1') {
            delete formulario.dataset.confirmado;
            return;
        }
        evento.preventDefault();
        window.solfConfirmar(formulario.dataset.confirm).then(function (ok) {
            if (ok) {
                formulario.dataset.confirmado = '1';
                formulario.requestSubmit();
            }
        });
    });
})();
