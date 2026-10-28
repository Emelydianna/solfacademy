<?php

declare(strict_types=1);

namespace App\Application\Admin;

use App\Application\Formato\Dinero;
use App\Application\Pagos\ComprobanteStorage;
use PDO;
use PDOException;

/**
 * Verificación de comprobantes de pago por administración.
 *
 * El estudiante envía el comprobante desde su portal y el movimiento queda
 * en 'Pendiente' (no descuenta saldo). Aquí se revisa el archivo y se
 * decide: aprobar → 'Pagado' (suma en v_inscripciones_detalle) o rechazar →
 * 'Fallido' con motivo. No se crean, editan ni borran pagos: el saldo sigue
 * siendo precio_inscripcion − SUM(pagos Pagados).
 */
final class PagosService
{
    public const ESTADOS = ['Pendiente', 'Pagado', 'Fallido'];

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Listado paginado (10 por página) con búsqueda y filtro de estado.
     * Los pagos por verificar aparecen primero.
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
            $where[] = '(CONCAT(e.nombre, \' \', e.apellido) LIKE :nombre OR e.email LIKE :email
                         OR c.nombre LIKE :curso OR p.referencia_pago LIKE :ref
                         OR CAST(p.id_pago AS CHAR) = :id_q)';
            foreach (['nombre', 'email', 'curso', 'ref'] as $clave) {
                $params[$clave] = '%' . $q . '%';
            }
            $params['id_q'] = ltrim($q, '#');
        }
        if (in_array($estado, self::ESTADOS, true)) {
            $where[] = 'p.estado = :estado';
            $params['estado'] = $estado;
        } else {
            $estado = '';
        }

        $select = "p.id_pago, p.id_inscripcion, p.fecha_pago, p.monto_pagado, p.concepto, p.metodo,
                      p.estado, p.referencia_pago, p.comprobante_url, p.observaciones,
                      p.fecha_verificacion, p.motivo_verificacion,
                      CONCAT(e.nombre, ' ', e.apellido) AS estudiante, e.email,
                      c.nombre AS curso, g.id_grupo, i.precio_inscripcion";

        $filas = ListadoAdmin::pagina(
            $this->pdo,
            $select,
            'pago p
             JOIN inscripcion i ON i.id_inscripcion = p.id_inscripcion
             JOIN estudiante e ON e.id_estudiante = i.id_estudiante
             JOIN grupo g ON g.id_grupo = i.id_grupo
             JOIN curso c ON c.id_curso = g.id_curso',
            $where ? ' WHERE ' . implode(' AND ', $where) : '',
            $params,
            "FIELD(p.estado, 'Pendiente', 'Pagado', 'Fallido'), p.fecha_pago DESC, p.id_pago DESC",
            $f
        );

        // Total pagado y saldo por matrícula: se calculan con una consulta
        // por inscripción (una sola referencia a `pago` por sentencia).
        $totales = [];
        foreach ($filas['filas'] as &$fila) {
            $idInscripcion = (int) $fila['id_inscripcion'];
            $totales[$idInscripcion] ??= $this->totalPagado($idInscripcion);
            $fila['total_pagado'] = $totales[$idInscripcion];
            $fila['saldo'] = $fila['precio_inscripcion'] === null
                ? null
                : round((float) $fila['precio_inscripcion'] - $totales[$idInscripcion], 2);
            $this->comprobante($fila);
        }
        unset($fila);

        return $filas + ['filtros' => ['q' => $q, 'estado' => $estado]];
    }

    /** Suma de los pagos acreditados de una matrícula (fuente única de verdad). */
    private function totalPagado(int $idInscripcion): float
    {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(monto_pagado), 0) FROM pago
             WHERE id_inscripcion = :inscripcion AND estado = 'Pagado'"
        );
        $stmt->execute(['inscripcion' => $idInscripcion]);
        return round((float) $stmt->fetchColumn(), 2);
    }

    /**
     * Indicadores de la cabecera: por verificar, monto en espera,
     * verificados hoy y total acreditado.
     *
     * @return array<string, int|float>
     */
    public function resumen(): array
    {
        $porVerificar = (int) $this->uno("SELECT COUNT(*) FROM pago
            WHERE estado = 'Pendiente' AND comprobante_url IS NOT NULL");
        $enEspera = (float) $this->uno("SELECT COALESCE(SUM(monto_pagado), 0) FROM pago
            WHERE estado = 'Pendiente' AND comprobante_url IS NOT NULL");
        $hoy = (int) $this->uno("SELECT COUNT(*) FROM pago
            WHERE estado = 'Pagado' AND fecha_verificacion >= CURDATE()");
        $acreditado = (float) $this->uno("SELECT COALESCE(SUM(monto_pagado), 0) FROM pago WHERE estado = 'Pagado'");

        return [
            'por_verificar' => $porVerificar,
            'en_espera'     => round($enEspera, 2),
            'verificados_hoy' => $hoy,
            'acreditado'    => round($acreditado, 2),
        ];
    }

    /**
     * Detalle de un pago con el contexto de la matrícula (precio congelado,
     * total pagado y saldo) y quién verificó, si ya se verificó.
     *
     * @return array<string, mixed>|null
     */
    public function detalle(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT p.*, i.id_inscripcion, i.estado AS inscripcion_estado, i.fecha AS inscripcion_fecha,
                    i.precio_inscripcion, i.observaciones AS inscripcion_observaciones,
                    e.id_estudiante, CONCAT(e.nombre, ' ', e.apellido) AS estudiante, e.email,
                    c.id_curso, c.nombre AS curso, g.id_grupo,
                    CONCAT(iv.nombre, ' ', iv.apellido) AS instructor
             FROM pago p
             JOIN inscripcion i ON i.id_inscripcion = p.id_inscripcion
             JOIN estudiante e ON e.id_estudiante = i.id_estudiante
             JOIN grupo g ON g.id_grupo = i.id_grupo
             JOIN curso c ON c.id_curso = g.id_curso
             JOIN instructor iv ON iv.id_instructor = g.id_instructor
             WHERE p.id_pago = :id"
        );
        $stmt->execute(['id' => $id]);
        $pago = $stmt->fetch();
        if (!$pago) {
            return null;
        }

        $pago['total_pagado'] = $this->totalPagado((int) $pago['id_inscripcion']);
        $pago['saldo'] = $pago['precio_inscripcion'] === null
            ? null
            : round((float) $pago['precio_inscripcion'] - $pago['total_pagado'], 2);
        $pago['total_tras_aprobar'] = $pago['estado'] === 'Pagado'
            ? $pago['total_pagado']
            : round($pago['total_pagado'] + (float) $pago['monto_pagado'], 2);
        $pago['excede_precio'] = $pago['precio_inscripcion'] !== null
            && $pago['total_tras_aprobar'] > (float) $pago['precio_inscripcion'];
        $pago['inscripcion_pendiente'] = $pago['inscripcion_estado'] === 'Pendiente';

        $this->comprobante($pago);

        if ($pago['verificado_por'] !== null) {
            $stmt = $this->pdo->prepare('SELECT email_usuario FROM usuario WHERE id_usuario = :id');
            $stmt->execute(['id' => $pago['verificado_por']]);
            $pago['verificado_por_email'] = (string) ($stmt->fetchColumn() ?: '');
        }

        $pago['pagos_matricula'] = $this->pagosDeInscripcion((int) $pago['id_inscripcion']);

        return $pago;
    }

    /**
     * Aprueba el comprobante: el movimiento pasa a 'Pagado' y por lo tanto
     * empieza a descontar saldo. Exige que exista el archivo en disco.
     *
     * @return array{ok: bool, mensaje: string}
     */
    public function aprobar(int $id, int $idUsuario): array
    {
        $url = '';
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                "SELECT p.id_pago, p.estado, p.comprobante_url, p.monto_pagado, p.id_inscripcion,
                        i.precio_inscripcion
                 FROM pago p
                 JOIN inscripcion i ON i.id_inscripcion = p.id_inscripcion
                 WHERE p.id_pago = :id FOR UPDATE"
            );
            $stmt->execute(['id' => $id]);
            $pago = $stmt->fetch();

            if (!$pago) {
                $this->pdo->rollBack();
                return ['ok' => false, 'mensaje' => 'El pago ya no existe.'];
            }
            if ($pago['estado'] !== 'Pendiente') {
                $this->pdo->rollBack();
                return ['ok' => false, 'mensaje' => 'Solo se puede verificar un pago pendiente.'];
            }
            $url = (string) ($pago['comprobante_url'] ?? '');
            if (!$this->archivoDisponible($url)) {
                $this->pdo->rollBack();
                return ['ok' => false, 'mensaje' => 'No se pudo verificar: el archivo del comprobante no está disponible en el servidor.'];
            }

            $this->pdo->prepare(
                "UPDATE pago SET estado = 'Pagado', verificado_por = :usuario,
                        fecha_verificacion = NOW(), motivo_verificacion = NULL
                 WHERE id_pago = :id"
            )->execute(['usuario' => $idUsuario, 'id' => $id]);

            $total = $this->totalPagado((int) $pago['id_inscripcion']);
            $precio = $pago['precio_inscripcion'] !== null ? (float) $pago['precio_inscripcion'] : null;

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($e instanceof PDOException && (string) $e->getCode() === '40001') {
                return ['ok' => false, 'mensaje' => 'Hubo una operación simultánea sobre este pago. Recarga e inténtalo de nuevo.'];
            }
            throw $e;
        }

        $mensaje = 'Comprobante verificado. El pago se acreditó (' . Dinero::monto((float) $pago['monto_pagado']) . ').';
        if ($precio !== null && $total > $precio) {
            $mensaje .= ' Atención: el total pagado (' . Dinero::monto($total)
                . ') supera el precio de la matrícula (' . Dinero::monto($precio) . ').';
        }

        return ['ok' => true, 'mensaje' => $mensaje];
    }

    /**
     * Rechaza el comprobante: 'Fallido' con motivo. El estudiante puede
     * enviar otro desde su portal.
     *
     * @return array{ok: bool, mensaje: string}
     */
    public function rechazar(int $id, int $idUsuario, string $motivo): array
    {
        $motivo = trim($motivo);
        if ($motivo === '' || mb_strlen($motivo) > 255) {
            return ['ok' => false, 'mensaje' => 'Indica un motivo de rechazo de hasta 255 caracteres.'];
        }

        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare('SELECT estado FROM pago WHERE id_pago = :id FOR UPDATE');
            $stmt->execute(['id' => $id]);
            $estado = $stmt->fetchColumn();

            if ($estado === false) {
                $this->pdo->rollBack();
                return ['ok' => false, 'mensaje' => 'El pago ya no existe.'];
            }
            if ($estado !== 'Pendiente') {
                $this->pdo->rollBack();
                return ['ok' => false, 'mensaje' => 'Solo se puede verificar un pago pendiente.'];
            }

            $this->pdo->prepare(
                "UPDATE pago SET estado = 'Fallido', verificado_por = :usuario,
                        fecha_verificacion = NOW(), motivo_verificacion = :motivo
                 WHERE id_pago = :id"
            )->execute(['usuario' => $idUsuario, 'motivo' => $motivo, 'id' => $id]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($e instanceof PDOException && (string) $e->getCode() === '40001') {
                return ['ok' => false, 'mensaje' => 'Hubo una operación simultánea sobre este pago. Recarga e inténtalo de nuevo.'];
            }
            throw $e;
        }

        return ['ok' => true, 'mensaje' => 'Comprobante rechazado. El estudiante podrá enviar otro desde su portal.'];
    }

    /** ¿El archivo del comprobante pertenece a algún pago registrado? */
    public function comprobanteExistente(string $archivo): bool
    {
        $idPago = ComprobanteStorage::idPagoDe($archivo);
        if ($idPago < 1) {
            return false;
        }
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM pago WHERE id_pago = :id');
        $stmt->execute(['id' => $idPago]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /** Historial de la matrícula para el detalle (contexto de verificación). */
    private function pagosDeInscripcion(int $idInscripcion): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id_pago, fecha_pago, monto_pagado, concepto, metodo, estado,
                    referencia_pago, comprobante_url, motivo_verificacion
             FROM pago WHERE id_inscripcion = :inscripcion
             ORDER BY fecha_pago DESC, id_pago DESC'
        );
        $stmt->execute(['inscripcion' => $idInscripcion]);
        $filas = $stmt->fetchAll();
        foreach ($filas as &$fila) {
            $this->comprobante($fila);
        }
        unset($fila);
        return $filas;
    }

    /**
     * Añade comprobante_enlace (ruta protegida del panel) y
     * comprobante_disponible (¿está el archivo en disco?).
     */
    private function comprobante(array &$fila): void
    {
        $url = is_string($fila['comprobante_url'] ?? null) ? (string) $fila['comprobante_url'] : '';
        $archivo = ComprobanteStorage::archivoDe($url);
        $fila['comprobante_subido'] = $archivo !== '';
        $fila['comprobante_enlace'] = $archivo !== ''
            ? '/admin/pagos/comprobante/' . rawurlencode($archivo)
            : '';
        $fila['comprobante_disponible'] = $this->archivoDisponible($url);
    }

    private function archivoDisponible(string $url): bool
    {
        if (ComprobanteStorage::esSubido($url)) {
            return ComprobanteStorage::existe($url);
        }
        // Comprobantes antiguos generados en /public/comprobantes.
        return $url !== '' && !str_contains($url, '..') && is_file(dirname(__DIR__, 3) . '/public' . $url);
    }

    private function uno(string $sql): mixed
    {
        return $this->pdo->query($sql)->fetchColumn();
    }
}
