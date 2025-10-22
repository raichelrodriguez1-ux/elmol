<?php
require_once 'config.php';
checkAdmin();

// Obtener datos del POST
 $data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['usuario']) || !isset($data['email']) || !isset($data['password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit();
}

try {
    // Verificar si el usuario o email ya existen
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = ? OR email = ?");
    $stmt->execute([$data['usuario'], $data['email']]);
    
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'El usuario o email ya existen']);
        exit();
    }
    
    // Hashear contraseña
    $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
    
    // Insertar usuario
    $stmt = $pdo->prepare("
        INSERT INTO usuarios (usuario, nombre_completo, email, password, rol) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $data['usuario'],
        $data['nombre_completo'],
        $data['email'],
        $hashedPassword,
        $data['rol'] ?? 'editor'
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Usuario agregado correctamente']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al agregar usuario: ' . $e->getMessage()]);
}
?>