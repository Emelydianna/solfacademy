# SolfAcademy

Plataforma de academia de música construida con **Slim 4** + **PHP-DI** y **MySQL/MariaDB**. Incluye sitio público, portales de estudiante e instructor y administración de categorías, instrumentos, cursos, grupos y estudiantes. El catálogo y el detalle de cursos ya leen datos reales de la base de datos.

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

`database/solfacademy.sql` crea las 23 tablas (las 17 del diagrama entidad-relación, más `practica_estudiante` y las tablas propias de la implementación) con sus claves foráneas, vistas, procedimientos almacenados y datos de prueba alineados con la web (categorías, instrumentos, instructores, 9 cursos, grupos, horarios, aulas, estudiantes e inscripciones de ejemplo):

| Área | Tablas |
|---|---|
| Catálogo | `categoria`, `instrumento`, `curso` |
| Instructores | `instructor` (con `foto`), `instructor_instrumento` (N:M, con `nivel_dominio`) |
| Reglas | `requisito_curso`, `descuento` (vigencia, usos y % o monto fijo) |
| Grupos | `grupo` (`capacidad_maxima`, `estado`), `horario`, `aula` |
| Matrícula | `estudiante`, `inscripcion` (con `precio_inscripcion` congelado al matricular) |
| Seguimiento | `evaluacion` (escala 0–10), `asistencia` (`presente` = `S`/`N`), `practica_estudiante` |
| Cobro | `pago` (con `referencia_pago`, `comprobante_url` y `creado_por`) |
| Perfil del alumno | `estudiante_foto`, `estudiante_preferencias`, `estudiante_instrumento_historial` |
| Reservas | `cabina`, `reserva_cabina`, `reserva_clase_extra` (también toca `grupo` e `instructor`) |
| Acceso | `usuario` (roles `estudiante` / `instructor` / `admin`) |

> **Nota**: `usuario.password_hash` es un añadido respecto al diagrama original; es necesario para autenticar con `password_hash()` de PHP. Todos los usuarios de prueba usan la contraseña `solfacademy123` (por ejemplo `admin@solfacademy.com` o `laura.gomez@ejemplo.com`).
>
> **Nota de pagos**: el estado de pago *no* se guarda duplicado en `inscripcion`; se calcula con `SUM(pago.monto_pagado) WHERE estado='Pagado'` (fuente única de verdad). La vista `v_inscripciones_detalle` ya lo hace por ti.
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

### Actualizar una instalación existente: Grupos y Estudiantes

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
| GET / POST | `/login`, `/registro` | Autenticación y alta de estudiantes |
| GET | `/logout` | Cierra la sesión |

### Roles y portales

La tabla `usuario` da a cada cuenta un `rol` (`estudiante`, `instructor` o `admin`). Al iniciar sesión, `AuthService` normaliza el usuario en la sesión como
`['id', 'nombre', 'email', 'rol', 'id_referencia']`, donde **`id_referencia` es el `id_estudiante` o el `id_instructor`** según el rol: es la llave que usa
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
| GET | `/estudiante/pagos` | Pagos y saldo pendiente |
| GET / POST | `/estudiante/perfil`, `/perfil/foto`, `/perfil/preferencias`, `/perfil/password` | Datos, foto, preferencias y contraseña |
| GET | `/estudiante/reservas` | Reservas de cabina y clases extras |
| GET / POST | `/estudiante/reservas/cabinas` | Reservar cabina de práctica |
| GET / POST | `/estudiante/reservas/extras` | Pedir clase extra con el profesor del grupo |
| POST | `/estudiante/reservas/{id}/cancelar` | Cancelar una reserva propia |

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

La edición se abre con `?editar={id}`. Los listados admiten `q` y `pagina`;
cursos también `nivel` y `estado`, e instrumentos `estado` (disponibilidad).
Cada página contiene hasta 10 registros y todos los formularios POST llevan
el campo `_csrf`, validado por el middleware global.

Los instructores se asignan a **grupos**, no directamente a cursos. El precio
se presenta en **USD como precio del curso**, no como mensualidad. Editarlo no
modifica `inscripcion.precio_inscripcion` de matrículas existentes.
Solo los cursos activos y disponibles aparecen en `/cursos` y `/cursos/{id}`;
se muestran grupos activos o planeados, horarios y requisitos reales. Cuando
faltan datos se indica expresamente; no se generan temarios ni reseñas ficticias.
La gestión de requisitos queda para los siguientes módulos.

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
Las aulas y los instructores se seleccionan de los registros ya existentes;
sus CRUD completos aún están pendientes.

#### Estudiantes y suspensión

El alta crea una ficha sin cuenta por defecto. Si se elige crear cuenta, pide
contraseña inicial y confirmación (8–72 bytes), guarda solo su hash y mantiene
la sesión del administrador. La edición sincroniza el email de ficha y cuenta
en una transacción; nunca rellena contraseñas en respuestas de error.
Un estudiante puede tener varios instrumentos, derivados de sus matrículas.
La ficha muestra notas, asistencia, importes pagados y saldo calculado, sin
modificar pagos. La gestión de nuevas matrículas se abordará por separado.

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

## Estructura del proyecto

```
config/db.php              → credenciales de la base de datos
app/settings.php           → ajustes globales (incluye 'db')
app/dependencies.php       → definiciones del contenedor (logger, PDO)
app/routes.php             → rutas de la aplicación
database/solfacademy.sql   → esquema + datos de prueba
scripts/comprobar-db.php   → verificación de conexión
scripts/generar-comprobantes.php → genera los PDF de /public/comprobantes
src/Application/Auth/      → AuthService (login, registro, sesión)
src/Application/Middleware/ → RequireRole, AuthMiddleware, sesión y vistas
src/Application/Estudiante/→ lógica del portal del estudiante
src/Application/Instructor/→ InstructorService (grupos, notas, asistencia, fichas)
src/Application/Admin/     → dashboard y gestión académica
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
```

La homepage usa categorías reales; los bloques de instructores y testimonios,
así como el perfil público del instructor, conservan los ejemplos de
`src/Data/SampleData.php` hasta abordar su módulo. El catálogo, el detalle del
curso y los portales autenticados usan PDO.

## Licencia

Ver [LICENSE](LICENSE).
