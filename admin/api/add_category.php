<?php
require_once 'config.php';
checkAdmin();

// Obtener datos del POST
 $data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['nombre'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit();
}

try {
    // Verificar si la categoría ya existe
    $stmt = $pdo->prepare("SELECT id FROM categorias WHERE nombre = ?");
    $stmt->execute([$data['nombre']]);
    
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'La categoría ya existe']);
        exit();
    }
    
    // Insertar categoría
    $stmt = $pdo->prepare("
        INSERT INTO categorias (nombre, descripcion, color) 
        VALUES (?, ?, ?)
    ");
    
    $stmt->execute([
        $data['nombre'],
        $data['descripcion'] ?? null,
        $data['color'] ?? '#007bff'
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Categoría agregada correctamente']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al agregar categoría: ' . $e->getMessage()]);
}
?>