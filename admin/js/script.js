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
        
        // Iniciar polling para nuevas notificaciones
        this.startPolling();
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
        setTimeout(() => {
            this.removeNotification(notification.id);
        }, duration);
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
    }
    
    removeNotification(id) {
        const element = this.container.querySelector(`[data-id="${id}"]`);
        if (element) {
            element.style.animation = 'slideOutRight 0.3s ease-out';
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
    
    async startPolling() {
        // Polling cada 30 segundos para nuevas notificaciones
        setInterval(async () => {
            try {
                const response = await fetch('api/get_notifications.php');
                if (response.ok) {
                    const notifications = await response.json();
                    notifications.forEach(n => {
                        if (!this.notifications.find(existing => existing.id === n.id)) {
                            this.addNotification(n.message, n.type);
                        }
                    });
                }
            } catch (error) {
                console.error('Error checking notifications:', error);
            }
        }, 30000);
    }
}

// Inicializar sistema de notificaciones
const notificationSystem = new NotificationSystem();

// Reemplazar la función showToast existente
function showToast(message, type = 'info') {
    notificationSystem.addNotification(message, type);
}

// --- UI & NAVIGATION ---
function showPage(pageId) {
    document.querySelectorAll('.page-content').forEach(p => p.style.display = 'none');
    document.querySelectorAll('.sidebar-item').forEach(i => i.classList.remove('active'));
    const page = document.getElementById(pageId + 'Page');
    if(page) {
        page.style.display = 'block';
        document.querySelector(`[data-page="${pageId}"]`).classList.add('active');
        document.getElementById('pageTitle').textContent = document.querySelector(`[data-page="${pageId}"] span`).textContent;
        
        // Inicializar filtros si es la página de productos
        if (pageId === 'products') {
            initializeProductFilters();
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
            // Validar tipo de archivo
            if (!file.type.startsWith('image/')) {
                showToast('Por favor, selecciona un archivo de imagen válido', 'danger');
                input.value = '';
                return;
            }
            
            // Validar tamaño (5MB máximo)
            if (file.size > 5 * 1024 * 1024) {
                showToast('El archivo es demasiado grande. Máximo 5MB', 'danger');
                input.value = '';
                return;
            }
            
            // MOSTRAR VISTA PREVIA LOCAL INMEDIATAMENTE
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
                nameDisplay.textContent = file.name;
                
                // Guardar la vista previa en un atributo para uso posterior
                preview.dataset.localPreview = e.target.result;
            };
            reader.readAsDataURL(file);
            
            // Subir archivo en segundo plano
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
            showToast(result.message || 'Error al subir la imagen', 'danger');
        }
    } catch (error) {
        showToast('Error al subir la imagen', 'danger');
    } finally {
        showLoading(false);
    }
}

// --- PRODUCT FILTERING ---
function initializeProductFilters() {
    // Configurar filtros
    document.getElementById('productSearch').addEventListener('input', filterProducts);
    document.getElementById('categoryFilter').addEventListener('change', filterProducts);
    document.getElementById('stockFilter').addEventListener('change', filterProducts);
    document.getElementById('sortBy').addEventListener('change', filterProducts);
    
    // Llenar filtro de categorías
    const categoryFilter = document.getElementById('categoryFilter');
    categoryFilter.innerHTML = '<option value="">Todas las categorías</option>';
    categories.forEach(cat => {
        const option = document.createElement('option');
        option.value = cat.id;
        option.textContent = cat.nombre;
        categoryFilter.appendChild(option);
    });
    
    // Inicializar productos filtrados
    filteredProducts = [...products];
    updateProductsTable();
}

function filterProducts() {
    const searchTerm = document.getElementById('productSearch').value.toLowerCase();
    const categoryFilter = document.getElementById('categoryFilter').value;
    const stockFilter = document.getElementById('stockFilter').value;
    const sortBy = document.getElementById('sortBy').value;
    
    // Filtrar productos
    filteredProducts = products.filter(product => {
        // Búsqueda por nombre
        if (searchTerm && !product.nombre.toLowerCase().includes(searchTerm)) {
            return false;
        }
        
        // Filtro por categoría
        if (categoryFilter && product.categoria_id != categoryFilter) {
            return false;
        }
        
        // Filtro por stock
        if (stockFilter === 'instock' && product.stock <= 0) {
            return false;
        }
        if (stockFilter === 'outofstock' && product.stock > 0) {
            return false;
        }
        if (stockFilter === 'lowstock' && product.stock >= 10) {
            return false;
        }
        
        return true;
    });
    
    // Ordenar productos
    switch(sortBy) {
        case 'name':
            filteredProducts.sort((a, b) => a.nombre.localeCompare(b.nombre));
            break;
        case 'price-asc':
            filteredProducts.sort((a, b) => parseFloat(a.precio) - parseFloat(b.precio));
            break;
        case 'price-desc':
            filteredProducts.sort((a, b) => parseFloat(b.precio) - parseFloat(a.precio));
            break;
        case 'stock':
            filteredProducts.sort((a, b) => parseInt(b.stock) - parseInt(a.stock));
            break;
    }
    
    currentPage = 1;
    updateProductsTable();
}

function updateProductsTable() {
    const tbody = document.getElementById('productsTableBody');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    // Calcular paginación
    const startIndex = (currentPage - 1) * productsPerPage;
    const endIndex = startIndex + productsPerPage;
    const paginatedProducts = filteredProducts.slice(startIndex, endIndex);
    
    // Mostrar productos
    paginatedProducts.forEach(product => {
        const row = document.createElement('tr');
        
        // Determinar imagen a mostrar
        let imageSrc = 'https://picsum.photos/seed/product' + product.id + '/50/50.jpg';
        if (product.imagen_url) {
            // Si es una URL completa o relativa
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
                <div class="btn-group" role="group">
                    <button class="btn btn-sm btn-outline-primary" onclick="editProduct(${product.id})" title="Editar">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteProduct(${product.id})" title="Eliminar">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        `;
        tbody.appendChild(row);
    });
    
    // Actualizar contador y paginación
    document.getElementById('productCount').textContent = 
        `Mostrando ${startIndex + 1}-${Math.min(endIndex, filteredProducts.length)} de ${filteredProducts.length} productos`;
    
    updatePagination();
}

function updatePagination() {
    const totalPages = Math.ceil(filteredProducts.length / productsPerPage);
    const pagination = document.getElementById('productPagination');
    
    pagination.innerHTML = '';
    
    // Botón anterior
    const prevLi = document.createElement('li');
    prevLi.className = `page-item ${currentPage === 1 ? 'disabled' : ''}`;
    prevLi.innerHTML = `<a class="page-link" href="#" onclick="changePage(${currentPage - 1}); return false;">Anterior</a>`;
    pagination.appendChild(prevLi);
    
    // Números de página
    for (let i = 1; i <= totalPages; i++) {
        if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
            const li = document.createElement('li');
            li.className = `page-item ${i === currentPage ? 'active' : ''}`;
            li.innerHTML = `<a class="page-link" href="#" onclick="changePage(${i}); return false;">${i}</a>`;
            pagination.appendChild(li);
        } else if (i === currentPage - 2 || i === currentPage + 2) {
            const li = document.createElement('li');
            li.className = 'page-item disabled';
            li.innerHTML = '<a class="page-link" href="#">...</a>';
            pagination.appendChild(li);
        }
    }
    
    // Botón siguiente
    const nextLi = document.createElement('li');
    nextLi.className = `page-item ${currentPage === totalPages ? 'disabled' : ''}`;
    nextLi.innerHTML = `<a class="page-link" href="#" onclick="changePage(${currentPage + 1}); return false;">Siguiente</a>`;
    pagination.appendChild(nextLi);
}

function changePage(page) {
    const totalPages = Math.ceil(filteredProducts.length / productsPerPage);
    if (page >= 1 && page <= totalPages) {
        currentPage = page;
        updateProductsTable();
    }
}

// --- DATA LOADING FUNCTIONS ---
async function loadData() {
    if (isLoadingData) return;
    isLoadingData = true;
    
    showLoading(true);
    try {
        console.log('Iniciando carga de datos...');
        
        // Cargar categorías
        try {
            console.log('Cargando categorías...');
            const categoriesResponse = await fetch('api/get_categories.php');
            console.log('Respuesta categorías:', categoriesResponse.status);
            
            if (!categoriesResponse.ok) {
                throw new Error(`HTTP ${categoriesResponse.status}: ${categoriesResponse.statusText}`);
            }
            
            categories = await categoriesResponse.json();
            console.log('Categorías cargadas:', categories.length);
        } catch (error) {
            console.error('Error cargando categorías:', error);
            showToast('Error al cargar categorías: ' + error.message, 'danger');
            categories = [];
        }
        
        // Cargar productos
        try {
            console.log('Cargando productos...');
            const productsResponse = await fetch('api/products.php');
            console.log('Respuesta productos:', productsResponse.status);
            
            if (!productsResponse.ok) {
                throw new Error(`HTTP ${productsResponse.status}: ${productsResponse.statusText}`);
            }
            
            products = await productsResponse.json();
            console.log('Productos cargados:', products.length);
        } catch (error) {
            console.error('Error cargando productos:', error);
            showToast('Error al cargar productos: ' + error.message, 'danger');
            products = [];
        }
        
        // Cargar usuarios
        try {
            console.log('Cargando usuarios...');
            const usersResponse = await fetch('api/get_users.php');
            console.log('Respuesta usuarios:', usersResponse.status);
            
            if (!usersResponse.ok) {
                throw new Error(`HTTP ${usersResponse.status}: ${usersResponse.statusText}`);
            }
            
            users = await usersResponse.json();
            console.log('Usuarios cargados:', users.length);
        } catch (error) {
            console.error('Error cargando usuarios:', error);
            showToast('Error al cargar usuarios: ' + error.message, 'danger');
            users = [];
        }
        
        // Cargar pedidos
        try {
            console.log('Cargando pedidos...');
            const ordersResponse = await fetch('api/get_orders.php');
            console.log('Respuesta pedidos:', ordersResponse.status);
            
            if (!ordersResponse.ok) {
                throw new Error(`HTTP ${ordersResponse.status}: ${ordersResponse.statusText}`);
            }
            
            orders = await ordersResponse.json();
            console.log('Pedidos cargados:', orders.length);
        } catch (error) {
            console.error('Error cargando pedidos:', error);
            showToast('Error al cargar pedidos: ' + error.message, 'danger');
            orders = [];
        }
        
        // Cargar clientes
        try {
            console.log('Cargando clientes...');
            const customersResponse = await fetch('api/get_customers.php');
            console.log('Respuesta clientes:', customersResponse.status);
            
            if (!customersResponse.ok) {
                throw new Error(`HTTP ${customersResponse.status}: ${customersResponse.statusText}`);
            }
            
            customers = await customersResponse.json();
            console.log('Clientes cargados:', customers.length);
        } catch (error) {
            console.error('Error cargando clientes:', error);
            showToast('Error al cargar clientes: ' + error.message, 'danger');
            customers = [];
        }
        
        // Inicializar productos filtrados
        filteredProducts = [...products];
        
        // Actualizar la interfaz
        updateDashboard(); 
        updateProductsTable(); 
        updateUsersTable(); 
        updateCategorySelects();
        updateCategoriesList();
        updateCustomersTable();
        updateOrdersTable();
        loadScheduleList();
        
        showToast('Datos cargados correctamente', 'success');
        
    } catch (error) { 
        console.error('Error general cargando datos:', error); 
        showToast('Error al cargar los datos: ' + error.message, 'danger'); 
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
                    No hay horarios configurados. Usa los controles below para configurar los horarios de cada día.
                </div>
            `;
            
            // Crear horarios vacíos para cada día
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
        
        // Agregar event listeners para los switches
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
        showToast('Error al cargar horarios: ' + error.message, 'danger');
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
    updateCharts(); 
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
        
        // Calcular estadísticas del cliente
        const totalOrders = customer.total_orders || 0;
        const totalSpent = parseFloat(customer.total_spent || 0);
        const lastOrder = customer.last_order ? new Date(customer.last_order).toLocaleDateString() : 'N/A';
        
        // Determinar tipo de cliente
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

// --- CHARTS ---
function updateCharts() { 
    if (typeof Chart === 'undefined') {
        console.error('La librería Chart.js no se ha cargado correctamente.');
        return;
    }

    if (window.chartsUpdating) {
        return;
    }
    window.chartsUpdating = true;

    try {
        // Gráfica de Categorías (se mantiene como doughnut)
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
            
            if(window.categoriesChart && typeof window.categoriesChart.destroy === 'function') {
                window.categoriesChart.destroy(); 
                window.categoriesChart = null;
            }
            
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
            
            if(window.ordersChart && typeof window.ordersChart.destroy === 'function') {
                window.ordersChart.destroy();
                window.ordersChart = null;
            }
            
            if (labels.length > 0) {
                window.ordersChart = new Chart(ordersCtx, {
                    type: 'bar', // CAMBIADO A BARRAS
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
        initializeAdvancedCharts();
    } catch (error) {
        console.error('Error al actualizar gráficos:', error);
    } finally {
        setTimeout(() => {
            window.chartsUpdating = false;
        }, 100);
    }
}

// --- ADVANCED CHARTS ---
function initializeAdvancedCharts() {
    updateSalesTrendChart();
    updateTopProductsChart();
    updateRevenueChart();
}

function updateSalesTrendChart() {
    const ctx = document.getElementById('salesTrendChart');
    if (!ctx) return;
    
    // Preparar datos de tendencia
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

function updateTopProductsChart() {
    const ctx = document.getElementById('topProductsChart');
    if (!ctx) return;
    
    // Calcular productos más vendidos
    const productSales = {};
    
    // Aquí necesitarías cargar los detalles de los pedidos
    // Por ahora, usaremos datos de ejemplo
    products.slice(0, 5).forEach((product, index) => {
        productSales[product.nombre] = Math.floor(Math.random() * 50) + 10;
    });
    
    const sortedProducts = Object.entries(productSales)
        .sort((a, b) => b[1] - a[1])
        .slice(0, 5);
    
    if (window.topProductsChart) {
        window.topProductsChart.destroy();
    }
    
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

function updateRevenueChart() {
    const ctx = document.getElementById('revenueChart');
    if (!ctx) return;
    
    // Preparar datos de ingresos mensuales
    const monthlyRevenue = {};
    const monthNames = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    
    // Inicializar todos los meses con 0
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
    
    // Generar y descargar reporte en Excel
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
    
    // Calcular promedios
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
        showToast('El texto de confirmación es incorrecto', 'danger');
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
            body: JSON.stringify({ confirmText: confirmText })
        });
        
        const result = await response.json();
        
        if (result.success) {
            bootstrap.Modal.getInstance(document.getElementById('cleanDatabaseModal')).hide();
            document.getElementById('confirmText').value = '';
            await loadData();
            showToast('Base de datos limpiada correctamente', 'success');
        } else {
            showToast(result.message || 'Error al limpiar la base de datos', 'danger');
        }
    } catch (error) {
        showToast('Error al limpiar la base de datos', 'danger');
    } finally {
        showLoading(false);
    }
}

// --- FORM HANDLERS ---
document.getElementById('addProductForm')?.addEventListener('submit', async function(e){ 
    e.preventDefault(); 
    
    // Determinar si es edición o creación
    const productId = this.dataset.productId;
    const isEditing = !!productId;
    
    // Recopilar datos manualmente
    const data = {
        nombre: document.getElementById('productName').value,
        categoria_id: document.getElementById('productCategory').value,
        marca: document.getElementById('productBrand').value,
        precio: document.getElementById('productPrice').value,
        stock: document.getElementById('productStock').value,
        descripcion: document.getElementById('productDescription').value,
        imagen_url: document.getElementById('productImage').value
    };
    
    // Validar datos requeridos
    if (!data.nombre || !data.precio) {
        showToast('Por favor, completa los campos obligatorios (nombre y precio)', 'danger');
        return;
    }
    
    try { 
        showLoading(true);
        
        const url = isEditing ? 'api/update_product.php' : 'api/add_product.php';
        const method = 'POST';
        
        // Si es edición, agregar el ID
        if (isEditing) {
            data.id = productId;
        }
        
        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            this.reset(); 
            // Limpiar campos de imagen
            document.getElementById('productImagePreview').style.display = 'none';
            document.getElementById('productImagePreview').src = '';
            document.getElementById('productImageName').textContent = 'Ningún archivo seleccionado';
            document.getElementById('productImage').value = '';
            
            // Restaurar título del modal
            document.querySelector('#addProductModal .modal-title').textContent = 'Agregar Producto';
            document.querySelector('#addProductForm button[type="submit"]').textContent = 'Agregar Producto';
            delete this.dataset.productId;
            
            bootstrap.Modal.getInstance(document.getElementById('addProductModal')).hide(); 
            await loadData();
            showToast(isEditing ? 'Producto actualizado correctamente' : 'Producto agregado correctamente', 'success'); 
        } else {
            showToast(result.message || 'Error al guardar el producto', 'danger');
        }
    } catch (error) { 
        console.error('Error:', error);
        showToast('Error de conexión al guardar el producto', 'danger'); 
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
        password:formData.get('newUserPassword') 
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
        
        const result = await response.json();
        
        if (result.success) {
            this.reset(); 
            bootstrap.Modal.getInstance(document.getElementById('addUserModal')).hide(); 
            await loadData();
            showToast('Usuario agregado','success'); 
        } else {
            showToast(result.message || 'Error al agregar usuario', 'danger');
        }
    }catch(error){ 
        showToast('Error de conexión.','danger'); 
    } finally { 
        showLoading(false); 
    } 
});

document.getElementById('addCategoryForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    // Determinar si es edición o creación
    const categoryId = this.dataset.categoryId;
    const isEditing = !!categoryId;
    
    const data = {
        nombre: document.getElementById('categoryName').value,
        descripcion: document.getElementById('categoryDescription').value,
        color: document.getElementById('categoryColor').value
    };
    
    // Validar datos requeridos
    if (!data.nombre) {
        showToast('Por favor, ingresa el nombre de la categoría', 'danger');
        return;
    }
    
    try {
        showLoading(true);
        
        const url = isEditing ? 'api/update_category.php' : 'api/add_category.php';
        const method = 'POST';
        
        // Si es edición, agregar el ID
        if (isEditing) {
            data.id = categoryId;
        }
        
        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            bootstrap.Modal.getInstance(document.getElementById('addCategoryModal')).hide();
            this.reset();
            
            // Restaurar título del modal
            document.querySelector('#addCategoryModal .modal-title').textContent = 'Agregar Categoría';
            document.querySelector('#addCategoryForm button[type="submit"]').textContent = 'Agregar Categoría';
            delete this.dataset.categoryId;
            
            await loadData();
            showToast(isEditing ? 'Categoría actualizada exitosamente' : 'Categoría agregada exitosamente', 'success');
        } else {
            showToast(result.message || 'Error al guardar la categoría', 'danger');
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('Error al guardar la categoría', 'danger');
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
    }; 
    if(data.new_password && data.new_password!==document.getElementById('profileConfirmPassword').value){ 
        showToast('Las nuevas contraseñas no coinciden.','danger'); 
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
        
        const result = await response.json();
        
        if (result.success) {
            document.getElementById('usernameDisplay').textContent = data.nombre_completo;
            document.getElementById('userEmail').textContent = data.email;
            showToast('Perfil actualizado','success'); 
        } else {
            showToast(result.message || 'Error al actualizar perfil', 'danger');
        }
    }catch(error){ 
        showToast(error.message,'danger'); 
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
        currency:document.getElementById('currency').value 
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
        
        const result = await response.json();
        
        if (result.success) {
            showToast('Configuración guardada','success'); 
        } else {
            showToast(result.message || 'Error al guardar configuración', 'danger');
        }
    }catch(error){ 
        showToast('Error de conexión.','danger'); 
    } finally { 
        showLoading(false); 
    } 
});

async function saveAllSchedule(){ 
    const days = ['lunes','martes','miercoles','jueves','viernes','sabado','domingo']; 
    const scheduleData = []; 
    
    // Validar que todos los días tengan configuración
    let hasError = false;
    
    days.forEach(day => { 
        const isEnabled = document.getElementById(`${day}Enabled`)?.checked || false;
        const openTime = document.getElementById(`${day}Open`)?.value || null;
        const closeTime = document.getElementById(`${day}Close`)?.value || null;
        
        // Si está habilitado, validar que tenga horas
        if (isEnabled && (!openTime || !closeTime)) {
            showToast(`Por favor, configura las horas de apertura y cierre para ${day}`, 'danger');
            hasError = true;
            return;
        }
        
        // Validar que la hora de cierre sea posterior a la de apertura
        if (isEnabled && openTime && closeTime && openTime >= closeTime) {
            showToast(`La hora de cierre debe ser posterior a la de apertura para ${day}`, 'danger');
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
            body: JSON.stringify({schedule: scheduleData})
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const result = await response.json();
        
        if (result.success) {
            showToast('Horarios guardados correctamente', 'success');
            // Recargar horarios para actualizar la interfaz
            await loadScheduleList();
        } else {
            showToast(result.message || 'Error al guardar horarios', 'danger');
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('Error de conexión al guardar horarios', 'danger'); 
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
                ]
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('Horarios restablecidos correctamente', 'success');
            await loadScheduleList();
        } else {
            showToast(result.message || 'Error al restablecer horarios', 'danger');
        }
    } catch (error) {
        showToast('Error al restablecer horarios', 'danger');
    } finally {
        showLoading(false);
    }
}

// --- PLACEHOLDER FUNCTIONS ---
async function editProduct(id){ 
    try {
        showLoading(true);
        const response = await fetch(`api/get_product.php?id=${id}`);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const product = await response.json();
        
        if (product) {
            // Llenar el formulario con los datos del producto
            document.getElementById('productName').value = product.nombre || '';
            document.getElementById('productCategory').value = product.categoria_id || '';
            document.getElementById('productBrand').value = product.marca || '';
            document.getElementById('productPrice').value = product.precio || '';
            document.getElementById('productStock').value = product.stock || '';
            document.getElementById('productDescription').value = product.descripcion || '';
            document.getElementById('productImage').value = product.imagen_url || '';
            
            // Manejar la imagen existente
            const preview = document.getElementById('productImagePreview');
            const nameDisplay = document.getElementById('productImageName');
            
            if (product.imagen_url_full) {
                // Usar la URL completa de la imagen
                preview.src = product.imagen_url_full;
                preview.style.display = 'block';
                nameDisplay.textContent = 'Imagen actual';
                
                // Guardar la URL completa para referencia
                preview.dataset.currentImage = product.imagen_url_full;
            } else {
                // Si no hay imagen, mostrar placeholder
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
            
            const modal = new bootstrap.Modal(document.getElementById('addProductModal'));
            modal.show();
        } else {
            showToast(product.message || 'Producto no encontrado', 'danger');
        }
    } catch (error) {
        console.error('Error al cargar producto:', error);
        showToast('Error al cargar producto', 'danger');
    } finally {
        showLoading(false);
    }
}

async function editCategory(id){ 
    try {
        showLoading(true);
        const response = await fetch(`api/get_category.php?id=${id}`);
        const category = await response.json();
        
        if (category) {
            document.getElementById('categoryName').value = category.nombre;
            document.getElementById('categoryDescription').value = category.descripcion;
            document.getElementById('categoryColor').value = category.color;
            
            // Cambiar el título del modal y el botón
            document.querySelector('#addCategoryModal .modal-title').textContent = 'Editar Categoría';
            document.querySelector('#addCategoryForm button[type="submit"]').textContent = 'Actualizar Categoría';
            
            // Agregar ID de la categoría al formulario
            document.getElementById('addCategoryForm').dataset.categoryId = id;
            
            const modal = new bootstrap.Modal(document.getElementById('addCategoryModal'));
            modal.show();
        } else {
            showToast('Categoría no encontrada', 'danger');
        }
    } catch (error) {
        showToast('Error al cargar categoría', 'danger');
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
                body: JSON.stringify({id: id})
            });
            
            const result = await response.json();
            
            if (result.success) {
                await loadData();
                showToast('Categoría eliminada correctamente', 'success');
            } else {
                showToast(result.message || 'Error al eliminar categoría', 'danger');
            }
        } catch (error) {
            showToast('Error al eliminar categoría', 'danger');
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
                body: JSON.stringify({id: id})
            });
            
            const result = await response.json();
            
            if (result.success) {
                await loadData();
                showToast('Usuario eliminado correctamente', 'success');
            } else {
                showToast(result.message || 'Error al eliminar usuario', 'danger');
            }
        } catch (error) {
            showToast('Error al eliminar usuario', 'danger');
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
                status: newStatus
            })
        });
        
        const result = await response.json();
        if (result.success) {
            showToast('Estado del pedido actualizado', 'success');
            
            // Enviar notificación al cliente
            if (result.notification_sent) {
                showToast('Notificación enviada al cliente', 'info');
            }
        } else {
            showToast('Error al actualizar estado', 'error');
        }
    } catch (error) {
        showToast('Error de conexión', 'error');
    }
}

function viewOrderDetails(orderId) {
    // Implementar modal con detalles del pedido
    const order = orders.find(o => o.id === orderId);
    if (!order) return;
    
    // Crear modal dinámicamente
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
                                <!-- Se cargarán los detalles aquí -->
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
    
    // Cargar detalles del pedido
    loadOrderDetails(orderId);
    
    // Limpiar modal al cerrar
    modal.addEventListener('hidden.bs.modal', () => {
        modal.remove();
    });
}

async function loadOrderDetails(orderId) {
    try {
        const response = await fetch(`api/get_order_details.php?id=${orderId}`);
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
    }
}

function printOrder(orderId) {
    // Implementar función de impresión
    window.open(`api/print_order.php?id=${orderId}`, '_blank');
}

// --- CUSTOMER MANAGEMENT ---
function viewCustomerDetails(customerId) {
    const customer = customers.find(c => c.id === customerId);
    if (!customer) return;
    
    // Crear modal con detalles del cliente
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
                                <!-- Se cargarán los pedidos aquí -->
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
    
    // Cargar pedidos del cliente
    loadCustomerOrders(customerId);
    
    modal.addEventListener('hidden.bs.modal', () => {
        modal.remove();
    });
}

async function loadCustomerOrders(customerId) {
    try {
        const response = await fetch(`api/get_customer_orders.php?id=${customerId}`);
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
    
    // Crear modal para enviar email
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
                send_copy: sendCopy
            })
        });
        
        const result = await response.json();
        if (result.success) {
            showToast('Email enviado correctamente', 'success');
            bootstrap.Modal.getInstance(document.querySelector('.modal.show')).hide();
        } else {
            showToast('Error al enviar email: ' + result.message, 'error');
        }
    } catch (error) {
        showToast('Error de conexión al enviar email', 'error');
    }
}

// --- EVENT LISTENERS ---
document.addEventListener('DOMContentLoaded', function(){
    checkPermissions(); 
    loadData();
    
    // Setup file uploads
    setupFileUpload('productImageFile', 'productImagePreview', 'productImageName', 'productImage');
    setupFileUpload('storeLogoFile', 'storeLogoPreview', 'storeLogoName', 'storeLogo');
    
    // Setup clean database modal
    document.getElementById('confirmText')?.addEventListener('input', function(e) {
        const confirmBtn = document.getElementById('confirmCleanBtn');
        confirmBtn.disabled = e.target.value !== 'LIMPIAR_BASE_DE_DATOS';
    });
    
    document.getElementById('confirmCleanBtn')?.addEventListener('click', cleanDatabase);
    
    // Sidebar navigation
    document.querySelectorAll('.sidebar-item[data-page]').forEach(item=>{ 
        item.addEventListener('click', e=>{ 
            e.preventDefault(); 
            showPage(item.dataset.page); 
        }); 
    });

    // Mobile sidebar controls
    document.getElementById('mobileToggle').addEventListener('click', openMobileSidebar);
    document.getElementById('mobileOverlay').addEventListener('click', closeMobileSidebar);

    // Logout button
    document.getElementById('logoutBtn')?.addEventListener('click', logout);

    // Update time
    setInterval(()=>{ 
        const now=new Date(); 
        document.getElementById('currentTime').textContent=now.toLocaleString(); 
    },1000);
    
    // Handle window resize
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if (window.innerWidth > 768) {
                closeMobileSidebar();
            }
        }, 250);
    });
    
    // Handle touch gestures for mobile sidebar
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
});

// Manejar redimensionamiento de ventana para gráficos
let resizeTimeout;
window.addEventListener('resize', function() {
    clearTimeout(resizeTimeout);
    resizeTimeout = setTimeout(function() {
        if (window.categoriesChart) {
            window.categoriesChart.resize();
        }
        if (window.ordersChart) {
            window.ordersChart.resize();
        }
        if (window.salesTrendChart) {
            window.salesTrendChart.resize();
        }
        if (window.topProductsChart) {
            window.topProductsChart.resize();
        }
        if (window.revenueChart) {
            window.revenueChart.resize();
        }
    }, 250);
});