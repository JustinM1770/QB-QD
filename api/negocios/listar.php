<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../config/database.php';

$categoriaId = isset($_GET['categoria']) ? (int)$_GET['categoria'] : null;
$buscar = isset($_GET['buscar']) ? trim($_GET['buscar']) : null;
$pagina = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$limite = isset($_GET['limite']) ? min(50, max(1, (int)$_GET['limite'])) : 20;
$offset = ($pagina - 1) * $limite;

try {
    $where = ["n.activo = 1"];
    $params = [];

    if ($categoriaId) {
        $where[] = "n.id_categoria = ?";
        $params[] = $categoriaId;
    }

    if ($buscar) {
        $where[] = "(n.nombre LIKE ? OR n.descripcion LIKE ?)";
        $params[] = "%$buscar%";
        $params[] = "%$buscar%";
    }

    $whereClause = implode(' AND ', $where);

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM negocios n WHERE $whereClause");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $sql = "SELECT n.id, n.nombre, n.descripcion, n.direccion, n.telefono, n.logo, n.portada,
                   c.nombre as categoria, n.id_categoria, n.calificacion,
                   COALESCE(n.total_resenas, 0) as total_resenas,
                   COALESCE(n.tiempo_entrega, '30-45 min') as tiempo_entrega,
                   COALESCE(n.costo_envio, 0) as costo_envio,
                   COALESCE(n.pedido_minimo, 0) as pedido_minimo,
                   n.abierto
            FROM negocios n
            LEFT JOIN categorias c ON n.id_categoria = c.id
            WHERE $whereClause
            ORDER BY n.calificacion DESC, n.nombre ASC
            LIMIT ? OFFSET ?";

    $params[] = $limite;
    $params[] = $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $negocios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Cast types
    foreach ($negocios as &$n) {
        $n['id'] = (int)$n['id'];
        $n['id_categoria'] = (int)$n['id_categoria'];
        $n['calificacion'] = (float)$n['calificacion'];
        $n['total_resenas'] = (int)$n['total_resenas'];
        $n['costo_envio'] = (float)$n['costo_envio'];
        $n['pedido_minimo'] = (float)$n['pedido_minimo'];
        $n['abierto'] = (bool)$n['abierto'];
    }

    echo json_encode([
        'success' => true,
        'negocios' => $negocios,
        'total' => $total
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error del servidor']);
}
