<?php
require_once 'config.php';
checkAdmin();

// Obtener datos del POST
 $data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de usuario no proporcionado']);
    exit();
}

try {
    // Verificar si el usuario existe
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE id = ?");
    $stmt->execute([$data['id']]);
    
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
        exit();
    }
    
    // Eliminar usuario
    $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
    $stmt->execute([$data['id']]);
    
    echo json_encode(['success' => true, 'message' => 'Usuario eliminado correctamente']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al eliminar usuario: ' . $e->getMessage()]);
}
?>