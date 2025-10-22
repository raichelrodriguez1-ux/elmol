<?php
require_once 'config.php';

// Obtener configuración
 $sql = "SELECT * FROM configuracion";
 $result = $conn->query($sql);

if ($result) {
    $configuracion = [];
    while ($row = $result->fetch_assoc()) {
        $configuracion[] = $row;
    }
    sendResponse($configuracion);
} else {
    sendResponse(['error' => 'Error al obtener configuración: ' . $conn->error], 500);
}

 $conn->close();
?>