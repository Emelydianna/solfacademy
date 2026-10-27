<?php

namespace App\Controllers\Web;

use App\Data\SampleData;
use App\Application\Catalogo\CatalogoService;
use App\Application\Catalogo\InstructoresPublicosService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

class HomeController
{
    private PhpRenderer $view;
    private CatalogoService $catalogo;
    private InstructoresPublicosService $instructores;

    public function __construct(PhpRenderer $view, CatalogoService $catalogo, InstructoresPublicosService $instructores)
    {
        $this->view = $view;
        $this->catalogo = $catalogo;
        $this->instructores = $instructores;
    }

    public function home(Request $request, Response $response): Response
    {
        $this->view->setLayout('web/layout.php');

        return $this->view->render($response, 'web/home.php', [
            'tituloPagina' => 'SolfAcademy — Aprende de los mejores músicos',
            'paginaActiva' => 'inicio',
            'categorias' => $this->catalogo->categorias(),
            'instructores' => $this->instructores->listar(),
            // Los testimonios conservan la maqueta hasta su módulo.
            'testimonios' => SampleData::testimonios(),
        ]);
    }
}
