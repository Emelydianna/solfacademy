-- ════════════════════════════════════════════════════════════════════════════════════
-- BASE DE DATOS - SOLFACADEMY
-- ════════════════════════════════════════════════════════════════════════════════════

DROP DATABASE IF EXISTS solfacademy;
CREATE DATABASE solfacademy CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE solfacademy;

-- ════════════════════════════════════════════════════════════════════════════════════
-- 1. CATEGORIAS_INSTRUMENTO
-- ════════════════════════════════════════════════════════════════════════════════════
CREATE TABLE categorias_instrumento (
    id_categoria INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    descripcion TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ════════════════════════════════════════════════════════════════════════════════════
-- 2. INSTRUMENTOS
-- ════════════════════════════════════════════════════════════════════════════════════
CREATE TABLE instrumentos (
    id_instrumento INT PRIMARY KEY AUTO_INCREMENT,
    id_categoria INT NOT NULL,
    nombre_instrumento VARCHAR(100) NOT NULL,
    descripcion TEXT,
    precio_alquiler DECIMAL(10, 2),
    disponible BOOLEAN DEFAULT TRUE,
    imagen TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_categoria) REFERENCES categorias_instrumento(id_categoria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ════════════════════════════════════════════════════════════════════════════════════
-- 3. INSTRUCTORES 
-- ════════════════════════════════════════════════════════════════════════════════════
CREATE TABLE instructores (
    id_instructor INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    telefono VARCHAR(20),
    anios_experiencia INT,
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ════════════════════════════════════════════════════════════════════════════════════
-- 4. INSTRUCTOR_INSTRUMENTO 
-- ════════════════════════════════════════════════════════════════════════════════════
CREATE TABLE instructor_instrumento (
    id_instructor INT NOT NULL,
    id_instrumento INT NOT NULL,
    nivel_dominio ENUM('principiante', 'intermedio', 'avanzado') DEFAULT 'intermedio',
    anios_experiencia_instrumento INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_instructor, id_instrumento),
    FOREIGN KEY (id_instructor) REFERENCES instructores(id_instructor),
    FOREIGN KEY (id_instrumento) REFERENCES instrumentos(id_instrumento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ════════════════════════════════════════════════════════════════════════════════════
-- 5. AULAS
-- ════════════════════════════════════════════════════════════════════════════════════
CREATE TABLE aulas (
    id_aula INT PRIMARY KEY AUTO_INCREMENT,
    nombre_aula VARCHAR(100) NOT NULL,
    capacidad INT,
    recursos VARCHAR(255),
    ubicacion VARCHAR(150),
    activa BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ════════════════════════════════════════════════════════════════════════════════════
-- 6. CURSOS
-- ════════════════════════════════════════════════════════════════════════════════════
CREATE TABLE cursos (
    id_curso INT PRIMARY KEY AUTO_INCREMENT,
    id_instrumento INT NOT NULL,
    nombre_curso VARCHAR(150) NOT NULL,
    descripcion TEXT,
    nivel ENUM('principiante', 'intermedio', 'avanzado') DEFAULT 'principiante',
    duracion_semanas INT,
    precio DECIMAL(10, 2) NOT NULL,
    disponible BOOLEAN DEFAULT TRUE,
    activo BOOLEAN DEFAULT TRUE,
    max_grupos INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_instrumento) REFERENCES instrumentos(id_instrumento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ════════════════════════════════════════════════════════════════════════════════════
-- 7. REQUISITOS_CURSO
-- ════════════════════════════════════════════════════════════════════════════════════
CREATE TABLE requisitos_curso (
    id_requisito INT PRIMARY KEY AUTO_INCREMENT,
    id_curso INT NOT NULL UNIQUE,
    nivel_minimo ENUM('principiante', 'intermedio', 'avanzado'),
    edad_minima INT,
    edad_maxima INT,
    conocimientos_previos VARCHAR(255),
    necesita_instrumento BOOLEAN DEFAULT FALSE,
    puede_alquilar BOOLEAN DEFAULT TRUE,
    descripcion_general TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_curso) REFERENCES cursos(id_curso)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ════════════════════════════════════════════════════════════════════════════════════
-- 8. GRUPOS 
-- ════════════════════════════════════════════════════════════════════════════════════
CREATE TABLE grupos (
    id_grupo INT PRIMARY KEY AUTO_INCREMENT,
    id_curso INT NOT NULL,
    id_instructor INT NOT NULL,
    id_aula INT NOT NULL,
    capacidad_maxima INT NOT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE,
    estado ENUM('planeado', 'activo', 'finalizado', 'cancelado') DEFAULT 'planeado',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_curso) REFERENCES cursos(id_curso),
    FOREIGN KEY (id_instructor) REFERENCES instructores(id_instructor),
    FOREIGN KEY (id_aula) REFERENCES aulas(id_aula),
    CHECK (fecha_fin IS NULL OR fecha_fin >= fecha_inicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ════════════════════════════════════════════════════════════════════════════════════
-- 9. HORARIOS
-- ════════════════════════════════════════════════════════════════════════════════════
CREATE TABLE horarios (
    id_horario INT PRIMARY KEY AUTO_INCREMENT,
    id_grupo INT NOT NULL,
    dia_semana ENUM('lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'),
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_grupo) REFERENCES grupos(id_grupo),
    CHECK (hora_fin > hora_inicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ════════════════════════════════════════════════════════════════════════════════════
-- 10. ESTUDIANTES
-- ════════════════════════════════════════════════════════════════════════════════════
CREATE TABLE estudiantes (
    id_estudiante INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    telefono VARCHAR(20),
    fecha_inscripcion DATE,
    direccion VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ════════════════════════════════════════════════════════════════════════════════════
-- 11. USUARIOS
-- ════════════════════════════════════════════════════════════════════════════════════
CREATE TABLE usuarios (
    id_usuario INT PRIMARY KEY AUTO_INCREMENT,
    id_estudiante INT UNIQUE,
    email_usuario VARCHAR(150) NOT NULL UNIQUE,
    contraseña VARCHAR(255) NOT NULL,
    rol ENUM('admin', 'instructor', 'estudiante') DEFAULT 'estudiante',
    activo BOOLEAN DEFAULT TRUE,
    ultimo_acceso TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_estudiante) REFERENCES estudiantes(id_estudiante)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ════════════════════════════════════════════════════════════════════════════════════
-- 12. INSCRIPCIONES
-- ════════════════════════════════════════════════════════════════════════════════════

CREATE TABLE inscripciones (
    id_inscripcion INT PRIMARY KEY AUTO_INCREMENT,
    id_estudiante INT NOT NULL,
    id_grupo INT NOT NULL,
    fecha_inscripcion DATE NOT NULL,
    estado ENUM('activo', 'completado', 'cancelado', 'suspendido') DEFAULT 'activo',
    precio_pagado DECIMAL(10, 2) DEFAULT 0.00,
    estado_pago ENUM('pendiente', 'parcial', 'pagado') DEFAULT 'pendiente',
    monto_pendiente DECIMAL(10, 2) DEFAULT 0.00,
    observaciones TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_estudiante) REFERENCES estudiantes(id_estudiante),
    FOREIGN KEY (id_grupo) REFERENCES grupos(id_grupo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ════════════════════════════════════════════════════════════════════════════════════
-- 13. PAGOS
-- ════════════════════════════════════════════════════════════════════════════════════

CREATE TABLE pagos (
    id_pago INT PRIMARY KEY AUTO_INCREMENT,
    id_inscripcion INT NOT NULL,
    fecha_pago DATE NOT NULL,
    monto_pagado DECIMAL(10, 2) NOT NULL,
    metodo_pago ENUM('efectivo', 'tarjeta', 'transferencia', 'cheque') NOT NULL,
    referencia_pago VARCHAR(100),
    comprobante_url VARCHAR(255),
    observaciones TEXT,
    creado_por INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_inscripcion) REFERENCES inscripciones(id_inscripcion),
    FOREIGN KEY (creado_por) REFERENCES usuarios(id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ════════════════════════════════════════════════════════════════════════════════════
-- 14. ASISTENCIA
-- ════════════════════════════════════════════════════════════════════════════════════
CREATE TABLE asistencia (
    id_asistencia INT PRIMARY KEY AUTO_INCREMENT,
    id_inscripcion INT NOT NULL,
    id_grupo INT NOT NULL,
    fecha_clase DATE NOT NULL,
    presente BOOLEAN DEFAULT FALSE,
    justificacion VARCHAR(255),
    observaciones TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_inscripcion) REFERENCES inscripciones(id_inscripcion),
    FOREIGN KEY (id_grupo) REFERENCES grupos(id_grupo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ════════════════════════════════════════════════════════════════════════════════════
-- 15. EVALUACIONES
-- ════════════════════════════════════════════════════════════════════════════════════
CREATE TABLE evaluaciones (
    id_evaluacion INT PRIMARY KEY AUTO_INCREMENT,
    id_inscripcion INT NOT NULL,
    id_grupo INT NOT NULL,
    tipo_evaluacion ENUM('practica', 'teorica', 'presentacion', 'parcial', 'final') DEFAULT 'practica',
    fecha_evaluacion DATE NOT NULL,
    calificacion DECIMAL(5, 2),
    descripcion VARCHAR(255),
    observaciones_profesor TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_inscripcion) REFERENCES inscripciones(id_inscripcion),
    FOREIGN KEY (id_grupo) REFERENCES grupos(id_grupo),
    CHECK (calificacion >= 0 AND calificacion <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ════════════════════════════════════════════════════════════════════════════════════
-- 16. DESCUENTOS
-- ════════════════════════════════════════════════════════════════════════════════════
CREATE TABLE descuentos (
    id_descuento INT PRIMARY KEY AUTO_INCREMENT,
    codigo_descuento VARCHAR(50) NOT NULL UNIQUE,
    descripcion VARCHAR(200),
    porcentaje INT,
    monto_fijo DECIMAL(10, 2),
    id_curso INT,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    usos_disponibles INT DEFAULT -1,
    usos_realizados INT DEFAULT 0,
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_curso) REFERENCES cursos(id_curso),
    CHECK (fecha_fin >= fecha_inicio),
    CHECK ((porcentaje IS NOT NULL AND monto_fijo IS NULL) OR (porcentaje IS NULL AND monto_fijo IS NOT NULL) OR (porcentaje IS NOT NULL AND monto_fijo IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ════════════════════════════════════════════════════════════════════════════════════
-- VISTAS ÚTILES
-- ════════════════════════════════════════════════════════════════════════════════════

CREATE VIEW v_grupos_completos AS
SELECT 
    g.id_grupo,
    c.nombre_curso,
    i.nombre as instructor_nombre,
    a.nombre_aula,
    g.capacidad_maxima,
    COUNT(insc.id_inscripcion) as inscritos,
    g.capacidad_maxima - COUNT(insc.id_inscripcion) as lugares_disponibles,
    g.fecha_inicio,
    g.fecha_fin,
    g.estado
FROM grupos g
JOIN cursos c ON g.id_curso = c.id_curso
JOIN instructores i ON g.id_instructor = i.id_instructor
JOIN aulas a ON g.id_aula = a.id_aula
LEFT JOIN inscripciones insc ON g.id_grupo = insc.id_grupo
GROUP BY g.id_grupo;

CREATE VIEW v_horarios_grupos AS
SELECT 
    g.id_grupo,
    c.nombre_curso,
    h.dia_semana,
    h.hora_inicio,
    h.hora_fin,
    a.nombre_aula
FROM grupos g
JOIN cursos c ON g.id_curso = c.id_curso
JOIN horarios h ON g.id_grupo = h.id_grupo
JOIN aulas a ON g.id_aula = a.id_aula
ORDER BY h.dia_semana, h.hora_inicio;

CREATE VIEW v_inscripciones_detalle AS
SELECT 
    insc.id_inscripcion,
    CONCAT(e.nombre, ' ', e.apellido) as estudiante,
    c.nombre_curso,
    insc.precio_pagado,
    insc.estado_pago,
    insc.monto_pendiente,
    insc.estado
FROM inscripciones insc
JOIN estudiantes e ON insc.id_estudiante = e.id_estudiante
JOIN grupos g ON insc.id_grupo = g.id_grupo
JOIN cursos c ON g.id_curso = c.id_curso;

CREATE VIEW v_asistencia_estudiante AS
SELECT 
    CONCAT(e.nombre, ' ', e.apellido) as estudiante,
    c.nombre_curso,
    COUNT(a.id_asistencia) as total_clases,
    SUM(CASE WHEN a.presente = TRUE THEN 1 ELSE 0 END) as presente,
    ROUND(SUM(CASE WHEN a.presente = TRUE THEN 1 ELSE 0 END) / COUNT(a.id_asistencia) * 100, 2) as porcentaje_asistencia
FROM asistencia a
JOIN inscripciones insc ON a.id_inscripcion = insc.id_inscripcion
JOIN estudiantes e ON insc.id_estudiante = e.id_estudiante
JOIN grupos g ON a.id_grupo = g.id_grupo
JOIN cursos c ON g.id_curso = c.id_curso
GROUP BY insc.id_inscripcion, e.id_estudiante;

CREATE VIEW v_calificaciones_estudiante AS
SELECT 
    CONCAT(e.nombre, ' ', e.apellido) as estudiante,
    c.nombre_curso,
    ev.tipo_evaluacion,
    ev.calificacion,
    ROUND(AVG(ev.calificacion) OVER (PARTITION BY insc.id_inscripcion), 2) as promedio
FROM evaluaciones ev
JOIN inscripciones insc ON ev.id_inscripcion = insc.id_inscripcion
JOIN estudiantes e ON insc.id_estudiante = e.id_estudiante
JOIN grupos g ON ev.id_grupo = g.id_grupo
JOIN cursos c ON g.id_curso = c.id_curso
ORDER BY e.nombre, c.nombre_curso;

-- ════════════════════════════════════════════════════════════════════════════════════
-- PROCEDIMIENTOS ALMACENADOS
-- ════════════════════════════════════════════════════════════════════════════════════

DELIMITER $$

CREATE PROCEDURE sp_verificar_disponibilidad_grupo(
    IN p_id_grupo INT,
    OUT p_lugares INT
)
BEGIN
    SELECT 
        g.capacidad_maxima - COUNT(insc.id_inscripcion) INTO p_lugares
    FROM grupos g
    LEFT JOIN inscripciones insc ON g.id_grupo = insc.id_grupo
    WHERE g.id_grupo = p_id_grupo
    GROUP BY g.id_grupo;
END$$

CREATE PROCEDURE sp_registrar_inscripcion(
    IN p_id_estudiante INT,
    IN p_id_grupo INT,
    OUT p_success BOOLEAN,
    OUT p_message VARCHAR(255)
)
BEGIN
    DECLARE v_lugares INT;
    DECLARE v_precio DECIMAL(10,2);
    
    START TRANSACTION;
    
    -- Verificar disponibilidad
    CALL sp_verificar_disponibilidad_grupo(p_id_grupo, v_lugares);
    
    IF v_lugares <= 0 THEN
        SET p_success = FALSE;
        SET p_message = 'No hay lugares disponibles en el grupo';
        ROLLBACK;
    ELSE
        -- Obtener precio del curso
        SELECT c.precio INTO v_precio
        FROM grupos g
        JOIN cursos c ON g.id_curso = c.id_curso
        WHERE g.id_grupo = p_id_grupo;
        
        -- Registrar inscripción
        INSERT INTO inscripciones (id_estudiante, id_grupo, fecha_inscripcion, estado, monto_pendiente)
        VALUES (p_id_estudiante, p_id_grupo, CURDATE(), 'activo', v_precio);
        
        SET p_success = TRUE;
        SET p_message = 'Inscripción realizada exitosamente';
        COMMIT;
    END IF;
END$$

CREATE PROCEDURE sp_actualizar_pago(
    IN p_id_inscripcion INT,
    IN p_monto DECIMAL(10,2),
    IN p_metodo VARCHAR(50),
    IN p_referencia VARCHAR(100),
    OUT p_success BOOLEAN
)
BEGIN
    DECLARE v_monto_pendiente DECIMAL(10,2);
    DECLARE v_precio_pagado DECIMAL(10,2);
    DECLARE v_precio_curso DECIMAL(10,2);
    
    START TRANSACTION;
    
    -- Obtener información actual
    SELECT i.monto_pendiente, i.precio_pagado INTO v_monto_pendiente, v_precio_pagado
    FROM inscripciones i
    WHERE i.id_inscripcion = p_id_inscripcion;
    
    -- Obtener precio total del curso
    SELECT c.precio INTO v_precio_curso
    FROM inscripciones i
    JOIN grupos g ON i.id_grupo = g.id_grupo
    JOIN cursos c ON g.id_curso = c.id_curso
    WHERE i.id_inscripcion = p_id_inscripcion;
    
    -- Registrar pago
    INSERT INTO pagos (id_inscripcion, fecha_pago, monto_pagado, metodo_pago, referencia_pago, creado_por)
    VALUES (p_id_inscripcion, CURDATE(), p_monto, p_metodo, p_referencia, 1);
    
    -- Actualizar inscripción
    UPDATE inscripciones
    SET precio_pagado = v_precio_pagado + p_monto,
        monto_pendiente = v_precio_curso - (v_precio_pagado + p_monto),
        estado_pago = CASE 
            WHEN (v_precio_pagado + p_monto) = 0 THEN 'pendiente'
            WHEN (v_precio_pagado + p_monto) < v_precio_curso THEN 'parcial'
            ELSE 'pagado'
        END
    WHERE id_inscripcion = p_id_inscripcion;
    
    SET p_success = TRUE;
    COMMIT;
END$$

DELIMITER ;











-- ════════════════════════════════════════════════════════════════════════════════════
-- DATOS DE PRUEBA
-- ════════════════════════════════════════════════════════════════════════════════════

USE solfacademy;

-- ════════════════════════════════════════════════════════════════════════════════════
-- 1. CATEGORIAS_INSTRUMENTO
-- ════════════════════════════════════════════════════════════════════════════════════
INSERT INTO categorias_instrumento (nombre, descripcion) VALUES
('Cuerda', 'Instrumentos de cuerda (guitarra, violín, bajo, etc.)'),
('Viento', 'Instrumentos de viento (flauta, trompeta, clarinete, etc.)'),
('Percusión', 'Instrumentos de percusión (batería, timbal, xilófono, etc.)'),
('Teclado', 'Instrumentos de teclado (piano, órgano, sintetizador, etc.)');


-- ════════════════════════════════════════════════════════════════════════════════════
-- 2. INSTRUMENTOS
-- ════════════════════════════════════════════════════════════════════════════════════
INSERT INTO instrumentos (id_categoria, nombre_instrumento, descripcion, precio_alquiler, disponible, imagen) VALUES
(1, 'Guitarra Acústica', 'Guitarra de 6 cuerdas de madera', 35.00, TRUE, 'default.jpg'),
(1, 'Guitarra Eléctrica', 'Guitarra de cuerpo sólido con amplificador', 45.00, TRUE, 'default.jpg'),
(1, 'Violín', 'Instrumento de arco de 4 cuerdas', 50.00, TRUE, 'default.jpg'),
(1, 'Bajo Eléctrico', 'Bajo de 4 cuerdas', 40.00, TRUE, 'default.jpg'),
(2, 'Flauta Traversa', 'Flauta de metal', 25.00, TRUE, 'default.jpg'),
(2, 'Trompeta', 'Instrumento de viento de latón', 40.00, TRUE, 'default.jpg'),
(2, 'Clarinete', 'Instrumento de viento de madera', 35.00, TRUE, 'default.jpg'),
(2, 'Saxofón Alto', 'Instrumento de viento de latón', 45.00, TRUE, 'default.jpg'),
(3, 'Batería Completa', 'Set de 5 piezas con platillos', 60.00, TRUE, 'default.jpg'),
(3, 'Timbal', 'Instrumento de percusión de timpani', 40.00, TRUE, 'default.jpg'),
(3, 'Xilófono', 'Instrumento de percusión de madera y metal', 50.00, TRUE, 'default.jpg'),
(4, 'Piano Acústico', 'Piano de cola de 88 teclas', 100.00, false, 'default.jpg'),
(4, 'Teclado Electrónico', 'Sintetizador de 61 teclas', 50.00, TRUE, 'default.jpg'),
(4, 'Órgano Electrónico', 'Órgano de tuberías digitales', 75.00, TRUE, 'default.jpg');

update instrumentos set imagen='default.jpg'

-- ════════════════════════════════════════════════════════════════════════════════════
-- 3. INSTRUCTORES 
-- ════════════════════════════════════════════════════════════════════════════════════
INSERT INTO instructores (nombre, apellido, email, telefono, anios_experiencia, activo) VALUES
('Juan', 'Pérez García', 'juan.perez@solfacademy.com', '2501234567', 15, TRUE),
('María', 'López Martínez', 'maria.lopez@solfacademy.com', '2507654321', 12, TRUE),
('Carlos', 'González Ruiz', 'carlos.gonzalez@solfacademy.com', '2502345678', 10, TRUE),
('Ana', 'Rodríguez Flores', 'ana.rodriguez@solfacademy.com', '2503456789', 8, TRUE),
('Pedro', 'Hernández Vega', 'pedro.hernandez@solfacademy.com', '2504567890', 20, TRUE),
('Laura', 'Sánchez Cruz', 'laura.sanchez@solfacademy.com', '2505678901', 7, TRUE);


-- ════════════════════════════════════════════════════════════════════════════════════
-- 4. INSTRUCTOR_INSTRUMENTO 
-- ════════════════════════════════════════════════════════════════════════════════════
INSERT INTO instructor_instrumento (id_instructor, id_instrumento, nivel_dominio, anios_experiencia_instrumento) VALUES
(1, 1, 'avanzado', 15),  -- Juan: Guitarra Acústica
(1, 2, 'intermedio', 8), -- Juan: Guitarra Eléctrica
(2, 5, 'avanzado', 12),  -- María: Flauta Traversa
(2, 6, 'intermedio', 5), -- María: Trompeta
(3, 1, 'intermedio', 10),-- Carlos: Guitarra Acústica
(3, 4, 'avanzado', 10),  -- Carlos: Bajo Eléctrico
(4, 12, 'avanzado', 8),  -- Ana: Teclado Electrónico
(4, 14, 'intermedio', 6),-- Ana: Órgano Electrónico
(5, 3, 'avanzado', 20),  -- Pedro: Violín
(5, 1, 'avanzado', 18),  -- Pedro: Guitarra Acústica
(6, 9, 'avanzado', 7),   -- Laura: Batería Completa
(6, 10, 'intermedio', 5);-- Laura: Timbal


-- ════════════════════════════════════════════════════════════════════════════════════
-- 5. AULAS
-- ════════════════════════════════════════════════════════════════════════════════════
INSERT INTO aulas (nombre_aula, capacidad, recursos, ubicacion, activa) VALUES
('Aula 101', 20, 'Pizarra, Espejo, Equipo de Sonido', 'Piso 1', TRUE),
('Aula 102', 15, 'Pizarra, Espejo, Grabadora', 'Piso 1', TRUE),
('Studio A', 8, 'Micrófono, Interfaz de Audio, Monitores', 'Piso 2', TRUE),
('Studio B', 10, 'Piano, Pizarra, Espejo', 'Piso 2', TRUE),
('Sala Ensamble', 25, 'Batería, Amplificadores, Consola', 'Piso 3', TRUE),
('Aula 301', 18, 'Sillas, Atriles, Pizarra', 'Piso 3', TRUE),
('Ensayo Privado 1', 2, 'Espejo, Estantería', 'Piso 1', TRUE),
('Ensayo Privado 2', 3, 'Espejo, Sofá', 'Piso 2', TRUE);


-- ════════════════════════════════════════════════════════════════════════════════════
-- 6. CURSOS
-- ════════════════════════════════════════════════════════════════════════════════════
INSERT INTO cursos (id_instrumento, nombre_curso, descripcion, nivel, duracion_semanas, precio, disponible, activo, max_grupos) VALUES
(1, 'Guitarra Acústica Nivel 1', 'Curso básico de guitarra acústica para principiantes', 'principiante', 12, 150.00, TRUE, TRUE, 3),
(1, 'Guitarra Acústica Nivel 2', 'Curso intermedio con técnicas avanzadas', 'intermedio', 14, 200.00, TRUE, TRUE, 2),
(1, 'Guitarra Acústica Nivel 3', 'Curso avanzado con repertorio clásico', 'avanzado', 16, 250.00, TRUE, TRUE, 2),
(2, 'Guitarra Eléctrica Básica', 'Introducción a guitarra eléctrica y amplificadores', 'principiante', 12, 180.00, TRUE, TRUE, 2),
(4, 'Bajo Eléctrico Fundamental', 'Fundamentos del bajo eléctrico', 'principiante', 12, 160.00, TRUE, TRUE, 2),
(5, 'Flauta Traversa Inicial', 'Primeros pasos en flauta traversa', 'principiante', 12, 140.00, TRUE, TRUE, 2),
(6, 'Trompeta para Principiantes', 'Introducción a la trompeta', 'principiante', 12, 170.00, TRUE, TRUE, 2),
(12, 'Teclado Electrónico Nivel 1', 'Introducción al teclado electrónico', 'principiante', 10, 130.00, TRUE, TRUE, 3),
(12, 'Teclado Electrónico Nivel 2', 'Técnicas intermedias y composición', 'intermedio', 12, 180.00, TRUE, TRUE, 2),
(14, 'Órgano Electrónico Avanzado', 'Técnicas avanzadas de órgano', 'avanzado', 14, 220.00, TRUE, TRUE, 1),
(9, 'Batería Elemental', 'Introducción a la batería y ritmo', 'principiante', 12, 140.00, TRUE, TRUE, 2),
(3, 'Violín Nivel 1', 'Primeros pasos en violín', 'principiante', 12, 190.00, TRUE, TRUE, 2);

-- ════════════════════════════════════════════════════════════════════════════════════
-- 7. REQUISITOS_CURSO
-- ════════════════════════════════════════════════════════════════════════════════════
INSERT INTO requisitos_curso (id_curso, nivel_minimo, edad_minima, edad_maxima, conocimientos_previos, necesita_instrumento, puede_alquilar, descripcion_general) VALUES
(1, NULL, 6, NULL, 'Ninguno', FALSE, TRUE, 'Abierto para todas las edades sin conocimiento previo'),
(2, 'principiante', 6, NULL, 'Haber completado Guitarra Nivel 1', TRUE, TRUE, 'Requiere dominio básico de guitarra'),
(3, 'intermedio', 8, NULL, 'Haber completado Guitarra Nivel 2', TRUE, TRUE, 'Requiere guitarra propia de buena calidad'),
(4, NULL, 10, NULL, 'Ninguno', FALSE, TRUE, 'Abierto para principiantes en guitarra eléctrica'),
(5, NULL, 10, NULL, 'Ninguno', FALSE, TRUE, 'Para quienes quieren aprender bajo'),
(6, NULL, 8, NULL, 'Ninguno', FALSE, TRUE, 'Abierto para todas las edades'),
(7, NULL, 8, NULL, 'Ninguno', FALSE, TRUE, 'Abierto para todas las edades'),
(8, NULL, 5, NULL, 'Ninguno', FALSE, TRUE, 'Abierto para niños desde 5 años'),
(9, 'principiante', 5, NULL, 'Haber completado Teclado Nivel 1', TRUE, FALSE, 'Requiere instrumento propio'),
(10, 'avanzado', 12, NULL, 'Máximo 2 años de experiencia', TRUE, FALSE, 'Solo para estudiantes avanzados'),
(11, NULL, 7, NULL, 'Ninguno', FALSE, TRUE, 'Abierto para todas las edades'),
(12, NULL, 8, NULL, 'Ninguno', FALSE, TRUE, 'Abierto para todas las edades');


-- ════════════════════════════════════════════════════════════════════════════════════
-- 8. GRUPOS 
-- ════════════════════════════════════════════════════════════════════════════════════
INSERT INTO grupos (id_curso, id_instructor, id_aula, capacidad_maxima, fecha_inicio, fecha_fin, estado) VALUES
(1, 1, 1, 15, '2026-09-01', '2026-11-30', 'activo'),              -- Guitarra Acústica Nivel 1, finaliza 30 Nov
(2, 1, 2, 12, '2026-09-01', '2026-12-20', 'activo'),              -- Guitarra Acústica Nivel 2, finaliza 20 Dic
(3, 5, 4, 10, '2026-09-15', '2027-01-31', 'planeado'),            -- Guitarra Acústica Nivel 3, finaliza 31 Ene
(4, 3, 1, 12, '2026-09-08', '2026-12-10', 'activo'),              -- Guitarra Eléctrica, finaliza 10 Dic
(5, 3, 3, 8, '2026-09-10', NULL, 'activo'),                      -- Bajo Eléctrico, sin fecha fin aún
(6, 2, 2, 10, '2026-09-05', '2026-11-28', 'activo'),              -- Flauta Traversa, finaliza 28 Nov
(7, 2, 1, 12, '2026-09-12', NULL, 'planeado'),                   -- Trompeta, sin fecha fin aún
(8, 4, 4, 15, '2026-09-03', '2026-11-15', 'activo'),              -- Teclado Electrónico Nivel 1, finaliza 15 Nov
(9, 4, 3, 10, '2026-09-17', NULL, 'planeado'),                   -- Teclado Electrónico Nivel 2, sin fecha fin
(10, 4, 6, 8, '2026-10-01', '2026-12-31', 'planeado'),            -- Órgano Electrónico, finaliza 31 Dic
(11, 6, 5, 12, '2026-09-06', '2026-12-01', 'activo'),             -- Batería, finaliza 1 Dic
(12, 5, 2, 10, '2026-09-14', NULL, 'planeado');                   -- Violín Nivel 1, sin fecha fin aún

-- ════════════════════════════════════════════════════════════════════════════════════
-- 9. HORARIOS
-- ════════════════════════════════════════════════════════════════════════════════════
INSERT INTO horarios (id_grupo, dia_semana, hora_inicio, hora_fin) VALUES
(1, 'lunes', '10:00:00', '11:30:00'),
(1, 'miercoles', '10:00:00', '11:30:00'),
(1, 'viernes', '10:00:00', '11:30:00'),
(2, 'martes', '11:45:00', '13:15:00'),
(2, 'jueves', '11:45:00', '13:15:00'),
(3, 'lunes', '15:00:00', '16:30:00'),
(3, 'miercoles', '15:00:00', '16:30:00'),
(4, 'martes', '16:45:00', '18:15:00'),
(4, 'jueves', '16:45:00', '18:15:00'),
(5, 'lunes', '18:30:00', '19:30:00'),
(5, 'viernes', '18:30:00', '19:30:00'),
(6, 'lunes', '14:00:00', '15:30:00'),
(6, 'jueves', '14:00:00', '15:30:00'),
(8, 'martes', '10:00:00', '11:00:00'),
(8, 'viernes', '10:00:00', '11:00:00'),
(11, 'sabado', '11:00:00', '12:30:00'),
(11, 'sabado', '13:00:00', '14:30:00');

-- ════════════════════════════════════════════════════════════════════════════════════
-- 10. ESTUDIANTES
-- ════════════════════════════════════════════════════════════════════════════════════
INSERT INTO estudiantes (nombre, apellido, email, telefono, fecha_inscripcion, direccion) VALUES
('Carlota', 'Mendez Ruiz', 'carlota.mendez@email.com', '2509876543', '2026-08-10', 'Calle 1, San Salvador'),
('Miguel', 'Torres García', 'miguel.torres@email.com', '2501234567', '2026-08-12', 'Avenida Central, San Salvador'),
('Sofia', 'Romero López', 'sofia.romero@email.com', '2502345678', '2026-08-15', 'Calle 5, San Marcos'),
('Diego', 'Castillo Flores', 'diego.castillo@email.com', '2503456789', '2026-08-18', 'Barrio Escalante, San Salvador'),
('Valentina', 'Herrera Vega', 'valentina.herrera@email.com', '2504567890', '2026-08-20', 'Calle San José, Santa Tecla'),
('Mateo', 'Soto Pérez', 'mateo.soto@email.com', '2505678901', '2026-08-22', 'Boulevard Los Próceres, San Salvador'),
('Isabella', 'Campos Rodríguez', 'isabella.campos@email.com', '2506789012', '2026-08-25', 'Calle Poniente, San Salvador'),
('Lucas', 'Gutiérrez Martínez', 'lucas.gutierrez@email.com', '2507890123', '2026-08-28', 'Avenida Masferrer, San Salvador'),
('Emma', 'Vargas Cruz', 'emma.vargas@email.com', '2508901234', '2026-09-01', 'Residencial Las Colinas, San Salvador'),
('Alejandro', 'Díaz López', 'alejandro.diaz@email.com', '2501112233', '2026-09-03', 'Calle Comercial, Santa Ana');


-- ════════════════════════════════════════════════════════════════════════════════════
-- 11. USUARIOS
-- ════════════════════════════════════════════════════════════════════════════════════
INSERT INTO usuarios (id_estudiante, email_usuario, contraseña, rol, activo, ultimo_acceso) VALUES
(1, 'carlota.mendez@email.com', '$2y$10$hashed_password_1', 'estudiante', TRUE, '2026-08-26 14:30:00'),
(2, 'miguel.torres@email.com', '$2y$10$hashed_password_2', 'estudiante', TRUE, '2026-08-26 10:15:00'),
(3, 'sofia.romero@email.com', '$2y$10$hashed_password_3', 'estudiante', TRUE, '2026-08-25 18:45:00'),
(4, 'diego.castillo@email.com', '$2y$10$hashed_password_4', 'estudiante', TRUE, NULL),
(5, 'valentina.herrera@email.com', '$2y$10$hashed_password_5', 'estudiante', TRUE, '2026-08-26 09:30:00'),
(6, 'mateo.soto@email.com', '$2y$10$hashed_password_6', 'estudiante', TRUE, '2026-08-24 16:20:00'),
(7, 'isabella.campos@email.com', '$2y$10$hashed_password_7', 'estudiante', TRUE, '2026-08-26 15:00:00'),
(8, 'lucas.gutierrez@email.com', '$2y$10$hashed_password_8', 'estudiante', TRUE, NULL),
(9, 'emma.vargas@email.com', '$2y$10$hashed_password_9', 'estudiante', TRUE, '2026-08-25 19:45:00'),
(10, 'alejandro.diaz@email.com', '$2y$10$hashed_password_10', 'estudiante', TRUE, '2026-08-26 11:30:00'),
(NULL, 'admin@solfacademy.com', '$2y$10$hashed_password_admin', 'admin', TRUE, '2026-08-26 08:00:00'),
(NULL, 'juan.perez@solfacademy.com', '$2y$10$hashed_password_juan', 'instructor', TRUE, '2026-08-26 13:45:00'),
(NULL, 'maria.lopez@solfacademy.com', '$2y$10$hashed_password_maria', 'instructor', TRUE, '2026-08-26 09:00:00'),
(NULL, 'carlos.gonzalez@solfacademy.com', '$2y$10$hashed_password_carlos', 'instructor', TRUE, '2026-08-26 16:30:00');


-- ════════════════════════════════════════════════════════════════════════════════════
-- 12. INSCRIPCIONES
-- ════════════════════════════════════════════════════════════════════════════════════
INSERT INTO inscripciones (id_estudiante, id_grupo, fecha_inscripcion, estado, precio_pagado, estado_pago, monto_pendiente, observaciones) VALUES
(1, 1, '2026-08-20', 'activo', 75.00, 'parcial', 75.00, NULL),
(2, 1, '2026-08-22', 'activo', 0.00, 'pendiente', 150.00, NULL),
(3, 2, '2026-08-15', 'activo', 200.00, 'pagado', 0.00, 'Estudiante destacado'),
(4, 4, '2026-08-25', 'activo', 90.00, 'parcial', 90.00, NULL),
(5, 5, '2026-08-18', 'activo', 160.00, 'pagado', 0.00, NULL),
(6, 6, '2026-08-20', 'activo', 70.00, 'parcial', 70.00, 'Requiere refuerzo'),
(7, 8, '2026-08-23', 'activo', 0.00, 'pendiente', 130.00, NULL),
(8, 4, '2026-08-26', 'activo', 45.00, 'parcial', 135.00, NULL),
(9, 11, '2026-09-01', 'activo', 0.00, 'pendiente', 140.00, NULL),
(10, 6, '2026-09-02', 'activo', 70.00, 'parcial', 70.00, NULL);


-- ════════════════════════════════════════════════════════════════════════════════════
-- 13. PAGOS
-- ════════════════════════════════════════════════════════════════════════════════════
INSERT INTO pagos (id_inscripcion, fecha_pago, monto_pagado, metodo_pago, referencia_pago, comprobante_url, observaciones, creado_por) VALUES
(1, '2026-08-20', 75.00, 'transferencia', 'TRF-001234', '/comprobantes/pago_1.pdf', NULL, 11),
(3, '2026-08-18', 200.00, 'tarjeta', NULL, '/comprobantes/pago_3.pdf', 'Pago completo', 11),
(4, '2026-08-25', 90.00, 'efectivo', NULL, NULL, NULL, 11),
(5, '2026-08-22', 160.00, 'transferencia', 'TRF-001256', '/comprobantes/pago_5.pdf', NULL, 11),
(6, '2026-08-21', 70.00, 'efectivo', NULL, NULL, NULL, 11),
(8, '2026-08-26', 45.00, 'tarjeta', NULL, '/comprobantes/pago_8.pdf', NULL, 11),
(10, '2026-09-02', 70.00, 'transferencia', 'TRF-001300', '/comprobantes/pago_10.pdf', NULL, 11);

-- ════════════════════════════════════════════════════════════════════════════════════
-- 14. ASISTENCIA
-- ════════════════════════════════════════════════════════════════════════════════════
INSERT INTO asistencia (id_inscripcion, id_grupo, fecha_clase, presente, justificacion, observaciones) VALUES
(1, 1, '2026-09-01', TRUE, NULL, NULL),
(1, 1, '2026-09-03', TRUE, NULL, 'Llegó 5 minutos tarde'),
(1, 1, '2026-09-05', FALSE, 'Enfermo', 'Se recuperará pronto'),
(2, 1, '2026-09-01', TRUE, NULL, NULL),
(2, 1, '2026-09-03', TRUE, NULL, NULL),
(3, 2, '2026-09-01', TRUE, NULL, 'Alumno muy dedicado'),
(3, 2, '2026-09-02', TRUE, NULL, NULL),
(4, 4, '2026-09-08', TRUE, NULL, NULL),
(5, 5, '2026-09-10', TRUE, NULL, 'Excelente desempeño'),
(5, 5, '2026-09-15', TRUE, NULL, NULL),
(6, 6, '2026-09-05', TRUE, NULL, NULL),
(6, 6, '2026-09-10', FALSE, 'Ocupado', 'Trabajo urgente'),
(7, 8, '2026-09-03', TRUE, NULL, NULL),
(8, 4, '2026-09-08', FALSE, 'Razones personales', NULL);

-- ════════════════════════════════════════════════════════════════════════════════════
-- 15. EVALUACIONES
-- ════════════════════════════════════════════════════════════════════════════════════
INSERT INTO evaluaciones (id_inscripcion, id_grupo, tipo_evaluacion, fecha_evaluacion, calificacion, descripcion, observaciones_profesor) VALUES
(1, 1, 'practica', '2026-09-10', 75.50, 'Evaluación de técnica básica de guitarra', 'Buen progreso, necesita más práctica'),
(1, 1, 'teorica', '2026-09-15', 82.00, 'Teoría musical básica', 'Excelente comprensión'),
(2, 1, 'practica', '2026-09-10', 68.00, 'Evaluación de técnica básica de guitarra', 'Está mejorando'),
(3, 2, 'practica', '2026-09-12', 92.00, 'Evaluación técnica intermedia', 'Estudiante destacado'),
(3, 2, 'teorica', '2026-09-14', 88.50, 'Teoría musical intermedia', 'Excelente'),
(3, 2, 'presentacion', '2026-09-20', 90.00, 'Presentación de canción completa', 'Muy profesional'),
(4, 4, 'practica', '2026-09-15', 76.00, 'Técnica de guitarra eléctrica', 'Necesita trabajar rítmica'),
(5, 5, 'practica', '2026-09-17', 85.00, 'Técnica de bajo eléctrico', 'Muy buena coordinación'),
(6, 6, 'practica', '2026-09-12', 70.00, 'Técnica de flauta traversa', 'Trabajar respiración'),
(7, 8, 'practica', '2026-09-08', 65.00, 'Técnica básica de teclado', 'Necesita práctica diaria');


-- ════════════════════════════════════════════════════════════════════════════════════
-- 16. DESCUENTOS
-- ════════════════════════════════════════════════════════════════════════════════════
INSERT INTO descuentos (codigo_descuento, descripcion, porcentaje, monto_fijo, id_curso, fecha_inicio, fecha_fin, usos_disponibles, usos_realizados, activo) VALUES
('VERANO2026', 'Descuento de verano - 15% en todos los cursos', 15, NULL, NULL, '2026-06-01', '2026-08-31', -1, 0, TRUE),
('PROMO50', 'Descuento especial - $50 en cursos de guitarra', NULL, 50.00, 1, '2026-09-01', '2026-09-30', 20, 3, TRUE),
('HERMANOS', 'Descuento por hermanos inscritos - 10%', 10, NULL, NULL, '2026-01-01', '2026-12-31', -1, 0, TRUE),
('REFERIDO', 'Descuento por referencia - 20%', 20, NULL, NULL, '2026-08-01', '2026-10-31', 50, 2, TRUE),
('BIENVENIDA', 'Bienvenida nuevos estudiantes - $30', NULL, 30.00, NULL, '2026-09-01', '2026-09-15', 100, 5, TRUE),
('PIANO-DESC', 'Descuento especial en piano - 25%', 25, NULL, 8, '2026-09-01', '2026-09-30', 10, 1, TRUE);

-- ════════════════════════════════════════════════════════════════════════════════════
-- VERIFICACIÓN: Contar registros en cada tabla
-- ════════════════════════════════════════════════════════════════════════════════════
SELECT 'Categorías' as Tabla, COUNT(*) as Total FROM categorias_instrumento
UNION ALL
SELECT 'Instrumentos', COUNT(*) FROM instrumentos
UNION ALL
SELECT 'Instructores', COUNT(*) FROM instructores
UNION ALL
SELECT 'Instructor-Instrumento', COUNT(*) FROM instructor_instrumento
UNION ALL
SELECT 'Aulas', COUNT(*) FROM aulas
UNION ALL
SELECT 'Cursos', COUNT(*) FROM cursos
UNION ALL
SELECT 'Requisitos', COUNT(*) FROM requisitos_curso
UNION ALL
SELECT 'Grupos', COUNT(*) FROM grupos
UNION ALL
SELECT 'Horarios', COUNT(*) FROM horarios
UNION ALL
SELECT 'Estudiantes', COUNT(*) FROM estudiantes
UNION ALL
SELECT 'Usuarios', COUNT(*) FROM usuarios
UNION ALL
SELECT 'Inscripciones', COUNT(*) FROM inscripciones
UNION ALL
SELECT 'Pagos', COUNT(*) FROM pagos
UNION ALL
SELECT 'Asistencia', COUNT(*) FROM asistencia
UNION ALL
SELECT 'Evaluaciones', COUNT(*) FROM evaluaciones
UNION ALL
SELECT 'Descuentos', COUNT(*) FROM descuentos;














