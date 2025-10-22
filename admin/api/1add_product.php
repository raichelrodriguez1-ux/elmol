<?php
require_once __DIR__ . '/config.php';
checkAdmin(); // Asegúrate de que solo admins puedan usarlo

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

try {
    // Validación de campos
    $nombre = trim($_POST['nombre'] ?? '');
    $precio = floatval($_POST['precio'] ?? 0);
    $garantia = trim($_POST['garantia'] ?? '');
    $stock = intval($_POST['stock'] ?? 0);
    $categoria_id = !empty($_POST['categoria_id']) ? intval($_POST['categoria_id']) : null;
    $marca = trim($_POST['marca'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');

    if (!$nombre || !$precio || !$garantia) {
        throw new Exception("Nombre, precio y garantía son obligatorios");
    }

    // Validar y subir imagen
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Imagen del producto es obligatoria");
    }

    $file = $_FILES['image'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxFileSize = 5 * 1024 * 1024; // 5MB

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowedTypes)) {
        throw new Exception("Tipo de archivo no permitido");
    }
    if ($file['size'] > $maxFileSize) {
        throw new Exception("El archivo es demasiado grande");
    }

    // Guardar imagen en carpeta correcta
    $type = 'products';
    $uploadDir = __DIR__ . "/../../uploads/{$type}/"; // Desde admin/api a htdocs/tienda/uploads
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $filename = basename($file['name']); // Mantener nombre original
    $uploadPath = $uploadDir . $filename;

    // Evitar sobreescribir archivos existentes
    $counter = 1;
    while (file_exists($uploadPath)) {
        $filename = pathinfo($file['name'], PATHINFO_FILENAME) . "_{$counter}." . pathinfo($file['name'], PATHINFO_EXTENSION);
        $uploadPath = $uploadDir . $filename;
        $counter++;
    }

    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        throw new Exception("Error al guardar la imagen");
    }

    // URL completa
    $baseURL = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]/tienda/";
    $imagen_url = $baseURL . "uploads/{$type}/{$filename}";

    // Insertar producto en DB
    $stmt = $pdo->prepare("
        INSERT INTO productos (nombre, categoria_id, marca, precio, stock, descripcion, imagen_url, garantia, fecha_creacion)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $stmt->execute([
        $nombre,
        $categoria_id,
        $marca,
        $precio,
        $stock,
        $descripcion,
        $imagen_url,
        $garantia
    ]);

    $productId = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'message' => 'Producto agregado correctamente',
        'id' => intval($productId),
        'data' => [
            'nombre' => $nombre,
            'precio' => $precio,
            'stock' => $stock,
            'garantia' => $garantia,
            'imagen_url' => $imagen_url
        ]
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
