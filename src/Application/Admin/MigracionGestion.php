<?php

declare(strict_types=1);

namespace App\Application\Admin;

use PDO;

/** Migración incremental e idempotente: no borra tablas ni registros. */
final class MigracionGestion
{
    public static function columnas(PDO $pdo): void
    {
        $definiciones = [
            'estudiante' => [
                'estado' => "ENUM('Activo','Suspendido') NOT NULL DEFAULT 'Activo'",
                'suspension_fecha' => 'DATETIME NULL',
                'suspension_motivo' => 'VARCHAR(255) NULL',
                'fecha_nacimiento' => 'DATE NULL',
            ],
            'usuario' => [
                'version_sesion' => 'INT UNSIGNED NOT NULL DEFAULT 0',
                'reactivar_tras_suspension' => 'TINYINT(1) NOT NULL DEFAULT 0',
            ],
            'aula' => [
                'tipo_inactividad' => "ENUM('General','Mantenimiento') NOT NULL DEFAULT 'General'",
            ],
            'inscripcion' => [
                'revisado_por' => 'INT UNSIGNED NULL',
                'fecha_revision' => 'DATETIME NULL',
                'motivo_revision' => 'VARCHAR(255) NULL',
                // Cupón aplicado al matricular; precio_inscripcion guarda
                // siempre el precio final ya con el descuento aplicado.
                'id_descuento' => 'INT UNSIGNED NULL',
            ],
            // Verificación de comprobantes: quién aprobó/rechazó y cuándo.
            // creado_por sigue siendo quien creó la fila (en el flujo del
            // portal, el propio estudiante).
            'pago' => [
                'verificado_por' => 'INT UNSIGNED NULL',
                'fecha_verificacion' => 'DATETIME NULL',
                'motivo_verificacion' => 'VARCHAR(255) NULL',
            ],
            // Imagen de referencia del curso en var/uploads/cursos.
            'curso' => [
                'imagen' => 'VARCHAR(255) NULL',
            ],
        ];
        foreach ($definiciones as $tabla => $campos) {
            $existentes = $pdo->query("SHOW COLUMNS FROM $tabla")->fetchAll(PDO::FETCH_COLUMN);
            $agregar = [];
            foreach ($campos as $campo => $definicion) {
                if (!in_array($campo, $existentes, true)) {
                    $agregar[] = "ADD COLUMN $campo $definicion";
                }
            }
            if ($agregar) {
                $pdo->exec("ALTER TABLE $tabla " . implode(', ', $agregar));
            }
        }
        $pdo->exec('CREATE TABLE IF NOT EXISTS inscripcion_auditoria (
            id_auditoria INT UNSIGNED NOT NULL AUTO_INCREMENT,
            id_inscripcion INT UNSIGNED NOT NULL,
            estado_anterior VARCHAR(20) NULL,
            estado_nuevo VARCHAR(20) NOT NULL,
            motivo VARCHAR(255) NULL,
            id_usuario INT UNSIGNED NOT NULL,
            fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_auditoria),
            KEY idx_auditoria_inscripcion (id_inscripcion),
            CONSTRAINT fk_auditoria_inscripcion FOREIGN KEY (id_inscripcion)
                REFERENCES inscripcion (id_inscripcion)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        // Identidad de la academia: una sola fila (id = 1) con valores por
        // defecto. Si ya existe no se toca: lo que haya configurado la
        // academia se conserva.
        $pdo->exec('CREATE TABLE IF NOT EXISTS configuracion (
            id_configuracion TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
            nombre VARCHAR(80) NOT NULL,
            lema VARCHAR(120) NOT NULL,
            logo_url VARCHAR(255) NULL,
            email_contacto VARCHAR(150) NULL,
            telefono VARCHAR(30) NULL,
            direccion VARCHAR(180) NULL,
            ciudad VARCHAR(120) NULL,
            facebook VARCHAR(150) NULL,
            instagram VARCHAR(150) NULL,
            youtube VARCHAR(150) NULL,
            pie_pagina VARCHAR(180) NULL,
            nota_pdf VARCHAR(180) NULL,
            actualizado_en DATETIME NULL,
            PRIMARY KEY (id_configuracion)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $pdo->exec("INSERT IGNORE INTO configuracion
            (id_configuracion, nombre, lema, email_contacto, telefono, direccion, ciudad, pie_pagina)
            VALUES (1, 'SolfAcademy', 'Academia de Música', 'info@solfacademy.com', '+34 912 345 678',
                    'Calle de la Música 123, Madrid', 'Madrid, España',
                    '© 2026 SolfAcademy · Todos los derechos reservados.')");
    }

    /**
     * Vistas y procedimientos del esquema. Se recrean siempre (DROP +
     * CREATE): no guardan estado, solo definen consultas, y así una
     * instalación existente queda alineada con database/solfacademy.sql.
     *
     * También retira los objetos que ya no usa la aplicación
     * (v_horarios_grupos y sp_actualizar_pago).
     */
    public static function objetos(PDO $pdo): void
    {
        // ---- Limpieza de objetos retirados del esquema ----
        $pdo->exec('DROP VIEW IF EXISTS v_grupos_completos');
        $pdo->exec('DROP VIEW IF EXISTS v_horarios_grupos');
        $pdo->exec('DROP PROCEDURE IF EXISTS sp_actualizar_pago');

        // ---- Vistas ----
        $pdo->exec('DROP VIEW IF EXISTS v_inscripciones_detalle');
        $pdo->exec('CREATE VIEW v_inscripciones_detalle AS
            SELECT ins.id_inscripcion,
                   CONCAT(e.nombre, \' \', e.apellido) AS estudiante,
                   c.nombre AS curso,
                   ins.fecha,
                   ins.estado,
                   ins.precio_inscripcion,
                   COALESCE(p.total_pagado, 0) AS total_pagado,
                   CASE
                       WHEN ins.precio_inscripcion IS NULL THEN NULL
                       ELSE ROUND(ins.precio_inscripcion - COALESCE(p.total_pagado, 0), 2)
                   END AS saldo_pendiente,
                   ins.observaciones
            FROM inscripcion ins
            JOIN estudiante e ON e.id_estudiante = ins.id_estudiante
            JOIN grupo g ON g.id_grupo = ins.id_grupo
            JOIN curso c ON c.id_curso = g.id_curso
            LEFT JOIN (SELECT id_inscripcion, SUM(monto_pagado) AS total_pagado
                       FROM pago
                       WHERE estado = \'Pagado\'
                       GROUP BY id_inscripcion) p ON p.id_inscripcion = ins.id_inscripcion');

        $pdo->exec('DROP VIEW IF EXISTS v_asistencia_estudiante');
        $pdo->exec('CREATE VIEW v_asistencia_estudiante AS
            SELECT ins.id_inscripcion,
                   CONCAT(e.nombre, \' \', e.apellido) AS estudiante,
                   c.nombre AS curso,
                   COUNT(*) AS total_clases,
                   SUM(ase.presente = \'S\') AS presentes,
                   ROUND(SUM(ase.presente = \'S\') / NULLIF(COUNT(*), 0) * 100, 2) AS porcentaje_asistencia
            FROM asistencia ase
            JOIN inscripcion ins ON ins.id_inscripcion = ase.id_inscripcion
            JOIN estudiante e ON e.id_estudiante = ins.id_estudiante
            JOIN grupo g ON g.id_grupo = ins.id_grupo
            JOIN curso c ON c.id_curso = g.id_curso
            GROUP BY ins.id_inscripcion, e.nombre, e.apellido, c.nombre');

        $pdo->exec('DROP VIEW IF EXISTS v_calificaciones_estudiante');
        $pdo->exec('CREATE VIEW v_calificaciones_estudiante AS
            SELECT ins.id_inscripcion,
                   CONCAT(e.nombre, \' \', e.apellido) AS estudiante,
                   c.nombre AS curso,
                   ev.tipo,
                   ev.fecha_evaluacion,
                   ev.calificacion,
                   ROUND(AVG(ev.calificacion) OVER (PARTITION BY ins.id_inscripcion), 2) AS promedio
            FROM evaluacion ev
            JOIN inscripcion ins ON ins.id_inscripcion = ev.id_inscripcion
            JOIN estudiante e ON e.id_estudiante = ins.id_estudiante
            JOIN grupo g ON g.id_grupo = ins.id_grupo
            JOIN curso c ON c.id_curso = g.id_curso');

        // ---- Procedimientos almacenados ----
        $pdo->exec('DROP PROCEDURE IF EXISTS sp_verificar_disponibilidad_grupo');
        $pdo->exec('CREATE PROCEDURE sp_verificar_disponibilidad_grupo (
            IN  p_id_grupo  INT,
            OUT p_lugares   INT
        )
        BEGIN
            SELECT g.capacidad_maxima - COUNT(ins.id_inscripcion)
            INTO   p_lugares
            FROM   grupo g
            LEFT JOIN inscripcion ins ON ins.id_grupo = g.id_grupo
                                     AND ins.estado IN (\'Activa\', \'Pendiente\')
            WHERE  g.id_grupo = p_id_grupo
            GROUP  BY g.id_grupo, g.capacidad_maxima;
        END');

        $pdo->exec('DROP PROCEDURE IF EXISTS sp_registrar_inscripcion');
        $pdo->exec('CREATE PROCEDURE sp_registrar_inscripcion (
            IN  p_id_estudiante   INT UNSIGNED,
            IN  p_id_grupo        INT UNSIGNED,
            IN  p_precio_ofertado DECIMAL(8,2),
            IN  p_id_descuento    INT UNSIGNED,
            IN  p_observaciones   VARCHAR(255),
            IN  p_id_usuario      INT UNSIGNED,
            OUT p_id_inscripcion  INT UNSIGNED,
            OUT p_success         BOOLEAN,
            OUT p_message         VARCHAR(255)
        )
        BEGIN
            DECLARE v_id_curso      INT UNSIGNED DEFAULT NULL;
            DECLARE v_lugares       INT DEFAULT NULL;
            DECLARE v_precio_base   DECIMAL(8,2) DEFAULT NULL;
            DECLARE v_estado_grupo  VARCHAR(20)  DEFAULT NULL;
            DECLARE v_activo        TINYINT DEFAULT 0;
            DECLARE v_disponible    TINYINT DEFAULT 0;
            DECLARE v_estado_est    VARCHAR(20)  DEFAULT NULL;
            DECLARE v_duplicada     VARCHAR(20)  DEFAULT NULL;
            DECLARE v_precio_final  DECIMAL(8,2) DEFAULT NULL;
            DECLARE v_d_activo      TINYINT DEFAULT NULL;
            DECLARE v_d_inicio      DATE DEFAULT NULL;
            DECLARE v_d_fin         DATE DEFAULT NULL;
            DECLARE v_d_usos_disp   INT DEFAULT NULL;
            DECLARE v_d_usos_real   INT DEFAULT 0;
            DECLARE v_d_curso       INT UNSIGNED DEFAULT NULL;

            DECLARE EXIT HANDLER FOR SQLEXCEPTION
            BEGIN
                ROLLBACK;
                SET p_id_inscripcion = NULL;
                SET p_success = FALSE;
                SET p_message = \'Error inesperado al registrar la inscripción\';
            END;

            SET p_id_inscripcion = NULL;

            START TRANSACTION;

            SELECT id_curso INTO v_id_curso FROM grupo WHERE id_grupo = p_id_grupo;
            IF v_id_curso IS NULL THEN
                SET p_success = FALSE;
                SET p_message = \'El grupo o estudiante ya no existe.\';
                ROLLBACK;
            ELSE
                SELECT activo, disponible, precio
                INTO   v_activo, v_disponible, v_precio_base
                FROM   curso WHERE id_curso = v_id_curso FOR UPDATE;

                SELECT estado INTO v_estado_est
                FROM   estudiante WHERE id_estudiante = p_id_estudiante FOR UPDATE;

                SELECT g.capacidad_maxima - COUNT(ins.id_inscripcion),
                       MIN(g.estado)
                INTO   v_lugares, v_estado_grupo
                FROM   grupo g
                LEFT JOIN inscripcion ins ON ins.id_grupo = g.id_grupo
                                         AND ins.estado IN (\'Activa\', \'Pendiente\')
                WHERE  g.id_grupo = p_id_grupo
                GROUP  BY g.id_grupo, g.capacidad_maxima
                FOR UPDATE;

                IF v_estado_est IS NULL OR v_estado_est <> \'Activo\' THEN
                    SET p_success = FALSE;
                    SET p_message = \'El estudiante está suspendido.\';
                    ROLLBACK;

                ELSEIF v_estado_grupo <> \'activo\' OR v_activo = 0 OR v_disponible = 0 THEN
                    SET p_success = FALSE;
                    SET p_message = \'El grupo ya no admite nuevas matrículas.\';
                    ROLLBACK;

                ELSEIF v_lugares <= 0 THEN
                    SET p_success = FALSE;
                    SET p_message = \'Este grupo ya no tiene lugares disponibles.\';
                    ROLLBACK;

                ELSE
                    SELECT m.estado
                    INTO   v_duplicada
                    FROM   inscripcion m
                    JOIN   grupo g ON g.id_grupo = m.id_grupo
                    WHERE  m.id_estudiante = p_id_estudiante
                      AND  g.id_curso = v_id_curso
                      AND  m.estado IN (\'Activa\', \'Pendiente\')
                    LIMIT 1;

                    IF v_duplicada IS NOT NULL THEN
                        SET p_success = FALSE;
                        SET p_message = IF(v_duplicada = \'Pendiente\',
                            \'Ya tienes una solicitud pendiente para este curso.\',
                            \'Ya estás matriculado en este curso.\');
                        ROLLBACK;

                    ELSE
                        SET v_precio_final = p_precio_ofertado;

                        IF p_id_descuento IS NOT NULL THEN
                            SELECT activo, fecha_inicio, fecha_fin,
                                   usos_disponibles, usos_realizados, id_curso
                            INTO   v_d_activo, v_d_inicio, v_d_fin,
                                   v_d_usos_disp, v_d_usos_real, v_d_curso
                            FROM   descuento
                            WHERE  id_descuento = p_id_descuento
                            FOR UPDATE;

                            IF v_d_activo IS NULL OR v_d_activo <> 1
                               OR CURDATE() < v_d_inicio OR CURDATE() > v_d_fin
                               OR (v_d_usos_disp <> -1 AND v_d_usos_real >= v_d_usos_disp)
                               OR (v_d_curso IS NOT NULL AND v_d_curso <> v_id_curso) THEN
                                SET p_id_descuento = NULL;
                                SET v_precio_final = v_precio_base;
                            ELSE
                                UPDATE descuento
                                SET    usos_realizados = usos_realizados + 1
                                WHERE  id_descuento = p_id_descuento;
                            END IF;
                        END IF;

                        INSERT INTO inscripcion (id_estudiante, id_grupo, fecha, estado,
                                                 precio_inscripcion, observaciones, id_descuento)
                        VALUES (p_id_estudiante, p_id_grupo, CURDATE(), \'Pendiente\',
                                v_precio_final, p_observaciones, p_id_descuento);

                        SET p_id_inscripcion = LAST_INSERT_ID();

                        INSERT INTO inscripcion_auditoria (id_inscripcion, estado_anterior,
                                                           estado_nuevo, motivo, id_usuario)
                        VALUES (p_id_inscripcion, NULL, \'Pendiente\',
                                \'Solicitud creada desde el portal del estudiante\', p_id_usuario);

                        SET p_success = TRUE;
                        SET p_message = \'Solicitud enviada. Administración revisará los requisitos antes de activarla.\';
                        COMMIT;
                    END IF;
                END IF;
            END IF;
        END');
    }

    public static function aplicar(PDO $pdo): void
    {
        self::columnas($pdo);
        self::objetos($pdo);
        // También protege el procedimiento de matrícula existente y futuras
        // escrituras SQL, sin reemplazar procedimientos instalados.
        foreach (['INSERT' => 'bi_inscripcion_estudiante_activo', 'UPDATE' => 'bu_inscripcion_estudiante_activo'] as $evento => $nombre) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema = DATABASE() AND trigger_name = :nombre');
            $stmt->execute(['nombre' => $nombre]);
            if (!(int) $stmt->fetchColumn()) {
                $condicion = $evento === 'UPDATE'
                    ? "NEW.estado IN ('Activa','Pendiente') AND (OLD.estado NOT IN ('Activa','Pendiente') OR NEW.id_estudiante <> OLD.id_estudiante OR NEW.id_grupo <> OLD.id_grupo)"
                    : "NEW.estado IN ('Activa','Pendiente')";
                $pdo->exec("CREATE TRIGGER $nombre BEFORE $evento ON inscripcion FOR EACH ROW
                    BEGIN
                        DECLARE v_estado VARCHAR(20) DEFAULT NULL;
                        IF $condicion THEN
                            SELECT estado INTO v_estado FROM estudiante WHERE id_estudiante = NEW.id_estudiante FOR UPDATE;
                            IF v_estado IS NULL OR v_estado <> 'Activo' THEN
                                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No se puede matricular a un estudiante suspendido o inexistente';
                            END IF;
                        END IF;
                    END");
            }
        }
    }
}
