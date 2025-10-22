<?php
// Habilitar CORS para permitir peticiones desde el frontend
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Manejar preflight request para CORS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Incluir configuración con ruta corregida
require_once __DIR__ . '/config.php';

// Verificar sesión y permisos
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado - Sesión no iniciada']);
    exit();
}

// Verificar rol de administrador (opcional, si tienes roles)
 $stmt = $pdo->prepare("SELECT rol FROM usuarios WHERE id = ?");
 $stmt->execute([$_SESSION['user_id']]);
 $user = $stmt->fetch();

if (!$user || ($user['rol'] !== 'admin' && $user['rol'] !== 'editor')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No autorizado - Permisos insuficientes']);
    exit();
}

// Solo permitir método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

// Obtener datos del POST
 $data = json_decode(file_get_contents('php://input'), true);

// Registrar para depuración (quitar en producción)
error_log("Datos recibidos: " . print_r($data, true));

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No se recibieron datos válidos']);
    exit();
}

try {
    // Validación básica de campos requeridos
    $required_fields = ['nombre', 'precio'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            throw new Exception("El campo '{$field}' es obligatorio");
        }
    }
    
    // Validar nombre
    $nombre = trim($data['nombre']);
    if (strlen($nombre) < 2) {
        throw new Exception('El nombre del producto debe tener al menos 2 caracteres');
    }
    
    // Validar precio
    if (!is_numeric($data['precio']) || floatval($data['precio']) <= 0) {
        throw new Exception('El precio debe ser un número mayor a 0');
    }
    $precio = floatval($data['precio']);
    
    // Validar stock (opcional)
    $stock = 0;
    if (isset($data['stock']) && $data['stock'] !== '') {
        if (!is_numeric($data['stock']) || intval($data['stock']) < 0) {
            throw new Exception('El stock debe ser un número mayor o igual a 0');
        }
        $stock = intval($data['stock']);
    }
    
    // Validar categoría (opcional)
    $categoria_id = null;
    if (!empty($data['categoria_id'])) {
        if (!is_numeric($data['categoria_id'])) {
            throw new Exception('ID de categoría inválido');
        }
        
        // Verificar que la categoría existe
        $stmt = $pdo->prepare("SELECT id FROM categorias WHERE id = ?");
        $stmt->execute([$data['categoria_id']]);
        if (!$stmt->fetch()) {
            throw new Exception('La categoría seleccionada no existe');
        }
        $categoria_id = intval($data['categoria_id']);
    }
    
    // Preparar datos adicionales
    $marca = !empty($data['marca']) ? trim($data['marca']) : null;
    $descripcion = !empty($data['descripcion']) ? trim($data['descripcion']) : null;
    $imagen_url = !empty($data['imagen_url']) ? trim($data['imagen_url']) : null;
   
    
    // Insertar producto
    $stmt = $pdo->prepare("
        INSERT INTO productos (nombre, categoria_id, marca, precio, stock, descripcion, imagen_url, fecha_creacion) 
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $result = $stmt->execute([
        $nombre,
        $categoria_id,
        $marca,
        $precio,
        $stock,
        $descripcion,
        $imagen_url
        
    ]);
    
    if (!$result) {
        throw new Exception('Error al insertar el producto en la base de datos');
    }
    
    // Obtener el ID del producto insertado
    $productId = $pdo->lastInsertId();
    
    // Retornar respuesta exitosa
    echo json_encode([
        'success' => true, 
        'message' => 'Producto agregado correctamente',
        'id' => intval($productId),
        'data' => [
            'nombre' => $nombre,
            'precio' => $precio,
            'stock' => $stock
        
        ]
    ]);

} catch (Exception $e) {
    // Registrar error para depuración
    error_log("Error en add_product.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage(),
        'debug' => 'Error interno del servidor'
    ]);
}
?>