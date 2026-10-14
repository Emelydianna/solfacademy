-- ============================================================
--  SolfAcademy — Esquema de base de datos + datos de prueba
--  MySQL / MariaDB (utf8mb4)
--
--  Basado en el diagrama entidad-relación del proyecto:
--  CATEGORÍA, INSTRUMENTO, INSTRUCTOR, INSTRUCTOR-INSTRUMENTO,
--  CURSO, REQUISITO CURSO, DESCUENTO, AULA, GRUPO, HORARIO,
--  ESTUDIANTE, USUARIO, INSCRIPCIÓN, EVALUACIÓN, ASISTENCIA, PAGO
--
--  Nota: la tabla USUARIO incluye la columna "password_hash"
--  (no aparece en el diagrama, pero es necesaria para el sistema
--  de autenticación con password_hash() de PHP — fase 2).
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
DROP TABLE IF EXISTS pago;
DROP TABLE IF EXISTS asistencia;
DROP TABLE IF EXISTS evaluacion;
DROP TABLE IF EXISTS inscripcion;
DROP TABLE IF EXISTS practica_estudiante;
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
    precio_alquiler DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (id_instrumento),
    CONSTRAINT fk_instrumento_categoria FOREIGN KEY (id_categoria)
        REFERENCES categoria (id_categoria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE instructor (
    id_instructor      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre             VARCHAR(100) NOT NULL,
    apellido           VARCHAR(100) NOT NULL,
    anios_experiencia  TINYINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id_instructor)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Relación N:M INSTRUCTOR <-> INSTRUMENTO
CREATE TABLE instructor_instrumento (
    id_instructor         INT UNSIGNED NOT NULL,
    id_instrumento        INT UNSIGNED NOT NULL,
    anios_experiencia_i   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id_instrumento, id_instructor),
    CONSTRAINT fk_ii_instructor FOREIGN KEY (id_instructor)
        REFERENCES instructor (id_instructor),
    CONSTRAINT fk_ii_instrumento FOREIGN KEY (id_instrumento)
        REFERENCES instrumento (id_instrumento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE curso (
    id_curso       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_instrumento INT UNSIGNED NOT NULL,
    nombre         VARCHAR(150) NOT NULL,
    nivel          ENUM('Principiante','Intermedio','Avanzado') NOT NULL,
    precio         DECIMAL(8,2) NOT NULL,
    PRIMARY KEY (id_curso),
    CONSTRAINT fk_curso_instrumento FOREIGN KEY (id_instrumento)
        REFERENCES instrumento (id_instrumento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE requisito_curso (
    id_requisito  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_curso      INT UNSIGNED NOT NULL,
    nivel_minimo  VARCHAR(100) NULL,
    edad_minima   TINYINT UNSIGNED NOT NULL DEFAULT 14,
    edad_maxima   TINYINT UNSIGNED NOT NULL DEFAULT 99,
    PRIMARY KEY (id_requisito),
    CONSTRAINT fk_requisito_curso FOREIGN KEY (id_curso)
        REFERENCES curso (id_curso)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE descuento (
    id_descuento     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_curso         INT UNSIGNED NOT NULL,
    codigo           VARCHAR(30) NOT NULL,
    porcentaje_monto DECIMAL(6,2) NOT NULL,
    PRIMARY KEY (id_descuento),
    UNIQUE KEY uq_descuento_codigo (codigo),
    CONSTRAINT fk_descuento_curso FOREIGN KEY (id_curso)
        REFERENCES curso (id_curso)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  GRUPOS Y HORARIOS
-- ============================================================

CREATE TABLE aula (
    id_aula    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre     VARCHAR(50) NOT NULL,
    capacidad  TINYINT UNSIGNED NOT NULL,
    recursos   VARCHAR(255) NULL,
    PRIMARY KEY (id_aula)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE grupo (
    id_grupo      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_curso      INT UNSIGNED NOT NULL,
    id_instructor INT UNSIGNED NOT NULL,
    id_aula       INT UNSIGNED NOT NULL,
    fecha_inicio  DATE NOT NULL,
    fecha_fin     DATE NULL,
    PRIMARY KEY (id_grupo),
    CONSTRAINT fk_grupo_curso FOREIGN KEY (id_curso)
        REFERENCES curso (id_curso),
    CONSTRAINT fk_grupo_instructor FOREIGN KEY (id_instructor)
        REFERENCES instructor (id_instructor),
    CONSTRAINT fk_grupo_aula FOREIGN KEY (id_aula)
        REFERENCES aula (id_aula)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE horario (
    id_horario  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_grupo    INT UNSIGNED NOT NULL,
    dia_semana  ENUM('Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo') NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fin    TIME NOT NULL,
    PRIMARY KEY (id_horario),
    CONSTRAINT fk_horario_grupo FOREIGN KEY (id_grupo)
        REFERENCES grupo (id_grupo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  ESTUDIANTES, USUARIOS Y MATRÍCULA
-- ============================================================

CREATE TABLE estudiante (
    id_estudiante INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre        VARCHAR(100) NOT NULL,
    apellido      VARCHAR(100) NOT NULL,
    email         VARCHAR(150) NOT NULL,
    telefono      VARCHAR(20) NULL,
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
    PRIMARY KEY (id_usuario),
    UNIQUE KEY uq_usuario_email (email_usuario),
    CONSTRAINT fk_usuario_estudiante FOREIGN KEY (id_estudiante)
        REFERENCES estudiante (id_estudiante),
    CONSTRAINT fk_usuario_instructor FOREIGN KEY (id_instructor)
        REFERENCES instructor (id_instructor)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inscripcion (
    id_inscripcion INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_estudiante  INT UNSIGNED NOT NULL,
    id_grupo       INT UNSIGNED NOT NULL,
    fecha          DATE NOT NULL,
    estado         ENUM('Pendiente','Activa','Cancelada','Finalizada') NOT NULL DEFAULT 'Activa',
    PRIMARY KEY (id_inscripcion),
    CONSTRAINT fk_inscripcion_estudiante FOREIGN KEY (id_estudiante)
        REFERENCES estudiante (id_estudiante),
    CONSTRAINT fk_inscripcion_grupo FOREIGN KEY (id_grupo)
        REFERENCES grupo (id_grupo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE evaluacion (
    id_evaluacion  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_inscripcion INT UNSIGNED NOT NULL,
    tipo           VARCHAR(50) NOT NULL,
    calificacion   DECIMAL(4,2) NULL,
    PRIMARY KEY (id_evaluacion),
    CONSTRAINT fk_evaluacion_inscripcion FOREIGN KEY (id_inscripcion)
        REFERENCES inscripcion (id_inscripcion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE asistencia (
    id_asistencia INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_inscripcion INT UNSIGNED NOT NULL,
    fecha_clase   DATE NOT NULL,
    presente      ENUM('S','N') NOT NULL DEFAULT 'N',
    PRIMARY KEY (id_asistencia),
    CONSTRAINT fk_asistencia_inscripcion FOREIGN KEY (id_inscripcion)
        REFERENCES inscripcion (id_inscripcion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pago (
    id_pago       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_inscripcion INT UNSIGNED NOT NULL,
    fecha_pago    DATE NOT NULL,
    monto_pagado  DECIMAL(8,2) NOT NULL,
    concepto      VARCHAR(60) NOT NULL DEFAULT 'Mensualidad',
    metodo        VARCHAR(40) NULL,
    estado        ENUM('Pagado','Pendiente','Fallido') NOT NULL DEFAULT 'Pagado',
    PRIMARY KEY (id_pago),
    CONSTRAINT fk_pago_inscripcion FOREIGN KEY (id_inscripcion)
        REFERENCES inscripcion (id_inscripcion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Registro de práctica individual (dashboard del portal)
CREATE TABLE practica_estudiante (
    id_practica   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_estudiante INT UNSIGNED NOT NULL,
    fecha         DATE NOT NULL,
    horas         DECIMAL(4,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (id_practica),
    CONSTRAINT fk_practica_estudiante FOREIGN KEY (id_estudiante)
        REFERENCES estudiante (id_estudiante)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
--  DATOS DE PRUEBA
--  Alineados con src/Data/SampleData.php (los cursos, categorías
--  e instructores que se ven en el sitio web).
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
INSERT INTO instrumento (id_instrumento, id_categoria, nombre, precio_alquiler) VALUES
    (1, 1, 'Guitarra', 15.00),
    (2, 4, 'Batería',  25.00),
    (3, 3, 'Canto',    10.00),
    (4, 2, 'Piano',    30.00),
    (5, 5, 'Violín',   20.00);

-- Instructores ------------------------------------------------
INSERT INTO instructor (id_instructor, nombre, apellido, anios_experiencia) VALUES
    (1, 'Carlos', 'Mendoza', 12),
    (2, 'Sofía',  'Martínez', 8),
    (3, 'Mateo',  'Silva',   10),
    (4, 'Ana',    'Ríos',     9),
    (5, 'Diego',  'Torres',  11);

-- Instructor <-> Instrumento (N:M) ----------------------------
INSERT INTO instructor_instrumento (id_instructor, id_instrumento, anios_experiencia_i) VALUES
    (1, 4, 12),  -- Carlos -> Piano
    (2, 5,  8),  -- Sofía  -> Violín
    (3, 1, 10),  -- Mateo  -> Guitarra
    (4, 3,  9),  -- Ana    -> Canto
    (5, 2, 11);  -- Diego  -> Batería

-- Cursos (los 9 del catálogo) ---------------------------------
INSERT INTO curso (id_curso, id_instrumento, nombre, nivel, precio) VALUES
    (1, 1, 'Guitarra para Principiantes',                        'Principiante', 49.00),
    (2, 4, 'Piano Clásico I: Fundamentos',                       'Principiante', 55.00),
    (3, 4, 'Piano Clásico II: Desarrollo de Técnica y Repertorio','Intermedio',  79.00),
    (4, 3, 'Canto: Técnica Vocal y Respiración',                 'Principiante', 45.00),
    (5, 2, 'Batería: Ritmo y Coordinación',                      'Principiante', 52.00),
    (6, 5, 'Violín: Expresión y Afinación',                      'Intermedio',   62.00),
    (7, 1, 'Guitarra Intermedia: Arpegios y Ritmos',             'Intermedio',   59.00),
    (8, 2, 'Batería Avanzada: Grooves y Solos',                  'Avanzado',     69.00),
    (9, 4, 'Piano Avanzado: Improvisación',                      'Avanzado',     85.00);

-- Requisitos de cada curso ------------------------------------
INSERT INTO requisito_curso (id_curso, nivel_minimo, edad_minima, edad_maxima) VALUES
    (1, 'Sin requisitos previos',        14, 99),
    (2, 'Sin requisitos previos',        14, 99),
    (3, 'Piano Clásico I aprobado',      14, 99),
    (4, 'Sin requisitos previos',        14, 99),
    (5, 'Sin requisitos previos',        14, 99),
    (6, 'Curso de iniciación aprobado',  14, 99),
    (7, 'Curso de iniciación aprobado',  14, 99),
    (8, 'Nivel intermedio aprobado',     16, 99),
    (9, 'Nivel intermedio aprobado',     16, 99);

-- Descuentos --------------------------------------------------
INSERT INTO descuento (id_curso, codigo, porcentaje_monto) VALUES
    (3, 'PRIMAVERA20', 10.00),
    (1, 'NUEVO15',     15.00);

-- Aulas -------------------------------------------------------
INSERT INTO aula (id_aula, nombre, capacidad, recursos) VALUES
    (1, 'Aula 1', 12, 'Pianos y piano de cola'),
    (2, 'Aula 2', 15, 'Baterías acústicas y percusión'),
    (3, 'Aula 3', 10, 'Piano de cola, sillas y atriles');

-- Grupos ------------------------------------------------------
INSERT INTO grupo (id_grupo, id_curso, id_instructor, id_aula, fecha_inicio, fecha_fin) VALUES
    (1, 3, 1, 3, '2026-03-02', '2026-05-25'),  -- Piano Clásico II (curso del prototipo)
    (2, 1, 3, 1, '2026-03-02', '2026-05-25'),  -- Guitarra para Principiantes
    (3, 4, 4, 1, '2026-03-03', '2026-05-26');  -- Canto

-- Horarios (el grupo 1: Lunes y Miércoles 18:00-19:30) --------
INSERT INTO horario (id_grupo, dia_semana, hora_inicio, hora_fin) VALUES
    (1, 'Lunes',    '18:00', '19:30'),
    (1, 'Miércoles','18:00', '19:30'),
    (2, 'Lunes',    '10:00', '11:30'),
    (3, 'Martes',   '19:00', '20:30');

-- Estudiantes -------------------------------------------------
INSERT INTO estudiante (id_estudiante, nombre, apellido, email, telefono) VALUES
    (1, 'Laura',  'Gómez',  'laura.gomez@ejemplo.com',  '600111222'),
    (2, 'Daniel', 'Reyes',  'daniel.reyes@ejemplo.com', '600333444'),
    (3, 'Marta',  'López',  'marta.lopez@ejemplo.com',  '600555666');

-- Usuarios (contraseña de todos: "solfacademy123") ------------
INSERT INTO usuario (id_usuario, id_estudiante, id_instructor, email_usuario, password_hash, rol, activo) VALUES
    (1, 1,    NULL, 'laura.gomez@ejemplo.com',   '$2y$12$EICJjyzk2SHbCrsr7Nh.8eg4VXDwjxdntzguHelswFWVyubudFHtq', 'estudiante',  1),
    (2, 2,    NULL, 'daniel.reyes@ejemplo.com',  '$2y$12$EICJjyzk2SHbCrsr7Nh.8eg4VXDwjxdntzguHelswFWVyubudFHtq', 'estudiante',  1),
    (3, 3,    NULL, 'marta.lopez@ejemplo.com',   '$2y$12$EICJjyzk2SHbCrsr7Nh.8eg4VXDwjxdntzguHelswFWVyubudFHtq', 'estudiante',  1),
    (4, NULL, 1,    'carlos.mendoza@solfacademy.com', '$2y$12$EICJjyzk2SHbCrsr7Nh.8eg4VXDwjxdntzguHelswFWVyubudFHtq', 'instructor', 1),
    (5, NULL, 2,    'sofia.martinez@solfacademy.com', '$2y$12$EICJjyzk2SHbCrsr7Nh.8eg4VXDwjxdntzguHelswFWVyubudFHtq', 'instructor', 1),
    (6, NULL, 3,    'mateo.silva@solfacademy.com',    '$2y$12$EICJjyzk2SHbCrsr7Nh.8eg4VXDwjxdntzguHelswFWVyubudFHtq', 'instructor', 1),
    (7, NULL, NULL, 'admin@solfacademy.com',          '$2y$12$EICJjyzk2SHbCrsr7Nh.8eg4VXDwjxdntzguHelswFWVyubudFHtq', 'admin',      1);

-- Inscripciones -----------------------------------------------
INSERT INTO inscripcion (id_inscripcion, id_estudiante, id_grupo, fecha, estado) VALUES
    (1, 1, 1, '2026-02-20', 'Activa'),   -- Laura -> Piano Clásico II
    (2, 2, 2, '2026-02-21', 'Activa'),   -- Daniel -> Guitarra para Principiantes
    (3, 3, 3, '2026-02-22', 'Pendiente');-- Marta -> Canto

-- Evaluaciones ------------------------------------------------
INSERT INTO evaluacion (id_inscripcion, tipo, calificacion) VALUES
    (1, 'Parcial', 8.50),
    (1, 'Final',   9.00),
    (2, 'Parcial', 7.50);

-- Asistencias -------------------------------------------------
INSERT INTO asistencia (id_inscripcion, fecha_clase, presente) VALUES
    (1, '2026-03-02', 'S'),
    (1, '2026-03-04', 'S'),
    (2, '2026-03-02', 'N');

-- Pagos -------------------------------------------------------
INSERT INTO inscripcion (id_inscripcion, id_estudiante, id_grupo, fecha, estado) VALUES
    (4, 2, 1, '2026-02-25', 'Cancelada');

INSERT INTO pago (id_inscripcion, fecha_pago, monto_pagado, concepto, metodo, estado) VALUES
    (1, '2026-02-20', 79.00, 'Mensualidad',   'Tarjeta de crédito',      'Pagado'),
    (2, '2026-02-21', 49.00, 'Inscripción',   'Transferencia bancaria',  'Pagado'),
    (3, '2026-02-22', 45.00, 'Inscripción',   'Efectivo',                'Pagado');

-- ============================================================
--  SEED FASE 2 — PORTAL DEL ESTUDIANTE
--  Usa fechas dinámicas (CURDATE) para que el portal se vea
--  "vivo" siempre que se re-importe el script.
-- ============================================================

-- Grupos adicionales: Piano (Mateo), Batería y Violín ---------
INSERT INTO grupo (id_grupo, id_curso, id_instructor, id_aula, fecha_inicio, fecha_fin) VALUES
    (4, 3, 1, 3, DATE_SUB(CURDATE(), INTERVAL 70 DAY), DATE_ADD(CURDATE(), INTERVAL 20 DAY)),  -- Piano Clásico II
    (5, 5, 5, 2, DATE_SUB(CURDATE(), INTERVAL 50 DAY), DATE_ADD(CURDATE(), INTERVAL 35 DAY)),  -- Batería
    (6, 6, 2, 1, DATE_SUB(CURDATE(), INTERVAL 30 DAY), DATE_ADD(CURDATE(), INTERVAL 45 DAY));  -- Violín

-- Horarios (el piano incluye una clase "de hoy" para que el
-- dashboard muestre siempre "Próxima clase: Hoy") -------------
INSERT INTO horario (id_grupo, dia_semana, hora_inicio, hora_fin) VALUES
    (4, 'Lunes',     '17:00', '18:30'),
    (4, 'Miércoles', '17:00', '18:30'),
    (4, ELT(WEEKDAY(CURDATE()) + 1, 'Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'), '17:00', '18:30'),
    (5, 'Martes',    '19:00', '20:30'),
    (5, 'Jueves',    '19:00', '20:30'),
    (6, 'Viernes',   '18:00', '19:30');

-- Estudiante Mateo Díaz (el "ALUMNO - INTERMEDIO" del prototipo)
INSERT INTO estudiante (id_estudiante, nombre, apellido, email, telefono) VALUES
    (4, 'Mateo', 'Díaz', 'mateo.diaz@ejemplo.com', '600777888');

INSERT INTO usuario (id_estudiante, id_instructor, email_usuario, password_hash, rol, activo) VALUES
    (4, NULL, 'mateo.diaz@ejemplo.com', '$2y$12$EICJjyzk2SHbCrsr7Nh.8eg4VXDwjxdntzguHelswFWVyubudFHtq', 'estudiante', 1);

-- Sus 5 matrículas (insertadas una a una para capturar los ids) 
INSERT INTO inscripcion (id_estudiante, id_grupo, fecha, estado)
VALUES (4, 4, DATE_SUB(CURDATE(), INTERVAL 70 DAY), 'Activa');
SET @ins_piano = LAST_INSERT_ID();

INSERT INTO inscripcion (id_estudiante, id_grupo, fecha, estado)
VALUES (4, 2, DATE_SUB(CURDATE(), INTERVAL 100 DAY), 'Finalizada');
SET @ins_guitarra = LAST_INSERT_ID();

INSERT INTO inscripcion (id_estudiante, id_grupo, fecha, estado)
VALUES (4, 5, DATE_SUB(CURDATE(), INTERVAL 50 DAY), 'Activa');
SET @ins_bateria = LAST_INSERT_ID();

INSERT INTO inscripcion (id_estudiante, id_grupo, fecha, estado)
VALUES (4, 6, DATE_SUB(CURDATE(), INTERVAL 30 DAY), 'Activa');
SET @ins_violin = LAST_INSERT_ID();

INSERT INTO inscripcion (id_estudiante, id_grupo, fecha, estado)
VALUES (4, 3, CURDATE(), 'Pendiente');
SET @ins_canto = LAST_INSERT_ID();

-- Asistencias (volumen para el KPI global y el % de asistencia)
-- Piano: 12/13 · Guitarra: 10/10 · Batería: 11/12 · Violín: 9/10
INSERT INTO asistencia (id_inscripcion, fecha_clase, presente)
SELECT @ins_piano, DATE_SUB(CURDATE(), INTERVAL nums.n * 2 DAY), IF(nums.n <= 12, 'S', 'N')
FROM (SELECT ROW_NUMBER() OVER (ORDER BY (SELECT NULL)) AS n
      FROM information_schema.columns LIMIT 13) AS nums;

INSERT INTO asistencia (id_inscripcion, fecha_clase, presente)
SELECT @ins_guitarra, DATE_SUB(CURDATE(), INTERVAL nums.n * 2 DAY), 'S'
FROM (SELECT ROW_NUMBER() OVER (ORDER BY (SELECT NULL)) AS n
      FROM information_schema.columns LIMIT 10) AS nums;

INSERT INTO asistencia (id_inscripcion, fecha_clase, presente)
SELECT @ins_bateria, DATE_SUB(CURDATE(), INTERVAL nums.n * 2 DAY), IF(nums.n <= 11, 'S', 'N')
FROM (SELECT ROW_NUMBER() OVER (ORDER BY (SELECT NULL)) AS n
      FROM information_schema.columns LIMIT 12) AS nums;

INSERT INTO asistencia (id_inscripcion, fecha_clase, presente)
SELECT @ins_violin, DATE_SUB(CURDATE(), INTERVAL nums.n * 2 DAY), IF(nums.n <= 9, 'S', 'N')
FROM (SELECT ROW_NUMBER() OVER (ORDER BY (SELECT NULL)) AS n
      FROM information_schema.columns LIMIT 10) AS nums;

-- Evaluaciones (media exacta: 9.2) ---------------------------
INSERT INTO evaluacion (id_inscripcion, tipo, calificacion) VALUES
    (@ins_piano,    'Parcial de Solfeo',        9.00),
    (@ins_piano,    'Práctica de Repertorio',   9.50),
    (@ins_guitarra, 'Examen Final',             9.40),
    (@ins_bateria,  'Parcial de Ritmo',         8.80),
    (@ins_violin,   'Práctica de Técnica',      9.30);

-- Pagos de Mateo (1 pagado + 1 pendiente + próximo vencimiento)
INSERT INTO pago (id_inscripcion, fecha_pago, monto_pagado, concepto, metodo, estado) VALUES
    (@ins_piano,    DATE_SUB(CURDATE(), INTERVAL 70 DAY), 79.00, 'Inscripción',           'Tarjeta de crédito', 'Pagado'),
    (@ins_guitarra, DATE_SUB(CURDATE(), INTERVAL 100 DAY), 49.00, 'Inscripción',          'Transferencia bancaria', 'Pagado'),
    (@ins_bateria,  DATE_SUB(CURDATE(), INTERVAL 50 DAY), 52.00, 'Mensualidad',           'Tarjeta de crédito', 'Pagado'),
    (@ins_violin,   DATE_SUB(CURDATE(), INTERVAL 15 DAY), 62.00, 'Mensualidad',           'PayPal',             'Pendiente'),
    (@ins_piano,    DATE_ADD(CURDATE(), INTERVAL 5 DAY),  79.00, 'Mensualidad (próxima)', NULL,                 'Pendiente');

-- Práctica semanal individual (14.5 horas totales, como el prototipo)
INSERT INTO practica_estudiante (id_estudiante, fecha, horas) VALUES
    (4, DATE_SUB(CURDATE(), INTERVAL 6 DAY), 1.50),
    (4, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 2.00),
    (4, DATE_SUB(CURDATE(), INTERVAL 4 DAY), 0.50),
    (4, DATE_SUB(CURDATE(), INTERVAL 3 DAY), 3.00),
    (4, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 2.50),
    (4, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 4.00),
    (4, CURDATE(),                           1.00);
