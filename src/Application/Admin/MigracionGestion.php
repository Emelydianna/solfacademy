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
            ],
            'usuario' => [
                'version_sesion' => 'INT UNSIGNED NOT NULL DEFAULT 0',
                'reactivar_tras_suspension' => 'TINYINT(1) NOT NULL DEFAULT 0',
            ],
            'aula' => [
                'tipo_inactividad' => "ENUM('General','Mantenimiento') NOT NULL DEFAULT 'General'",
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
