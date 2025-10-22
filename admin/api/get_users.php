<?php
require_once 'config.php';
checkAdmin();

try {
    $stmt = $pdo->prepare("SELECT id, usuario, nombre_completo, email, rol, fecha_creacion FROM usuarios ORDER BY nombre_completo");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($users);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al obtener usuarios: ' . $e->getMessage()]);
}
?>