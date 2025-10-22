<?php
require_once 'config.php';

try {
    $sql = "SELECT 
                p.id, p.nombre, p.descripcion, p.precio, p.stock, p.garantia, p.imagen_url, 
                p.categoria_id, p.marca, c.nombre AS categoria_nombre
            FROM productos p
            LEFT JOIN categorias c ON p.categoria_id = c.id
            ORDER BY p.nombre ASC";

    $result = $conn->query($sql);

    if (!$result) {
        sendResponse(['error' => 'Error en la consulta de productos: ' . $conn->error], 500);
    }

    $products = [];
    
    // Detectar base URL automáticamente
    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";

    while ($row = $result->fetch_assoc()) {
        $imagen_url = $row['imagen_url'];

        // Si la imagen no es una URL completa, convertirla a URL absoluta
        if (!empty($imagen_url) && !filter_var($imagen_url, FILTER_VALIDATE_URL)) {
            $imagen_url = $base_url . '/' . ltrim($imagen_url, '/');
        } elseif (empty($imagen_url)) {
            // Imagen placeholder si no hay imagen
            $imagen_url = "https://picsum.photos/seed/product{$row['id']}/400/300.jpg";
        }

        $products[] = [
            'id' => (int)$row['id'],
            'nombre' => htmlspecialchars($row['nombre'], ENT_QUOTES, 'UTF-8'),
            'descripcion' => htmlspecialchars($row['descripcion'] ?? 'Sin descripción', ENT_QUOTES, 'UTF-8'),
            'precio' => (float)$row['precio'],
            'stock' => (int)$row['stock'],
            'garantia' => htmlspecialchars($row['garantia'], ENT_QUOTES, 'UTF-8'),
            'imagen_url' => $imagen_url,
            'categoria_id' => (int)$row['categoria_id'],
            'categoria_nombre' => htmlspecialchars($row['categoria_nombre'] ?? 'Sin categoría', ENT_QUOTES, 'UTF-8'),
            'marca' => htmlspecialchars($row['marca'], ENT_QUOTES, 'UTF-8')
        ];
    }

    sendResponse($products);

} catch (Exception $e) {
    sendResponse(['error' => 'Error inesperado en el servidor.'], 500);
}

$conn->close();
?>
