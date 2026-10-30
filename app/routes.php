<?php

use Slim\App;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;
use App\Controllers\Web\AuthController;
use App\Controllers\Web\CourseCatalogController;
use App\Controllers\Web\CourseDetailController;
use App\Controllers\Web\HomeController;
use App\Controllers\Web\InstructorProfileController;
use App\Application\Middleware\AuthMiddleware;
use App\Application\Middleware\RequireRole;
use App\Controllers\Estudiante\AsistenciasController;
use App\Controllers\Estudiante\CalendarioController;
use App\Controllers\Estudiante\CalificacionesController;
use App\Controllers\Estudiante\CertificadoController;
use App\Controllers\Estudiante\DashboardController;
use App\Controllers\Estudiante\MisCursosController;
use App\Controllers\Estudiante\MatriculaController;
use App\Controllers\Estudiante\PagosController;
use App\Controllers\Estudiante\PerfilController;
use App\Controllers\Estudiante\ReservaCabinaController;
use App\Controllers\Estudiante\ReservaController;
use App\Controllers\Estudiante\ReservaExtraController;
use App\Controllers\Instructor\DashboardController as InstructorDashboardController;
use App\Controllers\Instructor\GruposController;
use App\Controllers\Instructor\AsistenciasController as InstructorAsistenciasController;
use App\Controllers\Instructor\CalificacionesController as InstructorCalificacionesController;
use App\Controllers\Instructor\ReportesController;
use App\Application\Actions\User\ListUsersAction;
use App\Application\Actions\User\ViewUserAction;

return function (App $app) {
    // ==========================================
    // 1. RUTAS PÚBLICAS ( Landing)
    // ==========================================
    $app->get('/', [HomeController::class, 'home']);

    // Catálogo de cursos 
    $app->get('/cursos', [CourseCatalogController::class, 'catalog']);

    // Detalle de curso 
    $app->get('/cursos/{id}', [CourseDetailController::class, 'detail']);

    // Imagen de referencia del curso (fichero en var/uploads/cursos)
    $app->get('/cursos/{id:[1-9][0-9]*}/imagen', [CourseDetailController::class, 'imagen']);

    // Perfil de instructor 
    $app->get('/instructores/{id}', [InstructorProfileController::class, 'profile']);
    $app->get('/instructores/{id:[1-9][0-9]*}/foto', [InstructorProfileController::class, 'foto']);

    // Login y registro 
    $app->get('/login', [AuthController::class, 'loginForm']);
    $app->post('/login', [AuthController::class, 'login']);
    $app->get('/registro', [AuthController::class, 'registerForm']);
    $app->post('/registro', [AuthController::class, 'registro']);
    $app->get('/logout', [AuthController::class, 'logout']);

    // ==========================================
    // 2. RUTAS DE LA APLICACIÓN 
    //    Portal del Estudiante — requieren sesión
    // ==========================================
    $app->group('/estudiante', function (Group $group) {
        $group->get('/dashboard', [DashboardController::class, 'index']);
        $group->get('/cursos', [MisCursosController::class, 'index']);
        $group->get('/calendario', [CalendarioController::class, 'index']);
        $group->get('/asistencias', [AsistenciasController::class, 'index']);
        $group->get('/calificaciones', [CalificacionesController::class, 'index']);
        $group->get('/calificaciones/boletin', [CalificacionesController::class, 'descargarBoletin']);
        // Certificado en PDF de una inscripción finalizada (dueño solamente)
        $group->get('/certificado/{id:[1-9][0-9]*}', [CertificadoController::class, 'descargar']);
        $group->get('/pagos', [PagosController::class, 'index']);
        $group->post('/pagos/{id:[1-9][0-9]*}/comprobante', [PagosController::class, 'subirComprobante']);
        $group->get('/pagos/comprobante/{archivo}', [PagosController::class, 'verComprobante']);
        $group->get('/perfil', [PerfilController::class, 'index']);
        $group->post('/perfil', [PerfilController::class, 'actualizar']);
        $group->post('/perfil/foto', [PerfilController::class, 'subirFoto']);
        $group->post('/perfil/preferencias', [PerfilController::class, 'guardarPreferencias']);
        $group->post('/perfil/password', [PerfilController::class, 'cambiarPassword']);
        $group->get('/reservas', [ReservaController::class, 'index']);
        $group->get('/reservas/cabinas', [ReservaCabinaController::class, 'crearFormulario']);
        $group->post('/reservas/cabinas', [ReservaCabinaController::class, 'crear']);
        $group->get('/reservas/extras', [ReservaExtraController::class, 'crearFormulario']);
        $group->post('/reservas/extras', [ReservaExtraController::class, 'crear']);
        $group->post('/reservas/{id}/cancelar', [ReservaController::class, 'cancelar']);
        $group->get('/matricular/{grupo:[1-9][0-9]*}', [MatriculaController::class, 'solicitar']);
        $group->post('/matricular/{grupo:[1-9][0-9]*}', [MatriculaController::class, 'confirmar']);
        $group->post('/matriculas/{id:[1-9][0-9]*}/cancelar', [MatriculaController::class, 'cancelar']);
    })->add(new RequireRole('estudiante'));

    $app->options('/{routes:.*}', function (Request $request, Response $response) {
       
        return $response;
    });

    $app->get('/inicio', function (Request $request, Response $response) {
        $response->getBody()->write('Hello world!');
        return $response;
    });

   
    $app->group('/users', function (Group $group) {
        $group->get('', ListUsersAction::class);
        $group->get('/{id}', ViewUserAction::class);
    });


    // Logo de la academia (subido desde /admin/configuracion). Es pública
    // porque el login y la home no tienen sesión: solo se sirve el archivo
    // con el prefijo permitido, nunca una ruta arbitraria.
    $app->get('/marca/logo', function (\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Message\ResponseInterface $response) {
        $ruta = \App\Application\Config\ConfiguracionService::archivoLogo();
        if ($ruta === null || !is_file($ruta)) {
            return $response->withStatus(404);
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo !== false ? (finfo_file($finfo, $ruta) ?: 'image/png') : 'image/png';
        if ($finfo !== false) {
            finfo_close($finfo);
        }

        return $response
            ->withHeader('Content-Type', (string) $mime)
            ->withHeader('Cache-Control', 'public, max-age=3600')
            ->withBody(new \Slim\Psr7\Stream(fopen($ruta, 'rb')));
    });

    // Servir foto de perfil desde var/uploads (no rastreable en git)
    $app->get('/perfil/foto/{archivo}', function (\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Message\ResponseInterface $response, array $args) {
        $archivo = basename($args['archivo'] ?? '');
        $ruta = __DIR__ . '/../var/uploads/perfiles/' . $archivo;
        if (!is_file($ruta)) {
            return $response->withStatus(404);
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $ruta) ?: 'application/octet-stream';
        finfo_close($finfo);
        $stream = new \Slim\Psr7\Stream(fopen($ruta, 'rb'));
        return $response
            ->withHeader('Content-Type', $mime)
            ->withHeader('Cache-Control', 'private, max-age=3600')
            ->withBody($stream);
    })->add(AuthMiddleware::class);


    // ==========================================
    // 3. RUTAS INSTRUCTOR
    // ==========================================
    $app->group('/instructor', function (Group $group) {
        $group->get('/dashboard', [InstructorDashboardController::class, 'index']);
        $group->get('/perfil', [\App\Controllers\Instructor\PerfilController::class, 'index']);
        $group->post('/perfil', [\App\Controllers\Instructor\PerfilController::class, 'actualizar']);
        $group->post('/perfil/foto', [\App\Controllers\Instructor\PerfilController::class, 'subirFoto']);
        $group->post('/perfil/password', [\App\Controllers\Instructor\PerfilController::class, 'cambiarPassword']);
        $group->get('/grupos', [GruposController::class, 'index']);
        $group->get('/grupos/{id}', [GruposController::class, 'ver']);
        $group->get('/grupos/{id}/exportar', [GruposController::class, 'exportar']);
        $group->get('/asistencias', [InstructorAsistenciasController::class, 'index']);
        $group->post('/asistencias/guardar', [InstructorAsistenciasController::class, 'guardar']);
        $group->get('/calificaciones', [InstructorCalificacionesController::class, 'index']);
        $group->post('/calificaciones/crear', [InstructorCalificacionesController::class, 'crear']);
        $group->post('/calificaciones/guardar', [InstructorCalificacionesController::class, 'guardar']);
        $group->get('/reportes', [ReportesController::class, 'index']);
        $group->get('/alumnos/{id}/perfil', [\App\Controllers\Instructor\AlumnosController::class, 'perfil']);
    })->add(new RequireRole('instructor'));

    // ==========================================
    // 4. RUTAS ADMINISTRACIÓN 
    // ==========================================
    $app->group('/admin', function (Group $group) {
        $group->get('/dashboard', [\App\Controllers\Admin\DashboardController::class, 'index']);
        $group->get('/instructores', [\App\Controllers\Admin\InstructoresController::class, 'index']);
        $group->get('/instructores/nuevo', [\App\Controllers\Admin\InstructoresController::class, 'formulario']);
        $group->post('/instructores', [\App\Controllers\Admin\InstructoresController::class, 'guardar']);
        $group->get('/instructores/{id:[1-9][0-9]*}', [\App\Controllers\Admin\InstructoresController::class, 'ver']);
        $group->get('/instructores/{id:[1-9][0-9]*}/editar', [\App\Controllers\Admin\InstructoresController::class, 'formulario']);
        $group->post('/instructores/{id:[1-9][0-9]*}', [\App\Controllers\Admin\InstructoresController::class, 'guardar']);
        $group->post('/instructores/{id:[1-9][0-9]*}/estado', [\App\Controllers\Admin\InstructoresController::class, 'estado']);
        $group->get('/aulas', [\App\Controllers\Admin\AulasController::class, 'index']);
        $group->get('/aulas/nuevo', [\App\Controllers\Admin\AulasController::class, 'formulario']);
        $group->post('/aulas', [\App\Controllers\Admin\AulasController::class, 'guardar']);
        $group->get('/aulas/{id:[1-9][0-9]*}', [\App\Controllers\Admin\AulasController::class, 'ver']);
        $group->get('/aulas/{id:[1-9][0-9]*}/editar', [\App\Controllers\Admin\AulasController::class, 'formulario']);
        $group->post('/aulas/{id:[1-9][0-9]*}', [\App\Controllers\Admin\AulasController::class, 'guardar']);
        $group->post('/aulas/{id:[1-9][0-9]*}/estado', [\App\Controllers\Admin\AulasController::class, 'estado']);
        $group->get('/requisitos', [\App\Controllers\Admin\RequisitosController::class, 'index']);
        $group->get('/requisitos/{id:[1-9][0-9]*}', [\App\Controllers\Admin\RequisitosController::class, 'formulario']);
        $group->post('/requisitos/{id:[1-9][0-9]*}', [\App\Controllers\Admin\RequisitosController::class, 'guardar']);
        $group->get('/matriculas', [\App\Controllers\Admin\MatriculasController::class, 'index']);
        $group->get('/matriculas/{id:[1-9][0-9]*}', [\App\Controllers\Admin\MatriculasController::class, 'detalle']);
        $group->post('/matriculas/{id:[1-9][0-9]*}/aprobar', [\App\Controllers\Admin\MatriculasController::class, 'aprobar']);
        $group->post('/matriculas/{id:[1-9][0-9]*}/rechazar', [\App\Controllers\Admin\MatriculasController::class, 'rechazar']);
        $group->get('/pagos', [\App\Controllers\Admin\PagosController::class, 'index']);
        $group->get('/pagos/comprobante/{archivo}', [\App\Controllers\Admin\PagosController::class, 'comprobante']);
        $group->get('/pagos/{id:[1-9][0-9]*}', [\App\Controllers\Admin\PagosController::class, 'detalle']);
        $group->post('/pagos/{id:[1-9][0-9]*}/aprobar', [\App\Controllers\Admin\PagosController::class, 'aprobar']);
        $group->post('/pagos/{id:[1-9][0-9]*}/rechazar', [\App\Controllers\Admin\PagosController::class, 'rechazar']);
        $group->get('/descuentos', [\App\Controllers\Admin\DescuentosController::class, 'index']);
        $group->get('/descuentos/nuevo', [\App\Controllers\Admin\DescuentosController::class, 'nuevo']);
        $group->post('/descuentos', [\App\Controllers\Admin\DescuentosController::class, 'guardarNuevo']);
        $group->get('/descuentos/{id:[1-9][0-9]*}', [\App\Controllers\Admin\DescuentosController::class, 'formulario']);
        $group->post('/descuentos/{id:[1-9][0-9]*}', [\App\Controllers\Admin\DescuentosController::class, 'guardar']);
        $group->post('/descuentos/{id:[1-9][0-9]*}/estado', [\App\Controllers\Admin\DescuentosController::class, 'estado']);
        $group->get('/usuarios', [\App\Controllers\Admin\UsuariosController::class, 'index']);
        $group->get('/usuarios/nuevo', [\App\Controllers\Admin\UsuariosController::class, 'nuevo']);
        $group->post('/usuarios', [\App\Controllers\Admin\UsuariosController::class, 'guardar']);
        $group->get('/usuarios/{id:[1-9][0-9]*}', [\App\Controllers\Admin\UsuariosController::class, 'detalle']);
        $group->post('/usuarios/{id:[1-9][0-9]*}/bloqueo', [\App\Controllers\Admin\UsuariosController::class, 'bloqueo']);
        $group->get('/reportes', [\App\Controllers\Admin\ReportesController::class, 'index']);
        $group->get('/reportes/exportar', [\App\Controllers\Admin\ReportesController::class, 'exportar']);
        $group->get('/configuracion', [\App\Controllers\Admin\ConfiguracionController::class, 'index']);
        $group->post('/configuracion', [\App\Controllers\Admin\ConfiguracionController::class, 'guardar']);
        $group->post('/configuracion/logo', [\App\Controllers\Admin\ConfiguracionController::class, 'logo']);
        $group->post('/configuracion/logo/quitar', [\App\Controllers\Admin\ConfiguracionController::class, 'quitarLogo']);
        $group->get('/grupos', [\App\Controllers\Admin\GruposController::class, 'index']);
        $group->post('/grupos', [\App\Controllers\Admin\GruposController::class, 'guardar']);
        $group->get('/grupos/{id:[1-9][0-9]*}', [\App\Controllers\Admin\GruposController::class, 'ver']);
        $group->post('/grupos/{id:[1-9][0-9]*}', [\App\Controllers\Admin\GruposController::class, 'guardar']);
        $group->get('/estudiantes', [\App\Controllers\Admin\EstudiantesController::class, 'index']);
        $group->get('/estudiantes/nuevo', [\App\Controllers\Admin\EstudiantesController::class, 'formulario']);
        $group->post('/estudiantes', [\App\Controllers\Admin\EstudiantesController::class, 'guardar']);
        $group->get('/estudiantes/{id:[1-9][0-9]*}', [\App\Controllers\Admin\EstudiantesController::class, 'ver']);
        $group->get('/estudiantes/{id:[1-9][0-9]*}/editar', [\App\Controllers\Admin\EstudiantesController::class, 'formulario']);
        $group->post('/estudiantes/{id:[1-9][0-9]*}', [\App\Controllers\Admin\EstudiantesController::class, 'guardar']);
        $group->post('/estudiantes/{id:[1-9][0-9]*}/estado', [\App\Controllers\Admin\EstudiantesController::class, 'estado']);
        $group->get('/{seccion:categorias|instrumentos|cursos}', [\App\Controllers\Admin\GestionAcademicaController::class, 'index']);
        $group->post('/{seccion:categorias|instrumentos|cursos}', [\App\Controllers\Admin\GestionAcademicaController::class, 'guardar']);
        $group->post('/{seccion:categorias|instrumentos|cursos}/{id:[1-9][0-9]*}', [\App\Controllers\Admin\GestionAcademicaController::class, 'guardar']);
        $group->post('/{seccion:categorias|instrumentos}/{id:[1-9][0-9]*}/eliminar', [\App\Controllers\Admin\GestionAcademicaController::class, 'eliminar']);
        $group->post('/cursos/{id:[1-9][0-9]*}/estado', [\App\Controllers\Admin\GestionAcademicaController::class, 'estado']);
        $group->post('/cursos/{id:[1-9][0-9]*}/imagen/quitar', [\App\Controllers\Admin\GestionAcademicaController::class, 'quitarImagen']);
    })->add(new RequireRole('admin'));
};
