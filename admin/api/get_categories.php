<?php
require_once 'config.php';

try {
    $stmt = $pdo->prepare("SELECT * FROM categorias ORDER BY nombre");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($categories);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al obtener categorías: ' . $e->getMessage()]);
}
?>