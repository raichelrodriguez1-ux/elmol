<?php
require_once 'config.php';
checkAdmin();

// Obtener datos del POST
 $data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit();
}

try {
    // Actualizar cada configuración
    foreach ($data as $key => $value) {
        $stmt = $pdo->prepare("
            INSERT INTO configuracion (clave, valor) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE valor = ?
        ");
        
        $stmt->execute([$key, $value, $value]);
    }
    
    echo json_encode(['success' => true, 'message' => 'Configuración guardada correctamente']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al guardar configuración: ' . $e->getMessage()]);
}
?>