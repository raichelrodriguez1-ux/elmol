<?php
require_once 'config.php';

try {
    $stmt = $pdo->prepare("
        SELECT c.*, 
               COUNT(o.id) as total_pedidos, 
               COALESCE(SUM(o.total), 0) as total_gastado,
               MAX(o.fecha_pedido) as ultimo_pedido
        FROM clientes c 
        LEFT JOIN pedidos o ON c.id = o.cliente_id 
        GROUP BY c.id 
        ORDER BY c.nombre
    ");
    $stmt->execute();
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($customers);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al obtener clientes: ' . $e->getMessage()]);
}
?>