<?php

declare(strict_types=1);

namespace Tests\Application\Admin;

use App\Application\Admin\EstudiantesService;
use App\Application\Admin\GruposService;
use App\Application\Admin\MigracionGestion;
use PDO;
use Slim\Views\PhpRenderer;
use Tests\TestCase;

/** Todas las escrituras de prueba quedan en tablas temporales de esta conexión. */
abstract class GestionOperativaTestCase extends TestCase
{
    protected ?PDO $pdo = null;
    protected GruposService $grupos;
    protected EstudiantesService $estudiantes;
    private array $temporales = [];
    private array $sesionAnterior = [];

    protected function setUp(): void
    {
        $this->sesionAnterior = $_SESSION;
        if (getenv('SOLFACADEMY_TEST_DB') !== '1') {
            $this->markTestSkipped('Activa SOLFACADEMY_TEST_DB=1 para usar MySQL temporal.');
        }
        $db = require __DIR__ . '/../../../config/db.php';
        $this->pdo = new PDO("mysql:host={$db['host']};dbname={$db['dbname']};charset={$db['charset']}", $db['user'], $db['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
        foreach (['categoria', 'instrumento', 'instructor', 'instructor_instrumento', 'curso', 'requisito_curso', 'descuento', 'configuracion', 'aula', 'grupo', 'horario', 'estudiante', 'usuario', 'inscripcion', 'inscripcion_auditoria', 'pago', 'evaluacion', 'asistencia', 'estudiante_foto', 'reserva_clase_extra', 'cabina', 'reserva_cabina'] as $tabla) {
            $this->pdo->exec("CREATE TEMPORARY TABLE operativa_molde LIKE $tabla");
            $this->pdo->exec("CREATE TEMPORARY TABLE $tabla LIKE operativa_molde");
            $this->pdo->exec('DROP TEMPORARY TABLE operativa_molde');
            $this->temporales[] = $tabla;
        }
        MigracionGestion::columnas($this->pdo);
        $this->pdo->exec("INSERT INTO categoria (id_categoria, nombre) VALUES (1, 'Cuerdas'), (2, 'Teclados')");
        $this->pdo->exec("INSERT INTO instrumento (id_instrumento, id_categoria, nombre) VALUES (1, 1, 'Violín'), (2, 2, 'Piano')");
        $this->pdo->exec("INSERT INTO instructor (id_instructor, nombre, apellido, activo) VALUES (1, 'Ana', 'López', 1), (2, 'Carlos', 'Mendoza', 1), (3, 'Laura', 'Ruiz', 1), (4, 'Inactivo', 'Profesor', 0)");
        $this->pdo->exec('INSERT INTO instructor_instrumento (id_instructor, id_instrumento) VALUES (1, 1), (2, 2), (3, 1), (4, 1)');
        $this->pdo->exec("INSERT INTO aula (id_aula, nombre, capacidad, activa) VALUES (1, 'Aula 1', 12, 1), (2, 'Aula 2', 20, 1), (3, 'Cerrada', 12, 0)");
        $this->pdo->exec("INSERT INTO curso (id_curso, id_instrumento, nombre, nivel, precio, max_grupos) VALUES (1, 1, 'Violín inicial', 'Principiante', 49.95, 3), (2, 1, 'Violín intermedio', 'Intermedio', 69.95, 3), (3, 2, 'Piano', 'Principiante', 55.00, 3)");
        $this->pdo->exec("INSERT INTO estudiante (id_estudiante, nombre, apellido, email, fecha_inscripcion) VALUES (1, 'Sofía', 'Alumno', 'alumno@prueba.test', '2026-01-01'), (2, 'Sin', 'Cuenta', 'sin@prueba.test', NULL)");
        $stmt = $this->pdo->prepare("INSERT INTO usuario (id_usuario, email_usuario, password_hash, rol, id_estudiante) VALUES (1, 'admin@prueba.test', :admin, 'admin', NULL), (2, 'alumno@prueba.test', :alumno, 'estudiante', 1)");
        $stmt->execute(['admin' => password_hash('PruebaSegura123', PASSWORD_DEFAULT), 'alumno' => password_hash('PruebaSegura123', PASSWORD_DEFAULT)]);
        $this->grupos = new GruposService($this->pdo);
        $this->estudiantes = new EstudiantesService($this->pdo);
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        if ($this->pdo && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        foreach (array_reverse($this->temporales) as $tabla) {
            $this->pdo->exec("DROP TEMPORARY TABLE IF EXISTS $tabla");
        }
        $_SESSION = $this->sesionAnterior;
        $this->pdo = null;
    }

    protected function datosGrupo(array $cambios = []): array
    {
        return array_merge(['id_curso' => '1', 'id_instructor' => '1', 'id_aula' => '1', 'capacidad_maxima' => '12',
            'fecha_inicio' => '2030-01-01', 'fecha_fin' => '2030-12-31', 'estado' => 'activo',
            'horarios' => [['dia_semana' => 'Lunes', 'hora_inicio' => '09:00', 'hora_fin' => '10:00']]], $cambios);
    }

    protected function crearGrupo(array $cambios = []): int
    {
        $r = $this->grupos->guardar($this->datosGrupo($cambios));
        $this->assertSame([], $r['errores']);
        return $r['id'];
    }

    protected function datosEstudiante(array $cambios = []): array
    {
        return array_merge(['nombre' => 'Nuevo', 'apellido' => 'Alumno', 'email' => 'nuevo@prueba.test', 'telefono' => '+503 1234 5678',
            'direccion' => 'Dirección', 'fecha_inscripcion' => '2026-01-01'], $cambios);
    }

    protected function matricular(int $grupo, string $estado = 'Activa', int $estudiante = 1): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO inscripcion (id_estudiante, id_grupo, fecha, precio_inscripcion, estado) VALUES (:e, :g, CURDATE(), 49.95, :estado)');
        $stmt->execute(['e' => $estudiante, 'g' => $grupo, 'estado' => $estado]);
        return (int) $this->pdo->lastInsertId();
    }

    protected function appAdmin(): \Slim\App
    {
        $_SESSION['usuario'] = ['id' => 1, 'rol' => 'admin', 'nombre' => 'Administrador', 'id_referencia' => 0, 'version_sesion' => 0];
        $app = $this->getAppInstance();
        $app->getContainer()->set(PDO::class, $this->pdo);
        $app->getContainer()->set(PhpRenderer::class, new PhpRenderer(__DIR__ . '/../../../templates'));
        return $app;
    }
}
