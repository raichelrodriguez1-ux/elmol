<?php
// Configuración de la base de datos
 $host = 'localhost';
 $dbname = 'tienda_admin';
 $user = 'root';
 $password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Función para verificar si el usuario está autenticado
function checkAuth() {
    session_start();
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
        exit();
    }
    
    // Obtener información del usuario actual
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

// Función para verificar si el usuario tiene permisos de administrador
function checkAdmin() {
    $user = checkAuth();
    if ($user['rol'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Permisos insuficientes']);
        exit();
    }
    return $user;
}

// Configurar cabeceras para respuestas JSON
header('Content-Type: application/json');
?>