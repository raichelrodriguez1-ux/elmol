<?php
// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // <-- Tu usuario de MySQL
define('DB_PASS', '');   // <-- Tu contraseña de MySQL
define('DB_NAME', 'tienda_admin');

// Crear la conexión
 $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Verificar la conexión
if ($conn->connect_error) {
    // En un entorno real, aquí se registraría el error en un log
    // y no se mostraría al usuario final.
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Error de conexión a la base de datos: ' . $conn->connect_error]);
    exit();
}

// Establecer el charset a utf8mb4 para soporte completo de caracteres
 $conn->set_charset("utf8mb4");
?>