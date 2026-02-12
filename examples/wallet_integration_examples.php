<?php
/**
 * QuickBite - Ejemplo de Integración del Sistema de Wallet
 * Casos de uso prácticos para integración con el sistema de pedidos
 * 
 * @version 1.0.0
 * @date 2025-11-20
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/models/WalletService.php';
class WalletIntegrationExample {
    
    private $walletService;
    private $conn;
    public function __construct($connection) {
        $this->conn = $connection;
        $this->walletService = new WalletService($connection);
    }
    /**
     * CASO 1: Procesar pago de pedido completo
     * - Cliente paga pedido
     * - Se cobra comisión al negocio
     * - Se paga al repartidor
     */
    public function procesarPagoPedido($pedidoId, $clienteId, $negocioId, $repartidorId, $montoTotal) {
        try {
            $this->conn->begin_transaction();
            // Configuración de comisiones
            $comisionPlataforma = $montoTotal * 0.10; // 10% para la plataforma
            $comisionRepartidor = $montoTotal * 0.15; // 15% para el repartidor
            $pagoNegocio = $montoTotal - $comisionPlataforma - $comisionRepartidor;
            // 1. Cobrar comisión al negocio
            $resultNegocio = $this->walletService->addTransaction(
                userId: $negocioId,
                amount: -$comisionPlataforma,
                type: 'commission',
                refId: $pedidoId,
                refType: 'order',
                description: "Comisión por pedido #{$pedidoId}",
                metadata: [
                    'order_total' => $montoTotal,
                    'commission_rate' => 0.10,
                    'client_id' => $clienteId
                ]
            );
            if (!$resultNegocio['success']) {
                throw new Exception("Error al cobrar comisión al negocio: " . $resultNegocio['message']);
            }
            // 2. Acreditar ganancia al negocio
            $resultGananciaNegocio = $this->walletService->addTransaction(
                amount: $pagoNegocio,
                type: 'deposit',
                description: "Pago por pedido #{$pedidoId}",
                    'net_amount' => $pagoNegocio
            if (!$resultGananciaNegocio['success']) {
                throw new Exception("Error al acreditar al negocio: " . $resultGananciaNegocio['message']);
            // 3. Pagar al repartidor
            $resultRepartidor = $this->walletService->addTransaction(
                userId: $repartidorId,
}
                amount: $comisionRepartidor,
                description: "Pago por entrega #{$pedidoId}",
                    'delivery_fee' => $comisionRepartidor,
                    'client_id' => $clienteId,
                    'business_id' => $negocioId
            if (!$resultRepartidor['success']) {
                throw new Exception("Error al pagar al repartidor: " . $resultRepartidor['message']);
            // 4. Verificar si el repartidor puede seguir trabajando
            $canWork = $this->walletService->canDriverWork($repartidorId);
            if (!$canWork) {
                // Suspender temporalmente al repartidor
                $this->walletService->updateWalletStatus($repartidorId, 'suspended');
                // Aquí podrías enviar una notificación
            $this->conn->commit();
            return [
                'success' => true,
                'negocio_balance' => $resultGananciaNegocio['balance_after'],
                'repartidor_balance' => $resultRepartidor['balance_after'],
                'repartidor_can_work' => $canWork,
                'message' => 'Pedido procesado exitosamente'
}
            ];
        } catch (Exception $e) {
            $this->conn->rollback();
                'success' => false,
                'message' => $e->getMessage()
        }
     * CASO 2: Procesar reembolso por cancelación
    public function procesarReembolsoPedido($pedidoId, $clienteId, $negocioId, $repartidorId, $montoTotal) {
            $comisionPlataforma = $montoTotal * 0.10;
            $comisionRepartidor = $montoTotal * 0.15;
            // 1. Devolver comisión al negocio
            $this->walletService->addTransaction(
                amount: $comisionPlataforma,
                type: 'refund',
                refType: 'order_cancellation',
                description: "Reembolso comisión pedido #{$pedidoId}"
            // 2. Retirar ganancia del negocio
                amount: -$pagoNegocio,
                description: "Devolución por cancelación #{$pedidoId}"
            // 3. Retirar pago del repartidor
                amount: -$comisionRepartidor,
                'message' => 'Reembolso procesado exitosamente'
     * CASO 3: Procesar propina para repartidor
    public function procesarPropina($pedidoId, $repartidorId, $montoPropina) {
        $result = $this->walletService->addTransaction(
            userId: $repartidorId,
            amount: $montoPropina,
            type: 'tip',
            refId: $pedidoId,
            refType: 'order',
            description: "Propina recibida en pedido #{$pedidoId}",
            metadata: [
                'tip_amount' => $montoPropina
            ]
        );
        return $result;
     * CASO 4: Aplicar penalización a repartidor
    public function aplicarPenalizacion($repartidorId, $motivo, $montoPenalizacion, $pedidoId = null) {
            amount: -$montoPenalizacion,
            type: 'penalty',
            refType: 'penalty',
            description: "Penalización: {$motivo}",
                'reason' => $motivo,
                'penalty_amount' => $montoPenalizacion
        // Verificar si debe ser suspendido
        if ($result['success']) {
                $result['driver_suspended'] = true;
     * CASO 5: Bono promocional
    public function aplicarBono($userId, $montoBono, $descripcion, $campaignId = null) {
            userId: $userId,
            amount: $montoBono,
            type: 'bonus',
            refId: $campaignId,
            refType: 'promotion',
            description: $descripcion,
                'campaign_id' => $campaignId,
                'bonus_type' => 'promotional'
     * CASO 6: Retiro de fondos del repartidor
    public function procesarRetiro($repartidorId, $montoRetiro, $metodoPago, $cuentaDestino) {
            // Verificar balance disponible
            $balanceInfo = $this->walletService->getBalance($repartidorId);
            
            if (!$balanceInfo['success']) {
                throw new Exception('No se pudo obtener el balance');
            $balanceActual = (float)$balanceInfo['balance'];
            if ($balanceActual < $montoRetiro) {
                throw new Exception('Saldo insuficiente para retiro');
            // Procesar retiro
            $result = $this->walletService->addTransaction(
                amount: -$montoRetiro,
                type: 'withdrawal',
                refId: 'WD_' . time(),
}
                refType: 'withdrawal',
                description: "Retiro a {$metodoPago}",
                    'payment_method' => $metodoPago,
                    'account' => $cuentaDestino,
                    'withdrawal_amount' => $montoRetiro
            if ($result['success']) {
                // Aquí integrarías con MercadoPago o Stripe para transferir
                // Por ahora solo registramos la transacción
                $result['withdrawal_id'] = $result['transaction_id'];
                $result['status'] = 'pending'; // pending, completed, failed
            return $result;
     * CASO 7: Verificar estado del repartidor antes de asignar pedido
    public function verificarDisponibilidadRepartidor($repartidorId) {
            // Verificar que tenga wallet activa
                return [
                    'available' => false,
                    'reason' => 'No tiene wallet configurada'
                ];
            if ($balanceInfo['status'] !== 'active') {
                    'reason' => 'Wallet suspendida o bloqueada'
            // Verificar límite de crédito
                    'reason' => 'Balance insuficiente - debe regularizar pagos',
                    'current_balance' => $balanceInfo['balance']
                'available' => true,
                'current_balance' => $balanceInfo['balance']
                'available' => false,
                'reason' => 'Error al verificar disponibilidad',
                'error' => $e->getMessage()
     * CASO 8: Resumen financiero del repartidor
    public function obtenerResumenFinanciero($repartidorId, $dias = 30) {
            $historial = $this->walletService->getTransactionHistory($repartidorId, 1000);
            if (!$historial['success']) {
}
                throw new Exception('Error al obtener historial');
            $fechaLimite = date('Y-m-d H:i:s', strtotime("-{$dias} days"));
            $resumen = [
                'balance_actual' => $balanceInfo['balance'],
                'total_ingresos' => 0,
                'total_egresos' => 0,
                'total_propinas' => 0,
                'total_penalizaciones' => 0,
                'total_bonos' => 0,
                'pedidos_completados' => 0,
                'transacciones' => []
            foreach ($historial['transactions'] as $trans) {
                if ($trans['created_at'] < $fechaLimite) continue;
                $amount = (float)$trans['amount'];
                if ($amount > 0) {
                    $resumen['total_ingresos'] += $amount;
                } else {
                    $resumen['total_egresos'] += abs($amount);
                }
                switch ($trans['type']) {
                    case 'tip':
                        $resumen['total_propinas'] += $amount;
                        break;
                    case 'penalty':
                        $resumen['total_penalizaciones'] += abs($amount);
                    case 'bonus':
                        $resumen['total_bonos'] += $amount;
                    case 'deposit':
                        if ($trans['reference_type'] === 'order') {
                            $resumen['pedidos_completados']++;
                        }
            $resumen['ganancia_neta'] = $resumen['total_ingresos'] - $resumen['total_egresos'];
                'periodo_dias' => $dias,
                'resumen' => $resumen
}
// ========================================
// EJEMPLO DE USO
/*
// Inicializar
$database = new DatabaseMysqli();
$conn = $database->getConnection();
$integration = new WalletIntegrationExample($conn);
// Caso 1: Procesar pago de pedido
$result = $integration->procesarPagoPedido(
    pedidoId: 123,
    clienteId: 456,
    negocioId: 789,
    repartidorId: 321,
    montoTotal: 500.00
);
if ($result['success']) {
    echo "Pedido procesado exitosamente\n";
    echo "Balance negocio: $" . $result['negocio_balance'] . "\n";
    echo "Balance repartidor: $" . $result['repartidor_balance'] . "\n";
    echo "¿Repartidor puede trabajar? " . ($result['repartidor_can_work'] ? 'SÍ' : 'NO') . "\n";
// Caso 2: Verificar disponibilidad antes de asignar
$disponibilidad = $integration->verificarDisponibilidadRepartidor(321);
if ($disponibilidad['available']) {
    // Asignar pedido
}
    echo "Repartidor disponible con balance: $" . $disponibilidad['current_balance'];
} else {
    echo "Repartidor no disponible: " . $disponibilidad['reason'];
// Caso 3: Aplicar propina
$propina = $integration->procesarPropina(
    montoPropina: 50.00
// Caso 4: Obtener resumen financiero
$resumen = $integration->obtenerResumenFinanciero(321, 30);
if ($resumen['success']) {
    echo "Ganancia neta últimos 30 días: $" . $resumen['resumen']['ganancia_neta'];
*/
}
