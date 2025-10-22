<?php
require_once 'config.php';
checkAdmin();

// Obtener datos del POST
 $data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de categoría no proporcionado']);
    exit();
}

try {
    // Verificar si la categoría existe
    $stmt = $pdo->prepare("SELECT id FROM categorias WHERE id = ?");
    $stmt->execute([$data['id']]);
    
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Categoría no encontrada']);
        exit();
    }
    
    // Verificar si hay productos asociados a esta categoría
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM productos WHERE categoria_id = ?");
    $stmt->execute([$data['id']]);
    $count = $stmt->fetch()['count'];
    
    if ($count > 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No se puede eliminar la categoría porque tiene productos asociados']);
        exit();
    }
    
    // Eliminar categoría
    $stmt = $pdo->prepare("DELETE FROM categorias WHERE id = ?");
    $stmt->execute([$data['id']]);
    
    echo json_encode(['success' => true, 'message' => 'Categoría eliminada correctamente']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al eliminar categoría: ' . $e->getMessage()]);
}
?>