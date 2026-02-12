<?php
/**
 * QuickBite - Wallet API
 * Endpoints para gestión de billetera digital
 *
 * @version 2.0.0
 * @date 2025-12-25
 */

// Headers
header('Content-Type: application/json');
// CORS restringido al dominio de la aplicación
$allowed_origin = getenv('APP_URL') ?: 'https://quickbite.com.mx';
header('Access-Control-Allow-Origin: ' . $allowed_origin);
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

// Manejo de preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Iniciar sesión
session_start();

// Incluir dependencias
require_once __DIR__ . '/../config/database.php';

// Función para validar sesión
function validateSession() {
    if (!isset($_SESSION['id_usuario'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'No autorizado. Debes iniciar sesión.'
        ]);
        exit;
    }
    return $_SESSION['id_usuario'];
}

// Función para validar permisos de admin
function isAdmin() {
    return isset($_SESSION['tipo_usuario']) && $_SESSION['tipo_usuario'] === 'admin';
}

// Función para validar permisos de negocio
function isNegocio() {
    return isset($_SESSION['tipo_usuario']) && $_SESSION['tipo_usuario'] === 'negocio';
}

// Función para validar permisos de repartidor
function isRepartidor() {
    return isset($_SESSION['tipo_usuario']) && $_SESSION['tipo_usuario'] === 'repartidor';
}

try {
    // Crear conexión a la base de datos
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        throw new Exception('Error de conexión a la base de datos');
    }

    // Obtener método y acción
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';

    switch ($action) {

        // ==================== GET BALANCE ====================
        case 'balance':
            if ($method !== 'GET') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }

            $userId = validateSession();

            // Obtener saldo del wallet del usuario
            $stmt = $db->prepare("
                SELECT
                    id_wallet,
                    saldo_disponible,
                    saldo_pendiente,
                    estado,
                    tipo_wallet
                FROM wallet
                WHERE id_usuario = ?
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($wallet) {
                echo json_encode([
                    'success' => true,
                    'wallet' => [
                        'id_wallet' => $wallet['id_wallet'],
                        'saldo_disponible' => (float)$wallet['saldo_disponible'],
                        'saldo_pendiente' => (float)$wallet['saldo_pendiente'],
                        'saldo_total' => (float)$wallet['saldo_disponible'] + (float)$wallet['saldo_pendiente'],
                        'estado' => $wallet['estado'],
                        'tipo' => $wallet['tipo_wallet']
                    ]
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Wallet no encontrada',
                    'wallet' => null
                ]);
            }
            break;

        // ==================== GET HISTORY ====================
        case 'history':
            if ($method !== 'GET') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }

            $userId = validateSession();
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

            // Validar límites
            $limit = min(max($limit, 1), 100); // Entre 1 y 100
            $offset = max($offset, 0);

            // Obtener ID del wallet
            $stmt = $db->prepare("SELECT id_wallet FROM wallet WHERE id_usuario = ? LIMIT 1");
            $stmt->execute([$userId]);
            $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$wallet) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Wallet no encontrada',
                    'transactions' => []
                ]);
                break;
            }

            // Obtener historial de transacciones
            $stmt = $db->prepare("
                SELECT
                    id_transaccion,
                    tipo,
                    monto,
                    saldo_anterior,
                    saldo_nuevo,
                    descripcion,
                    id_referencia,
                    tipo_referencia,
                    estado,
                    fecha_creacion
                FROM wallet_transacciones
                WHERE id_wallet = ?
                ORDER BY fecha_creacion DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$wallet['id_wallet'], $limit, $offset]);
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Formatear transacciones
            $formattedTransactions = [];
            foreach ($transactions as $t) {
                $formattedTransactions[] = [
                    'id' => $t['id_transaccion'],
                    'tipo' => $t['tipo'],
                    'monto' => (float)$t['monto'],
                    'saldo_anterior' => (float)$t['saldo_anterior'],
                    'saldo_nuevo' => (float)$t['saldo_nuevo'],
                    'descripcion' => $t['descripcion'],
                    'referencia' => [
                        'id' => $t['id_referencia'],
                        'tipo' => $t['tipo_referencia']
                    ],
                    'estado' => $t['estado'],
                    'fecha' => $t['fecha_creacion']
                ];
            }

            echo json_encode([
                'success' => true,
                'transactions' => $formattedTransactions,
                'pagination' => [
                    'limit' => $limit,
                    'offset' => $offset,
                    'count' => count($formattedTransactions)
                ]
            ]);
            break;

        // ==================== ADD TRANSACTION ====================
        case 'transaction':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }

            $userId = validateSession();

            // Solo admin puede agregar transacciones manualmente
            if (!isAdmin()) {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'message' => 'No tienes permisos para realizar esta acción'
                ]);
                exit;
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
                exit;
            }

            $targetUserId = $data['user_id'] ?? null;
            $amount = $data['amount'] ?? null;
            $type = $data['type'] ?? null;
            $description = $data['description'] ?? 'Transacción manual';

            if (!$targetUserId || !$amount || !$type) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Faltan parámetros requeridos: user_id, amount, type'
                ]);
                exit;
            }

            // Validar tipo
            $validTypes = ['credito', 'debito', 'deposito', 'retiro', 'comision', 'reembolso'];
            if (!in_array($type, $validTypes)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Tipo de transacción inválido',
                    'valid_types' => $validTypes
                ]);
                exit;
            }

            // Obtener wallet del usuario objetivo
            $stmt = $db->prepare("SELECT id_wallet, saldo_disponible FROM wallet WHERE id_usuario = ?");
            $stmt->execute([$targetUserId]);
            $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$wallet) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Wallet del usuario no encontrada'
                ]);
                exit;
            }

            // Calcular nuevo saldo
            $saldoAnterior = (float)$wallet['saldo_disponible'];
            $montoFloat = (float)$amount;

            // Si es débito o retiro, el monto debe ser negativo
            if (in_array($type, ['debito', 'retiro', 'comision'])) {
                $montoFloat = -abs($montoFloat);
            } else {
                $montoFloat = abs($montoFloat);
            }

            $saldoNuevo = $saldoAnterior + $montoFloat;

            // Validar que no quede en negativo (opcional, depende de tu lógica)
            if ($saldoNuevo < 0 && !in_array($type, ['credito'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Saldo insuficiente',
                    'saldo_actual' => $saldoAnterior,
                    'monto_solicitado' => abs($montoFloat)
                ]);
                exit;
            }

            // Iniciar transacción
            $db->beginTransaction();

            try {
                // Actualizar saldo
                $stmt = $db->prepare("UPDATE wallet SET saldo_disponible = ? WHERE id_wallet = ?");
                $stmt->execute([$saldoNuevo, $wallet['id_wallet']]);

                // Registrar transacción
                $stmt = $db->prepare("
                    INSERT INTO wallet_transacciones
                    (id_wallet, tipo, monto, saldo_anterior, saldo_nuevo, descripcion, estado, fecha_creacion)
                    VALUES (?, ?, ?, ?, ?, ?, 'completado', NOW())
                ");
                $stmt->execute([
                    $wallet['id_wallet'],
                    $type,
                    $montoFloat,
                    $saldoAnterior,
                    $saldoNuevo,
                    $description
                ]);

                $transactionId = $db->lastInsertId();

                $db->commit();

                echo json_encode([
                    'success' => true,
                    'message' => 'Transacción registrada correctamente',
                    'transaction' => [
                        'id' => $transactionId,
                        'tipo' => $type,
                        'monto' => $montoFloat,
                        'saldo_anterior' => $saldoAnterior,
                        'saldo_nuevo' => $saldoNuevo
                    ]
                ]);

            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;

        // ==================== CREATE WALLET ====================
        case 'create':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }

            $userId = validateSession();

            $data = json_decode(file_get_contents('php://input'), true);
            $type = $data['type'] ?? 'usuario';

            // Validar tipo de wallet
            $validTypes = ['usuario', 'negocio', 'repartidor'];
            if (!in_array($type, $validTypes)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Tipo de wallet inválido',
                    'valid_types' => $validTypes
                ]);
                exit;
            }

            // Verificar si ya existe wallet
            $stmt = $db->prepare("SELECT id_wallet FROM wallet WHERE id_usuario = ?");
            $stmt->execute([$userId]);
            $existingWallet = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existingWallet) {
                echo json_encode([
                    'success' => false,
                    'message' => 'El usuario ya tiene un wallet creado',
                    'wallet_id' => $existingWallet['id_wallet']
                ]);
                exit;
            }

            // Crear wallet
            $stmt = $db->prepare("
                INSERT INTO wallet
                (id_usuario, tipo_wallet, saldo_disponible, saldo_pendiente, estado, fecha_creacion)
                VALUES (?, ?, 0.00, 0.00, 'activo', NOW())
            ");
            $stmt->execute([$userId, $type]);
            $walletId = $db->lastInsertId();

            echo json_encode([
                'success' => true,
                'message' => 'Wallet creado exitosamente',
                'wallet' => [
                    'id_wallet' => $walletId,
                    'tipo' => $type,
                    'saldo_disponible' => 0.00,
                    'saldo_pendiente' => 0.00,
                    'estado' => 'activo'
                ]
            ]);
            break;

        // ==================== CAN DRIVER WORK ====================
        case 'can_work':
            if ($method !== 'GET') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }

            $driverId = validateSession();

            // Verificar que sea repartidor
            if (!isRepartidor()) {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'message' => 'Solo repartidores pueden usar esta función'
                ]);
                exit;
            }

            $limit = isset($_GET['limit']) ? (float)$_GET['limit'] : -200.00;

            // Obtener saldo del repartidor
            $stmt = $db->prepare("
                SELECT saldo_disponible, estado
                FROM wallet
                WHERE id_usuario = ? AND tipo_wallet = 'repartidor'
            ");
            $stmt->execute([$driverId]);
            $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$wallet) {
                echo json_encode([
                    'success' => false,
                    'can_work' => false,
                    'message' => 'Wallet no encontrada'
                ]);
                exit;
            }

            $saldo = (float)$wallet['saldo_disponible'];
            $estado = $wallet['estado'];

            // Puede trabajar si: estado activo Y saldo mayor al límite
            $canWork = ($estado === 'activo' && $saldo >= $limit);

            echo json_encode([
                'success' => true,
                'can_work' => $canWork,
                'wallet_status' => [
                    'saldo' => $saldo,
                    'limite' => $limit,
                    'estado' => $estado,
                    'diferencia' => $saldo - $limit
                ],
                'message' => $canWork
                    ? 'El repartidor puede trabajar'
                    : 'El repartidor está bloqueado (saldo por debajo del límite o cuenta inactiva)'
            ]);
            break;

        // ==================== UPDATE STATUS (ADMIN) ====================
        case 'update_status':
            if ($method !== 'PUT') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Método no permitido']);
                exit;
            }

            validateSession();

            if (!isAdmin()) {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'message' => 'Solo administradores pueden actualizar el estado de wallets'
                ]);
                exit;
            }

            $data = json_decode(file_get_contents('php://input'), true);

            $targetUserId = $data['user_id'] ?? null;
            $status = $data['status'] ?? null;

            if (!$targetUserId || !$status) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Faltan parámetros: user_id, status'
                ]);
                exit;
            }

            // Validar estado
            $validStatuses = ['activo', 'suspendido', 'bloqueado', 'inactivo'];
            if (!in_array($status, $validStatuses)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Estado inválido',
                    'valid_statuses' => $validStatuses
                ]);
                exit;
            }

            // Actualizar estado
            $stmt = $db->prepare("UPDATE wallet SET estado = ? WHERE id_usuario = ?");
            $result = $stmt->execute([$status, $targetUserId]);

            if ($stmt->rowCount() > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Estado actualizado correctamente',
                    'new_status' => $status
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'No se encontró wallet para el usuario especificado'
                ]);
            }
            break;

        // ==================== DEFAULT ====================
        default:
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Acción no válida',
                'available_actions' => [
                    'balance' => 'GET - Obtener balance del wallet',
                    'history' => 'GET - Historial de transacciones',
                    'transaction' => 'POST - Agregar transacción (solo admin)',
                    'create' => 'POST - Crear wallet',
                    'can_work' => 'GET - Verificar si repartidor puede trabajar',
                    'update_status' => 'PUT - Actualizar estado de wallet (solo admin)'
                ]
            ]);
            break;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor',
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
