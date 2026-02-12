<?php

namespace QuickBite\Tests\Unit\Services;

use QuickBite\Tests\TestCase;
use PDO;
use PDOStatement;

// Incluir las dependencias del servicio
require_once dirname(__DIR__, 3) . '/config/quickbite_fees.php';
require_once dirname(__DIR__, 3) . '/services/ComisionService.php';

/**
 * Tests unitarios para ComisionService
 */
class ComisionServiceTest extends TestCase
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
     * Test: Crear instancia de ComisionService
     */
    public function testComisionServiceCanBeInstantiated(): void
    {
        $service = new \ComisionService($this->mockPdo);
        $this->assertInstanceOf(\ComisionService::class, $service);
    }

    /**
     * Test: Crear instancia sin conexión a BD
     */
    public function testComisionServiceCanBeInstantiatedWithoutDb(): void
    {
        $service = new \ComisionService(null);
        $this->assertInstanceOf(\ComisionService::class, $service);
    }

    /**
     * Test: Calcular distribución retorna array con claves esperadas
     */
    public function testCalcularDistribucionRetornaArrayCompleto(): void
    {
        $service = new \ComisionService(null);

        $resultado = $service->calcularDistribucion(
            subtotalProductos: 200.00,
            distanciaKm: 3.5,
            propina: 20.00
        );

        $this->assertIsArray($resultado);
        // La estructura es anidada: cliente, negocio, repartidor, quickbite
        $this->assertArrayHasKey('cliente', $resultado);
        $this->assertArrayHasKey('negocio', $resultado);
        $this->assertArrayHasKey('repartidor', $resultado);
        $this->assertArrayHasKey('quickbite', $resultado);

        // Verificar estructura del cliente
        $this->assertArrayHasKey('productos', $resultado['cliente']);
        $this->assertArrayHasKey('envio', $resultado['cliente']);
        $this->assertArrayHasKey('total', $resultado['cliente']);
    }

    /**
     * Test: Calcular envío para distancia normal
     */
    public function testCalcularEnvioDistanciaNormal(): void
    {
        $service = new \ComisionService(null);

        $costoEnvio = $service->calcularEnvio(distanciaKm: 3.0);

        $this->assertIsFloat($costoEnvio);
        $this->assertGreaterThan(0, $costoEnvio);
    }

    /**
     * Test: Calcular envío para distancia cero
     */
    public function testCalcularEnvioDistanciaCero(): void
    {
        $service = new \ComisionService(null);

        $costoEnvio = $service->calcularEnvio(distanciaKm: 0);

        // Debería haber un mínimo de envío
        $this->assertIsFloat($costoEnvio);
    }

    /**
     * Test: Calcular cargo de servicio sin membresía
     */
    public function testCalcularCargoServicioSinMembresia(): void
    {
        $service = new \ComisionService(null);

        $cargoServicio = $service->calcularCargoServicio();

        $this->assertIsFloat($cargoServicio);
    }

    /**
     * Test: Calcular pago a repartidor incluye propina
     */
    public function testCalcularPagoRepartidorIncluyePropina(): void
    {
        $service = new \ComisionService(null);

        $pagoSinPropina = $service->calcularPagoRepartidor(distanciaKm: 3.0, propina: 0);
        $pagoConPropina = $service->calcularPagoRepartidor(distanciaKm: 3.0, propina: 50.00);

        $this->assertEquals($pagoConPropina, $pagoSinPropina + 50.00);
    }

    /**
     * Test: Calcular comisión negocio retorna array
     */
    public function testCalcularComisionRetornaArray(): void
    {
        $service = new \ComisionService(null);

        $comision = $service->calcularComision(subtotalProductos: 500.00);

        $this->assertIsArray($comision);
    }

    /**
     * Test: Verificar membresía sin conexión retorna false
     */
    public function testVerificarMembresiaSinConexionRetornaFalse(): void
    {
        $service = new \ComisionService(null);

        $esMiembro = $service->verificarMembresiaClub(1);

        $this->assertFalse($esMiembro);
    }

    /**
     * Test: Verificar negocio premium sin conexión retorna false
     */
    public function testVerificarNegocioPremiumSinConexionRetornaFalse(): void
    {
        $service = new \ComisionService(null);

        $esPremium = $service->verificarNegocioPremium(1);

        $this->assertFalse($esPremium);
    }

    /**
     * Test: Verificar membresía con usuario miembro
     */
    public function testVerificarMembresiaConUsuarioMiembro(): void
    {
        $this->mockStmt->method('execute')
            ->willReturn(true);

        $this->mockStmt->method('fetch')
            ->willReturn([
                'es_miembro' => 1,
                'fecha_fin_membresia' => '2027-12-31'
            ]);

        $this->mockPdo->method('prepare')
            ->willReturn($this->mockStmt);

        $service = new \ComisionService($this->mockPdo);

        $esMiembro = $service->verificarMembresiaClub(1);

        $this->assertTrue($esMiembro);
    }

    /**
     * Test: Verificar membresía con usuario no miembro
     */
    public function testVerificarMembresiaConUsuarioNoMiembro(): void
    {
        $this->mockStmt->method('execute')
            ->willReturn(true);

        $this->mockStmt->method('fetch')
            ->willReturn(false);

        $this->mockPdo->method('prepare')
            ->willReturn($this->mockStmt);

        $service = new \ComisionService($this->mockPdo);

        $esMiembro = $service->verificarMembresiaClub(1);

        $this->assertFalse($esMiembro);
    }

    /**
     * Test: Obtener comisión negocio normal vs premium
     */
    public function testObtenerComisionNegocioNormalVsPremium(): void
    {
        // Mock para negocio NO premium
        $mockStmtNormal = $this->createMock(PDOStatement::class);
        $mockStmtNormal->method('execute')->willReturn(true);
        $mockStmtNormal->method('fetch')->willReturn(false);

        $mockPdoNormal = $this->createMock(PDO::class);
        $mockPdoNormal->method('prepare')->willReturn($mockStmtNormal);

        $serviceNormal = new \ComisionService($mockPdoNormal);
        $comisionNormal = $serviceNormal->obtenerComisionNegocio(1);

        // Mock para negocio premium
        $mockStmtPremium = $this->createMock(PDOStatement::class);
        $mockStmtPremium->method('execute')->willReturn(true);
        $mockStmtPremium->method('fetch')->willReturn([
            'es_premium' => 1,
            'fecha_fin_premium' => '2027-12-31'
        ]);

        $mockPdoPremium = $this->createMock(PDO::class);
        $mockPdoPremium->method('prepare')->willReturn($mockStmtPremium);

        $servicePremium = new \ComisionService($mockPdoPremium);
        $comisionPremium = $servicePremium->obtenerComisionNegocio(2);

        // Comisión premium debería ser menor
        $this->assertLessThan($comisionNormal, $comisionPremium);
    }

    /**
     * Test: Calcular total pedido retorna desglose completo
     */
    public function testCalcularTotalPedidoRetornaDesglose(): void
    {
        $service = new \ComisionService(null);

        $resultado = $service->calcularTotalPedido(
            subtotalProductos: 250.00,
            distanciaKm: 4.0,
            propina: 30.00
        );

        $this->assertIsArray($resultado);
        $this->assertArrayHasKey('subtotal_productos', $resultado);
        $this->assertArrayHasKey('costo_envio', $resultado);
        $this->assertArrayHasKey('cargo_servicio', $resultado);
        $this->assertArrayHasKey('propina', $resultado);
        $this->assertArrayHasKey('total', $resultado);
        $this->assertArrayHasKey('es_miembro_club', $resultado);

        // Verificar que el total es la suma de los componentes
        $totalEsperado = $resultado['subtotal_productos'] +
                         $resultado['costo_envio'] +
                         $resultado['cargo_servicio'] +
                         $resultado['propina'];

        $this->assertEquals($totalEsperado, $resultado['total']);
    }

    /**
     * Test: Dentro de rango de entrega
     */
    public function testDentroDeRangoEntrega(): void
    {
        $service = new \ComisionService(null);

        // Distancia dentro del rango
        $this->assertTrue($service->dentroDeRangoEntrega(5.0));

        // Distancia en el límite (asumiendo máximo de 15km)
        $this->assertTrue($service->dentroDeRangoEntrega(15.0));
    }

    /**
     * Test: Fuera de rango de entrega
     */
    public function testFueraDeRangoEntrega(): void
    {
        $service = new \ComisionService(null);

        // Distancia muy grande
        $this->assertFalse($service->dentroDeRangoEntrega(50.0));
        $this->assertFalse($service->dentroDeRangoEntrega(100.0));
    }

    /**
     * Test: Registrar ahorro sin conexión retorna false
     */
    public function testRegistrarAhorroSinConexionRetornaFalse(): void
    {
        $service = new \ComisionService(null);

        $resultado = $service->registrarAhorroMiembro(
            idUsuario: 1,
            idPedido: 100,
            tipoAhorro: 'envio',
            montoAhorrado: 25.00
        );

        $this->assertFalse($resultado);
    }

    /**
     * Test: Obtener beneficios miembro sin conexión retorna null
     */
    public function testObtenerBeneficiosSinConexionRetornaNull(): void
    {
        $service = new \ComisionService(null);

        $resultado = $service->obtenerBeneficiosMiembro(1);

        $this->assertNull($resultado);
    }

    /**
     * Test: Valores son redondeados correctamente
     */
    public function testValoresSonRedondeados(): void
    {
        $service = new \ComisionService(null);

        $resultado = $service->calcularTotalPedido(
            subtotalProductos: 199.99,
            distanciaKm: 3.333,
            propina: 15.555
        );

        // Verificar que los valores tienen máximo 2 decimales
        $this->assertEquals(
            round($resultado['subtotal_productos'], 2),
            $resultado['subtotal_productos']
        );
        $this->assertEquals(
            round($resultado['total'], 2),
            $resultado['total']
        );
    }
}
