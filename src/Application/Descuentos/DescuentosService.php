<?php

declare(strict_types=1);

namespace App\Application\Descuentos;

use App\Application\Admin\GestionAcademicaService;
use App\Application\Admin\ListadoAdmin;
use DomainException;
use PDO;
use PDOException;

/**
 * Códigos de descuento (tabla `descuento`): solo administración los crea
 * desde /admin/descuentos.
 *
 * El descuento se aplica al solicitar la matrícula: el precio que se
 * congela en `inscripcion.precio_inscripcion` ya sale con el descuento y
 * `inscripcion.id_descuento` guarda qué cupón se usó. Por eso pagos,
 * la vista v_inscripciones_detalle y el resto del cálculo de saldos no
 * cambian en absoluto.
 */
final class DescuentosService
{
    public const USOS_SIN_LIMITE = -1;

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Listado paginado (10 por página) con búsqueda y filtro de estado.
     *
     * @return array{filas: array<int, array<string, mixed>>, total: int, pagina: int, paginas: int, desde: int, hasta: int, filtros: array{q: string, estado: string}}
     */
    public function listar(array $f): array
    {
        $q = mb_substr(GestionAcademicaService::texto($f, 'q'), 0, 150);
        $estado = GestionAcademicaService::texto($f, 'estado');

        $where = [];
        $params = [];
        if ($q !== '') {
            $where[] = '(d.codigo LIKE :codigo OR d.descripcion LIKE :desc OR c.nombre LIKE :curso)';
            foreach (['codigo', 'desc', 'curso'] as $clave) {
                $params[$clave] = '%' . $q . '%';
            }
        }
        if (in_array($estado, ['1', '0'], true)) {
            $where[] = 'd.activo = :activo';
            $params['activo'] = (int) $estado;
        } else {
            $estado = '';
        }

        $filas = ListadoAdmin::pagina(
            $this->pdo,
            'd.*, COALESCE(c.nombre, \'Todos los cursos\') AS curso,
                (CURDATE() BETWEEN d.fecha_inicio AND d.fecha_fin) AS vigente',
            'descuento d LEFT JOIN curso c ON c.id_curso = d.id_curso',
            $where ? ' WHERE ' . implode(' AND ', $where) : '',
            $params,
            'd.activo DESC, d.fecha_fin DESC, d.id_descuento DESC',
            $f
        );

        return $filas + ['filtros' => ['q' => $q, 'estado' => $estado]];
    }

    /** Cursos reales para el selector del formulario. */
    public function cursos(): array
    {
        return $this->pdo->query(
            'SELECT c.id_curso, c.nombre, c.activo
             FROM curso c ORDER BY c.nombre, c.id_curso'
        )->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function buscar(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT d.*, COALESCE(c.nombre, \'Todos los cursos\') AS curso
             FROM descuento d LEFT JOIN curso c ON c.id_curso = d.id_curso
             WHERE d.id_descuento = :id'
        );
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch();
        return $fila ?: null;
    }

    /**
     * Sugerencia de código sin usar para el formulario de creación.
     * El administrador puede reemplazarla por la suya.
     */
    public function sugerirCodigo(): string
    {
        $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        for ($intento = 0; $intento < 20; $intento++) {
            $candidato = 'PROMO-';
            for ($i = 0; $i < 6; $i++) {
                $candidato .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
            }
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM descuento WHERE codigo = :codigo');
            $stmt->execute(['codigo' => $candidato]);
            if ((int) $stmt->fetchColumn() === 0) {
                return $candidato;
            }
        }
        return 'PROMO-' . strtoupper(bin2hex(random_bytes(3)));
    }

    /**
     * Crea o actualiza un código. Devuelve valores y errores igual que el
     * resto de formularios del panel (Requisitos, Aulas, Grupos…).
     *
     * @param array<string, mixed> $datos
     * @return array{valores: array<string, string>, errores: array<string, string>, id: int}
     */
    public function guardar(array $datos, int $id = 0): array
    {
        $v = [];
        foreach (['codigo', 'descripcion', 'tipo', 'porcentaje', 'monto_fijo', 'fecha_inicio', 'fecha_fin', 'id_curso', 'usos_disponibles'] as $campo) {
            $v[$campo] = GestionAcademicaService::texto($datos, $campo);
        }
        $v['sin_limite'] = isset($datos['sin_limite']) && $datos['sin_limite'] === '1' ? '1' : '0';
        $v['activo'] = isset($datos['activo']) && $datos['activo'] === '1' ? '1' : '0';
        $v['codigo'] = strtoupper(trim($v['codigo']));

        $errores = [];
        if (preg_match('/^[A-Z0-9][A-Z0-9-]{2,29}$/D', $v['codigo']) !== 1) {
            $errores['codigo'] = 'El código debe tener entre 3 y 30 caracteres (letras, números o guion).';
        }
        if (mb_strlen($v['descripcion']) > 200) {
            $errores['descripcion'] = 'La descripción no puede superar 200 caracteres.';
        }

        $esPorcentaje = $v['tipo'] === 'porcentaje';
        $porcentaje = null;
        $montoFijo = null;
        if ($v['tipo'] !== 'porcentaje' && $v['tipo'] !== 'monto') {
            $errores['tipo'] = 'Elige porcentaje o monto fijo.';
        } elseif ($esPorcentaje) {
            if (!is_numeric($v['porcentaje']) || (float) $v['porcentaje'] <= 0 || (float) $v['porcentaje'] > 100) {
                $errores['porcentaje'] = 'El porcentaje debe estar entre 0.01 y 100.';
            } else {
                $porcentaje = round((float) $v['porcentaje'], 2);
            }
        } else {
            if (!is_numeric($v['monto_fijo']) || (float) $v['monto_fijo'] <= 0) {
                $errores['monto_fijo'] = 'El monto fijo debe ser mayor a cero.';
            } else {
                $montoFijo = round((float) $v['monto_fijo'], 2);
            }
        }

        foreach (['fecha_inicio' => 'Fecha de inicio', 'fecha_fin' => 'Fecha de fin'] as $campo => $etiqueta) {
            if (!ListadoAdmin::fecha($v[$campo])) {
                $errores[$campo] = "$etiqueta debe ser una fecha válida (AAAA-MM-DD).";
            }
        }
        if (!$errores && $v['fecha_fin'] < $v['fecha_inicio']) {
            $errores['fecha_fin'] = 'La fecha de fin no puede ser anterior a la de inicio.';
        }

        $usos = self::USOS_SIN_LIMITE;
        if ($v['sin_limite'] !== '1') {
            if (!preg_match('/^[1-9][0-9]*$/D', $v['usos_disponibles'])) {
                $errores['usos_disponibles'] = 'Indica un número de usos mayor que cero o marca "sin límite".';
            } else {
                $usos = (int) $v['usos_disponibles'];
            }
        }

        $idCurso = null;
        if ($v['id_curso'] !== '' && $v['id_curso'] !== '0') {
            if (!ctype_digit($v['id_curso'])) {
                $errores['id_curso'] = 'Selecciona un curso válido.';
            } else {
                $idCurso = (int) $v['id_curso'];
            }
        }

        $resultado = ['valores' => $v, 'errores' => $errores, 'id' => $id];
        if ($errores) {
            return $resultado;
        }

        try {
            $this->pdo->beginTransaction();

            if ($id > 0) {
                $stmt = $this->pdo->prepare('SELECT id_descuento, usos_realizados FROM descuento WHERE id_descuento = :id FOR UPDATE');
                $stmt->execute(['id' => $id]);
                $actual = $stmt->fetch();
                if (!$actual) {
                    throw new DomainException('El código ya no existe.');
                }
                if ($usos !== self::USOS_SIN_LIMITE && $usos < (int) $actual['usos_realizados']) {
                    throw new DomainException('No puedes bajar los usos por debajo de los ya realizados (' . (int) $actual['usos_realizados'] . ').');
                }
            }

            $stmt = $this->pdo->prepare('SELECT id_descuento FROM descuento WHERE codigo = :codigo FOR UPDATE');
            $stmt->execute(['codigo' => $v['codigo']]);
            $repetido = (int) ($stmt->fetchColumn() ?: 0);
            if ($repetido !== 0 && $repetido !== $id) {
                throw new DomainException('Ya existe un código con el nombre "' . $v['codigo'] . '".');
            }

            $campos = [
                'codigo'            => $v['codigo'],
                'descripcion'       => $v['descripcion'] !== '' ? $v['descripcion'] : null,
                'porcentaje'        => $porcentaje,
                'monto_fijo'        => $montoFijo,
                'fecha_inicio'      => $v['fecha_inicio'],
                'fecha_fin'         => $v['fecha_fin'],
                'usos_disponibles'  => $usos,
                'id_curso'          => $idCurso,
                'activo'            => (int) $v['activo'],
            ];

            if ($id > 0) {
                $parametros = $campos + ['id_set' => $id];
                $sql = 'UPDATE descuento SET ' . implode(', ', array_map(static fn (string $c): string => "$c = :$c", array_keys($campos))) . ' WHERE id_descuento = :id_set';
            } else {
                $parametros = $campos;
                $sql = 'INSERT INTO descuento (codigo, descripcion, porcentaje, monto_fijo, fecha_inicio, fecha_fin,
                                                usos_disponibles, id_curso, activo)
                        VALUES (:codigo, :descripcion, :porcentaje, :monto_fijo, :fecha_inicio, :fecha_fin,
                                :usos_disponibles, :id_curso, :activo)';
            }
            $this->pdo->prepare($sql)->execute($parametros);
            $id = $id > 0 ? $id : (int) $this->pdo->lastInsertId();

            $this->pdo->commit();
            $resultado['id'] = $id;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($e instanceof DomainException) {
                $resultado['errores']['general'] = $e->getMessage();
            } elseif ($e instanceof PDOException && (string) $e->getCode() === '23000') {
                $resultado['errores']['general'] = 'Ya existe un código con ese nombre.';
            } elseif ($e instanceof PDOException && (string) $e->getCode() === '40001') {
                $resultado['errores']['general'] = 'Hubo un cambio simultáneo. Recarga e inténtalo de nuevo.';
            } else {
                throw $e;
            }
        }

        return $resultado;
    }

    /**
     * Activa o desactiva un código. No se borra nunca: `inscripcion`
     * conserva la referencia al cupón utilizado.
     *
     * @return array{tipo: string, mensaje: string}
     */
    public function cambiarEstado(int $id, int $activo): array
    {
        if (!in_array($activo, [0, 1], true)) {
            return ['tipo' => 'error', 'mensaje' => 'Estado no válido.'];
        }
        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare('SELECT activo FROM descuento WHERE id_descuento = :id FOR UPDATE');
            $stmt->execute(['id' => $id]);
            $actual = $stmt->fetch();
            if (!$actual) {
                $this->pdo->rollBack();
                return ['tipo' => 'error', 'mensaje' => 'El código ya no existe.'];
            }
            $this->pdo->prepare('UPDATE descuento SET activo = :activo WHERE id_descuento = :id')
                ->execute(['activo' => $activo, 'id' => $id]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($e instanceof PDOException && (string) $e->getCode() === '40001') {
                return ['tipo' => 'error', 'mensaje' => 'Hubo un cambio simultáneo. Recarga e inténtalo de nuevo.'];
            }
            throw $e;
        }

        return [
            'tipo'    => 'exito',
            'mensaje' => $activo === 1
                ? 'Código activado. Ya puede usarse en las matrículas.'
                : 'Código desactivado. No se pueden usar nuevos, los ya aplicados se conservan.',
        ];
    }

    /**
     * Valida un código y calcula el precio final sin consumir usos.
     * Se usa en la previsualización del portal y dentro de la transacción
     * de matrícula (con $bloquear = true).
     *
     * @return array{ok: bool, mensaje: string, id_descuento?: int, codigo?: string, precio_final?: float, ahorro?: float, detalle?: string}
     */
    public function evaluar(string $codigo, int $idCurso, float $precioBase, bool $bloquear = false): array
    {
        $codigo = strtoupper(trim($codigo));
        if ($codigo === '') {
            return ['ok' => false, 'mensaje' => 'Indica un código de descuento.'];
        }

        $sql = 'SELECT * FROM descuento WHERE codigo = :codigo' . ($bloquear ? ' FOR UPDATE' : '');
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['codigo' => $codigo]);
        $d = $stmt->fetch();

        if (!$d) {
            return ['ok' => false, 'mensaje' => 'El código "' . $codigo . '" no existe.'];
        }
        if ((int) $d['activo'] !== 1) {
            return ['ok' => false, 'mensaje' => 'El código "' . $codigo . '" está desactivado.'];
        }

        $hoy = (string) $this->pdo->query('SELECT CURDATE()')->fetchColumn();
        if ($hoy < (string) $d['fecha_inicio']) {
            return ['ok' => false, 'mensaje' => 'El código "' . $codigo . '" aún no está vigente.'];
        }
        if ($hoy > (string) $d['fecha_fin']) {
            return ['ok' => false, 'mensaje' => 'El código "' . $codigo . '" ya venció.'];
        }
        if ($d['id_curso'] !== null && (int) $d['id_curso'] !== $idCurso) {
            return ['ok' => false, 'mensaje' => 'El código "' . $codigo . '" no aplica a este curso.'];
        }
        $usos = (int) $d['usos_disponibles'];
        if ($usos !== self::USOS_SIN_LIMITE && (int) $d['usos_realizados'] >= $usos) {
            return ['ok' => false, 'mensaje' => 'El código "' . $codigo . '" ya no tiene usos disponibles.'];
        }

        $precioFinal = $d['porcentaje'] !== null
            ? round($precioBase * (1 - (float) $d['porcentaje'] / 100), 2)
            : max(0.0, round($precioBase - (float) $d['monto_fijo'], 2));
        $ahorro = round($precioBase - $precioFinal, 2);

        $detalle = $d['porcentaje'] !== null
            ? (float) $d['porcentaje'] . '% de descuento'
            : '$ ' . number_format((float) $d['monto_fijo'], 2, '.', ',') . ' de descuento';

        return [
            'ok'            => true,
            'mensaje'       => 'Código aplicado: ' . $detalle . '.',
            'id_descuento'  => (int) $d['id_descuento'],
            'codigo'        => (string) $d['codigo'],
            'precio_final'  => $precioFinal,
            'ahorro'        => $ahorro,
            'detalle'       => $detalle,
        ];
    }

    /**
     * Descuenta un uso del cupón. Debe llamarse dentro de la transacción
     * de la matrícula para que usos y matrícula fallen juntos.
     */
    public function consumir(int $idDescuento): void
    {
        $this->pdo->prepare('UPDATE descuento SET usos_realizados = usos_realizados + 1 WHERE id_descuento = :id')
            ->execute(['id' => $idDescuento]);
    }
}
