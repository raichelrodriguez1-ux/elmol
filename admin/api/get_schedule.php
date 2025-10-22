<?php
require_once 'config.php';

try {
    $stmt = $pdo->prepare("SELECT * FROM horarios ORDER BY FIELD(dia_semana, 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo')");
    $stmt->execute();
    $schedule = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($schedule);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al obtener horarios: ' . $e->getMessage()]);
}
?>