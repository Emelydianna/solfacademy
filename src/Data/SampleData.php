<?php

declare(strict_types=1);

namespace App\Data;

/**
 * Datos de ejemplo con la misma forma que las tablas del esquema
 * (CATEGORÍA, INSTRUCTOR, etc.). Cuando la base de datos MySQL esté lista,
 * se sustituyen por consultas PDO sin tocar las vistas.
 */
final class SampleData
{
    /**
     * Filas de la tabla CATEGORÍA (+ icono presentacional de Bootstrap Icons).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function categorias(): array
    {
        return [
            ['id_categoria' => 1, 'nombre' => 'Guitarra', 'descripcion' => 'Ritmo, arpegio y acordes', 'icono' => 'bi-guitar'],
            ['id_categoria' => 2, 'nombre' => 'Piano', 'descripcion' => 'Técnica y arpegio legato', 'icono' => 'bi-music-note-beamed'],
            ['id_categoria' => 3, 'nombre' => 'Canto', 'descripcion' => 'Técnica vocal y respiración', 'icono' => 'bi-mic'],
            ['id_categoria' => 4, 'nombre' => 'Batería', 'descripcion' => 'Ritmo y coordinación de extremidades', 'icono' => 'bi-drum'],
            ['id_categoria' => 5, 'nombre' => 'Violín', 'descripcion' => 'Expresión, postura y afinación', 'icono' => 'bi-music-note'],
            ['id_categoria' => 6, 'nombre' => 'Clases Particulares', 'descripcion' => 'A tu ritmo, uno a uno', 'icono' => 'bi-person-workspace'],
            ['id_categoria' => 7, 'nombre' => 'Teoría Musical', 'descripcion' => 'Configuración, armonía y composición', 'icono' => 'bi-journal-music'],
        ];
    }

    /**
     * Filas de la tabla INSTRUCTOR (+ datos de presentación del prototipo).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function instructores(): array
    {
        return [
            [
                'id_instructor' => 1,
                'nombre' => 'Carlos',
                'apellido' => 'Mendoza',
                'rol' => 'Profesor de Piano',
                'anios_experiencia' => 12,
                'valoracion' => 4.9,
                'num_resenas' => 58,
                'email' => 'carlos.mendoza@solfacademy.com',
                'bio' => 'Licenciado del Real Conservatorio, 12 años de experiencia en enseñanza musical.',
                'biografia' => 'Carlos Mendoza es un apasionado educador musical con más de 15 años de '
                    . 'experiencia transformando la vida de sus alumnos a través del piano. Se formó en '
                    . 'el Real Conservatorio Superior de Música de Madrid y posteriormente realizó '
                    . 'estudios de posgrado en el Conservatorio de París, donde se especializó en '
                    . 'interpretación de piano clásico. Su enfoque combina la disciplina técnica con la '
                    . 'expresividad artística, ayudando a cada estudiante a encontrar su propia voz musical.',
                'especialidades' => ['Técnica de piano clásico', 'Acordes y armonía', 'Preparación de audiciones'],
                'experiencia' => ['Profesor en el Conservatorio de París', '12 años de experiencia en docencia', 'Dirigente de coros juveniles'],
            ],
            [
                'id_instructor' => 2,
                'nombre' => 'Sofía',
                'apellido' => 'Martínez',
                'rol' => 'Profesora de Violín',
                'anios_experiencia' => 8,
                'valoracion' => 4.8,
                'num_resenas' => 41,
                'email' => 'sofia.martinez@solfacademy.com',
                'bio' => 'Especialista en música clásica, 8 años dando clases a todos los niveles.',
                'biografia' => 'Sofía Martínez es violinista y pedagoga especializada en música clásica. '
                    . 'Formada en el Conservatorio de Viena, compagina su actividad de intérprete con la '
                    . 'docencia desde hace 8 años. Su clase se centra en la salud postural, el sonido y la '
                    . 'expresión, adaptándose a cada alumno desde iniciación hasta nivel de conservatorio.',
                'especialidades' => ['Técnica de arco y vibrato', 'Música de cámara', 'Preparación de audiciones'],
                'experiencia' => ['Violinista de la Orquesta de Cámara de Madrid', '8 años de experiencia en docencia', 'Formada en el Conservatorio de Viena'],
            ],
            [
                'id_instructor' => 3,
                'nombre' => 'Mateo',
                'apellido' => 'Silva',
                'rol' => 'Profesor de Guitarra',
                'anios_experiencia' => 10,
                'valoracion' => 4.9,
                'num_resenas' => 36,
                'email' => 'mateo.silva@solfacademy.com',
                'bio' => 'Formación en jazz y música popular, 10 años de experiencia.',
                'biografia' => 'Mateo Silva es guitarrista con formación en jazz y música popular. Con 10 '
                    . 'años de experiencia docente, ha acompañado a cientos de alumnos desde sus primeros '
                    . 'acordes hasta la improvisación con banda. Su enseñanza es práctica y cercana, '
                    . 'centrada en tocar canciones reales desde la primera clase.',
                'especialidades' => ['Ritmo y arpegios', 'Guitarra acústica y eléctrica', 'Improvisación básica'],
                'experiencia' => ['Guitarrista de sesión en estudios de Madrid', '10 años de experiencia en docencia', 'Formación en música popular y jazz'],
            ],
        ];
    }

    /**
     * Devuelve un instructor con todos los datos de la pantalla de perfil del
     * prototipo (biografía, especialidades, experiencia y cursos que imparte).
     * Devuelve null si el id no existe.
     *
     * @return array<string, mixed>|null
     */
    public static function instructorDetalle(int $id): ?array
    {
        $instructor = null;

        foreach (self::instructores() as $fila) {
            if ((int) $fila['id_instructor'] === $id) {
                $instructor = $fila;
                break;
            }
        }

        if ($instructor === null) {
            return null;
        }

        if (empty($instructor['biografia'])) {
            // Perfil genérico para instructores de ejemplo sin ficha propia
            $instructor['biografia'] = $instructor['bio']
                . ' Imparte clases en SolfAcademy con un enfoque práctico y personalizado, '
                . 'con material didáctico propio adaptado a cada alumno.';
            $instructor['especialidades'] = ['Atención personalizada', 'Material didáctico propio', 'Todos los niveles'];
            $instructor['experiencia'] = [$instructor['anios_experiencia'] . ' años de experiencia en docencia'];
            $instructor['num_resenas'] = $instructor['num_resenas'] ?? 0;
        }

        // Cursos que imparte (un instructor imparte grupos de CURSO)
        $instructor['cursos'] = array_values(array_filter(
            self::cursos(),
            static fn (array $curso): bool => $curso['instructor'] === $instructor['nombre'] . ' ' . $instructor['apellido']
        ));

        return $instructor;
    }

    /**
     * Filas de la tabla INSTRUMENTO (instrumentos del catálogo del prototipo).
     *
     * @return array<int, string>
     */
    public static function instrumentos(): array
    {
        return ['Guitarra', 'Batería', 'Canto', 'Piano', 'Violín'];
    }

    /**
     * Valores de la columna Nivel de la tabla CURSO.
     *
     * @return array<int, string>
     */
    public static function niveles(): array
    {
        return ['Principiante', 'Intermedio', 'Avanzado'];
    }

    /**
     * Franjas horarias derivadas de la tabla HORARIO (mañana / tarde / noche).
     *
     * @return array<int, string>
     */
    public static function horarios(): array
    {
        return ['Mañana', 'Tarde', 'Noche'];
    }

    /**
     * Filas de la tabla CURSO unidas con INSTRUMENTO (muestra de ejemplo
     * para el catálogo; el orden es el de "Más populares").
     *
     * @return array<int, array<string, mixed>>
     */
    public static function cursos(): array
    {
        return [
            [
                'id_curso' => 1,
                'id_instrumento' => 1,
                'instrumento' => 'Guitarra',
                'nombre' => 'Guitarra para Principiantes',
                'nivel' => 'Principiante',
                'precio' => 49,
                'horario' => 'Mañana',
                'instructor' => 'Mateo Silva',
                'descripcion' => 'Acordes básicos, ritmo y tus primeras canciones paso a paso.',
                'icono' => 'bi-guitar',
            ],
            [
                'id_curso' => 3,
                'id_instrumento' => 4,
                'instrumento' => 'Piano',
                'nombre' => 'Piano Clásico I: Fundamentos',
                'nivel' => 'Principiante',
                'precio' => 55,
                'horario' => 'Tarde',
                'instructor' => 'Carlos Mendoza',
                'descripcion' => 'Postura, lectura de partituras y ejercicios de ambas manos.',
                'icono' => 'bi-music-note-beamed',
            ],
            [
                'id_curso' => 4,
                'id_instrumento' => 4,
                'instrumento' => 'Piano',
                'nombre' => 'Piano Clásico II: Desarrollo de Técnica y Repertorio',
                'nivel' => 'Intermedio',
                'precio' => 79,
                'horario' => 'Mañana',
                'instructor' => 'Carlos Mendoza',
                'descripcion' => 'Armonía, escalas y piezas clásicas de Chopin y Bach.',
                'icono' => 'bi-music-note-beamed',
            ],
            [
                'id_curso' => 5,
                'id_instrumento' => 3,
                'instrumento' => 'Canto',
                'nombre' => 'Canto: Técnica Vocal y Respiración',
                'nivel' => 'Principiante',
                'precio' => 45,
                'horario' => 'Noche',
                'instructor' => 'Ana Ríos',
                'descripcion' => 'Control respiratorio, afinación y calentamiento vocal diario.',
                'icono' => 'bi-mic',
            ],
            [
                'id_curso' => 6,
                'id_instrumento' => 2,
                'instrumento' => 'Batería',
                'nombre' => 'Batería: Ritmo y Coordinación',
                'nivel' => 'Principiante',
                'precio' => 52,
                'horario' => 'Tarde',
                'instructor' => 'Diego Torres',
                'descripcion' => 'Técnica de baquetas, trabajo con metrónomo y primeros ritmos.',
                'icono' => 'bi-drum',
            ],
            [
                'id_curso' => 8,
                'id_instrumento' => 5,
                'instrumento' => 'Violín',
                'nombre' => 'Violín: Expresión y Afinación',
                'nivel' => 'Intermedio',
                'precio' => 62,
                'horario' => 'Mañana',
                'instructor' => 'Sofía Martínez',
                'descripcion' => 'Posición del arco, vibrato y expresión musical con repertorio propio.',
                'icono' => 'bi-music-note',
            ],
            [
                'id_curso' => 2,
                'id_instrumento' => 1,
                'instrumento' => 'Guitarra',
                'nombre' => 'Guitarra Intermedia: Arpegios y Ritmos',
                'nivel' => 'Intermedio',
                'precio' => 59,
                'horario' => 'Tarde',
                'instructor' => 'Mateo Silva',
                'descripcion' => 'Arpegios, punteos y ritmos de rock y pop aplicados a canciones reales.',
                'icono' => 'bi-guitar',
            ],
            [
                'id_curso' => 7,
                'id_instrumento' => 2,
                'instrumento' => 'Batería',
                'nombre' => 'Batería Avanzada: Grooves y Solos',
                'nivel' => 'Avanzado',
                'precio' => 69,
                'horario' => 'Noche',
                'instructor' => 'Diego Torres',
                'descripcion' => 'Grooves complejos, fills y desarrollo de solos con banda.',
                'icono' => 'bi-drum',
            ],
            [
                'id_curso' => 9,
                'id_instrumento' => 4,
                'instrumento' => 'Piano',
                'nombre' => 'Piano Avanzado: Improvisación',
                'nivel' => 'Avanzado',
                'precio' => 85,
                'horario' => 'Noche',
                'instructor' => 'Carlos Mendoza',
                'descripcion' => 'Improvisación sobre jazz y progresiones armónicas modernas.',
                'icono' => 'bi-music-note-beamed',
            ],
        ];
    }

    /**
     * Devuelve un curso de ejemplo con todos los datos de la pantalla de
     * detalle del prototipo (descripción, temario, reseñas, requisitos y
     * grupo). Devuelve null si el id no existe.
     *
     * @return array<string, mixed>|null
     */
    public static function cursoDetalle(int $id): ?array
    {
        $curso = null;

        foreach (self::cursos() as $fila) {
            if ((int) $fila['id_curso'] === $id) {
                $curso = $fila;
                break;
            }
        }

        if ($curso === null) {
            return null;
        }

        $detallePersonalizado = self::cursosDetalle()[$id] ?? null;

        if ($detallePersonalizado !== null) {
            return array_merge($curso, $detallePersonalizado);
        }

        // Detalle genérico para el resto de cursos de ejemplo
        $requisitoPorNivel = [
            'Principiante' => 'Sin requisitos previos',
            'Intermedio' => 'Curso de iniciación aprobado',
            'Avanzado' => 'Nivel intermedio aprobado',
        ];

        return array_merge($curso, [
            'descripcion_larga' => $curso['descripcion']
                . ' Un curso práctico con acompañamiento personalizado de nuestro equipo de instructores, '
                . 'horarios flexibles y material de estudio incluido.',
            'temario' => [
                ['titulo' => 'Fundamentos y técnica inicial', 'descripcion' => 'Calentamiento, postura y ejercicios base del instrumento.', 'duracion' => '3 semanas'],
                ['titulo' => 'Práctica guiada y repertorio', 'descripcion' => 'Aplicación de lo aprendido con canciones y piezas a tu nivel.', 'duracion' => '6 semanas'],
                ['titulo' => 'Interpretación y evaluación final', 'descripcion' => 'Montaje de la pieza final y presentación ante el instructor.', 'duracion' => '3 semanas'],
            ],
            'reseñas' => [],
            'requisito' => [
                'nivel_minimo' => $requisitoPorNivel[$curso['nivel']] ?? 'Consulta con tutoría',
                'edad_minima' => 14,
                'edad_maxima' => 99,
            ],
            'grupo' => [
                'horario' => $curso['horario'] . ' — 18:00 a 19:30',
                'aula' => 'Sede Centro — Aula 1',
                'duracion' => '12 semanas · 24 sesiones',
            ],
        ]);
    }

    /**
     * Detalle ampliado de cursos de ejemplo (por id_curso), con la forma de
     * las tablas CURSO, REQUISITO CURSO, GRUPO, HORARIO y AULA.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function cursosDetalle(): array
    {
        return [
            // Pantalla "detalle-curso" del prototipo
            4 => [
                'descripcion_larga' => 'Este curso intermedio está diseñado para estudiantes que ya dominan los '
                    . 'conocimientos básicos del piano y desean perfeccionar su técnica, interpretar piezas de '
                    . 'repertorio clásico y desarrollar sensibilidad musical. Se abordan conceptos de armonía '
                    . 'avanzada, lectura analítica y expresividad.',
                'temario' => [
                    [
                        'titulo' => 'Fundamentos de la armonía especial y análisis compuesto',
                        'descripcion' => 'Estudio de acordes de séptima, progresiones armónicas y análisis de '
                            . 'partituras de J. S. Bach.',
                        'duracion' => '3 semanas',
                    ],
                    [
                        'titulo' => 'El Barroco y Bach: Invenciones a dos voces',
                        'descripcion' => 'Independencia de manos, contrapunto y articulación barroca aplicada '
                            . 'a las Invenciones.',
                        'duracion' => '3 semanas',
                    ],
                    [
                        'titulo' => 'Expresividad romántica con Chopin: Nocturnos y valses',
                        'descripcion' => 'Rubato, dinámicas y fraseo del romanticismo a través del repertorio '
                            . 'de Chopin.',
                        'duracion' => '4 semanas',
                    ],
                    [
                        'titulo' => 'Integración final: improvisación y repertorio de recital',
                        'descripcion' => 'Improvisación guiada, montaje del repertorio final y presentación '
                            . 'de recital evaluada.',
                        'duracion' => '2 semanas',
                    ],
                ],
                'reseñas' => [
                    [
                        'autor' => 'Elena Vargas',
                        'iniciales' => 'EV',
                        'valoracion' => 5,
                        'texto' => 'El mejor curso de piano que he hecho. El temario está muy bien estructurado '
                            . 'y el profesor explica con mucha claridad.',
                    ],
                    [
                        'autor' => 'Javier Ortiz',
                        'iniciales' => 'JO',
                        'valoracion' => 5,
                        'texto' => 'Salí del curso tocando los Nocturnos de Chopin. La evaluación final da '
                            . 'mucha confianza y motivación.',
                    ],
                ],
                'requisito' => [
                    'nivel_minimo' => 'Piano Clásico I aprobado',
                    'edad_minima' => 14,
                    'edad_maxima' => 99,
                ],
                'grupo' => [
                    'horario' => 'Lunes y Miércoles — 18:00 a 19:30',
                    'aula' => 'Sede Centro — Aula 3',
                    'duracion' => '12 semanas · 24 sesiones',
                ],
            ],
        ];
    }

    /**
     * Reseñas de alumnos para la sección de testimonios.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function testimonios(): array
    {
        return [
            [
                'autor' => 'Laura Gómez',
                'iniciales' => 'LG',
                'valoracion' => 5,
                'texto' => 'Los cursos de canto cambiaron mi vida. La metodología es muy práctica y los profesores son increíbles. ¡Mi confianza ha aumentado enormemente!',
            ],
            [
                'autor' => 'Daniel Reyes',
                'iniciales' => 'DR',
                'valoracion' => 5,
                'texto' => 'Excelente metodología. Pude pasar de no saber nada a tocar piezas completas en solo 6 meses. ¡Totalmente recomendado!',
            ],
        ];
    }
}
