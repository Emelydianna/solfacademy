<?php

declare(strict_types=1);

namespace App\Application\Perfil;

use DomainException;
use Psr\Http\Message\UploadedFileInterface;

final class FotoInstructorStorage
{
    private string $directorio;

    public function __construct(?string $directorio = null)
    {
        $this->directorio = $directorio ?? dirname(__DIR__, 3) . '/var/uploads/perfiles';
    }

    public function guardar(UploadedFileInterface $archivo, int $id): string
    {
        if ($archivo->getError() !== UPLOAD_ERR_OK || ($archivo->getSize() ?? 0) > 2 * 1024 * 1024) {
            throw new DomainException('No se pudo recibir la foto o supera los 2 MB.', 3);
        }
        $stream = $archivo->getStream();
        if ($stream->isSeekable()) {
            $stream->rewind();
        }
        $contenido = $stream->read(2 * 1024 * 1024 + 1);
        $info = @getimagesizefromstring($contenido);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contenido);
        $extensiones = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
        if (!$info || !isset($extensiones[$mime]) || $info['mime'] !== $mime || strlen($contenido) > 2 * 1024 * 1024
            || $info[0] > 8000 || $info[1] > 8000 || $info[0] * $info[1] > 40000000) {
            throw new DomainException('Selecciona una imagen JPEG, PNG, GIF o WebP válida, de hasta 2 MB y 8000 px por lado.', 3);
        }
        if (!is_dir($this->directorio) && !@mkdir($this->directorio, 0755, true) && !is_dir($this->directorio)) {
            throw new DomainException('No se pudo preparar el almacenamiento de fotos.', 3);
        }
        $nombre = 'perfil_instructor_' . $id . '_' . bin2hex(random_bytes(16)) . '.' . $extensiones[$mime];
        if (@file_put_contents($this->directorio . '/' . $nombre, $contenido, LOCK_EX) !== strlen($contenido)) {
            @unlink($this->directorio . '/' . $nombre);
            throw new DomainException('No se pudo guardar la foto.', 3);
        }
        return '/perfil/foto/' . $nombre;
    }

    public function ruta(?string $publica, int $id): ?string
    {
        if ($publica === null || !preg_match('~^/perfil/foto/(perfil_instructor_' . $id . '_[a-zA-Z0-9_-]+\.(?:jpg|jpeg|png|gif|webp))$~D', $publica, $m)) {
            return null;
        }
        $ruta = $this->directorio . '/' . $m[1];
        return is_file($ruta) && !is_link($ruta) ? $ruta : null;
    }

    public function borrar(?string $publica, int $id): void
    {
        $ruta = $this->ruta($publica, $id);
        if ($ruta !== null) {
            @unlink($ruta);
        }
    }
}
