<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Diagnóstico Completo del API</h1>";

// 1. Verificar sesión
echo "<h2>1. Verificación de Sesión</h2>";
session_start();
echo "<p>Estado de sesión: " . session_status() . "</p>";
echo "<p>ID de sesión: " . session_id() . "</p>";
echo "<p>ID de usuario: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'No definido') . "</p>";

// 2. Verificar configuración
echo "<h2>2. Verificación de Configuración</h2>";
if (file_exists(__DIR__ . '/../config.php')) {
    echo "<p style='color: green;'>✓ config.php existe</p>";
    require_once __DIR__ . '/../config.php';
    
    if (isset($pdo)) {
        echo "<p style='color: green;'>✓ PDO está definido</p>";
        
        // 3. Probar consulta simple
        echo "<h2>3. Prueba de Consulta Simple</h2>";
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM productos");
            $result = $stmt->fetch();
            echo "<p style='color: green;'>✓ Consulta simple exitosa: {$result['total']} productos</p>";
            
            // 4. Probar consulta con JOIN
            echo "<h2>4. Prueba de Consulta con JOIN</h2>";
            $stmt = $pdo->query("
                SELECT p.*, c.nombre as categoria_nombre 
                FROM productos p 
                LEFT JOIN categorias c ON p.categoria_id = c.id 
                LIMIT 5
            ");
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo "<p style='color: green;'>✓ Consulta con JOIN exitosa: " . count($products) . " productos</p>";
            
            // 5. Probar consulta preparada
            echo "<h2>5. Prueba de Consulta Preparada</h2>";
            $stmt = $pdo->prepare("SELECT * FROM productos WHERE stock > ? LIMIT ?");
            $result = $stmt->execute([0, 5]);
            if ($result) {
                $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo "<p style='color: green;'>✓ Consulta preparada exitosa: " . count($products) . " productos</p>";
            } else {
                echo "<p style='color: red;'>✗ Error en consulta preparada</p>";
                $errorInfo = $stmt->errorInfo();
                echo "<p style='color: red;'>Error: " . $errorInfo[2] . "</p>";
            }
            
        } catch (PDOException $e) {
            echo "<p style='color: red;'>✗ Error en consulta: " . $e->getMessage() . "</p>";
        }
        
    } else {
        echo "<p style='color: red;'>✗ PDO no está definido</p>";
    }
} else {
    echo "<p style='color: red;'>✗ config.php no existe</p>";
}

// 6. Verificar información del servidor
echo "<h2>6. Información del Servidor</h2>";
echo "<p>Versión de PHP: " . phpversion() . "</p>";
echo "<p>Versión de MySQL: " . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . "</p>";
echo "<p>Modo emulate prepares: " . ($pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES) ? 'Sí' : 'No') . "</p>";

// 7. Verificar estructura de tablas
echo "<h2>7. Estructura de Tablas</h2>";
 $tables = ['productos', 'categorias', 'usuarios', 'pedidos', 'clientes'];
foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("DESCRIBE $table");
        $columns = $stmt->fetchAll();
        echo "<h3>Tabla: $table</h3>";
        echo "<table border='1'>";
        echo "<tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Clave</th></tr>";
        foreach ($columns as $column) {
            echo "<tr>";
            echo "<td>{$column['Field']}</td>";
            echo "<td>{$column['Type']}</td>";
            echo "<td>{$column['Null']}</td>";
            echo "<td>{$column['Key']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error en tabla $table: " . $e->getMessage() . "</p>";
    }
}
?>