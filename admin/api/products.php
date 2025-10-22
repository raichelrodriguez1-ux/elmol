<?php
// Activar todos los errores para desarrollo
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Asegurar que no haya salida antes de los headers
ob_start();

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Manejar preflight request para CORS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    ob_end_clean();
    exit();
}

// Función para enviar respuesta JSON
function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    ob_end_clean();
    echo json_encode($data);
    exit();
}

// Función para registrar y enviar error
function sendError($message, $statusCode = 500) {
    error_log("API Error: " . $message);
    sendJsonResponse([
        'success' => false,
        'message' => $message
    ], $statusCode);
}

try {
    // Iniciar sesión si no está iniciada
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Incluir configuración
    if (!file_exists(__DIR__ . '/../config.php')) {
        sendError('Archivo config.php no encontrado', 500);
    }
    
    require_once __DIR__ . '/../config.php';
    
    // Verificar que $pdo esté disponible
    if (!isset($pdo) || !$pdo) {
        sendError('Conexión a base de datos no establecida', 500);
    }
    
    // Verificar sesión
    if (!isset($_SESSION['user_id'])) {
        sendError('No autorizado - Sesión no iniciada', 401);
    }
    
    // Parámetros de paginación y filtros
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $category = isset($_GET['category']) ? (int)$_GET['category'] : 0;
    $stockFilter = isset($_GET['stock']) ? $_GET['stock'] : '';
    $sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'name';

    // Validar página
    if ($page < 1) $page = 1;
    if ($perPage < 1 || $perPage > 100) $perPage = 10;

    // Construir consulta base
    $sql = "SELECT p.*, c.nombre as categoria_nombre 
            FROM productos p 
            LEFT JOIN categorias c ON p.categoria_id = c.id";
    
    // Construir cláusulas WHERE
    $where = [];
    $params = [];
    
    if (!empty($search)) {
        $where[] = "(p.nombre LIKE ? OR p.descripcion LIKE ? OR p.marca LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    if ($category > 0) {
        $where[] = "p.categoria_id = ?";
        $params[] = $category;
    }
    
    if ($stockFilter === 'instock') {
        $where[] = "p.stock > 0";
    } elseif ($stockFilter === 'outofstock') {
        $where[] = "p.stock = 0";
    } elseif ($stockFilter === 'lowstock') {
        $where[] = "p.stock > 0 AND p.stock < 10";
    }
    
    if (!empty($where)) {
        $sql .= " WHERE " . implode(' AND ', $where);
    }
    
    // Ordenamiento
    switch($sortBy) {
        case 'price-asc':
            $sql .= " ORDER BY p.precio ASC";
            break;
        case 'price-desc':
            $sql .= " ORDER BY p.precio DESC";
            break;
        case 'stock':
            $sql .= " ORDER BY p.stock DESC";
            break;
        case 'name':
        default:
            $sql .= " ORDER BY p.nombre ASC";
            break;
    }
    
    // Calcular offset
    $offset = ($page - 1) * $perPage;
    
    // Construir consulta con paginación (sintaxis compatible con MariaDB/MySQL)
    $sql .= " LIMIT ?, ?";
    $params[] = $offset;
    $params[] = $perPage;
    
    // Debug: registrar la consulta y parámetros
    error_log("SQL Query: " . $sql);
    error_log("Parameters: " . print_r($params, true));
    
    // Preparar y ejecutar consulta
    $stmt = $pdo->prepare($sql);
    
    if (!$stmt) {
        sendError('Error preparando la consulta SQL', 500);
    }
    
    $result = $stmt->execute($params);
    
    if (!$result) {
        $errorInfo = $stmt->errorInfo();
        sendError('Error ejecutando la consulta: ' . $errorInfo[2], 500);
    }
    
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener total de productos para paginación
    $countSql = "SELECT COUNT(*) as total FROM productos p";
    if (!empty($where)) {
        $countSql .= " WHERE " . implode(' AND ', $where);
    }
    
    $countStmt = $pdo->prepare($countSql);
    $countParams = array_slice($params, 0, -2); // Excluir LIMIT y OFFSET
    
    if (!$countStmt) {
        sendError('Error preparando consulta de conteo', 500);
    }
    
    $countResult = $countStmt->execute($countParams);
    
    if (!$countResult) {
        $errorInfo = $countStmt->errorInfo();
        sendError('Error ejecutando consulta de conteo: ' . $errorInfo[2], 500);
    }
    
    $totalProducts = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $response = [
        'success' => true,
        'products' => $products,
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => (int)$totalProducts,
            'pages' => ceil($totalProducts / $perPage)
        ],
        'debug' => [
            'sql' => $sql,
            'params' => $params,
            'total_products' => $totalProducts
        ]
    ];
    
    sendJsonResponse($response);

} catch (PDOException $e) {
    sendError('Error de base de datos: ' . $e->getMessage() . ' (Código: ' . $e->getCode() . ')', 500);
} catch (Exception $e) {
    sendError('Error interno: ' . $e->getMessage() . ' (Línea: ' . $e->getLine() . ')', 500);
}
?>