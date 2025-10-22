<?php
require_once 'config.php';
checkAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

 $data = json_decode(file_get_contents('php://input'), true);
 $confirmText = $data['confirmText'] ?? '';

if ($confirmText !== 'LIMPIAR_BASE_DE_DATOS') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Texto de confirmación incorrecto']);
    exit();
}

try {
    $pdo->beginTransaction();
    
    // Desactivar restricciones de clave externa
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // Obtener todas las tablas
    $tables = [];
    $stmt = $pdo->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }
    
    // Truncar todas las tablas excepto usuarios y configuración
    $excludeTables = ['usuarios', 'configuracion'];
    
    foreach ($tables as $table) {
        if (!in_array($table, $excludeTables)) {
            $pdo->exec("TRUNCATE TABLE `$table`");
        }
    }
    
    // Reiniciar AUTO_INCREMENT
    foreach ($tables as $table) {
        if (!in_array($table, $excludeTables)) {
            $pdo->exec("ALTER TABLE `$table` AUTO_INCREMENT = 1");
        }
    }
    
    // Reactivar restricciones de clave externa
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => 'Base de datos limpiada correctamente']);

} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al limpiar la base de datos: ' . $e->getMessage()]);
}
?>