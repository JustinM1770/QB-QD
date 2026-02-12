<?php

namespace QuickBite\Tests\Unit\Models;

use QuickBite\Tests\TestCase;
use PDO;
use PDOStatement;

// Incluir el modelo Pedido
require_once dirname(__DIR__, 3) . '/models/Pedido.php';

/**
 * Tests unitarios para el modelo Pedido
 */
class PedidoTest extends TestCase
{
    private $mockPdo;
    private $mockStmt;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear mocks
        $this->mockStmt = $this->createMock(PDOStatement::class);
        $this->mockPdo = $this->createMock(PDO::class);
    }

    /**
     * Test: Crear instancia de Pedido
     */
    public function testPedidoCanBeInstantiated(): void
    {
        $pedido = new \Pedido($this->mockPdo);
        $this->assertInstanceOf(\Pedido::class, $pedido);
    }

    /**
     * Test: Propiedades por defecto del pedido
     */
    public function testPedidoHasDefaultProperties(): void
    {
        $pedido = new \Pedido($this->mockPdo);

        $this->assertEquals(0.00, $pedido->cargo_servicio);
        $this->assertEquals(0.00, $pedido->impuestos);
        $this->assertEquals(0.00, $pedido->propina);
        $this->assertEquals(0.00, $pedido->monto_efectivo);
        $this->assertEquals('delivery', $pedido->tipo_pedido);
        $this->assertEquals(0, $pedido->es_programado);
        $this->assertNull($pedido->pickup_time);
        $this->assertNull($pedido->fecha_programada);
    }

    /**
     * Test: Asignar propiedades al pedido
     */
    public function testPedidoPropertiesCanBeSet(): void
    {
        $pedido = new \Pedido($this->mockPdo);

        $pedido->id_usuario = 1;
        $pedido->id_negocio = 5;
        $pedido->total_productos = 100.00;
        $pedido->costo_envio = 25.00;
        $pedido->monto_total = 125.00;
        $pedido->tipo_pedido = 'pickup';

        $this->assertEquals(1, $pedido->id_usuario);
        $this->assertEquals(5, $pedido->id_negocio);
        $this->assertEquals(100.00, $pedido->total_productos);
        $this->assertEquals(25.00, $pedido->costo_envio);
        $this->assertEquals(125.00, $pedido->monto_total);
        $this->assertEquals('pickup', $pedido->tipo_pedido);
    }

    /**
     * Test: Tipo de pedido válido (delivery o pickup)
     */
    public function testTipoPedidoValues(): void
    {
        $pedido = new \Pedido($this->mockPdo);

        // Delivery por defecto
        $this->assertEquals('delivery', $pedido->tipo_pedido);

        // Cambiar a pickup
        $pedido->tipo_pedido = 'pickup';
        $this->assertEquals('pickup', $pedido->tipo_pedido);
    }

    /**
     * Test: Crear pedido ejecuta query correcta
     */
    public function testCrearPedidoExecutesInsertQuery(): void
    {
        // Configurar mock para simular insert exitoso
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $this->mockStmt->method('bindParam')
            ->willReturn(true);

        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('INSERT INTO'))
            ->willReturn($this->mockStmt);

        $this->mockPdo->method('lastInsertId')
            ->willReturn('123');

        $pedido = new \Pedido($this->mockPdo);
        $pedido->id_usuario = 1;
        $pedido->id_negocio = 1;
        $pedido->id_direccion = 1;
        $pedido->id_estado = 1;
        $pedido->total_productos = 100.00;
        $pedido->costo_envio = 20.00;
        $pedido->monto_total = 120.00;

        $result = $pedido->crear();

        $this->assertTrue($result);
        $this->assertEquals('123', $pedido->id_pedido);
    }

    /**
     * Test: Crear pedido falla retorna mensaje de error
     */
    public function testCrearPedidoFailureReturnsError(): void
    {
        $this->mockStmt->method('execute')
            ->willReturn(false);

        $this->mockStmt->method('bindParam')
            ->willReturn(true);

        $this->mockStmt->method('errorInfo')
            ->willReturn(['00000', null, 'Test error message']);

        $this->mockPdo->method('prepare')
            ->willReturn($this->mockStmt);

        $pedido = new \Pedido($this->mockPdo);
        $pedido->id_usuario = 1;
        $pedido->id_negocio = 1;
        $pedido->id_estado = 1;
        $pedido->total_productos = 100.00;
        $pedido->monto_total = 100.00;

        $result = $pedido->crear();

        $this->assertIsString($result);
        $this->assertStringContainsString('Error', $result);
    }

    /**
     * Test: Agregar detalle de pedido
     */
    public function testAgregarDetalleExecutesInsertQuery(): void
    {
        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $this->mockStmt->method('bindParam')
            ->willReturn(true);

        $this->mockPdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('INSERT INTO detalles_pedido'))
            ->willReturn($this->mockStmt);

        $pedido = new \Pedido($this->mockPdo);

        $result = $pedido->agregarDetalle(
            id_pedido: 1,
            id_producto: 5,
            cantidad: 2,
            precio_unitario: 50.00,
            subtotal: 100.00
        );

        $this->assertTrue($result);
    }

    /**
     * Test: Estados de pedido válidos (1-7)
     */
    public function testEstadosPedidoValidos(): void
    {
        $pedido = new \Pedido($this->mockPdo);

        // Estados válidos según el modelo:
        // 1:pendiente, 2:confirmado, 3:en_preparacion, 4:listo,
        // 5:en_camino, 6:entregado, 7:cancelado
        $estadosValidos = [1, 2, 3, 4, 5, 6, 7];

        foreach ($estadosValidos as $estado) {
            $pedido->id_estado = $estado;
            $this->assertEquals($estado, $pedido->id_estado);
            $this->assertGreaterThanOrEqual(1, $pedido->id_estado);
            $this->assertLessThanOrEqual(7, $pedido->id_estado);
        }
    }

    /**
     * Test: Cálculo de monto total correcto
     */
    public function testCalculoMontoTotal(): void
    {
        $pedido = new \Pedido($this->mockPdo);

        $pedido->total_productos = 200.00;
        $pedido->costo_envio = 35.00;
        $pedido->cargo_servicio = 10.00;
        $pedido->impuestos = 32.00;
        $pedido->propina = 20.00;

        // Calcular monto total esperado
        $expectedTotal = $pedido->total_productos +
                         $pedido->costo_envio +
                         $pedido->cargo_servicio +
                         $pedido->impuestos +
                         $pedido->propina;

        $pedido->monto_total = $expectedTotal;

        $this->assertEquals(297.00, $pedido->monto_total);
    }

    /**
     * Test: Pedido programado tiene fecha
     */
    public function testPedidoProgramadoTieneFecha(): void
    {
        $pedido = new \Pedido($this->mockPdo);

        // Pedido normal
        $this->assertEquals(0, $pedido->es_programado);
        $this->assertNull($pedido->fecha_programada);

        // Pedido programado
        $pedido->es_programado = 1;
        $pedido->fecha_programada = '2026-01-20 18:00:00';

        $this->assertEquals(1, $pedido->es_programado);
        $this->assertEquals('2026-01-20 18:00:00', $pedido->fecha_programada);
    }

    /**
     * Test: Método de pago puede ser null
     */
    public function testMetodoPagoPuedeSerNull(): void
    {
        $pedido = new \Pedido($this->mockPdo);

        $pedido->id_metodo_pago = null;
        $this->assertNull($pedido->id_metodo_pago);

        $pedido->metodo_pago = 'efectivo';
        $this->assertEquals('efectivo', $pedido->metodo_pago);
    }
}
