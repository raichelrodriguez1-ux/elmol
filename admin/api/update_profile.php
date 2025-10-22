<?php
require_once 'config.php';
 $user = checkAuth();

// Obtener datos del POST
 $data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit();
}

try {
    // Actualizar datos básicos
    $stmt = $pdo->prepare("
        UPDATE usuarios 
        SET nombre_completo = ?, email = ? 
        WHERE id = ?
    ");
    
    $stmt->execute([
        $data['nombre_completo'],
        $data['email'],
        $user['id']
    ]);
    
    // Si se proporciona una nueva contraseña, actualizarla
    if (!empty($data['new_password'])) {
        // Verificar contraseña actual
        if (!password_verify($data['current_password'], $user['password'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'La contraseña actual es incorrecta']);
            exit();
        }
        
        // Actualizar contraseña
        $hashedPassword = password_hash($data['new_password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
        $stmt->execute([$hashedPassword, $user['id']]);
    }
    
    echo json_encode(['success' => true, 'message' => 'Perfil actualizado correctamente']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al actualizar perfil: ' . $e->getMessage()]);
}
?>