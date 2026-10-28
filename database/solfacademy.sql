-- ============================================================
--  SolfAcademy — Esquema de base de datos + datos de prueba
--  MySQL / MariaDB (utf8mb4)
--
--  Basado en el diagrama entidad-relación del proyecto:
--  CATEGORÍA, INSTRUMENTO, INSTRUCTOR, INSTRUCTOR-INSTRUMENTO,
--  CURSO, REQUISITO CURSO, DESCUENTO, AULA, GRUPO, HORARIO,
--  ESTUDIANTE, USUARIO, INSCRIPCIÓN, EVALUACIÓN, ASISTENCIA, PAGO,
--  PRÁCTICA ESTUDIANTE.
--
--  Ampliaciones propias de la implementación (fase 2):
--  CABINA, RESERVA CABINA, RESERVA CLASE EXTRA (reservas del portal
--  del estudiante) y ESTUDIANTE FOTO, ESTUDIANTE PREFERENCIAS,
--  ESTUDIANTE INSTRUMENTO HISTORIAL (perfil del estudiante).
--
--  Notas respecto al diagrama original:
--   · usuario incluye "password_hash" (autenticación con
--     password_hash() de PHP) y "id_instructor" para que un
--     instructor pueda tener cuenta.
--   · grupo incluye "capacidad_maxima" y "estado" para controlar
--     el cupo y el ciclo de vida del grupo.
--   · El estado de pago NO se guarda duplicado en inscripcion:
--     se deriva de la suma de la tabla pago (fuente única de
--     verdad). Solo se congela "precio_inscripcion" al matricular.
--   · Se añaden vistas y procedimientos almacenados que usan (o
--     usarán) el portal y el panel administrativo.
--
--  Importar desde la raíz del proyecto:
--      mysql -u valet solfacademy < database/solfacademy.sql
--  o desde phpMyAdmin / Workbench seleccionando este archivo.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Base de datos ------------------------------------------------
CREATE DATABASE IF NOT EXISTS solfacademy
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE solfacademy;

-- Borra las tablas si ya existieren (re-importable) -----------
DROP TABLE IF EXISTS inscripcion_auditoria;
DROP TABLE IF EXISTS pago;
DROP TABLE IF EXISTS asistencia;
DROP TABLE IF EXISTS evaluacion;
DROP TABLE IF EXISTS inscripcion;
DROP TABLE IF EXISTS practica_estudiante;
DROP TABLE IF EXISTS reserva_clase_extra;
DROP TABLE IF EXISTS reserva_cabina;
DROP TABLE IF EXISTS cabina;
DROP TABLE IF EXISTS estudiante_instrumento_historial;
DROP TABLE IF EXISTS estudiante_preferencias;
DROP TABLE IF EXISTS estudiante_foto;
DROP TABLE IF EXISTS usuario;
DROP TABLE IF EXISTS estudiante;
DROP TABLE IF EXISTS horario;
DROP TABLE IF EXISTS grupo;
DROP TABLE IF EXISTS aula;
DROP TABLE IF EXISTS descuento;
DROP TABLE IF EXISTS requisito_curso;
DROP TABLE IF EXISTS curso;
DROP TABLE IF EXISTS instructor_instrumento;
DROP TABLE IF EXISTS instructor;
DROP TABLE IF EXISTS instrumento;
DROP TABLE IF EXISTS categoria;

-- ============================================================
--  CATÁLOGO
-- ============================================================

CREATE TABLE categoria (
    id_categoria INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre       VARCHAR(100) NOT NULL,
    descripcion  VARCHAR(255) NULL,
    PRIMARY KEY (id_categoria),
    UNIQUE KEY uq_categoria_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE instrumento (
    id_instrumento  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_categoria    INT UNSIGNED NOT NULL,
    nombre          VARCHAR(100) NOT NULL,
    descripcion     TEXT NULL,
    precio_alquiler DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    disponible      TINYINT(1) NOT NULL DEFAULT 1,
    imagen          VARCHAR(255) NULL,
    PRIMARY KEY (id_instrumento),
    CONSTRAINT fk_instrumento_categoria FOREIGN KEY (id_categoria)
        REFERENCES categoria (id_categoria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE instructor (
    id_instructor      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre             VARCHAR(100) NOT NULL,
    apellido           VARCHAR(100) NOT NULL,
    email              VARCHAR(150) NULL,
    telefono           VARCHAR(20) NULL,
    foto               VARCHAR(255) NULL,
    anios_experiencia  TINYINT UNSIGNED NOT NULL DEFAULT 0,
    activo             TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id_instructor),
    UNIQUE KEY uq_instructor_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Relación N:M INSTRUCTOR <-> INSTRUMENTO
CREATE TABLE instructor_instrumento (
    id_instructor         INT UNSIGNED NOT NULL,
    id_instrumento        INT UNSIGNED NOT NULL,
    anios_experiencia_i   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    nivel_dominio         ENUM('principiante','intermedio','avanzado') NOT NULL DEFAULT 'intermedio',
    PRIMARY KEY (id_instrumento, id_instructor),
    CONSTRAINT fk_ii_instructor FOREIGN KEY (id_instructor)
        REFERENCES instructor (id_instructor),
    CONSTRAINT fk_ii_instrumento FOREIGN KEY (id_instrumento)
        REFERENCES instrumento (id_instrumento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE curso (
    id_curso          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_instrumento    INT UNSIGNED NOT NULL,
    nombre            VARCHAR(150) NOT NULL,
    descripcion       TEXT NULL,
    nivel             ENUM('Principiante','Intermedio','Avanzado') NOT NULL,
    duracion_semanas  INT UNSIGNED NULL,
    precio            DECIMAL(8,2) NOT NULL,
    disponible        TINYINT(1) NOT NULL DEFAULT 1,
    activo            TINYINT(1) NOT NULL DEFAULT 1,
    max_grupos        INT UNSIGNED NOT NULL DEFAULT 2,
    PRIMARY KEY (id_curso),
    CONSTRAINT fk_curso_instrumento FOREIGN KEY (id_instrumento)
        REFERENCES instrumento (id_instrumento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE requisito_curso (
    id_requisito            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_curso                INT UNSIGNED NOT NULL,
    nivel_minimo            VARCHAR(100) NULL,
    edad_minima             TINYINT UNSIGNED NOT NULL DEFAULT 14,
    edad_maxima             TINYINT UNSIGNED NOT NULL DEFAULT 99,
    conocimientos_previos   VARCHAR(255) NULL,
    necesita_instrumento    TINYINT(1) NOT NULL DEFAULT 0,
    puede_alquilar          TINYINT(1) NOT NULL DEFAULT 1,
    descripcion_general     TEXT NULL,
    PRIMARY KEY (id_requisito),
    UNIQUE KEY uq_requisito_curso (id_curso),
    CONSTRAINT fk_requisito_curso FOREIGN KEY (id_curso)
        REFERENCES curso (id_curso)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Descuento global (id_curso NULL) o de un curso concreto.
-- Se aplica porcentaje O monto fijo (nunca ambos): CHECK.
-- usos_disponibles = -1 significa "sin límite".
CREATE TABLE descuento (
    id_descuento      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_curso          INT UNSIGNED NULL,
    codigo            VARCHAR(30) NOT NULL,
    descripcion       VARCHAR(200) NULL,
    porcentaje        DECIMAL(5,2) NULL,
    monto_fijo        DECIMAL(8,2) NULL,
    fecha_inicio      DATE NOT NULL,
    fecha_fin         DATE NOT NULL,
    usos_disponibles  INT NOT NULL DEFAULT -1,
    usos_realizados   INT UNSIGNED NOT NULL DEFAULT 0,
    activo            TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id_descuento),
    UNIQUE KEY uq_descuento_codigo (codigo),
    CONSTRAINT fk_descuento_curso FOREIGN KEY (id_curso)
        REFERENCES curso (id_curso),
    CHECK (fecha_fin >= fecha_inicio),
    CHECK ((porcentaje IS NOT NULL) <> (monto_fijo IS NOT NULL)),
    CHECK (porcentaje IS NULL OR (porcentaje > 0 AND porcentaje <= 100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  GRUPOS Y HORARIOS
-- ============================================================

CREATE TABLE aula (
    id_aula     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre      VARCHAR(50) NOT NULL,
    capacidad   TINYINT UNSIGNED NOT NULL,
    recursos    VARCHAR(255) NULL,
    ubicacion   VARCHAR(150) NULL,
    activa      TINYINT(1) NOT NULL DEFAULT 1,
    tipo_inactividad ENUM('General','Mantenimiento') NOT NULL DEFAULT 'General',
    PRIMARY KEY (id_aula)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE grupo (
    id_grupo        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_curso        INT UNSIGNED NOT NULL,
    id_instructor   INT UNSIGNED NOT NULL,
    id_aula         INT UNSIGNED NOT NULL,
    capacidad_maxima INT UNSIGNED NOT NULL DEFAULT 12,
    fecha_inicio    DATE NOT NULL,
    fecha_fin       DATE NULL,
    estado          ENUM('planeado','activo','finalizado','cancelado') NOT NULL DEFAULT 'activo',
    PRIMARY KEY (id_grupo),
    CONSTRAINT fk_grupo_curso FOREIGN KEY (id_curso)
        REFERENCES curso (id_curso),
    CONSTRAINT fk_grupo_instructor FOREIGN KEY (id_instructor)
        REFERENCES instructor (id_instructor),
    CONSTRAINT fk_grupo_aula FOREIGN KEY (id_aula)
        REFERENCES aula (id_aula),
    CHECK (fecha_fin IS NULL OR fecha_fin >= fecha_inicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE horario (
    id_horario   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_grupo     INT UNSIGNED NOT NULL,
    dia_semana   ENUM('Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo') NOT NULL,
    hora_inicio  TIME NOT NULL,
    hora_fin     TIME NOT NULL,
    PRIMARY KEY (id_horario),
    CONSTRAINT fk_horario_grupo FOREIGN KEY (id_grupo)
        REFERENCES grupo (id_grupo),
    CHECK (hora_fin > hora_inicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  ESTUDIANTES, USUARIOS Y MATRÍCULA
-- ============================================================

CREATE TABLE estudiante (
    id_estudiante     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre            VARCHAR(100) NOT NULL,
    apellido          VARCHAR(100) NOT NULL,
    email             VARCHAR(150) NOT NULL,
    telefono          VARCHAR(20) NULL,
    fecha_inscripcion DATE NULL,
    direccion         VARCHAR(255) NULL,
    fecha_nacimiento  DATE NULL,
    estado            ENUM('Activo','Suspendido') NOT NULL DEFAULT 'Activo',
    suspension_fecha  DATETIME NULL,
    suspension_motivo VARCHAR(255) NULL,
    PRIMARY KEY (id_estudiante),
    UNIQUE KEY uq_estudiante_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE usuario (
    id_usuario    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_estudiante INT UNSIGNED NULL,
    id_instructor INT UNSIGNED NULL,
    email_usuario VARCHAR(150) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    rol           ENUM('estudiante','instructor','admin') NOT NULL DEFAULT 'estudiante',
    activo        TINYINT(1) NOT NULL DEFAULT 1,
    ultimo_acceso DATETIME NULL,
    version_sesion INT UNSIGNED NOT NULL DEFAULT 0,
    reactivar_tras_suspension TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id_usuario),
    UNIQUE KEY uq_usuario_email (email_usuario),
    CONSTRAINT fk_usuario_estudiante FOREIGN KEY (id_estudiante)
        REFERENCES estudiante (id_estudiante),
    CONSTRAINT fk_usuario_instructor FOREIGN KEY (id_instructor)
        REFERENCES instructor (id_instructor)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inscripcion (
    id_inscripcion     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_estudiante      INT UNSIGNED NOT NULL,
    id_grupo           INT UNSIGNED NOT NULL,
    fecha              DATE NOT NULL,
    estado             ENUM('Pendiente','Activa','Cancelada','Finalizada') NOT NULL DEFAULT 'Activa',
    precio_inscripcion DECIMAL(8,2) NULL,   -- precio congelado al matricular
    observaciones      TEXT NULL,
    revisado_por       INT UNSIGNED NULL,
    fecha_revision     DATETIME NULL,
    motivo_revision    VARCHAR(255) NULL,
    PRIMARY KEY (id_inscripcion),
    CONSTRAINT fk_inscripcion_estudiante FOREIGN KEY (id_estudiante)
        REFERENCES estudiante (id_estudiante),
    CONSTRAINT fk_inscripcion_grupo FOREIGN KEY (id_grupo)
        REFERENCES grupo (id_grupo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- El estado de pago de una inscripción se calcula siempre
-- desde esta tabla (SUM(monto_pagado) WHERE estado='Pagado').
CREATE TABLE pago (
    id_pago         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_inscripcion  INT UNSIGNED NOT NULL,
    fecha_pago      DATE NOT NULL,
    monto_pagado    DECIMAL(8,2) NOT NULL,
    concepto        VARCHAR(60) NOT NULL DEFAULT 'Mensualidad',
    metodo          VARCHAR(40) NULL,
    estado          ENUM('Pagado','Pendiente','Fallido') NOT NULL DEFAULT 'Pagado',
    referencia_pago VARCHAR(100) NULL,
    comprobante_url VARCHAR(255) NULL,
    observaciones   TEXT NULL,
    creado_por      INT UNSIGNED NULL,      -- usuario administrador que lo registró
    PRIMARY KEY (id_pago),
    CONSTRAINT fk_pago_inscripcion FOREIGN KEY (id_inscripcion)
        REFERENCES inscripcion (id_inscripcion),
    CONSTRAINT fk_pago_usuario FOREIGN KEY (creado_por)
        REFERENCES usuario (id_usuario),
    CHECK (monto_pagado > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Historial de cambios de estado de una matrícula; nunca se borra.
CREATE TABLE inscripcion_auditoria (
    id_auditoria     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_inscripcion   INT UNSIGNED NOT NULL,
    estado_anterior  VARCHAR(20) NULL,
    estado_nuevo     VARCHAR(20) NOT NULL,
    motivo           VARCHAR(255) NULL,
    id_usuario       INT UNSIGNED NOT NULL,
    fecha            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_auditoria),
    KEY idx_auditoria_inscripcion (id_inscripcion),
    CONSTRAINT fk_auditoria_inscripcion FOREIGN KEY (id_inscripcion)
        REFERENCES inscripcion (id_inscripcion),
    CONSTRAINT fk_auditoria_usuario FOREIGN KEY (id_usuario)
        REFERENCES usuario (id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE evaluacion (
    id_evaluacion          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_inscripcion         INT UNSIGNED NOT NULL,
    tipo                   VARCHAR(50) NOT NULL,
    fecha_evaluacion       DATE NULL,
    calificacion           DECIMAL(4,2) NULL,   -- escala 0 a 10
    descripcion            VARCHAR(255) NULL,
    observaciones_profesor TEXT NULL,
    PRIMARY KEY (id_evaluacion),
    CONSTRAINT fk_evaluacion_inscripcion FOREIGN KEY (id_inscripcion)
        REFERENCES inscripcion (id_inscripcion),
    CHECK (calificacion IS NULL OR (calificacion >= 0 AND calificacion <= 10))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE asistencia (
    id_asistencia  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_inscripcion INT UNSIGNED NOT NULL,
    fecha_clase    DATE NOT NULL,
    presente       ENUM('S','N') NOT NULL DEFAULT 'N',
    justificacion  VARCHAR(255) NULL,
    observaciones  TEXT NULL,
    PRIMARY KEY (id_asistencia),
    UNIQUE KEY uq_asistencia_inscripcion_fecha (id_inscripcion, fecha_clase),
    CONSTRAINT fk_asistencia_inscripcion FOREIGN KEY (id_inscripcion)
        REFERENCES inscripcion (id_inscripcion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Registro de práctica individual (dashboard del portal)
CREATE TABLE practica_estudiante (
    id_practica   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_estudiante INT UNSIGNED NOT NULL,
    fecha         DATE NOT NULL,
    horas         DECIMAL(4,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (id_practica),
    UNIQUE KEY uq_practica_estudiante_fecha (id_estudiante, fecha),
    CONSTRAINT fk_practica_estudiante FOREIGN KEY (id_estudiante)
        REFERENCES estudiante (id_estudiante)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  PERFIL DEL ESTUDIANTE
--  (foto, preferencias musicales e historial de instrumentos)
-- ============================================================

-- Última foto subida; el portal usa REPLACE sobre la clave única
-- para que cada estudiante solo tenga una.
CREATE TABLE estudiante_foto (
    id_foto       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_estudiante INT UNSIGNED NOT NULL,
    ruta          VARCHAR(255) NOT NULL,
    fecha_subida  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_foto),
    UNIQUE KEY uq_est_foto (id_estudiante),
    CONSTRAINT fk_foto_est FOREIGN KEY (id_estudiante)
        REFERENCES estudiante (id_estudiante)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE estudiante_preferencias (
    id_pref            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_estudiante      INT UNSIGNED NOT NULL,
    generos_favoritos  VARCHAR(255) NULL,
    objetivo_academia  VARCHAR(255) NULL,
    PRIMARY KEY (id_pref),
    UNIQUE KEY uq_pref_est (id_estudiante),
    CONSTRAINT fk_pref_est FOREIGN KEY (id_estudiante)
        REFERENCES estudiante (id_estudiante)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Qué ha estudiado el alumno antes (se muestra en su ficha)
CREATE TABLE estudiante_instrumento_historial (
    id_historial INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_estudiante INT UNSIGNED NOT NULL,
    instrumento  VARCHAR(100) NOT NULL,
    periodo      VARCHAR(100) NOT NULL,
    nivel        VARCHAR(100) NOT NULL,
    orden        INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id_historial),
    KEY idx_hist_est (id_estudiante),
    CONSTRAINT fk_hist_estudiante FOREIGN KEY (id_estudiante)
        REFERENCES estudiante (id_estudiante)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  CABINAS Y RESERVAS
--  (reserva_clase_extra también toca grupo e instructor: el
--  estudiante pide una clase extra con el profesor de su grupo)
-- ============================================================

CREATE TABLE cabina (
    id_cabina      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre         VARCHAR(100) NOT NULL,
    tipo           VARCHAR(50) NOT NULL DEFAULT 'piano',
    capacidad      TINYINT UNSIGNED NOT NULL DEFAULT 1,
    estado         ENUM('Activa','Inactiva') NOT NULL DEFAULT 'Activa',
    observaciones  TEXT NULL,
    PRIMARY KEY (id_cabina)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reserva_cabina (
    id_reserva         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_estudiante      INT UNSIGNED NOT NULL,
    id_cabina          INT UNSIGNED NOT NULL,
    fecha              DATE NOT NULL,
    hora_inicio        TIME NOT NULL,
    hora_fin           TIME NOT NULL,
    estado             ENUM('Pendiente','Confirmada','Cancelada','Asistida','No asistió') NOT NULL DEFAULT 'Pendiente',
    motivo             VARCHAR(255) NULL,
    fecha_creacion     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_reserva),
    -- Una cabina no puede estar ocupada dos veces en el mismo hueco
    UNIQUE KEY uq_cabina_fecha_hora (id_cabina, fecha, hora_inicio),
    KEY fk_reserva_cabina_estudiante (id_estudiante),
    CONSTRAINT fk_reserva_cabina_cabina FOREIGN KEY (id_cabina)
        REFERENCES cabina (id_cabina),
    CONSTRAINT fk_reserva_cabina_estudiante FOREIGN KEY (id_estudiante)
        REFERENCES estudiante (id_estudiante),
    CHECK (hora_fin > hora_inicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reserva_clase_extra (
    id_reserva          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_estudiante       INT UNSIGNED NOT NULL,
    id_grupo            INT UNSIGNED NOT NULL,
    id_instructor       INT UNSIGNED NOT NULL,
    fecha               DATE NOT NULL,
    hora_inicio         TIME NOT NULL,
    hora_fin            TIME NOT NULL,
    estado              ENUM('Pendiente','Confirmada','Cancelada','Asistida','No asistió') NOT NULL DEFAULT 'Pendiente',
    motivo              VARCHAR(255) NOT NULL,
    observaciones_admin TEXT NULL,
    fecha_creacion      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_reserva),
    KEY fk_reserva_extra_estudiante (id_estudiante),
    KEY fk_reserva_extra_grupo (id_grupo),
    KEY fk_reserva_extra_instructor (id_instructor),
    CONSTRAINT fk_reserva_extra_estudiante FOREIGN KEY (id_estudiante)
        REFERENCES estudiante (id_estudiante),
    CONSTRAINT fk_reserva_extra_grupo FOREIGN KEY (id_grupo)
        REFERENCES grupo (id_grupo),
    CONSTRAINT fk_reserva_extra_instructor FOREIGN KEY (id_instructor)
        REFERENCES instructor (id_instructor),
    CHECK (hora_fin > hora_inicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- Los suspendidos conservan su historial, pero no pueden tomar nuevos cupos.
-- En instalaciones existentes: php scripts/migrar-gestion.php (sin reimportar).
DELIMITER $$
CREATE TRIGGER bi_inscripcion_estudiante_activo BEFORE INSERT ON inscripcion FOR EACH ROW
BEGIN
    DECLARE v_estado VARCHAR(20) DEFAULT NULL;
    IF NEW.estado IN ('Activa','Pendiente') THEN
        SELECT estado INTO v_estado FROM estudiante WHERE id_estudiante = NEW.id_estudiante FOR UPDATE;
        IF v_estado IS NULL OR v_estado <> 'Activo' THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No se puede matricular a un estudiante suspendido o inexistente';
        END IF;
    END IF;
END$$
CREATE TRIGGER bu_inscripcion_estudiante_activo BEFORE UPDATE ON inscripcion FOR EACH ROW
BEGIN
    DECLARE v_estado VARCHAR(20) DEFAULT NULL;
    IF NEW.estado IN ('Activa','Pendiente') AND (OLD.estado NOT IN ('Activa','Pendiente') OR NEW.id_estudiante <> OLD.id_estudiante OR NEW.id_grupo <> OLD.id_grupo) THEN
        SELECT estado INTO v_estado FROM estudiante WHERE id_estudiante = NEW.id_estudiante FOR UPDATE;
        IF v_estado IS NULL OR v_estado <> 'Activo' THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No se puede matricular a un estudiante suspendido o inexistente';
        END IF;
    END IF;
END$$
DELIMITER ;

-- ============================================================
--  VISTAS
-- ============================================================

-- Se eliminan primero para que el script sea re-importable
DROP VIEW IF EXISTS v_grupos_completos;
DROP VIEW IF EXISTS v_horarios_grupos;
DROP VIEW IF EXISTS v_inscripciones_detalle;
DROP VIEW IF EXISTS v_asistencia_estudiante;
DROP VIEW IF EXISTS v_calificaciones_estudiante;

-- Ocupación de cada grupo (las inscripciones canceladas no
-- ocupan cupo; por eso el LEFT JOIN filtra por estado).
CREATE VIEW v_grupos_completos AS
SELECT g.id_grupo,
       c.nombre AS curso,
       CONCAT(i.nombre, ' ', i.apellido) AS instructor,
       a.nombre AS aula,
       g.capacidad_maxima,
       COUNT(ins.id_inscripcion) AS inscritos,
       g.capacidad_maxima - COUNT(ins.id_inscripcion) AS lugares_disponibles,
       g.fecha_inicio,
       g.fecha_fin,
       g.estado
FROM grupo g
JOIN curso c ON c.id_curso = g.id_curso
JOIN instructor i ON i.id_instructor = g.id_instructor
JOIN aula a ON a.id_aula = g.id_aula
LEFT JOIN inscripcion ins ON ins.id_grupo = g.id_grupo
                         AND ins.estado IN ('Activa', 'Pendiente')
GROUP BY g.id_grupo, g.capacidad_maxima, c.nombre, i.nombre, i.apellido,
         a.nombre, g.fecha_inicio, g.fecha_fin, g.estado;

-- Horario semanal de todos los grupos, ordenado Lunes..Domingo
CREATE VIEW v_horarios_grupos AS
SELECT g.id_grupo,
       c.nombre AS curso,
       h.dia_semana,
       h.hora_inicio,
       h.hora_fin,
       a.nombre AS aula
FROM grupo g
JOIN curso c ON c.id_curso = g.id_curso
JOIN horario h ON h.id_grupo = g.id_grupo
JOIN aula a ON a.id_aula = g.id_aula
ORDER BY FIELD(h.dia_semana, 'Lunes', 'Martes', 'Miércoles', 'Jueves',
               'Viernes', 'Sábado', 'Domingo'), h.hora_inicio;

-- Detalle de matrículas con saldo calculado desde la tabla pago
CREATE VIEW v_inscripciones_detalle AS
SELECT ins.id_inscripcion,
       CONCAT(e.nombre, ' ', e.apellido) AS estudiante,
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
           WHERE estado = 'Pagado'
           GROUP BY id_inscripcion) p ON p.id_inscripcion = ins.id_inscripcion;

-- % de asistencia por matrícula (NULLIF evita división por cero)
CREATE VIEW v_asistencia_estudiante AS
SELECT ins.id_inscripcion,
       CONCAT(e.nombre, ' ', e.apellido) AS estudiante,
       c.nombre AS curso,
       COUNT(*) AS total_clases,
       SUM(ase.presente = 'S') AS presentes,
       ROUND(SUM(ase.presente = 'S') / NULLIF(COUNT(*), 0) * 100, 2) AS porcentaje_asistencia
FROM asistencia ase
JOIN inscripcion ins ON ins.id_inscripcion = ase.id_inscripcion
JOIN estudiante e ON e.id_estudiante = ins.id_estudiante
JOIN grupo g ON g.id_grupo = ins.id_grupo
JOIN curso c ON c.id_curso = g.id_curso
GROUP BY ins.id_inscripcion, e.nombre, e.apellido, c.nombre;

-- Notas con promedio por matrícula (escala 0 a 10)
CREATE VIEW v_calificaciones_estudiante AS
SELECT ins.id_inscripcion,
       CONCAT(e.nombre, ' ', e.apellido) AS estudiante,
       c.nombre AS curso,
       ev.tipo,
       ev.fecha_evaluacion,
       ev.calificacion,
       ROUND(AVG(ev.calificacion) OVER (PARTITION BY ins.id_inscripcion), 2) AS promedio
FROM evaluacion ev
JOIN inscripcion ins ON ins.id_inscripcion = ev.id_inscripcion
JOIN estudiante e ON e.id_estudiante = ins.id_estudiante
JOIN grupo g ON g.id_grupo = ins.id_grupo
JOIN curso c ON c.id_curso = g.id_curso;

-- ============================================================
--  PROCEDIMIENTOS ALMACENADOS
--
--  Ejemplo de uso desde PHP (PDO):
--      $pdo->prepare('CALL sp_registrar_inscripcion(:e, :g, @ok, @msg)');
--      $pdo->query('SELECT @ok AS ok, @msg AS msg')->fetch();
-- ============================================================

DELIMITER $$

-- Se eliminan primero para que el script sea re-importable
DROP PROCEDURE IF EXISTS sp_verificar_disponibilidad_grupo$$
DROP PROCEDURE IF EXISTS sp_registrar_inscripcion$$
DROP PROCEDURE IF EXISTS sp_actualizar_pago$$

-- Lugares libres de un grupo (NULL si el grupo no existe)
CREATE PROCEDURE sp_verificar_disponibilidad_grupo (
    IN  p_id_grupo  INT,
    OUT p_lugares   INT
)
BEGIN
    SELECT g.capacidad_maxima - COUNT(ins.id_inscripcion)
    INTO   p_lugares
    FROM   grupo g
    LEFT JOIN inscripcion ins ON ins.id_grupo = g.id_grupo
                             AND ins.estado IN ('Activa', 'Pendiente')
    WHERE  g.id_grupo = p_id_grupo
    GROUP  BY g.id_grupo, g.capacidad_maxima;
END$$

-- Matrícula segura: valida grupo activo, cupo libre y que el
-- estudiante no esté ya inscrito; congela el precio del curso.
CREATE PROCEDURE sp_registrar_inscripcion (
    IN  p_id_estudiante INT,
    IN  p_id_grupo      INT,
    OUT p_success       BOOLEAN,
    OUT p_message       VARCHAR(255)
)
BEGIN
    DECLARE v_lugares   INT DEFAULT NULL;
    DECLARE v_precio    DECIMAL(8,2) DEFAULT NULL;
    DECLARE v_estado    VARCHAR(20)  DEFAULT NULL;
    DECLARE v_duplicada INT DEFAULT 0;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_success = FALSE;
        SET p_message = 'Error inesperado al registrar la inscripción';
    END;

    START TRANSACTION;

    -- FOR UPDATE bloquea el grupo y sus inscripciones para que dos
    -- matrículas simultáneas no tomen el mismo último cupo.
    SELECT g.capacidad_maxima - COUNT(ins.id_inscripcion),
           MIN(c.precio),
           MIN(g.estado)
    INTO   v_lugares, v_precio, v_estado
    FROM   grupo g
    JOIN   curso c ON c.id_curso = g.id_curso
    LEFT JOIN inscripcion ins ON ins.id_grupo = g.id_grupo
                             AND ins.estado IN ('Activa', 'Pendiente')
    WHERE  g.id_grupo = p_id_grupo
    GROUP  BY g.id_grupo, g.capacidad_maxima
    FOR UPDATE;

    IF v_precio IS NULL THEN
        SET p_success = FALSE;
        SET p_message = 'El grupo indicado no existe';
        ROLLBACK;

    ELSEIF v_estado <> 'activo' THEN
        SET p_success = FALSE;
        SET p_message = CONCAT('El grupo no admite inscripciones (estado: ', v_estado, ')');
        ROLLBACK;

    ELSE
        SELECT COUNT(*)
        INTO   v_duplicada
        FROM   inscripcion
        WHERE  id_estudiante = p_id_estudiante
          AND  id_grupo = p_id_grupo
          AND  estado IN ('Activa', 'Pendiente');

        IF v_duplicada > 0 THEN
            SET p_success = FALSE;
            SET p_message = 'El estudiante ya está inscrito en este grupo';
            ROLLBACK;

        ELSEIF v_lugares <= 0 THEN
            SET p_success = FALSE;
            SET p_message = 'No hay lugares disponibles en el grupo';
            ROLLBACK;

        ELSE
            INSERT INTO inscripcion (id_estudiante, id_grupo, fecha, estado, precio_inscripcion)
            VALUES (p_id_estudiante, p_id_grupo, CURDATE(), 'Activa', v_precio);

            SET p_success = TRUE;
            SET p_message = 'Inscripción registrada';
            COMMIT;
        END IF;
    END IF;
END$$

-- Registra un pago y valida el monto. El saldo de la inscripción
-- nunca se guarda: se consulta con SUM(pagos).
CREATE PROCEDURE sp_actualizar_pago (
    IN  p_id_inscripcion INT,
    IN  p_monto          DECIMAL(8,2),
    IN  p_metodo         VARCHAR(40),
    IN  p_concepto       VARCHAR(60),
    IN  p_referencia     VARCHAR(100),
    IN  p_creado_por     INT UNSIGNED,
    OUT p_success        BOOLEAN,
    OUT p_message        VARCHAR(255)
)
BEGIN
    DECLARE v_id_insc INT DEFAULT NULL;
    DECLARE v_precio  DECIMAL(8,2) DEFAULT NULL;
    DECLARE v_total   DECIMAL(10,2) DEFAULT 0;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_success = FALSE;
        SET p_message = 'Error inesperado al registrar el pago';
    END;

    IF p_monto IS NULL OR p_monto <= 0 THEN
        SET p_success = FALSE;
        SET p_message = 'El monto debe ser mayor a cero';

    ELSE
        START TRANSACTION;

        SELECT id_inscripcion, precio_inscripcion
        INTO   v_id_insc, v_precio
        FROM   inscripcion
        WHERE  id_inscripcion = p_id_inscripcion
        FOR UPDATE;

        IF v_id_insc IS NULL THEN
            SET p_success = FALSE;
            SET p_message = 'La inscripción indicada no existe';
            ROLLBACK;

        ELSE
            INSERT INTO pago (id_inscripcion, fecha_pago, monto_pagado, concepto,
                              metodo, estado, referencia_pago, creado_por)
            VALUES (p_id_inscripcion, CURDATE(), p_monto,
                    COALESCE(p_concepto, 'Mensualidad'), p_metodo, 'Pagado',
                    p_referencia, p_creado_por);

            SELECT COALESCE(SUM(monto_pagado), 0)
            INTO   v_total
            FROM   pago
            WHERE  id_inscripcion = p_id_inscripcion
              AND  estado = 'Pagado';

            SET p_success = TRUE;

            IF v_precio IS NOT NULL AND v_total > v_precio THEN
                SET p_message = CONCAT('Pago registrado. Atención: el total pagado (',
                                       ROUND(v_total, 2),
                                       ') supera el precio de la inscripción (', v_precio, ')');
            ELSEIF v_precio IS NOT NULL AND v_total >= v_precio THEN
                SET p_message = 'Pago registrado. Inscripción saldada';
            ELSE
                SET p_message = 'Pago registrado';
            END IF;

            COMMIT;
        END IF;
    END IF;
END$$

DELIMITER ;

-- ============================================================
--  DATOS DE PRUEBA
--  Alineados con src/Data/SampleData.php (los cursos, categorías
--  e instructores que se ven en el sitio web). Las fechas de los
--  grupos "fase 2" usan CURDATE para que el portal se vea vivo
--  siempre que se re-importe el script.
-- ============================================================

-- Categorías (las 7 tarjetas del homepage) --------------------
INSERT INTO categoria (id_categoria, nombre, descripcion) VALUES
    (1, 'Guitarra',            'Ritmo, arpegio y acordes'),
    (2, 'Piano',               'Técnica y arpegio legato'),
    (3, 'Canto',               'Técnica vocal y respiración'),
    (4, 'Batería',             'Ritmo y coordinación de extremidades'),
    (5, 'Violín',              'Expresión, postura y afinación'),
    (6, 'Clases Particulares', 'A tu ritmo, uno a uno'),
    (7, 'Teoría Musical',      'Configuración, armonía y composición');

-- Instrumentos ------------------------------------------------
INSERT INTO instrumento (id_instrumento, id_categoria, nombre, descripcion, precio_alquiler, disponible, imagen) VALUES
    (1, 1, 'Guitarra',  'Instrumento de cuerda de seis cuerdas', 15.00, 1, NULL),
    (2, 4, 'Batería',   'Set acústico de percusión',             25.00, 1, NULL),
    (3, 3, 'Canto',     'Clases de técnica vocal',               10.00, 1, NULL),
    (4, 2, 'Piano',     'Piano acústico y digital',              30.00, 1, NULL),
    (5, 5, 'Violín',    'Instrumento de cuerda frotada',         20.00, 1, NULL);

-- Instructores ------------------------------------------------
INSERT INTO instructor (id_instructor, nombre, apellido, anios_experiencia, email, telefono, activo) VALUES
    (1, 'Carlos', 'Mendoza', 12, 'carlos.mendoza@solfacademy.com', '22110001', 1),
    (2, 'Sofía',  'Martínez', 8, 'sofia.martinez@solfacademy.com', '22110002', 1),
    (3, 'Mateo',  'Silva',   10, 'mateo.silva@solfacademy.com',    '22110003', 1),
    (4, 'Ana',    'Ríos',     9, 'ana.rios@solfacademy.com',       '22110004', 1),
    (5, 'Diego',  'Torres',  11, 'diego.torres@solfacademy.com',   '22110005', 1);

-- Instructor <-> Instrumento (N:M) ----------------------------
INSERT INTO instructor_instrumento (id_instructor, id_instrumento, anios_experiencia_i, nivel_dominio) VALUES
    (1, 4, 12, 'avanzado'),  -- Carlos -> Piano
    (2, 5,  8, 'avanzado'),  -- Sofía  -> Violín
    (3, 1, 10, 'avanzado'),  -- Mateo  -> Guitarra
    (4, 3,  9, 'avanzado'),  -- Ana    -> Canto
    (5, 2, 11, 'avanzado');  -- Diego  -> Batería

-- Cursos (los 9 del catálogo) ---------------------------------
INSERT INTO curso (id_curso, id_instrumento, nombre, nivel, precio, descripcion, duracion_semanas, disponible, activo, max_grupos) VALUES
    (1, 1, 'Guitarra para Principiantes',                         'Principiante', 49.00, 'Acordes básicos, ritmo y tus primeras canciones',        12, 1, 1, 3),
    (2, 4, 'Piano Clásico I: Fundamentos',                        'Principiante', 55.00, 'Postura, lectura de notas y ejercicios de las dos manos', 12, 1, 1, 3),
    (3, 4, 'Piano Clásico II: Desarrollo de Técnica y Repertorio','Intermedio',   79.00, 'Técnica, arpegios y repertorio de nivel intermedio',      16, 1, 1, 2),
    (4, 3, 'Canto: Técnica Vocal y Respiración',                  'Principiante', 45.00, 'Resonancia, respiración y control de volumen',            10, 1, 1, 3),
    (5, 2, 'Batería: Ritmo y Coordinación',                       'Principiante', 52.00, 'Coordinación, groove y lectura rítmica',                  12, 1, 1, 3),
    (6, 5, 'Violín: Expresión y Afinación',                       'Intermedio',   62.00, 'Postura de arco, afinación y expresión',                  12, 1, 1, 2),
    (7, 1, 'Guitarra Intermedia: Arpegios y Ritmos',              'Intermedio',   59.00, 'Arpegios, ritmos alternados y punteo',                    12, 1, 1, 2),
    (8, 2, 'Batería Avanzada: Grooves y Solos',                   'Avanzado',     69.00, 'Grooves, fills y coordinación avanzada',                  12, 1, 1, 2),
    (9, 4, 'Piano Avanzado: Improvisación',                       'Avanzado',     85.00, 'Armonía aplicada e improvisación sobre estándares',        16, 1, 1, 2);

-- Requisitos de cada curso ------------------------------------
INSERT INTO requisito_curso (id_curso, nivel_minimo, edad_minima, edad_maxima, conocimientos_previos, necesita_instrumento, puede_alquilar, descripcion_general) VALUES
    (1, 'Sin requisitos previos',       14, 99, 'Ninguno',                              0, 1, 'Puerta de entrada a la guitarra'),
    (2, 'Sin requisitos previos',       14, 99, 'Ninguno',                              0, 1, 'No hace falta saber leer música'),
    (3, 'Piano Clásico I aprobado',     14, 99, 'Ejercicios diarios de ambas manos',    0, 1, 'Continuación directa de Piano Clásico I'),
    (4, 'Sin requisitos previos',       14, 99, 'Ninguno',                              0, 1, 'Enfoque en respiración y dicción'),
    (5, 'Sin requisitos previos',       14, 99, 'Ninguno',                              0, 1, 'Se puede practicar con pad de práctica'),
    (6, 'Curso de iniciación aprobado', 14, 99, 'Nociones básicas de postura',           1, 1, 'Recomendable tener violín propio o alquilado'),
    (7, 'Curso de iniciación aprobado', 14, 99, 'Acordes abiertos y rasgueo básico',     1, 1, 'Requiere guitarra propia o alquiler'),
    (8, 'Nivel intermedio aprobado',    16, 99, 'Grooves en 4/4 y lectura rítmica',      1, 0, 'Requiere batería propia o acceso al aula'),
    (9, 'Nivel intermedio aprobado',    16, 99, 'Armonía funcional básica',              0, 1, 'Imprescindible piano propio para practicar');

-- Descuentos (vigentes relativos a hoy) -----------------------
INSERT INTO descuento (id_curso, codigo, descripcion, porcentaje, monto_fijo, fecha_inicio, fecha_fin, usos_disponibles, usos_realizados, activo) VALUES
    (3,    'PRIMAVERA20', '10% en Piano Clásico II',            10.00, NULL,    DATE_SUB(CURDATE(), INTERVAL 30 DAY), DATE_ADD(CURDATE(), INTERVAL 60 DAY),  -1, 0, 1),
    (1,    'NUEVO15',     '15% para nuevas inscripciones',      15.00, NULL,    DATE_SUB(CURDATE(), INTERVAL 15 DAY), DATE_ADD(CURDATE(), INTERVAL 45 DAY),  50, 2, 1),
    (NULL, 'BIENVENIDA30','$30 de descuento en cualquier curso', NULL, 30.00,  DATE_SUB(CURDATE(), INTERVAL 7 DAY),  DATE_ADD(CURDATE(), INTERVAL 30 DAY), 100, 5, 1);

-- Aulas -------------------------------------------------------
INSERT INTO aula (id_aula, nombre, capacidad, recursos, ubicacion, activa) VALUES
    (1, 'Aula 1', 12, 'Pianos y piano de cola',               'Planta baja', 1),
    (2, 'Aula 2', 15, 'Baterías acústicas y percusión',       'Planta baja', 1),
    (3, 'Aula 3', 10, 'Piano de cola, sillas y atriles',      'Primer piso', 1);

-- Grupos ------------------------------------------------------
-- 1-3: grupos históricos (ya finalizados). 4-6: fechas dinámicas
-- para que el portal del estudiante siempre tenga cursos vivos.
INSERT INTO grupo (id_grupo, id_curso, id_instructor, id_aula, capacidad_maxima, fecha_inicio, fecha_fin, estado) VALUES
    (1, 3, 1, 3, 10, '2026-03-02', '2026-05-25', 'finalizado'),                                                    -- Piano Clásico II (curso del prototipo)
    (2, 1, 3, 1, 12, '2026-03-02', '2026-05-25', 'finalizado'),                                                    -- Guitarra para Principiantes
    (3, 4, 4, 1, 10, '2026-03-03', '2026-05-26', 'finalizado'),                                                    -- Canto
    (4, 3, 1, 3, 10, DATE_SUB(CURDATE(), INTERVAL 70 DAY), DATE_ADD(CURDATE(), INTERVAL 20 DAY), 'activo'),        -- Piano Clásico II
    (5, 5, 5, 2, 12, DATE_SUB(CURDATE(), INTERVAL 50 DAY), DATE_ADD(CURDATE(), INTERVAL 35 DAY), 'activo'),        -- Batería
    (6, 6, 2, 1, 10, DATE_SUB(CURDATE(), INTERVAL 30 DAY), DATE_ADD(CURDATE(), INTERVAL 45 DAY), 'activo');        -- Violín

-- Horarios (el grupo 1: Lunes y Miércoles 18:00-19:30) --------
-- El grupo 4 incluye una clase "de hoy" para que el dashboard
-- muestre siempre "Próxima clase: Hoy".
INSERT INTO horario (id_grupo, dia_semana, hora_inicio, hora_fin) VALUES
    (1, 'Lunes',     '18:00', '19:30'),
    (1, 'Miércoles', '18:00', '19:30'),
    (2, 'Lunes',     '10:00', '11:30'),
    (3, 'Martes',    '19:00', '20:30'),
    (4, 'Lunes',     '17:00', '18:30'),
    (4, 'Miércoles', '17:00', '18:30'),
    (4, ELT(WEEKDAY(CURDATE()) + 1, 'Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'), '17:00', '18:30'),
    (5, 'Martes',    '19:00', '20:30'),
    (5, 'Jueves',    '19:00', '20:30'),
    (6, 'Viernes',   '18:00', '19:30');

-- Estudiantes -------------------------------------------------
INSERT INTO estudiante (id_estudiante, nombre, apellido, email, telefono, fecha_inscripcion, direccion) VALUES
    (1, 'Laura',  'Gómez', 'laura.gomez@ejemplo.com',  '600111222', '2026-02-18', 'Col. Escalón, San Salvador'),
    (2, 'Daniel', 'Reyes', 'daniel.reyes@ejemplo.com', '600333444', '2026-02-19', 'Col. Florida, San Salvador'),
    (3, 'Marta',  'López', 'marta.lopez@ejemplo.com',  '600555666', '2026-02-21', 'Santa Tecla'),
    (4, 'Mateo',  'Díaz',  'mateo.diaz@ejemplo.com',   '600777888', DATE_SUB(CURDATE(), INTERVAL 100 DAY), 'San Miguel');

-- Usuarios (contraseña de todos: "solfacademy123") ------------
INSERT INTO usuario (id_usuario, id_estudiante, id_instructor, email_usuario, password_hash, rol, activo, ultimo_acceso) VALUES
    (1, 1,    NULL, 'laura.gomez@ejemplo.com',          '$2y$12$EICJjyzk2SHbCrsr7Nh.8eg4VXDwjxdntzguHelswFWVyubudFHtq', 'estudiante',  1, DATE_SUB(NOW(), INTERVAL 3 DAY)),
    (2, 2,    NULL, 'daniel.reyes@ejemplo.com',         '$2y$12$EICJjyzk2SHbCrsr7Nh.8eg4VXDwjxdntzguHelswFWVyubudFHtq', 'estudiante',  1, NULL),
    (3, 3,    NULL, 'marta.lopez@ejemplo.com',          '$2y$12$EICJjyzk2SHbCrsr7Nh.8eg4VXDwjxdntzguHelswFWVyubudFHtq', 'estudiante',  1, DATE_SUB(NOW(), INTERVAL 2 DAY)),
    (4, NULL, 1,    'carlos.mendoza@solfacademy.com',   '$2y$12$EICJjyzk2SHbCrsr7Nh.8eg4VXDwjxdntzguHelswFWVyubudFHtq', 'instructor', 1, DATE_SUB(NOW(), INTERVAL 5 HOUR)),
    (5, NULL, 2,    'sofia.martinez@solfacademy.com',   '$2y$12$EICJjyzk2SHbCrsr7Nh.8eg4VXDwjxdntzguHelswFWVyubudFHtq', 'instructor', 1, NULL),
    (6, NULL, 3,    'mateo.silva@solfacademy.com',      '$2y$12$EICJjyzk2SHbCrsr7Nh.8eg4VXDwjxdntzguHelswFWVyubudFHtq', 'instructor', 1, DATE_SUB(NOW(), INTERVAL 1 DAY)),
    (7, NULL, NULL, 'admin@solfacademy.com',            '$2y$12$EICJjyzk2SHbCrsr7Nh.8eg4VXDwjxdntzguHelswFWVyubudFHtq', 'admin',      1, DATE_SUB(NOW(), INTERVAL 2 HOUR)),
    (8, 4,    NULL, 'mateo.diaz@ejemplo.com',           '$2y$12$EICJjyzk2SHbCrsr7Nh.8eg4VXDwjxdntzguHelswFWVyubudFHtq', 'estudiante', 1, DATE_SUB(NOW(), INTERVAL 1 HOUR));

-- Inscripciones -----------------------------------------------
-- Los grupos 1-3 ya terminaron, por eso sus matrículas están
-- cerradas; las de Mateo (4..8) son las que alimentan el portal.
INSERT INTO inscripcion (id_inscripcion, id_estudiante, id_grupo, fecha, estado, precio_inscripcion, observaciones) VALUES
    (1, 1, 1, '2026-02-20', 'Finalizada', 79.00, NULL),                       -- Laura -> Piano Clásico II
    (2, 2, 2, '2026-02-21', 'Finalizada', 49.00, NULL),                       -- Daniel -> Guitarra para Principiantes
    (3, 3, 3, '2026-02-22', 'Cancelada',  45.00, 'Se retiró a mitad de curso'),
    (4, 2, 1, '2026-02-25', 'Cancelada',  79.00, NULL),                       -- Daniel también probó Piano II
    (5, 4, 4, DATE_SUB(CURDATE(), INTERVAL 70 DAY),  'Activa', 79.00, NULL),  -- Mateo -> Piano Clásico II
    (6, 4, 2, DATE_SUB(CURDATE(), INTERVAL 100 DAY), 'Finalizada', 49.00, NULL), -- Mateo -> Guitarra (cursada completa)
    (7, 4, 5, DATE_SUB(CURDATE(), INTERVAL 50 DAY),  'Activa', 52.00, NULL),  -- Mateo -> Batería
    (8, 4, 6, DATE_SUB(CURDATE(), INTERVAL 30 DAY),  'Activa', 62.00, NULL),  -- Mateo -> Violín
    (9, 4, 3, CURDATE(), 'Pendiente', 45.00, NULL);                           -- Mateo -> Canto (pendiente de pago)

-- Evaluaciones ------------------------------------------------
INSERT INTO evaluacion (id_inscripcion, tipo, fecha_evaluacion, calificacion, descripcion, observaciones_profesor) VALUES
    (5, 'Parcial de Solfeo',      DATE_SUB(CURDATE(), INTERVAL 40 DAY), 9.00, 'Ritmo, lectura y solfeo oral',        'Muy buena actitud en clase'),
    (5, 'Práctica de Repertorio', DATE_SUB(CURDATE(), INTERVAL 10 DAY), 9.50, 'Pieza clásica de nivel intermedio',   'Interpretación madura y expresiva'),
    (6, 'Examen Final',           DATE_SUB(CURDATE(), INTERVAL 60 DAY), 9.40, 'Acordes, ritmo y canción completa',   'Aprobó con sobresaliente'),
    (7, 'Parcial de Ritmo',       DATE_SUB(CURDATE(), INTERVAL 20 DAY), 8.80, 'Grooves en 4/4 y coordinación',       'Trabajar el cambio de palo'),
    (8, 'Práctica de Técnica',    DATE_SUB(CURDATE(), INTERVAL 10 DAY), 9.30, 'Postura de arco y afinación',         'Progreso constante');

-- Asistencias -------------------------------------------------
INSERT INTO asistencia (id_inscripcion, fecha_clase, presente, justificacion, observaciones) VALUES
    (1, '2026-03-02', 'S', NULL, NULL),
    (1, '2026-03-04', 'S', NULL, NULL),
    (2, '2026-03-02', 'N', 'Enfermedad', 'Avisó por mensaje'),
    (3, '2026-03-03', 'S', NULL, NULL);

-- Asistencias de Mateo (volumen para el % y los KPIs del portal)
-- Piano: 12/13 · Guitarra: 10/10 · Batería: 11/12 · Violín: 9/10
-- Fechas: una cada 2 días hacia atrás desde hoy.
INSERT INTO asistencia (id_inscripcion, fecha_clase, presente, justificacion, observaciones)
SELECT 5, DATE_SUB(CURDATE(), INTERVAL nums.n * 2 DAY), IF(nums.n <= 12, 'S', 'N'),
       IF(nums.n > 12, 'Enfermedad', NULL), IF(nums.n > 12, 'Avisó al instructor', NULL)
FROM (SELECT ROW_NUMBER() OVER (ORDER BY (SELECT NULL)) AS n
      FROM information_schema.columns LIMIT 13) AS nums;

INSERT INTO asistencia (id_inscripcion, fecha_clase, presente, justificacion, observaciones)
SELECT 6, DATE_SUB(CURDATE(), INTERVAL nums.n * 2 DAY), 'S', NULL, NULL
FROM (SELECT ROW_NUMBER() OVER (ORDER BY (SELECT NULL)) AS n
      FROM information_schema.columns LIMIT 10) AS nums;

INSERT INTO asistencia (id_inscripcion, fecha_clase, presente, justificacion, observaciones)
SELECT 7, DATE_SUB(CURDATE(), INTERVAL nums.n * 2 DAY), IF(nums.n <= 11, 'S', 'N'),
       IF(nums.n > 11, 'Compromiso laboral', NULL), IF(nums.n > 11, 'Repondrá la clase', NULL)
FROM (SELECT ROW_NUMBER() OVER (ORDER BY (SELECT NULL)) AS n
      FROM information_schema.columns LIMIT 12) AS nums;

INSERT INTO asistencia (id_inscripcion, fecha_clase, presente, justificacion, observaciones)
SELECT 8, DATE_SUB(CURDATE(), INTERVAL nums.n * 2 DAY), IF(nums.n <= 9, 'S', 'N'),
       IF(nums.n > 9, 'Viaje familiar', NULL), NULL
FROM (SELECT ROW_NUMBER() OVER (ORDER BY (SELECT NULL)) AS n
      FROM information_schema.columns LIMIT 10) AS nums;

-- Pagos -------------------------------------------------------
INSERT INTO pago (id_inscripcion, fecha_pago, monto_pagado, concepto, metodo, estado, referencia_pago, comprobante_url, observaciones, creado_por) VALUES
    (1, '2026-02-20', 79.00, 'Mensualidad',  'Tarjeta de crédito',     'Pagado', NULL,          '/comprobantes/pago-1.pdf', NULL, 7),
    (2, '2026-02-21', 49.00, 'Inscripción',  'Transferencia bancaria', 'Pagado', 'TRF-001234', '/comprobantes/pago-2.pdf', NULL, 7),
    (3, '2026-02-22', 45.00, 'Inscripción',  'Efectivo',               'Pagado', NULL,          NULL,                        'Pago en recepción', 7),
    (5, DATE_SUB(CURDATE(), INTERVAL 70 DAY),  79.00, 'Inscripción', 'Tarjeta de crédito',      'Pagado', NULL,          '/comprobantes/pago-5.pdf', NULL, 7),
    (6, DATE_SUB(CURDATE(), INTERVAL 100 DAY), 49.00, 'Inscripción', 'Transferencia bancaria',  'Pagado', 'TRF-001290', '/comprobantes/pago-6.pdf', NULL, 7),
    (7, DATE_SUB(CURDATE(), INTERVAL 50 DAY),  52.00, 'Mensualidad', 'Tarjeta de crédito',      'Pagado', NULL,          '/comprobantes/pago-7.pdf', NULL, 7),
    (8, DATE_SUB(CURDATE(), INTERVAL 15 DAY),  62.00, 'Mensualidad', 'PayPal',                  'Pendiente', NULL,       NULL,                        'Pendiente de confirmar', NULL),
    (5, DATE_ADD(CURDATE(), INTERVAL 5 DAY),   79.00, 'Mensualidad (próxima)', NULL,             'Pendiente', NULL,       NULL,                        'Próximo vencimiento', NULL);

-- Práctica semanal individual (14.5 horas totales) ------------
INSERT INTO practica_estudiante (id_estudiante, fecha, horas) VALUES
    (4, DATE_SUB(CURDATE(), INTERVAL 6 DAY), 1.50),
    (4, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 2.00),
    (4, DATE_SUB(CURDATE(), INTERVAL 4 DAY), 0.50),
    (4, DATE_SUB(CURDATE(), INTERVAL 3 DAY), 3.00),
    (4, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 2.50),
    (4, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 4.00),
    (4, CURDATE(),                           1.00);

-- Perfil del estudiante: foto, preferencias e historial ----------
-- (La foto no se siembra: los archivos viven en var/uploads, que
--  no está versionado. El portal muestra las iniciales si no hay.)
INSERT INTO estudiante_preferencias (id_estudiante, generos_favoritos, objetivo_academia) VALUES
    (4, 'Jazz, Pop', 'Dominar técnica, ensamble e improvisación');

INSERT INTO estudiante_instrumento_historial (id_estudiante, instrumento, periodo, nivel, orden) VALUES
    (1, 'Piano',             '2022 - Presente', 'Intermedio Avanzado', 1),
    (1, 'Teoría y Armonía',  '2023',            'Básico Completado',   2),
    (1, 'Guitarra Acústica', '2021 - 2022',     'Básico Iniciado',     3);

-- Cabinas de práctica --------------------------------------------
INSERT INTO cabina (id_cabina, nombre, tipo, capacidad, estado, observaciones) VALUES
    (1, 'Cabina Piano 1', 'piano', 1, 'Activa', 'Piano vertical y atril'),
    (2, 'Cabina Piano 2', 'piano', 1, 'Activa', 'Piano digital con cascos'),
    (3, 'Cabina Piano 3', 'piano', 1, 'Activa', 'Piano vertical, junto a la recepción');

-- ============================================================
--  VERIFICACIÓN: Contar registros en cada tabla
-- ============================================================
SELECT 'categoria' AS Tabla, COUNT(*) AS Total FROM categoria
UNION ALL SELECT 'instrumento',          COUNT(*) FROM instrumento
UNION ALL SELECT 'instructor',           COUNT(*) FROM instructor
UNION ALL SELECT 'instructor_instrumento',COUNT(*) FROM instructor_instrumento
UNION ALL SELECT 'curso',                COUNT(*) FROM curso
UNION ALL SELECT 'requisito_curso',      COUNT(*) FROM requisito_curso
UNION ALL SELECT 'descuento',            COUNT(*) FROM descuento
UNION ALL SELECT 'aula',                 COUNT(*) FROM aula
UNION ALL SELECT 'grupo',                COUNT(*) FROM grupo
UNION ALL SELECT 'horario',              COUNT(*) FROM horario
UNION ALL SELECT 'estudiante',           COUNT(*) FROM estudiante
UNION ALL SELECT 'usuario',              COUNT(*) FROM usuario
UNION ALL SELECT 'inscripcion',          COUNT(*) FROM inscripcion
UNION ALL SELECT 'pago',                 COUNT(*) FROM pago
UNION ALL SELECT 'evaluacion',           COUNT(*) FROM evaluacion
UNION ALL SELECT 'asistencia',           COUNT(*) FROM asistencia
UNION ALL SELECT 'practica_estudiante',  COUNT(*) FROM practica_estudiante
UNION ALL SELECT 'estudiante_foto',      COUNT(*) FROM estudiante_foto
UNION ALL SELECT 'estudiante_preferencias', COUNT(*) FROM estudiante_preferencias
UNION ALL SELECT 'estudiante_instrumento_historial', COUNT(*) FROM estudiante_instrumento_historial
UNION ALL SELECT 'cabina',               COUNT(*) FROM cabina
UNION ALL SELECT 'reserva_cabina',       COUNT(*) FROM reserva_cabina
UNION ALL SELECT 'reserva_clase_extra',  COUNT(*) FROM reserva_clase_extra;
