# SolfAcademy

Plataforma de academia de música construida con **Slim 4** + **PHP-DI** y **MySQL/MariaDB**. Incluye sitio público, portales de estudiante e instructor y administración de categorías, instrumentos, cursos, grupos, estudiantes, instructores y aulas. El catálogo, los cursos y los perfiles públicos de instructores leen datos reales de la base de datos.

## Requisitos

- PHP 8.0 o superior (probado con PHP 8.4)
- [Composer](https://getcomposer.org/)
- MySQL 8 / MariaDB 10.4 o superior

## Instalación

```bash
# 1. Dependencias de PHP
composer install

# 2. Crear la base de datos (o déjala crear al importar el SQL)
mysql -u valet -e "CREATE DATABASE solfacademy CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 3. Importar esquema + datos de prueba
mysql -u valet solfacademy < database/solfacademy.sql
#    (también puedes importar database/solfacademy.sql desde phpMyAdmin o Workbench)

# 4. Configurar tus credenciales
#    Editar config/db.php (ver sección siguiente)

# 5. Arrancar
composer start
```

Abre <http://127.0.0.1:8080> en el navegador.

Ejecuta las pruebas con:

```bash
composer test
```

Las pruebas de integración académica se habilitan explícitamente:

```bash
SOLFACADEMY_TEST_DB=1 vendor/bin/phpunit --do-not-cache-result
```

Usan la conexión de `config/db.php` y necesitan permiso `CREATE TEMPORARY TABLES`.
Crean tablas **temporales y vacías**, visibles solo en su propia conexión, con la
estructura de las existentes. No reimportan el SQL ni modifican registros reales.
Sin esa variable, las pruebas de integración se omiten; las de permisos y CSRF sí se ejecutan.

## Configuración de la base de datos

La conexión se configura en **`config/db.php`** (credenciales por defecto de desarrollo: usuario `valet` sin contraseña en `127.0.0.1`, base de datos `solfacademy`). Edita ese archivo con los datos de tu servidor:

```php
// config/db.php
return [
    'host'    => '127.0.0.1',
    'dbname'  => 'solfacademy',
    'user'    => 'valet',
    'pass'    => '',
    'charset' => 'utf8mb4',
];
```

Cómo fluye la configuración hasta la conexión PDO:

1. **`config/db.php`** — credenciales (fuente única de verdad).
2. **`app/settings.php`** — las incluye en el objeto `Settings` como `'db'`.
3. **`app/dependencies.php`** — registra `PDO::class` en el contenedor PHP-DI leyendo de `$settings->get('db')` (equivalente PHP-DI de `$container['db'] = function ($c) {...}` de la documentación de Slim).

La conexión es perezosa: PDO solo se abre cuando un controlador solicita `PDO::class`.

### Comprobar la conexión

```bash
php scripts/comprobar-db.php
```

Imprime la conexión correcta con el número de tablas y registros, o el error exacto si algo falla.

### Esquema

`database/solfacademy.sql` crea las 24 tablas (las 17 del diagrama entidad-relación, más `practica_estudiante` y las tablas propias de la implementación) con sus claves foráneas, vistas, procedimientos almacenados y datos de prueba alineados con la web (categorías, instrumentos, instructores, 9 cursos, grupos, horarios, aulas, estudiantes e inscripciones de ejemplo):

| Área | Tablas |
|---|---|
| Catálogo | `categoria`, `instrumento`, `curso` |
| Instructores | `instructor` (con `foto`), `instructor_instrumento` (N:M, con `nivel_dominio`) |
| Reglas | `requisito_curso`, `descuento` (vigencia, usos y % o monto fijo; CRUD en `/admin/descuentos`) |
| Grupos | `grupo` (`capacidad_maxima`, `estado`), `horario`, `aula` |
| Matrícula | `estudiante`, `inscripcion` (con `precio_inscripcion` congelado al matricular —ya con el descuento, si se aplicó código— e `id_descuento`) |
| Seguimiento | `evaluacion` (escala 0–10), `asistencia` (`presente` = `S`/`N`), `practica_estudiante` |
| Cobro | `pago` (con `referencia_pago`, `comprobante_url`, `creado_por` y verificación: `verificado_por`, `fecha_verificacion`, `motivo_verificacion`) |
| Perfil del alumno | `estudiante_foto`, `estudiante_preferencias`, `estudiante_instrumento_historial` |
| Reservas | `cabina`, `reserva_cabina`, `reserva_clase_extra` (también toca `grupo` e `instructor`) |
| Acceso | `usuario` (roles `estudiante` / `instructor` / `admin`; listado, alta y bloqueo en `/admin/usuarios`) |
| Configuración | `configuracion` (una sola fila: marca, lema, logo, contacto, redes, pie de página y nota de los PDF) |

> **Nota**: `usuario.password_hash` es un añadido respecto al diagrama original; es necesario para autenticar con `password_hash()` de PHP. Todos los usuarios de prueba usan la contraseña `solfacademy123` (por ejemplo `admin@solfacademy.com` o `laura.gomez@ejemplo.com`).
>
> **Nota de pagos**: el estado de pago *no* se guarda duplicado en `inscripcion`; se calcula con `SUM(pago.monto_pagado) WHERE estado='Pagado'` (fuente única de verdad). La vista `v_inscripciones_detalle` ya lo hace por ti. El estudiante envía su comprobante desde `/estudiante/pagos` y el movimiento nace en `Pendiente` (no descuenta); al verificarlo desde `/admin/pagos` pasa a `Pagado`, y si se rechaza queda en `Fallido` con motivo para que pueda enviar otro.
>
> **Nota de descuentos**: los cupones viven en `descuento` y solo el administrador los crea. Se aplican **al solicitar la matrícula**: el precio congelado ya sale con el descuento y `inscripcion.id_descuento` guarda el cupón usado, por lo que pagos y saldos no cambian de fórmula.
>
> **Nota sobre `evaluacion`**: la tabla guarda **una fila por inscripción**, no una por grupo. Para referirse a "la misma evaluación" de todos los alumnos de un grupo se usa el par `(tipo, fecha_evaluacion)`. El instructor crea la evaluación para su grupo completo desde el portal (fila por cada matrícula activa, con `calificacion = NULL`) y después registra las notas.

#### Vistas

| Vista | Qué muestra |
|---|---|
| `v_grupos_completos` | Cupo de cada grupo (las inscripciones canceladas no ocupan lugar) |
| `v_horarios_grupos` | Horario semanal de todos los grupos, ordenado Lunes→Domingo |
| `v_inscripciones_detalle` | Matrículas con total pagado y saldo pendiente calculados |
| `v_asistencia_estudiante` | % de asistencia por matrícula |
| `v_calificaciones_estudiante` | Notas con promedio por matrícula (escala 0–10) |

#### Procedimientos almacenados

| Procedimiento | Qué hace |
|---|---|
| `sp_verificar_disponibilidad_grupo(id_grupo, OUT lugares)` | Lugares libres de un grupo |
| `sp_registrar_inscripcion(estudiante, grupo, OUT ok, OUT msg)` | Matrícula segura: valida cupo, grupo activo y doble inscripción; congela el precio |
| `sp_actualizar_pago(inscripción, monto, método, concepto, referencia, creado_por, OUT ok, OUT msg)` | Registra un pago validando el monto |

El script es re-importable: borra y recrea las tablas cada vez que se ejecuta.

### Actualizar una instalación existente: gestión administrativa

**No reimportes `database/solfacademy.sql` si tienes datos que conservar.** Ejecuta:

```bash
php scripts/migrar-gestion.php
```

La migración es incremental e idempotente. Añade `estudiante.estado`
(`Activo` / `Suspendido`), fecha y motivo de la última suspensión, y los campos
de revocación de sesiones/restauración de acceso en `usuario`. Instala dos
triggers que impiden nuevas matrículas vigentes de alumnos suspendidos,
incluidas las que pasan por el procedimiento almacenado existente.
Requiere permisos `ALTER`, `TRIGGER` y lectura de metadatos. MySQL aplica el DDL
por pasos; si falla un permiso, corrígelo y vuelve a ejecutar el script.
No borra registros, no cambia contraseñas ni infiere suspensiones a partir de
cuentas que estaban deshabilitadas. Las instalaciones nuevas ya incluyen los
campos y triggers en el SQL base.

La misma migración añade `aula.tipo_inactividad` (`General` / `Mantenimiento`).
Se conserva `aula.activa` como indicador de habilitación: si está deshabilitada,
el nuevo campo distingue mantenimiento de otra inactividad. Las aulas existentes
reciben `General`; no se presume que estén en mantenimiento. Vuelve a ejecutar
el comando al actualizar a los módulos de Instructores y Aulas.

Para Requisitos y Matrículas añade `estudiante.fecha_nacimiento` (opcional),
campos de revisión de `inscripcion` y la tabla de historial
`inscripcion_auditoria`. Ejecuta nuevamente el mismo comando; no importa
ni borra registros. La edad de alumnos existentes queda vacía hasta que se
configure, sin inventar fechas históricas.

Para Pagos y Descuentos añade a `pago` los campos de verificación
(`verificado_por`, `fecha_verificacion`, `motivo_verificacion`) y a
`inscripcion` el cupón aplicado (`id_descuento`). Igual que en los casos
anteriores: mismo comando, sin borrar ni modificar registros; los pagos
existentes quedan como estaban y solo los nuevos pasan por verificación.

Para Configuración crea la tabla `configuracion` con su fila de valores
por defecto (los mismos que tenía la web antes del módulo). Si ya existe
la fila no se toca: lo que haya configurado la academia se conserva.

### Evitar errores comunes

- **"Unknown database solfacademy"** → aún no importaste `database/solfacademy.sql`.
- **"Access denied"** → revisa usuario/contraseña en `config/db.php`.
- **"Connection refused"** → el servidor MySQL/MariaDB no está activo.

## Rutas

### Públicas (sin sesión)

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/` | Homepage |
| GET | `/cursos` | Catálogo con filtros (instrumento, nivel, horario), búsqueda y orden |
| GET | `/cursos/{id}` | Detalle de curso |
| GET | `/instructores/{id}` | Perfil de instructor |
| GET | `/instructores/{id}/foto` | Foto pública solo de un instructor activo |
| GET | `/marca/logo` | Logo de la academia configurado (público; 404 si no hay) |
| GET / POST | `/login`, `/registro` | Autenticación y alta de estudiantes |
| GET | `/logout` | Cierra la sesión |

### Roles y portales

La tabla `usuario` da a cada cuenta un `rol` (`estudiante`, `instructor` o `admin`). Al iniciar sesión, `AuthService` normaliza el usuario en la sesión como
`['id', 'nombre', 'email', 'rol', 'id_referencia', 'version_sesion']`, donde **`id_referencia` es el `id_estudiante` o el `id_instructor`** según el rol: es la llave que usa
cada portal para acotar sus consultas.

Cada grupo de rutas está protegido por **`App\Application\Middleware\RequireRole`**:

| Situación | Resultado |
|---|---|
| Sin sesión | 302 → `/login` |
| Sesión de otro rol | 302 → al portal del rol que tiene |
| Rol correcto | Paso libre |

Además de la comprobación de rol, las operaciones sobre un `id` concreto (grupo, alumno, inscripción) validan en el servicio que el recurso **pertenezca al instructor** que hace la petición (`InstructorService::grupoPerteneceInstructor()`), para que nadie pueda leer ni escribir datos ajenos.

#### Portal del estudiante — `/estudiante/*`

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/estudiante/dashboard` | KPIs, próximos pagos y clases de hoy |
| GET | `/estudiante/cursos` | Mis cursos, progreso e historial |
| GET | `/estudiante/calendario` | Agenda semanal |
| GET | `/estudiante/asistencias` | Historial de asistencia |
| GET | `/estudiante/calificaciones` | Notas y promedios |
| GET | `/estudiante/calificaciones/boletin` | Boletín en PDF |
| GET | `/estudiante/pagos` | Pagos y saldo pendiente, con formulario para enviar comprobante |
| POST | `/estudiante/pagos/{id}/comprobante` | Sube comprobante + monto → movimiento `Pendiente` en verificación |
| GET | `/estudiante/pagos/comprobante/{archivo}` | Descarga del propio comprobante (sesión obligatoria) |
| GET / POST | `/estudiante/perfil`, `/perfil/foto`, `/perfil/preferencias`, `/perfil/password` | Datos, foto, preferencias y contraseña |
| GET | `/estudiante/reservas` | Reservas de cabina y clases extras |
| GET / POST | `/estudiante/reservas/cabinas` | Reservar cabina de práctica |
| GET / POST | `/estudiante/reservas/extras` | Pedir clase extra con el profesor del grupo |
| POST | `/estudiante/reservas/{id}/cancelar` | Cancelar una reserva propia |
| GET / POST | `/estudiante/matricular/{grupo}` | Revisar grupo/requisitos y enviar solicitud pendiente |
| POST | `/estudiante/matriculas/{id}/cancelar` | Cancelar una solicitud propia pendiente |

#### Portal del instructor — `/instructor/*`

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/instructor/dashboard` | Grupos, alumnos, clases de hoy y promedio |
| GET / POST | `/instructor/perfil`, `/perfil/foto`, `/perfil/password` | Ficha, foto y contraseña |
| GET | `/instructor/grupos` | Mis grupos |
| GET | `/instructor/grupos/{id}` | Alumnos de un grupo |
| GET | `/instructor/grupos/{id}/exportar` | Lista del grupo en CSV |
| GET | `/instructor/asistencias` | Control de asistencia por grupo y fecha |
| POST | `/instructor/asistencias/guardar` | Guardar asistencia |
| GET | `/instructor/calificaciones` | Notas por grupo y evaluación |
| POST | `/instructor/calificaciones/crear` | Crea la evaluación para **todo el grupo** (una fila por matrícula activa) |
| POST | `/instructor/calificaciones/guardar` | Registra notas (0–100 en el formulario → 0–10 en BD) |
| GET | `/instructor/reportes` | Asistencia, distribución de notas y evolución |
| GET | `/instructor/alumnos/{id}/perfil` | Ficha académica de un alumno (solo si es suyo) |

#### Administración — `/admin/*`

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/admin/dashboard` | Indicadores globales, ingresos, matrículas, alertas y actividad reciente |
| GET / POST | `/admin/categorias` | Buscar, listar y crear categorías |
| GET / POST | `/admin/instrumentos` | Buscar, listar y crear instrumentos |
| GET / POST | `/admin/cursos` | Buscar, filtrar, listar y crear cursos |
| POST | `/admin/{seccion}/{id}` | Editar categoría, instrumento o curso |
| POST | `/admin/categorias/{id}/eliminar` | Eliminar solo si no hay instrumentos relacionados |
| POST | `/admin/instrumentos/{id}/eliminar` | Eliminar solo si no hay cursos o instructores relacionados |
| POST | `/admin/cursos/{id}/estado` | Desactivar/reactivar sin borrar historial |
| GET / POST | `/admin/grupos` | Listar/filtrar y crear grupos con horarios |
| GET / POST | `/admin/grupos/{id}` | Ver grupo y alumnos / guardar edición |
| GET | `/admin/estudiantes` | Directorio, búsqueda, filtros y paginación |
| GET | `/admin/estudiantes/nuevo` | Formulario de alta con cuenta opcional |
| POST | `/admin/estudiantes` | Crear ficha y, opcionalmente, cuenta |
| GET / POST | `/admin/estudiantes/{id}` | Ficha académica / guardar datos personales |
| GET | `/admin/estudiantes/{id}/editar` | Formulario de edición |
| POST | `/admin/estudiantes/{id}/estado` | Suspender/liberar cupos o reactivar |
| GET / POST | `/admin/instructores` | Listado filtrado / crear instructor, especialidades y cuenta opcional |
| GET | `/admin/instructores/nuevo` | Formulario de alta |
| GET / POST | `/admin/instructores/{id}` | Ficha, carga e historial / guardar edición |
| GET | `/admin/instructores/{id}/editar` | Formulario de edición |
| POST | `/admin/instructores/{id}/estado` | Suspender/reactivar sin cancelar matrículas |
| GET / POST | `/admin/aulas` | Listado con disponibilidad calculada / crear aula |
| GET | `/admin/aulas/nuevo` | Formulario de alta |
| GET / POST | `/admin/aulas/{id}` | Agenda e historial / guardar datos y capacidad |
| GET | `/admin/aulas/{id}/editar` | Formulario de edición |
| POST | `/admin/aulas/{id}/estado` | Habilitar, deshabilitar o poner en mantenimiento |
| GET | `/admin/requisitos` | Listar y buscar requisitos de cursos |
| GET / POST | `/admin/requisitos/{id}` | Consultar o guardar requisitos de un curso |
| GET | `/admin/matriculas` | Listar y filtrar solicitudes e inscripciones |
| GET | `/admin/matriculas/{id}` | Revisar requisitos, cupo e historial |
| POST | `/admin/matriculas/{id}/aprobar` | Aprobar una solicitud pendiente con confirmación |
| POST | `/admin/matriculas/{id}/rechazar` | Rechazar y liberar cupo con motivo |
| GET | `/admin/pagos` | Cola de comprobantes con filtros e indicadores |
| GET | `/admin/pagos/{id}` | Ver comprobante, monto y saldo; aprobar o rechazar |
| POST | `/admin/pagos/{id}/aprobar` | Acredita el pago (`Pagado`) y actualiza el saldo |
| POST | `/admin/pagos/{id}/rechazar` | Rechaza con motivo (`Fallido`); el alumno puede reenviar |
| GET | `/admin/pagos/comprobante/{archivo}` | Sirve el comprobante (solo panel autenticado) |
| GET / POST | `/admin/descuentos`, `/admin/descuentos/nuevo`, `/admin/descuentos/{id}` | Listar, crear y editar códigos de descuento |
| POST | `/admin/descuentos/{id}/estado` | Activar/desactivar (nunca se borra un cupón) |
| GET | `/admin/usuarios` | Cuentas de acceso con píldoras por rol, búsqueda y estado |
| GET / POST | `/admin/usuarios/nuevo`, `/admin/usuarios` | Alta de cuenta (admin, o persona sin cuenta) |
| GET | `/admin/usuarios/{id}` | Ficha de la cuenta y acciones de seguridad |
| POST | `/admin/usuarios/{id}/bloqueo` | Bloquear/reactivar (invalida sesiones abiertas) |
| GET | `/admin/reportes` | Métricas y reportes generales (KPIs, dona y tabla) |
| GET | `/admin/reportes/exportar` | Descarga del PDF del reporte (generado en servidor) |
| GET / POST | `/admin/configuracion` | Datos de la academia (marca, contacto, redes, pie y nota de PDF) |
| POST | `/admin/configuracion/logo`, `/admin/configuracion/logo/quitar` | Subir o quitar el logo de la academia |

La edición se abre con `?editar={id}`. Los listados admiten `q` y `pagina`;
cursos también `nivel` y `estado`, e instrumentos `estado` (disponibilidad).
Cada página contiene hasta 10 registros y todos los formularios POST llevan
el campo `_csrf`, validado por el middleware global.

Los instructores se asignan a **grupos**, no directamente a cursos. El precio
se presenta en **USD como precio del curso**, no como mensualidad. Editarlo no
modifica `inscripcion.precio_inscripcion` de matrículas existentes.
Solo los cursos activos y disponibles aparecen en `/cursos` y `/cursos/{id}`;
se muestran grupos activos con cupos, horarios y requisitos reales. Cuando
faltan datos se indica expresamente; no se generan temarios ni reseñas ficticias.

El detalle de curso permite a un estudiante autenticado solicitar matrícula en
un grupo con lugares. Sin sesión, la ruta de solicitud redirige a
`/login?next=…` y el login vuelve automáticamente al formulario. Si el curso
no tiene requisitos configurados, se dice claramente; no se muestran como
"pendientes" condiciones inexistentes.

#### Requisitos

`/admin/requisitos` lista todos los cursos y permite distinguir los que tienen
condiciones configuradas. El formulario administra nivel mínimo, rango de edad,
conocimientos, instrumento propio/alquiler y descripción. Solo puede existir un
requisito por curso y se actualiza dentro de transacción.

La edad se verifica automáticamente cuando `estudiante.fecha_nacimiento` existe;
si falta, el estudiante puede enviar la solicitud, pero el administrador debe
confirmarla manualmente antes de aprobar. Nivel y conocimientos siempre requieren
revisión explícita. No se infiere aprobación por matrículas anteriores.

#### Matrículas desde el portal

`/estudiante/matricular/{grupo}` muestra curso, grupo, instructor, aula, fechas,
horarios, lugares, requisitos y precio. El estudiante debe confirmar que revisó
la información. Al enviar:

- se bloquean curso, estudiante y grupo en el orden usado por Grupos;
- se comprueba estudiante activo, grupo activo, curso habilitado, cupo,
  matrícula vigente del mismo curso y conflictos de horario;
- se crea `Pendiente`, congelando `precio_inscripcion`;
- se registra el evento inicial en `inscripcion_auditoria`;
- **no se crea ningún pago**.

`Activa + Pendiente` ocupan lugar. Una matrícula pendiente queda visible en
Mis Cursos, donde el propio estudiante puede cancelarla y liberar el cupo.
Las solicitudes no vencen automáticamente por ahora; la lista administrativa
muestra su antigüedad.

`/admin/matriculas` filtra por estudiante, curso y estado. El detalle revisa
edad, nivel, conocimientos, instrumento, conflictos, grupo, cupo y precio. La
aprobación exige confirmar requisitos y, si no hay fecha de nacimiento,
confirmar la edad manualmente; vuelve a validar estudiante, grupo, duplicados,
horarios, edad y capacidad. El rechazo exige motivo. Aprobar no significa que
la matrícula esté pagada. Cada decisión queda en la auditoría con responsable,
fecha y motivo; los registros existentes pueden no tener historial anterior.

#### Pagos: verificación de comprobantes

`/admin/pagos` es la cola de verificación: ordena primero los comprobantes
pendientes y ofrece búsqueda por estudiante, curso, referencia o `#pago`,
filtro por estado e indicadores (por verificar, monto en espera, verificados
hoy y total acreditado).

El estudiante envía su comprobante desde `/estudiante/pagos`: monto, método,
referencia opcional y archivo (JPG, PNG, WEBP o PDF hasta 5MB). El movimiento
nace en `Pendiente` con su `comprobante_url` y **no descuenta saldo**; mientras
haya uno en verificación no se acepta otro para esa matrícula. El archivo se
guarda en `var/uploads/comprobantes` (fuera de `public`) y solo se sirve desde
rutas con sesión —`/estudiante/pagos/comprobante/…` y
`/admin/pagos/comprobante/…`— validando que el nombre pertenezca a un pago.

El detalle admin muestra el comprobante embebido, el importe declarado, el
precio congelado, lo pagado y el saldo. Aprobar exige que el archivo exista en
disco y acredita el movimiento (`Pagado`) con `verificado_por` y
`fecha_verificacion`; si el total quedara por encima del precio de la
matrícula se aprueba igualmente con un aviso, la misma semántica de
`sp_actualizar_pago`. Rechazar exige motivo, deja `Fallido` con
`motivo_verificacion` (visible para el estudiante) y habilita un nuevo envío.
Ninguna acción edita montos ni borra pagos: el saldo sigue saliendo de
`SUM(pago) WHERE estado='Pagado'`.

#### Descuentos

`/admin/descuentos` crea los cupones: código único (el formulario sugiere uno
sin usar), porcentaje **o** monto fijo, alcance global o de un curso,
vigencia, usos —con opción «sin límite»— y estado. Solo se desactivan y nunca
se borran, porque `inscripcion.id_descuento` conserva la referencia. El
listado indica si está vigente hoy, los usos realizados/disponibles y el
estado, con búsqueda y filtro de activos/inactivos.

El estudiante escribe el código en la solicitud de matrícula y pulsa
**Aplicar** para ver el precio final antes de enviar. Al confirmar, la
matrícula congela `precio_inscripcion` con el descuento, guarda el cupón y
incrementa `usos_realizados` en la misma transacción. Si el código no vale
(no existe, vencido, desactivado, sin usos o propio de otro curso) la
matrícula **se crea igual** y el mensaje indica que quedó sin descuento: un
cupón erróneo nunca pierde la solicitud.

#### Grupos

El formulario se abre con `?editar={id}`; `?curso={id}` filtra el listado y
preselecciona el curso al crear. Permite asignar instructor, aula, cupos, fechas,
estado y hasta 21 franjas semanales. Filtra instructores activos por el
instrumento que dominan y conserva visibles las asignaciones anteriores para
revisión. Las nuevas asignaciones se validan también en servidor.

Se comprueban capacidad del aula, plazas ya ocupadas (`Activa` + `Pendiente`),
máximo de grupos del curso y cruces de instructor/aula por día, horas y fechas.
Los grupos y las clases extras comparten bloqueos de instructor para evitar
reservas incompatibles simultáneas. Se guarda grupo y horarios en transacción.
No se cambia el curso de un grupo con matrículas; la reasignación de instructor
requiere confirmación y resolver primero sus clases extras futuras.
No se borran grupos. Para cerrar uno deben resolverse sus matrículas vigentes.
Las aulas y los instructores se administran desde sus propios módulos y se
seleccionan desde los registros reales. El selector señala aulas en mantenimiento.

#### Estudiantes y suspensión

El alta crea una ficha sin cuenta por defecto. Si se elige crear cuenta, pide
contraseña inicial y confirmación (8–72 bytes), guarda solo su hash y mantiene
la sesión del administrador. La edición sincroniza el email de ficha y cuenta
en una transacción; nunca rellena contraseñas en respuestas de error.
Un estudiante puede tener varios instrumentos, derivados de sus matrículas.
La ficha muestra notas, asistencia, importes pagados y saldo calculado, sin
modificar pagos. Su fecha de nacimiento es opcional y alimenta la verificación
de requisitos cuando esté registrada.

Suspender requiere motivo y confirmación de las matrículas afectadas. En una
única transacción cancela las `Activas` y `Pendientes`, libera sus cupos,
marca al estudiante como suspendido y deshabilita sus cuentas. Conserva notas,
asistencias, matrículas finalizadas y pagos; no genera devoluciones ni modifica
reservas. Si las matrículas cambiaron desde que se abrió la ficha, exige volver
a revisarlas antes de confirmar.

Reactivar no restaura matrículas canceladas: restablece únicamente las cuentas
que estaban activas antes de suspender. Una cuenta previamente deshabilitada
se mantiene deshabilitada. Deshabilitar solo el acceso desde Editar no libera
cupos: para retirar al alumno hay que usar Suspender.

El middleware comprueba en cada petición autenticada el estado de la cuenta,
el estado académico y una versión de sesión. Una sesión anterior a la suspensión
no vuelve a funcionar al reactivar: el estudiante debe iniciar sesión de nuevo.

#### Instructores

El directorio permite buscar por nombre, contacto o especialidad, filtrar por
instrumento y estado y paginar sin duplicar instructores. Las especialidades
son instrumentos reales, con nivel de dominio y experiencia por instrumento.
La ficha muestra grupos abiertos, carga semanal configurada, historial y
clases extras futuras. La columna Calificación indica **Sin valoraciones**:
no existen reseñas docentes y no se confunden con las notas de sus alumnos.

El alta tiene cuenta opcional de rol fijo `instructor`. Ficha, especialidades,
cuenta y foto se guardan en transacción, conservando la sesión del admin. El
correo se sincroniza también al editar desde el portal del instructor. No se
puede quitar una especialidad requerida por sus grupos activos o planeados.
No se borran instructores: suspender exige resolver antes grupos abiertos y
clases extras futuras, bloquea el acceso e invalida sesiones, sin cancelar
matrículas de alumnos. Reactivar restaura solo el acceso previamente habilitado.

Las fotos se validan por contenido y MIME (JPEG/PNG/GIF/WebP, hasta 2 MB y 8000 px
por lado), se guardan con nombres aleatorios en `var/uploads/perfiles` y solo se
retira la anterior después de guardar correctamente. El endpoint público
`/instructores/{id}/foto` comprueba que el instructor esté activo y que el archivo
pertenezca a su prefijo; no abre la ruta privada de fotos de estudiantes.

#### Aulas

Se administran nombre, ubicación, capacidad (1–255) y recursos, con búsqueda y
paginación. El ID corresponde a `id_aula`, no a una numeración ficticia. Los
recursos se presentan como etiquetas y el horario de uso muestra las franjas
de grupos reales, con su agenda y fechas; no representa horarios de apertura.

**Disponible ahora** y **Ocupada según agenda** se calculan al cargar/actualizar
usando día, hora y fechas de grupos activos o planeados. Se muestra la hora y
zona de consulta. No son mediciones de presencia ni un estado editable.
La habilitación se cambia con confirmación desde la ficha: Activa, Inactiva o
Mantenimiento. No se deshabilita un aula con grupos abiertos ni se reduce su
capacidad por debajo de la configurada en ellos. Los bloqueos se comparten con
la gestión de grupos y se conserva el historial; no se borran aulas.

#### Usuarios

`/admin/usuarios` lista las cuentas de acceso con píldoras de filtro por rol
(Todos, Admin, Instructor, Estudiante —el ENUM de `usuario` no admite más),
búsqueda por correo/nombre/`#id` y filtro de estado. Cada fila muestra avatar
o iniciales, correo, badge de rol con color propio, el último acceso en
términos relativos (`Fecha::relativo`: "Activo ahora", "Hace 12 min", "Ayer",
"Nunca ha entrado") y `• Activo` / `• Bloqueado`. La cuenta administrativa no
tiene ficha de persona, así que se presenta como "Administrador", igual que en
el sidebar.

Las acciones son de seguridad, no de edición: el correo y los datos
personales se siguen cambiando desde Estudiantes/Instructores, donde ya se
sincronizan en transacción. **Bloquear** pone `activo = 0` y suma
`version_sesion`, de modo que cualquier sesión abierta deja de valer en la
siguiente petición (`CuentaService::vigente`); no se puede bloquear la cuenta
propia, y no se reactiva una cuenta cuyo estudiante esté suspendido o cuyo
instructor esté inactivo: primero se reactiva a la persona desde su ficha.
Las cuentas nunca se borran.

El alta (`/admin/usuarios/nuevo`) crea una cuenta admin sin persona, o da
acceso a un instructor/estudiante **sin cuenta** y activo: el correo se toma
de la ficha (y queda bloqueado en el formulario) para no desincronizarlo, la
contraseña solo se guarda con `password_hash()` y una persona sin correo en
su ficha lo completa al crear la cuenta. La contraseña solo se guarda con
`password_hash()`, debe tener 8–72 bytes y teclearse dos veces.

#### Reportes

`/admin/reportes` reúne finanzas, admisiones y rendimiento académico con
**datos reales** de la base de datos (nada de cifras de ejemplo):

| Indicador | Cálculo |
|---|---|
| Ingresos Totales | `SUM(pago) WHERE estado='Pagado'` y variación de los últimos 12 meses frente a los 12 anteriores («Sin periodo anterior comparable» si no hay base) |
| Alumnos Activos | `estudiante` con estado `Activo` |
| Tasa de Retención | matrículas **no canceladas** ÷ matrículas totales (`1 − abandono`) |
| Tasa de Aprobación | evaluaciones con nota **≥ 7** ÷ evaluaciones con nota (misma regla que el informe del instructor, escala 0–10) |

La dona **«Distribución de Alumnos por Instrumento»** agrupa a los alumnos
distintos con matrícula vigente (`Activa`/`Pendiente`) por el instrumento de
su curso, con Chart.js y leyenda de porcentajes; si hay más de cinco
instrumentos, el resto se agrupa en «Otros». La tabla **«Programas más
Populares»** ordena cursos por matrículas vigentes y muestra su **tendencia**:
variación de matrículas de los últimos 30 días frente a los 30 anteriores
(verde/rojo, «+1 nueva» cuando no había base y «—» cuando no hay comparación).

**Exportar PDF** descarga `reportes-generales-AAAAmmdd.pdf` generado en el
servidor con `App\Application\Formato\Pdf` (Helvetica + Latin-1, sin
dependencias): mismos KPIs, las barras de distribución con los colores de la
leyenda y la tabla de programas. Las páginas se cortan solas cuando el
contenido no cabe. La pantalla y el PDF comparten la misma fuente de datos
(`ReportesService::datos()`), así que nunca muestran cifras distintas.

#### Configuración de la academia

`/admin/configuracion` reúne **los datos de la academia** que opera esta
instalación en una sola fila (`configuracion`, id = 1):

- **Identidad**: nombre comercial (marca) y lema.
- **Logo**: PNG/JPG/WEBP hasta 512KB, validados por **MIME real** (no se
  aceptan SVG: es HTML ejecutable y la ruta es pública). Se guarda en
  `var/uploads/configuracion` con un único archivo; hay botón para
  quitarlo. Si no hay logo, se muestra el icono musical de fábrica.
- **Contacto**: correo, teléfono, dirección y ciudad (sede).
- **Redes**: Facebook, Instagram y YouTube con su URL completa; si están
  vacías, el icono no aparece en el pie (antes apuntaban a `#`).
- **Pie de página y PDF**: texto de copyright de la web y nota al pie del
  boletín, los comprobantes de pago y los reportes.

`ViewDataMiddleware` inyecta `$config` en **todas** las plantillas, así que
un solo guardado cambia la web pública, el login y registro, los tres
sidebars con sus pies `© {año} {nombre} {lema}` y los títulos de pestaña
(el sufijo histórico "— SolfAcademy" se sustituye por el nombre
configurado sin tocar los controladores). Los tres generadores de PDF
(boletín, `scripts/generar-comprobantes.php` y reportes) leen la misma
fila.

El logo se sirve en **`GET /marca/logo`** (público, porque el login y la
home no tienen sesión): solo devuelve el archivo con el prefijo permitido
o 404 si no existe. Si la tabla aún no está migrada o la base de datos no
responde, se usan los valores de fábrica y **ninguna página se rompe**.

## Estructura del proyecto

```
config/db.php              → credenciales de la base de datos
app/settings.php           → ajustes globales (incluye 'db')
app/dependencies.php       → definiciones del contenedor (logger, PDO)
app/routes.php             → rutas de la aplicación
database/solfacademy.sql   → esquema + datos de prueba
scripts/comprobar-db.php   → verificación de conexión
scripts/migrar-gestion.php → migración incremental sin reimportar los datos
scripts/generar-comprobantes.php → genera los PDF de /public/comprobantes
src/Application/Auth/      → AuthService (login, registro, sesión)
src/Application/Middleware/ → RequireRole, AuthMiddleware, sesión y vistas
src/Application/Estudiante/→ lógica del portal del estudiante
src/Application/Instructor/→ InstructorService (grupos, notas, asistencia, fichas)
src/Application/Perfil/    → almacenamiento y validación de fotos
src/Application/Pagos/     → ComprobanteStorage (subida y lectura de archivos)
src/Application/Descuentos/→ DescuentosService (códigos y su aplicación al matricular)
src/Application/Admin/     → dashboard, verificación de pagos, usuarios y gestión académica
src/Application/Academico/ → requisitos y ciclo de matrículas
src/Application/Formato/   → Dinero, Fecha y Pdf (sin depender del locale ni de librerías)
src/Application/Config/    → ConfiguracionService (marca, contacto y logo de la academia)
src/Application/Catalogo/  → categorías y catálogo público desde PDO
src/Controllers/Web/       → controladores del sitio web
src/Controllers/Estudiante/→ controladores del portal del estudiante
src/Controllers/Instructor/→ controladores del portal del instructor
src/Controllers/Admin/     → dashboard y controladores de gestión académica
src/Data/SampleData.php    → datos de ejemplo de la web pública
templates/web/             → vistas (layout.php + secciones)
templates/estudiante/      → vistas del portal del estudiante
templates/instructor/      → vistas del portal del instructor
templates/admin/           → vistas del panel de administración
public/css/style.css       → estilos globales
public/css/portal.css      → design system del portal (tema oscuro)
public/css/admin-academica.css → componentes de gestión sin alterar otros portales
public/js/admin-academica.js → selector categoría/instrumento y confirmaciones
public/comprobantes/       → PDF de comprobante de pago (generados)
var/uploads/comprobantes/  → comprobantes subidos por los alumnos (no versionado)
```

La homepage usa categorías e instructores reales. Solo el bloque de testimonios
conserva los ejemplos de `src/Data/SampleData.php`. Los perfiles de instructores
no inventan biografías, puntuaciones ni reseñas; muestran especialidades,
experiencia y cursos publicados desde PDO. El catálogo, el detalle del curso
y los portales autenticados también usan PDO.

## Licencia

Ver [LICENSE](LICENSE).
