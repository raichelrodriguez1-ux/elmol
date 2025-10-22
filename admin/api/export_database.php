<?php
require_once 'config.php';
checkAdmin();

try {
    // Obtener todas las tablas
    $tables = [];
    $stmt = $pdo->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }

    $data = [];
    
    foreach ($tables as $table) {
        // Obtener estructura de la tabla
        $stmt = $pdo->query("DESCRIBE `$table`");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Obtener datos de la tabla
        $stmt = $pdo->query("SELECT * FROM `$table`");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $data[$table] = [
            'columns' => $columns,
            'rows' => $rows
        ];
    }

    // Crear JSON
    $json = json_encode($data, JSON_PRETTY_PRINT);
    
    // Configurar headers para descarga
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="database_export_' . date('Y-m-d_H-i-s') . '.json"');
    header('Content-Length: ' . strlen($json));
    
    echo $json;
    exit();

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al exportar la base de datos: ' . $e->getMessage()]);
}
?>