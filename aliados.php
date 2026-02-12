<?php
/**
 * QuickBite - Página de Negocios Aliados
 * Muestra descuentos disponibles para miembros del QuickBite Club
 */

session_start();
require_once 'config/database.php';

// Conexión a BD
$database = new Database();
$db = $database->getConnection();

// Verificar si usuario está logueado
$usuarioLogueado = isset($_SESSION['id_usuario']);
$esMiembro = false;
$usuario = null;

if ($usuarioLogueado) {
    $stmt = $db->prepare("SELECT id_usuario, nombre, es_miembro, es_miembro_club, fecha_fin_membresia FROM usuarios WHERE id_usuario = ?");
    $stmt->execute([$_SESSION['id_usuario']]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    $esMiembro = ($usuario['es_miembro'] == 1 || $usuario['es_miembro_club'] == 1) &&
                 ($usuario['fecha_fin_membresia'] === null || $usuario['fecha_fin_membresia'] >= date('Y-m-d'));
}

// Obtener categorías de aliados
$stmt = $db->query("SELECT * FROM categorias_aliados WHERE activo = 1 ORDER BY orden");
$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener aliados activos
$stmt = $db->query("
    SELECT na.*, ca.nombre as categoria_nombre, ca.icono as categoria_icono
    FROM negocios_aliados na
    JOIN categorias_aliados ca ON na.id_categoria = ca.id_categoria
    WHERE na.estado = 'activo'
    AND (na.fecha_fin_alianza IS NULL OR na.fecha_fin_alianza >= CURDATE())
    ORDER BY ca.orden, na.nombre
");
$aliados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agrupar aliados por categoría
$aliadosPorCategoria = [];
foreach ($aliados as $aliado) {
    $cat = $aliado['id_categoria'];
    if (!isset($aliadosPorCategoria[$cat])) {
        $aliadosPorCategoria[$cat] = [];
    }
    $aliadosPorCategoria[$cat][] = $aliado;
}

// Procesar generación de código si se solicita
$mensaje = null;
$codigoGenerado = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generar_codigo']) && $esMiembro) {
    $idAliado = (int)$_POST['id_aliado'];

    // Verificar límites
    $stmt = $db->prepare("SELECT limite_usos_mes, solo_primera_vez FROM negocios_aliados WHERE id_aliado = ?");
    $stmt->execute([$idAliado]);
    $aliado = $stmt->fetch(PDO::FETCH_ASSOC);

    $puedeGenerar = true;

    // Verificar si solo primera vez
    if ($aliado['solo_primera_vez']) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM uso_beneficios_aliados WHERE id_usuario = ? AND id_aliado = ? AND estado = 'verificado'");
        $stmt->execute([$_SESSION['id_usuario'], $idAliado]);
        if ($stmt->fetchColumn() > 0) {
            $mensaje = ['tipo' => 'error', 'texto' => 'Este beneficio solo es válido la primera vez'];
            $puedeGenerar = false;
        }
    }

    // Verificar límite mensual
    if ($puedeGenerar && $aliado['limite_usos_mes']) {
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM uso_beneficios_aliados
            WHERE id_usuario = ? AND id_aliado = ?
            AND MONTH(fecha_uso) = MONTH(CURDATE()) AND YEAR(fecha_uso) = YEAR(CURDATE())
        ");
        $stmt->execute([$_SESSION['id_usuario'], $idAliado]);
        if ($stmt->fetchColumn() >= $aliado['limite_usos_mes']) {
            $mensaje = ['tipo' => 'error', 'texto' => 'Has alcanzado el límite de usos este mes'];
            $puedeGenerar = false;
        }
    }

    if ($puedeGenerar) {
        // Generar código único
        $codigo = 'QB' . strtoupper(substr(md5($_SESSION['id_usuario'] . $idAliado . time() . rand()), 0, 8));

        $stmt = $db->prepare("
            INSERT INTO codigos_descuento_aliados (id_usuario, id_aliado, codigo, fecha_expiracion)
            VALUES (?, ?, ?, DATE_ADD(CURDATE(), INTERVAL 7 DAY))
        ");
        $stmt->execute([$_SESSION['id_usuario'], $idAliado, $codigo]);

        $codigoGenerado = $codigo;
        $mensaje = ['tipo' => 'exito', 'texto' => 'Código generado exitosamente. Válido por 7 días.'];
    }
}

// Iconos de FontAwesome para categorías
$iconos = [
    'dumbbell' => 'fa-dumbbell',
    'stethoscope' => 'fa-stethoscope',
    'spa' => 'fa-spa',
    'film' => 'fa-film',
    'pills' => 'fa-pills',
    'graduation-cap' => 'fa-graduation-cap',
    'tools' => 'fa-tools',
    'ellipsis-h' => 'fa-ellipsis-h'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Negocios Aliados - QuickBite Club</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #2563EB;
            --primary-dark: #1e40af;
            --gold: #FFD700;
            --success: #10B981;
        }

        body {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            min-height: 100vh;
        }

        .hero-section {
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%);
            color: white;
            padding: 60px 0 40px;
            margin-bottom: 40px;
        }

        .hero-section h1 {
            font-weight: 700;
        }

        .member-badge {
            background: linear-gradient(135deg, var(--gold) 0%, #FFA500 100%);
            color: #1a1a1a;
            padding: 8px 20px;
            border-radius: 30px;
            font-weight: 600;
            display: inline-block;
            margin-top: 15px;
        }

        .category-pill {
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 30px;
            padding: 10px 20px;
            margin: 5px;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .category-pill:hover, .category-pill.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .category-pill i {
            font-size: 18px;
        }

        .aliado-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: all 0.3s;
            height: 100%;
        }

        .aliado-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }

        .aliado-header {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            padding: 20px;
            text-align: center;
        }

        .aliado-icon {
            width: 70px;
            height: 70px;
            background: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            color: white;
            font-size: 28px;
        }

        .aliado-body {
            padding: 20px;
        }

        .aliado-nombre {
            font-weight: 700;
            font-size: 18px;
            margin-bottom: 5px;
        }

        .aliado-categoria {
            color: #64748b;
            font-size: 13px;
            margin-bottom: 15px;
        }

        .descuento-badge {
            background: linear-gradient(135deg, var(--success) 0%, #059669 100%);
            color: white;
            padding: 10px 15px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 20px;
            display: inline-block;
            margin-bottom: 10px;
        }

        .descuento-desc {
            font-size: 14px;
            color: #1e40af;
            font-weight: 600;
        }

        .condiciones {
            font-size: 12px;
            color: #64748b;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px dashed #e2e8f0;
        }

        .btn-generar {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 10px;
            font-weight: 600;
            width: 100%;
            margin-top: 15px;
            transition: all 0.3s;
        }

        .btn-generar:hover {
            transform: scale(1.02);
            box-shadow: 0 4px 15px rgba(37, 99, 235, 0.4);
            color: white;
        }

        .btn-generar:disabled {
            background: #cbd5e1;
            cursor: not-allowed;
        }

        .codigo-box {
            background: #f0fdf4;
            border: 2px dashed var(--success);
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            margin-top: 15px;
        }

        .codigo-text {
            font-family: monospace;
            font-size: 24px;
            font-weight: 700;
            color: var(--success);
            letter-spacing: 2px;
        }

        .no-member-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.9);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 20px;
            border-radius: 16px;
        }

        .card-wrapper {
            position: relative;
        }

        .alert-codigo {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            border: none;
            border-left: 4px solid var(--success);
        }

        .back-btn {
            color: white;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            opacity: 0.9;
            transition: opacity 0.3s;
        }

        .back-btn:hover {
            opacity: 1;
            color: white;
        }
    </style>
</head>
<body>
    <!-- Hero Section -->
    <div class="hero-section">
        <div class="container">
            <a href="index.php" class="back-btn mb-3">
                <i class="fas fa-arrow-left"></i> Volver al inicio
            </a>
            <h1><i class="fas fa-handshake me-2"></i>Negocios Aliados</h1>
            <p class="mb-0">Descuentos exclusivos para miembros QuickBite Club</p>
            <?php if ($esMiembro): ?>
                <div class="member-badge">
                    <i class="fas fa-crown me-2"></i>Eres miembro del Club
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="container pb-5">
        <?php if ($mensaje): ?>
            <div class="alert <?php echo $mensaje['tipo'] === 'exito' ? 'alert-codigo' : 'alert-danger'; ?> mb-4">
                <i class="fas <?php echo $mensaje['tipo'] === 'exito' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> me-2"></i>
                <?php echo htmlspecialchars($mensaje['texto']); ?>
                <?php if ($codigoGenerado): ?>
                    <div class="codigo-box mt-3">
                        <small class="text-muted d-block mb-2">Tu código de descuento:</small>
                        <div class="codigo-text"><?php echo $codigoGenerado; ?></div>
                        <small class="text-muted d-block mt-2">Muestra este código en el negocio</small>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (!$esMiembro): ?>
            <div class="alert alert-warning mb-4">
                <div class="d-flex align-items-center">
                    <i class="fas fa-lock fa-2x me-3"></i>
                    <div>
                        <strong>Beneficios exclusivos para miembros</strong>
                        <p class="mb-2">Hazte miembro del QuickBite Club por solo $49/mes y accede a todos estos descuentos.</p>
                        <a href="membership_subscribe.php" class="btn btn-warning btn-sm">
                            <i class="fas fa-crown me-1"></i> Unirme al Club
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Filtros por categoría -->
        <div class="text-center mb-4">
            <button class="category-pill active" data-categoria="all">
                <i class="fas fa-th"></i> Todos
            </button>
            <?php foreach ($categorias as $cat): ?>
                <?php if (isset($aliadosPorCategoria[$cat['id_categoria']])): ?>
                    <button class="category-pill" data-categoria="<?php echo $cat['id_categoria']; ?>">
                        <i class="fas <?php echo $iconos[$cat['icono']] ?? 'fa-store'; ?>"></i>
                        <?php echo htmlspecialchars($cat['nombre']); ?>
                    </button>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <!-- Grid de aliados -->
        <div class="row g-4">
            <?php foreach ($aliados as $aliado): ?>
                <div class="col-md-6 col-lg-4 aliado-item" data-categoria="<?php echo $aliado['id_categoria']; ?>">
                    <div class="card-wrapper">
                        <div class="aliado-card">
                            <div class="aliado-header">
                                <div class="aliado-icon">
                                    <i class="fas <?php echo $iconos[$aliado['categoria_icono']] ?? 'fa-store'; ?>"></i>
                                </div>
                                <div class="descuento-badge">
                                    <?php echo number_format($aliado['descuento_porcentaje'], 0); ?>% OFF
                                </div>
                            </div>
                            <div class="aliado-body">
                                <div class="aliado-nombre"><?php echo htmlspecialchars($aliado['nombre']); ?></div>
                                <div class="aliado-categoria">
                                    <i class="fas <?php echo $iconos[$aliado['categoria_icono']] ?? 'fa-store'; ?> me-1"></i>
                                    <?php echo htmlspecialchars($aliado['categoria_nombre']); ?>
                                </div>

                                <p class="descuento-desc">
                                    <?php echo htmlspecialchars($aliado['descripcion_descuento']); ?>
                                </p>

                                <?php if ($aliado['descripcion']): ?>
                                    <p class="text-muted small"><?php echo htmlspecialchars($aliado['descripcion']); ?></p>
                                <?php endif; ?>

                                <?php if ($aliado['direccion']): ?>
                                    <p class="small mb-1">
                                        <i class="fas fa-map-marker-alt text-danger me-1"></i>
                                        <?php echo htmlspecialchars($aliado['direccion']); ?>
                                    </p>
                                <?php endif; ?>

                                <?php if ($aliado['telefono']): ?>
                                    <p class="small mb-0">
                                        <i class="fas fa-phone text-primary me-1"></i>
                                        <?php echo htmlspecialchars($aliado['telefono']); ?>
                                    </p>
                                <?php endif; ?>

                                <?php if ($aliado['condiciones']): ?>
                                    <div class="condiciones">
                                        <i class="fas fa-info-circle me-1"></i>
                                        <?php echo htmlspecialchars($aliado['condiciones']); ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($esMiembro): ?>
                                    <form method="POST">
                                        <input type="hidden" name="id_aliado" value="<?php echo $aliado['id_aliado']; ?>">
                                        <button type="submit" name="generar_codigo" class="btn btn-generar">
                                            <i class="fas fa-qrcode me-2"></i>Generar código
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <button class="btn btn-generar" disabled>
                                        <i class="fas fa-lock me-2"></i>Solo miembros
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($aliados)): ?>
            <div class="text-center py-5">
                <i class="fas fa-store-slash fa-4x text-muted mb-3"></i>
                <h4>No hay aliados disponibles</h4>
                <p class="text-muted">Pronto agregaremos más negocios aliados en tu zona.</p>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Filtrar por categoría
        document.querySelectorAll('.category-pill').forEach(pill => {
            pill.addEventListener('click', function() {
                document.querySelectorAll('.category-pill').forEach(p => p.classList.remove('active'));
                this.classList.add('active');

                const categoria = this.dataset.categoria;
                document.querySelectorAll('.aliado-item').forEach(item => {
                    if (categoria === 'all' || item.dataset.categoria === categoria) {
                        item.style.display = 'block';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });
        });
    </script>
</body>
</html>
