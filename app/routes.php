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

    // Login y registro (pantalla 5 del prototipo).
    // POST /login y POST /registro con sesión: fase 2 de la aplicación.
    $app->get('/login', [AuthController::class, 'loginForm']);
    $app->get('/registro', [AuthController::class, 'registerForm']);
    
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
};