<?php
require_once 'config.php';

// Obtener categorías
 $sql = "SELECT * FROM categorias ORDER BY nombre";
 $result = $conn->query($sql);

if ($result) {
    $categorias = [];
    while ($row = $result->fetch_assoc()) {
        $categorias[] = $row;
    }
    sendResponse($categorias);
} else {
    sendResponse(['error' => 'Error al obtener categorías: ' . $conn->error], 500);
}

 $conn->close();
?>