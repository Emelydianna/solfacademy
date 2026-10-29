<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Config\ConfiguracionService;
use PDO;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Views\PhpRenderer;

/**
 * Expone a todas las plantillas tres atributos:
 *
 *  - `usuarioSesion`: usuario de la sesión (lo usa el layout para la navbar).
 *  - `fotoPerfil`: ruta pública de la foto del usuario de sesión, o null.
 *    Se resuelve aquí una sola vez por petición para que el layout la muestre
 *    en el sidebar y en el topbar de cualquier pantalla del portal, sin que
 *    cada controlador tenga que pasarla.
 *  - `config`: datos de la academia (marca, lema, contacto, redes, pie y
 *    nota de PDF) con `logo` ya traducido a su ruta pública. Si la tabla
 *    aún no existe, se usan los valores de fábrica: ninguna página se rompe.
 */
class ViewDataMiddleware implements Middleware
{
    private PhpRenderer $view;
    private ContainerInterface $container;

    public function __construct(PhpRenderer $view, ContainerInterface $container)
    {
        $this->view = $view;
        $this->container = $container;
    }

    /**
     * {@inheritdoc}
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $usuario = $_SESSION['usuario'] ?? null;

        $this->view->addAttribute('usuarioSesion', $usuario);
        $this->view->addAttribute('fotoPerfil', $this->fotoDelUsuario($usuario));
        $this->view->addAttribute('config', $this->configuracion());

        return $handler->handle($request);
    }

    /**
     * Configuración de la academia para esta petición. Cualquier fallo
     * (tabla sin migrar, base de datos caída) deja los valores de fábrica.
     *
     * @return array<string, mixed>
     */
    private function configuracion(): array
    {
        try {
            $config = (new ConfiguracionService($this->container->get(PDO::class)))->obtener();
        } catch (\Throwable $e) {
            $config = ConfiguracionService::porDefecto();
        }

        $config['logo'] = ConfiguracionService::logoPublico(
            isset($config['logo_url']) ? (string) $config['logo_url'] : null
        );

        return $config;
    }

    /**
     * Foto del rol correspondiente. `id_referencia` es la llave de la sesión:
     * id_instructor o id_estudiante; el admin no tiene, así que se quedan las
     * iniciales.
     *
     * El PDO se pide al contenedor solo cuando hay referencia: las páginas
     * públicas no abren una conexión que no van a usar. Cualquier fallo deja
     * la foto en null (el layout muestra las iniciales) en vez de romper la
     * página.
     */
    private function fotoDelUsuario(mixed $usuario): ?string
    {
        if (!is_array($usuario)) {
            return null;
        }

        $id = (int) ($usuario['id_referencia'] ?? 0);
        if ($id <= 0) {
            return null;
        }

        $consulta = match ((string) ($usuario['rol'] ?? '')) {
            'instructor' => 'SELECT foto FROM instructor WHERE id_instructor = :id',
            'estudiante' => 'SELECT ruta FROM estudiante_foto WHERE id_estudiante = :id',
            default      => null,
        };

        if ($consulta === null) {
            return null;
        }

        try {
            $stmt = $this->container->get(PDO::class)->prepare($consulta);
            $stmt->execute(['id' => $id]);
            $ruta = (string) ($stmt->fetchColumn() ?: '');
        } catch (\Throwable $e) {
            return null;
        }

        return $ruta !== '' ? $ruta : null;
    }
}
