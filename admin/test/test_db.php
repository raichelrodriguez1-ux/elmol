<?php
// Intenta con credenciales comunes
 $hosts = ['localhost', '127.0.0.1'];
 $users = ['root', 'admin', 'usuario'];
 $passwords = ['', 'root', 'admin', 'password', '123456'];
 $dbname = 'tienda_admin'; // Cambia esto si tu base de datos tiene otro nombre

foreach ($hosts as $host) {
    foreach ($users as $user) {
        foreach ($passwords as $pass) {
            try {
                $pdo = new PDO(
                    "mysql:host=$host;dbname=$dbname;charset=utf8mb4", 
                    $user, 
                    $pass
                );
                
                echo "<h2 style='color: green;'>✓ Conexión exitosa!</h2>";
                echo "<p><strong>Host:</strong> $host</p>";
                echo "<p><strong>Usuario:</strong> $user</p>";
                echo "<p><strong>Base de datos:</strong> $dbname</p>";
                echo "<p><strong>Contraseña:</strong> " . ($pass ? $pass : '(vacía)') . "</p>";
                
                // Probar una consulta
                $stmt = $pdo->query("SHOW TABLES");
                $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
                echo "<p><strong>Tablas encontradas:</strong> " . implode(', ', $tables) . "</p>";
                
                exit;
                
            } catch (PDOException $e) {
                // Continuar con la siguiente combinación
                continue;
            }
        }
    }
}

echo "<h2 style='color: red;'>✗ No se pudo encontrar una combinación válida</h2>";
echo "<p>Por favor, verifica tus credenciales de base de datos manualmente</p>";
?>