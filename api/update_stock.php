<?php
require_once 'config.php';

// Método POST: Actualizar stock de producto
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $id = $conn->real_escape_string($data['id']);
    $stock = $conn->real_escape_string($data['stock']);
    
    $sql = "UPDATE productos SET stock = $stock WHERE id = $id";
    
    if ($conn->query($sql)) {
        sendResponse(['success' => true]);
    } else {
        sendResponse(['error' => 'Error al actualizar stock: ' . $conn->error], 500);
    }
}

 $conn->close();
?>