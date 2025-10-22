<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

//require_once __DIR__ . '/config.php';
require_once 'config.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

try {
    // Por ahora retornamos un array vacío hasta que implementes el sistema de notificaciones
    echo json_encode([]);
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos']);
}
?>