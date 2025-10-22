<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once 'config.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID no proporcionado']);
    exit();
}

try {
    $id = intval($_GET['id']);
    
    $stmt = $pdo->prepare("
        SELECT p.*, c.nombre as categoria_nombre,
               CASE 
                   WHEN p.imagen_url LIKE 'http%' THEN p.imagen_url
                   WHEN p.imagen_url IS NOT NULL AND p.imagen_url != '' THEN CONCAT('../', p.imagen_url)
                   ELSE NULL
               END as imagen_url_full
        FROM productos p 
        LEFT JOIN categorias c ON p.categoria_id = c.id 
        WHERE p.id = ?
    ");
    
    $stmt->execute([$id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($product) {
        echo json_encode($product);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Producto no encontrado']);
    }
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos']);
}
?>