<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Application\Catalogo\CatalogoService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

class CourseCatalogController
{
    private PhpRenderer $view;
    private CatalogoService $service;

    public function __construct(PhpRenderer $view, CatalogoService $service)
    {
        $this->view = $view;
        $this->service = $service;
    }

    public function catalog(Request $request, Response $response): Response
    {
        $this->view->setLayout('web/layout.php');
        return $this->view->render($response, 'web/cursos/catalogo.php', array_merge(
            $this->service->catalogo($request->getQueryParams()),
            ['tituloPagina' => 'Catálogo de Cursos — SolfAcademy', 'paginaActiva' => 'cursos']
        ));
    }
}
