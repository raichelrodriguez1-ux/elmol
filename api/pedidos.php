<?php
require_once 'config.php';

header('Content-Type: application/json');

// Obtener el método de solicitud
 $method = $_SERVER['REQUEST_METHOD'];

// Obtener datos de la solicitud
 $data = json_decode(file_get_contents('php://input'), true);

if ($method === 'POST' && isset($data['action'])) {
    if ($data['action'] === 'create') {
        // Iniciar transacción
        $conn->begin_transaction();
        
        try {
            // Insertar pedido
            $clientId = $data['clientId'];
            $total = $data['total'];
            $deliveryType = $data['deliveryType'];
            $address = $data['address'];
            $notes = $data['notes'];
            
            $insertOrderQuery = "INSERT INTO pedidos (cliente_id, total, estado) VALUES (?, ?, 'pendiente')";
            $insertOrderStmt = $conn->prepare($insertOrderQuery);
            $insertOrderStmt->bind_param("id", $clientId, $total);
            $insertOrderStmt->execute();
            
            $orderId = $conn->insert_id;
            
            // Insertar detalles del pedido
            foreach ($data['items'] as $item) {
                $productId = $item['id'];
                $quantity = $item['quantity'];
                $price = $item['price'];
                
                $insertDetailQuery = "INSERT INTO detalles_pedido (pedido_id, producto_id, cantidad, precio_unitario) VALUES (?, ?, ?, ?)";
                $insertDetailStmt = $conn->prepare($insertDetailQuery);
                $insertDetailStmt->bind_param("iiid", $orderId, $productId, $quantity, $price);
                $insertDetailStmt->execute();
                
                // Actualizar stock del producto
                $updateStockQuery = "UPDATE productos SET stock = stock - ? WHERE id = ?";
                $updateStockStmt = $conn->prepare($updateStockQuery);
                $updateStockStmt->bind_param("ii", $quantity, $productId);
                $updateStockStmt->execute();
            }
            
            // Confirmar transacción
            $conn->commit();
            
            echo json_encode(['id' => $orderId, 'message' => 'Pedido creado exitosamente']);
            
        } catch (Exception $e) {
            // Revertir transacción en caso de error
            $conn->rollback();
            http_response_code(500);
            echo json_encode(['error' => 'Error al crear el pedido: ' . $e->getMessage()]);
        }
        
        if (isset($insertOrderStmt)) $insertOrderStmt->close();
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
}

 $conn->close();
?>