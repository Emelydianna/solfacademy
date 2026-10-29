document.addEventListener('DOMContentLoaded', function () {
    const primerError = document.querySelector('#form-academico [aria-invalid="true"]');
    if (primerError) primerError.focus();
    const categoria = document.querySelector('[data-filtro-categoria]');
    const instrumento = document.getElementById('id_instrumento');
    if (categoria && instrumento) {
        const opciones = Array.from(instrumento.options).map(function (opcion) { return opcion.cloneNode(true); });
        const filtrar = function () {
            const seleccionado = instrumento.value;
            instrumento.replaceChildren();
            opciones.forEach(function (opcion) {
                if (!opcion.value || !categoria.value || opcion.dataset.categoria === categoria.value) {
                    instrumento.add(opcion.cloneNode(true));
                }
            });
            instrumento.value = seleccionado;
            if (instrumento.selectedIndex < 0) instrumento.value = '';
        };
        categoria.addEventListener('change', filtrar);
        filtrar();
    }

    const cursoGrupo = document.querySelector('[data-grupo-curso]');
    const instructorGrupo = document.querySelector('[data-grupo-instructor]');
    if (cursoGrupo && instructorGrupo) {
        const opcionesInstructor = Array.from(instructorGrupo.options).map(function (o) { return o.cloneNode(true); });
        const filtrarInstructor = function () {
            const seleccionado = instructorGrupo.value;
            const instrumentoCurso = cursoGrupo.selectedOptions[0]?.dataset.instrumento;
            instructorGrupo.replaceChildren();
            opcionesInstructor.forEach(function (o) {
                const compatible = o.dataset.activo === '1' && instrumentoCurso && (o.dataset.instrumentos || '').split(',').includes(instrumentoCurso);
                const actual = o.value === instructorGrupo.dataset.original && cursoGrupo.value === cursoGrupo.dataset.original;
                if (!o.value || compatible || actual) instructorGrupo.add(o.cloneNode(true));
            });
            instructorGrupo.value = seleccionado;
            if (instructorGrupo.selectedIndex < 0) instructorGrupo.value = '';
        };
        cursoGrupo.addEventListener('change', filtrarInstructor);
        filtrarInstructor();
    }

    const horarios = document.querySelector('[data-horarios]');
    const agregarHorario = document.querySelector('[data-agregar-horario]');
    if (horarios && agregarHorario) {
        const renumerar = function () {
            const filas = horarios.querySelectorAll('[data-horario]');
            filas.forEach(function (fila, indice) {
                fila.querySelectorAll('[data-campo]').forEach(function (campo) { campo.name = 'horarios[' + indice + '][' + campo.dataset.campo + ']'; });
                fila.querySelector('[data-quitar-horario]').disabled = filas.length === 1;
            });
            agregarHorario.disabled = filas.length >= 21;
        };
        agregarHorario.addEventListener('click', function () {
            horarios.appendChild(document.getElementById('plantilla-horario').content.cloneNode(true));
            renumerar();
        });
        horarios.addEventListener('click', function (evento) {
            const boton = evento.target.closest('[data-quitar-horario]');
            if (boton && horarios.querySelectorAll('[data-horario]').length > 1) { boton.closest('[data-horario]').remove(); renumerar(); }
        });
        renumerar();
    }

    const crearCuenta = document.querySelector('[data-crear-cuenta]');
    const camposCuenta = document.querySelector('[data-cuenta-campos]');
    if (crearCuenta && camposCuenta) {
        const alternarCuenta = function () {
            camposCuenta.hidden = !crearCuenta.checked;
            camposCuenta.querySelectorAll('input').forEach(function (campo) { campo.disabled = !crearCuenta.checked; campo.required = crearCuenta.checked; });
        };
        crearCuenta.addEventListener('change', alternarCuenta);
        alternarCuenta();
    }

    const especialidades = document.querySelector('[data-especialidades]');
    const agregarEspecialidad = document.querySelector('[data-agregar-especialidad]');
    if (especialidades && agregarEspecialidad) {
        const renumerarEspecialidades = function () {
            const filas = especialidades.querySelectorAll('[data-especialidad]');
            filas.forEach(function (fila, indice) {
                fila.querySelectorAll('[data-especialidad-campo]').forEach(function (campo) { campo.name = 'especialidades[' + indice + '][' + campo.dataset.especialidadCampo + ']'; });
                fila.querySelector('[data-quitar-especialidad]').disabled = filas.length === 1;
            });
            agregarEspecialidad.disabled = filas.length >= 50;
        };
        agregarEspecialidad.addEventListener('click', function () {
            especialidades.appendChild(document.getElementById('plantilla-especialidad').content.cloneNode(true));
            renumerarEspecialidades();
        });
        especialidades.addEventListener('click', function (evento) {
            const boton = evento.target.closest('[data-quitar-especialidad]');
            if (boton && especialidades.querySelectorAll('[data-especialidad]').length > 1) {
                boton.closest('[data-especialidad]').remove();
                renumerarEspecialidades();
            }
        });
        renumerarEspecialidades();
    }

    // Las confirmaciones de formularios con data-confirm las gestiona
    // notificaciones.js (SweetAlert2), cargado en el <head> de los layouts.
});
