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

    public static function aplicar(PDO $pdo): void
    {
        self::columnas($pdo);
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
