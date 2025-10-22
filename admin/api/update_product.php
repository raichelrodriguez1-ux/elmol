<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/config.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

// Verificar rol
 $stmt = $pdo->prepare("SELECT rol FROM usuarios WHERE id = ?");
 $stmt->execute([$_SESSION['user_id']]);
 $user = $stmt->fetch();

if (!$user || ($user['rol'] !== 'admin' && $user['rol'] !== 'editor')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No autorizado - Permisos insuficientes']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

 $data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No se recibieron datos válidos']);
    exit();
}

if (!isset($data['id']) || empty($data['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID del producto no proporcionado']);
    exit();
}

try {
    // Validar que el producto existe
    $stmt = $pdo->prepare("SELECT id FROM productos WHERE id = ?");
    $stmt->execute([$data['id']]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Producto no encontrado']);
        exit();
    }
    
    // Validación básica
    $required_fields = ['nombre', 'precio', 'garantia'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            throw new Exception("El campo '{$field}' es obligatorio");
        }
    }
    
    $nombre = trim($data['nombre']);
    if (strlen($nombre) < 2) {
        throw new Exception('El nombre del producto debe tener al menos 2 caracteres');
    }
    
    $garantia = trim($data['garantia']);
    if (strlen($garantia) < 1) {
        throw new Exception('La garantía del producto es obligatoria');
    }
    
    if (!is_numeric($data['precio']) || floatval($data['precio']) <= 0) {
        throw new Exception('El precio debe ser un número mayor a 0');
    }
    $precio = floatval($data['precio']);
    
    $stock = 0;
    if (isset($data['stock']) && $data['stock'] !== '') {
        if (!is_numeric($data['stock']) || intval($data['stock']) < 0) {
            throw new Exception('El stock debe ser un número mayor o igual a 0');
        }
        $stock = intval($data['stock']);
    }
    
    $categoria_id = null;
    if (!empty($data['categoria_id'])) {
        if (!is_numeric($data['categoria_id'])) {
            throw new Exception('ID de categoría inválido');
        }
        
        $stmt = $pdo->prepare("SELECT id FROM categorias WHERE id = ?");
        $stmt->execute([$data['categoria_id']]);
        if (!$stmt->fetch()) {
            throw new Exception('La categoría seleccionada no existe');
        }
        $categoria_id = intval($data['categoria_id']);
    }
    
    $marca = !empty($data['marca']) ? trim($data['marca']) : null;
    $descripcion = !empty($data['descripcion']) ? trim($data['descripcion']) : null;
    $imagen_url = !empty($data['imagen_url']) ? trim($data['imagen_url']) : null;
    
    $stmt = $pdo->prepare("
        UPDATE productos 
        SET nombre = ?, categoria_id = ?, marca = ?, precio = ?, stock = ?, descripcion = ?, imagen_url = ?, garantia = ?
        WHERE id = ?
    ");
    
    $result = $stmt->execute([
        $nombre,
        $categoria_id,
        $marca,
        $precio,
        $stock,
        $descripcion,
        $imagen_url,
        $garantia,
        $data['id']
    ]);
    
    if (!$result) {
        throw new Exception('Error al actualizar el producto');
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Producto actualizado correctamente',
        'id' => intval($data['id']),
        'data' => [
            'nombre' => $nombre,
            'precio' => $precio,
            'stock' => $stock,
            'garantia' => $garantia
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>