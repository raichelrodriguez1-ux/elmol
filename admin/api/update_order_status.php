<?php
// Se requiere la configuración de la base de datos y las funciones de autenticación
require_once 'config.php';
checkAdmin();


// 1. Verificar que el usuario esté logueado
if (!isLoggedIn()) {
    http_response_code(401); // No autorizado
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado.']);
    exit();
}

// 2. Establecer el tipo de contenido a JSON
header('Content-Type: application/json');

// 3. Obtener los datos enviados desde el frontend (vienen en formato JSON)
 $data = json_decode(file_get_contents('php://input'), true);

// 4. Validar los datos recibidos
if (!isset($data['order_id']) || !isset($data['status']) || !isset($data['csrf_token'])) {
    http_response_code(400); // Solicitud incorrecta
    echo json_encode(['success' => false, 'message' => 'Faltan datos requeridos.']);
    exit();
}

 $orderId = intval($data['order_id']);
 $newStatus = $data['status'];
 $csrfToken = $data['csrf_token'];

// 5. Validar el Token CSRF para prevenir ataques
if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    http_response_code(403); // Prohibido
    echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido.']);
    exit();
}

// 6. Validar que el estado sea uno de los permitidos
 $allowedStatuses = ['pendiente', 'procesando', 'enviado', 'completado', 'cancelado'];
if (!in_array($newStatus, $allowedStatuses)) {
    http_response_code(400); // Solicitud incorrecta
    echo json_encode(['success' => false, 'message' => 'Estado de pedido no válido.']);
    exit();
}

// 7. Preparar y ejecutar la consulta a la base de datos
try {
    $sql = "UPDATE pedidos SET estado = :status WHERE id = :order_id";
    $stmt = $pdo->prepare($sql);
    
    // Vincular los parámetros para prevenir inyección SQL
    $stmt->bindParam(':status', $newStatus, PDO::PARAM_STR);
    $stmt->bindParam(':order_id', $orderId, PDO::PARAM_INT);
    
    // Ejecutar la consulta
    $stmt->execute();
    
    // 8. Verificar si se actualizó algún registro
    if ($stmt->rowCount() > 0) {
        // Opcional: Aquí podrías añadir lógica para enviar una notificación al cliente
        // Por ejemplo, si el pedido se marca como "completado" o "enviado"
        $notificationSent = false;
        if ($newStatus === 'completado' || $newStatus === 'enviado') {
            // Llama a una función para enviar un email (debes implementarla)
            // sendOrderUpdateNotification($orderId, $newStatus);
            $notificationSent = true; // Simulamos que se envió
        }

        echo json_encode([
            'success' => true, 
            'message' => 'Estado del pedido actualizado correctamente.',
            'notification_sent' => $notificationSent // Informar al frontend si se envió una notificación
        ]);
    } else {
        http_response_code(404); // No encontrado
        echo json_encode(['success' => false, 'message' => 'No se encontró el pedido con el ID especificado.']);
    }

} catch (PDOException $e) {
    // 9. Manejar errores de la base de datos
    http_response_code(500); // Error interno del servidor
    // En producción, es mejor no mostrar el error exacto al usuario
    error_log("Error al actualizar estado del pedido: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al actualizar el estado en la base de datos.']);
}
?>