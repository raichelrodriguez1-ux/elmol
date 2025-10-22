<?php
session_start();

// Si ya está logueado, redirigir al panel
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Procesar formulario de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'config.php';
    
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Por favor, complete todos los campos';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE usuario = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                header('Location: index.php');
                exit();
            } else {
                $error = 'Usuario o contraseña incorrectos';
            }
        } catch (PDOException $e) {
            $error = 'Error en el sistema. Intente más tarde.';
        }
    }
}
?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Administración</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #1a3a52;
            --secondary-color: #0f2438;
            --accent-color: #051929;
            --gradient-primary: linear-gradient(135deg, #1a3a52 0%, #0f2438 50%, #051929 100%);
            --gradient-secondary: linear-gradient(135deg, #2c5282 0%, #1a365d 50%, #0f2438 100%);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--gradient-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 15px;
            position: relative;
            overflow: hidden;
        }
        
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="%23ffffff" fill-opacity="0.03" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,122.7C672,117,768,139,864,138.7C960,139,1056,117,1152,101.3C1248,85,1344,75,1392,69.3L1440,64L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>') no-repeat bottom;
            background-size: cover;
            opacity: 0.5;
        }
        
        .login-container {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 25px;
            box-shadow: 0 25px 80px rgba(26, 58, 82, 0.4);
            overflow: hidden;
            width: 100%;
            max-width: 950px;
            display: flex;
            min-height: 550px;
            backdrop-filter: blur(20px);
            animation: fadeIn 0.8s ease-out;
            transition: all 0.3s ease;
        }
        
        .login-left {
            background: var(--gradient-secondary);
            color: white;
            padding: 3.5rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            flex: 1;
            position: relative;
            overflow: hidden;
        }
        
        .login-left::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: rotate 30s linear infinite;
        }
        
        .login-right {
            padding: 3.5rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            flex: 1;
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        }
        
        .login-logo {
            font-size: 3.5rem;
            margin-bottom: 1.5rem;
            position: relative;
            z-index: 1;
            animation: pulse 2s infinite;
        }
        
        .login-title {
            font-size: 2.2rem;
            font-weight: bold;
            margin-bottom: 1.5rem;
            position: relative;
            z-index: 1;
        }
        
        .login-subtitle {
            opacity: 0.95;
            margin-bottom: 2.5rem;
            font-size: 1.1rem;
            position: relative;
            z-index: 1;
        }
        
        .form-floating {
            margin-bottom: 1.8rem;
        }
        
        .form-control {
            border-radius: 15px;
            border: 2px solid #e2e8f0;
            padding: 14px 18px;
            font-size: 1rem;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            background: #f8fafc;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.3rem rgba(26, 58, 82, 0.25);
            background: white;
            transform: translateY(-2px);
        }
        
        .btn-login {
            background: var(--gradient-primary);
            border: none;
            border-radius: 50px;
            padding: 14px 35px;
            font-size: 1.15rem;
            font-weight: 600;
            color: white;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            width: 100%;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(26, 58, 82, 0.3);
        }
        
        .btn-login::before {
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
        
        .btn-login:hover::before {
            width: 400px;
            height: 400px;
        }
        
        .btn-login:hover:not(:disabled) {
            transform: translateY(-4px);
            box-shadow: 0 15px 40px rgba(26, 58, 82, 0.4);
            background: var(--gradient-secondary);
        }
        
        .btn-login:disabled {
            opacity: 0.8;
            cursor: not-allowed;
        }
        
        .password-toggle {
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6c757d;
            z-index: 10;
            transition: color 0.3s ease;
        }
        
        .password-toggle:hover {
            color: var(--primary-color);
        }
        
        .alert {
            border-radius: 15px;
            border: none;
            padding: 18px;
            margin-bottom: 1.8rem;
            backdrop-filter: blur(10px);
            animation: slideDown 0.5s ease-out;
        }
        
        .features-list {
            list-style: none;
            padding: 0;
            margin-top: 2.5rem;
            position: relative;
            z-index: 1;
        }
        
        .features-list li {
            margin-bottom: 1.2rem;
            display: flex;
            align-items: center;
            padding: 0.5rem;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        .features-list li:hover {
            background: rgba(255,255,255,0.1);
            transform: translateX(5px);
        }
        
        .features-list i {
            margin-right: 12px;
            font-size: 1.3rem;
            width: 25px;
            text-align: center;
        }
        
        /* Mejoras para responsividad */
        @media (max-width: 992px) {
            .login-container {
                max-width: 800px;
            }
            
            .login-left {
                padding: 2.5rem;
            }
            
            .login-right {
                padding: 2.5rem;
            }
        }
        
        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
                max-width: 450px;
                min-height: auto;
            }
            
            .login-left {
                padding: 2rem;
                min-height: 280px;
            }
            
            .login-right {
                padding: 2rem;
            }
            
            .login-logo {
                font-size: 2.5rem;
            }
            
            .login-title {
                font-size: 1.6rem;
            }
            
            .login-subtitle {
                font-size: 1rem;
                margin-bottom: 1.5rem;
            }
            
            .features-list {
                margin-top: 1.5rem;
            }
            
            .features-list li {
                margin-bottom: 0.8rem;
                font-size: 0.9rem;
            }
            
            .features-list i {
                font-size: 1.1rem;
            }
        }
        
        @media (max-width: 480px) {
            body {
                padding: 10px;
            }
            
            .login-container {
                max-width: 100%;
                border-radius: 20px;
            }
            
            .login-left {
                padding: 1.5rem;
                min-height: 240px;
            }
            
            .login-right {
                padding: 1.5rem;
            }
            
            .login-logo {
                font-size: 2rem;
                margin-bottom: 1rem;
            }
            
            .login-title {
                font-size: 1.4rem;
                margin-bottom: 1rem;
            }
            
            .login-subtitle {
                font-size: 0.9rem;
                margin-bottom: 1rem;
            }
            
            .form-floating {
                margin-bottom: 1.2rem;
            }
            
            .form-control {
                padding: 12px 15px;
                font-size: 0.95rem;
            }
            
            .btn-login {
                padding: 12px 25px;
                font-size: 1rem;
            }
            
            .features-list {
                display: none;
            }
        }
        
        @media (max-width: 360px) {
            .login-left {
                padding: 1rem;
            }
            
            .login-right {
                padding: 1rem;
            }
            
            .login-logo {
                font-size: 1.8rem;
            }
            
            .login-title {
                font-size: 1.2rem;
            }
        }
        
        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .form-check-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(26, 58, 82, 0.25);
        }
        
        /* Animaciones */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Loader personalizado */
        .spinner-wrapper {
            display: inline-block;
            vertical-align: middle;
        }
        
        .custom-spinner {
            display: inline-block;
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-left">
            <i class="fas fa-store login-logo"></i>
            <h2 class="login-title">Panel de Administración</h2>
            <p class="login-subtitle">Gestiona tu tienda de forma eficiente</p>
            <ul class="features-list">
                <li><i class="fas fa-chart-line"></i> Analíticas en tiempo real</li>
                <li><i class="fas fa-box"></i> Gestión de productos</li>
                <li><i class="fas fa-users"></i> Base de clientes</li>
                <li><i class="fas fa-cog"></i> Configuración avanzada</li>
            </ul>
        </div>
        
        <div class="login-right">
            <h3 class="mb-4">Iniciar Sesión</h3>
            
            <div id="alertContainer"></div>
            
            <form id="loginForm" action="login.php" method="POST">
                <div class="form-floating">
                    <input type="text" class="form-control" id="username" name="username" placeholder="Usuario" required>
                    <label for="username">
                        <i class="fas fa-user me-2"></i>Usuario
                    </label>
                </div>
                
                <div class="form-floating position-relative">
                    <input type="password" class="form-control" id="password" name="password" placeholder="Contraseña" required>
                    <label for="password">
                        <i class="fas fa-lock me-2"></i>Contraseña
                    </label>
                    <i class="fas fa-eye password-toggle" id="passwordToggle"></i>
                </div>
                
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="rememberMe" name="rememberMe">
                    <label class="form-check-label" for="rememberMe">
                        Recordarme
                    </label>
                </div>
                
                <button type="submit" class="btn btn-login" id="loginBtn">
                    <span id="btnText">Iniciar Sesión</span>
                    <span id="btnLoader" class="spinner-wrapper" style="display: none;">
                        <span class="custom-spinner"></span>
                    </span>
                </button>
            </form>
            
            <div class="text-center mt-4">
                <small class="text-muted">
                    <i class="fas fa-shield-alt me-1"></i>
                    Conexión segura y encriptada
                </small>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Script para mostrar/ocultar la contraseña
        document.getElementById('passwordToggle').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this;
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
        
        // Script para manejar el envío del formulario con indicador de carga
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            const loginBtn = document.getElementById('loginBtn');
            const btnText = document.getElementById('btnText');
            const btnLoader = document.getElementById('btnLoader');
            const alertContainer = document.getElementById('alertContainer');
            
            // Limpiar alertas anteriores
            alertContainer.innerHTML = '';
            
            // Validación básica
            if (username.length < 3) {
                e.preventDefault();
                showAlert('El nombre de usuario debe tener al menos 3 caracteres', 'warning');
                return;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                showAlert('La contraseña debe tener al menos 6 caracteres', 'warning');
                return;
            }
            
            // Mostrar indicador de carga
            loginBtn.disabled = true;
            btnText.style.display = 'none';
            btnLoader.style.display = 'inline-block';
            
            // Simular tiempo de carga (quitar en producción)
            // setTimeout(() => {
            //     loginBtn.disabled = false;
            //     btnText.style.display = 'inline';
            //     btnLoader.style.display = 'none';
            // }, 2000);
        });
        
        // Función para mostrar alertas
        function showAlert(message, type) {
            const alertContainer = document.getElementById('alertContainer');
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                <i class="fas fa-exclamation-triangle me-2"></i>${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            alertContainer.appendChild(alertDiv);
            
            // Auto-eliminar después de 5 segundos
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }
        
        // Detectar si hay un error de login (simulado para demostración)
        <?php
        // Mostrar alerta de error si existe
        if (defined('LOGIN_ERROR')) {
            echo 'showAlert("' . htmlspecialchars(LOGIN_ERROR) . '", "danger");';
        }
        ?>
        
        // Mejorar la experiencia táctil en dispositivos móviles
        if ('ontouchstart' in window) {
            document.body.classList.add('touch-device');
        }
        
        // Prevenir zoom en inputs en iOS
        document.addEventListener('gesturestart', function(e) {
            e.preventDefault();
        });
    </script>
</body>
</html>