<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

/**
 * Rellena el body de las peticiones multipart desde $_POST.
 *
 * Slim solo registra parsers para JSON y urlencoded: ante
 * `multipart/form-data` (formularios con subida de archivos) deja
 * getParsedBody() vacío, mientras que PHP sí ha rellenado $_POST con los
 * campos. Sin esto, cualquier formulario con enctype perdería todos sus
 * campos y fallaría en la validación.
 *
 * Va antes que CsrfMiddleware (LIFO en app/middleware.php) para que la
 * validación del token y los controladores vean el body completo.
 */
final class MultipartBodyMiddleware implements Middleware
{
    /**
     * {@inheritdoc}
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        $cabecera = (string) $request->getHeaderLine('Content-Type');
        $tipo = strtolower(trim(explode(';', $cabecera)[0]));

        if ($tipo === 'multipart/form-data' && empty($request->getParsedBody())) {
            $request = $request->withParsedBody($_POST);
        }

        return $handler->handle($request);
    }
}
