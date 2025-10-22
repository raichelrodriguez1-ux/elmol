<?php
// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'tienda_admin'); // Reemplaza con el nombre real
define('DB_USER', 'root'); // Reemplaza con el usuario real
define('DB_PASS', ''); // Reemplaza con la contraseña real

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", 
        DB_USER, 
        DB_PASS
    );
    
    // Configurar PDO para mostrar errores
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>