<?php
require_once 'config.php';
checkAdmin();

// Obtener datos del POST
 $data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['schedule'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit();
}

try {
    // Primero, eliminar todos los horarios existentes
    $stmt = $pdo->prepare("DELETE FROM horarios");
    $stmt->execute();
    
    // Insertar los nuevos horarios
    $stmt = $pdo->prepare("
        INSERT INTO horarios (dia_semana, hora_apertura, hora_cierre, esta_abierto) 
        VALUES (?, ?, ?, ?)
    ");
    
    foreach ($data['schedule'] as $day) {
        $stmt->execute([
            $day['dia_semana'],
            $day['hora_apertura'],
            $day['hora_cierre'],
            $day['esta_abierto']
        ]);
    }
    
    echo json_encode(['success' => true, 'message' => 'Horarios guardados correctamente']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al guardar horarios: ' . $e->getMessage()]);
}
?>