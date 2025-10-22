<?php
require_once 'config.php';
checkAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Error al subir la imagen']);
    exit();
}

$file = $_FILES['image'];
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$maxFileSize = 5 * 1024 * 1024; // 5MB

// Validar tipo de archivo
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime, $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Tipo de archivo no permitido']);
    exit();
}

// Validar tamaño
if ($file['size'] > $maxFileSize) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'El archivo es demasiado grande']);
    exit();
}

// Generar nombre único
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = uniqid() . '.' . $extension;
$type = $_POST['type'] ?? 'products';

// Validar tipo
if (!in_array($type, ['products', 'logos'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Tipo de upload no válido']);
    exit();
}

$uploadDir = "../../uploads/{$type}/";
$uploadPath = $uploadDir . $filename;

// Crear directorio si no existe
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Mover archivo
if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
    // Construir URL completa
    $baseURL = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]/tienda/";
    $fullURL = $baseURL . "uploads/{$type}/{$filename}";

    echo json_encode([
        'success' => true, 
        'message' => 'Imagen subida correctamente',
        'path' => $fullURL  // <-- Aquí ya es URL completa
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al guardar la imagen']);
}
?>
