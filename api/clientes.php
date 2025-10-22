<?php
require_once 'config.php';

header('Content-Type: application/json');

// Obtener el método de solicitud
 $method = $_SERVER['REQUEST_METHOD'];

// Obtener datos de la solicitud
 $data = json_decode(file_get_contents('php://input'), true);

if ($method === 'POST' && isset($data['action'])) {
    if ($data['action'] === 'findOrCreate') {
        // Buscar cliente por teléfono o email
        $phone = $data['phone'];
        $email = $data['email'];
        
        $checkQuery = "SELECT id FROM clientes WHERE telefono = ? OR email = ?";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bind_param("ss", $phone, $email);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        if ($result->num_rows > 0) {
            // Cliente existe, devolver su ID
            $row = $result->fetch_assoc();
            echo json_encode(['id' => $row['id']]);
        } else {
            // Crear nuevo cliente
            $name = $data['name'];
            $insertQuery = "INSERT INTO clientes (nombre, email, telefono) VALUES (?, ?, ?)";
            $insertStmt = $conn->prepare($insertQuery);
            $insertStmt->bind_param("sss", $name, $email, $phone);
            
            if ($insertStmt->execute()) {
                $clientId = $conn->insert_id;
                echo json_encode(['id' => $clientId]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Error al crear cliente']);
            }
        }
        
        $checkStmt->close();
        if (isset($insertStmt)) $insertStmt->close();
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
}

 $conn->close();
?>