<?php
require_once 'config.php';
checkAdmin();

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de categoría no proporcionado']);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT * FROM categorias WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$category) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Categoría no encontrada']);
        exit();
    }
    
    echo json_encode($category);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al obtener categoría: ' . $e->getMessage()]);
}
?>