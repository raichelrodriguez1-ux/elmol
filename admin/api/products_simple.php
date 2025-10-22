<?php
// Versión simplificada para diagnosticar el problema
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

try {
    // Consulta simple sin paginación primero
    $sql = "SELECT p.*, c.nombre as categoria_nombre 
            FROM productos p 
            LEFT JOIN categorias c ON p.categoria_id = c.id 
            ORDER BY p.nombre ASC";
    
    echo json_encode([
        'debug' => [
            'sql' => $sql,
            'session_id' => session_id(),
            'user_id' => $_SESSION['user_id']
        ]
    ]);
    
    $stmt = $pdo->query($sql);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'products' => $products,
        'total' => count($products)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>