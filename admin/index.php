<?php
// Evitar múltiples sesiones
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generar token CSRF si no existe
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Conexión a la base de datos
require_once 'config.php';
require_once 'cache.php';

// Inicializar caché
 $cache = new SimpleCache();

// Obtener información del usuario actual
 $currentUser = $cache->get('user_' . $_SESSION['user_id'], function() use ($pdo) {
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
});

// Obtener configuración de la tienda
 $storeSettings = $cache->get('store_settings', function() use ($pdo) {
    $stmt = $pdo->prepare("SELECT clave, valor FROM configuracion");
    $stmt->execute();
    $configRows = $stmt->fetchAll();
    $settings = [];
    foreach ($configRows as $row) {
        $settings[$row['clave']] = $row['valor'];
    }
    return $settings;
});

// Obtener horarios de la tienda
 $storeSchedule = $cache->get('store_schedule', function() use ($pdo) {
    $stmt = $pdo->prepare("SELECT * FROM horarios ORDER BY FIELD(dia_semana, 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo')");
    $stmt->execute();
    return $stmt->fetchAll();
});
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - <?php echo htmlspecialchars($storeSettings['store_name'] ?? 'Mi Tienda'); ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- SheetJS for Excel -->
    <script src="https://cdn.sheetjs.com/xlsx-0.20.0/package/dist/xlsx.full.min.js"></script>
    
    <style>
        :root {
            --primary-color: #1a3a52;
            --secondary-color: #0f2438;
            --accent-color: #051929;
            --sidebar-width: 280px;
            --gradient-primary: linear-gradient(135deg, #1a3a52 0%, #0f2438 50%, #051929 100%);
            --gradient-secondary: linear-gradient(135deg, #2c5282 0%, #1a365d 50%, #0f2438 100%);
            --gradient-accent: linear-gradient(135deg, #051929 0%, #0a1628 50%, #1a3a52 100%);
        }
        
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
        }
        
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #f0f4f8 0%, #e2e8f0 100%); 
            overflow-x: hidden;
            font-size: 16px;
        }
        
        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--gradient-primary);
            color: white;
            z-index: 1040;
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 10px 0 40px rgba(26, 58, 82, 0.3);
            display: flex;
            flex-direction: column;
        }
        
        .sidebar-header { 
            padding: 2rem 1.5rem; 
            text-align: center; 
            border-bottom: 1px solid rgba(255,255,255,0.15); 
            position: relative; 
            overflow: hidden; 
            flex-shrink: 0; 
        }
        
        .sidebar-header::before { 
            content: ''; 
            position: absolute; 
            top: -50%; 
            left: -50%; 
            width: 200%; 
            height: 200%; 
            background: radial-gradient(circle, rgba(255,255,255,0.05) 0%, transparent 70%); 
            animation: rotate 25s linear infinite; 
        }
        
        @keyframes rotate { 
            from { transform: rotate(0deg); } 
            to { transform: rotate(360deg); } 
        }
        
        .sidebar-menu {
            padding: 1rem 0;
            flex-grow: 1;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .sidebar-menu::-webkit-scrollbar { 
            width: 0px; 
            background: transparent; 
        }
        
        .sidebar-menu:hover::-webkit-scrollbar { 
            width: 6px; 
        }
        
        .sidebar-menu { 
            scrollbar-width: thin; 
            scrollbar-color: rgba(255, 255, 255, 0.3); 
        }
        
        .sidebar-item { 
            padding: 1rem 1.5rem; 
            display: flex; 
            align-items: center; 
            color: rgba(255,255,255,0.85); 
            text-decoration: none; 
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); 
            cursor: pointer; 
            position: relative; 
            margin: 0.25rem 0; 
            border-radius: 0 25px 25px 0; 
            white-space: nowrap; 
            overflow: hidden; 
            text-overflow: ellipsis;
            min-height: 50px;
        }
        
        .sidebar-item::before { 
            content: ''; 
            position: absolute; 
            left: 0; 
            top: 0; 
            bottom: 0; 
            width: 0; 
            background: var(--gradient-secondary); 
            transition: width 0.4s cubic-bezier(0.4, 0, 0.2, 1); 
            border-radius: 0 25px 25px 0; 
        }
        
        .sidebar-item:hover { 
            background: rgba(255,255,255,0.1); 
            color: white; 
            transform: translateX(5px); 
        }
        
        .sidebar-item:hover::before { 
            width: 4px; 
        }
        
        .sidebar-item.active { 
            background: rgba(255,255,255,0.15); 
            color: white; 
            border-left: 4px solid white; 
        }
        
        .sidebar-item i { 
            margin-right: 1rem; 
            width: 20px; 
            text-align: center; 
            flex-shrink: 0; 
            font-size: 1.1rem;
        }
        
        /* Main Content */
        .main-content { 
            margin-left: var(--sidebar-width); 
            min-height: 100vh; 
            transition: margin-left 0.4s cubic-bezier(0.4, 0, 0.2, 1); 
        }
        
        /* Top Bar */
        .top-bar { 
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%); 
            padding: 1rem 1.5rem; 
            box-shadow: 0 4px 20px rgba(26, 58, 82, 0.1); 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            backdrop-filter: blur(10px);
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .user-menu { 
            display: flex; 
            align-items: center; 
            gap: 1rem; 
        }
        
        .user-avatar { 
            width: 40px; 
            height: 40px; 
            border-radius: 50%; 
            background: var(--gradient-primary); 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            color: white; 
            font-weight: bold; 
            box-shadow: 0 5px 15px rgba(26, 58, 82, 0.3); 
            transition: transform 0.3s ease; 
            cursor: pointer; 
        }
        
        .user-avatar:hover { 
            transform: scale(1.1); 
        }
        
        /* Content Area */
        .content-area { 
            padding: 1.5rem; 
        }
        
        /* Stats Cards */
        .stats-card { 
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%); 
            border-radius: 15px; 
            padding: 1.5rem; 
            box-shadow: 0 10px 30px rgba(26, 58, 82, 0.1); 
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); 
            height: 100%; 
            position: relative; 
            overflow: hidden; 
            margin-bottom: 1.5rem;
        }
        
        .stats-card::before { 
            content: ''; 
            position: absolute; 
            top: 0; 
            left: 0; 
            right: 0; 
            height: 4px; 
            background: var(--gradient-primary); 
        }
        
        .stats-card:hover { 
            transform: translateY(-5px); 
            box-shadow: 0 20px 40px rgba(26, 58, 82, 0.2); 
        }
        
        .stats-icon { 
            width: 60px; 
            height: 60px; 
            border-radius: 15px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 1.5rem; 
            margin-bottom: 1rem; 
            box-shadow: 0 8px 25px rgba(0,0,0,0.15); 
            transition: transform 0.3s ease; 
        }
        
        .stats-icon:hover { 
            transform: scale(1.1) rotate(5deg); 
        }
        
        .stats-icon.primary { 
            background: var(--gradient-primary); 
            color: white; 
        }
        
        .stats-icon.danger { 
            background: linear-gradient(135deg, #f093fb, #f5576c); 
            color: white; 
        }
        
        .stats-icon.info { 
            background: linear-gradient(135deg, #4facfe, #00f2fe); 
            color: white; 
        }
        
        .stats-icon.success { 
            background: linear-gradient(135deg, #43e97b, #38f9d7); 
            color: white; 
        }
        
        .stats-number { 
            font-size: 2rem; 
            font-weight: bold; 
            color: var(--primary-color); 
            margin-bottom: 0.5rem; 
        }
        
        .stats-label { 
            color: #6c757d; 
            font-size: 0.9rem; 
            font-weight: 500; 
        }
        
        /* Charts */
        .chart-container { 
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%); 
            border-radius: 15px; 
            padding: 1.5rem; 
            box-shadow: 0 10px 30px rgba(26, 58, 82, 0.1); 
            margin-bottom: 1.5rem;
            position: relative;
            height: 300px;
            min-height: 250px;
        }

        .chart-container canvas {
            max-height: 100%;
            width: 100% !important;
        }

        @media (max-width: 768px) {
            .chart-container {
                height: 250px;
                min-height: 200px;
            }
        }

        @media (max-width: 480px) {
            .chart-container {
                height: 200px;
                min-height: 180px;
            }
        }

        .chart-wrapper {
            position: relative;
            width: 100%;
            height: 100%;
        }
        
        /* Tables */
        .table-container { 
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%); 
            border-radius: 15px; 
            padding: 1.5rem; 
            box-shadow: 0 10px 30px rgba(26, 58, 82, 0.1); 
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .table { 
            margin-top: 1rem; 
            min-width: 600px;
        }
        
        .table th { 
            border-top: none; 
            font-weight: 600; 
            color: var(--primary-color); 
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%); 
            padding: 0.75rem; 
            font-size: 0.9rem;
        }
        
        .table td { 
            padding: 0.75rem; 
            vertical-align: middle; 
            font-size: 0.9rem;
        }
        
        .table-hover tbody tr:hover { 
            background: rgba(26, 58, 82, 0.05); 
        }
        
        /* Forms */
        .form-container { 
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%); 
            border-radius: 15px; 
            padding: 1.5rem; 
            box-shadow: 0 10px 30px rgba(26, 58, 82, 0.1); 
        }
        
        .form-control { 
            border-radius: 10px; 
            border: 2px solid #e2e8f0; 
            padding: 12px 16px; 
            transition: all 0.3s ease; 
            background: #f8fafc; 
            font-size: 16px;
        }
        
        .form-control:focus { 
            border-color: var(--primary-color); 
            box-shadow: 0 0 0 0.25rem rgba(26, 58, 82, 0.25); 
            background: white; 
        }
        
        .form-select { 
            border-radius: 10px; 
            border: 2px solid #e2e8f0; 
            padding: 12px 16px; 
            transition: all 0.3s ease; 
            background: #f8fafc; 
            font-size: 16px;
        }
        
        .form-select:focus { 
            border-color: var(--primary-color); 
            box-shadow: 0 0 0 0.25rem rgba(26, 58, 82, 0.25); 
            background: white; 
        }
        
        /* Buttons */
        .btn-primary-custom { 
            background: var(--gradient-primary); 
            border: none; 
            border-radius: 10px; 
            padding: 0.75rem 1.5rem; 
            color: white; 
            font-weight: 600; 
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); 
            position: relative; 
            overflow: hidden; 
            box-shadow: 0 8px 25px rgba(26, 58, 82, 0.3); 
            font-size: 0.9rem;
            min-height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-primary-custom::before { 
            content: ''; 
            position: absolute; 
            top: 50%; 
            left: 50%; 
            width: 0; 
            height: 0; 
            background: rgba(255,255,255,0.2); 
            border-radius: 50%; 
            transition: all 0.6s ease; 
            transform: translate(-50%, -50%); 
        }
        
        .btn-primary-custom:hover::before { 
            width: 300px; 
            height: 300px; 
        }
        
        .btn-primary-custom:hover { 
            transform: translateY(-3px); 
            box-shadow: 0 12px 30px rgba(26, 58, 82, 0.4); 
            background: var(--gradient-secondary); 
        }
        
        .btn-sm { 
            padding: 0.5rem 1rem; 
            font-size: 0.8rem; 
            min-height: 36px;
        }
        
        /* Loading */
        .loading-overlay { 
            position: fixed; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%; 
            background: rgba(26, 58, 82, 0.9); 
            display: none; 
            justify-content: center; 
            align-items: center; 
            z-index: 2000; 
            backdrop-filter: blur(10px); 
        }
        
        .loading-overlay.active { 
            display: flex; 
        }
        
        /* Modal */
        .modal-content { 
            border-radius: 15px; 
            border: none; 
            box-shadow: 0 20px 60px rgba(26, 58, 82, 0.3); 
        }
        
        .modal-header { 
            background: var(--gradient-primary); 
            color: white; 
            border-radius: 15px 15px 0 0; 
            border: none; 
        }
        
        .btn-close { 
            filter: brightness(0) invert(1); 
        }
        
        /* Badge */
        .badge { 
            padding: 0.4rem 0.8rem; 
            border-radius: 50px; 
            font-weight: 500; 
            font-size: 0.8rem;
        }
        
        /* Alert */
        .alert { 
            border-radius: 10px; 
            border: none; 
            padding: 1rem 1.5rem; 
            margin-bottom: 1rem; 
        }
        
        .alert-success { 
            background: linear-gradient(135deg, #d4edda, #c3e6cb); 
            color: #155724; 
        }
        
        .alert-danger { 
            background: linear-gradient(135deg, #f8d7da, #f5c6cb); 
            color: #721c24; 
        }

        /* Category Management */
        .category-card { 
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%); 
            border-radius: 12px; 
            padding: 1.5rem; 
            margin-bottom: 1rem; 
            box-shadow: 0 5px 20px rgba(26, 58, 82, 0.1); 
            transition: all 0.3s ease; 
            border-left: 4px solid var(--primary-color); 
        }
        
        .category-card:hover { 
            transform: translateY(-3px); 
            box-shadow: 0 10px 30px rgba(26, 58, 82, 0.2); 
        }
        
        .category-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 1rem; 
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        .category-name { 
            font-size: 1.1rem; 
            font-weight: 600; 
            color: var(--primary-color); 
        }
        
        .category-actions { 
            display: flex; 
            gap: 0.5rem; 
        }
        
        .category-stats { 
            display: flex; 
            gap: 1.5rem; 
            margin-top: 1rem; 
            padding-top: 1rem; 
            border-top: 1px solid #e2e8f0; 
        }
        
        .category-stat { 
            text-align: center; 
        }
        
        .category-stat-number { 
            font-size: 1.3rem; 
            font-weight: bold; 
            color: var(--primary-color); 
        }
        
        .category-stat-label { 
            font-size: 0.8rem; 
            color: #6c757d; 
        }

        /* Schedule Management */
        .schedule-day { 
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%); 
            border-radius: 12px; 
            padding: 1.5rem; 
            margin-bottom: 1rem; 
            box-shadow: 0 5px 20px rgba(26, 58, 82, 0.1); 
            transition: all 0.3s ease; 
        }
        
        .schedule-day:hover { 
            transform: translateY(-3px); 
            box-shadow: 0 10px 30px rgba(26, 58, 82, 0.2); 
        }
        
        .schedule-day-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 1rem; 
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        .schedule-day-name { 
            font-weight: 600; 
            color: var(--primary-color); 
            font-size: 1rem; 
        }
        
        .schedule-day-status { 
            padding: 0.25rem 0.75rem; 
            border-radius: 20px; 
            font-size: 0.8rem; 
            font-weight: 500; 
        }
        
        .schedule-day-status.open { 
            background: linear-gradient(135deg, #28a745, #20c997); 
            color: white; 
        }
        
        .schedule-day-status.closed { 
            background: linear-gradient(135deg, #dc3545, #c82333); 
            color: white; 
        }
        
        .schedule-time-inputs { 
            display: flex; 
            align-items: center; 
            gap: 1rem; 
            flex-wrap: wrap;
        }
        
        .schedule-time-inputs input { 
            border-radius: 8px; 
            border: 2px solid #e2e8f0; 
            padding: 0.5rem; 
            transition: all 0.3s ease; 
            font-size: 16px;
        }
        
        .schedule-time-inputs input:focus { 
            border-color: var(--primary-color); 
            box-shadow: 0 0 0 0.2rem rgba(26, 58, 82, 0.25); 
        }

        /* File Upload */
        .file-upload-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }

        .file-upload-input {
            position: absolute;
            left: -9999px;
        }

        .file-upload-label {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border: 2px dashed #cbd5e0;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            min-height: 100px;
        }

        .file-upload-label:hover {
            border-color: var(--primary-color);
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        }

        .file-upload-preview {
            max-width: 100%;
            max-height: 200px;
            border-radius: 8px;
            margin-top: 1rem;
            display: none;
        }

        .file-upload-info {
            text-align: center;
            color: #6c757d;
        }

        .file-upload-name {
            margin-top: 0.5rem;
            font-size: 0.9rem;
            color: var(--primary-color);
            font-weight: 500;
        }

        /* Danger Zone */
        .danger-zone {
            background: linear-gradient(135deg, #fff5f5 0%, #fed7d7 100%);
            border: 2px solid #fc8181;
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 2rem;
        }

        .danger-zone h5 {
            color: #c53030;
            margin-bottom: 1rem;
        }

        .btn-danger-custom {
            background: linear-gradient(135deg, #e53e3e 0%, #c53030 100%);
            border: none;
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            color: white;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-danger-custom:hover {
            background: linear-gradient(135deg, #c53030 0%, #9b2c2c 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(229, 62, 62, 0.3);
        }
        
        /* Mobile Responsiveness */
        .mobile-toggle { 
            display: none; 
            position: fixed; 
            top: 1rem; 
            left: 1rem; 
            z-index: 1050; 
            background: var(--gradient-primary); 
            color: white; 
            border: none; 
            border-radius: 10px; 
            padding: 0.75rem; 
            font-size: 1.2rem; 
            cursor: pointer; 
            box-shadow: 0 5px 15px rgba(26, 58, 82, 0.3); 
            transition: all 0.3s ease; 
            min-width: 44px;
            min-height: 44px;
        }
        
        .mobile-toggle:hover { 
            transform: scale(1.1); 
        }
        
        .mobile-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1035;
            display: none;
            opacity: 0;
            transition: opacity 0.4s ease;
        }
        
        .mobile-overlay.active { 
            display: block; 
            opacity: 1; 
        }
        
        /* Tablets */
        @media (max-width: 992px) {
            .content-area { 
                padding: 1rem; 
            }
            
            .stats-card {
                padding: 1.25rem;
            }
            
            .chart-container {
                padding: 1.25rem;
            }
            
            .table-container {
                padding: 1.25rem;
            }
            
            .form-container {
                padding: 1.25rem;
            }
        }
        
        /* Mobile */
        @media (max-width: 768px) {
            body {
                font-size: 14px;
            }
            
            .sidebar { 
                transform: translateX(-100%); 
                width: 280px;
            }
            
            .sidebar.active { 
                transform: translateX(0); 
            }
            
            .main-content { 
                margin-left: 0; 
            }
            
            .mobile-toggle { 
                display: flex; 
                align-items: center;
                justify-content: center;
            }
            
            .content-area { 
                padding: 0.75rem; 
            }
            
            .top-bar {
                padding: 0.75rem 1rem;
                flex-direction: row;
                align-items: center;
            }
            
            .user-menu {
                gap: 0.5rem;
            }
            
            .user-avatar {
                width: 35px;
                height: 35px;
            }
            
            #userEmail {
                font-size: 0.8rem;
            }
            
            #userRole {
                font-size: 0.7rem;
            }
            
            .stats-card {
                padding: 1rem;
                margin-bottom: 1rem;
            }
            
            .stats-icon {
                width: 50px;
                height: 50px;
                font-size: 1.2rem;
                margin-bottom: 0.75rem;
            }
            
            .stats-number {
                font-size: 1.5rem;
            }
            
            .stats-label {
                font-size: 0.8rem;
            }
            
            .chart-container {
                padding: 1rem;
                margin-bottom: 1rem;
            }
            
            .table-container {
                padding: 1rem;
                border-radius: 10px;
            }
            
            .table {
                font-size: 0.8rem;
                min-width: 500px;
            }
            
            .table th,
            .table td {
                padding: 0.5rem;
            }
            
            .form-container {
                padding: 1rem;
            }
            
            .btn-primary-custom {
                padding: 0.6rem 1.2rem;
                font-size: 0.85rem;
            }
            
            .category-card {
                padding: 1rem;
            }
            
            .category-name {
                font-size: 1rem;
            }
            
            .category-stat-number {
                font-size: 1.1rem;
            }
            
            .schedule-day {
                padding: 1rem;
            }
            
            .schedule-time-inputs {
                gap: 0.5rem;
            }
            
            .modal-dialog {
                margin: 0.5rem;
            }
            
            .modal-content {
                border-radius: 10px;
            }
            
            .modal-body {
                padding: 1rem;
            }
            
            .modal-header {
                padding: 1rem;
            }
            
            .modal-title {
                font-size: 1.1rem;
            }
        }
        
        /* Small Mobile */
        @media (max-width: 480px) {
            body {
                font-size: 13px;
            }
            
            .sidebar {
                width: 100%;
            }
            
            .sidebar-header {
                padding: 1.5rem 1rem;
            }
            
            .sidebar-header i {
                font-size: 2rem;
            }
            
            .sidebar-header h4 {
                font-size: 1.2rem;
            }
            
            .sidebar-item {
                padding: 0.875rem 1rem;
                font-size: 0.9rem;
            }
            
            .sidebar-item i {
                font-size: 1rem;
                margin-right: 0.75rem;
            }
            
            .top-bar {
                padding: 0.5rem 0.75rem;
            }
            
            #pageTitle {
                font-size: 1rem;
            }
            
            #currentTime {
                font-size: 0.75rem;
                display: none;
            }
            
            .content-area {
                padding: 0.5rem;
            }
            
            .stats-card {
                padding: 0.75rem;
            }
            
            .stats-icon {
                width: 45px;
                height: 45px;
                font-size: 1.1rem;
            }
            
            .stats-number {
                font-size: 1.3rem;
            }
            
            .stats-label {
                font-size: 0.75rem;
            }
            
            .chart-container {
                padding: 0.75rem;
            }
            
            .chart-container h5 {
                font-size: 1rem;
            }
            
            .table-container {
                padding: 0.75rem;
            }
            
            .table {
                font-size: 0.75rem;
                min-width: 400px;
            }
            
            .form-container {
                padding: 0.75rem;
            }
            
            .form-container h4,
            .form-container h5 {
                font-size: 1rem;
            }
            
            .form-label {
                font-size: 0.85rem;
                margin-bottom: 0.25rem;
            }
            
            .form-control,
            .form-select {
                padding: 0.625rem 0.875rem;
                font-size: 0.9rem;
            }
            
            .btn-primary-custom {
                padding: 0.5rem 1rem;
                font-size: 0.8rem;
                width: 100%;
                margin-bottom: 0.5rem;
            }
            
            .btn-group .btn-primary-custom {
                width: auto;
            }
            
            .category-card {
                padding: 0.75rem;
            }
            
            .category-name {
                font-size: 0.95rem;
            }
            
            .category-description {
                font-size: 0.8rem;
            }
            
            .category-stat-number {
                font-size: 1rem;
            }
            
            .category-stat-label {
                font-size: 0.75rem;
            }
            
            .schedule-day {
                padding: 0.75rem;
            }
            
            .schedule-day-name {
                font-size: 0.9rem;
            }
            
            .schedule-day-status {
                font-size: 0.75rem;
                padding: 0.2rem 0.5rem;
            }
            
            .schedule-time-inputs {
                flex-direction: column;
                align-items: stretch;
                gap: 0.5rem;
            }
            
            .schedule-time-inputs span {
                display: none;
            }
            
            .form-check {
                margin-top: 0.5rem;
            }
            
            .modal-dialog {
                margin: 0;
                height: 100vh;
            }
            
            .modal-content {
                height: 100vh;
                border-radius: 0;
            }
            
            .modal-body {
                padding: 0.75rem;
            }
            
            .modal-header {
                padding: 0.75rem;
            }
            
            .modal-title {
                font-size: 1rem;
            }
            
            .row {
                margin: 0;
            }
            
            .col-md-6,
            .col-md-8,
            .col-md-4,
            .col-md-3 {
                padding: 0.25rem;
            }
        }
        
        /* Extra Small Mobile */
        @media (max-width: 360px) {
            .sidebar-header {
                padding: 1rem 0.75rem;
            }
            
            .sidebar-header i {
                font-size: 1.8rem;
            }
            
            .sidebar-header h4 {
                font-size: 1.1rem;
            }
            
            .sidebar-header small {
                font-size: 0.75rem;
            }
            
            .sidebar-item {
                padding: 0.75rem 0.875rem;
                font-size: 0.85rem;
            }
            
            .stats-card {
                padding: 0.625rem;
            }
            
            .stats-icon {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }
            
            .stats-number {
                font-size: 1.2rem;
            }
            
            .form-control,
            .form-select {
                padding: 0.5rem 0.75rem;
                font-size: 0.85rem;
            }
        }
        
        /* Touch device optimizations */
        @media (hover: none) and (pointer: coarse) {
            .sidebar-item:hover {
                transform: none;
            }
            
            .stats-card:hover {
                transform: none;
            }
            
            .category-card:hover {
                transform: none;
            }
            
            .schedule-day:hover {
                transform: none;
            }
            
            .btn-primary-custom:hover {
                transform: none;
            }
            
            .user-avatar:hover {
                transform: none;
            }
        }
        
        /* Landscape mobile */
        @media (max-height: 500px) and (orientation: landscape) {
            .sidebar-header {
                padding: 1rem;
            }
            
            .sidebar-header i {
                font-size: 1.5rem;
                margin-bottom: 0.5rem;
            }
            
            .sidebar-header h4 {
                font-size: 1rem;
                margin-bottom: 0.25rem;
            }
            
            .sidebar-item {
                padding: 0.625rem 1rem;
            }
        }
        
        /* Notification System */
        #notificationContainer {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 350px;
        }
        
        .notification {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            margin-bottom: 10px;
            padding: 15px;
            display: flex;
            align-items: center;
            animation: slideInRight 0.3s ease-out;
            border-left: 4px solid #17a2b8;
            transform: translateX(0);
            opacity: 1;
            transition: transform 0.3s ease, opacity 0.3s ease;
        }
        
        .notification.hide {
            transform: translateX(100%);
            opacity: 0;
        }
        
        .notification-success {
            border-left-color: #28a745;
        }
        
        .notification-error {
            border-left-color: #dc3545;
        }
        
        .notification-warning {
            border-left-color: #ffc107;
        }
        
        .notification-content {
            display: flex;
            align-items: center;
            gap: 12px;
            width: 100%;
        }
        
        .notification-icon {
            font-size: 20px;
            color: #17a2b8;
        }
        
        .notification-success .notification-icon {
            color: #28a745;
        }
        
        .notification-error .notification-icon {
            color: #dc3545;
        }
        
        .notification-warning .notification-icon {
            color: #ffc107;
        }
        
        .notification-message {
            flex: 1;
        }
        
        .notification-text {
            font-weight: 500;
            margin-bottom: 2px;
        }
        
        .notification-time {
            font-size: 12px;
            color: #6c757d;
        }
        
        .notification-close {
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            padding: 0;
            font-size: 16px;
        }
        
        .notification-close:hover {
            color: #000;
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        /* Loading states */
        .loading {
            position: relative;
            pointer-events: none;
            opacity: 0.6;
        }
        
        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Mobile Overlay -->
    <div class="mobile-overlay" id="mobileOverlay"></div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner-border text-light" role="status">
            <span class="visually-hidden">Cargando...</span>
        </div>
    </div>
    
    <!-- Mobile Toggle -->
    <button class="mobile-toggle" id="mobileToggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-store" style="font-size: 2.5rem; margin-bottom: 0.8rem;"></i>
            <h4>Panel Admin</h4>
            <small>Bienvenido, <span id="usernameDisplay"><?php echo htmlspecialchars($currentUser['nombre_completo'] ?? $currentUser['usuario']); ?></span></small>
        </div>
        <nav class="sidebar-menu">
            <a class="sidebar-item active" data-page="dashboard" data-permission="all">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a class="sidebar-item" data-page="products" data-permission="all">
                <i class="fas fa-box"></i>
                <span>Productos</span>
            </a>
            <a class="sidebar-item" data-page="categories" data-permission="admin">
                <i class="fas fa-tags"></i>
                <span>Categorías</span>
            </a>
            <a class="sidebar-item" data-page="customers" data-permission="admin">
                <i class="fas fa-users"></i>
                <span>Clientes</span>
            </a>
            <a class="sidebar-item" data-page="orders" data-permission="admin">
                <i class="fas fa-shopping-cart"></i>
                <span>Pedidos</span>
            </a>
            <a class="sidebar-item" data-page="users" data-permission="admin">
                <i class="fas fa-user-shield"></i>
                <span>Gestión de Usuarios</span>
            </a>
            <a class="sidebar-item" data-page="profile" data-permission="all">
                <i class="fas fa-user-circle"></i>
                <span>Mi Perfil</span>
            </a>
            <a class="sidebar-item" data-page="settings" data-permission="admin">
                <i class="fas fa-cog"></i>
                <span>Configuración</span>
            </a>
            <a class="sidebar-item" data-page="schedule" data-permission="admin">
                <i class="fas fa-clock"></i>
                <span>Horarios</span>
            </a>
            <a class="sidebar-item" href="../../index.html" target="_blank" data-permission="all">
                <i class="fas fa-external-link-alt"></i>
                <span>Ver Tienda</span>
            </a>
            <a class="sidebar-item" id="logoutBtn" data-permission="all">
                <i class="fas fa-sign-out-alt"></i>
                <span>Cerrar Sesión</span>
            </a>
        </nav>
    </aside>
    
    <!-- Main Content -->
    <main class="main-content">
        <header class="top-bar">
            <div>
                <h5 id="pageTitle">Dashboard</h5>
            </div>
            <div class="user-menu">
                <span id="currentTime"></span>
                <div class="dropdown">
                    <div class="user-avatar" data-bs-toggle="dropdown">
                        <i class="fas fa-user"></i>
                    </div>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item" href="#" onclick="showPage('profile')">
                                <i class="fas fa-user-circle me-2"></i>Mi Perfil
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="#" onclick="logout()">
                                <i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión
                            </a>
                        </li>
                    </ul>
                </div>
                <div>
                    <div id="userEmail"><?php echo htmlspecialchars($currentUser['email']); ?></div>
                    <small class="text-muted" id="userRole"><?php echo ucfirst($currentUser['rol']); ?></small>
                </div>
            </div>
        </header>
        
        <div class="content-area">
            <!-- Dashboard Page -->
            <div id="dashboardPage" class="page-content">
                <div class="row mb-4">
                    <div class="col-md-3 col-sm-6">
                        <div class="stats-card">
                            <div class="stats-icon primary">
                                <i class="fas fa-box"></i>
                            </div>
                            <div class="stats-number" id="totalProducts">0</div>
                            <div class="stats-label">Total Productos</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="stats-card">
                            <div class="stats-icon danger">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                            <div class="stats-number" id="totalOrders">0</div>
                            <div class="stats-label">Total Pedidos</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="stats-card">
                            <div class="stats-icon info">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stats-number" id="totalCustomers">0</div>
                            <div class="stats-label">Total Clientes</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="stats-card">
                            <div class="stats-icon success">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                            <div class="stats-number" id="totalRevenue">$0</div>
                            <div class="stats-label">Ingresos Totales</div>
                        </div>
                    </div>
                </div>
                
                <!-- Reportes Avanzados -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5>Reportes Avanzados</h5>
                            <div class="btn-group">
                                <button class="btn btn-sm btn-outline-primary" onclick="generateReport('sales')">
                                    <i class="fas fa-chart-line me-1"></i>Reporte de Ventas
                                </button>
                                <button class="btn btn-sm btn-outline-success" onclick="generateReport('products')">
                                    <i class="fas fa-box me-1"></i>Reporte de Productos
                                </button>
                                <button class="btn btn-sm btn-outline-info" onclick="generateReport('customers')">
                                    <i class="fas fa-users me-1"></i>Reporte de Clientes
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-8">
                        <div class="chart-container">
                            <h5 class="mb-4">Pedidos por Mes</h5>
                            <div class="chart-wrapper">
                                <canvas id="ordersChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="chart-container">
                            <h5 class="mb-4">Productos por Categoría</h5>
                            <div class="chart-wrapper">
                                <canvas id="categoriesChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="chart-container">
                            <h5 class="mb-4">Tendencia de Ventas</h5>
                            <div class="chart-wrapper">
                                <canvas id="salesTrendChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="chart-container">
                            <h5 class="mb-4">Productos Más Vendidos</h5>
                            <div class="chart-wrapper">
                                <canvas id="topProductsChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="chart-container">
                            <h5 class="mb-4">Ingresos Mensuales</h5>
                            <div class="chart-wrapper">
                                <canvas id="revenueChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Products Page -->
            <div id="productsPage" class="page-content" style="display: none;">
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <h4>Gestión de Productos</h4>
                    <div class="btn-group d-flex flex-wrap gap-2">
                        <button class="btn btn-primary-custom" onclick="exportToExcel()">
                            <i class="fas fa-download me-2"></i>Exportar Excel
                        </button>
                        <button class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addProductModal">
                            <i class="fas fa-plus me-2"></i>Agregar Producto
                        </button>
                    </div>
                </div>
                <div class="table-container">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" class="form-control" id="productSearch" placeholder="Buscar productos...">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" id="categoryFilter">
                                <option value="">Todas las categorías</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" id="stockFilter">
                                <option value="">Todos los stocks</option>
                                <option value="instock">Con stock</option>
                                <option value="outofstock">Sin stock</option>
                                <option value="lowstock">Stock bajo (< 10)</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" id="sortBy">
                                <option value="name">Nombre</option>
                                <option value="price-asc">Precio: Menor a Mayor</option>
                                <option value="price-desc">Precio: Mayor a Menor</option>
                                <option value="stock">Stock</option>
                            </select>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Imagen</th>
                                    <th>Nombre</th>
                                    <th>Categoría</th>
                                    <th>Marca</th>
                                    <th>Precio</th>
                                    <th>Stock</th>
                                    <th>Garantía</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="productsTableBody"></tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div class="text-muted" id="productCount">Mostrando 0 productos</div>
                        <nav>
                            <ul class="pagination pagination-sm mb-0" id="productPagination"></ul>
                        </nav>
                    </div>
                </div>
            </div>

            <!-- Categories Page -->
            <div id="categoriesPage" class="page-content" style="display: none;">
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <h4>Gestión de Categorías</h4>
                    <button class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                        <i class="fas fa-plus me-2"></i>Agregar Categoría
                    </button>
                </div>
                <div id="categoriesList"></div>
            </div>
            
            <!-- Customers Page -->
            <div id="customersPage" class="page-content" style="display: none;">
                <h4 class="mb-4">Gestión de Clientes</h4>
                <div class="table-container">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Cliente</th>
                                    <th>Email</th>
                                    <th>Teléfono</th>
                                    <th>Pedidos</th>
                                    <th>Total Gastado</th>
                                    <th>Último Pedido</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="customersTableBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Orders Page -->
            <div id="ordersPage" class="page-content" style="display: none;">
                <h4 class="mb-4">Gestión de Pedidos</h4>
                <div class="table-container">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Cliente</th>
                                    <th>Fecha</th>
                                    <th>Total</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="ordersTableBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Users Page -->
            <div id="usersPage" class="page-content" style="display: none;">
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <h4>Gestión de Usuarios</h4>
                    <button class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="fas fa-plus me-2"></i>Agregar Usuario
                    </button>
                </div>
                <div class="table-container">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Usuario</th>
                                    <th>Nombre Completo</th>
                                    <th>Email</th>
                                    <th>Rol</th>
                                    <th>Fecha de Creación</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="usersTableBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Profile Page -->
            <div id="profilePage" class="page-content" style="display: none;">
                <div class="row">
                    <div class="col-md-8">
                        <div class="form-container">
                            <h4 class="mb-4">Mi Perfil</h4>
                            <form id="profileForm">
                                <!-- Token CSRF -->
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                
                                <div class="mb-3">
                                    <label for="profileNombre" class="form-label">Nombre Completo</label>
                                    <input type="text" class="form-control" id="profileNombre" value="<?php echo htmlspecialchars($currentUser['nombre_completo']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="profileEmail" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="profileEmail" value="<?php echo htmlspecialchars($currentUser['email']); ?>" required>
                                </div>
                                <hr>
                                <h5 class="mb-3">Cambiar Contraseña</h5>
                                <div class="mb-3">
                                    <label for="profileCurrentPassword" class="form-label">Contraseña Actual</label>
                                    <input type="password" class="form-control" id="profileCurrentPassword">
                                </div>
                                <div class="mb-3">
                                    <label for="profileNewPassword" class="form-label">Nueva Contraseña</label>
                                    <input type="password" class="form-control" id="profileNewPassword">
                                </div>
                                <div class="mb-3">
                                    <label for="profileConfirmPassword" class="form-label">Confirmar Nueva Contraseña</label>
                                    <input type="password" class="form-control" id="profileConfirmPassword">
                                </div>
                                <button type="submit" class="btn btn-primary-custom">Guardar Cambios</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Settings Page -->
            <div id="settingsPage" class="page-content" style="display: none;">
                <h4 class="mb-4">Configuración de la Tienda</h4>
                <div class="row">
                    <div class="col-md-8">
                        <div class="form-container">
                            <h5 class="mb-4">Información Básica</h5>
                            <form id="storeSettingsForm">
                                <!-- Token CSRF -->
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                
                                <div class="mb-3">
                                    <label for="storeName" class="form-label">Nombre de la Tienda</label>
                                    <input type="text" class="form-control" id="storeName" value="<?php echo htmlspecialchars($storeSettings['store_name'] ?? ''); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="storeLogo" class="form-label">Logo de la Tienda</label>
                                    <div class="file-upload-wrapper">
                                        <label for="storeLogoFile" class="file-upload-label">
                                            <div class="file-upload-info">
                                                <i class="fas fa-cloud-upload-alt fa-2x mb-2"></i>
                                                <p>Click para subir o arrastra una imagen</p>
                                                <div class="file-upload-name" id="storeLogoName">Ningún archivo seleccionado</div>
                                            </div>
                                        </label>
                                        <input type="file" id="storeLogoFile" class="file-upload-input" accept="image/*">
                                        <img id="storeLogoPreview" class="file-upload-preview" src="<?php echo !empty($storeSettings['store_logo']) ? '../' . htmlspecialchars($storeSettings['store_logo']) : ''; ?>" alt="Vista previa del logo">
                                    </div>
                                    <input type="hidden" id="storeLogo" value="<?php echo htmlspecialchars($storeSettings['store_logo'] ?? ''); ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="whatsappNumber" class="form-label">Número de WhatsApp</label>
                                    <input type="text" class="form-control" id="whatsappNumber" value="<?php echo htmlspecialchars($storeSettings['whatsapp_number'] ?? ''); ?>" required>
                                    <small class="form-text text-muted">Incluye el código de país (ej: +5491112345678).</small>
                                </div>
                                <div class="mb-3">
                                    <label for="currency" class="form-label">Moneda</label>
                                    <select class="form-select" id="currency">
                                        <option value="ARS" <?php echo ($storeSettings['currency'] ?? '') == 'ARS' ? 'selected' : ''; ?>>ARS - Peso Argentino</option>
                                        <option value="USD" <?php echo ($storeSettings['currency'] ?? '') == 'USD' ? 'selected' : ''; ?>>USD - Dólar Americano</option>
                                        <option value="EUR" <?php echo ($storeSettings['currency'] ?? '') == 'EUR' ? 'selected' : ''; ?>>EUR - Euro</option>
                                        <option value="MXN" <?php echo ($storeSettings['currency'] ?? '') == 'MXN' ? 'selected' : ''; ?>>MXN - Peso Mexicano</option>
                                        <option value="CUP" <?php echo ($storeSettings['currency'] ?? '') == 'CUP' ? 'selected' : ''; ?>>CUP - Peso Cubano</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary-custom">Guardar Cambios</button>
                            </form>
                        </div>

                        <div class="form-container mt-4">
                            <h5 class="mb-4">Gestión de Base de Datos</h5>
                            <div class="d-flex flex-wrap gap-2">
                                <button class="btn btn-primary-custom" onclick="exportDatabase()">
                                    <i class="fas fa-download me-2"></i>Exportar Base de Datos
                                </button>
                                <button class="btn btn-danger-custom" onclick="showCleanDatabaseModal()">
                                    <i class="fas fa-trash-alt me-2"></i>Limpiar Base de Datos
                                </button>
                            </div>
                            <small class="form-text text-muted mt-2 d-block">
                                <strong>Exportar:</strong> Descarga una copia completa de la base de datos en formato JSON.<br>
                                <strong>Limpiar:</strong> Elimina todos los datos excepto usuarios y configuración. Esta acción no se puede deshacer.
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Schedule Page -->
            <div id="schedulePage" class="page-content" style="display: none;">
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                    <h4>Configuración de Horarios</h4>
                    <div class="btn-group d-flex flex-wrap gap-2">
                        <button class="btn btn-secondary" onclick="resetSchedule()">
                            <i class="fas fa-undo me-2"></i>Restablecer
                        </button>
                        <button class="btn btn-primary-custom" onclick="saveAllSchedule()">
                            <i class="fas fa-save me-2"></i>Guardar Todos los Horarios
                        </button>
                    </div>
                </div>
                <div id="scheduleList"></div>
            </div>
        </div>
    </main>
    
    <!-- Modals -->
    <div class="modal fade" id="addProductModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Agregar Producto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addProductForm">
                        <!-- Token CSRF -->
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nombre</label>
                                <input type="text" class="form-control" id="productName" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Categoría</label>
                                <select class="form-select" id="productCategory" required>
                                    <option value="">Seleccionar</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Marca</label>
                                <input type="text" class="form-control" id="productBrand" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Precio</label>
                                <input type="number" class="form-control" id="productPrice" step="0.01" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Stock</label>
                                <input type="number" class="form-control" id="productStock" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Garantía</label>
                                <input type="text" class="form-control" id="productWarranty" required placeholder="Ej: 6 Meses">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Descripción</label>
                            <textarea class="form-control" id="productDescription" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Imagen del Producto</label>
                            <div class="file-upload-wrapper">
                                <label for="productImageFile" class="file-upload-label">
                                    <div class="file-upload-info">
                                        <i class="fas fa-cloud-upload-alt fa-2x mb-2"></i>
                                        <p>Click para subir o arrastra una imagen</p>
                                        <div class="file-upload-name" id="productImageName">Ningún archivo seleccionado</div>
                                    </div>
                                </label>
                                <input type="file" id="productImageFile" class="file-upload-input" accept="image/*">
                                <img id="productImagePreview" class="file-upload-preview" src="" alt="Vista previa de la imagen">
                            </div>
                            <input type="hidden" id="productImage">
                        </div>
                        <button type="submit" class="btn btn-primary-custom">Agregar Producto</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Agregar Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addUserForm">
                        <!-- Token CSRF -->
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        
                        <div class="mb-3">
                            <label for="newUserUsername" class="form-label">Usuario</label>
                            <input type="text" class="form-control" id="newUserUsername" name="newUserUsername" required>
                        </div>
                        <div class="mb-3">
                            <label for="newUserNombre" class="form-label">Nombre Completo</label>
                            <input type="text" class="form-control" id="newUserNombre" name="newUserNombre" required>
                        </div>
                        <div class="mb-3">
                            <label for="newUserEmail" class="form-label">Email</label>
                            <input type="email" class="form-control" id="newUserEmail" name="newUserEmail" required>
                        </div>
                        <div class="mb-3">
                            <label for="newUserRole" class="form-label">Rol</label>
                            <select class="form-select" id="newUserRole" name="newUserRole" required>
                                <option value="editor">Editor</option>
                                <option value="admin">Administrador</option>
                            </select>
                        </div>
                        <div>
                            <div class="mb-3">
                                <label for="newUserPassword" class="form-label">Contraseña</label>
                                <input type="password" class="form-control" id="newUserPassword" name="newUserPassword" required>
                            </div>
                            <button type="submit" class="btn btn-primary-custom">Agregar Usuario</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Agregar Categoría</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addCategoryForm">
                        <!-- Token CSRF -->
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        
                        <div class="mb-3">
                            <label for="categoryName" class="form-label">Nombre de la Categoría</label>
                            <input type="text" class="form-control" id="categoryName" required>
                        </div>
                        <div class="mb-3">
                            <label for="categoryDescription" class="form-label">Descripción</label>
                            <textarea class="form-control" id="categoryDescription" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="categoryColor" class="form-label">Color (opcional)</label>
                            <input type="color" class="form-control" id="categoryColor" value="#007bff">
                        </div>
                        <button type="submit" class="btn btn-primary-custom">Agregar Categoría</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Clean Database Modal -->
    <div class="modal fade" id="cleanDatabaseModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">⚠️ Confirmar Limpieza de Base de Datos</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger" role="alert">
                        <strong>¡ADVERTENCIA!</strong> Esta acción eliminará permanentemente todos los datos de la base de datos excepto:
                        <ul class="mb-0 mt-2">
                            <li>Usuarios registrados</li>
                            <li>Configuración de la tienda</li>
                        </ul>
                    </div>
                    
                    <p>Esta acción <strong>no se puede deshacer</strong>. Se eliminarán:</p>
                    <ul>
                        <li>Todos los productos</li>
                        <li>Todas las categorías</li>
                        <li>Todos los clientes</li>
                        <li>Todos los pedidos</li>
                        <li>Todos los detalles de pedidos</li>
                        <li>Configuración de horarios</li>
                    </ul>
                    
                    <div class="form-group mt-3">
                        <label for="confirmText" class="form-label">Para confirmar, escribe <code>LIMPIAR_BASE_DE_DATOS</code> exactamente como se muestra:</label>
                        <input type="text" class="form-control" id="confirmText" placeholder="LIMPIAR_BASE_DE_DATOS">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" id="confirmCleanBtn" disabled>Confirmar Limpieza</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        
async function testConnection() {
    try {
        console.log('Probando conexión con el servidor...');
        
        // Probar conexión básica
        const response = await fetch('api/get_categories.php');
        console.log('Respuesta del servidor:', response.status, response.statusText);
        
        if (response.ok) {
            const data = await response.json();
            console.log('Datos recibidos:', data);
            showToast('Conexión exitosa con el servidor', 'success');
        } else {
            console.error('Error en la respuesta:', response.status);
            showToast(`Error del servidor: ${response.status}`, 'error');
        }
    } catch (error) {
        console.error('Error de conexión:', error);
        showToast('Error de conexión: ' + error.message, 'error');
    }
}

// Ejecutar prueba al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    testConnection();
});
        // Global Variables
        let products = [], categories = [], orders = [], customers = [], users = [], storeSettings = {}, storeSchedule = [];
        let currentUser = <?php echo json_encode($currentUser); ?>;
        let isLoadingData = false;
        let filteredProducts = [];
        let currentPage = 1;
        const productsPerPage = 10;
        
        // --- NOTIFICATION SYSTEM ---
        class NotificationSystem {
            constructor() {
                this.notifications = [];
                this.container = null;
                this.init();
            }
            
            init() {
                // Crear contenedor de notificaciones
                this.container = document.createElement('div');
                this.container.id = 'notificationContainer';
                document.body.appendChild(this.container);
            }
            
            addNotification(message, type = 'info', duration = 5000) {
                const notification = {
                    id: Date.now(),
                    message,
                    type,
                    timestamp: new Date()
                };
                
                this.notifications.push(notification);
                this.showNotification(notification);
                
                // Auto-eliminar después del tiempo especificado
                if (duration > 0) {
                    setTimeout(() => {
                        this.removeNotification(notification.id);
                    }, duration);
                }
                
                return notification.id;
            }
            
            showNotification(notification) {
                const element = document.createElement('div');
                element.className = `notification notification-${notification.type}`;
                element.dataset.id = notification.id;
                
                const icons = {
                    success: 'fa-check-circle',
                    error: 'fa-exclamation-circle',
                    warning: 'fa-exclamation-triangle',
                    info: 'fa-info-circle'
                };
                
                element.innerHTML = `
                    <div class="notification-content">
                        <div class="notification-icon">
                            <i class="fas ${icons[notification.type]}"></i>
                        </div>
                        <div class="notification-message">
                            <div class="notification-text">${notification.message}</div>
                            <div class="notification-time">${this.formatTime(notification.timestamp)}</div>
                        </div>
                        <button class="notification-close" onclick="notificationSystem.removeNotification(${notification.id})">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `;
                
                this.container.appendChild(element);
                
                // Animación de entrada
                setTimeout(() => element.classList.add('show'), 10);
            }
            
            removeNotification(id) {
                const element = this.container.querySelector(`[data-id="${id}"]`);
                if (element) {
                    element.classList.add('hide');
                    setTimeout(() => {
                        element.remove();
                    }, 300);
                }
                
                this.notifications = this.notifications.filter(n => n.id !== id);
            }
            
            formatTime(date) {
                return new Date(date).toLocaleTimeString('es-ES', {
                    hour: '2-digit',
                    minute: '2-digit'
                });
            }
        }
        
        // Inicializar sistema de notificaciones
        const notificationSystem = new NotificationSystem();
        
        // Reemplazar la función showToast existente
        function showToast(message, type = 'info') {
            return notificationSystem.addNotification(message, type);
        }
        
        // --- ADMIN PANEL MODULE ---
        const AdminPanel = {
            // Módulo de productos
            products: {
                init: function() {
                    this.bindEvents();
                    this.loadProducts();
                },
                
                bindEvents: function() {
                    document.getElementById('productSearch').addEventListener('input', this.filterProducts);
                    document.getElementById('categoryFilter').addEventListener('change', this.filterProducts);
                    document.getElementById('stockFilter').addEventListener('change', this.filterProducts);
                    document.getElementById('sortBy').addEventListener('change', this.filterProducts);
                },
                
                loadProducts: async function(page = 1) {
                    try {
                        showLoading(true);
                        
                        const search = document.getElementById('productSearch').value;
                        const category = document.getElementById('categoryFilter').value;
                        const stock = document.getElementById('stockFilter').value;
                        const sort = document.getElementById('sortBy').value;
                        
                        const params = new URLSearchParams({
                            page: page,
                            search: search,
                            category: category,
                            stock: stock,
                            sort: sort
                        });
                        
                        const response = await fetch(`api/products.php?${params}`);
                        
                        if (!response.ok) {
                            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                        }
                        
                        const data = await response.json();
                        
                        if (data.success && data.products) {
                            products = data.products;
                            this.renderProducts(data.products);
                            this.renderPagination(data.pagination);
                        } else {
                            showToast(data.message || 'Error al cargar productos', 'error');
                        }
                    } catch (error) {
                        console.error('Error loading products:', error);
                        showToast('Error de conexión al cargar productos: ' + error.message, 'error');
                    } finally {
                        showLoading(false);
                    }
                },
                
                renderProducts: function(products) {
                    const tbody = document.getElementById('productsTableBody');
                    if (!tbody) return;
                    
                    tbody.innerHTML = '';
                    
                    if (products.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="8" class="text-center">No hay productos que coincidan con los filtros</td></tr>';
                        return;
                    }
                    
                    products.forEach(product => {
                        const row = document.createElement('tr');
                        
                        // Determinar imagen a mostrar
                        let imageSrc = 'https://picsum.photos/seed/product' + product.id + '/50/50.jpg';
                        if (product.imagen_url) {
                            if (product.imagen_url.startsWith('http')) {
                                imageSrc = product.imagen_url;
                            } else {
                                imageSrc = '../' + product.imagen_url;
                            }
                        }
                        
                        // Determinar estado del stock
                        let stockBadge = '';
                        if (product.stock <= 0) {
                            stockBadge = '<span class="badge bg-danger">Sin stock</span>';
                        } else if (product.stock < 10) {
                            stockBadge = '<span class="badge bg-warning">Stock bajo</span>';
                        } else {
                            stockBadge = '<span class="badge bg-success">En stock</span>';
                        }
                        
                        row.innerHTML = `
                            <td><img src="${imageSrc}" width="50" height="50" style="object-fit: cover; border-radius: 10px;" onerror="this.src='https://picsum.photos/seed/product${product.id}/50/50.jpg'"></td>
                            <td>
                                <div class="fw-bold">${product.nombre}</div>
                                <small class="text-muted">${product.descripcion ? product.descripcion.substring(0, 50) + '...' : ''}</small>
                            </td>
                            <td>${product.categoria_nombre || 'N/A'}</td>
                            <td>${product.marca || 'N/A'}</td>
                            <td>
                                <div class="fw-bold text-primary">$${parseFloat(product.precio).toFixed(2)}</div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span>${product.stock}</span>
                                    ${stockBadge}
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-info">${product.garantia || 'N/A'}</span>
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <button class="btn btn-sm btn-outline-primary" onclick="AdminPanel.products.editProduct(${product.id})" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="AdminPanel.products.deleteProduct(${product.id})" title="Eliminar">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        `;
                        tbody.appendChild(row);
                    });
                    
                    // Actualizar contador
                    document.getElementById('productCount').textContent = 
                        `Mostrando ${products.length} productos`;
                },
                
                renderPagination: function(pagination) {
                    const paginationElement = document.getElementById('productPagination');
                    if (!paginationElement) return;
                    
                    paginationElement.innerHTML = '';
                    
                    if (pagination.pages <= 1) return;
                    
                    // Botón anterior
                    const prevLi = document.createElement('li');
                    prevLi.className = `page-item ${pagination.page <= 1 ? 'disabled' : ''}`;
                    prevLi.innerHTML = `<a class="page-link" href="#" onclick="AdminPanel.products.changePage(${pagination.page - 1}); return false;">Anterior</a>`;
                    paginationElement.appendChild(prevLi);
                    
                    // Números de página
                    for (let i = 1; i <= pagination.pages; i++) {
                        if (i === 1 || i === pagination.pages || (i >= pagination.page - 1 && i <= pagination.page + 1)) {
                            const li = document.createElement('li');
                            li.className = `page-item ${i === pagination.page ? 'active' : ''}`;
                            li.innerHTML = `<a class="page-link" href="#" onclick="AdminPanel.products.changePage(${i}); return false;">${i}</a>`;
                            paginationElement.appendChild(li);
                        } else if (i === pagination.page - 2 || i === pagination.page + 2) {
                            const li = document.createElement('li');
                            li.className = 'page-item disabled';
                            li.innerHTML = '<a class="page-link" href="#">...</a>';
                            paginationElement.appendChild(li);
                        }
                    }
                    
                    // Botón siguiente
                    const nextLi = document.createElement('li');
                    nextLi.className = `page-item ${pagination.page >= pagination.pages ? 'disabled' : ''}`;
                    nextLi.innerHTML = `<a class="page-link" href="#" onclick="AdminPanel.products.changePage(${pagination.page + 1}); return false;">Siguiente</a>`;
                    paginationElement.appendChild(nextLi);
                },
                
                changePage: function(page) {
                    currentPage = page;
                    this.loadProducts(page);
                },
                
                filterProducts: function() {
                    AdminPanel.products.loadProducts(1);
                },
                
                editProduct: async function(id) {
    try {
        showLoading(true);
        console.log('Editando producto ID:', id);
        
        const response = await fetch(`api/get_product.php?id=${id}`);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const product = await response.json();
        console.log('Producto cargado:', product);
        
        if (product) {
            // Llenar el formulario con los datos del producto
            document.getElementById('productName').value = product.nombre || '';
            document.getElementById('productCategory').value = product.categoria_id || '';
            document.getElementById('productBrand').value = product.marca || '';
            document.getElementById('productPrice').value = product.precio || '';
            document.getElementById('productStock').value = product.stock || '';
            document.getElementById('productWarranty').value = product.garantia || '';
            document.getElementById('productDescription').value = product.descripcion || '';
            document.getElementById('productImage').value = product.imagen_url || '';
            
            // Manejar la imagen existente
            const preview = document.getElementById('productImagePreview');
            const nameDisplay = document.getElementById('productImageName');
            
            if (product.imagen_url_full) {
                preview.src = product.imagen_url_full;
                preview.style.display = 'block';
                nameDisplay.textContent = 'Imagen actual';
                preview.dataset.currentImage = product.imagen_url_full;
            } else {
                preview.src = 'https://picsum.photos/seed/product' + id + '/200/200.jpg';
                preview.style.display = 'block';
                nameDisplay.textContent = 'Sin imagen';
                preview.dataset.currentImage = '';
            }
            
            // Cambiar el título del modal y el botón
            document.querySelector('#addProductModal .modal-title').textContent = 'Editar Producto';
            document.querySelector('#addProductForm button[type="submit"]').textContent = 'Actualizar Producto';
            
            // Agregar ID del producto al formulario
            document.getElementById('addProductForm').dataset.productId = id;
            
            // Actualizar token CSRF
            const csrfToken = document.querySelector('input[name="csrf_token"]');
            if (csrfToken) {
                // Obtener un nuevo token del servidor
                try {
                    const tokenResponse = await fetch('api/get_csrf_token.php');
                    const tokenData = await tokenResponse.json();
                    if (tokenData.success) {
                        csrfToken.value = tokenData.csrf_token;
                        console.log('Token CSRF actualizado:', tokenData.csrf_token);
                    }
                } catch (error) {
                    console.error('Error al obtener nuevo token CSRF:', error);
                }
            }
            
            const modal = new bootstrap.Modal(document.getElementById('addProductModal'));
            modal.show();
        } else {
            showToast(product.message || 'Producto no encontrado', 'error');
        }
    } catch (error) {
        console.error('Error al cargar producto:', error);
        showToast('Error al cargar producto: ' + error.message, 'error');
    } finally {
        showLoading(false);
    }
},
                
                deleteProduct: async function(id) {
                    if (!confirm('¿Estás seguro de que quieres eliminar este producto? Esta acción no se puede deshacer.')) {
                        return;
                    }
                    
                    try {
                        showLoading(true);
                        const response = await fetch('api/delete_product.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                id: id,
                                csrf_token: document.querySelector('input[name="csrf_token"]').value
                            })
                        });
                        
                        if (!response.ok) {
                            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                        }
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            await this.loadProducts(currentPage);
                            showToast('Producto eliminado correctamente', 'success');
                        } else {
                            showToast(result.message || 'Error al eliminar producto', 'error');
                        }
                    } catch (error) {
                        console.error('Error al eliminar producto:', error);
                        showToast('Error de conexión al eliminar producto: ' + error.message, 'error');
                    } finally {
                        showLoading(false);
                    }
                }
            },
            
            // Módulo de gráficos
            charts: {
                init: function() {
                    this.initCharts();
                },
                
                initCharts: function() {
                    this.updateCharts();
                },
                
                updateCharts: function() {
                    if (typeof Chart === 'undefined') {
                        console.error('La librería Chart.js no se ha cargado correctamente.');
                        return;
                    }

                    if (window.chartsUpdating) {
                        return;
                    }
                    window.chartsUpdating = true;

                    try {
                        // Destruir gráficos existentes antes de crear nuevos
                        const chartIds = ['categoriesChart', 'ordersChart', 'salesTrendChart', 'topProductsChart', 'revenueChart'];
                        chartIds.forEach(chartId => {
                            if (window[chartId] && typeof window[chartId].destroy === 'function') {
                                window[chartId].destroy();
                                window[chartId] = null;
                            }
                        });

                        // Gráfica de Categorías
                        const ctx = document.getElementById('categoriesChart'); 
                        if(ctx) { 
                            const container = ctx.parentElement;
                            if (container) {
                                container.style.height = '300px';
                            }

                            const count = {}; 
                            products.forEach(p => { 
                                const name = p.categoria_nombre || 'Sin Categoría'; 
                                count[name] = (count[name] || 0) + 1; 
                            }); 
                            
                            if (Object.keys(count).length > 0) {
                                window.categoriesChart = new Chart(ctx, { 
                                    type: 'doughnut', 
                                    data: { 
                                        labels: Object.keys(count), 
                                        datasets: [{ 
                                            data: Object.values(count), 
                                            backgroundColor: [
                                                '#1a3a52', '#2c5282', '#0f2438', '#051929', 
                                                '#4a5568', '#718096', '#2d3748', '#1a202c'
                                            ],
                                            borderWidth: 2,
                                            borderColor: '#ffffff'
                                        }] 
                                    }, 
                                    options: { 
                                        responsive: true, 
                                        maintainAspectRatio: false,
                                        resizeDelay: 100,
                                        plugins: { 
                                            legend: { 
                                                position: 'bottom',
                                                labels: {
                                                    padding: 15,
                                                    font: {
                                                        size: 12,
                                                        family: "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif"
                                                    },
                                                    color: '#4a5568'
                                                }
                                            },
                                            tooltip: {
                                                callbacks: {
                                                    label: function(context) {
                                                        const label = context.label || '';
                                                        const value = context.parsed || 0;
                                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                                        const percentage = ((value / total) * 100).toFixed(1);
                                                        return `${label}: ${value} (${percentage}%)`;
                                                    }
                                                }
                                            }
                                        },
                                        animation: {
                                            animateScale: true,
                                            animateRotate: true
                                        }
                                    } 
                                }); 
                            } else {
                                ctx.getContext('2d').font = '16px Segoe UI';
                                ctx.getContext('2d').fillStyle = '#6c757d';
                                ctx.getContext('2d').textAlign = 'center';
                                ctx.getContext('2d').fillText('No hay datos disponibles', ctx.width / 2, ctx.height / 2);
                            }
                        }
                        
                        // GRÁFICA DE PEDIDOS POR MES - CAMBIADA A BARRAS
                        const ordersCtx = document.getElementById('ordersChart');
                        if(ordersCtx) {
                            const container = ordersCtx.parentElement;
                            if (container) {
                                container.style.height = '300px';
                            }

                            const monthCounts = {};
                            const monthNames = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
                            
                            orders.forEach(order => {
                                if (order.fecha_pedido) {
                                    const date = new Date(order.fecha_pedido);
                                    const monthKey = `${date.getFullYear()}-${date.getMonth() + 1}`;
                                    monthCounts[monthKey] = (monthCounts[monthKey] || 0) + 1;
                                }
                            });
                            
                            const sortedMonths = Object.keys(monthCounts).sort();
                            const lastSixMonths = sortedMonths.slice(-6);
                            
                            const labels = lastSixMonths.map(month => {
                                const [year, monthNum] = month.split('-');
                                return `${monthNames[parseInt(monthNum) - 1]} ${year.slice(2)}`;
                            });
                            
                            const data = lastSixMonths.map(month => monthCounts[month]);
                            
                            if (labels.length > 0) {
                                window.ordersChart = new Chart(ordersCtx, {
                                    type: 'bar',
                                    data: {
                                        labels: labels,
                                        datasets: [{
                                            label: 'Pedidos',
                                            data: data,
                                            backgroundColor: 'rgba(26, 58, 82, 0.8)',
                                            borderColor: '#1a3a52',
                                            borderWidth: 2,
                                            borderRadius: 8,
                                            hoverBackgroundColor: 'rgba(26, 58, 82, 1)'
                                        }]
                                    },
                                    options: {
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        resizeDelay: 100,
                                        interaction: {
                                            intersect: false,
                                            mode: 'index'
                                        },
                                        plugins: {
                                            legend: {
                                                display: false
                                            },
                                            tooltip: {
                                                backgroundColor: 'rgba(26, 58, 82, 0.9)',
                                                titleColor: '#ffffff',
                                                bodyColor: '#ffffff',
                                                borderColor: '#1a3a52',
                                                borderWidth: 1,
                                                padding: 12,
                                                displayColors: false,
                                                callbacks: {
                                                    title: function(context) {
                                                        return `Pedidos: ${context[0].label}`;
                                                    },
                                                    label: function(context) {
                                                        return `Total: ${context.parsed.y} pedidos`;
                                                    }
                                                }
                                            }
                                        },
                                        scales: {
                                            y: {
                                                beginAtZero: true,
                                                grid: {
                                                    color: 'rgba(0, 0, 0, 0.05)',
                                                    drawBorder: false
                                                },
                                                ticks: {
                                                    font: {
                                                        size: 11,
                                                        family: "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif"
                                                    },
                                                    color: '#6c757d',
                                                    stepSize: 1
                                                }
                                            },
                                            x: {
                                                grid: {
                                                    display: false,
                                                    drawBorder: false
                                                },
                                                ticks: {
                                                    font: {
                                                        size: 11,
                                                        family: "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif"
                                                    },
                                                    color: '#6c757d'
                                                }
                                            }
                                        }
                                    }
                                });
                            } else {
                                ordersCtx.getContext('2d').font = '16px Segoe UI';
                                ordersCtx.getContext('2d').fillStyle = '#6c757d';
                                ordersCtx.getContext('2d').textAlign = 'center';
                                ordersCtx.getContext('2d').fillText('No hay datos disponibles', ordersCtx.width / 2, ordersCtx.height / 2);
                            }
                        }
                        
                        // Inicializar gráficos avanzados
                        this.initializeAdvancedCharts();
                    } catch (error) {
                        console.error('Error al actualizar gráficos:', error);
                    } finally {
                        setTimeout(() => {
                            window.chartsUpdating = false;
                        }, 100);
                    }
                },
                
                initializeAdvancedCharts: function() {
                    this.updateSalesTrendChart();
                    this.updateTopProductsChart();
                    this.updateRevenueChart();
                },

                updateSalesTrendChart: function() {
                    const ctx = document.getElementById('salesTrendChart');
                    if (!ctx) return;
                    
                    const monthlyData = {};
                    const monthNames = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
                    
                    orders.forEach(order => {
                        if (order.fecha_pedido) {
                            const date = new Date(order.fecha_pedido);
                            const monthKey = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
                            
                            if (!monthlyData[monthKey]) {
                                monthlyData[monthKey] = { orders: 0, revenue: 0 };
                            }
                            
                            monthlyData[monthKey].orders++;
                            monthlyData[monthKey].revenue += parseFloat(order.total || 0);
                        }
                    });
                    
                    const sortedMonths = Object.keys(monthlyData).sort();
                    const lastSixMonths = sortedMonths.slice(-6);
                    
                    const labels = lastSixMonths.map(month => {
                        const [year, monthNum] = month.split('-');
                        return `${monthNames[parseInt(monthNum) - 1]} ${year.slice(2)}`;
                    });
                    
                    const ordersData = lastSixMonths.map(month => monthlyData[month].orders);
                    const revenueData = lastSixMonths.map(month => monthlyData[month].revenue);
                    
                    if (window.salesTrendChart) {
                        window.salesTrendChart.destroy();
                    }
                    
                    if (labels.length > 0) {
                        window.salesTrendChart = new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: labels,
                                datasets: [{
                                    label: 'Pedidos',
                                    data: ordersData,
                                    borderColor: '#1a3a52',
                                    backgroundColor: 'rgba(26, 58, 82, 0.1)',
                                    borderWidth: 3,
                                    tension: 0.4,
                                    fill: true,
                                    yAxisID: 'y'
                                }, {
                                    label: 'Ingresos ($)',
                                    data: revenueData,
                                    borderColor: '#28a745',
                                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                                    borderWidth: 3,
                                    tension: 0.4,
                                    fill: true,
                                    yAxisID: 'y1'
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                interaction: {
                                    mode: 'index',
                                    intersect: false,
                                },
                                scales: {
                                    y: {
                                        type: 'linear',
                                        display: true,
                                        position: 'left',
                                        title: {
                                            display: true,
                                            text: 'Pedidos'
                                        }
                                    },
                                    y1: {
                                        type: 'linear',
                                        display: true,
                                        position: 'right',
                                        title: {
                                            display: true,
                                            text: 'Ingresos ($)'
                                        },
                                        grid: {
                                            drawOnChartArea: false,
                                        },
                                    }
                                }
                            }
                        });
                    }
                },

                updateTopProductsChart: function() {
                    const ctx = document.getElementById('topProductsChart');
                    if (!ctx) return;
                    
                    const productSales = {};
                    products.slice(0, 5).forEach((product, index) => {
                        productSales[product.nombre] = Math.floor(Math.random() * 50) + 10;
                    });
                    
                    const sortedProducts = Object.entries(productSales)
                        .sort((a, b) => b[1] - a[1])
                        .slice(0, 5);
                    
                    if (window.topProductsChart) {
                        window.topProductsChart.destroy();
                    }
                    
                    if (sortedProducts.length > 0) {
                        window.topProductsChart = new Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels: sortedProducts.map(p => p[0]),
                                datasets: [{
                                    label: 'Unidades Vendidas',
                                    data: sortedProducts.map(p => p[1]),
                                    backgroundColor: [
                                        'rgba(26, 58, 82, 0.8)',
                                        'rgba(44, 82, 130, 0.8)',
                                        'rgba(15, 36, 56, 0.8)',
                                        'rgba(5, 25, 41, 0.8)',
                                        'rgba(74, 85, 104, 0.8)'
                                    ],
                                    borderWidth: 2,
                                    borderColor: '#ffffff'
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                indexAxis: 'y',
                                scales: {
                                    x: {
                                        beginAtZero: true
                                    }
                                }
                            }
                        });
                    }
                },

                updateRevenueChart: function() {
                    const ctx = document.getElementById('revenueChart');
                    if (!ctx) return;
                    
                    const monthlyRevenue = {};
                    const monthNames = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
                    
                    const currentYear = new Date().getFullYear();
                    for (let i = 0; i < 12; i++) {
                        monthlyRevenue[`${currentYear}-${String(i + 1).padStart(2, '0')}`] = 0;
                    }
                    
                    orders.forEach(order => {
                        if (order.fecha_pedido) {
                            const date = new Date(order.fecha_pedido);
                            if (date.getFullYear() === currentYear) {
                                const monthKey = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
                                monthlyRevenue[monthKey] += parseFloat(order.total || 0);
                            }
                        }
                    });
                    
                    const labels = Object.keys(monthlyRevenue).map(month => {
                        const monthNum = parseInt(month.split('-')[1]);
                        return monthNames[monthNum - 1];
                    });
                    
                    const data = Object.values(monthlyRevenue);
                    
                    if (window.revenueChart) {
                        window.revenueChart.destroy();
                    }
                    
                    window.revenueChart = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: 'Ingresos Mensuales ($)',
                                data: data,
                                backgroundColor: 'rgba(40, 167, 69, 0.8)',
                                borderColor: '#28a745',
                                borderWidth: 2,
                                borderRadius: 8
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        callback: function(value) {
                                            return '$' + value.toLocaleString();
                                        }
                                    }
                                }
                            },
                            plugins: {
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            return 'Ingresos: $' + context.parsed.y.toLocaleString();
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
            },
            
            // Inicialización general
            init: function() {
                this.products.init();
                this.charts.init();
            }
        };
        
        // --- UI & NAVIGATION ---
        function showPage(pageId) {
            document.querySelectorAll('.page-content').forEach(p => p.style.display = 'none');
            document.querySelectorAll('.sidebar-item').forEach(i => i.classList.remove('active'));
            const page = document.getElementById(pageId + 'Page');
            if(page) {
                page.style.display = 'block';
                document.querySelector(`[data-page="${pageId}"]`).classList.add('active');
                document.getElementById('pageTitle').textContent = document.querySelector(`[data-page="${pageId}"] span`).textContent;
                
                if (pageId === 'products') {
                    AdminPanel.products.init();
                }
            }
            if (window.innerWidth <= 768) { closeMobileSidebar(); }
        }

        function checkPermissions() {
            const role = currentUser.rol || 'admin';
            document.querySelectorAll('[data-permission]').forEach(item => {
                const perm = item.dataset.permission;
                if (perm === 'admin' && role !== 'admin') { item.style.display = 'none'; }
            });
        }
        
        function logout() { 
            if(confirm('¿Estás seguro de que quieres cerrar sesión?')) { 
                window.location.href = 'logout.php'; 
            } 
        }

        function showLoading(show) {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) overlay.classList.toggle('active', show);
        }

        // --- MOBILE SIDEBAR ---
        function openMobileSidebar() { 
            document.getElementById('sidebar').classList.add('active'); 
            document.getElementById('mobileOverlay').classList.add('active'); 
            document.body.style.overflow = 'hidden';
        }
        
        function closeMobileSidebar() { 
            document.getElementById('sidebar').classList.remove('active'); 
            document.getElementById('mobileOverlay').classList.remove('active'); 
            document.body.style.overflow = '';
        }

        // --- FILE UPLOAD FUNCTIONS ---
        function setupFileUpload(inputId, previewId, nameId, hiddenId) {
            const input = document.getElementById(inputId);
            const preview = document.getElementById(previewId);
            const nameDisplay = document.getElementById(nameId);
            const hiddenInput = document.getElementById(hiddenId);
            
            input.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    if (!file.type.startsWith('image/')) {
                        showToast('Por favor, selecciona un archivo de imagen válido', 'error');
                        input.value = '';
                        return;
                    }
                    
                    if (file.size > 5 * 1024 * 1024) {
                        showToast('El archivo es demasiado grande. Máximo 5MB', 'error');
                        input.value = '';
                        return;
                    }
                    
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        preview.src = e.target.result;
                        preview.style.display = 'block';
                        nameDisplay.textContent = file.name;
                        preview.dataset.localPreview = e.target.result;
                    };
                    reader.readAsDataURL(file);
                    
                    uploadImage(file, hiddenId);
                }
            });
        }
        
        async function uploadImage(file, hiddenId) {
            const formData = new FormData();
            formData.append('image', file);
            formData.append('type', hiddenId === 'storeLogo' ? 'logos' : 'products');
            
            try {
                showLoading(true);
                const response = await fetch('api/upload_image.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    document.getElementById(hiddenId).value = result.path;
                    showToast('Imagen subida correctamente', 'success');
                } else {
                    showToast(result.message || 'Error al subir la imagen', 'error');
                }
            } catch (error) {
                showToast('Error al subir la imagen', 'error');
            } finally {
                showLoading(false);
            }
        }

        // --- DATA LOADING FUNCTIONS ---
    async function loadData() {
    if (isLoadingData) return;
    isLoadingData = true;
    
    showLoading(true);
    
    try {
        console.log('Iniciando carga de datos...');
        
        // Función helper para cargar datos con manejo detallado de errores
        const fetchWithErrorHandling = async (url, description) => {
            try {
                console.log(`Cargando ${description} desde ${url}...`);
                const response = await fetch(url);
                
                console.log(`Respuesta para ${description}:`, response.status, response.statusText);
                
                if (!response.ok) {
                    const errorText = await response.text();
                    console.error(`Error en ${description}:`, response.status, errorText);
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                // Verificar que la respuesta sea JSON válido
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    const text = await response.text();
                    console.error(`Respuesta no es JSON para ${description}:`, text);
                    throw new Error('La respuesta no es JSON válido');
                }
                
                const data = await response.json();
                console.log(`${description} cargados:`, data);
                return data;
            } catch (error) {
                console.error(`Error cargando ${description}:`, error);
                showToast(`Error al cargar ${description}: ${error.message}`, 'error');
                throw error;
            }
        };
        
        // Cargar categorías primero (necesario para productos)
        try {
            categories = await fetchWithErrorHandling('api/get_categories.php', 'categorías');
        } catch (error) {
            categories = [];
        }
        
        // Cargar productos
        try {
            const productsData = await fetchWithErrorHandling('api/products.php', 'productos');
            products = productsData.products || [];
        } catch (error) {
            products = [];
        }
        
        // Cargar otros datos en paralelo
        const [usersData, ordersData, customersData, scheduleData] = await Promise.allSettled([
            fetchWithErrorHandling('api/get_users.php', 'usuarios').catch(() => []),
            fetchWithErrorHandling('api/get_orders.php', 'pedidos').catch(() => []),
            fetchWithErrorHandling('api/get_customers.php', 'clientes').catch(() => []),
            fetchWithErrorHandling('api/get_schedule.php', 'horarios').catch(() => [])
        ]);
        
        // Procesar resultados
        if (usersData.status === 'fulfilled') {
            users = usersData.value;
        } else {
            users = [];
        }
        
        if (ordersData.status === 'fulfilled') {
            orders = ordersData.value;
        } else {
            orders = [];
        }
        
        if (customersData.status === 'fulfilled') {
            customers = customersData.value;
        } else {
            customers = [];
        }
        
        if (scheduleData.status === 'fulfilled') {
            storeSchedule = scheduleData.value;
        } else {
            storeSchedule = [];
        }
        
        // Actualizar la interfaz
        updateDashboard(); 
        updateUsersTable(); 
        updateCategorySelects();
        updateCategoriesList();
        updateCustomersTable();
        updateOrdersTable();
        loadScheduleList();
        
        showToast('Datos cargados correctamente', 'success');
        
    } catch (error) { 
        console.error('Error general cargando datos:', error); 
        showToast('Error al cargar los datos: ' + error.message, 'error'); 
    } finally { 
        isLoadingData = false;
        showLoading(false); 
    }
}
        async function loadScheduleList() {
            const container = document.getElementById('scheduleList'); 
            if (!container) return;
            
            try {
                showLoading(true);
                const response = await fetch('api/get_schedule.php');
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const schedule = await response.json();
                console.log('Horarios cargados:', schedule);
                
                container.innerHTML = '';
                const dayNames = { 
                    'lunes': 'Lunes', 
                    'martes': 'Martes', 
                    'miercoles': 'Miércoles', 
                    'jueves': 'Jueves', 
                    'viernes': 'Viernes', 
                    'sabado': 'Sábado', 
                    'domingo': 'Domingo' 
                };
                
                if (schedule.length === 0) {
                    container.innerHTML = `
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            No hay horarios configurados.
                        </div>
                    `;
                    
                    const days = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'];
                    days.forEach(day => {
                        const dayName = dayNames[day];
                        const scheduleDay = document.createElement('div'); 
                        scheduleDay.className = 'schedule-day';
                        scheduleDay.innerHTML = `
                            <div class="schedule-day-header">
                                <div class="schedule-day-name">${dayName}</div>
                                <div class="schedule-day-status closed">
                                    Cerrado
                                </div>
                            </div>
                            <div class="schedule-time-inputs">
                                <input type="time" class="form-control form-control-sm" value="" id="${day}Open">
                                <span class="mx-2">a</span>
                                <input type="time" class="form-control form-control-sm" value="" id="${day}Close">
                                <div class="form-check form-switch ms-3">
                                    <input class="form-check-input" type="checkbox" id="${day}Enabled">
                                    <label class="form-check-label" for="${day}Enabled"></label>
                                </div>
                            </div>
                        `;
                        container.appendChild(scheduleDay);
                    });
                } else {
                    schedule.forEach(dayData => {
                        const dayName = dayNames[dayData.dia_semana];
                        const scheduleDay = document.createElement('div'); 
                        scheduleDay.className = 'schedule-day';
                        scheduleDay.innerHTML = `
                            <div class="schedule-day-header">
                                <div class="schedule-day-name">${dayName}</div>
                                <div class="schedule-day-status ${dayData.esta_abierto ? 'open' : 'closed'}">
                                    ${dayData.esta_abierto ? 'Abierto' : 'Cerrado'}
                                </div>
                            </div>
                            <div class="schedule-time-inputs">
                                <input type="time" class="form-control form-control-sm" value="${dayData.hora_apertura || ''}" id="${dayData.dia_semana}Open">
                                <span class="mx-2">a</span>
                                <input type="time" class="form-control form-control-sm" value="${dayData.hora_cierre || ''}" id="${dayData.dia_semana}Close">
                                <div class="form-check form-switch ms-3">
                                    <input class="form-check-input" type="checkbox" id="${dayData.dia_semana}Enabled" ${dayData.esta_abierto ? 'checked' : ''}>
                                    <label class="form-check-label" for="${dayData.dia_semana}Enabled"></label>
                                </div>
                            </div>
                        `;
                        container.appendChild(scheduleDay);
                    });
                }
                
                container.querySelectorAll('.form-check-input').forEach(checkbox => {
                    checkbox.addEventListener('change', function() {
                        const day = this.id.replace('Enabled', '');
                        const statusElement = this.closest('.schedule-day').querySelector('.schedule-day-status');
                        const openInput = document.getElementById(day + 'Open');
                        const closeInput = document.getElementById(day + 'Close');
                        
                        if (this.checked) {
                            statusElement.classList.remove('closed');
                            statusElement.classList.add('open');
                            statusElement.textContent = 'Abierto';
                            openInput.disabled = false;
                            closeInput.disabled = false;
                        } else {
                            statusElement.classList.remove('open');
                            statusElement.classList.add('closed');
                            statusElement.textContent = 'Cerrado';
                            openInput.disabled = true;
                            closeInput.disabled = true;
                        }
                    });
                });
                
            } catch (error) { 
                console.error('Error loading schedule:', error); 
                showToast('Error al cargar horarios: ' + error.message, 'error');
            } finally {
                showLoading(false);
            }
        }

        // --- UI UPDATE FUNCTIONS ---
        function updateDashboard() { 
            document.getElementById('totalProducts').textContent = products.length; 
            document.getElementById('totalOrders').textContent = orders.length; 
            document.getElementById('totalCustomers').textContent = customers.length; 
            const totalRevenue = orders.reduce((sum, order) => sum + parseFloat(order.total || 0), 0); 
            document.getElementById('totalRevenue').textContent = `$${totalRevenue.toFixed(2)}`; 
            AdminPanel.charts.updateCharts();
        }
        
        function updateUsersTable() { 
            const tbody=document.getElementById('usersTableBody'); 
            if(!tbody) return; 
            tbody.innerHTML=''; 
            users.forEach(user=>{ 
                const row=document.createElement('tr'); 
                row.innerHTML=`
                    <td>${user.usuario}</td>
                    <td>${user.nombre_completo}</td>
                    <td>${user.email}</td>
                    <td>
                        <span class="badge bg-${user.rol==='admin'?'danger':'primary'}">
                            ${user.rol==='admin'?'Administrador':'Editor'}
                        </span>
                    </td>
                    <td>${new Date(user.fecha_creacion).toLocaleDateString()}</td>
                    <td>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteUser(${user.id})" ${user.id===currentUser.id?'disabled':''}>
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                `; 
                tbody.appendChild(row); 
            }); 
        }
        
        function updateCategorySelects() { 
            document.querySelectorAll('#productCategory').forEach(select=>{ 
                const currentValue=select.value; 
                select.innerHTML='<option value="">Seleccionar</option>'; 
                categories.forEach(cat=>{ 
                    const opt=document.createElement('option'); 
                    opt.value=cat.id; 
                    opt.textContent=cat.nombre; 
                    select.appendChild(opt); 
                }); 
                select.value=currentValue; 
            }); 
        }
        
        function updateCategoriesList() {
            const container = document.getElementById('categoriesList'); 
            if (!container) return;
            container.innerHTML = '';
            if (categories.length === 0) {
                container.innerHTML = `
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-tags fa-3x mb-3"></i>
                        <p>No hay categorías registradas</p>
                        <button class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                            <i class="fas fa-plus me-2"></i>Agregar Primera Categoría
                        </button>
                    </div>
                `;
                return;
            }
            categories.forEach(category => {
                const productCount = products.filter(p => p.categoria_id == category.id).length;
                const categoryCard = document.createElement('div'); 
                categoryCard.className = 'category-card';
                categoryCard.innerHTML = `
                    <div class="category-header">
                        <div class="category-name">
                            <i class="fas fa-tag me-2" style="color: ${category.color}"></i>${category.nombre}
                        </div>
                        <div class="category-actions">
                            <button class="btn btn-sm btn-outline-primary me-1" onclick="editCategory(${category.id})">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteCategory(${category.id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <div class="category-description text-muted mb-2">${category.descripcion || 'Sin descripción'}</div>
                    <div class="category-stats">
                        <div class="category-stat">
                            <div class="category-stat-number">${productCount}</div>
                            <div class="category-stat-label">Productos</div>
                        </div>
                    </div>
                `;
                container.appendChild(categoryCard);
            });
        }
        
        function updateCustomersTable() {
            const tbody = document.getElementById('customersTableBody');
            if (!tbody) return;
            
            tbody.innerHTML = '';
            
            customers.forEach(customer => {
                const row = document.createElement('tr');
                
                const totalOrders = customer.total_orders || 0;
                const totalSpent = parseFloat(customer.total_spent || 0);
                const lastOrder = customer.last_order ? new Date(customer.last_order).toLocaleDateString() : 'N/A';
                
                let customerType = 'Nuevo';
                let typeBadge = 'secondary';
                
                if (totalOrders > 10) {
                    customerType = 'VIP';
                    typeBadge = 'warning';
                } else if (totalOrders > 5) {
                    customerType = 'Frecuente';
                    typeBadge = 'info';
                } else if (totalOrders > 0) {
                    customerType = 'Regular';
                    typeBadge = 'primary';
                }
                
                row.innerHTML = `
                    <td>
                        <div class="d-flex align-items-center">
                            <div class="user-avatar me-2" style="width: 35px; height: 35px; font-size: 14px;">
                                ${customer.nombre ? customer.nombre.charAt(0).toUpperCase() : 'C'}
                            </div>
                            <div>
                                <div class="fw-bold">${customer.nombre || 'N/A'}</div>
                                <small class="text-muted">${customer.email || ''}</small>
                            </div>
                        </div>
                    </td>
                    <td>${customer.email || 'N/A'}</td>
                    <td>${customer.telefono || 'N/A'}</td>
                    <td>
                        <div class="text-center">
                            <div class="fw-bold">${totalOrders}</div>
                            <small class="text-muted">pedidos</small>
                        </div>
                    </td>
                    <td>
                        <div class="text-center">
                            <div class="fw-bold text-success">$${totalSpent.toFixed(2)}</div>
                            <small class="text-muted">total gastado</small>
                        </div>
                    </td>
                    <td>
                        <div class="text-center">
                            <div>${lastOrder}</div>
                            <span class="badge bg-${typeBadge}">${customerType}</span>
                        </div>
                    </td>
                    <td>
                        <div class="btn-group" role="group">
                            <button class="btn btn-sm btn-outline-primary" onclick="viewCustomerDetails(${customer.id})" title="Ver detalles">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-success" onclick="sendEmailToCustomer(${customer.id})" title="Enviar email">
                                <i class="fas fa-envelope"></i>
                            </button>
                        </div>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }
        
        function updateOrdersTable() {
            const tbody = document.getElementById('ordersTableBody');
            if (!tbody) return;
            
            tbody.innerHTML = '';
            
            orders.forEach(order => {
                const row = document.createElement('tr');
                
                const statusColors = {
                    'pending': 'warning',
                    'processing': 'info',
                    'shipped': 'primary',
                    'delivered': 'success',
                    'cancelled': 'danger'
                };
                
                const statusTexts = {
                    'pending': 'Pendiente',
                    'processing': 'Procesando',
                    'shipped': 'Enviado',
                    'delivered': 'Entregado',
                    'cancelled': 'Cancelado'
                };
                
                row.innerHTML = `
                    <td>#${order.id}</td>
                    <td>
                        <div class="fw-bold">${order.customer_name || 'N/A'}</div>
                        <small class="text-muted">${order.customer_email || ''}</small>
                    </td>
                    <td>${new Date(order.fecha_pedido).toLocaleDateString()}</td>
                    <td class="fw-bold">$${parseFloat(order.total).toFixed(2)}</td>
                    <td>
                        <select class="form-select form-select-sm" onchange="updateOrderStatus(${order.id}, this.value)">
                            ${Object.entries(statusTexts).map(([value, text]) => 
                                `<option value="${value}" ${order.estado === value ? 'selected' : ''}>${text}</option>`
                            ).join('')}
                        </select>
                    </td>
                    <td>
                        <div class="btn-group" role="group">
                            <button class="btn btn-sm btn-outline-primary" onclick="viewOrderDetails(${order.id})" title="Ver detalles">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-success" onclick="printOrder(${order.id})" title="Imprimir">
                                <i class="fas fa-print"></i>
                            </button>
                        </div>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }
        
        // --- REPORTS ---
        function generateReport(type) {
            showLoading(true);
            
            let reportData = {};
            let reportTitle = '';
            
            switch(type) {
                case 'sales':
                    reportTitle = 'Reporte de Ventas';
                    reportData = generateSalesReport();
                    break;
                case 'products':
                    reportTitle = 'Reporte de Productos';
                    reportData = generateProductsReport();
                    break;
                case 'customers':
                    reportTitle = 'Reporte de Clientes';
                    reportData = generateCustomersReport();
                    break;
            }
            
            const wb = XLSX.utils.book_new();
            const ws = XLSX.utils.json_to_sheet(reportData.data);
            XLSX.utils.book_append_sheet(wb, ws, reportTitle);
            XLSX.writeFile(wb, `${reportTitle}_${new Date().toISOString().split('T')[0]}.xlsx`);
            
            showToast(`${reportTitle} generado correctamente`, 'success');
            showLoading(false);
        }

        function generateSalesReport() {
            const salesData = {};
            
            orders.forEach(order => {
                const date = new Date(order.fecha_pedido);
                const monthKey = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
                
                if (!salesData[monthKey]) {
                    salesData[monthKey] = {
                        mes: monthKey,
                        pedidos: 0,
                        ingresos: 0,
                        promedio_pedido: 0
                    };
                }
                
                salesData[monthKey].pedidos++;
                salesData[monthKey].ingresos += parseFloat(order.total || 0);
            });
            
            Object.values(salesData).forEach(month => {
                month.promedio_pedido = month.pedidos > 0 ? month.ingresos / month.pedidos : 0;
            });
            
            return {
                data: Object.values(salesData).sort((a, b) => a.mes.localeCompare(b.mes))
            };
        }

        function generateProductsReport() {
            return {
                data: products.map(product => ({
                    id: product.id,
                    nombre: product.nombre,
                    categoria: product.categoria_nombre || 'N/A',
                    marca: product.marca || 'N/A',
                    precio: parseFloat(product.precio),
                    stock: parseInt(product.stock),
                    garantia: product.garantia || 'N/A',
                    valor_inventario: parseFloat(product.precio) * parseInt(product.stock),
                    estado: product.stock > 0 ? 'En stock' : 'Sin stock'
                }))
            };
        }

        function generateCustomersReport() {
            return {
                data: customers.map(customer => ({
                    id: customer.id,
                    nombre: customer.nombre || 'N/A',
                    email: customer.email || 'N/A',
                    telefono: customer.telefono || 'N/A',
                    total_pedidos: customer.total_orders || 0,
                    total_gastado: parseFloat(customer.total_spent || 0),
                    promedio_pedido: customer.total_orders > 0 ? 
                        parseFloat(customer.total_spent) / customer.total_orders : 0,
                    fecha_registro: customer.fecha_registro || 'N/A',
                    ultimo_pedido: customer.last_order || 'N/A'
                }))
            };
        }
        
        // --- DATABASE MANAGEMENT FUNCTIONS ---
        function exportDatabase() {
            window.open('api/export_database.php', '_blank');
            showToast('La exportación de la base de datos comenzará en una nueva pestaña', 'info');
        }

        function showCleanDatabaseModal() {
            const modal = new bootstrap.Modal(document.getElementById('cleanDatabaseModal'));
            modal.show();
        }

        async function cleanDatabase() {
            const confirmText = document.getElementById('confirmText').value;
            
            if (confirmText !== 'LIMPIAR_BASE_DE_DATOS') {
                showToast('El texto de confirmación es incorrecto', 'error');
                return;
            }
            
            if (!confirm('¿Estás absolutamente seguro? Esta acción eliminará todos los datos y no se puede deshacer.')) {
                return;
            }
            
            try {
                showLoading(true);
                const response = await fetch('api/clean_database.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ 
                        confirmText: confirmText,
                        csrf_token: document.querySelector('input[name="csrf_token"]').value
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    bootstrap.Modal.getInstance(document.getElementById('cleanDatabaseModal')).hide();
                    document.getElementById('confirmText').value = '';
                    await loadData();
                    showToast('Base de datos limpiada correctamente', 'success');
                } else {
                    showToast(result.message || 'Error al limpiar la base de datos', 'error');
                }
            } catch (error) {
                showToast('Error al limpiar la base de datos', 'error');
            } finally {
                showLoading(false);
            }
        }

        // --- FORM HANDLERS ---
        document.getElementById('addProductForm')?.addEventListener('submit', async function(e){ 
            e.preventDefault(); 
            
            const productId = this.dataset.productId;
            const isEditing = !!productId;
            
            const data = {
                nombre: document.getElementById('productName').value,
                categoria_id: document.getElementById('productCategory').value,
                marca: document.getElementById('productBrand').value,
                precio: document.getElementById('productPrice').value,
                stock: document.getElementById('productStock').value,
                garantia: document.getElementById('productWarranty').value,
                descripcion: document.getElementById('productDescription').value,
                imagen_url: document.getElementById('productImage').value,
                csrf_token: this.querySelector('input[name="csrf_token"]').value
            };
            
            if (!data.nombre || !data.precio || !data.garantia) {
                showToast('Por favor, completa los campos obligatorios (nombre, precio y garantía)', 'error');
                return;
            }
            
            try { 
                showLoading(true);
                
                const url = isEditing ? 'api/update_product.php' : 'api/add_product.php';
                
                if (isEditing) {
                    data.id = productId;
                }
                
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const result = await response.json();
                
                if (result.success) {
                    this.reset(); 
                    document.getElementById('productImagePreview').style.display = 'none';
                    document.getElementById('productImagePreview').src = '';
                    document.getElementById('productImageName').textContent = 'Ningún archivo seleccionado';
                    document.getElementById('productImage').value = '';
                    
                    document.querySelector('#addProductModal .modal-title').textContent = 'Agregar Producto';
                    document.querySelector('#addProductForm button[type="submit"]').textContent = 'Agregar Producto';
                    delete this.dataset.productId;
                    
                    bootstrap.Modal.getInstance(document.getElementById('addProductModal')).hide(); 
                    await AdminPanel.products.loadProducts(currentPage);
                    showToast(isEditing ? 'Producto actualizado correctamente' : 'Producto agregado correctamente', 'success'); 
                } else {
                    showToast(result.message || 'Error al guardar el producto', 'error');
                }
            } catch (error) { 
                console.error('Error:', error);
                showToast('Error de conexión al guardar el producto: ' + error.message, 'error'); 
            } finally {
                showLoading(false);
            }
        });
        
        document.getElementById('addUserForm')?.addEventListener('submit', async function(e){ 
            e.preventDefault(); 
            const formData=new FormData(this); 
            const data={ 
                usuario:formData.get('newUserUsername'), 
                nombre_completo:formData.get('newUserNombre'), 
                email:formData.get('newUserEmail'), 
                rol:formData.get('newUserRole'), 
                password:formData.get('newUserPassword'),
                csrf_token: this.querySelector('input[name="csrf_token"]').value
            }; 
            try{ 
                showLoading(true); 
                const response = await fetch('api/add_user.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const result = await response.json();
                
                if (result.success) {
                    this.reset(); 
                    bootstrap.Modal.getInstance(document.getElementById('addUserModal')).hide(); 
                    await loadData();
                    showToast('Usuario agregado','success'); 
                } else {
                    showToast(result.message || 'Error al agregar usuario', 'error');
                }
            }catch(error){ 
                showToast('Error de conexión: ' + error.message,'error'); 
            } finally { 
                showLoading(false); 
            } 
        });
        
        document.getElementById('addCategoryForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const categoryId = this.dataset.categoryId;
            const isEditing = !!categoryId;
            
            const data = {
                nombre: document.getElementById('categoryName').value,
                descripcion: document.getElementById('categoryDescription').value,
                color: document.getElementById('categoryColor').value,
                csrf_token: this.querySelector('input[name="csrf_token"]').value
            };
            
            if (!data.nombre) {
                showToast('Por favor, ingresa el nombre de la categoría', 'error');
                return;
            }
            
            try {
                showLoading(true);
                
                const url = isEditing ? 'api/update_category.php' : 'api/add_category.php';
                
                if (isEditing) {
                    data.id = categoryId;
                }
                
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const result = await response.json();
                
                if (result.success) {
                    bootstrap.Modal.getInstance(document.getElementById('addCategoryModal')).hide();
                    this.reset();
                    
                    document.querySelector('#addCategoryModal .modal-title').textContent = 'Agregar Categoría';
                    document.querySelector('#addCategoryForm button[type="submit"]').textContent = 'Agregar Categoría';
                    delete this.dataset.categoryId;
                    
                    await loadData();
                    showToast(isEditing ? 'Categoría actualizada exitosamente' : 'Categoría agregada exitosamente', 'success');
                } else {
                    showToast(result.message || 'Error al guardar la categoría', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('Error al guardar la categoría: ' + error.message, 'error');
            } finally {
                showLoading(false);
            }
        });
        
        document.getElementById('profileForm')?.addEventListener('submit', async function(e){ 
            e.preventDefault(); 
            const data={ 
                nombre_completo:document.getElementById('profileNombre').value, 
                email:document.getElementById('profileEmail').value, 
                current_password:document.getElementById('profileCurrentPassword').value, 
                new_password:document.getElementById('profileNewPassword').value,
                csrf_token: this.querySelector('input[name="csrf_token"]').value
            }; 
            if(data.new_password && data.new_password!==document.getElementById('profileConfirmPassword').value){ 
                showToast('Las nuevas contraseñas no coinciden.','error'); 
                return; 
            } 
            try{ 
                showLoading(true);
                const response = await fetch('api/update_profile.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const result = await response.json();
                
                if (result.success) {
                    document.getElementById('usernameDisplay').textContent = data.nombre_completo;
                    document.getElementById('userEmail').textContent = data.email;
                    showToast('Perfil actualizado','success'); 
                } else {
                    showToast(result.message || 'Error al actualizar perfil', 'error');
                }
            }catch(error){ 
                showToast(error.message,'error'); 
            } finally {
                showLoading(false);
            }
        });
        
        document.getElementById('storeSettingsForm')?.addEventListener('submit', async function(e){ 
            e.preventDefault(); 
            const data={ 
                store_name:document.getElementById('storeName').value, 
                store_logo:document.getElementById('storeLogo').value, 
                whatsapp_number:document.getElementById('whatsappNumber').value, 
                currency:document.getElementById('currency').value,
                csrf_token: this.querySelector('input[name="csrf_token"]').value
            }; 
            try{ 
                showLoading(true); 
                const response = await fetch('api/update_settings.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const result = await response.json();
                
                if (result.success) {
                    showToast('Configuración guardada','success'); 
                } else {
                    showToast(result.message || 'Error al guardar configuración', 'error');
                }
            }catch(error){ 
                showToast('Error de conexión: ' + error.message,'error'); 
            } finally { 
                showLoading(false); 
            } 
        });
        
        async function saveAllSchedule(){ 
            const days = ['lunes','martes','miercoles','jueves','viernes','sabado','domingo']; 
            const scheduleData = []; 
            
            let hasError = false;
            
            days.forEach(day => { 
                const isEnabled = document.getElementById(`${day}Enabled`)?.checked || false;
                const openTime = document.getElementById(`${day}Open`)?.value || null;
                const closeTime = document.getElementById(`${day}Close`)?.value || null;
                
                if (isEnabled && (!openTime || !closeTime)) {
                    showToast(`Por favor, configura las horas de apertura y cierre para ${day}`, 'error');
                    hasError = true;
                    return;
                }
                
                if (isEnabled && openTime && closeTime && openTime >= closeTime) {
                    showToast(`La hora de cierre debe ser posterior a la de apertura para ${day}`, 'error');
                    hasError = true;
                    return;
                }
                
                scheduleData.push({ 
                    dia_semana: day, 
                    hora_apertura: isEnabled ? openTime : null, 
                    hora_cierre: isEnabled ? closeTime : null, 
                    esta_abierto: isEnabled 
                }); 
            });
            
            if (hasError) {
                return;
            }
            
            if (!confirm('¿Estás seguro de que quieres guardar estos horarios?')) {
                return;
            }
            
            try {
                showLoading(true);
                const response = await fetch('api/update_schedule.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        schedule: scheduleData,
                        csrf_token: document.querySelector('input[name="csrf_token"]').value
                    })
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const result = await response.json();
                
                if (result.success) {
                    showToast('Horarios guardados correctamente', 'success');
                    await loadScheduleList();
                } else {
                    showToast(result.message || 'Error al guardar horarios', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('Error de conexión al guardar horarios: ' + error.message, 'error'); 
            } finally {
                showLoading(false);
            }
        }

        async function resetSchedule() {
            if (!confirm('¿Estás seguro de que quieres restablecer todos los horarios?')) {
                return;
            }
            
            try {
                showLoading(true);
                const response = await fetch('api/update_schedule.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        schedule: [
                            {dia_semana: 'lunes', hora_apertura: '09:00', hora_cierre: '18:00', esta_abierto: true},
                            {dia_semana: 'martes', hora_apertura: '09:00', hora_cierre: '18:00', esta_abierto: true},
                            {dia_semana: 'miercoles', hora_apertura: '09:00', hora_cierre: '18:00', esta_abierto: true},
                            {dia_semana: 'jueves', hora_apertura: '09:00', hora_cierre: '18:00', esta_abierto: true},
                            {dia_semana: 'viernes', hora_apertura: '09:00', hora_cierre: '18:00', esta_abierto: true},
                            {dia_semana: 'sabado', hora_apertura: '10:00', hora_cierre: '14:00', esta_abierto: true},
                            {dia_semana: 'domingo', hora_apertura: null, hora_cierre: null, esta_abierto: false}
                        ],
                        csrf_token: document.querySelector('input[name="csrf_token"]').value
                    })
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const result = await response.json();
                
                if (result.success) {
                    showToast('Horarios restablecidos correctamente', 'success');
                    await loadScheduleList();
                } else {
                    showToast(result.message || 'Error al restablecer horarios', 'error');
                }
            } catch (error) {
                showToast('Error al restablecer horarios: ' + error.message, 'error');
            } finally {
                showLoading(false);
            }
        }
        
        // --- EVENT LISTENERS ---
        document.addEventListener('DOMContentLoaded', function(){
            checkPermissions(); 
            loadData();
            
            setupFileUpload('productImageFile', 'productImagePreview', 'productImageName', 'productImage');
            setupFileUpload('storeLogoFile', 'storeLogoPreview', 'storeLogoName', 'storeLogo');
            
            document.getElementById('confirmText')?.addEventListener('input', function(e) {
                const confirmBtn = document.getElementById('confirmCleanBtn');
                confirmBtn.disabled = e.target.value !== 'LIMPIAR_BASE_DE_DATOS';
            });
            
            document.getElementById('confirmCleanBtn')?.addEventListener('click', cleanDatabase);
            
            document.querySelectorAll('.sidebar-item[data-page]').forEach(item=>{ 
                item.addEventListener('click', e=>{ 
                    e.preventDefault(); 
                    showPage(item.dataset.page); 
                }); 
            });

            document.getElementById('mobileToggle').addEventListener('click', openMobileSidebar);
            document.getElementById('mobileOverlay').addEventListener('click', closeMobileSidebar);
            document.getElementById('logoutBtn')?.addEventListener('click', logout);

            setInterval(()=>{ 
                const now=new Date(); 
                document.getElementById('currentTime').textContent=now.toLocaleString(); 
            },1000);
            
            let resizeTimer;
            window.addEventListener('resize', function() {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(function() {
                    if (window.innerWidth > 768) {
                        closeMobileSidebar();
                    }
                    
                    // Redimensionar gráficos solo si existen y son instancias de Chart
                    if (window.categoriesChart && typeof window.categoriesChart.resize === 'function') {
                        window.categoriesChart.resize();
                    }
                    if (window.ordersChart && typeof window.ordersChart.resize === 'function') {
                        window.ordersChart.resize();
                    }
                    if (window.salesTrendChart && typeof window.salesTrendChart.resize === 'function') {
                        window.salesTrendChart.resize();
                    }
                    if (window.topProductsChart && typeof window.topProductsChart.resize === 'function') {
                        window.topProductsChart.resize();
                    }
                    if (window.revenueChart && typeof window.revenueChart.resize === 'function') {
                        window.revenueChart.resize();
                    }
                }, 250);
            });
            
            let touchStartX = 0;
            let touchEndX = 0;
            
            document.addEventListener('touchstart', function(e) {
                touchStartX = e.changedTouches[0].screenX;
            });
            
            document.addEventListener('touchend', function(e) {
                touchEndX = e.changedTouches[0].screenX;
                handleSwipe();
            });
            
            function handleSwipe() {
                if (touchEndX < touchStartX - 50) {
                    if (document.getElementById('sidebar').classList.contains('active')) {
                        closeMobileSidebar();
                    }
                }
                if (touchEndX > touchStartX + 50) {
                    if (!document.getElementById('sidebar').classList.contains('active') && window.innerWidth <= 768) {
                        openMobileSidebar();
                    }
                }
            }
            
            AdminPanel.init();
        });

        // --- PLACEHOLDER FUNCTIONS ---
        async function editCategory(id){ 
            try {
                showLoading(true);
                const response = await fetch(`api/get_category.php?id=${id}`);
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const category = await response.json();
                
                if (category) {
                    document.getElementById('categoryName').value = category.nombre;
                    document.getElementById('categoryDescription').value = category.descripcion;
                    document.getElementById('categoryColor').value = category.color;
                    
                    document.querySelector('#addCategoryModal .modal-title').textContent = 'Editar Categoría';
                    document.querySelector('#addCategoryForm button[type="submit"]').textContent = 'Actualizar Categoría';
                    
                    document.getElementById('addCategoryForm').dataset.categoryId = id;
                    
                    const modal = new bootstrap.Modal(document.getElementById('addCategoryModal'));
                    modal.show();
                } else {
                    showToast('Categoría no encontrada', 'error');
                }
            } catch (error) {
                showToast('Error al cargar categoría: ' + error.message, 'error');
            } finally {
                showLoading(false);
            }
        }
        
        async function deleteCategory(id){ 
            if(confirm('¿Estás seguro de que quieres eliminar esta categoría? Esta acción no se puede deshacer.')){ 
                try {
                    showLoading(true);
                    const response = await fetch('api/delete_category.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            id: id,
                            csrf_token: document.querySelector('input[name="csrf_token"]').value
                        })
                    });
                    
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        await loadData();
                        showToast('Categoría eliminada correctamente', 'success');
                    } else {
                        showToast(result.message || 'Error al eliminar categoría', 'error');
                    }
                } catch (error) {
                    showToast('Error al eliminar categoría: ' + error.message, 'error');
                } finally {
                    showLoading(false);
                }
            } 
        }
        
        async function deleteUser(id){ 
            if(confirm('¿Estás seguro de que quieres eliminar este usuario? Esta acción no se puede deshacer.')){ 
                try {
                    showLoading(true);
                    const response = await fetch('api/delete_user.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            id: id,
                            csrf_token: document.querySelector('input[name="csrf_token"]').value
                        })
                    });
                    
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        await loadData();
                        showToast('Usuario eliminado correctamente', 'success');
                    } else {
                        showToast(result.message || 'Error al eliminar usuario', 'error');
                    }
                } catch (error) {
                    showToast('Error al eliminar usuario: ' + error.message, 'error');
                } finally {
                    showLoading(false);
                }
            } 
        }
        
        function exportToExcel(){ 
            const wb = XLSX.utils.book_new();
            
            const wsData = products.map(p => ({
                'ID': p.id,
                'Nombre': p.nombre,
                'Categoría': p.categoria_nombre,
                'Marca': p.marca,
                'Precio': p.precio,
                'Stock': p.stock,
                'Garantía': p.garantia,
                'Descripción': p.descripcion
            }));
            
            const ws = XLSX.utils.json_to_sheet(wsData);
            XLSX.utils.book_append_sheet(wb, ws, "Productos");
            
            XLSX.writeFile(wb, "productos.xlsx");
            
            showToast('Archivo Excel exportado correctamente', 'success');
        }
        
        // --- ORDER MANAGEMENT ---
        async function updateOrderStatus(orderId, newStatus) {
            try {
                const response = await fetch('api/update_order_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        order_id: orderId,
                        status: newStatus,
                        csrf_token: document.querySelector('input[name="csrf_token"]').value
                    })
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const result = await response.json();
                if (result.success) {
                    showToast('Estado del pedido actualizado', 'success');
                    
                    if (result.notification_sent) {
                        showToast('Notificación enviada al cliente', 'info');
                    }
                } else {
                    showToast('Error al actualizar estado: ' + result.message, 'error');
                }
            } catch (error) {
                showToast('Error de conexión: ' + error.message, 'error');
            }
        }

        function viewOrderDetails(orderId) {
            const order = orders.find(o => o.id === orderId);
            if (!order) return;
            
            const modal = document.createElement('div');
            modal.className = 'modal fade';
            modal.innerHTML = `
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Detalles del Pedido #${order.id}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Información del Cliente</h6>
                                    <p><strong>Nombre:</strong> ${order.customer_name || 'N/A'}</p>
                                    <p><strong>Email:</strong> ${order.customer_email || 'N/A'}</p>
                                    <p><strong>Teléfono:</strong> ${order.customer_phone || 'N/A'}</p>
                                    <p><strong>Dirección:</strong> ${order.customer_address || 'N/A'}</p>
                                </div>
                                <div class="col-md-6">
                                    <h6>Información del Pedido</h6>
                                    <p><strong>Fecha:</strong> ${new Date(order.fecha_pedido).toLocaleString()}</p>
                                    <p><strong>Estado:</strong> ${order.estado}</p>
                                    <p><strong>Método de Pago:</strong> ${order.payment_method || 'N/A'}</p>
                                    <p><strong>Total:</strong> $${parseFloat(order.total).toFixed(2)}</p>
                                </div>
                            </div>
                            <hr>
                            <h6>Productos del Pedido</h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Producto</th>
                                            <th>Cantidad</th>
                                            <th>Precio Unit.</th>
                                            <th>Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody id="orderDetailsBody">
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
            
            loadOrderDetails(orderId);
            
            modal.addEventListener('hidden.bs.modal', () => {
                modal.remove();
            });
        }

        async function loadOrderDetails(orderId) {
            try {
                const response = await fetch(`api/get_order_details.php?id=${orderId}`);
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const details = await response.json();
                
                const tbody = document.getElementById('orderDetailsBody');
                tbody.innerHTML = '';
                
                details.forEach(item => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${item.product_name}</td>
                        <td>${item.quantity}</td>
                        <td>$${parseFloat(item.price).toFixed(2)}</td>
                        <td>$${(parseFloat(item.price) * parseInt(item.quantity)).toFixed(2)}</td>
                    `;
                    tbody.appendChild(row);
                });
            } catch (error) {
                console.error('Error loading order details:', error);
                showToast('Error al cargar detalles del pedido', 'error');
            }
        }

        function printOrder(orderId) {
            window.open(`api/print_order.php?id=${orderId}`, '_blank');
        }
        
        // --- CUSTOMER MANAGEMENT ---
        function viewCustomerDetails(customerId) {
            const customer = customers.find(c => c.id === customerId);
            if (!customer) return;
            
            const modal = document.createElement('div');
            modal.className = 'modal fade';
            modal.innerHTML = `
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Detalles del Cliente</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Información Personal</h6>
                                    <p><strong>Nombre:</strong> ${customer.nombre || 'N/A'}</p>
                                    <p><strong>Email:</strong> ${customer.email || 'N/A'}</p>
                                    <p><strong>Teléfono:</strong> ${customer.telefono || 'N/A'}</p>
                                    <p><strong>Dirección:</strong> ${customer.direccion || 'N/A'}</p>
                                    <p><strong>Fecha de Registro:</strong> ${customer.fecha_registro ? new Date(customer.fecha_registro).toLocaleDateString() : 'N/A'}</p>
                                </div>
                                <div class="col-md-6">
                                    <h6>Estadísticas</h6>
                                    <p><strong>Total de Pedidos:</strong> ${customer.total_orders || 0}</p>
                                    <p><strong>Total Gastado:</strong> $${parseFloat(customer.total_spent || 0).toFixed(2)}</p>
                                    <p><strong>Promedio por Pedido:</strong> $${customer.total_orders > 0 ? (parseFloat(customer.total_spent) / customer.total_orders).toFixed(2) : '0.00'}</p>
                                    <p><strong>Último Pedido:</strong> ${customer.last_order ? new Date(customer.last_order).toLocaleDateString() : 'N/A'}</p>
                                </div>
                            </div>
                            <hr>
                            <h6>Historial de Pedidos Recientes</h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>ID Pedido</th>
                                            <th>Fecha</th>
                                            <th>Total</th>
                                            <th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody id="customerOrdersBody">
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
            
            loadCustomerOrders(customerId);
            
            modal.addEventListener('hidden.bs.modal', () => {
                modal.remove();
            });
        }

        async function loadCustomerOrders(customerId) {
            try {
                const response = await fetch(`api/get_customer_orders.php?id=${customerId}`);
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const orders = await response.json();
                
                const tbody = document.getElementById('customerOrdersBody');
                tbody.innerHTML = '';
                
                if (orders.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="4" class="text-center">No hay pedidos registrados</td></tr>';
                    return;
                }
                
                orders.forEach(order => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>#${order.id}</td>
                        <td>${new Date(order.fecha_pedido).toLocaleDateString()}</td>
                        <td>$${parseFloat(order.total).toFixed(2)}</td>
                        <td><span class="badge bg-${getStatusColor(order.estado)}">${getStatusText(order.estado)}</span></td>
                    `;
                    tbody.appendChild(row);
                });
            } catch (error) {
                console.error('Error loading customer orders:', error);
                showToast('Error al cargar pedidos del cliente', 'error');
            }
        }

        function getStatusColor(status) {
            const colors = {
                'pending': 'warning',
                'processing': 'info',
                'shipped': 'primary',
                'delivered': 'success',
                'cancelled': 'danger'
            };
            return colors[status] || 'secondary';
        }

        function getStatusText(status) {
            const texts = {
                'pending': 'Pendiente',
                'processing': 'Procesando',
                'shipped': 'Enviado',
                'delivered': 'Entregado',
                'cancelled': 'Cancelado'
            };
            return texts[status] || status;
        }

        function sendEmailToCustomer(customerId) {
            const customer = customers.find(c => c.id === customerId);
            if (!customer) return;
            
            const modal = document.createElement('div');
            modal.className = 'modal fade';
            modal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Enviar Email a ${customer.nombre}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <form id="sendEmailForm">
                                <div class="mb-3">
                                    <label for="emailSubject" class="form-label">Asunto</label>
                                    <input type="text" class="form-control" id="emailSubject" required>
                                </div>
                                <div class="mb-3">
                                    <label for="emailMessage" class="form-label">Mensaje</label>
                                    <textarea class="form-control" id="emailMessage" rows="5" required></textarea>
                                </div>
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="emailCopy">
                                    <label class="form-check-label" for="emailCopy">
                                        Enviar copia a mi correo
                                    </label>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-primary" onclick="sendEmail(${customerId})">Enviar Email</button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
            
            modal.addEventListener('hidden.bs.modal', () => {
                modal.remove();
            });
        }

        async function sendEmail(customerId) {
            const customer = customers.find(c => c.id === customerId);
            if (!customer) return;
            
            const subject = document.getElementById('emailSubject').value;
            const message = document.getElementById('emailMessage').value;
            const sendCopy = document.getElementById('emailCopy').checked;
            
            if (!subject || !message) {
                showToast('Por favor, completa todos los campos', 'warning');
                return;
            }
            
            try {
                const response = await fetch('api/send_email.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        customer_id: customerId,
                        subject: subject,
                        message: message,
                        send_copy: sendCopy,
                        csrf_token: document.querySelector('input[name="csrf_token"]').value
                    })
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const result = await response.json();
                if (result.success) {
                    showToast('Email enviado correctamente', 'success');
                    bootstrap.Modal.getInstance(document.querySelector('.modal.show')).hide();
                } else {
                    showToast('Error al enviar email: ' + result.message, 'error');
                }
            } catch (error) {
                showToast('Error de conexión al enviar email: ' + error.message, 'error');
            }
        }
    </script>
</body>
</html>