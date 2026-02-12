<?php
/**
 * QuickBite - Confirmación de Membresía PRO para Negocios
 */

session_start();
require_once 'config/database.php';
require_once 'config/env.php';

// Verificar autenticación
if (!isset($_SESSION['loggedin']) || $_SESSION['tipo_usuario'] !== 'negocio') {
    header("Location: login.php");
    exit;
}

$database = new Database();
$db = $database->getConnection();

$id_usuario = $_SESSION['id_usuario'];
$plan = $_GET['plan'] ?? 'pro_mensual';
$payment_id = $_GET['payment_id'] ?? $_GET['collection_id'] ?? null;

// Obtener negocio
$stmt = $db->prepare("SELECT id_negocio, nombre FROM negocios WHERE id_propietario = ?");
$stmt->execute([$id_usuario]);
$negocio = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$negocio) {
    header("Location: admin/negocio_configuracion.php");
    exit;
}

$id_negocio = $negocio['id_negocio'];
$activacion_exitosa = false;

// Verificar pago si hay ID
if ($payment_id) {
    $mp_access_token = env('MP_ACCESS_TOKEN');

    if ($mp_access_token) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://api.mercadopago.com/v1/payments/' . $payment_id,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $mp_access_token],
            CURLOPT_TIMEOUT => 10
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200) {
            $payment = json_decode($response, true);
            if ($payment['status'] === 'approved') {
                // Activar membresía PRO
                $duracion = ($plan === 'pro_anual') ? 12 : 1;

                try {
                    $db->beginTransaction();

                    // Insertar membresía
                    $stmt = $db->prepare("
                        INSERT INTO membresias_negocios
                        (id_negocio, id_plan, fecha_inicio, fecha_fin, estado, metodo_pago, referencia_pago, monto_pagado)
                        VALUES (?, 2, CURDATE(), DATE_ADD(CURDATE(), INTERVAL ? MONTH), 'activa', 'mercadopago', ?, ?)
                    ");
                    $monto = ($plan === 'pro_anual') ? 799 * 12 : 499;
                    $stmt->execute([$id_negocio, $duracion, $payment_id, $monto]);

                    // Actualizar negocio
                    $stmt = $db->prepare("
                        UPDATE negocios SET
                            es_premium = 1,
                            comision_actual = 5.00,
                            fecha_inicio_premium = CURDATE(),
                            fecha_fin_premium = DATE_ADD(CURDATE(), INTERVAL ? MONTH)
                        WHERE id_negocio = ?
                    ");
                    $stmt->execute([$duracion, $id_negocio]);

                    $db->commit();
                    $activacion_exitosa = true;
                } catch (Exception $e) {
                    $db->rollBack();
                    error_log("Error activando membresía PRO: " . $e->getMessage());
                }
            }
        }
    }
}

// Obtener datos actualizados
$stmt = $db->prepare("SELECT es_premium, fecha_fin_premium FROM negocios WHERE id_negocio = ?");
$stmt->execute([$id_negocio]);
$estado = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Membresía PRO Activada - QuickBite</title>
    <link rel="icon" type="image/x-icon" href="assets/img/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=DM+Sans:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --gold: #FFD700;
            --success: #22C55E;
            --dark: #0F172A;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .success-card {
            background: white;
            border-radius: 24px;
            padding: 3rem;
            text-align: center;
            max-width: 500px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
        }
        .crown-icon {
            font-size: 5rem;
            color: var(--gold);
            margin-bottom: 1.5rem;
            animation: bounce 1s ease infinite;
        }
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        h1 {
            font-family: 'DM Sans', sans-serif;
            font-size: 2rem;
            margin-bottom: 1rem;
            color: var(--dark);
        }
        .success-badge {
            background: linear-gradient(135deg, var(--gold) 0%, #FFA500 100%);
            color: var(--dark);
            padding: 0.5rem 1.5rem;
            border-radius: 50px;
            font-weight: 700;
            display: inline-block;
            margin-bottom: 1.5rem;
        }
        .benefits-list {
            text-align: left;
            background: #F8FAFC;
            border-radius: 16px;
            padding: 1.5rem;
            margin: 1.5rem 0;
        }
        .benefit-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 0;
            color: var(--dark);
        }
        .benefit-item i {
            color: var(--success);
        }
        .btn-dashboard {
            background: linear-gradient(135deg, var(--gold) 0%, #FFA500 100%);
            color: var(--dark);
            border: none;
            padding: 1rem 2rem;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1.1rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s;
        }
        .btn-dashboard:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(255,215,0,0.5);
            color: var(--dark);
        }
        .validity {
            color: #64748B;
            font-size: 0.875rem;
            margin-top: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="success-card">
        <div class="crown-icon">👑</div>
        <h1>¡Bienvenido a PRO!</h1>
        <div class="success-badge">
            <i class="fas fa-check-circle"></i> Membresía Activada
        </div>
        <p>Tu negocio <strong><?php echo htmlspecialchars($negocio['nombre']); ?></strong> ahora es Socio QuickBite PRO</p>

        <div class="benefits-list">
            <div class="benefit-item">
                <i class="fas fa-check-circle"></i>
                <span>Comisión reducida al 5%</span>
            </div>
            <div class="benefit-item">
                <i class="fas fa-check-circle"></i>
                <span>Bot de WhatsApp para pedidos</span>
            </div>
            <div class="benefit-item">
                <i class="fas fa-check-circle"></i>
                <span>Menú Mágico con IA</span>
            </div>
            <div class="benefit-item">
                <i class="fas fa-check-circle"></i>
                <span>Prioridad en días de lluvia</span>
            </div>
            <div class="benefit-item">
                <i class="fas fa-check-circle"></i>
                <span>Distintivo de Verificado</span>
            </div>
            <div class="benefit-item">
                <i class="fas fa-check-circle"></i>
                <span>Kit de Marketing físico (en camino)</span>
            </div>
        </div>

        <a href="admin/negocio_configuracion.php" class="btn-dashboard">
            <i class="fas fa-rocket"></i> Ir a mi Panel PRO
        </a>

        <?php if ($estado['fecha_fin_premium']): ?>
        <div class="validity">
            <i class="fas fa-calendar"></i>
            Válido hasta: <?php echo date('d/m/Y', strtotime($estado['fecha_fin_premium'])); ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
