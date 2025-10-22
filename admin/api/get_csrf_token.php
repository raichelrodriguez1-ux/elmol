<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

// Generar nuevo token CSRF
 $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

echo json_encode([
    'success' => true,
    'csrf_token' => $_SESSION['csrf_token']
]);
?>