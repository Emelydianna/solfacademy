<?php

declare(strict_types=1);

namespace App\Application\Academico;

use App\Application\Admin\GruposService;
use App\Application\Admin\ListadoAdmin;
use App\Application\Descuentos\DescuentosService;
use App\Application\Formato\Dinero;
use DateTimeImmutable;
use DomainException;
use PDO;
use PDOException;

final class MatriculasService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function gruposDisponibles(int $idCurso, int $idEstudiante = 0): array
    {
        $stmt = $this->pdo->prepare("SELECT g.id_grupo, g.id_curso, g.capacidad_maxima, g.fecha_inicio, g.fecha_fin, g.estado,
            CONCAT(i.nombre, ' ', i.apellido) AS instructor, a.nombre AS aula,
            (SELECT COUNT(*) FROM inscripcion m WHERE m.id_grupo = g.id_grupo AND m.estado IN ('Activa','Pendiente')) AS ocupados
            FROM grupo g JOIN curso c ON c.id_curso = g.id_curso
            JOIN instructor i ON i.id_instructor = g.id_instructor
            JOIN aula a ON a.id_aula = g.id_aula
            WHERE g.id_curso = :curso AND g.estado = 'activo' AND c.activo = 1 AND c.disponible = 1
              AND i.activo = 1 AND a.activa = 1
            ORDER BY g.fecha_inicio, g.id_grupo");
        $stmt->execute(['curso' => $idCurso]);
        $grupos = $stmt->fetchAll();
        $horarios = new GruposService($this->pdo);
        $vigente = $this->matriculaVigente($idEstudiante, $idCurso);
        foreach ($grupos as &$g) {
            $g['id_grupo'] = (int) $g['id_grupo'];
            $g['capacidad_maxima'] = (int) $g['capacidad_maxima'];
            $g['ocupados'] = (int) $g['ocupados'];
            $g['lugares'] = max(0, $g['capacidad_maxima'] - $g['ocupados']);
            $g['horarios'] = $horarios->horarios($g['id_grupo']);
            $g['tiene_matricula_vigente'] = $vigente !== null;
        }
        unset($g);
        return ['grupos' => $grupos, 'vigente' => $vigente];
    }

    private function matriculaVigente(int $idEstudiante, int $idCurso): ?array
    {
        if ($idEstudiante < 1 || $idCurso < 1) {
            return null;
        }
        $stmt = $this->pdo->prepare("SELECT m.id_inscripcion, m.estado, m.id_grupo FROM inscripcion m
            JOIN grupo g ON g.id_grupo = m.id_grupo
            WHERE m.id_estudiante = :estudiante AND g.id_curso = :curso AND m.estado IN ('Pendiente','Activa')");
        $stmt->execute(['estudiante' => $idEstudiante, 'curso' => $idCurso]);
        return $stmt->fetch() ?: null;
    }

    public function datosSolicitud(int $idGrupo, int $idEstudiante): array
    {
        $stmt = $this->pdo->prepare("SELECT g.*, c.nombre AS curso, c.nivel, c.precio, c.disponible, c.activo,
            CONCAT(i.nombre, ' ', i.apellido) AS instructor, a.nombre AS aula,
            (SELECT COUNT(*) FROM inscripcion m WHERE m.id_grupo = g.id_grupo AND m.estado IN ('Activa','Pendiente')) AS ocupados
            FROM grupo g JOIN curso c ON c.id_curso = g.id_curso
            JOIN instructor i ON i.id_instructor = g.id_instructor JOIN aula a ON a.id_aula = g.id_aula
            WHERE g.id_grupo = :grupo");
        $stmt->execute(['grupo' => $idGrupo]);
        $grupo = $stmt->fetch();
        if (!$grupo) {
            return [];
        }
        $stmt = $this->pdo->prepare('SELECT id_estudiante, estado, fecha_nacimiento, nombre, apellido FROM estudiante WHERE id_estudiante = :id');
        $stmt->execute(['id' => $idEstudiante]);
        $estudiante = $stmt->fetch();
        $stmt = $this->pdo->prepare('SELECT * FROM requisito_curso WHERE id_curso = :id');
        $stmt->execute(['id' => $grupo['id_curso']]);
        $requisito = $stmt->fetch() ?: null;
        $horarios = (new GruposService($this->pdo))->horarios($idGrupo);
        $vigente = $this->matriculaVigente($idEstudiante, (int) $grupo['id_curso']);
        return ['grupo' => $grupo, 'estudiante' => $estudiante, 'requisito' => $requisito,
            'horarios' => $horarios, 'vigente' => $vigente,
            'lugares' => max(0, (int) $grupo['capacidad_maxima'] - (int) $grupo['ocupados'])];
    }

    /**
     * Solicitud de matrícula desde el portal. Congela el precio —con el
     * código de descuento, si es válido— y registra el cupón usado.
     *
     * @param string $codigoDescuento Código introducido por el estudiante (opcional).
     *                                Un código inválido no bloquea: solo se avisa.
     * @return array{ok: bool, id?: int, mensaje: string}
     */
    public function solicitar(int $idGrupo, int $idEstudiante, int $idUsuario, bool $confirmarRequisitos, string $codigoDescuento = ''): array
    {
        try {
            $this->pdo->beginTransaction();
            $datos = $this->bloquearSolicitud($idGrupo, $idEstudiante);
            if (!$datos) {
                throw new DomainException('El grupo o estudiante ya no existe.');
            }
            ['grupo' => $grupo, 'estudiante' => $estudiante, 'requisito' => $requisito] = $datos;
            if ($estudiante['estado'] !== 'Activo') {
                throw new DomainException('El estudiante está suspendido.');
            }
            if ($grupo['estado'] !== 'activo' || !$grupo['activo'] || !$grupo['disponible']) {
                throw new DomainException('El grupo ya no admite nuevas matrículas.');
            }
            if ((int) $grupo['ocupados'] >= (int) $grupo['capacidad_maxima']) {
                throw new DomainException('Este grupo ya no tiene lugares disponibles.');
            }
            $vigente = $this->matriculaVigente($idEstudiante, (int) $grupo['id_curso']);
            if ($vigente) {
                throw new DomainException($vigente['estado'] === 'Pendiente'
                    ? 'Ya tienes una solicitud pendiente para este curso.'
                    : 'Ya estás matriculado en este curso.');
            }
            if ($this->hayConflictoHorario($idEstudiante, $idGrupo)) {
                throw new DomainException('El horario del grupo coincide con otra matrícula vigente.');
            }
            if ($requisito && $requisito['edad_minima'] !== null && $estudiante['fecha_nacimiento'] !== null) {
                $edad = (new DateTimeImmutable($estudiante['fecha_nacimiento']))->diff(new DateTimeImmutable('today'))->y;
                if ($edad < (int) $requisito['edad_minima'] || $edad > (int) $requisito['edad_maxima']) {
                    throw new DomainException('Tu edad registrada no cumple el rango indicado en los requisitos.');
                }
            }
            if ($requisito && !$confirmarRequisitos) {
                throw new DomainException('Debes confirmar que revisaste los requisitos del curso.');
            }

            // Código de descuento (opcional): se congela el precio final y
            // se guarda el cupón en inscripcion.id_descuento. Un código no
            // válido no bloquea la matrícula, solo se avisa en el mensaje.
            $precio = round((float) $grupo['precio'], 2);
            $descuento = null;
            $avisoDescuento = '';
            $codigo = strtoupper(trim($codigoDescuento));
            if ($codigo !== '') {
                $evaluado = (new DescuentosService($this->pdo))->evaluar($codigo, (int) $grupo['id_curso'], $precio, true);
                if ($evaluado['ok']) {
                    $precio = (float) $evaluado['precio_final'];
                    $descuento = $evaluado;
                } else {
                    $avisoDescuento = ' ' . $evaluado['mensaje'] . ' La matrícula quedó registrada sin descuento.';
                }
            }

            $this->pdo->prepare('INSERT INTO inscripcion (id_estudiante, id_grupo, fecha, estado, precio_inscripcion, observaciones, id_descuento)
                VALUES (:estudiante, :grupo, CURDATE(), \'Pendiente\', :precio, \'Solicitud creada por el estudiante\', :descuento)')
                ->execute(['estudiante' => $idEstudiante, 'grupo' => $idGrupo, 'precio' => $precio,
                    'descuento' => $descuento['id_descuento'] ?? null]);
            $id = (int) $this->pdo->lastInsertId();
            if ($descuento !== null) {
                (new DescuentosService($this->pdo))->consumir((int) $descuento['id_descuento']);
            }
            $this->auditar($id, null, 'Pendiente', 'Solicitud creada desde el portal del estudiante', $idUsuario);
            $this->pdo->commit();

            $mensaje = 'Solicitud enviada. Administración revisará los requisitos antes de activarla.';
            if ($descuento !== null) {
                $mensaje .= ' Descuento ' . $descuento['codigo'] . ' aplicado (' . $descuento['detalle']
                    . '): precio final ' . Dinero::monto((float) $descuento['precio_final']) . '.';
            }
            return ['ok' => true, 'id' => $id, 'mensaje' => $mensaje . $avisoDescuento];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($e instanceof PDOException && (string) $e->getCode() === '40001') {
                return ['ok' => false, 'mensaje' => 'Hubo una matrícula simultánea. Recarga e inténtalo de nuevo.'];
            }
            throw $e;
        }
    }

    /**
     * Previsualiza un código de descuento para el formulario de matrícula.
     * Solo consulta: no consume usos ni escribe en la base de datos.
     *
     * @return array{ok: bool, mensaje: string, id_descuento?: int, codigo?: string, precio_final?: float, ahorro?: float, detalle?: string}
     */
    public function previsualizarDescuento(int $idCurso, float $precioBase, string $codigo): array
    {
        return (new DescuentosService($this->pdo))->evaluar($codigo, $idCurso, $precioBase);
    }

    public function cancelarPropia(int $idInscripcion, int $idEstudiante, int $idUsuario): array
    {
        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare('SELECT id_estudiante, estado FROM inscripcion WHERE id_inscripcion = :id FOR UPDATE');
            $stmt->execute(['id' => $idInscripcion]);
            $inscripcion = $stmt->fetch();
            if (!$inscripcion || (int) $inscripcion['id_estudiante'] !== $idEstudiante) {
                throw new DomainException('La matrícula no existe o no te pertenece.');
            }
            if ($inscripcion['estado'] !== 'Pendiente') {
                throw new DomainException('Solo puedes cancelar solicitudes pendientes.');
            }
            $this->cambiarEstado($idInscripcion, 'Cancelada', 'Cancelada por el estudiante desde el portal', $idUsuario);
            $this->pdo->commit();
            return ['ok' => true, 'mensaje' => 'Solicitud cancelada. El lugar se liberó inmediatamente.'];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function listarAdmin(array $f): array
    {
        $filtros = ['q' => mb_substr(\App\Application\Admin\GestionAcademicaService::texto($f, 'q'), 0, 150),
            'estado' => \App\Application\Admin\GestionAcademicaService::texto($f, 'estado'), 'curso' => \App\Application\Admin\GestionAcademicaService::texto($f, 'curso')];
        $where = [];
        $params = [];
        if ($filtros['q'] !== '') {
            $where[] = "(CONCAT(e.nombre, ' ', e.apellido) LIKE :nombre OR e.email LIKE :email OR c.nombre LIKE :curso_q OR CAST(m.id_inscripcion AS CHAR) = :id_q)";
            foreach (['nombre', 'email', 'curso_q'] as $campo) {
                $params[$campo] = '%' . $filtros['q'] . '%';
            }
            $params['id_q'] = ltrim($filtros['q'], '#');
        }
        if (in_array($filtros['estado'], ['Pendiente', 'Activa', 'Cancelada', 'Finalizada'], true)) {
            $where[] = 'm.estado = :estado';
            $params['estado'] = $filtros['estado'];
        } else {
            $filtros['estado'] = '';
        }
        if (ctype_digit($filtros['curso']) && (int) $filtros['curso'] > 0) {
            $where[] = 'g.id_curso = :curso';
            $params['curso'] = (int) $filtros['curso'];
        } else {
            $filtros['curso'] = '';
        }
        return ListadoAdmin::pagina($this->pdo,
            "m.*, CONCAT(e.nombre, ' ', e.apellido) AS estudiante, e.email, e.estado AS estudiante_estado, e.fecha_nacimiento,
                c.id_curso, c.nombre AS curso, g.id_grupo, CONCAT(i.nombre, ' ', i.apellido) AS instructor,
                TIMESTAMPDIFF(DAY, m.fecha, CURDATE()) AS dias_antiguedad,
                (SELECT COALESCE(SUM(p.monto_pagado), 0) FROM pago p WHERE p.id_inscripcion = m.id_inscripcion AND p.estado = 'Pagado') AS total_pagado",
            'inscripcion m JOIN estudiante e ON e.id_estudiante = m.id_estudiante JOIN grupo g ON g.id_grupo = m.id_grupo
                JOIN curso c ON c.id_curso = g.id_curso JOIN instructor i ON i.id_instructor = g.id_instructor',
            $where ? ' WHERE ' . implode(' AND ', $where) : '', $params,
            "FIELD(m.estado, 'Pendiente', 'Activa', 'Finalizada', 'Cancelada'), m.fecha, m.id_inscripcion", $f)
            + ['filtros' => $filtros];
    }

    public function detalle(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT m.*, CONCAT(e.nombre, \' \', e.apellido) AS estudiante, e.email, e.fecha_nacimiento,
            g.id_curso, g.id_grupo, g.capacidad_maxima, c.nombre AS curso, c.nivel, c.activo AS curso_activo, c.disponible,
            CONCAT(i.nombre, \' \', i.apellido) AS instructor, a.nombre AS aula,
            d.codigo AS descuento_codigo, d.porcentaje AS descuento_porcentaje, d.monto_fijo AS descuento_monto_fijo,
            r.nivel_minimo, r.edad_minima, r.edad_maxima, r.conocimientos_previos, r.necesita_instrumento, r.puede_alquilar, r.descripcion_general
            FROM inscripcion m JOIN estudiante e ON e.id_estudiante = m.id_estudiante
            JOIN grupo g ON g.id_grupo = m.id_grupo JOIN curso c ON c.id_curso = g.id_curso
            JOIN instructor i ON i.id_instructor = g.id_instructor JOIN aula a ON a.id_aula = g.id_aula
            LEFT JOIN descuento d ON d.id_descuento = m.id_descuento
            LEFT JOIN requisito_curso r ON r.id_curso = c.id_curso WHERE m.id_inscripcion = :id');
        $stmt->execute(['id' => $id]);
        $m = $stmt->fetch();
        if (!$m) {
            return [];
        }
        $m['grupo_estado'] = $this->pdo->query("SELECT estado FROM grupo WHERE id_grupo = " . (int) $m['id_grupo'])->fetchColumn();
        $m['horarios'] = (new GruposService($this->pdo))->horarios((int) $m['id_grupo']);
        $m['ocupados'] = (int) $this->pdo->query("SELECT COUNT(*) FROM inscripcion WHERE id_grupo = " . (int) $m['id_grupo'] . " AND estado IN ('Activa','Pendiente')")->fetchColumn();
        $m['conflicto_horario'] = $this->hayConflictoHorario((int) $m['id_estudiante'], (int) $m['id_grupo'], $id);
        $m['edad'] = $m['fecha_nacimiento'] ? (new DateTimeImmutable($m['fecha_nacimiento']))->diff(new DateTimeImmutable('today'))->y : null;
        $m['auditoria'] = $this->auditoria($id);
        return $m;
    }

    public function aprobar(int $id, int $idUsuario, bool $confirmarRequisitos, bool $confirmarEdad, string $observaciones): array
    {
        $observaciones = trim($observaciones);
        if (!$confirmarRequisitos) {
            throw new DomainException('Confirma que revisaste los requisitos antes de aprobar.');
        }
        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare('SELECT id_estudiante, id_grupo, estado FROM inscripcion WHERE id_inscripcion = :id FOR UPDATE');
            $stmt->execute(['id' => $id]);
            $m = $stmt->fetch();
            if (!$m) {
                throw new DomainException('La matrícula ya no existe.');
            }
            if ($m['estado'] !== 'Pendiente') {
                throw new DomainException('Solo se puede aprobar una matrícula pendiente.');
            }
            $stmt = $this->pdo->prepare('SELECT id_estudiante, estado, fecha_nacimiento FROM estudiante WHERE id_estudiante = :id FOR UPDATE');
            $stmt->execute(['id' => $m['id_estudiante']]);
            $estudiante = $stmt->fetch();
            $stmt = $this->pdo->prepare('SELECT g.id_curso, g.estado, g.capacidad_maxima, c.activo, c.disponible FROM grupo g JOIN curso c ON c.id_curso = g.id_curso WHERE g.id_grupo = :id FOR UPDATE');
            $stmt->execute(['id' => $m['id_grupo']]);
            $grupo = $stmt->fetch();
            if ($estudiante['estado'] !== 'Activo') {
                throw new DomainException('El estudiante está suspendido.');
            }
            if (!$grupo || $grupo['estado'] !== 'activo' || !$grupo['activo'] || !$grupo['disponible']) {
                throw new DomainException('El curso o grupo ya no admite matrículas activas.');
            }
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM inscripcion WHERE id_grupo = :id AND estado IN ('Activa','Pendiente')");
            $stmt->execute(['id' => $m['id_grupo']]);
            if ((int) $stmt->fetchColumn() > (int) $grupo['capacidad_maxima']) {
                throw new DomainException('El grupo supera su capacidad. Revisa sus matrículas vigentes antes de aprobar.');
            }
            $vigente = $this->matriculaVigente((int) $m['id_estudiante'], (int) $grupo['id_curso']);
            if ($vigente && (int) $vigente['id_inscripcion'] !== $id) {
                throw new DomainException('El estudiante ya tiene otra matrícula vigente para este curso.');
            }
            if ($this->hayConflictoHorario((int) $m['id_estudiante'], (int) $m['id_grupo'], $id)) {
                throw new DomainException('La matrícula entra en conflicto con otra vigente del estudiante.');
            }
            $stmt = $this->pdo->prepare('SELECT * FROM requisito_curso WHERE id_curso = :id');
            $stmt->execute(['id' => $grupo['id_curso']]);
            $r = $stmt->fetch();
            if ($r && $r['edad_minima'] !== null && $estudiante['fecha_nacimiento'] !== null) {
                $edad = (new DateTimeImmutable($estudiante['fecha_nacimiento']))->diff(new DateTimeImmutable('today'))->y;
                if ($edad < (int) $r['edad_minima'] || $edad > (int) $r['edad_maxima']) {
                    throw new DomainException('La edad registrada no cumple los requisitos vigentes del curso.');
                }
            } elseif ($r && $r['edad_minima'] !== null && !$confirmarEdad) {
                throw new DomainException('Confirma la verificación manual de la edad del estudiante.');
            }
            $motivo = $observaciones ?: 'Requisitos revisados y matrícula aprobada';
            if ($r && $r['edad_minima'] !== null && $estudiante['fecha_nacimiento'] === null) {
                $motivo .= '; edad confirmada manualmente';
            }
            $this->cambiarEstado($id, 'Activa', $motivo, $idUsuario);
            $this->pdo->commit();
            return ['ok' => true, 'mensaje' => 'Matrícula aprobada y activada.'];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function rechazar(int $id, int $idUsuario, string $motivo): array
    {
        $motivo = trim($motivo);
        if ($motivo === '' || mb_strlen($motivo) > 255) {
            return ['ok' => false, 'mensaje' => 'Indica un motivo de rechazo de hasta 255 caracteres.'];
        }
        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare('SELECT estado FROM inscripcion WHERE id_inscripcion = :id FOR UPDATE');
            $stmt->execute(['id' => $id]);
            $estado = $stmt->fetchColumn();
            if ($estado === false) {
                throw new DomainException('La matrícula ya no existe.');
            }
            if ($estado !== 'Pendiente') {
                throw new DomainException('Solo se puede rechazar una matrícula pendiente.');
            }
            $this->cambiarEstado($id, 'Cancelada', 'Rechazada: ' . $motivo, $idUsuario);
            $this->pdo->commit();
            return ['ok' => true, 'mensaje' => 'Solicitud rechazada. El lugar quedó liberado.'];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    private function bloquearSolicitud(int $idGrupo, int $idEstudiante): ?array
    {
        // El orden de bloqueos debe coincidir con GruposService: curso,
        // después estudiante y finalmente grupo. Así no se cruzan transacciones
        // de matrícula con una edición simultánea del grupo.
        $stmt = $this->pdo->prepare('SELECT id_curso FROM grupo WHERE id_grupo = :id');
        $stmt->execute(['id' => $idGrupo]);
        $idCurso = (int) $stmt->fetchColumn();
        if ($idCurso < 1) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT id_curso FROM curso WHERE id_curso = :id FOR UPDATE');
        $stmt->execute(['id' => $idCurso]);
        if (!$stmt->fetchColumn()) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT id_estudiante, estado, fecha_nacimiento FROM estudiante WHERE id_estudiante = :id FOR UPDATE');
        $stmt->execute(['id' => $idEstudiante]);
        $estudiante = $stmt->fetch();
        if (!$estudiante) {
            return null;
        }
        $stmt = $this->pdo->prepare("SELECT g.*, c.nombre AS curso, c.nivel, c.precio, c.disponible, c.activo,
            CONCAT(i.nombre, ' ', i.apellido) AS instructor, a.nombre AS aula,
            (SELECT COUNT(*) FROM inscripcion m WHERE m.id_grupo = g.id_grupo AND m.estado IN ('Activa','Pendiente')) AS ocupados
            FROM grupo g JOIN curso c ON c.id_curso = g.id_curso JOIN instructor i ON i.id_instructor = g.id_instructor
            JOIN aula a ON a.id_aula = g.id_aula WHERE g.id_grupo = :id FOR UPDATE");
        $stmt->execute(['id' => $idGrupo]);
        $grupo = $stmt->fetch();
        if (!$grupo || (int) $grupo['id_curso'] !== $idCurso) {
            throw new DomainException('El grupo cambió mientras se preparaba la solicitud.');
        }
        $stmt = $this->pdo->prepare('SELECT * FROM requisito_curso WHERE id_curso = :id');
        $stmt->execute(['id' => $idCurso]);
        return ['grupo' => $grupo, 'estudiante' => $estudiante, 'requisito' => $stmt->fetch() ?: null];
    }

    private function hayConflictoHorario(int $idEstudiante, int $idGrupo, int $idExcluir = 0): bool
    {
        $inicioNuevo = $this->inicio($idGrupo);
        $finNuevo = $this->fin($idGrupo);
        $stmt = $this->pdo->prepare("SELECT g.fecha_inicio, g.fecha_fin, h.dia_semana, h.hora_inicio, h.hora_fin
            FROM inscripcion m JOIN grupo g ON g.id_grupo = m.id_grupo JOIN horario h ON h.id_grupo = g.id_grupo
            WHERE m.id_estudiante = :estudiante AND m.estado IN ('Pendiente','Activa') AND m.id_grupo <> :grupo
              AND m.id_inscripcion <> :excluir AND g.estado <> 'cancelado'
              AND g.fecha_inicio <= :nuevo_fin AND COALESCE(g.fecha_fin, '9999-12-31') >= :nuevo_inicio");
        $stmt->execute(['estudiante' => $idEstudiante, 'grupo' => $idGrupo, 'excluir' => $idExcluir,
            'nuevo_inicio' => $inicioNuevo, 'nuevo_fin' => $finNuevo]);
        $nuevos = (new GruposService($this->pdo))->horarios($idGrupo);
        foreach ($stmt->fetchAll() as $otro) {
            foreach ($nuevos as $nuevo) {
                if ($otro['dia_semana'] === $nuevo['dia_semana'] && $otro['hora_inicio'] < $nuevo['hora_fin'] && $otro['hora_fin'] > $nuevo['hora_inicio']
                    && GruposService::coincideDia(max($otro['fecha_inicio'], $inicioNuevo), min($otro['fecha_fin'] ?: '9999-12-31', $finNuevo), $nuevo['dia_semana'])) {
                    return true;
                }
            }
        }
        return false;
    }

    private function inicio(int $id): string
    {
        $stmt = $this->pdo->prepare('SELECT fecha_inicio FROM grupo WHERE id_grupo = :id');
        $stmt->execute(['id' => $id]);
        return (string) $stmt->fetchColumn();
    }

    private function fin(int $id): string
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(fecha_fin, \'9999-12-31\') FROM grupo WHERE id_grupo = :id');
        $stmt->execute(['id' => $id]);
        return (string) $stmt->fetchColumn();
    }

    private function cambiarEstado(int $id, string $estado, string $motivo, int $idUsuario): void
    {
        $stmt = $this->pdo->prepare('SELECT estado FROM inscripcion WHERE id_inscripcion = :id');
        $stmt->execute(['id' => $id]);
        $anterior = (string) $stmt->fetchColumn();
        $this->pdo->prepare('UPDATE inscripcion SET estado = :estado, revisado_por = :usuario, fecha_revision = NOW(), motivo_revision = :motivo WHERE id_inscripcion = :id')
            ->execute(['estado' => $estado, 'usuario' => $idUsuario, 'motivo' => mb_substr($motivo, 0, 255), 'id' => $id]);
        $this->auditar($id, $anterior, $estado, $motivo, $idUsuario);
    }

    private function auditar(int $id, ?string $anterior, string $nuevo, string $motivo, int $idUsuario): void
    {
        $this->pdo->prepare('INSERT INTO inscripcion_auditoria (id_inscripcion, estado_anterior, estado_nuevo, motivo, id_usuario)
            VALUES (:inscripcion, :anterior, :nuevo, :motivo, :usuario)')
            ->execute(['inscripcion' => $id, 'anterior' => $anterior, 'nuevo' => $nuevo, 'motivo' => mb_substr($motivo, 0, 255), 'usuario' => $idUsuario]);
    }

    private function auditoria(int $id): array
    {
        $stmt = $this->pdo->prepare("SELECT a.*, CONCAT(u.email_usuario, CASE WHEN u.rol = 'estudiante' THEN '' ELSE CONCAT(' (', u.rol, ')') END) AS responsable
            FROM inscripcion_auditoria a JOIN usuario u ON u.id_usuario = a.id_usuario
            WHERE a.id_inscripcion = :id ORDER BY a.fecha DESC, a.id_auditoria DESC");
        $stmt->execute(['id' => $id]);
        return $stmt->fetchAll();
    }
}
