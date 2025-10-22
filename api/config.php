<?php
// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'tienda_admin');

// Conexión a la base de datos
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Verificar conexión
if ($conn->connect_error) {
    die(json_encode(['error' => 'Error de conexión: ' . $conn->connect_error]));
}

// Establecer charset
$conn->set_charset("utf8mb4");

// Función para enviar respuesta JSON
function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    header("Access-Control-Allow-Origin: *"); // <-- Permitir CORS
    echo json_encode($data);
    exit;
}
?>
