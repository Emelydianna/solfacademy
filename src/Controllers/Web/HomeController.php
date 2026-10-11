<?php

namespace App\Controllers\Web;

use App\Data\SampleData;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

class HomeController
{
    private PhpRenderer $view;

    public function __construct(PhpRenderer $view)
    {
        $this->view = $view;
    }

    public function home(Request $request, Response $response): Response
    {
        $this->view->setLayout('web/layout.php');

        return $this->view->render($response, 'web/home.php', [
            'tituloPagina' => 'SolfAcademy — Aprende de los mejores músicos',
            'paginaActiva' => 'inicio',
            // Datos de ejemplo con la forma de las tablas del esquema
            'categorias' => SampleData::categorias(),
            'instructores' => SampleData::instructores(),
            'testimonios' => SampleData::testimonios(),
        ]);
    }
}