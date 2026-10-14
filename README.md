# SolfAcademy

Plataforma de academia de música construida con **Slim 4** + **PHP-DI** y **MySQL/MariaDB**. El sitio web público (homepage, catálogo de cursos, detalle de curso, perfiles de instructores, login/registro) está desarrollado siguiendo el prototipo de diseño; la parte autenticada (matrículas, horarios, pagos) corresponde a la fase 2.

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

`database/solfacademy.sql` crea las 17 tablas del diagrama entidad-relación (más `practica_estudiante`) con sus claves foráneas, vistas, procedimientos almacenados y datos de prueba alineados con la web (categorías, instrumentos, instructores, 9 cursos, grupos, horarios, aulas, estudiantes e inscripciones de ejemplo):

| Área | Tablas |
|---|---|
| Catálogo | `categoria`, `instrumento`, `curso` |
| Instructores | `instructor`, `instructor_instrumento` (N:M, con `nivel_dominio`) |
| Reglas | `requisito_curso`, `descuento` (vigencia, usos y % o monto fijo) |
| Grupos | `grupo` (`capacidad_maxima`, `estado`), `horario`, `aula` |
| Matrícula | `estudiante`, `inscripcion` (con `precio_inscripcion` congelado al matricular) |
| Seguimiento | `evaluacion` (escala 0–10), `asistencia` (`presente` = `S`/`N`), `practica_estudiante` |
| Cobro | `pago` (con `referencia_pago`, `comprobante_url` y `creado_por`) |
| Acceso | `usuario` (roles `estudiante` / `instructor` / `admin`) |

> **Nota**: `usuario.password_hash` es un añadido respecto al diagrama original; es necesario para autenticar con `password_hash()` de PHP. Todos los usuarios de prueba usan la contraseña `solfacademy123` (por ejemplo `admin@solfacademy.com` o `laura.gomez@ejemplo.com`).
>
> **Nota de pagos**: el estado de pago *no* se guarda duplicado en `inscripcion`; se calcula con `SUM(pago.monto_pagado) WHERE estado='Pagado'` (fuente única de verdad). La vista `v_inscripciones_detalle` ya lo hace por ti.

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

### Evitar errores comunes

- **"Unknown database solfacademy"** → aún no importaste `database/solfacademy.sql`.
- **"Access denied"** → revisa usuario/contraseña en `config/db.php`.
- **"Connection refused"** → el servidor MySQL/MariaDB no está activo.

## Rutas del sitio web (fase 1)

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/` | Homepage |
| GET | `/cursos` | Catálogo con filtros (instrumento, nivel, horario), búsqueda y orden |
| GET | `/cursos/{id}` | Detalle de curso |
| GET | `/instructores/{id}` | Perfil de instructor |
| GET | `/login` | Iniciar sesión (formulario; POST en fase 2) |
| GET | `/registro` | Crear cuenta (formulario; POST en fase 2) |

## Estructura del proyecto

```
config/db.php              → credenciales de la base de datos
app/settings.php           → ajustes globales (incluye 'db')
app/dependencies.php       → definiciones del contenedor (logger, PDO)
app/routes.php             → rutas de la aplicación
database/solfacademy.sql   → esquema + datos de prueba
scripts/comprobar-db.php   → verificación de conexión
src/Controllers/Web/       → controladores del sitio web
src/Data/SampleData.php    → datos de ejemplo (se sustituirá por PDO)
templates/web/             → vistas (layout.php + secciones)
public/css/style.css       → estilos globales
```

Los datos de ejemplo viven en `src/Data/SampleData.php` con la misma forma que las tablas; al implementar la capa de acceso a datos se sustituyen por consultas PDO sin tocar las vistas.

## Licencia

Ver [LICENSE](LICENSE).
