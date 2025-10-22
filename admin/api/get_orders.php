<?php
require_once 'config.php';

try {
    $stmt = $pdo->prepare("
        SELECT o.*, c.nombre as cliente_nombre 
        FROM pedidos o 
        LEFT JOIN clientes c ON o.cliente_id = c.id 
        ORDER BY o.fecha_pedido DESC
    ");
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($orders);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al obtener pedidos: ' . $e->getMessage()]);
}
?>