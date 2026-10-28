<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Security\Csrf;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;
use Slim\Psr7\Stream;

/**
 * Bloquea las peticiones POST/PUT/PATCH/DELETE sin token CSRF válido.
 *
 * Cubre las rutas mutantes de la app (login, registro, perfiles, reservas,
 * asistencias, calificaciones, envío de comprobantes y formularios del panel)
 * sin tener que acordarse cada controlador de validar nada: si el token falta,
 * no coincide o pertenece a otra sesión, responde 403.
 *
 * Los métodos seguros (GET/HEAD/OPTIONS) pasan sin más.
 */
final class CsrfMiddleware implements Middleware
{
    private const METODOS_SEGUROS = ['GET', 'HEAD', 'OPTIONS', 'TRACE'];

    /** @var list<string> */
    private const METODOS_PROTEGIDOS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * {@inheritdoc}
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        $metodo = strtoupper($request->getMethod());

        if (in_array($metodo, self::METODOS_SEGUROS, true) || !in_array($metodo, self::METODOS_PROTEGIDOS, true)) {
            return $handler->handle($request);
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (Csrf::esValido($this->tokenDeLaPeticion($request))) {
            return $handler->handle($request);
        }

        return $this->rechazar($request);
    }

    /**
     * El token viaja como campo `_csrf`. BodyParsingMiddleware y
     * ServerRequestCreator rellenan getParsedBody() con `$_POST` para
     * urlencoded; en multipart (subida de fotos) puede venir vacío, así
     * que se cae a `$_POST`, que PHP siempre rellena.
     */
    private function tokenDeLaPeticion(Request $request): mixed
    {
        $cuerpo = $request->getParsedBody();

        if (is_array($cuerpo) && isset($cuerpo['_csrf'])) {
            return $cuerpo['_csrf'];
        }

        return $_POST['_csrf'] ?? null;
    }

    private function rechazar(Request $request): Response
    {
        $destino = $this->mismoOrigen((string) $request->getHeaderLine('Referer')) ? $request->getHeaderLine('Referer') : '/';

        $html = '<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Solicitud no válida — SolfAcademy</title>
<style>
  body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center;
         background:#0b0f19; color:#e5e7eb; font-family:system-ui,-apple-system,sans-serif; padding:1rem; }
  .caja { max-width:520px; width:100%; padding:2rem; background:#131b2e;
          border:1px solid #1f293d; border-radius:1rem; }
  h1 { margin:0 0 .75rem; color:#fff; font-size:1.15rem; }
  p { margin:0; color:#94a3b8; font-size:.95rem; line-height:1.6; }
  a { display:inline-block; margin-top:1.25rem; padding:.6rem 1.3rem; background:#ffc107;
      color:#0b0f19; text-decoration:none; border-radius:.5rem; font-weight:600; }
  a:hover { background:#f0ad4e; }
</style>
</head>
<body>
  <div class="caja">
    <h1>Solicitud no válida</h1>
    <p>El formulario caducó, se envió desde otra pestaña o fue originado fuera de la academia.
       Vuelve atrás y recarga la página para obtener un token nuevo.</p>
    <a href="' . htmlspecialchars($destino, ENT_QUOTES) . '">Volver</a>
  </div>
</body>
</html>';

        $stream = new Stream(fopen('php://temp', 'r+'));
        $stream->write($html);
        $stream->rewind();

        return (new SlimResponse())
            ->withStatus(403)
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withHeader('Cache-Control', 'no-store')
            ->withBody($stream);
    }

    /** Un Referer de otro dominio no se usa como destino de vuelta. */
    private function mismoOrigen(string $referencia): bool
    {
        if ($referencia === '') {
            return false;
        }

        $host = (string) parse_url($referencia, PHP_URL_HOST);

        return $host !== '' && $host === (string) ($_SERVER['HTTP_HOST'] ?? '');
    }
}
