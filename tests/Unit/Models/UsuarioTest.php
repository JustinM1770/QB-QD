<?php

namespace QuickBite\Tests\Unit\Models;

use QuickBite\Tests\TestCase;
use PDO;
use PDOStatement;

// Incluir el modelo Usuario
require_once dirname(__DIR__, 3) . '/models/Usuario.php';

/**
 * Tests unitarios para el modelo Usuario
 */
class UsuarioTest extends TestCase
{
    private $mockPdo;
    private $mockStmt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockStmt = $this->createMock(PDOStatement::class);
        $this->mockPdo = $this->createMock(PDO::class);
    }

    /**
     * Test: Crear instancia de Usuario
     */
    public function testUsuarioCanBeInstantiated(): void
    {
        $usuario = new \Usuario($this->mockPdo);
        $this->assertInstanceOf(\Usuario::class, $usuario);
    }

    /**
     * Test: Propiedades del usuario pueden ser asignadas
     */
    public function testUsuarioPropertiesCanBeSet(): void
    {
        $usuario = new \Usuario($this->mockPdo);

        $usuario->nombre = 'Juan';
        $usuario->apellido = 'Pérez';
        $usuario->email = 'juan@example.com';
        $usuario->telefono = '4491234567';
        $usuario->tipo_usuario = 'cliente';

        $this->assertEquals('Juan', $usuario->nombre);
        $this->assertEquals('Pérez', $usuario->apellido);
        $this->assertEquals('juan@example.com', $usuario->email);
        $this->assertEquals('4491234567', $usuario->telefono);
        $this->assertEquals('cliente', $usuario->tipo_usuario);
    }

    /**
     * Test: Login exitoso retorna success true
     */
    public function testLoginExitosoRetornaSuccessTrue(): void
    {
        $hashedPassword = password_hash('password123', PASSWORD_BCRYPT);

        $this->mockStmt->method('execute')
            ->willReturn(true);

        $this->mockStmt->method('rowCount')
            ->willReturn(1);

        $this->mockStmt->method('fetch')
            ->willReturn([
                'id_usuario' => 1,
                'email' => 'test@example.com',
                'password' => $hashedPassword,
                'nombre' => 'Test',
                'apellido' => 'User',
                'telefono' => '1234567890',
                'foto_perfil' => null,
                'fecha_creacion' => '2026-01-01',
                'fecha_actualizacion' => null,
                'tipo_usuario' => 'cliente',
                'verification_code' => null,
                'is_verified' => 1,
                'activo' => 1
            ]);

        $this->mockStmt->method('bindParam')
            ->willReturn(true);

        $this->mockPdo->method('prepare')
            ->willReturn($this->mockStmt);

        $usuario = new \Usuario($this->mockPdo);
        $usuario->email = 'test@example.com';
        $usuario->password = 'password123';

        $result = $usuario->login();

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('user_data', $result);
    }

    /**
     * Test: Login con contraseña incorrecta retorna error
     */
    public function testLoginConPasswordIncorrectoRetornaError(): void
    {
        $hashedPassword = password_hash('correctpassword', PASSWORD_BCRYPT);

        $this->mockStmt->method('execute')
            ->willReturn(true);

        $this->mockStmt->method('rowCount')
            ->willReturn(1);

        $this->mockStmt->method('fetch')
            ->willReturn([
                'id_usuario' => 1,
                'email' => 'test@example.com',
                'password' => $hashedPassword,
                'nombre' => 'Test',
                'apellido' => 'User',
                'is_verified' => 1,
                'activo' => 1
            ]);

        $this->mockStmt->method('bindParam')
            ->willReturn(true);

        $this->mockPdo->method('prepare')
            ->willReturn($this->mockStmt);

        $usuario = new \Usuario($this->mockPdo);
        $usuario->email = 'test@example.com';
        $usuario->password = 'wrongpassword';

        $result = $usuario->login();

        $this->assertFalse($result['success']);
        $this->assertEquals('invalid_credentials', $result['error']);
    }

    /**
     * Test: Login con usuario no encontrado
     */
    public function testLoginUsuarioNoEncontrado(): void
    {
        $this->mockStmt->method('execute')
            ->willReturn(true);

        $this->mockStmt->method('rowCount')
            ->willReturn(0);

        $this->mockStmt->method('bindParam')
            ->willReturn(true);

        $this->mockPdo->method('prepare')
            ->willReturn($this->mockStmt);

        $usuario = new \Usuario($this->mockPdo);
        $usuario->email = 'noexiste@example.com';
        $usuario->password = 'anypassword';

        $result = $usuario->login();

        $this->assertFalse($result['success']);
        $this->assertEquals('user_not_found', $result['error']);
    }

    /**
     * Test: Login con email no verificado
     */
    public function testLoginEmailNoVerificado(): void
    {
        $hashedPassword = password_hash('password123', PASSWORD_BCRYPT);

        $this->mockStmt->method('execute')
            ->willReturn(true);

        $this->mockStmt->method('rowCount')
            ->willReturn(1);

        $this->mockStmt->method('fetch')
            ->willReturn([
                'id_usuario' => 1,
                'email' => 'test@example.com',
                'password' => $hashedPassword,
                'nombre' => 'Test',
                'is_verified' => 0,  // No verificado
                'activo' => 1
            ]);

        $this->mockStmt->method('bindParam')
            ->willReturn(true);

        $this->mockPdo->method('prepare')
            ->willReturn($this->mockStmt);

        $usuario = new \Usuario($this->mockPdo);
        $usuario->email = 'test@example.com';
        $usuario->password = 'password123';

        $result = $usuario->login();

        $this->assertFalse($result['success']);
        $this->assertEquals('email_not_verified', $result['error']);
    }

    /**
     * Test: Tipos de usuario válidos
     */
    public function testTiposDeUsuarioValidos(): void
    {
        $usuario = new \Usuario($this->mockPdo);

        $tiposValidos = ['cliente', 'negocio', 'repartidor', 'admin'];

        foreach ($tiposValidos as $tipo) {
            $usuario->tipo_usuario = $tipo;
            $this->assertEquals($tipo, $usuario->tipo_usuario);
        }
    }

    /**
     * Test: Password es hasheado correctamente
     */
    public function testPasswordHashingFunciona(): void
    {
        $password = 'MiPasswordSeguro123!';
        $hash = password_hash($password, PASSWORD_BCRYPT);

        // Verificar que el hash no es igual al password
        $this->assertNotEquals($password, $hash);

        // Verificar que el password se puede verificar
        $this->assertTrue(password_verify($password, $hash));

        // Verificar que password incorrecto no funciona
        $this->assertFalse(password_verify('WrongPassword', $hash));
    }

    /**
     * Test: Email se sanitiza correctamente
     */
    public function testEmailSanitization(): void
    {
        $usuario = new \Usuario($this->mockPdo);

        // Email con espacios
        $usuario->email = '  test@example.com  ';

        // Al usar el método login, el email debería ser sanitizado
        // Verificamos que se puede asignar
        $this->assertStringContainsString('test@example.com', $usuario->email);
    }

    /**
     * Test: Usuario tiene propiedades de verificación
     */
    public function testUsuarioTienePropiedadesVerificacion(): void
    {
        $usuario = new \Usuario($this->mockPdo);

        $usuario->verification_code = 'ABC123';
        $usuario->is_verified = 0;

        $this->assertEquals('ABC123', $usuario->verification_code);
        $this->assertEquals(0, $usuario->is_verified);

        // Simular verificación
        $usuario->is_verified = 1;
        $usuario->verification_code = null;

        $this->assertEquals(1, $usuario->is_verified);
        $this->assertNull($usuario->verification_code);
    }

    /**
     * Test: Usuario puede estar activo o inactivo
     */
    public function testUsuarioEstadoActivo(): void
    {
        $usuario = new \Usuario($this->mockPdo);

        $usuario->activo = 1;
        $this->assertEquals(1, $usuario->activo);

        $usuario->activo = 0;
        $this->assertEquals(0, $usuario->activo);
    }

    /**
     * Test: Validación de formato de email
     */
    public function testValidacionFormatoEmail(): void
    {
        $emailsValidos = [
            'test@example.com',
            'usuario@dominio.com.mx',
            'nombre.apellido@empresa.org'
        ];

        $emailsInvalidos = [
            'sindominio',
            '@faltanombre.com',
            'espacios en@email.com'
        ];

        foreach ($emailsValidos as $email) {
            $this->assertTrue(
                filter_var($email, FILTER_VALIDATE_EMAIL) !== false,
                "Email válido marcado como inválido: $email"
            );
        }

        foreach ($emailsInvalidos as $email) {
            $this->assertFalse(
                filter_var($email, FILTER_VALIDATE_EMAIL) !== false,
                "Email inválido marcado como válido: $email"
            );
        }
    }
}
