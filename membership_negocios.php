<?php
/**
 * QuickBite - Membresías para Negocios
 * Planes: Socio Aliado (Gratis) y Socio QuickBite PRO ($499-$799/mes)
 */

session_start();
error_reporting(0);
ini_set('display_errors', 0);

// Verificar autenticación
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php?redirect=membership_negocios.php");
    exit;
}

if (!isset($_SESSION['tipo_usuario']) || $_SESSION['tipo_usuario'] !== 'negocio') {
    header("Location: index.php");
    exit;
}

require_once 'config/database.php';
require_once 'config/env.php';
require_once 'models/Negocio.php';

$database = new Database();
$db = $database->getConnection();

// Obtener información del negocio
$id_usuario = $_SESSION['id_usuario'];
$negocio = new Negocio($db);
$negocios = $negocio->obtenerPorIdPropietario($id_usuario);

if (empty($negocios)) {
    header("Location: admin/negocio_configuracion.php");
    exit;
}

$negocio_info = $negocios[0];
$id_negocio = $negocio_info['id_negocio'];

// Verificar plan actual
$stmt = $db->prepare("SELECT es_premium, comision_actual, fecha_fin_premium FROM negocios WHERE id_negocio = ?");
$stmt->execute([$id_negocio]);
$plan_actual = $stmt->fetch(PDO::FETCH_ASSOC);

$es_pro = $plan_actual['es_premium'] == 1;
$fecha_vencimiento = $plan_actual['fecha_fin_premium'];

// Estadísticas del mes
$stmt = $db->prepare("
    SELECT
        COUNT(*) as total_pedidos,
        COALESCE(SUM(total_productos), 0) as ventas_totales,
        COALESCE(SUM(comision_plataforma), 0) as comisiones_pagadas
    FROM pedidos
    WHERE id_negocio = ?
    AND id_estado = 6
    AND MONTH(fecha_creacion) = MONTH(CURDATE())
    AND YEAR(fecha_creacion) = YEAR(CURDATE())
");
$stmt->execute([$id_negocio]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Calcular ahorro potencial con PRO
$ventas_mensuales = $stats['ventas_totales'] ?? 0;
$ahorro_mensual = $ventas_mensuales * 0.05; // 5% de diferencia (10% - 5%)

// Configuración de MercadoPago
$mp_access_token = env('MP_ACCESS_TOKEN');
$mp_public_key = env('MP_PUBLIC_KEY');
$mercadopago_available = !empty($mp_access_token) && !empty($mp_public_key);

// Procesar suscripción
$mensaje = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['plan'])) {
    $plan_seleccionado = $_POST['plan'];

    if ($plan_seleccionado === 'pro_mensual' || $plan_seleccionado === 'pro_anual') {
        $precio = $plan_seleccionado === 'pro_mensual' ? 499 : 799;
        $periodo = $plan_seleccionado === 'pro_mensual' ? 'mensual' : 'anual';

        if ($mercadopago_available) {
            // Crear preferencia de MercadoPago
            $app_url = env('APP_URL', 'https://quickbite.com.mx');

            $preference_data = [
                "items" => [[
                    "id" => "membresia_negocio_" . $plan_seleccionado,
                    "title" => "Membresía Socio QuickBite PRO - " . ucfirst($periodo),
                    "description" => "Suscripción PRO para negocios QuickBite",
                    "quantity" => 1,
                    "currency_id" => "MXN",
                    "unit_price" => (float)$precio
                ]],
                "payer" => [
                    "email" => $_SESSION["email"] ?? "negocio" . $id_negocio . "@quickbite.com.mx"
                ],
                "back_urls" => [
                    "success" => $app_url . "/membership_negocios_success.php?plan=" . $plan_seleccionado,
                    "failure" => $app_url . "/membership_negocios.php?error=1",
                    "pending" => $app_url . "/membership_negocios.php?pending=1"
                ],
                "auto_return" => "approved",
                "external_reference" => "negocio_" . $id_negocio . "_" . $plan_seleccionado . "_" . time(),
                "metadata" => [
                    "negocio_id" => $id_negocio,
                    "plan" => $plan_seleccionado,
                    "type" => "business_membership"
                ],
                "notification_url" => $app_url . "/webhooks/mercadopago.php?type=business_membership"
            ];

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => 'https://api.mercadopago.com/checkout/preferences',
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($preference_data),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $mp_access_token,
                    'Content-Type: application/json'
                ],
                CURLOPT_TIMEOUT => 30
            ]);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $result = json_decode($response, true);

            if (($http_code === 200 || $http_code === 201) && !empty($result['init_point'])) {
                header('Location: ' . $result['init_point']);
                exit;
            } else {
                $error = "Error al procesar el pago. Intenta de nuevo.";
            }
        } else {
            $error = "Sistema de pagos no disponible. Contacta a soporte.";
        }
    }
}

// Planes de membresía
$planes = [
    'aliado' => [
        'nombre' => 'Socio Aliado',
        'emoji' => '🔵',
        'precio' => 0,
        'periodo' => 'Gratis para siempre',
        'comision' => 10,
        'beneficios' => [
            ['icon' => 'fa-store', 'texto' => 'Aparecer en la app QuickBite', 'incluido' => true],
            ['icon' => 'fa-tablet-alt', 'texto' => 'Gestión manual en tablet', 'incluido' => true],
            ['icon' => 'fa-money-bill-wave', 'texto' => 'Pago por transferencia semanal', 'incluido' => true],
            ['icon' => 'fa-chart-bar', 'texto' => 'Reportes básicos', 'incluido' => true],
            ['icon' => 'fa-headset', 'texto' => 'Soporte por correo', 'incluido' => true],
            ['icon' => 'fab fa-whatsapp', 'texto' => 'Bot de WhatsApp', 'incluido' => false],
            ['icon' => 'fa-robot', 'texto' => 'Menú Mágico con IA', 'incluido' => false],
            ['icon' => 'fa-cloud-rain', 'texto' => 'Prioridad en días de lluvia', 'incluido' => false],
            ['icon' => 'fa-check-circle', 'texto' => 'Distintivo de Verificado', 'incluido' => false],
            ['icon' => 'fa-ad', 'texto' => 'Publicidad en la app', 'incluido' => false],
            ['icon' => 'fa-gift', 'texto' => 'Kit de Marketing físico', 'incluido' => false],
        ]
    ],
    'pro_mensual' => [
        'nombre' => 'Socio QuickBite PRO',
        'emoji' => '👑',
        'precio' => 499,
        'periodo' => '/mes',
        'comision' => 8,
        'popular' => true,
        'beneficios' => [
            ['icon' => 'fa-store', 'texto' => 'Aparecer en la app QuickBite', 'incluido' => true],
            ['icon' => 'fa-tablet-alt', 'texto' => 'Gestión en tablet + Web Dashboard', 'incluido' => true],
            ['icon' => 'fa-money-bill-wave', 'texto' => 'Dispersión Martes y Viernes', 'incluido' => true],
            ['icon' => 'fa-chart-line', 'texto' => 'Reportes avanzados + Analytics', 'incluido' => true],
            ['icon' => 'fa-headset', 'texto' => 'Soporte especializado prioritario', 'incluido' => true],
            ['icon' => 'fab fa-whatsapp', 'texto' => 'Bot de WhatsApp para pedidos', 'incluido' => true],
            ['icon' => 'fa-robot', 'texto' => 'Menú Mágico con IA (foto a menú)', 'incluido' => true],
            ['icon' => 'fa-cloud-rain', 'texto' => 'Prioridad en días de lluvia', 'incluido' => true],
            ['icon' => 'fa-check-circle', 'texto' => 'Distintivo de Verificado', 'incluido' => true],
            ['icon' => 'fa-ad', 'texto' => 'Publicidad en la app', 'incluido' => true],
            ['icon' => 'fa-gift', 'texto' => 'Kit de Marketing físico', 'incluido' => true],
        ]
    ],
    'pro_anual' => [
        'nombre' => 'Socio QuickBite PRO Anual',
        'emoji' => '👑',
        'precio' => 799,
        'precio_real' => 5988, // $499 x 12
        'descuento' => 87,
        'periodo' => '/mes (pago anual)',
        'comision' => 8,
        'beneficios' => 'Incluye todos los beneficios PRO + 3 meses GRATIS'
    ]
];

// Negocios aliados (para mostrar en la sección de beneficios)
$stmt = $db->query("SELECT nombre, logo FROM negocios WHERE es_premium = 1 AND activo = 1 ORDER BY RAND() LIMIT 6");
$negocios_pro = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Membresías para Negocios - QuickBite</title>
    <link rel="icon" type="image/x-icon" href="assets/img/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=DM+Sans:wght@400;500;700&family=League+Spartan:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #0165FF;
            --primary-dark: #0052CC;
            --gold: #FFD700;
            --gold-dark: #E6C200;
            --success: #22C55E;
            --danger: #EF4444;
            --dark: #0F172A;
            --gray-50: #F8FAFC;
            --gray-100: #F1F5F9;
            --gray-200: #E2E8F0;
            --gray-500: #64748B;
            --gray-600: #475569;
            --gray-900: #0F172A;
            --gradient-gold: linear-gradient(135deg, #FFD700 0%, #FFA500 100%);
            --gradient-blue: linear-gradient(135deg, #0165FF 0%, #4285F4 100%);
            --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0,0,0,0.1);
            --border-radius: 16px;
            --border-radius-lg: 24px;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background: var(--gray-50);
            color: var(--gray-900);
            line-height: 1.6;
        }

        h1, h2, h3, h4 {
            font-family: 'DM Sans', sans-serif;
            font-weight: 700;
        }

        .container { max-width: 1200px; margin: 0 auto; padding: 0 1.5rem; }

        /* Header */
        .header {
            background: white;
            border-bottom: 2px solid var(--gray-100);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 800;
            font-family: 'League Spartan', sans-serif;
            text-decoration: none;
        }

        .logo .quick { color: var(--primary); }
        .logo .bite { color: var(--gold); text-shadow: 0 0 20px rgba(255, 215, 0, 0.5); }

        .back-btn {
            color: var(--gray-600);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 12px;
            transition: all 0.3s;
        }

        .back-btn:hover {
            background: var(--gray-100);
            color: var(--primary);
        }

        /* Hero */
        .hero {
            background: var(--gradient-gold);
            padding: 3rem 0;
            text-align: center;
            color: var(--dark);
        }

        .hero h1 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .hero p {
            font-size: 1.25rem;
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto;
        }

        /* Stats Cards */
        .stats-section {
            margin-top: -50px;
            position: relative;
            z-index: 10;
            padding-bottom: 2rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            text-align: center;
            box-shadow: var(--shadow-lg);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary);
        }

        .stat-label {
            color: var(--gray-500);
            font-size: 0.875rem;
        }

        /* Plan Cards */
        .plans-section {
            padding: 3rem 0;
        }

        .section-title {
            text-align: center;
            margin-bottom: 2rem;
        }

        .section-title h2 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .section-title p {
            color: var(--gray-500);
        }

        .plans-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 2rem;
            max-width: 1000px;
            margin: 0 auto;
        }

        .plan-card {
            background: white;
            border-radius: var(--border-radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            border: 2px solid var(--gray-100);
            transition: all 0.3s;
            position: relative;
        }

        .plan-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-xl);
        }

        .plan-card.popular {
            border-color: var(--gold);
            transform: scale(1.03);
        }

        .plan-card.popular:hover {
            transform: scale(1.03) translateY(-5px);
        }

        .plan-card.current {
            border-color: var(--success);
        }

        .popular-badge {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            background: var(--gradient-gold);
            color: var(--dark);
            text-align: center;
            padding: 0.5rem;
            font-weight: 700;
            font-size: 0.875rem;
        }

        .current-badge {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            background: var(--success);
            color: white;
            text-align: center;
            padding: 0.5rem;
            font-weight: 700;
            font-size: 0.875rem;
        }

        .plan-header {
            padding: 2rem;
            text-align: center;
            padding-top: 3rem;
        }

        .plan-header.aliado {
            background: linear-gradient(135deg, var(--gray-50) 0%, var(--gray-100) 100%);
        }

        .plan-header.pro {
            background: var(--gradient-gold);
            color: var(--dark);
        }

        .plan-emoji {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }

        .plan-name {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .plan-price {
            font-size: 3rem;
            font-weight: 800;
        }

        .plan-price small {
            font-size: 1rem;
            font-weight: 400;
        }

        .plan-comision {
            font-size: 1.25rem;
            margin-top: 0.5rem;
            font-weight: 600;
        }

        .plan-body {
            padding: 2rem;
        }

        .benefit-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .benefit-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--gray-100);
        }

        .benefit-item:last-child {
            border-bottom: none;
        }

        .benefit-item i {
            width: 24px;
            text-align: center;
        }

        .benefit-item.included i {
            color: var(--success);
        }

        .benefit-item.excluded {
            opacity: 0.5;
        }

        .benefit-item.excluded i {
            color: var(--gray-500);
        }

        .btn-plan {
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 1rem;
        }

        .btn-plan.aliado {
            background: var(--gray-200);
            color: var(--gray-600);
        }

        .btn-plan.pro {
            background: var(--gradient-gold);
            color: var(--dark);
        }

        .btn-plan.pro:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(255, 215, 0, 0.5);
        }

        .btn-plan:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        /* Savings Calculator */
        .savings-card {
            background: linear-gradient(135deg, #F0FDF4 0%, #DCFCE7 100%);
            border: 2px solid var(--success);
            border-radius: var(--border-radius);
            padding: 2rem;
            margin: 2rem auto;
            max-width: 800px;
        }

        .savings-card h3 {
            color: var(--success);
            margin-bottom: 1rem;
        }

        .savings-amount {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--success);
        }

        /* Kit Marketing Section */
        .kit-section {
            background: var(--dark);
            color: white;
            padding: 4rem 0;
            margin-top: 3rem;
        }

        .kit-section h2 {
            color: var(--gold);
            margin-bottom: 2rem;
            text-align: center;
        }

        .kit-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
        }

        .kit-item {
            background: rgba(255,255,255,0.1);
            border-radius: var(--border-radius);
            padding: 2rem;
            text-align: center;
        }

        .kit-item i {
            font-size: 3rem;
            color: var(--gold);
            margin-bottom: 1rem;
        }

        .kit-item h4 {
            margin-bottom: 0.5rem;
        }

        .kit-item p {
            color: var(--gray-200);
            font-size: 0.9rem;
        }

        /* Pro Badge */
        .pro-only-badge {
            background: var(--gradient-gold);
            color: var(--dark);
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 700;
            margin-left: 0.5rem;
        }

        /* Negocios PRO Section */
        .negocios-pro-section {
            padding: 3rem 0;
            text-align: center;
        }

        .negocios-grid {
            display: flex;
            justify-content: center;
            gap: 2rem;
            flex-wrap: wrap;
            margin-top: 2rem;
        }

        .negocio-logo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--gold);
            box-shadow: var(--shadow-lg);
        }

        /* Alert */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1rem;
        }

        .alert-success {
            background: #F0FDF4;
            color: var(--success);
            border: 1px solid var(--success);
        }

        .alert-danger {
            background: #FEF2F2;
            color: var(--danger);
            border: 1px solid var(--danger);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero h1 { font-size: 1.75rem; }
            .plans-grid { grid-template-columns: 1fr; }
            .plan-card.popular { transform: none; }
            .plan-card.popular:hover { transform: translateY(-5px); }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container header-content">
            <a href="index.php" class="logo">
                <span class="quick">Quick</span><span class="bite">Bite</span>
            </a>
            <a href="admin/negocio_configuracion.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Volver al Panel
            </a>
        </div>
    </header>

    <!-- Hero -->
    <section class="hero">
        <div class="container">
            <h1>👑 Membresías para Negocios</h1>
            <p>Elige el plan que mejor se adapte a tu negocio y maximiza tus ventas con QuickBite</p>
        </div>
    </section>

    <!-- Stats -->
    <section class="stats-section">
        <div class="container">
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['pending'])): ?>
                <div class="alert alert-warning" style="background: #FFFBEB; color: #92400E; border-color: #F59E0B;">
                    <i class="fas fa-clock"></i> Tu pago está pendiente de confirmación. Te notificaremos cuando se complete.
                </div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($stats['total_pedidos'] ?? 0); ?></div>
                    <div class="stat-label">Pedidos este mes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">$<?php echo number_format($stats['ventas_totales'] ?? 0, 0); ?></div>
                    <div class="stat-label">Ventas este mes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $plan_actual['comision_actual'] ?? 10; ?>%</div>
                    <div class="stat-label">Tu comisión actual</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">$<?php echo number_format($stats['comisiones_pagadas'] ?? 0, 0); ?></div>
                    <div class="stat-label">Comisiones pagadas</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Savings Calculator -->
    <?php if (!$es_pro && $ahorro_mensual > 499): ?>
    <div class="container">
        <div class="savings-card">
            <h3><i class="fas fa-piggy-bank"></i> ¡Upgrade a PRO te conviene!</h3>
            <p>Con tus ventas de <strong>$<?php echo number_format($ventas_mensuales, 0); ?></strong> este mes:</p>
            <div class="savings-amount">
                Ahorrarías $<?php echo number_format($ahorro_mensual - 499, 0); ?>/mes
            </div>
            <small>Al pagar 5% de comisión en lugar de 10%</small>
        </div>
    </div>
    <?php endif; ?>

    <!-- Plans -->
    <section class="plans-section">
        <div class="container">
            <div class="section-title">
                <h2>Elige tu Plan</h2>
                <p>Todos los planes incluyen acceso a la plataforma QuickBite</p>
            </div>

            <div class="plans-grid">
                <!-- Plan Socio Aliado -->
                <div class="plan-card <?php echo !$es_pro ? 'current' : ''; ?>">
                    <?php if (!$es_pro): ?>
                        <div class="current-badge"><i class="fas fa-check"></i> Tu plan actual</div>
                    <?php endif; ?>
                    <div class="plan-header aliado">
                        <div class="plan-emoji">🔵</div>
                        <div class="plan-name">Socio Aliado</div>
                        <div class="plan-price">$0 <small>/siempre</small></div>
                        <div class="plan-comision">Comisión: 10%</div>
                    </div>
                    <div class="plan-body">
                        <ul class="benefit-list">
                            <?php foreach ($planes['aliado']['beneficios'] as $b): ?>
                                <li class="benefit-item <?php echo $b['incluido'] ? 'included' : 'excluded'; ?>">
                                    <i class="<?php echo strpos($b['icon'], 'fab') !== false ? $b['icon'] : 'fas ' . $b['icon']; ?>"></i>
                                    <span><?php echo $b['texto']; ?></span>
                                    <?php if (!$b['incluido']): ?>
                                        <span class="pro-only-badge">Solo PRO</span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if (!$es_pro): ?>
                            <button class="btn-plan aliado" disabled>Plan actual</button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Plan Socio PRO Mensual -->
                <div class="plan-card popular <?php echo $es_pro ? 'current' : ''; ?>">
                    <?php if ($es_pro): ?>
                        <div class="current-badge"><i class="fas fa-crown"></i> Tu plan actual</div>
                    <?php else: ?>
                        <div class="popular-badge"><i class="fas fa-star"></i> Más Popular</div>
                    <?php endif; ?>
                    <div class="plan-header pro">
                        <div class="plan-emoji">👑</div>
                        <div class="plan-name">Socio QuickBite PRO</div>
                        <div class="plan-price">$499 <small>/mes</small></div>
                        <div class="plan-comision">Comisión: 5%</div>
                    </div>
                    <div class="plan-body">
                        <ul class="benefit-list">
                            <?php foreach ($planes['pro_mensual']['beneficios'] as $b): ?>
                                <li class="benefit-item included">
                                    <i class="<?php echo strpos($b['icon'], 'fab') !== false ? $b['icon'] : 'fas ' . $b['icon']; ?>"></i>
                                    <span><?php echo $b['texto']; ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if ($es_pro): ?>
                            <div class="text-center">
                                <span class="text-success fw-bold"><i class="fas fa-check-circle"></i> PRO activo</span>
                                <br><small class="text-muted">Vence: <?php echo date('d/m/Y', strtotime($fecha_vencimiento)); ?></small>
                            </div>
                        <?php else: ?>
                            <form method="POST">
                                <input type="hidden" name="plan" value="pro_mensual">
                                <button type="submit" class="btn-plan pro">
                                    <i class="fas fa-crown"></i> Activar PRO Mensual
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Plan Anual (debajo) -->
            <?php if (!$es_pro): ?>
            <div style="max-width: 500px; margin: 2rem auto;">
                <div class="plan-card" style="border-color: var(--gold);">
                    <div class="plan-header pro" style="padding-bottom: 1rem;">
                        <div class="plan-emoji">👑</div>
                        <div class="plan-name">PRO Anual</div>
                        <div>
                            <span style="text-decoration: line-through; opacity: 0.7;">$5,988</span>
                            <span class="plan-price"> $799 <small>/mes</small></span>
                        </div>
                        <div style="background: var(--success); color: white; display: inline-block; padding: 0.25rem 0.75rem; border-radius: 20px; font-weight: bold; margin-top: 0.5rem;">
                            ¡Ahorra $4,189 (87% OFF)
                        </div>
                    </div>
                    <div class="plan-body">
                        <p class="text-center mb-3"><strong>Todos los beneficios PRO + 3 meses GRATIS</strong></p>
                        <form method="POST">
                            <input type="hidden" name="plan" value="pro_anual">
                            <button type="submit" class="btn-plan pro">
                                <i class="fas fa-crown"></i> Activar PRO Anual - $799/mes
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Kit de Marketing (Solo PRO) -->
    <section class="kit-section">
        <div class="container">
            <h2><i class="fas fa-gift"></i> Kit de Marketing <span class="pro-only-badge" style="font-size: 0.9rem;">Solo PRO</span></h2>
            <div class="kit-grid">
                <div class="kit-item">
                    <i class="fab fa-figma"></i>
                    <h4>Plantillas de Canva</h4>
                    <p>Marco para fotos, "Ya Abrimos", promociones y más diseños listos para usar</p>
                </div>
                <div class="kit-item">
                    <i class="fas fa-qrcode"></i>
                    <h4>Stickers QR</h4>
                    <p>Códigos QR personalizados para puerta, mesa y mostrador</p>
                </div>
                <div class="kit-item">
                    <i class="fas fa-stamp"></i>
                    <h4>Sello de Seguridad</h4>
                    <p>Stickers de seguridad para bolsas de entrega con tu marca</p>
                </div>
                <div class="kit-item">
                    <i class="fas fa-trophy"></i>
                    <h4>Placa Pionero Fundador</h4>
                    <p>Distintivo especial para los primeros 10 Socios PRO de tu zona</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Negocios PRO -->
    <?php if (!empty($negocios_pro)): ?>
    <section class="negocios-pro-section">
        <div class="container">
            <h2>Negocios que ya son <span style="color: var(--gold);">PRO</span></h2>
            <p>Únete a los negocios más exitosos de QuickBite</p>
            <div class="negocios-grid">
                <?php foreach ($negocios_pro as $np): ?>
                    <img src="<?php echo $np['logo'] ?: 'assets/img/default-logo.png'; ?>"
                         alt="<?php echo htmlspecialchars($np['nombre']); ?>"
                         class="negocio-logo"
                         title="<?php echo htmlspecialchars($np['nombre']); ?>">
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
