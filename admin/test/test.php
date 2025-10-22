<?php
// Activar todos los errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Test de Conexión a Base de Datos</h1>";

try {
    // Incluir configuración
    require_once __DIR__ . '/config.php';
    echo "<p style='color: green;'>✓ Config.php cargado correctamente</p>";
    
    // Probar conexión
    if (isset($pdo)) {
        echo "<p style='color: green;'>✓ Conexión a PDO establecida</p>";
        
        // Probar consulta simple
        $stmt = $pdo->query("SELECT 1 as test");
        $result = $stmt->fetch();
        if ($result) {
            echo "<p style='color: green;'>✓ Consulta de prueba exitosa</p>";
        }
        
        // Probar tabla productos
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM productos");
        $count = $stmt->fetch();
        echo "<p style='color: blue;'>📊 Total productos: " . $count['total'] . "</p>";
        
        // Probar tabla categorías
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM categorias");
        $count = $stmt->fetch();
        echo "<p style='color: blue;'>📊 Total categorías: " . $count['total'] . "</p>";
        
        // Verificar estructura de la tabla productos
        $stmt = $pdo->query("DESCRIBE productos");
        $columns = $stmt->fetchAll();
        echo "<h3>Estructura de la tabla productos:</h3>";
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
        
    } else {
        echo "<p style='color: red;'>✗ Error: PDO no está definido</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>✗ Error de base de datos: " . $e->getMessage() . "</p>";
    echo "<p style='color: red;'>Código: " . $e->getCode() . "</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error general: " . $e->getMessage() . "</p>";
}

echo "<h2>Información del Servidor</h2>";
echo "<p>Versión de PHP: " . phpversion() . "</p>";
echo "<p>Extensiones PDO: ";
echo extension_loaded('pdo_mysql') ? '<span style="color: green;">✓ MySQL PDO disponible</span>' : '<span style="color: red;">✗ MySQL PDO no disponible</span>';
echo "</p>";
echo "<p>Extensiones JSON: ";
echo extension_loaded('json') ? '<span style="color: green;">✓ JSON disponible</span>' : '<span style="color: red;">✗ JSON no disponible</span>';
echo "</p>";

// Verificar sesión
echo "<h2>Información de Sesión</h2>";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
echo "<p>Estado de sesión: " . session_status() . "</p>";
echo "<p>ID de sesión: " . session_id() . "</p>";
echo "<p>ID de usuario: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'No definido') . "</p>";

// Verificar archivos
echo "<h2>Archivos Requeridos</h2>";
 $requiredFiles = [
    '../config.php',
    '../cache.php'
];

foreach ($requiredFiles as $file) {
    if (file_exists(__DIR__ . '/' . $file)) {
        echo "<p style='color: green;'>✓ $file existe</p>";
    } else {
        echo "<p style='color: red;'>✗ $file no existe</p>";
    }
}
?>