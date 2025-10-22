<?php
require_once 'api/config.php';

// Cargar configuración de la tienda
 $config = [];
 $result = $conn->query("SELECT clave, valor FROM configuracion");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $config[$row['clave']] = $row['valor'];
    }
}
 $conn->close();

// Función para obtener URL completa de imágenes
function getImageUrl($imagePath) {
    global $config;
    if (empty($imagePath)) {
        return 'https://picsum.photos/seed/product/400/300.jpg';
    }
    
    // Si ya es una URL completa, devolverla tal cual
    if (filter_var($imagePath, FILTER_VALIDATE_URL)) {
        return $imagePath;
    }
    
    // Si es una ruta relativa, combinarla con la URL base
    $baseUrl = rtrim($config['base_url'] ?? 'http://localhost', '/');
    return $baseUrl . '/' . ltrim($imagePath, '/');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($config['store_name'] ?? 'Mi Tienda') ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
    :root {
        --primary-color: #1a3a52;
        --secondary-color: #0f2438;
        --accent-color: #051929;
        --light-bg: #f8f9fa;
        --dark-text: #212529;
        --success-color: #28a745;
        --warning-color: #ffc107;
        --danger-color: #dc3545;
        --gradient-primary: linear-gradient(135deg, #1a3a52 0%, #0f2438 50%, #051929 100%);
        --gradient-secondary: linear-gradient(135deg, #2c5282 0%, #1a365d 50%, #0f2438 100%);
        --gradient-accent: linear-gradient(135deg, #051929 0%, #0a1628 50%, #1a3a52 100%);
        --transition-base: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    
    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
        background: linear-gradient(135deg, #f0f4f8 0%, #e2e8f0 100%);
        color: var(--dark-text);
        overflow-x: hidden;
        min-height: 100vh;
    }
    
    /* Scrollbar Personalizado */
    ::-webkit-scrollbar {
        width: 10px;
    }
    
    ::-webkit-scrollbar-track {
        background: #f1f1f1;
    }
    
    ::-webkit-scrollbar-thumb {
        background: var(--primary-color);
        border-radius: 5px;
    }
    
    ::-webkit-scrollbar-thumb:hover {
        background: var(--secondary-color);
    }
    
    /* Navbar */
    header {
        background: var(--gradient-primary);
        box-shadow: 0 4px 20px rgba(26, 58, 82, 0.15);
        padding: 0.75rem 0;
        backdrop-filter: blur(10px);
        transition: var(--transition-base);
    }
    
    .header-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 20px;
    }
    
    .logo-container {
        display: flex;
        align-items: center;
    }
    
    .logo-container img {
        height: 50px;
        margin-right: 15px;
    }
    
    .logo-container h1 {
        font-size: clamp(1.1rem, 4vw, 1.5rem);
        color: white !important;
        text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        margin: 0;
        font-weight: 700;
    }
    
    /* Hero Section */
    .hero-section {
        background: var(--gradient-secondary);
        color: white;
        padding: clamp(3rem, 10vw, 6rem) 0;
        text-align: center;
        position: relative;
        overflow: hidden;
    }
    
    .hero-section::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="%23ffffff" fill-opacity="0.1" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,122.7C672,117,768,139,864,138.7C960,139,1056,117,1152,101.3C1248,85,1344,75,1392,69.3L1440,64L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>') no-repeat bottom;
        background-size: cover;
    }
    
    .hero-title {
        font-size: clamp(2rem, 6vw, 3.5rem);
        font-weight: 800;
        margin-bottom: 1.5rem;
        background: linear-gradient(135deg, #ffffff 0%, #e0e7ff 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        text-shadow: 0 4px 8px rgba(0,0,0,0.1);
        position: relative;
        z-index: 1;
    }
    
    .hero-subtitle {
        font-size: clamp(1rem, 3vw, 1.5rem);
        margin-bottom: 2.5rem;
        color: rgba(255,255,255,0.9);
        position: relative;
        z-index: 1;
    }
    
    .btn-ver-productos {
        background: white;
        color: var(--primary-color);
        border: none;
        padding: 12px 30px;
        font-size: 16px;
        font-weight: 600;
        border-radius: 50px;
        cursor: pointer;
        transition: var(--transition-base);
        position: relative;
        overflow: hidden;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        box-shadow: 0 4px 15px rgba(255, 255, 255, 0.3);
    }
    
    .btn-ver-productos::before {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 0;
        height: 0;
        background: rgba(26, 58, 82, 0.1);
        border-radius: 50%;
        transition: width 0.6s, height 0.6s;
        transform: translate(-50%, -50%);
    }
    
    .btn-ver-productos:hover::before {
        width: 300px;
        height: 300px;
    }
    
    .btn-ver-productos:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(255, 255, 255, 0.4);
    }
    
    /* Sección de productos */
    .products-section {
        max-width: 1200px;
        margin: 0 auto;
        padding: 2rem 20px;
    }
    
    .section-title {
        font-size: clamp(1.5rem, 4vw, 2rem);
        font-weight: 700;
        margin-bottom: 25px;
        color: var(--primary-color);
        text-align: center;
        position: relative;
    }
    
    .section-title::after {
        content: '';
        position: absolute;
        bottom: -10px;
        left: 50%;
        width: 60px;
        height: 4px;
        background: var(--gradient-primary);
        border-radius: 2px;
        transform: translateX(-50%);
    }
    
    .search-container {
        margin-bottom: 30px;
        position: relative;
        max-width: 600px;
        margin-left: auto;
        margin-right: auto;
    }
    
    .search-input {
        width: 100%;
        padding: 12px 45px 12px 20px;
        border-radius: 50px;
        border: 2px solid #e2e8f0;
        background: white;
        color: var(--dark-text);
        font-size: 16px;
        box-shadow: 0 4px 15px rgba(26, 58, 82, 0.08);
        transition: var(--transition-base);
    }
    
    .search-input:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.2rem rgba(26, 58, 82, 0.25);
        outline: none;
    }
    
    .search-icon {
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--primary-color);
    }
    
    /* Tarjetas de productos */
    .product-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 1.5rem;
        padding: 1.5rem 0;
    }
    
    .product-card { 
        background: white;
        border-radius: 16px; 
        overflow: hidden; 
        transition: var(--transition-base);
        box-shadow: 0 4px 20px rgba(26, 58, 82, 0.1);
        height: 100%;
        position: relative;
        border: none;
        cursor: pointer;
    }
    
    .product-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--gradient-primary);
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    
    .product-card:hover::before {
        opacity: 1;
    }
    
    .product-card:hover { 
        transform: translateY(-8px);
        box-shadow: 0 12px 30px rgba(26, 58, 82, 0.2);
    }
    
    .product-card .card-img-container {
        height: 220px;
        overflow: hidden;
        position: relative;
    }
    
    .product-card .card-img-top {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: var(--transition-base);
    }
    
    .product-card:hover .card-img-top {
        transform: scale(1.05);
        filter: brightness(1.1);
    }
    
    .product-card .card-body {
        padding: 1.25rem;
    }
    
    .product-card .card-title {
        color: var(--primary-color);
        font-size: 1.25rem;
        font-weight: 600;
        margin-bottom: 0.75rem;
        line-height: 1.3;
    }
    
    .product-card .card-text {
        color: #6c757d;
        font-size: 1rem;
        margin-bottom: 1rem;
        line-height: 1.5;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    
    .product-card .price {
        color: var(--primary-color);
        font-size: 1.25rem;
        font-weight: 700;
        margin-bottom: 15px;
    }
    
    .product-card .btn-primary {
        background: var(--gradient-primary);
        border: none;
        color: white;
        font-weight: 600;
        padding: 0.625rem 1.5rem;
        border-radius: 50px;
        transition: var(--transition-base);
        position: relative;
        overflow: hidden;
        box-shadow: 0 4px 15px rgba(26, 58, 82, 0.3);
    }
    
    .product-card .btn-primary::before {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 0;
        height: 0;
        background: rgba(255,255,255,0.2);
        border-radius: 50%;
        transition: width 0.6s, height 0.6s;
        transform: translate(-50%, -50%);
    }
    
    .product-card .btn-primary:hover::before {
        width: 300px;
        height: 300px;
    }
    
    .product-card .btn-primary:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(26, 58, 82, 0.4);
    }
    
    .product-card .btn-primary:disabled {
        background: #6c757d;
        cursor: not-allowed;
        opacity: 0.6;
    }
    
    /* Carrito flotante - Botón inferior derecho */
    .floating-cart {
        position: fixed;
        bottom: 20px;
        right: 20px;
        width: 60px;
        height: 60px;
        background: var(--gradient-primary);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 24px;
        box-shadow: 0 4px 20px rgba(26, 58, 82, 0.4);
        z-index: 1000;
        transition: var(--transition-base);
        cursor: pointer;
        border: none;
    }
    
    .floating-cart:hover:not(:disabled) {
        transform: scale(1.1);
        box-shadow: 0 6px 25px rgba(26, 58, 82, 0.5);
    }
    
    .floating-cart:active {
        transform: scale(0.95);
    }
    
    .floating-cart:disabled {
        background: #6c757d;
        cursor: not-allowed;
        opacity: 0.6;
    }
    
    #floatingCartCount { 
        background-color: var(--danger-color); 
        color: #fff; 
        font-weight: bold; 
        border-radius: 50%; 
        width: 22px; 
        height: 22px; 
        text-align: center; 
        line-height: 22px; 
        position: absolute; 
        top: -5px; 
        right: -5px; 
        font-size: 0.75rem; 
        animation: pulse 2s infinite;
    }
    
    /* Sidebar del carrito */
    #cartSidebar { 
        width: min(400px, 90vw); 
        position: fixed; 
        top: 0; 
        right: -100%; 
        height: 100vh; 
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%); 
        box-shadow: -5px 0 25px rgba(26, 58, 82, 0.15); 
        transition: right 0.4s cubic-bezier(0.4, 0, 0.2, 1); 
        z-index: 1050; 
        overflow-y: auto; 
        color: var(--dark-text);
    }
    
    #cartSidebar.active {
        right: 0;
    }
    
    .cart-header {
        background: var(--gradient-primary);
        color: white;
        padding: 1rem 1.25rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 2px 10px rgba(26, 58, 82, 0.2);
    }
    
    .cart-header h5 {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 600;
    }
    
    #cartSidebar .btn-outline-secondary {
        color: var(--primary-color);
        border-color: var(--primary-color);
    }
    
    #cartSidebar .btn-outline-secondary:hover {
        background-color: var(--primary-color);
        color: white;
    }
    
    #cartSidebar .border-top {
        border-color: rgba(26, 58, 82, 0.1) !important;
    }
    
    /* Estado de la tienda */
    .store-status { 
        position: fixed;
        top: 90px;
        right: 15px;
        z-index: 1000;
        padding: 0.5rem 1rem;
        border-radius: 50px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        animation: pulse 2.5s infinite;
        font-size: 0.875rem;
        backdrop-filter: blur(10px);
    }
    
    .store-open { 
        background: linear-gradient(135deg, #28a745, #20c997);
        color: white; 
    }
    
    .store-closed { 
        background: linear-gradient(135deg, #dc3545, #c82333);
        color: white; 
    }
    
    /* Modal de detalles del producto */
    .modal-content {
        border-radius: 20px;
        border: none;
        box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        overflow: hidden;
    }
    
    .modal-header {
        background: var(--gradient-primary);
        color: white;
        border: none;
        padding: 1.5rem;
        position: relative;
    }
    
    .modal-header::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #28a745, #20c997);
    }
    
    .modal-title {
        font-weight: 700;
        font-size: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .modal-body {
        padding: 2rem;
        background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
    }
    
    .product-detail-image {
        width: 100%;
        max-height: 400px;
        object-fit: contain;
        border-radius: 12px;
        background-color: #f8f9fa;
        padding: 1rem;
    }
    
    .product-detail-info {
        margin-top: 1.5rem;
    }
    
    .product-detail-title {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--primary-color);
        margin-bottom: 1rem;
    }
    
    .product-detail-price {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--success-color);
        margin-bottom: 1rem;
    }
    
    .product-detail-description {
        color: #6c757d;
        margin-bottom: 1.5rem;
        line-height: 1.6;
        background-color: rgba(26, 58, 82, 0.05);
        padding: 1rem;
        border-radius: 8px;
        border-left: 4px solid var(--primary-color);
    }
    
    .product-detail-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    
    .product-detail-meta-item {
        background: rgba(26, 58, 82, 0.05);
        padding: 0.5rem 1rem;
        border-radius: 50px;
        font-size: 0.9rem;
        color: var(--primary-color);
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .product-detail-meta-item i {
        font-size: 1rem;
    }
    
    .product-detail-stock {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 1.5rem;
    }
    
    .stock-indicator {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background-color: var(--success-color);
    }
    
    .stock-low {
        background-color: var(--warning-color);
    }
    
    .stock-out {
        background-color: var(--danger-color);
    }
    
    /* Modal de checkout mejorado */
    .checkout-modal .modal-body {
        padding: 2rem;
        background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
    }
    
    .form-section {
        background: white;
        border-radius: 16px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 4px 15px rgba(26, 58, 82, 0.08);
        border: 1px solid rgba(26, 58, 82, 0.1);
    }
    
    .form-section-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--primary-color);
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .form-section-title i {
        color: var(--primary-color);
    }
    
    .form-control {
        border-radius: 12px;
        border: 2px solid #e2e8f0;
        padding: 0.75rem 1rem;
        transition: var(--transition-base);
        background: #f8fafc;
        font-size: 0.95rem;
    }
    
    .form-control:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.2rem rgba(26, 58, 82, 0.25);
        background: white;
        transform: translateY(-2px);
    }
    
    .form-label {
        color: var(--primary-color);
        font-weight: 600;
        margin-bottom: 0.5rem;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }
    
    .required {
        color: var(--danger-color);
    }
    
    /* Radio buttons personalizados para tipo de entrega */
    .delivery-type {
        display: flex;
        gap: 1rem;
        margin-top: 0.5rem;
    }
    
    .delivery-option {
        flex: 1;
        position: relative;
    }
    
    .delivery-option input[type="radio"] {
        position: absolute;
        opacity: 0;
    }
    
    .delivery-option label {
        display: block;
        padding: 1rem;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        text-align: center;
        cursor: pointer;
        transition: var(--transition-base);
        background: #f8fafc;
        font-weight: 500;
    }
    
    .delivery-option input[type="radio"]:checked + label {
        background: var(--gradient-primary);
        color: white;
        border-color: var(--primary-color);
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(26, 58, 82, 0.2);
    }
    
    .delivery-option label:hover {
        border-color: var(--primary-color);
        transform: translateY(-2px);
    }
    
    .delivery-option i {
        display: block;
        font-size: 1.5rem;
        margin-bottom: 0.5rem;
    }
    
    /* Campos obligatorios */
    .form-control.is-invalid {
        border-color: var(--danger-color);
        background-image: none;
    }
    
    .invalid-feedback {
        display: none;
        color: var(--danger-color);
        font-size: 0.875rem;
        margin-top: 0.25rem;
    }
    
    .form-control.is-invalid ~ .invalid-feedback {
        display: block;
    }
    
    .btn-success {
        background: linear-gradient(135deg, #28a745, #20c997);
        border: none;
        color: white;
        font-weight: 600;
        padding: 0.75rem 2rem;
        border-radius: 50px;
        transition: var(--transition-base);
        position: relative;
        overflow: hidden;
        box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        font-size: 1rem;
    }
    
    .btn-success::before {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 0;
        height: 0;
        background: rgba(255,255,255,0.2);
        border-radius: 50%;
        transition: width 0.6s, height 0.6s;
        transform: translate(-50%, -50%);
    }
    
    .btn-success:hover::before {
        width: 300px;
        height: 300px;
    }
    
    .btn-success:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
    }
    
    .btn-success:disabled {
        background: #6c757d;
        cursor: not-allowed;
        opacity: 0.6;
    }
    
    /* Comprobante de Pedido Mejorado */
    .receipt-container {
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        border-radius: 16px;
        border: 2px dashed var(--primary-color);
        padding: 1.5rem;
        position: relative;
        overflow: hidden;
    }
    
    .receipt-container::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--gradient-primary);
    }
    
    .receipt-header {
        text-align: center;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid rgba(26, 58, 82, 0.1);
    }
    
    .receipt-number {
        display: inline-block;
        background: var(--gradient-primary);
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 50px;
        font-weight: 700;
        font-size: 1.1rem;
        margin-bottom: 0.5rem;
        box-shadow: 0 4px 10px rgba(26, 58, 82, 0.2);
    }
    
    .receipt-date {
        color: #6c757d;
        font-size: 0.9rem;
        font-weight: 500;
    }
    
    .receipt-section {
        margin-bottom: 1.5rem;
    }
    
    .receipt-section-title {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--primary-color);
        margin-bottom: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .receipt-section-title i {
        font-size: 0.8rem;
    }
    
    .receipt-info {
        background: rgba(26, 58, 82, 0.05);
        padding: 0.75rem;
        border-radius: 8px;
        font-size: 0.9rem;
    }
    
    .receipt-info-item {
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.5rem;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid rgba(26, 58, 82, 0.1);
    }
    
    .receipt-info-item:last-child {
        margin-bottom: 0;
        padding-bottom: 0;
        border-bottom: none;
    }
    
    .receipt-info-label {
        color: #6c757d;
        font-weight: 500;
    }
    
    .receipt-info-value {
        font-weight: 600;
        color: var(--dark-text);
    }
    
    .receipt-products {
        margin-bottom: 1.5rem;
    }
    
    .receipt-product-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem;
        margin-bottom: 0.5rem;
        background: white;
        border-radius: 8px;
        border: 1px solid rgba(26, 58, 82, 0.1);
        transition: var(--transition-base);
    }
    
    .receipt-product-item:hover {
        transform: translateX(5px);
        box-shadow: 0 2px 10px rgba(26, 58, 82, 0.1);
    }
    
    .receipt-product-name {
        font-weight: 600;
        color: var(--primary-color);
        flex: 1;
    }
    
    .receipt-product-quantity {
        background: var(--gradient-primary);
        color: white;
        padding: 0.25rem 0.5rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        margin: 0 0.5rem;
    }
    
    .receipt-product-price {
        font-weight: 700;
        color: var(--success-color);
        min-width: 80px;
        text-align: right;
    }
    
    .receipt-total {
        background: var(--gradient-primary);
        color: white;
        padding: 1rem;
        border-radius: 12px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 1.2rem;
        font-weight: 700;
        box-shadow: 0 4px 15px rgba(26, 58, 82, 0.3);
        margin-top: 1rem;
    }
    
    .receipt-total-label {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .receipt-notes {
        background: rgba(255, 193, 7, 0.1);
        border-left: 4px solid var(--warning-color);
        padding: 0.75rem;
        border-radius: 0 8px 8px 0;
        font-size: 0.9rem;
        color: #856404;
        font-style: italic;
        margin-top: 1rem;
    }
    
    .receipt-notes strong {
        color: #856404;
    }
    
    /* Toasts */
    .custom-toast { 
        position: fixed; 
        top: 20px; 
        right: 20px; 
        z-index: 1060; 
        padding: 1rem; 
        border-radius: 12px; 
        color: #fff; 
        display: flex; 
        align-items: center; 
        gap: 0.75rem; 
        box-shadow: 0 4px 20px rgba(26, 58, 82, 0.15);
        animation: slideInRight 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        backdrop-filter: blur(10px);
        border-left: 4px solid var(--primary-color);
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        color: var(--dark-text);
        max-width: 350px;
    }
    
    .custom-toast.error {
        border-left-color: var(--danger-color);
    }
    
    .custom-toast i {
        font-size: 1.25rem;
    }
    
    .custom-toast.success i {
        color: var(--success-color);
    }
    
    .custom-toast.error i {
        color: var(--danger-color);
    }
    
    /* Overlay */
    #overlay {
        position: fixed;
        top:0;
        left:0;
        width:100%;
        height:100%;
        background: rgba(0,0,0,0.5);
        display:none;
        z-index:1040;
        backdrop-filter: blur(5px);
    }
    
    /* Animaciones */
    .cart-item-enter { animation: fadeIn 0.3s ease; }
    @keyframes fadeIn { from {opacity:0; transform: translateX(20px);} to {opacity:1; transform: translateX(0);} }
    
    @keyframes pulse {
        0% { 
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            transform: scale(1);
        }
        50% { 
            box-shadow: 0 4px 25px rgba(0,0,0,0.25);
            transform: scale(1.05);
        }
        100% { 
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            transform: scale(1);
        }
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
    
    /* Responsive */
    @media (max-width: 768px) {
        .hero-title {
            font-size: 2rem;
        }
        
        .hero-subtitle {
            font-size: 1rem;
        }
        
        .header-content {
            flex-direction: column;
            gap: 15px;
        }
        
        .product-grid {
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }
        
        #cartSidebar {
            width: 100%;
        }
        
        .custom-toast {
            right: 10px;
            left: 10px;
            max-width: none;
        }
        
        .delivery-type {
            flex-direction: column;
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .receipt-product-item {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.5rem;
        }
        
        .receipt-product-price {
            text-align: left;
            min-width: auto;
        }
    }
    
    @media (max-width: 576px) {
        .product-grid {
            grid-template-columns: 1fr;
        }
        
        .floating-cart {
            width: 50px;
            height: 50px;
            font-size: 20px;
            bottom: 15px;
            right: 15px;
        }
    }
</style>
</head>
<body>
<header>
    <div class="header-content">
        <div class="logo-container">
            <img src="<?= getImageUrl($config['store_logo'] ?? '') ?>" alt="Logo">
            <h1><?= htmlspecialchars($config['store_name'] ?? 'Mi Tienda') ?></h1>
        </div>
    </div>
</header>

<section class="hero-section">
    <div class="container">
        <h2 class="hero-title">Bienvenido a nuestra Tienda en Línea</h2>
        <p class="hero-subtitle">Explora nuestros productos y realiza pedidos rápidos</p>
        <button class="btn-ver-productos" onclick="document.getElementById('productsSection').scrollIntoView({behavior: 'smooth'})">
            <i class="fas fa-shopping-bag"></i>
            Ver Productos
        </button>
    </div>
</section>

<div class="container mt-3" id="productsSection">
    <div id="storeStatus" class="store-status store-closed d-none">La tienda está cerrada</div>
    
    <div class="products-section">
        <h3 class="section-title">Todos los Productos</h3>
        
        <div class="search-container">
            <input type="text" class="search-input" placeholder="Buscar productos..." id="searchInput">
            <i class="fas fa-search search-icon"></i>
        </div>
        
        <div id="productGrid" class="product-grid"></div>
    </div>
</div>

<!-- Carrito flotante -->
<button class="floating-cart" id="openCartBtn" aria-label="Abrir carrito">
    <i class="fas fa-shopping-cart"></i>
    <span id="floatingCartCount">0</span>
</button>

<!-- Sidebar Carrito -->
<div id="cartSidebar" aria-hidden="true">
    <div class="cart-header">
        <h5><i class="fas fa-shopping-cart me-2"></i>Carrito</h5>
        <button class="btn btn-sm btn-light" id="closeCart" aria-label="Cerrar carrito"><i class="fas fa-times"></i></button>
    </div>
    <div id="cartItems" class="p-3"></div>
    <div class="mt-3 border-top pt-3 d-flex justify-content-between px-3">
        <strong>Total:</strong>
        <strong id="cartTotal">$0.00 USD</strong>
    </div>
    <div class="p-3">
        <button class="btn btn-success w-100" id="checkoutBtn" data-bs-toggle="modal" data-bs-target="#checkoutModal">
            <i class="fas fa-cash-register me-2"></i>Finalizar compra
        </button>
    </div>
</div>
<div id="overlay" aria-hidden="true"></div>

<!-- Modal de Detalles del Producto -->
<div class="modal fade" id="productDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
            <i class="fas fa-box"></i>
            Detalles del Producto
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div class="row">
            <div class="col-md-6">
                <img id="detailProductImage" src="" alt="" class="product-detail-image">
            </div>
            <div class="col-md-6">
                <div class="product-detail-info">
                    <h3 id="detailProductName" class="product-detail-title"></h3>
                    <div class="product-detail-price" id="detailProductPrice"></div>
                    <div class="product-detail-stock" id="detailProductStock">
                        <span class="stock-indicator"></span>
                        <span id="stockText"></span>
                    </div>
                    
                    <!-- Metadatos del producto -->
                    <div class="product-detail-meta">
                        <div class="product-detail-meta-item" id="detailProductCategory">
                            <i class="fas fa-tag"></i>
                            <span>Categoría</span>
                        </div>
                        <div class="product-detail-meta-item" id="detailProductBrand">
                            <i class="fas fa-copyright"></i>
                            <span>Marca</span>
                        </div>
                        <div class="product-detail-meta-item" id="detailProductWarranty">
                            <i class="fas fa-shield-alt"></i>
                            <span>Garantía</span>
                        </div>
                    </div>
                    
                    <!-- Descripción del producto -->
                    <div class="product-detail-description" id="detailProductDescription"></div>
                    
                    <div class="d-flex align-items-center gap-3">
                        <div class="input-group" style="max-width: 150px;">
                            <button class="btn btn-outline-secondary" type="button" id="decreaseQuantity">-</button>
                            <input type="number" class="form-control text-center" id="detailProductQuantity" value="1" min="1">
                            <button class="btn btn-outline-secondary" type="button" id="increaseQuantity">+</button>
                        </div>
                        <button class="btn btn-primary flex-grow-1" id="addToCartFromDetail">
                            <i class="fas fa-cart-plus me-2"></i>
                            Agregar al carrito
                        </button>
                    </div>
                </div>
            </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Checkout Modal Mejorado -->
<div class="modal fade checkout-modal" id="checkoutModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
            <i class="fas fa-cash-register"></i>
            Finalizar Pedido
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <form id="checkoutForm">
            <!-- Información Personal -->
            <div class="form-section">
                <h6 class="form-section-title">
                    <i class="fas fa-user"></i>
                    Información Personal
                </h6>
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label for="customerName" class="form-label">
                            Nombre Completo <span class="required">*</span>
                        </label>
                        <input type="text" class="form-control" id="customerName" required>
                        <div class="invalid-feedback">Por favor ingresa tu nombre completo</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="customerIdCard" class="form-label">
                            Número de Carnet <span class="required">*</span>
                        </label>
                        <input type="text" class="form-control" id="customerIdCard" required>
                        <div class="invalid-feedback">Por favor ingresa tu número de carnet</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="customerPhone" class="form-label">
                            Teléfono <span class="required">*</span>
                        </label>
                        <input type="tel" class="form-control" id="customerPhone" required>
                        <div class="invalid-feedback">Por favor ingresa un número de teléfono válido (mínimo 8 dígitos)</div>
                    </div>
                    <div class="col-md-12 mb-3">
                        <label for="customerEmail" class="form-label">
                            Email
                        </label>
                        <input type="email" class="form-control" id="customerEmail">
                        <div class="invalid-feedback">Por favor ingresa un email válido</div>
                    </div>
                </div>
            </div>
            
            <!-- Tipo de Entrega -->
            <div class="form-section">
                <h6 class="form-section-title">
                    <i class="fas fa-truck"></i>
                    Tipo de Entrega
                </h6>
                <div class="delivery-type">
                    <div class="delivery-option">
                        <input type="radio" id="deliveryLocal" name="deliveryType" value="local" checked>
                        <label for="deliveryLocal">
                            <i class="fas fa-store"></i>
                            Retirar en Local
                        </label>
                    </div>
                    <div class="delivery-option">
                        <input type="radio" id="deliveryHome" name="deliveryType" value="domicilio">
                        <label for="deliveryHome">
                            <i class="fas fa-home"></i>
                            Entrega a Domicilio
                        </label>
                    </div>
                </div>
                
                <!-- Dirección (solo para entrega a domicilio) -->
                <div id="addressSection" style="display: none; margin-top: 1rem;">
                    <label for="customerAddress" class="form-label">
                        Dirección de Entrega
                    </label>
                    <textarea class="form-control" id="customerAddress" rows="2"></textarea>
                </div>
            </div>
            
            <!-- Notas Adicionales -->
            <div class="form-section">
                <h6 class="form-section-title">
                    <i class="fas fa-sticky-note"></i>
                    Notas Adicionales
                </h6>
                <textarea class="form-control" id="customerNotes" rows="3" placeholder="Instrucciones especiales para tu pedido..."></textarea>
            </div>
            
            <!-- Comprobante de Pedido Mejorado -->
            <div class="form-section">
                <h6 class="form-section-title">
                    <i class="fas fa-receipt"></i>
                    Comprobante de Pedido
                </h6>
                <div class="receipt-container" id="orderReceipt">
                    <!-- El comprobante se generará dinámicamente -->
                </div>
            </div>
            
            <div class="text-center mt-4">
                <button type="button" class="btn btn-success btn-lg" id="submitOrderBtn" onclick="processCheckout()">
                    <i class="fab fa-whatsapp me-2"></i>
                    Enviar Pedido por WhatsApp
                </button>
            </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Toasts -->
<div id="toastContainer" aria-live="assertive" aria-atomic="true"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ===================== Variables =====================
let products = [], cart = [], categories = [];
let storeSettings = { 
    currency: '<?= $config['currency'] ?? 'USD' ?>', 
    whatsapp: '<?= $config['whatsapp_number'] ?? '' ?>',
    name: '<?= htmlspecialchars($config['store_name'] ?? 'Mi Tienda Virtual') ?>',
    baseUrl: '<?= $config['base_url'] ?? 'http://localhost' ?>'
};
let horarios = [], isStoreOpen = false;
let currentOrderNumber = 1;
let currentProductDetail = null;

// ===================== Funciones =====================

// Función para obtener URL completa de imágenes
function getImageUrl(imagePath) {
    if (!imagePath) {
        return 'https://picsum.photos/seed/product/400/300.jpg';
    }
    
    // Si ya es una URL completa, devolverla tal cual
    if (imagePath.startsWith('http://') || imagePath.startsWith('https://')) {
        return imagePath;
    }
    
    // Si es una ruta relativa, combinarla con la URL base
    const baseUrl = storeSettings.baseUrl.replace(/\/$/, '');
    return `${baseUrl}/${imagePath.replace(/^\//, '')}`;
}

// Función para formatear precios con el símbolo $ antes y la moneda después
function formatPrice(amount) {
    return `$${parseFloat(amount).toFixed(2)} ${storeSettings.currency}`;
}

// Generar número de pedido secuencial
function generateOrderNumber() {
    const savedNumber = localStorage.getItem('lastOrderNumber');
    if (savedNumber) {
        currentOrderNumber = parseInt(savedNumber) + 1;
    }
    localStorage.setItem('lastOrderNumber', currentOrderNumber);
    return `#${currentOrderNumber.toString().padStart(4, '0')}`;
}

// Función segura para obtener valores
function safeGet(value, defaultValue = '') {
    return value !== undefined && value !== null ? value : defaultValue;
}

async function fetchFromAPI(endpoint) {
    const res = await fetch(`api/${endpoint}.php`);
    if (!res.ok) throw new Error(`Error al cargar ${endpoint}`);
    return await res.json();
}

// Categorías
async function loadCategories() {
    try {
        const data = await fetchFromAPI('categorias');
        categories = data;
        console.log('Categorías cargadas:', categories); // Debug
    } catch (e) { 
        console.error('Error al cargar categorías:', e); 
        showToast('Error al cargar categorías','error'); 
    }
}

// Productos
async function loadProducts() {
    try {
        const data = await fetchFromAPI('productos');
        products = data;
        console.log('Productos cargados:', products); // Debug
        renderProducts();
    } catch (e) { 
        console.error('Error al cargar productos:', e); 
        showToast('Error al cargar productos','error'); 
    }
}

function renderProducts() {
    const container = document.getElementById('productGrid');
    container.innerHTML = '';
    products.forEach(p => {
        const div = document.createElement('div');
        div.className = 'product-card-enter';
        const isDisabled = !isStoreOpen;
        
        // Obtener nombre de categoría
        const category = categories.find(c => c.id === p.categoria_id);
        const categoryName = category ? category.nombre : 'Sin categoría';
        
        div.innerHTML = `
            <div class="card product-card" onclick="showProductDetail(${p.id})">
                <div class="card-img-container">
                    <img src="${getImageUrl(safeGet(p.imagen_url, ''))}" class="card-img-top" alt="${safeGet(p.nombre, 'Producto')}" loading="lazy">
                </div>
                <div class="card-body">
                    <h6 class="card-title">${safeGet(p.nombre, 'Producto sin nombre')}</h6>
                    <p class="card-text">${safeGet(p.descripcion, 'Sin descripción')}</p>
                    <p class="price"><strong>${formatPrice(safeGet(p.precio, 0))}</strong></p>
                    <button class="btn btn-primary w-100" onclick="event.stopPropagation(); addToCart(${p.id})" ${isDisabled ? 'disabled' : ''}>
                        <i class="fas fa-cart-plus me-2"></i>
                        ${isDisabled ? 'Tienda Cerrada' : 'Agregar al carrito'}
                    </button>
                </div>
            </div>
        `;
        container.appendChild(div);
    });
}

// Mostrar detalles del producto
function showProductDetail(productId) {
    const product = products.find(p => p.id === productId);
    if (!product) return;
    
    currentProductDetail = product;
    
    // Obtener nombre de categoría
    const category = categories.find(c => c.id === product.categoria_id);
    const categoryName = category ? category.nombre : 'Sin categoría';
    
    console.log('Producto seleccionado:', product); // Debug
    console.log('Categoría encontrada:', category); // Debug
    console.log('Nombre de categoría:', categoryName); // Debug
    
    // Actualizar modal con detalles del producto
    document.getElementById('detailProductImage').src = getImageUrl(safeGet(product.imagen_url, ''));
    document.getElementById('detailProductImage').alt = safeGet(product.nombre, 'Producto');
    document.getElementById('detailProductName').textContent = safeGet(product.nombre, 'Producto sin nombre');
    document.getElementById('detailProductPrice').textContent = formatPrice(safeGet(product.precio, 0));
    
    // Actualizar metadatos con nombres y valores reales
    document.getElementById('detailProductCategory').innerHTML = `
        <i class="fas fa-tag"></i>
        <span>Categoría: ${categoryName}</span>
    `;
    document.getElementById('detailProductBrand').innerHTML = `
        <i class="fas fa-copyright"></i>
        <span>Marca: ${safeGet(product.marca, 'Sin marca especificada')}</span>
    `;
    document.getElementById('detailProductWarranty').innerHTML = `
        <i class="fas fa-shield-alt"></i>
        <span>Garantía: ${safeGet(product.garantia, 'Sin garantía especificada')}</span>
    `;
    
    // Actualizar descripción con mejor formato
    const description = safeGet(product.descripcion, 'No hay descripción disponible para este producto.');
    document.getElementById('detailProductDescription').innerHTML = `
        <h6 class="mb-2">Descripción del producto</h6>
        <p>${description}</p>
    `;
    
    // Actualizar stock
    const stock = safeGet(product.stock, 0);
    const stockIndicator = document.querySelector('#detailProductStock .stock-indicator');
    const stockText = document.getElementById('stockText');
    
    if (stock <= 0) {
        stockIndicator.className = 'stock-indicator stock-out';
        stockText.textContent = 'Agotado';
        document.getElementById('addToCartFromDetail').disabled = true;
    } else if (stock <= 5) {
        stockIndicator.className = 'stock-indicator stock-low';
        stockText.textContent = `¡Últimas ${stock} unidades!`;
        document.getElementById('addToCartFromDetail').disabled = !isStoreOpen;
    } else {
        stockIndicator.className = 'stock-indicator';
        stockText.textContent = `${stock} unidades disponibles`;
        document.getElementById('addToCartFromDetail').disabled = !isStoreOpen;
    }
    
    // Resetear cantidad
    document.getElementById('detailProductQuantity').value = 1;
    
    // Mostrar modal
    const modal = new bootstrap.Modal(document.getElementById('productDetailModal'));
    modal.show();
}

// Carrito
function addToCart(productId, quantity = 1) {
    if (!isStoreOpen) {
        showToast('La tienda está cerrada. No puedes agregar productos al carrito.', 'error');
        return;
    }
    
    const product = products.find(p=>p.id===productId);
    if(!product) return;
    
    const existing = cart.find(i=>i.id===productId);
    if(existing){
        const newQuantity = existing.quantity + quantity;
        if(newQuantity <= safeGet(product.stock, 999)) {
            existing.quantity = newQuantity;
        } else { 
            showToast('No hay suficiente stock disponible','error'); 
            return; 
        }
    } else { 
        cart.push({...product, quantity}); 
    }
    
    updateCartCount(); 
    renderCart(); 
    saveCart(); 
    showToast('Producto agregado','success');
    
    // Si estamos en el modal de detalles, cerrarlo
    if (currentProductDetail && currentProductDetail.id === productId) {
        bootstrap.Modal.getInstance(document.getElementById('productDetailModal')).hide();
    }
}

function updateCartCount(){
    const count = cart.reduce((t,i)=>t+i.quantity,0);
    const el = document.getElementById('floatingCartCount');
    el.textContent = count;
    el.style.transform='scale(1.2)';
    setTimeout(()=>el.style.transform='scale(1)',200);
    
    // Actualizar estado del botón de checkout
    updateCheckoutButton();
}

function updateCheckoutButton() {
    const checkoutBtn = document.getElementById('checkoutBtn');
    const submitOrderBtn = document.getElementById('submitOrderBtn');
    const floatingCart = document.getElementById('openCartBtn');
    
    if (cart.length === 0) {
        checkoutBtn.disabled = true;
        submitOrderBtn.disabled = true;
        floatingCart.disabled = true;
    } else {
        checkoutBtn.disabled = false;
        submitOrderBtn.disabled = false;
        floatingCart.disabled = false;
    }
}

function renderCart(){
    const container = document.getElementById('cartItems');
    container.innerHTML='';
    if(cart.length===0){
        container.innerHTML='<p class="text-center text-muted">Carrito vacío</p>'; 
        document.getElementById('cartTotal').textContent='$0.00 USD';
        updateCheckoutButton();
        return;
    }
    cart.forEach(item=>{
        const div=document.createElement('div');
        div.className='cart-item mb-2';
        div.innerHTML=`
            <div class="d-flex align-items-center">
                <img src="${getImageUrl(safeGet(item.imagen_url, ''))}" width="50" height="50" style="object-fit:cover; border-radius:5px; margin-right:10px">
                <div class="flex-grow-1">
                    <h6 class="mb-1">${safeGet(item.nombre, 'Producto')}</h6>
                    <div class="d-flex align-items-center">
                        <button class="btn btn-sm btn-outline-secondary me-2" onclick="updateCartItem(${item.id},-1)">-</button>
                        <span>${item.quantity}</span>
                        <button class="btn btn-sm btn-outline-secondary ms-2" onclick="updateCartItem(${item.id},1)">+</button>
                        <button class="btn btn-sm btn-outline-danger ms-2" onclick="removeFromCart(${item.id})"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
                <div><strong>${formatPrice(safeGet(item.precio, 0) * item.quantity)}</strong></div>
            </div>
        `;
        container.appendChild(div);
    });
    const total = cart.reduce((s,i)=>s+safeGet(i.precio, 0)*i.quantity,0);
    document.getElementById('cartTotal').textContent=formatPrice(total);
    updateCheckoutButton();
}

function updateCartItem(id,change){ 
    const item=cart.find(i=>i.id===id); 
    if(!item) return; 
    const product=products.find(p=>p.id===id); 
    item.quantity+=change; 
    if(item.quantity<=0) removeFromCart(id); 
    else if(item.quantity>safeGet(product.stock, 999)){ 
        item.quantity=safeGet(product.stock, 999); 
        showToast('No hay más stock','error'); 
    } 
    updateCartCount(); 
    renderCart(); 
    saveCart(); 
}

function removeFromCart(id){ 
    cart=cart.filter(i=>i.id!==id); 
    updateCartCount(); 
    renderCart(); 
    saveCart(); 
    showToast('Producto eliminado','success'); 
}

function saveCart(){ localStorage.setItem('cart',JSON.stringify(cart)); }
function loadCart(){ 
    const c=localStorage.getItem('cart'); 
    if(c) cart=JSON.parse(c); 
    updateCartCount(); 
    renderCart(); 
}

// Horarios
async function loadSchedule(){
    try{
        const data = await fetchFromAPI('horarios');
        horarios = data.schedule;
        checkStoreStatus();
    } catch(e){ console.error(e); }
}

function checkStoreStatus(){
    const now = new Date();
    const days = ['domingo','lunes','martes','miercoles','jueves','viernes','sabado'];
    const today = days[now.getDay()];
    const statusEl = document.getElementById('storeStatus');
    const horario = horarios.find(h=>h.dia_semana===today);
    if(!horario || !horario.esta_abierto){ 
        isStoreOpen=false; 
        statusEl.textContent='La tienda está cerrada'; 
        statusEl.className='store-status store-closed'; 
        statusEl.classList.remove('d-none'); 
        renderProducts(); // Actualizar productos para mostrar botones deshabilitados
        return;
    }
    const horaActual = now.getHours()*60+now.getMinutes();
    const apertura = parseInt(horario.hora_apertura.split(':')[0])*60+parseInt(horario.hora_apertura.split(':')[1]);
    const cierre = parseInt(horario.hora_cierre.split(':')[0])*60+parseInt(horario.hora_cierre.split(':')[1]);
    isStoreOpen = horaActual>=apertura && horaActual<=cierre;
    statusEl.textContent = isStoreOpen?'La tienda está abierta':'La tienda está cerrada';
    statusEl.className = `store-status ${isStoreOpen?'store-open':'store-closed'}`;
    statusEl.classList.remove('d-none');
    renderProducts(); // Actualizar productos para mostrar estado correcto
}

// Toast
function showToast(msg,type='success'){ 
    const container=document.getElementById('toastContainer'); 
    const toast=document.createElement('div'); 
    toast.className=`custom-toast ${type}`; 
    const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
    toast.innerHTML=`
        <i class="fas ${icon}"></i>
        <div>${msg}</div>
    `; 
    container.appendChild(toast); 
    setTimeout(()=>{
        toast.style.animation = 'slideInRight 0.3s ease reverse';
        setTimeout(()=>container.removeChild(toast),300);
    },3000); 
}

// Validación de formulario
function validateForm() {
    let isValid = true;
    
    // Validar nombre completo
    const nameInput = document.getElementById('customerName');
    if (nameInput.value.trim().length < 3) {
        nameInput.classList.add('is-invalid');
        isValid = false;
    } else {
        nameInput.classList.remove('is-invalid');
    }
    
    // Validar carnet
    const idCardInput = document.getElementById('customerIdCard');
    if (idCardInput.value.trim().length < 5) {
        idCardInput.classList.add('is-invalid');
        isValid = false;
    } else {
        idCardInput.classList.remove('is-invalid');
    }
    
    // Validar teléfono (mínimo 8 dígitos)
    const phoneInput = document.getElementById('customerPhone');
    const phoneDigits = phoneInput.value.replace(/\D/g, '');
    if (phoneDigits.length < 8) {
        phoneInput.classList.add('is-invalid');
        isValid = false;
    } else {
        phoneInput.classList.remove('is-invalid');
    }
    
    // Validar email si está lleno
    const emailInput = document.getElementById('customerEmail');
    if (emailInput.value.trim() && !emailInput.value.includes('@')) {
        emailInput.classList.add('is-invalid');
        isValid = false;
    } else {
        emailInput.classList.remove('is-invalid');
    }
    
    // Validar dirección si es entrega a domicilio
    const deliveryType = document.querySelector('input[name="deliveryType"]:checked').value;
    if (deliveryType === 'domicilio') {
        const addressInput = document.getElementById('customerAddress');
        if (addressInput.value.trim().length < 10) {
            addressInput.classList.add('is-invalid');
            isValid = false;
        } else {
            addressInput.classList.remove('is-invalid');
        }
    }
    
    return isValid;
}

// Generar comprobante de pedido mejorado
function generateReceipt() {
    const receiptContainer = document.getElementById('orderReceipt');
    const orderNumber = generateOrderNumber();
    const deliveryType = document.querySelector('input[name="deliveryType"]:checked').value;
    const name = document.getElementById('customerName').value.trim() || 'No especificado';
    const idCard = document.getElementById('customerIdCard').value.trim() || 'No especificado';
    const phone = document.getElementById('customerPhone').value.trim() || 'No especificado';
    const email = document.getElementById('customerEmail').value.trim();
    const address = deliveryType === 'domicilio' ? (document.getElementById('customerAddress').value.trim() || 'No especificada') : 'Retirar en local';
    const notes = document.getElementById('customerNotes').value.trim();
    
    let receiptHTML = `
        <div class="receipt-header">
            <div class="receipt-number">
                <i class="fas fa-hashtag me-2"></i>${orderNumber}
            </div>
            <div class="receipt-date">
                <i class="fas fa-calendar-alt me-2"></i>${new Date().toLocaleString()}
            </div>
        </div>
        
        <div class="receipt-section">
            <div class="receipt-section-title">
                <i class="fas fa-user"></i>
                Datos del Cliente
            </div>
            <div class="receipt-info">
                <div class="receipt-info-item">
                    <span class="receipt-info-label">Nombre:</span>
                    <span class="receipt-info-value">${name}</span>
                </div>
                <div class="receipt-info-item">
                    <span class="receipt-info-label">Carnet:</span>
                    <span class="receipt-info-value">${idCard}</span>
                </div>
                <div class="receipt-info-item">
                    <span class="receipt-info-label">Teléfono:</span>
                    <span class="receipt-info-value">${phone}</span>
                </div>
                ${email ? `
                <div class="receipt-info-item">
                    <span class="receipt-info-label">Email:</span>
                    <span class="receipt-info-value">${email}</span>
                </div>
                ` : ''}
                <div class="receipt-info-item">
                    <span class="receipt-info-label">Entrega:</span>
                    <span class="receipt-info-value">${deliveryType === 'domicilio' ? '🏠 A Domicilio' : '🏪 Retirar en Local'}</span>
                </div>
                ${deliveryType === 'domicilio' ? `
                <div class="receipt-info-item">
                    <span class="receipt-info-label">Dirección:</span>
                    <span class="receipt-info-value">${address}</span>
                </div>
                ` : ''}
            </div>
        </div>
        
        <div class="receipt-section">
            <div class="receipt-section-title">
                <i class="fas fa-box"></i>
                Productos
            </div>
            <div class="receipt-products">
    `;
    
    let total = 0;
    cart.forEach(item => {
        const subtotal = safeGet(item.precio, 0) * item.quantity;
        total += subtotal;
        receiptHTML += `
            <div class="receipt-product-item">
                <span class="receipt-product-name">${safeGet(item.nombre, 'Producto')}</span>
                <span class="receipt-product-quantity">x${item.quantity}</span>
                <span class="receipt-product-price">${formatPrice(subtotal)}</span>
            </div>
        `;
    });
    
    receiptHTML += `
            </div>
        </div>
        
        <div class="receipt-total">
            <span class="receipt-total-label">
                <i class="fas fa-calculator"></i>
                Total:
            </span>
            <span>${formatPrice(total)}</span>
        </div>
    `;
    
    if (notes) {
        receiptHTML += `
            <div class="receipt-notes">
                <strong><i class="fas fa-sticky-note me-2"></i>Notas:</strong> ${notes}
            </div>
        `;
    }
    
    receiptContainer.innerHTML = receiptHTML;
}

// Guardar pedido en la base de datos
async function saveOrderToDatabase(orderData) {
    try {
        // Primero, guardar el cliente si no existe
        let clientId = null;
        const customerResponse = await fetch('api/clientes.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                nombre: orderData.name,
                email: orderData.email || `${orderData.phone}@placeholder.com`,
                telefono: orderData.phone
            })
        });
        
        if (customerResponse.ok) {
            const customerResult = await customerResponse.json();
            clientId = customerResult.id;
        }
        
        // Luego, guardar el pedido
        const orderResponse = await fetch('api/pedidos.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                cliente_id: clientId,
                total: orderData.total,
                estado: 'pendiente',
                direccion_entrega: orderData.address,
                tipo_entrega: orderData.deliveryType,
                notas: orderData.notes
            })
        });
        
        if (orderResponse.ok) {
            const orderResult = await orderResponse.json();
            const orderId = orderResult.id;
            
            // Finalmente, guardar los detalles del pedido
            for (const item of orderData.items) {
                await fetch('api/detalles_pedido.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        pedido_id: orderId,
                        producto_id: item.id,
                        cantidad: item.quantity,
                        precio_unitario: item.price
                    })
                });
            }
            
            return { success: true, orderId };
        } else {
            throw new Error('Error al guardar el pedido');
        }
    } catch (error) {
        console.error('Error al guardar pedido en la base de datos:', error);
        return { success: false, error: error.message };
    }
}

// Checkout
async function processCheckout(){
    if(cart.length===0){
        showToast('El carrito está vacío. Agrega productos antes de finalizar.','error'); 
        return;
    }
    
    if (!validateForm()) {
        showToast('Por favor completa los campos obligatorios','error');
        return;
    }
    
    const orderNumber = generateOrderNumber();
    const name = document.getElementById('customerName').value.trim();
    const idCard = document.getElementById('customerIdCard').value.trim();
    const phone = document.getElementById('customerPhone').value.trim();
    const email = document.getElementById('customerEmail').value.trim();
    const deliveryType = document.querySelector('input[name="deliveryType"]:checked').value;
    const address = deliveryType === 'domicilio' ? document.getElementById('customerAddress').value.trim() : 'Retirar en local';
    const notes = document.getElementById('customerNotes').value.trim();
    
    // Preparar datos para guardar en la base de datos
    const total = cart.reduce((s,i)=>s+safeGet(i.precio, 0)*i.quantity,0);
    const orderData = {
        name,
        idCard,
        phone,
        email,
        deliveryType,
        address,
        notes,
        total,
        items: cart.map(item => ({
            id: item.id,
            quantity: item.quantity,
            price: safeGet(item.precio, 0)
        }))
    };
    
    // Guardar pedido en la base de datos
    const dbResult = await saveOrderToDatabase(orderData);
    
    // Construir mensaje WhatsApp con el formato solicitado
    let message = `${safeGet(storeSettings.name, 'Mi Tienda')}\n`;
    message += `Nuevo pedido\n`;
    message += `${orderNumber}\n\n`;
    message += `Nombre: ${safeGet(name, 'No especificado')}\n`;
    message += `Carnet: ${safeGet(idCard, 'No especificado')}\n`;
    message += `Teléfono: ${safeGet(phone, 'No especificado')}\n`;
    if (email) message += `Email: ${safeGet(email, '')}\n`;
    message += `Tipo de Entrega: ${deliveryType === 'domicilio' ? 'A Domicilio' : 'Retirar en Local'}\n`;
    if (deliveryType === 'domicilio') {
        message += `Dirección: ${safeGet(address, 'No especificada')}\n`;
    }
    message += `\nProductos:\n`;
    cart.forEach(item=>{ 
        message += `${safeGet(item.nombre, 'Producto')} x${item.quantity}: ${formatPrice(safeGet(item.precio, 0)*item.quantity)}\n`; 
    });
    message += `\nTotal: ${formatPrice(total)}\n`;
    if(notes) message += `\nNotas: ${safeGet(notes, '')}`;
    message += `\n\nFecha: ${new Date().toLocaleString()}`;
    
    const phoneNumber = safeGet(storeSettings.whatsapp, '').replace(/[^\d]/g,'');
    if (phoneNumber) {
        window.open(`https://wa.me/${phoneNumber}?text=${encodeURIComponent(message)}`,'_blank');
    } else {
        showToast('No hay número de WhatsApp configurado','error');
        return;
    }
    
    cart=[]; updateCartCount(); renderCart(); saveCart();
    document.getElementById('checkoutForm').reset();
    bootstrap.Modal.getInstance(document.getElementById('checkoutModal')).hide();
    
    if (dbResult.success) {
        showToast(`✅ Pedido ${orderNumber} enviado a WhatsApp y guardado en el sistema`,'success');
    } else {
        showToast(`✅ Pedido ${orderNumber} enviado a WhatsApp, pero hubo un error al guardarlo en el sistema: ${dbResult.error}`,'error');
    }
}

// Eventos
document.getElementById('openCartBtn').addEventListener('click',()=>{
    if (cart.length === 0) {
        showToast('El carrito está vacío. Agrega productos antes de continuar.','error');
        return;
    }
    document.getElementById('cartSidebar').classList.add('active'); 
    document.getElementById('overlay').style.display='block';
});

document.getElementById('closeCart').addEventListener('click',()=>{
    document.getElementById('cartSidebar').classList.remove('active'); 
    document.getElementById('overlay').style.display='none';
});

document.getElementById('overlay').addEventListener('click',()=>{
    document.getElementById('cartSidebar').classList.remove('active'); 
    document.getElementById('overlay').style.display='none';
});

// Eventos para el modal de detalles del producto
document.getElementById('decreaseQuantity').addEventListener('click', function() {
    const input = document.getElementById('detailProductQuantity');
    const currentValue = parseInt(input.value) || 1;
    if (currentValue > 1) {
        input.value = currentValue - 1;
    }
});

document.getElementById('increaseQuantity').addEventListener('click', function() {
    const input = document.getElementById('detailProductQuantity');
    const currentValue = parseInt(input.value) || 1;
    const maxStock = safeGet(currentProductDetail.stock, 999);
    if (currentValue < maxStock) {
        input.value = currentValue + 1;
    } else {
        showToast('No hay más stock disponible', 'error');
    }
});

document.getElementById('addToCartFromDetail').addEventListener('click', function() {
    if (currentProductDetail) {
        const quantity = parseInt(document.getElementById('detailProductQuantity').value) || 1;
        addToCart(currentProductDetail.id, quantity);
    }
});

// Evento para mostrar/ocultar dirección según tipo de entrega
document.querySelectorAll('input[name="deliveryType"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const addressSection = document.getElementById('addressSection');
        if (this.value === 'domicilio') {
            addressSection.style.display = 'block';
        } else {
            addressSection.style.display = 'none';
        }
        // Actualizar comprobante si el modal está abierto
        if (document.getElementById('checkoutModal').classList.contains('show')) {
            generateReceipt();
        }
    });
});

// Evento para actualizar comprobante cuando se abre el modal
document.getElementById('checkoutModal').addEventListener('show.bs.modal', function () {
    generateReceipt();
});

// Actualizar comprobante en tiempo real
['customerName', 'customerIdCard', 'customerPhone', 'customerEmail', 'customerAddress', 'customerNotes'].forEach(id => {
    document.getElementById(id).addEventListener('input', function() {
        if (document.getElementById('checkoutModal').classList.contains('show')) {
            generateReceipt();
        }
    });
});

// Funcionalidad de búsqueda
document.getElementById('searchInput').addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    const filteredProducts = products.filter(p => 
        safeGet(p.nombre, '').toLowerCase().includes(searchTerm) || 
        safeGet(p.descripcion, '').toLowerCase().includes(searchTerm)
    );
    
    const container = document.getElementById('productGrid');
    container.innerHTML = '';
    
    if (filteredProducts.length === 0) {
        container.innerHTML = '<div class="col-12 text-center">No se encontraron productos</div>';
        return;
    }
    
    const isDisabled = !isStoreOpen;
    filteredProducts.forEach(p => {
        const div = document.createElement('div');
        div.className = 'product-card-enter';
        
        // Obtener nombre de categoría
        const category = categories.find(c => c.id === p.categoria_id);
        const categoryName = category ? category.nombre : 'Sin categoría';
        
        div.innerHTML = `
            <div class="card product-card" onclick="showProductDetail(${p.id})">
                <div class="card-img-container">
                    <img src="${getImageUrl(safeGet(p.imagen_url, ''))}" class="card-img-top" alt="${safeGet(p.nombre, 'Producto')}" loading="lazy">
                </div>
                <div class="card-body">
                    <h6 class="card-title">${safeGet(p.nombre, 'Producto sin nombre')}</h6>
                    <p class="card-text">${safeGet(p.descripcion, 'Sin descripción')}</p>
                    <p class="price"><strong>${formatPrice(safeGet(p.precio, 0))}</strong></p>
                    <button class="btn btn-primary w-100" onclick="event.stopPropagation(); addToCart(${p.id})" ${isDisabled ? 'disabled' : ''}>
                        <i class="fas fa-cart-plus me-2"></i>
                        ${isDisabled ? 'Tienda Cerrada' : 'Agregar al carrito'}
                    </button>
                </div>
            </div>
        `;
        container.appendChild(div);
    });
});

// Inicialización
loadCart();
loadCategories();
loadProducts();
loadSchedule();
</script>
</body>
</html>
