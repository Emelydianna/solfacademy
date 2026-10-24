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
use App\Controllers\Estudiante\AsistenciasController;
use App\Controllers\Estudiante\CalendarioController;
use App\Controllers\Estudiante\CalificacionesController;
use App\Controllers\Estudiante\DashboardController;
use App\Controllers\Estudiante\MisCursosController;
use App\Controllers\Estudiante\PagosController;
use App\Controllers\Estudiante\PerfilController;
use App\Controllers\Estudiante\ReservaCabinaController;
use App\Controllers\Estudiante\ReservaController;
use App\Controllers\Estudiante\ReservaExtraController;
use App\Application\Actions\User\ListUsersAction;
use App\Application\Actions\User\ViewUserAction;

return function (App $app) {
    // ==========================================
    // 1. RUTAS PÚBLICAS ( Landing)
    // ==========================================
    $app->get('/', [HomeController::class, 'home']);

    // Catálogo de cursos (pantalla 2 del prototipo)
    $app->get('/cursos', [CourseCatalogController::class, 'catalog']);

    // Detalle de curso (pantalla 3 del prototipo)
    $app->get('/cursos/{id}', [CourseDetailController::class, 'detail']);

    // Perfil de instructor (pantalla 4 del prototipo)
    $app->get('/instructores/{id}', [InstructorProfileController::class, 'profile']);

    // Login y registro (pantalla 5 del prototipo + autenticación real)
    $app->get('/login', [AuthController::class, 'loginForm']);
    $app->post('/login', [AuthController::class, 'login']);
    $app->get('/registro', [AuthController::class, 'registerForm']);
    $app->post('/registro', [AuthController::class, 'registro']);
    $app->get('/logout', [AuthController::class, 'logout']);

    // ==========================================
    // 2. RUTAS DE LA APLICACIÓN (Fase 2)
    //    Portal del Estudiante — requieren sesión
    // ==========================================
    $app->group('/estudiante', function (Group $group) {
        $group->get('/dashboard', [DashboardController::class, 'index']);
        $group->get('/cursos', [MisCursosController::class, 'index']);
        $group->get('/calendario', [CalendarioController::class, 'index']);
        $group->get('/asistencias', [AsistenciasController::class, 'index']);
        $group->get('/calificaciones', [CalificacionesController::class, 'index']);
        $group->get('/calificaciones/boletin', [CalificacionesController::class, 'descargarBoletin']);
        $group->get('/pagos', [PagosController::class, 'index']);
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
    })->add(AuthMiddleware::class);

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
};
