<?php

use Slim\App;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;
use App\Controllers\Web\HomeController;
use App\Application\Actions\User\ListUsersAction;
use App\Application\Actions\User\ViewUserAction;

return function (App $app) {
    // ==========================================
    // 1. RUTAS PÚBLICAS ( Landing)
    // ==========================================
    $app->get('/', [HomeController::class, 'home']);
    
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