<?php
require_once 'config.php';
checkAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

 $data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit();
}

try {
    $stmt = $pdo->prepare("
        UPDATE categorias 
        SET nombre = ?, descripcion = ?, color = ?
        WHERE id = ?
    ");
    
    $stmt->execute([
        $data['nombre'],
        $data['descripcion'] ?? null,
        $data['color'] ?? '#007bff',
        $data['id']
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Categoría actualizada correctamente']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al actualizar categoría: ' . $e->getMessage()]);
}
?>