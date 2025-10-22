<?php
require_once 'config.php';
checkAdmin();

// Obtener datos del POST
 $data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de producto no proporcionado']);
    exit();
}

try {
    // Verificar si el producto existe
    $stmt = $pdo->prepare("SELECT id FROM productos WHERE id = ?");
    $stmt->execute([$data['id']]);
    
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Producto no encontrado']);
        exit();
    }
    
    // Eliminar producto
    $stmt = $pdo->prepare("DELETE FROM productos WHERE id = ?");
    $stmt->execute([$data['id']]);
    
    echo json_encode(['success' => true, 'message' => 'Producto eliminado correctamente']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al eliminar producto: ' . $e->getMessage()]);
}
?>