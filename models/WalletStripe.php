<?php
/**
 * Clase WalletStripe - Integración real con Stripe para pagos
 * Maneja wallets de negocios y repartidores
 */

require_once __DIR__ . '/../vendor/autoload.php';

class WalletStripe {
    private $conn;
    private $stripe;
    private $stripe_key;
    
    const TIPO_NEGOCIO = 'business';
    const TIPO_REPARTIDOR = 'courier';
    
    const ESTADO_ACTIVO = 'activo';
    const ESTADO_BLOQUEADO = 'bloqueado';
    const ESTADO_SUSPENDIDO = 'suspendido';
    
    const COMISION_PLATAFORMA = 0.10; // 10%
    const MONTO_MINIMO_RETIRO = 100;
    
    public function __construct($db, $stripe_secret_key) {
        $this->conn = $db;
        $this->stripe_key = $stripe_secret_key;
        
        try {
            \Stripe\Stripe::setApiKey($stripe_secret_key);
            $this->stripe = \Stripe\Stripe::class;
        } catch (Exception $e) {
            error_log("Error iniciando Stripe: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Crear wallet para negocio o repartidor
     */
    public function crearWallet($id_usuario, $tipo_usuario, $nombre, $email) {
        try {
            $this->conn->beginTransaction();
            
            // Crear cuenta conectada en Stripe
            $cuenta_stripe = \Stripe\Account::create([
                'type' => 'express',
                'country' => 'MX',
                'email' => $email,
                'business_type' => $tipo_usuario === self::TIPO_NEGOCIO ? 'individual' : 'individual',
                'individual' => [
                    'first_name' => $nombre,
                    'email' => $email,
                ],
                'metadata' => [
                    'id_usuario' => $id_usuario,
                    'tipo' => $tipo_usuario
                ]
            ]);
            
            // Guardar en base de datos
            $query = "INSERT INTO wallets 
                     (id_usuario, tipo_usuario, stripe_account_id, saldo_disponible, 
                      saldo_pendiente, saldo_total, estado, fecha_creacion)
                     VALUES 
                     (:id_usuario, :tipo, :stripe_id, 0, 0, 0, :estado, NOW())";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id_usuario', $id_usuario);
            $stmt->bindParam(':tipo', $tipo_usuario);
            $stmt->bindParam(':stripe_id', $cuenta_stripe->id);
            $stmt->bindParam(':estado', self::ESTADO_ACTIVO);
            
            if (!$stmt->execute()) {
                throw new Exception("Error al crear wallet en BD");
            }
            
            $id_wallet = $this->conn->lastInsertId();
            
            $this->conn->commit();
            
            return [
                'id_wallet' => $id_wallet,
                'stripe_account_id' => $cuenta_stripe->id,
                'onboarding_url' => $this->generarOnboardingUrl($cuenta_stripe->id)
            ];
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Error creando wallet: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Generar URL de onboarding para completar perfil en Stripe
     */
    public function generarOnboardingUrl($stripe_account_id) {
        try {
            $link = \Stripe\AccountLink::create([
                'account' => $stripe_account_id,
                'type' => 'account_onboarding',
                'refresh_url' => $_ENV['APP_URL'] . '/wallet/onboarding_cancelled.php?account_id={ACCOUNT_ID}',
                'return_url' => $_ENV['APP_URL'] . '/wallet/onboarding_complete.php?account_id={ACCOUNT_ID}',
            ]);
            
            return $link->url;
            
        } catch (Exception $e) {
            error_log("Error generando onboarding URL: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Obtener wallet por usuario
     */
    public function obtenerWallet($id_usuario, $tipo_usuario) {
        $query = "SELECT * FROM wallets 
                 WHERE id_usuario = :id_usuario AND tipo_usuario = :tipo";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id_usuario', $id_usuario);
        $stmt->bindParam(':tipo', $tipo_usuario);
        
        if ($stmt->execute()) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        return null;
    }
    
    /**
     * Procesar ingreso a wallet (cuando se completa un pedido)
     */
    public function procesarIngreso($id_negocio, $id_pedido, $monto_total) {
        try {
            $this->conn->beginTransaction();
            
            // Obtener wallet del negocio
            $wallet_negocio = $this->obtenerWallet($id_negocio, self::TIPO_NEGOCIO);
            if (!$wallet_negocio) {
                throw new Exception("Wallet del negocio no existe");
            }
            
            // Calcular comisión y monto neto
            $comision = $monto_total * self::COMISION_PLATAFORMA;
            $monto_neto = $monto_total - $comision;
            
            // Actualizar saldo en base de datos
            $query_update = "UPDATE wallets 
                           SET saldo_pendiente = saldo_pendiente + :monto
                           WHERE id_wallet = :id_wallet";
            
            $stmt = $this->conn->prepare($query_update);
            $stmt->bindParam(':monto', $monto_neto);
            $stmt->bindParam(':id_wallet', $wallet_negocio['id_wallet']);
            
            if (!$stmt->execute()) {
                throw new Exception("Error al actualizar saldo pendiente");
            }
            
            // Registrar transacción
            $this->registrarTransaccion(
                $wallet_negocio['id_wallet'],
                'ingreso_pedido',
                $monto_neto,
                "Ingreso por pedido #" . $id_pedido,
                'pendiente',
                $id_pedido,
                $comision
            );
            
            $this->conn->commit();
            
            return [
                'exito' => true,
                'monto_neto' => $monto_neto,
                'comision' => $comision,
                'saldo_nuevo' => $wallet_negocio['saldo_pendiente'] + $monto_neto
            ];
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Error procesando ingreso: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Procesar ingreso para repartidor (entrega completada)
     */
    public function procesarIngresoRepartidor($id_repartidor, $id_pedido, $monto_entrega) {
        try {
            $this->conn->beginTransaction();
            
            // Obtener wallet del repartidor
            $wallet_repartidor = $this->obtenerWallet($id_repartidor, self::TIPO_REPARTIDOR);
            if (!$wallet_repartidor) {
                throw new Exception("Wallet del repartidor no existe");
            }
            
            // Actualizar saldo (sin comisión para repartidores)
            $query_update = "UPDATE wallets 
                           SET saldo_pendiente = saldo_pendiente + :monto
                           WHERE id_wallet = :id_wallet";
            
            $stmt = $this->conn->prepare($query_update);
            $stmt->bindParam(':monto', $monto_entrega);
            $stmt->bindParam(':id_wallet', $wallet_repartidor['id_wallet']);
            
            if (!$stmt->execute()) {
                throw new Exception("Error al actualizar saldo del repartidor");
            }
            
            // Registrar transacción
            $this->registrarTransaccion(
                $wallet_repartidor['id_wallet'],
                'ingreso_entrega',
                $monto_entrega,
                "Pago por entrega de pedido #" . $id_pedido,
                'pendiente',
                $id_pedido
            );
            
            $this->conn->commit();
            
            return [
                'exito' => true,
                'monto' => $monto_entrega,
                'saldo_nuevo' => $wallet_repartidor['saldo_pendiente'] + $monto_entrega
            ];
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Error procesando ingreso repartidor: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Solicitar retiro - Crea transfer en Stripe
     */
    public function solicitarRetiro($id_wallet, $monto) {
        try {
            // Validar monto
            if ($monto < self::MONTO_MINIMO_RETIRO) {
                throw new Exception("Monto mínimo para retiro: $" . self::MONTO_MINIMO_RETIRO);
            }
            
            // Obtener wallet
            $query = "SELECT * FROM wallets WHERE id_wallet = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id_wallet);
            $stmt->execute();
            $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$wallet) {
                throw new Exception("Wallet no encontrada");
            }
            
            if ($monto > $wallet['saldo_disponible']) {
                throw new Exception("Saldo insuficiente");
            }
            
            if ($wallet['estado'] !== self::ESTADO_ACTIVO) {
                throw new Exception("Wallet no está activa");
            }
            
            $this->conn->beginTransaction();
            
            // Crear transfer en Stripe
            $transfer = \Stripe\Transfer::create([
                'amount' => intval($monto * 100), // Stripe usa centavos
                'currency' => 'mxn',
                'destination' => $wallet['stripe_account_id'],
                'description' => 'Retiro de saldo QuickBite',
                'metadata' => [
                    'id_wallet' => $id_wallet,
                    'tipo' => 'retiro_solicitado'
                ]
            ]);
            
            // Registrar retiro en base de datos
            $query_retiro = "INSERT INTO wallet_retiros 
                           (id_wallet, monto, stripe_transfer_id, estado, fecha_solicitud)
                           VALUES 
                           (:id_wallet, :monto, :stripe_id, :estado, NOW())";
            
            $stmt = $this->conn->prepare($query_retiro);
            $stmt->bindParam(':id_wallet', $id_wallet);
            $stmt->bindParam(':monto', $monto);
            $stmt->bindParam(':stripe_id', $transfer->id);
            $stmt->bindParam(':estado', 'procesando');
            
            if (!$stmt->execute()) {
                throw new Exception("Error al registrar retiro");
            }
            
            $id_retiro = $this->conn->lastInsertId();
            
            // Actualizar saldo de wallet
            $query_update = "UPDATE wallets 
                           SET saldo_disponible = saldo_disponible - :monto,
                               saldo_pendiente = saldo_pendiente + :monto
                           WHERE id_wallet = :id_wallet";
            
            $stmt = $this->conn->prepare($query_update);
            $stmt->bindParam(':monto', $monto);
            $stmt->bindParam(':id_wallet', $id_wallet);
            
            if (!$stmt->execute()) {
                throw new Exception("Error al actualizar saldo");
            }
            
            // Registrar transacción
            $this->registrarTransaccion(
                $id_wallet,
                'retiro',
                -$monto,
                "Retiro procesado - Transfer ID: " . $transfer->id,
                'pendiente',
                null,
                0,
                $transfer->id
            );
            
            $this->conn->commit();
            
            return [
                'exito' => true,
                'id_retiro' => $id_retiro,
                'stripe_transfer_id' => $transfer->id,
                'estado' => $transfer->status,
                'monto' => $monto
            ];
            
        } catch (\Stripe\Exception\ApiErrorException $e) {
            $this->conn->rollBack();
            error_log("Error Stripe: " . $e->getMessage());
            throw new Exception("Error procesando retiro: " . $e->getMessage());
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Error en solicitarRetiro: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Webhook para procesar eventos de Stripe
     */
    public function procesarWebhook($event) {
        try {
            switch ($event['type']) {
                case 'transfer.created':
                    $this->manejarTransferCreated($event['data']['object']);
                    break;
                    
                case 'transfer.paid':
                    $this->manejarTransferPaid($event['data']['object']);
                    break;
                    
                case 'transfer.failed':
                    $this->manejarTransferFailed($event['data']['object']);
                    break;
                    
                case 'transfer.reversed':
                    $this->manejarTransferReversed($event['data']['object']);
                    break;
            }
            
        } catch (Exception $e) {
            error_log("Error procesando webhook: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Transfer creado en Stripe
     */
    private function manejarTransferCreated($transfer) {
        // El transfer ya se registró cuando se solicitó
        // Aquí podemos hacer logging adicional si es necesario
        error_log("Transfer creado en Stripe: " . $transfer->id);
    }
    
    /**
     * Transfer pagado exitosamente
     */
    private function manejarTransferPaid($transfer) {
        try {
            $this->conn->beginTransaction();
            
            // Actualizar estado del retiro
            $query = "UPDATE wallet_retiros 
                     SET estado = :estado, fecha_completacion = NOW()
                     WHERE stripe_transfer_id = :stripe_id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':estado', 'completado');
            $stmt->bindParam(':stripe_id', $transfer->id);
            
            if (!$stmt->execute()) {
                throw new Exception("Error actualizando retiro");
            }
            
            // Obtener wallet info para actualizar saldo
            $query_wallet = "SELECT w.*, r.id_wallet, r.monto 
                           FROM wallet_retiros r
                           JOIN wallets w ON r.id_wallet = w.id_wallet
                           WHERE r.stripe_transfer_id = :stripe_id";
            
            $stmt_wallet = $this->conn->prepare($query_wallet);
            $stmt_wallet->bindParam(':stripe_id', $transfer->id);
            $stmt_wallet->execute();
            $retiro = $stmt_wallet->fetch(PDO::FETCH_ASSOC);
            
            if ($retiro) {
                // Mover del saldo pendiente al disponible (solo para auditoría)
                $query_update = "UPDATE wallets 
                               SET saldo_pendiente = saldo_pendiente - :monto
                               WHERE id_wallet = :id_wallet";
                
                $stmt_update = $this->conn->prepare($query_update);
                $stmt_update->bindParam(':monto', $retiro['monto']);
                $stmt_update->bindParam(':id_wallet', $retiro['id_wallet']);
                
                if (!$stmt_update->execute()) {
                    throw new Exception("Error actualizando saldo pendiente");
                }
                
                // Registrar en auditoría
                $this->registrarAuditoria(
                    $retiro['id_wallet'],
                    'retiro_completado',
                    "Transfer " . $transfer->id . " completado exitosamente"
                );
            }
            
            $this->conn->commit();
            
            error_log("Transfer pagado exitosamente: " . $transfer->id);
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Error en manejarTransferPaid: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Transfer falló
     */
    private function manejarTransferFailed($transfer) {
        try {
            $this->conn->beginTransaction();
            
            // Actualizar retiro como fallido
            $query = "UPDATE wallet_retiros 
                     SET estado = :estado, razon_fallo = :razon
                     WHERE stripe_transfer_id = :stripe_id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':estado', 'fallido');
            $stmt->bindParam(':razon', $transfer->failure_message ?? 'Fallo desconocido');
            $stmt->bindParam(':stripe_id', $transfer->id);
            
            if (!$stmt->execute()) {
                throw new Exception("Error actualizando retiro fallido");
            }
            
            // Obtener wallet y devolver dinero
            $query_wallet = "SELECT w.*, r.monto 
                           FROM wallet_retiros r
                           JOIN wallets w ON r.id_wallet = w.id_wallet
                           WHERE r.stripe_transfer_id = :stripe_id";
            
            $stmt_wallet = $this->conn->prepare($query_wallet);
            $stmt_wallet->bindParam(':stripe_id', $transfer->id);
            $stmt_wallet->execute();
            $retiro = $stmt_wallet->fetch(PDO::FETCH_ASSOC);
            
            if ($retiro) {
                // Devolver dinero del saldo pendiente al disponible
                $query_update = "UPDATE wallets 
                               SET saldo_disponible = saldo_disponible + :monto,
                                   saldo_pendiente = saldo_pendiente - :monto
                               WHERE id_wallet = :id_wallet";
                
                $stmt_update = $this->conn->prepare($query_update);
                $stmt_update->bindParam(':monto', $retiro['monto']);
                $stmt_update->bindParam(':id_wallet', $retiro['id_wallet']);
                
                if (!$stmt_update->execute()) {
                    throw new Exception("Error revirtiendo saldo");
                }
                
                // Registrar transacción de reversión
                $this->registrarTransaccion(
                    $retiro['id_wallet'],
                    'retiro_fallido',
                    $retiro['monto'],
                    "Transfer " . $transfer->id . " falló. Dinero revertido.",
                    'completada'
                );
            }
            
            $this->conn->commit();
            
            error_log("Transfer falló: " . $transfer->id . " - " . ($transfer->failure_message ?? 'Sin mensaje'));
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Error en manejarTransferFailed: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Transfer invertido/reversado
     */
    private function manejarTransferReversed($transfer) {
        try {
            $this->conn->beginTransaction();
            
            // Buscar el retiro relacionado
            $query = "SELECT * FROM wallet_retiros 
                     WHERE stripe_transfer_id = :stripe_id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':stripe_id', $transfer->id);
            $stmt->execute();
            $retiro = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($retiro) {
                // Actualizar estado
                $query_update = "UPDATE wallet_retiros 
                               SET estado = 'reversado'
                               WHERE id_retiro = :id";
                
                $stmt_update = $this->conn->prepare($query_update);
                $stmt_update->bindParam(':id', $retiro['id_retiro']);
                
                if (!$stmt_update->execute()) {
                    throw new Exception("Error actualizando retiro reversado");
                }
                
                // Devolver dinero
                $query_wallet = "UPDATE wallets 
                               SET saldo_disponible = saldo_disponible + :monto
                               WHERE id_wallet = :id_wallet";
                
                $stmt_wallet = $this->conn->prepare($query_wallet);
                $stmt_wallet->bindParam(':monto', $retiro['monto']);
                $stmt_wallet->bindParam(':id_wallet', $retiro['id_wallet']);
                
                if (!$stmt_wallet->execute()) {
                    throw new Exception("Error restituyendo saldo");
                }
            }
            
            $this->conn->commit();
            
            error_log("Transfer reversado: " . $transfer->id);
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Error en manejarTransferReversed: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Registrar transacción
     */
    private function registrarTransaccion($id_wallet, $tipo, $monto, $descripcion, 
                                         $estado = 'completada', $id_pedido = null, 
                                         $comision = 0, $referencia_stripe = null) {
        $query = "INSERT INTO wallet_transacciones 
                 (id_wallet, tipo, monto, descripcion, estado, id_pedido, 
                  comision, referencia_stripe, fecha)
                 VALUES 
                 (:id_wallet, :tipo, :monto, :descripcion, :estado, :id_pedido,
                  :comision, :referencia_stripe, NOW())";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id_wallet', $id_wallet);
        $stmt->bindParam(':tipo', $tipo);
        $stmt->bindParam(':monto', $monto);
        $stmt->bindParam(':descripcion', $descripcion);
        $stmt->bindParam(':estado', $estado);
        $stmt->bindParam(':id_pedido', $id_pedido);
        $stmt->bindParam(':comision', $comision);
        $stmt->bindParam(':referencia_stripe', $referencia_stripe);
        
        return $stmt->execute();
    }
    
    /**
     * Registrar auditoría
     */
    private function registrarAuditoria($id_wallet, $accion, $descripcion) {
        $query = "INSERT INTO wallet_auditoria 
                 (id_wallet, accion, descripcion, fecha)
                 VALUES 
                 (:id_wallet, :accion, :descripcion, NOW())";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id_wallet', $id_wallet);
        $stmt->bindParam(':accion', $accion);
        $stmt->bindParam(':descripcion', $descripcion);
        
        return $stmt->execute();
    }
    
    /**
     * Obtener transacciones
     */
    public function obtenerTransacciones($id_wallet, $limite = 50, $offset = 0) {
        $query = "SELECT * FROM wallet_transacciones 
                 WHERE id_wallet = :id_wallet
                 ORDER BY fecha DESC 
                 LIMIT :limite OFFSET :offset";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id_wallet', $id_wallet);
        $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        return [];
    }
    
    /**
     * Obtener resumen de wallet
     */
    public function obtenerResumen($id_wallet) {
        $query = "SELECT * FROM wallets WHERE id_wallet = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id_wallet);
        
        if ($stmt->execute()) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        return null;
    }
    
    /**
     * Verificar si Stripe está onboarded
     */
    public function verificarOnboarding($stripe_account_id) {
        try {
            $account = \Stripe\Account::retrieve($stripe_account_id);
            return $account->charges_enabled;
        } catch (Exception $e) {
            error_log("Error verificando onboarding: " . $e->getMessage());
            return false;
        }
    }
}
?>