<?php

namespace QuickBite\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use PDO;

/**
 * Clase base para todos los tests de QuickBite
 */
abstract class TestCase extends BaseTestCase
{
    protected ?PDO $db = null;

    /**
     * Configuración antes de cada test
     */
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Limpieza después de cada test
     */
    protected function tearDown(): void
    {
        $this->db = null;
        parent::tearDown();
    }

    /**
     * Obtener conexión a base de datos de prueba
     */
    protected function getDatabase(): ?PDO
    {
        if ($this->db === null) {
            $this->db = getTestDatabase();
        }
        return $this->db;
    }

    /**
     * Verificar si la base de datos está disponible
     */
    protected function isDatabaseAvailable(): bool
    {
        return $this->getDatabase() !== null;
    }

    /**
     * Saltar test si no hay base de datos disponible
     */
    protected function requireDatabase(): void
    {
        if (!$this->isDatabaseAvailable()) {
            $this->markTestSkipped('Base de datos no disponible para este test.');
        }
    }

    /**
     * Crear un mock de PDO para tests unitarios sin BD
     */
    protected function createMockPDO(): PDO
    {
        return $this->createMock(PDO::class);
    }

    /**
     * Helper para crear datos de prueba de usuario
     */
    protected function createTestUserData(array $overrides = []): array
    {
        return array_merge([
            'nombre' => 'Usuario Test',
            'apellido' => 'Apellido Test',
            'email' => 'test_' . uniqid() . '@example.com',
            'telefono' => '4491234567',
            'password' => password_hash('TestPassword123!', PASSWORD_DEFAULT),
            'activo' => 1,
            'verificado' => 1
        ], $overrides);
    }

    /**
     * Helper para crear datos de prueba de pedido
     */
    protected function createTestOrderData(array $overrides = []): array
    {
        return array_merge([
            'id_usuario' => 1,
            'id_negocio' => 1,
            'total' => 150.00,
            'subtotal' => 130.00,
            'costo_envio' => 20.00,
            'metodo_pago' => 'efectivo',
            'id_estado' => 1,
            'direccion_entrega' => 'Calle Test 123, Colonia Centro'
        ], $overrides);
    }

    /**
     * Helper para crear datos de prueba de producto
     */
    protected function createTestProductData(array $overrides = []): array
    {
        return array_merge([
            'id_negocio' => 1,
            'nombre' => 'Producto Test',
            'descripcion' => 'Descripción del producto de prueba',
            'precio' => 99.00,
            'id_categoria' => 1,
            'disponible' => 1,
            'activo' => 1
        ], $overrides);
    }
}
