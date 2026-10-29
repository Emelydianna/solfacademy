<?php

declare(strict_types=1);

namespace App\Application\Admin;

use App\Application\Imagen\ImagenCursoStorage;
use DomainException;
use PDO;
use PDOException;
use Psr\Http\Message\UploadedFileInterface;

/** Gestión sobre el esquema existente; nunca borra cursos ni su historial. */
final class GestionAcademicaService
{
    public const ENTIDADES = [
        'categorias' => ['tabla' => 'categoria', 'id' => 'id_categoria', 'singular' => 'categoría'],
        'instrumentos' => ['tabla' => 'instrumento', 'id' => 'id_instrumento', 'singular' => 'instrumento'],
        'cursos' => ['tabla' => 'curso', 'id' => 'id_curso', 'singular' => 'curso'],
    ];
    public const NIVELES = ['Principiante', 'Intermedio', 'Avanzado'];
    public const POR_PAGINA = 10;

    private PDO $pdo;

    /** Se crea perezosamente: solo lo usa el alta/edición de cursos. */
    private ?ImagenCursoStorage $imagenes = null;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public static function texto(array $datos, string $clave, string $defecto = ''): string
    {
        return isset($datos[$clave]) && is_scalar($datos[$clave]) ? trim((string) $datos[$clave]) : $defecto;
    }

    private function entidad(string $seccion): array
    {
        if (!isset(self::ENTIDADES[$seccion])) {
            throw new \InvalidArgumentException('Sección académica desconocida.');
        }
        return self::ENTIDADES[$seccion];
    }

    public function buscar(string $seccion, int $id): ?array
    {
        $entidad = $this->entidad($seccion);
        $stmt = $this->pdo->prepare("SELECT * FROM {$entidad['tabla']} WHERE {$entidad['id']} = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function opciones(): array
    {
        return [
            'categorias' => $this->pdo->query('SELECT id_categoria, nombre FROM categoria ORDER BY nombre')->fetchAll(),
            'instrumentos' => $this->pdo->query('SELECT id_instrumento, id_categoria, nombre FROM instrumento ORDER BY nombre')->fetchAll(),
            'niveles' => self::NIVELES,
        ];
    }

    public function listar(string $seccion, array $filtros): array
    {
        $this->entidad($seccion);
        $q = mb_substr(self::texto($filtros, 'q'), 0, 150);
        $estado = self::texto($filtros, 'estado');
        $nivel = self::texto($filtros, 'nivel');
        $pagina = max(1, (int) self::texto($filtros, 'pagina', '1'));
        $where = [];
        $params = [];
        if ($seccion === 'categorias') {
            $from = 'categoria t';
            $select = 't.*, (SELECT COUNT(*) FROM instrumento i WHERE i.id_categoria = t.id_categoria) AS instrumentos_count';
            $campos = ['t.nombre', 't.descripcion'];
        } elseif ($seccion === 'instrumentos') {
            $from = 'instrumento t JOIN categoria cat ON cat.id_categoria = t.id_categoria';
            $select = 't.*, cat.nombre AS categoria';
            $campos = ['t.nombre', 't.descripcion', 'cat.nombre'];
        } else {
            $from = 'curso t JOIN instrumento ins ON ins.id_instrumento = t.id_instrumento JOIN categoria cat ON cat.id_categoria = ins.id_categoria';
            $select = 't.*, ins.nombre AS instrumento, cat.nombre AS categoria';
            $campos = ['t.nombre', 'ins.nombre', 'cat.nombre'];
            if (in_array($nivel, self::NIVELES, true)) {
                $where[] = 't.nivel = :nivel';
                $params['nivel'] = $nivel;
            } else {
                $nivel = '';
            }
        }
        if ($q !== '') {
            $busqueda = [];
            foreach ($campos as $i => $campo) {
                $busqueda[] = "$campo LIKE :q$i";
                $params['q' . $i] = '%' . $q . '%';
            }
            if ($seccion === 'cursos') {
                $busqueda[] = "EXISTS (SELECT 1 FROM grupo g JOIN instructor i ON i.id_instructor = g.id_instructor
                    WHERE g.id_curso = t.id_curso AND g.estado IN ('activo', 'planeado')
                    AND CONCAT(i.nombre, ' ', i.apellido) LIKE :profesor)";
                $params['profesor'] = '%' . $q . '%';
            }
            $where[] = '(' . implode(' OR ', $busqueda) . ')';
        }
        if ($seccion !== 'categorias' && in_array($estado, ['0', '1'], true)) {
            $where[] = 't.' . ($seccion === 'cursos' ? 'activo' : 'disponible') . ' = :estado';
            $params['estado'] = (int) $estado;
        } else {
            $estado = '';
        }
        $condicion = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM $from$condicion");
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();
        $paginas = max(1, (int) ceil($total / self::POR_PAGINA));
        $pagina = min($pagina, $paginas);
        $offset = ($pagina - 1) * self::POR_PAGINA;
        $stmt = $this->pdo->prepare("SELECT $select FROM $from$condicion ORDER BY t.nombre, t.{$this->entidad($seccion)['id']} LIMIT " . self::POR_PAGINA . " OFFSET $offset");
        $stmt->execute($params);
        $filas = $stmt->fetchAll();
        if ($seccion === 'cursos' && $filas) {
            // Una consulta adicional por página, no por curso. No multiplica
            // filas del listado ni interfiere con el conteo/paginación.
            $ids = array_column($filas, 'id_curso');
            $marcas = implode(', ', array_fill(0, count($ids), '?'));
            $stmt = $this->pdo->prepare("SELECT DISTINCT g.id_curso, CONCAT(i.nombre, ' ', i.apellido) AS nombre
                FROM grupo g JOIN instructor i ON i.id_instructor = g.id_instructor
                WHERE g.id_curso IN ($marcas) AND g.estado IN ('activo', 'planeado') ORDER BY nombre");
            $stmt->execute($ids);
            $nombres = [];
            foreach ($stmt->fetchAll() as $instructor) {
                $nombres[$instructor['id_curso']][] = $instructor['nombre'];
            }
            foreach ($filas as &$fila) {
                $fila['instructores'] = implode(', ', $nombres[$fila['id_curso']] ?? []);
            }
            unset($fila);
        }
        return [
            'filas' => $filas, 'total' => $total, 'pagina' => $pagina,
            'paginas' => $paginas, 'desde' => $total ? $offset + 1 : 0,
            'hasta' => min($offset + self::POR_PAGINA, $total),
            'filtros' => ['q' => $q, 'estado' => $estado, 'nivel' => $nivel],
        ];
    }

    /** Devuelve valores normalizados y errores sin perder lo introducido. */
    public function validar(string $seccion, array $datos, int $id = 0): array
    {
        $this->entidad($seccion);
        $valores = ['nombre' => self::texto($datos, 'nombre'), 'descripcion' => self::texto($datos, 'descripcion')];
        $errores = [];
        $maxNombre = $seccion === 'cursos' ? 150 : 100;
        if ($valores['nombre'] === '' || mb_strlen($valores['nombre']) > $maxNombre) {
            $errores['nombre'] = "Indica un nombre de hasta $maxNombre caracteres.";
        }
        $maxDescripcion = $seccion === 'categorias' ? 255 : 16000;
        if (mb_strlen($valores['descripcion']) > $maxDescripcion) {
            $errores['descripcion'] = "La descripción admite hasta $maxDescripcion caracteres.";
        }
        if ($seccion === 'categorias') {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM categoria WHERE nombre = :nombre AND id_categoria <> :id');
            $stmt->execute(['nombre' => $valores['nombre'], 'id' => $id]);
            if ((int) $stmt->fetchColumn() > 0) {
                $errores['nombre'] = 'Ya existe una categoría con este nombre.';
            }
        } else {
            $relacion = $seccion === 'cursos' ? 'id_instrumento' : 'id_categoria';
            $valores[$relacion] = self::texto($datos, $relacion);
            if (!preg_match('/^[1-9][0-9]{0,9}$/D', $valores[$relacion])
                || !$this->buscar($seccion === 'cursos' ? 'instrumentos' : 'categorias', (int) $valores[$relacion])) {
                $errores[$relacion] = 'Selecciona una opción válida.';
            }
            $precio = $seccion === 'cursos' ? 'precio' : 'precio_alquiler';
            $valores[$precio] = self::texto($datos, $precio);
            if (!preg_match('/^[0-9]{1,6}(\.[0-9]{1,2})?$/D', $valores[$precio])) {
                $errores[$precio] = 'Indica un importe entre 0 y 999999.99, con hasta dos decimales.';
            }
            $valores['disponible'] = self::texto($datos, 'disponible');
            if (!in_array($valores['disponible'], ['0', '1'], true)) {
                $errores['disponible'] = 'Selecciona la disponibilidad.';
            }
        }
        if ($seccion === 'cursos') {
            $valores['nivel'] = self::texto($datos, 'nivel');
            if (!in_array($valores['nivel'], self::NIVELES, true)) {
                $errores['nivel'] = 'Selecciona un nivel válido.';
            }
            foreach (['duracion_semanas', 'max_grupos'] as $campo) {
                $valores[$campo] = self::texto($datos, $campo);
                if ($campo === 'duracion_semanas' && $valores[$campo] === '') {
                    continue;
                }
                if (!preg_match('/^[1-9][0-9]{0,3}$/D', $valores[$campo])) {
                    $errores[$campo] = 'Indica un entero entre 1 y 9999.';
                }
            }
            $valores['activo'] = self::texto($datos, 'activo');
            if (!in_array($valores['activo'], ['0', '1'], true)) {
                $errores['activo'] = 'Selecciona el estado del curso.';
            }
            if ($id > 0) {
                $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM grupo WHERE id_curso = :id AND estado IN ('activo', 'planeado')");
                $stmt->execute(['id' => $id]);
                if ((int) $valores['max_grupos'] < (int) $stmt->fetchColumn()) {
                    $errores['max_grupos'] = 'No puede ser menor que la cantidad de grupos activos o planeados.';
                }
                $actual = $this->buscar('cursos', $id);
                if ($actual && (int) $actual['id_instrumento'] !== (int) $valores['id_instrumento']) {
                    $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM grupo WHERE id_curso = :id');
                    $stmt->execute(['id' => $id]);
                    if ((int) $stmt->fetchColumn() > 0) {
                        $errores['id_instrumento'] = 'No se puede cambiar el instrumento de un curso con grupos registrados.';
                    }
                }
            }
        }
        return ['valores' => $valores, 'errores' => $errores];
    }

    public function guardar(string $seccion, array $datos, int $id = 0, ?UploadedFileInterface $imagen = null): array
    {
        if ($seccion !== 'cursos' || $id < 1) {
            return $this->guardarRegistro($seccion, $datos, $id, $imagen);
        }
        // Comparte el bloqueo de curso con GruposService: el máximo de grupos
        // y el instrumento no pueden validarse sobre un conteo ya obsoleto.
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT id_curso FROM curso WHERE id_curso = :id FOR UPDATE');
            $stmt->execute(['id' => $id]);
            $resultado = $this->guardarRegistro($seccion, $datos, $id, $imagen);
            if ($resultado['errores']) {
                $this->pdo->rollBack();
            } else {
                $this->pdo->commit();
            }
            return $resultado;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    private function guardarRegistro(string $seccion, array $datos, int $id = 0, ?UploadedFileInterface $imagen = null): array
    {
        $entidad = $this->entidad($seccion);
        $actual = $id > 0 ? $this->buscar($seccion, $id) : null;
        if ($id > 0 && $actual === null) {
            return ['valores' => $datos, 'errores' => ['general' => 'El registro ya no existe.'], 'id' => $id];
        }
        $resultado = $this->validar($seccion, $datos, $id);
        if ($resultado['errores']) {
            return $resultado + ['id' => $id];
        }
        $valores = $resultado['valores'];
        if ($seccion === 'cursos' && $valores['duracion_semanas'] === '') {
            $valores['duracion_semanas'] = null;
        }

        // Imagen de referencia (solo cursos): el fichero se escribe después
        // de validar los campos y antes del SQL, para que la columna viaje
        // en la misma escritura. Si no se envía un archivo nuevo, `imagen`
        // no entra en los campos y el UPDATE la conserva intacta.
        $archivoNuevo = null;
        $anterior = $seccion === 'cursos' ? ($actual['imagen'] ?? null) : null;
        if ($seccion === 'cursos' && $imagen instanceof UploadedFileInterface) {
            try {
                $archivoNuevo = ($this->imagenes ??= new ImagenCursoStorage())->guardar($imagen);
            } catch (DomainException $e) {
                $resultado['errores']['imagen'] = $e->getMessage();
                return $resultado + ['id' => $id];
            }
            $valores['imagen'] = $archivoNuevo;
        }

        $campos = array_keys($valores);
        if ($id > 0) {
            $asignaciones = implode(', ', array_map(static fn (string $campo): string => "$campo = :$campo", $campos));
            $sql = "UPDATE {$entidad['tabla']} SET $asignaciones WHERE {$entidad['id']} = :id";
            $valores['id'] = $id;
        } else {
            $sql = "INSERT INTO {$entidad['tabla']} (" . implode(', ', $campos) . ') VALUES (:' . implode(', :', $campos) . ')';
        }
        try {
            $this->pdo->prepare($sql)->execute($valores);
        } catch (PDOException $e) {
            // El SQL falló: no queda un fichero huérfano.
            if ($archivoNuevo !== null) {
                $this->imagenes->borrar($archivoNuevo);
            }
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            $resultado['errores']['general'] = 'No se pudo guardar: nombre duplicado o relación que ya no está disponible. Revisa los datos.';
            return $resultado + ['id' => $id];
        }
        // Sustitución correcta: se retira la imagen anterior del disco.
        if ($archivoNuevo !== null && $anterior !== null && $anterior !== $archivoNuevo) {
            $this->imagenes->borrar($anterior);
        }
        return $resultado + ['id' => $id ?: (int) $this->pdo->lastInsertId()];
    }

    /**
     * Quita la imagen de referencia de un curso (fichero + columna) y la
     * tarjeta vuelve al icono de respaldo.
     *
     * @return array{ok: bool, mensaje: string}
     */
    public function quitarImagen(int $id): array
    {
        $curso = $this->buscar('cursos', $id);
        if ($curso === null) {
            return ['ok' => false, 'mensaje' => 'El curso ya no existe.'];
        }
        $archivo = (string) ($curso['imagen'] ?? '');
        if ($archivo === '') {
            return ['ok' => false, 'mensaje' => 'Este curso no tiene imagen asociada.'];
        }

        $this->pdo->prepare('UPDATE curso SET imagen = NULL WHERE id_curso = :id')
            ->execute(['id' => $id]);
        $this->imagenes ??= new ImagenCursoStorage();
        $this->imagenes->borrar($archivo);

        return ['ok' => true, 'mensaje' => 'Imagen eliminada: el curso muestra de nuevo el icono.'];
    }

    public function eliminar(string $seccion, int $id): array
    {
        $entidad = $this->entidad($seccion);
        if ($seccion === 'cursos') {
            throw new \InvalidArgumentException('Los cursos se desactivan, no se eliminan.');
        }
        if (!$this->buscar($seccion, $id)) {
            return ['tipo' => 'error', 'mensaje' => 'El registro ya no existe.'];
        }
        $sql = $seccion === 'categorias'
            ? 'SELECT COUNT(*) FROM instrumento WHERE id_categoria = :id'
            : 'SELECT (SELECT COUNT(*) FROM curso WHERE id_instrumento = :curso) + (SELECT COUNT(*) FROM instructor_instrumento WHERE id_instrumento = :instructor)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($seccion === 'categorias' ? ['id' => $id] : ['curso' => $id, 'instructor' => $id]);
        if ((int) $stmt->fetchColumn() > 0) {
            return ['tipo' => 'error', 'mensaje' => 'No se puede eliminar: tiene instrumentos, cursos o instructores relacionados.'];
        }
        try {
            $this->pdo->prepare("DELETE FROM {$entidad['tabla']} WHERE {$entidad['id']} = :id")->execute(['id' => $id]);
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            return ['tipo' => 'error', 'mensaje' => 'No se puede eliminar: tiene registros relacionados. Conserva el registro y edítalo si es necesario.'];
        }
        return ['tipo' => 'exito', 'mensaje' => 'Registro eliminado correctamente.'];
    }

    public function cambiarEstadoCurso(int $id, string $activo): array
    {
        if (!in_array($activo, ['0', '1'], true) || !$this->buscar('cursos', $id)) {
            return ['tipo' => 'error', 'mensaje' => 'Curso o estado no válido.'];
        }
        $this->pdo->prepare('UPDATE curso SET activo = :activo WHERE id_curso = :id')->execute(['activo' => (int) $activo, 'id' => $id]);
        return ['tipo' => 'exito', 'mensaje' => $activo === '1' ? 'Curso reactivado.' : 'Curso desactivado. Se conserva su historial.'];
    }
}
