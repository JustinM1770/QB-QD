// Lógica para el Asistente de IA - Editor de Menú Completo

let negocioId = null;
let currentContext = {};
let conversationHistory = [];
let parsedProducts = []; // Productos parseados del menú
let selectedImages = []; // Imágenes seleccionadas para análisis
let editingIndex = -1; // Índice del producto siendo editado
let currentFilter = 'all'; // Filtro de categoría actual

// Inicializar
window.addEventListener('load', async () => {
    const urlParams = new URLSearchParams(window.location.search);
    negocioId = urlParams.get('negocio_id') || sessionStorage.getItem('negocio_id');
    
    if (!negocioId) {
        negocioId = prompt('Por favor, ingresa el ID de tu negocio:');
        if (negocioId) {
            sessionStorage.setItem('negocio_id', negocioId);
        }
    }
    
    if (negocioId) {
        await loadInitialData();
    } else {
        addBotMessage('⚠️ Necesito el ID de tu negocio para comenzar. Por favor, recarga la página e ingrésalo.');
    }
    
    // Inicializar eventos del modal de upload
    initUploadEvents();
    
    // Inicializar eventos del formulario de edición
    initEditFormEvents();
});

async function loadInitialData() {
    // Cargar insights rápidos
    await loadQuickInsights();
    
    // Mensaje de bienvenida personalizado
    addBotMessage(`
        <h3>¡Hola! Soy tu Asistente IA</h3>
        <p>Estoy aquí para ayudarte a <strong>aumentar tus ventas</strong> y optimizar tu negocio.</p>
        <p><strong>Puedes preguntarme:</strong></p>
        <ul>
            <li>¿Qué puedo hacer para vender más?</li>
            <li>¿Cuál es mi plato más vendido?</li>
            <li>¿Cómo están mis ingresos este mes?</li>
            <li>¿Qué productos debo destacar?</li>
            <li>¿En qué horario vendo más?</li>
        </ul>
    `, [
        { text: 'Análisis completo de ventas', action: 'analyze_sales', icon: 'chart-bar' },
        { text: 'Dame recomendaciones', action: 'get_recommendations', icon: 'lightbulb' },
        { text: 'Insights del negocio', action: 'get_insights', icon: 'chart-line' },
        { text: 'Subir menú nuevo', action: 'upload', icon: 'camera' }
    ]);
}

// ========================================
// FUNCIONES DE UPLOAD E IMÁGENES MÚLTIPLES
// ========================================

function initUploadEvents() {
    const uploadZone = document.getElementById('uploadZone');
    const fileInput = document.getElementById('multiFileInput');
    
    if (!uploadZone || !fileInput) return;
    
    // Click para abrir selector
    uploadZone.addEventListener('click', () => fileInput.click());
    
    // Drag & Drop
    uploadZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadZone.classList.add('dragover');
    });
    
    uploadZone.addEventListener('dragleave', () => {
        uploadZone.classList.remove('dragover');
    });
    
    uploadZone.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadZone.classList.remove('dragover');
        handleFileSelect(e.dataTransfer.files);
    });
    
    // Selección de archivos
    fileInput.addEventListener('change', (e) => {
        handleFileSelect(e.target.files);
    });
}

function handleFileSelect(files) {
    const maxFiles = 5;
    const preview = document.getElementById('previewImages');
    const startBtn = document.getElementById('startAnalysisBtn');
    
    // Agregar nuevos archivos (máximo 5 total)
    for (let i = 0; i < files.length && selectedImages.length < maxFiles; i++) {
        const file = files[i];
        if (file.type.startsWith('image/')) {
            selectedImages.push(file);
        }
    }
    
    // Actualizar preview
    updateImagePreview();
    
    // Habilitar botón si hay imágenes
    if (startBtn) {
        startBtn.disabled = selectedImages.length === 0;
    }
}

function updateImagePreview() {
    const preview = document.getElementById('previewImages');
    preview.innerHTML = '';
    
    selectedImages.forEach((file, index) => {
        const reader = new FileReader();
        reader.onload = (e) => {
            const div = document.createElement('div');
            div.className = 'preview-item';
            div.innerHTML = `
                <img src="${e.target.result}" alt="Preview ${index + 1}">
                <button class="remove-preview" onclick="removeImage(${index})">
                    <i class="fas fa-times"></i>
                </button>
            `;
            preview.appendChild(div);
        };
        reader.readAsDataURL(file);
    });
}

function removeImage(index) {
    selectedImages.splice(index, 1);
    updateImagePreview();
    
    const startBtn = document.getElementById('startAnalysisBtn');
    if (startBtn) {
        startBtn.disabled = selectedImages.length === 0;
    }
}

function openUploadModal() {
    document.getElementById('uploadModal').classList.add('active');
    selectedImages = [];
    updateImagePreview();
    document.getElementById('startAnalysisBtn').disabled = true;
    document.getElementById('uploadProgress').classList.remove('active');
}

function closeUploadModal() {
    document.getElementById('uploadModal').classList.remove('active');
    selectedImages = [];
}

async function startImageAnalysis() {
    if (selectedImages.length === 0) return;
    
    const progressDiv = document.getElementById('uploadProgress');
    const progressFill = document.getElementById('progressFill');
    const progressText = document.getElementById('progressText');
    const startBtn = document.getElementById('startAnalysisBtn');
    
    progressDiv.classList.add('active');
    startBtn.disabled = true;
    
    // Resetear productos parseados
    parsedProducts = [];
    
    try {
        for (let i = 0; i < selectedImages.length; i++) {
            const file = selectedImages[i];
            const progress = ((i + 1) / selectedImages.length) * 100;
            
            progressFill.style.width = `${progress * 0.5}%`; // 50% para carga
            progressText.textContent = `Analizando imagen ${i + 1} de ${selectedImages.length}...`;
            
            // Procesar imagen
            const formData = new FormData();
            formData.append('image', file);
            
            const response = await fetch('/admin/menu_parser_endpoint.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success && result.data && result.data.productos) {
                parsedProducts = parsedProducts.concat(result.data.productos);
            }
            
            progressFill.style.width = `${50 + (progress * 0.5)}%`; // 50-100% para procesamiento
        }
        
        progressFill.style.width = '100%';
        progressText.textContent = '¡Análisis completado!';
        
        setTimeout(() => {
            closeUploadModal();
            displayEditableMenu(parsedProducts);
            addUserMessage(`📸 Subí ${selectedImages.length} imagen(es) del menú`);
        }, 500);
        
    } catch (error) {
        progressText.textContent = '❌ Error al procesar las imágenes';
        console.error('Error:', error);
    }
}

async function loadQuickInsights() {
    try {
        const response = await fetch('api/ai_assistant_mock.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'analyze_sales',
                negocio_id: negocioId
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            displayQuickInsights(result.data);
        }
    } catch (error) {
        console.error('Error cargando insights:', error);
    }
}

function displayQuickInsights(data) {
    const sidebar = document.getElementById('quickInsights');
    const stats = data.estadisticas;
    const top = data.top_3 || [];
    
    let html = '';
    
    if (stats) {
        html += `
            <div class="quick-insight" data-action="analyze_sales">
                <h4><i class="fas fa-shopping-cart"></i> Pedidos (30 días)</h4>
                <div class="insight-value">${stats.total_pedidos || 0}</div>
                <p>Ticket promedio: $${parseFloat(stats.ticket_promedio || 0).toFixed(2)}</p>
            </div>
            
            <div class="quick-insight" data-action="analyze_sales">
                <h4><i class="fas fa-dollar-sign"></i> Ingresos</h4>
                <div class="insight-value">$${parseFloat(stats.ingresos_totales || 0).toFixed(2)}</div>
                <p>${stats.clientes_unicos || 0} clientes únicos</p>
            </div>
        `;
    }
    
    if (top.length > 0) {
        html += `
            <div class="quick-insight" data-action="analyze_sales">
                <h4><i class="fas fa-star"></i> Top Producto</h4>
                <p><strong>${top[0].producto}</strong></p>
                <p>${top[0].cantidad_total} vendidos</p>
            </div>
        `;
    }
    
    html += `
        <button class="btn btn-sm btn-outline-primary w-100 mt-2" data-action="get_recommendations">
            <i class="fas fa-lightbulb"></i> Ver Recomendaciones
        </button>
    `;
    
    sidebar.innerHTML = html;

    sidebar.querySelectorAll('.quick-insight, button').forEach(element => {
        element.addEventListener('click', () => handleQuickAction(element.dataset.action));
    });
}

// ========================================
// EDITOR DE MENÚ - FUNCIONES CRUD
// ========================================

function initEditFormEvents() {
    const form = document.getElementById('editProductForm');
    if (form) {
        form.addEventListener('submit', saveProductEdit);
    }
}

function displayEditableMenu(products) {
    if (!products || products.length === 0) {
        addBotMessage('❌ No se encontraron productos en las imágenes.');
        return;
    }
    
    parsedProducts = products;
    
    // Obtener categorías únicas
    const categories = [...new Set(products.map(p => p.categoria || 'Sin categoría'))];
    
    let html = `
        <div class="menu-editor">
            <div class="menu-header">
                <div class="menu-stats">
                    <div class="stat-item">
                        <div class="stat-value" id="totalProducts">${products.length}</div>
                        <div class="stat-label">Productos</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value" id="totalCategories">${categories.length}</div>
                        <div class="stat-label">Categorías</div>
                    </div>
                </div>
                <div class="menu-actions">
                    <button class="btn-action btn-secondary" onclick="openUploadModal()">
                        <i class="fas fa-plus"></i> Más Imágenes
                    </button>
                    <button class="btn-action btn-primary" onclick="saveAllProducts()">
                        <i class="fas fa-save"></i> Guardar Todo
                    </button>
                </div>
            </div>
            
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchProducts" placeholder="Buscar productos..." oninput="filterProducts()">
            </div>
            
            <div class="category-filters" id="categoryFilters">
                <button class="category-filter active" data-category="all" onclick="setFilter('all')">
                    Todos
                </button>
                ${categories.map(cat => `
                    <button class="category-filter" data-category="${cat}" onclick="setFilter('${cat}')">
                        ${cat}
                    </button>
                `).join('')}
            </div>
            
            <div id="productsList">
                ${renderProductCards(products)}
            </div>
            
            <button class="btn-add-product" onclick="addNewProduct()">
                <i class="fas fa-plus-circle"></i> Agregar Producto Manualmente
            </button>
        </div>
    `;
    
    addBotMessage(html);
    currentContext.parsedMenu = { productos: products };
}

function renderProductCards(products) {
    return products.map((p, index) => `
        <div class="product-card" data-index="${index}" data-category="${p.categoria || 'Sin categoría'}" data-name="${(p.nombre || '').toLowerCase()}">
            <img src="${p.imagen || 'https://via.placeholder.com/70x70?text=📷'}" class="product-image" alt="${p.nombre || 'Producto'}" onerror="this.src='https://via.placeholder.com/70x70?text=📷'">
            <div class="product-info">
                <div class="product-name">${p.nombre || 'Sin nombre'}</div>
                <div class="product-desc">${p.descripcion || 'Sin descripción'}</div>
                <div class="product-price">$${parseFloat(p.precio || 0).toFixed(2)}</div>
                <span class="product-category">${p.categoria || 'Sin categoría'}</span>
            </div>
            <div class="product-actions">
                <button class="btn-edit" onclick="editProduct(${index})" title="Editar">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn-delete" onclick="deleteProduct(${index})" title="Eliminar">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `).join('');
}

function setFilter(category) {
    currentFilter = category;
    
    // Actualizar botones
    document.querySelectorAll('.category-filter').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.category === category);
    });
    
    filterProducts();
}

function filterProducts() {
    const searchTerm = (document.getElementById('searchProducts')?.value || '').toLowerCase();
    const cards = document.querySelectorAll('.product-card');
    
    cards.forEach(card => {
        const name = card.dataset.name || '';
        const category = card.dataset.category || '';
        
        const matchesSearch = name.includes(searchTerm);
        const matchesCategory = currentFilter === 'all' || category === currentFilter;
        
        card.style.display = matchesSearch && matchesCategory ? 'flex' : 'none';
    });
}

function editProduct(index) {
    const product = parsedProducts[index];
    if (!product) return;
    
    editingIndex = index;
    
    // Llenar formulario
    document.getElementById('editProductIndex').value = index;
    document.getElementById('editNombre').value = product.nombre || '';
    document.getElementById('editDescripcion').value = product.descripcion || '';
    document.getElementById('editPrecio').value = product.precio || 0;
    document.getElementById('editCalorias').value = product.calorias || '';
    
    // Llenar select de categorías
    const catSelect = document.getElementById('editCategoria');
    const categories = [...new Set(parsedProducts.map(p => p.categoria || 'Sin categoría'))];
    catSelect.innerHTML = '<option value="">Seleccionar categoría...</option>';
    categories.forEach(cat => {
        const option = document.createElement('option');
        option.value = cat;
        option.textContent = cat;
        option.selected = cat === product.categoria;
        catSelect.appendChild(option);
    });
    // Opción para agregar nueva categoría
    catSelect.innerHTML += '<option value="__new__">+ Nueva categoría...</option>';
    
    // Título del modal
    document.getElementById('modalTitle').textContent = 'Editar Producto';
    
    // Mostrar modal
    document.getElementById('editModal').classList.add('active');
    
    // Marcar tarjeta como editando
    document.querySelectorAll('.product-card').forEach(c => c.classList.remove('editing'));
    document.querySelector(`.product-card[data-index="${index}"]`)?.classList.add('editing');
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('active');
    editingIndex = -1;
    document.querySelectorAll('.product-card').forEach(c => c.classList.remove('editing'));
}

function saveProductEdit(e) {
    e.preventDefault();
    
    const index = editingIndex;
    if (index < 0 || !parsedProducts[index]) return;
    
    let categoria = document.getElementById('editCategoria').value;
    if (categoria === '__new__') {
        categoria = prompt('Nombre de la nueva categoría:');
        if (!categoria) return;
    }
    
    // Actualizar producto
    parsedProducts[index] = {
        ...parsedProducts[index],
        nombre: document.getElementById('editNombre').value,
        descripcion: document.getElementById('editDescripcion').value,
        precio: parseFloat(document.getElementById('editPrecio').value) || 0,
        categoria: categoria || 'Sin categoría',
        calorias: parseInt(document.getElementById('editCalorias').value) || null
    };
    
    // Actualizar vista
    refreshProductsList();
    closeEditModal();
    
    // Notificación
    showToast('✅ Producto actualizado');
}

function deleteProduct(index) {
    const product = parsedProducts[index];
    if (!product) return;
    
    if (confirm(`¿Eliminar "${product.nombre}"?`)) {
        parsedProducts.splice(index, 1);
        refreshProductsList();
        showToast('🗑️ Producto eliminado');
    }
}

function addNewProduct() {
    // Agregar producto vacío
    parsedProducts.push({
        nombre: 'Nuevo Producto',
        descripcion: '',
        precio: 0,
        categoria: 'Sin categoría',
        calorias: null,
        imagen: null
    });
    
    // Abrir editor del nuevo producto
    editProduct(parsedProducts.length - 1);
    
    // Actualizar título
    document.getElementById('modalTitle').textContent = 'Agregar Producto';
}

function refreshProductsList() {
    const container = document.getElementById('productsList');
    if (container) {
        container.innerHTML = renderProductCards(parsedProducts);
    }
    
    // Actualizar estadísticas
    const categories = [...new Set(parsedProducts.map(p => p.categoria || 'Sin categoría'))];
    const totalEl = document.getElementById('totalProducts');
    const catEl = document.getElementById('totalCategories');
    if (totalEl) totalEl.textContent = parsedProducts.length;
    if (catEl) catEl.textContent = categories.length;
    
    // Actualizar filtros de categoría
    const filtersContainer = document.getElementById('categoryFilters');
    if (filtersContainer) {
        filtersContainer.innerHTML = `
            <button class="category-filter ${currentFilter === 'all' ? 'active' : ''}" data-category="all" onclick="setFilter('all')">
                Todos
            </button>
            ${categories.map(cat => `
                <button class="category-filter ${currentFilter === cat ? 'active' : ''}" data-category="${cat}" onclick="setFilter('${cat}')">
                    ${cat}
                </button>
            `).join('')}
        `;
    }
    
    // Aplicar filtro actual
    filterProducts();
}

async function saveAllProducts() {
    if (parsedProducts.length === 0) {
        showToast('⚠️ No hay productos para guardar');
        return;
    }
    
    if (!confirm(`¿Guardar ${parsedProducts.length} productos en la base de datos?`)) return;
    
    showTypingIndicator();
    
    try {
        const response = await fetch('api/ai_assistant_mock.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'save_menu',
                negocio_id: negocioId,
                productos: parsedProducts
            })
        });
        
        const result = await response.json();
        removeTypingIndicator();
        
        if (result.success) {
            const data = result.data;
            let advertenciaHtml = '';
            
            // Mostrar advertencia si el negocio está cerrado
            if (data.advertencia) {
                advertenciaHtml = `
                    <div class="stat-card" style="border-left-color: #f59e0b; margin-bottom: 10px;">
                        <h4>⚠️ Atención</h4>
                        <p>${data.advertencia}</p>
                    </div>
                `;
            }
            
            addBotMessage(`
                ${advertenciaHtml}
                <div class="stat-card" style="border-left-color: #10b981;">
                    <h4>✅ ¡Menú Guardado!</h4>
                    <p>Se guardaron <strong>${data.total} productos</strong> correctamente.</p>
                    <p><small>Nuevos: ${data.insertados} | Actualizados: ${data.actualizados} | Errores: ${data.errores}</small></p>
                    <p>Puedes verlos en tu panel de administración.</p>
                </div>
            `, [
                { text: '📊 Analizar ventas', action: 'analyze_sales', icon: 'chart-bar' },
                { text: '💡 Recomendaciones', action: 'get_recommendations', icon: 'lightbulb' }
            ]);
            parsedProducts = [];
        } else {
            addBotMessage('❌ Error al guardar: ' + (result.error || 'Error desconocido'));
        }
    } catch (error) {
        removeTypingIndicator();
        addBotMessage('❌ Error de conexión al guardar el menú.');
    }
}

function showToast(message) {
    // Crear toast temporal
    const toast = document.createElement('div');
    toast.style.cssText = `
        position: fixed;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%);
        background: #1e293b;
        color: white;
        padding: 12px 24px;
        border-radius: 8px;
        z-index: 99999;
        animation: fadeInUp 0.3s ease;
    `;
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'fadeOutDown 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 2000);
}

// ========================================
// MANEJO DE ARCHIVOS E INPUT LEGACY
// ========================================

// Manejo de mensajes
document.getElementById('messageInput').addEventListener('keypress', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
});

document.getElementById('sendBtn').addEventListener('click', sendMessage);

async function sendMessage() {
    const input = document.getElementById('messageInput');
    const message = input.value.trim();
    
    if (!message) return;
    
    addUserMessage(message);
    input.value = '';
    
    conversationHistory.push({ role: 'user', content: message });
    
    // Analizar intención del mensaje
    await processUserMessage(message);
}

async function processUserMessage(message) {
    const lowerMsg = message.toLowerCase();
    
    // Detectar intención
    if (lowerMsg.includes('vender más') || lowerMsg.includes('aumentar ventas') || lowerMsg.includes('recomiend')) {
        await handleQuickAction('get_recommendations');
    } else if (lowerMsg.includes('más vendido') || lowerMsg.includes('top') || lowerMsg.includes('mejor')) {
        await handleQuickAction('analyze_sales');
    } else if (lowerMsg.includes('horario') || lowerMsg.includes('cuándo') || lowerMsg.includes('hora')) {
        await handleQuickAction('get_insights');
    } else if (lowerMsg.includes('ingreso') || lowerMsg.includes('ganancia') || lowerMsg.includes('dinero')) {
        await handleQuickAction('analyze_sales');
    } else {
        // Usar chat general con IA
        await sendChatMessage(message);
    }
}

async function sendChatMessage(message) {
    showTypingIndicator();
    
    try {
        const response = await fetch('api/ai_assistant_mock.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'chat',
                negocio_id: negocioId || 0,  // Enviar 0 si no hay negocio
                message: message
            })
        });
        
        const result = await response.json();
        removeTypingIndicator();
        
        if (result.success) {
            addBotMessage(result.data.response);
        } else {
            addBotMessage('❌ Lo siento, ocurrió un error. ¿Podrías reformular tu pregunta?');
        }
    } catch (error) {
        removeTypingIndicator();
        addBotMessage('❌ Error de conexión. Por favor, intenta de nuevo.');
    }
}

async function handleQuickAction(action) {
    showTypingIndicator();
    
    switch(action) {
        case 'analyze_sales':
            await analyzeSales();
            break;
        case 'get_recommendations':
            await getRecommendations();
            break;
        case 'get_insights':
            await getInsights();
            break;
        case 'optimize_menu':
            await optimizeMenu();
            break;
        case 'upload':
            openUploadModal();
            removeTypingIndicator();
            break;
        case 'save_menu':
            await saveAllProducts();
            removeTypingIndicator();
            break;
        case 'edit_menu':
            if (currentContext.parsedMenu) {
                displayEditableMenu(currentContext.parsedMenu.productos || []);
            }
            removeTypingIndicator();
            break;
    }
}

async function analyzeSales() {
    try {
        const response = await fetch('api/ai_assistant_mock.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'analyze_sales',
                negocio_id: negocioId
            })
        });
        
        const result = await response.json();
        removeTypingIndicator();
        
        if (result.success) {
            displaySalesAnalysis(result.data);
        } else {
            addBotMessage('❌ No pude obtener el análisis de ventas.');
        }
    } catch (error) {
        removeTypingIndicator();
        addBotMessage('❌ Error al analizar ventas.');
    }
}

function displaySalesAnalysis(data) {
    const stats = data.estadisticas;
    const top = data.top_3 || [];
    const productos = data.productos || [];
    
    let html = '<h3>📊 Análisis de Ventas (Últimos 30 días)</h3>';
    
    // Estadísticas generales
    html += '<div class="stat-card">';
    html += '<h4>Resumen General</h4>';
    html += '<div class="stat-grid">';
    html += `<div class="stat-item"><div class="value">${stats.total_pedidos || 0}</div><div class="label">Pedidos</div></div>`;
    html += `<div class="stat-item"><div class="value">$${parseFloat(stats.ingresos_totales || 0).toFixed(2)}</div><div class="label">Ingresos</div></div>`;
    html += `<div class="stat-item"><div class="value">$${parseFloat(stats.ticket_promedio || 0).toFixed(2)}</div><div class="label">Ticket Promedio</div></div>`;
    html += `<div class="stat-item"><div class="value">${stats.clientes_unicos || 0}</div><div class="label">Clientes</div></div>`;
    html += '</div>';
    html += '</div>';
    
    // Top 3 productos
    if (top.length > 0) {
        html += '<h4 style="margin-top: 20px;">⭐ Top 3 Productos Más Vendidos</h4>';
        top.forEach((prod, i) => {
            const icon = i === 0 ? '🥇' : i === 1 ? '🥈' : '🥉';
            html += `
                <div class="product-highlight">
                    <h4>${icon} ${prod.producto}</h4>
                    <p><strong>${prod.cantidad_total}</strong> unidades vendidas</p>
                    <p>💰 Ingresos: $${parseFloat(prod.ingresos_totales).toFixed(2)}</p>
                    <p>📁 ${prod.categoria || 'Sin categoría'}</p>
                </div>
            `;
        });
    }
    
    addBotMessage(html, [
        { text: '💡 Dame recomendaciones', action: 'get_recommendations', icon: 'lightbulb' },
        { text: '🔍 Ver más insights', action: 'get_insights', icon: 'search' },
        { text: '⚙️ Optimizar menú', action: 'optimize_menu', icon: 'cog' }
    ]);
}

async function getRecommendations() {
    try {
        const response = await fetch('api/ai_assistant_mock.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'get_recommendations',
                negocio_id: negocioId
            })
        });
        
        const result = await response.json();
        removeTypingIndicator();
        
        if (result.success && result.data.recomendaciones) {
            displayRecommendations(result.data.recomendaciones);
        } else {
            addBotMessage('❌ No pude generar recomendaciones en este momento.');
        }
    } catch (error) {
        removeTypingIndicator();
        addBotMessage('❌ Error al generar recomendaciones.');
    }
}

function displayRecommendations(recomendaciones) {
    let html = '<h3>💡 Recomendaciones Personalizadas para Aumentar Ventas</h3>';
    html += '<p>Basado en el análisis de tu negocio, aquí están mis recomendaciones:</p>';
    
    recomendaciones.forEach((rec, i) => {
        html += `
            <div class="recommendation-card">
                <h4>${i + 1}. ${rec.titulo}</h4>
                <p>${rec.descripcion}</p>
                <span class="impact ${rec.impacto}">
                    Impacto: ${rec.impacto.toUpperCase()}
                </span>
                <span class="badge bg-secondary ms-2">${rec.categoria}</span>
            </div>
        `;
    });
    
    addBotMessage(html, [
        { text: '📊 Ver análisis de ventas', action: 'analyze_sales', icon: 'chart-bar' },
        { text: '🔍 Insights adicionales', action: 'get_insights', icon: 'search' }
    ]);
}

async function getInsights() {
    try {
        const response = await fetch('api/ai_assistant_mock.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'get_insights',
                negocio_id: negocioId
            })
        });
        
        const result = await response.json();
        removeTypingIndicator();
        
        if (result.success) {
            displayInsights(result.data);
        } else {
            addBotMessage('❌ No pude obtener insights.');
        }
    } catch (error) {
        removeTypingIndicator();
        addBotMessage('❌ Error al obtener insights.');
    }
}

function displayInsights(data) {
    let html = '<h3>🔍 Insights del Negocio</h3>';
    
    // Horarios pico
    if (data.horarios_pico && data.horarios_pico.length > 0) {
        html += '<div class="stat-card">';
        html += '<h4>⏰ Horarios de Mayor Demanda</h4>';
        data.horarios_pico.forEach(h => {
            html += `<p>📍 <strong>${h.hora}:00hrs</strong> - ${h.pedidos} pedidos ($${parseFloat(h.ingresos).toFixed(2)})</p>`;
        });
        html += '</div>';
    }
    
    // Días populares
    if (data.dias_populares && data.dias_populares.length > 0) {
        html += '<div class="stat-card">';
        html += '<h4>📅 Días Más Populares</h4>';
        data.dias_populares.slice(0, 3).forEach(d => {
            html += `<p>📍 <strong>${d.dia}</strong> - ${d.pedidos} pedidos</p>`;
        });
        html += '</div>';
    }
    
    // Combos frecuentes
    if (data.combos_frecuentes && data.combos_frecuentes.length > 0) {
        html += '<div class="stat-card">';
        html += '<h4>🍽️ Productos que se Compran Juntos</h4>';
        data.combos_frecuentes.slice(0, 5).forEach(c => {
            html += `<p>• ${c.producto1} + ${c.producto2} <span class="badge bg-info">${c.veces} veces</span></p>`;
        });
        html += '<p class="mt-2"><em>💡 Considera crear combos con estos productos</em></p>';
        html += '</div>';
    }
    
    // Retención
    if (data.retencion_clientes) {
        const ret = data.retencion_clientes;
        html += '<div class="stat-card">';
        html += '<h4>👥 Retención de Clientes</h4>';
        html += `<p>Tasa de retención: <strong>${ret.tasa_retencion}%</strong></p>`;
        html += `<p>${ret.clientes_recurrentes} de ${ret.clientes_totales} clientes han ordenado más de una vez</p>`;
        html += '</div>';
    }
    
    addBotMessage(html, [
        { text: '💡 Recomendaciones basadas en esto', action: 'get_recommendations', icon: 'lightbulb' }
    ]);
}

async function optimizeMenu() {
    try {
        const response = await fetch('api/ai_assistant_mock.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'optimize_menu',
                negocio_id: negocioId
            })
        });
        
        const result = await response.json();
        removeTypingIndicator();
        
        if (result.success && result.data) {
            displayMenuOptimization(result.data);
        } else {
            addBotMessage('❌ No pude optimizar el menú.');
        }
    } catch (error) {
        removeTypingIndicator();
        addBotMessage('❌ Error al optimizar menú.');
    }
}

function displayMenuOptimization(data) {
    let html = '<h3>⚙️ Optimización del Menú</h3>';
    
    if (data.eliminar && data.eliminar.length > 0) {
        html += '<div class="stat-card" style="border-left-color: #dc3545;">';
        html += '<h4>❌ Considera Eliminar</h4>';
        data.eliminar.forEach(p => {
            html += `<p>• ${p}</p>`;
        });
        html += '</div>';
    }
    
    if (data.destacar && data.destacar.length > 0) {
        html += '<div class="stat-card" style="border-left-color: #28a745;">';
        html += '<h4>⭐ Destacar en el Menú</h4>';
        data.destacar.forEach(p => {
            html += `<p>• ${p}</p>`;
        });
        html += '</div>';
    }
    
    if (data.ajustar_precio && data.ajustar_precio.length > 0) {
        html += '<div class="stat-card" style="border-left-color: #ffc107;">';
        html += '<h4>💰 Ajustes de Precio Sugeridos</h4>';
        data.ajustar_precio.forEach(p => {
            html += `<p><strong>${p.producto}</strong>: $${p.precio_sugerido}<br><small>${p.razon}</small></p>`;
        });
        html += '</div>';
    }
    
    if (data.nuevos_productos && data.nuevos_productos.length > 0) {
        html += '<div class="stat-card" style="border-left-color: #17a2b8;">';
        html += '<h4>✨ Nuevos Productos Sugeridos</h4>';
        data.nuevos_productos.forEach(p => {
            html += `<p>• ${p}</p>`;
        });
        html += '</div>';
    }
    
    addBotMessage(html);
}

async function processMenuImage(file) {
    showTypingIndicator();
    
    const formData = new FormData();
    formData.append('image', file);
    
    try {
        // Usar ruta absoluta desde /admin/ hacia menu_parser_endpoint.php
        const response = await fetch('/admin/menu_parser_endpoint.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        removeTypingIndicator();
        
        if (result.success) {
            // menu_parser_endpoint.php retorna result.data con la estructura del menú
            displayEditableMenu(result.data.productos || []);
        } else {
            addBotMessage(`❌ Error al analizar imagen: ${result.error || 'Error desconocido'}`);
        }
    } catch (error) {
        removeTypingIndicator();
        addBotMessage('❌ Error al procesar la imagen.');
    }
}

// LEGACY - mantener para compatibilidad
function displayMenuParseResults(menu) {
    displayEditableMenu(menu.productos || []);
}

function addBotMessage(text, options = []) {
    const messagesDiv = document.getElementById('messages');
    const messageDiv = document.createElement('div');
    messageDiv.className = 'message bot';
    
    let html = `
        <div class="avatar bot">🤖</div>
        <div>
            <div class="bubble bot">${text}</div>
    `;
    
    if (options.length > 0) {
        html += '<div class="options">';
        options.forEach(opt => {
            const icon = opt.icon ? `<i class="fas fa-${opt.icon}"></i>` : '';
            html += `<button class="option-btn" data-action="${opt.action}">${icon} ${opt.text}</button>`;
        });
        html += '</div>';
    }
    
    html += '</div>';
    messageDiv.innerHTML = html;
    messagesDiv.appendChild(messageDiv);
    messagesDiv.scrollTop = messagesDiv.scrollHeight;

    // Add event listeners to newly added option buttons
    if (options.length > 0) {
        messageDiv.querySelectorAll('.option-btn').forEach(button => {
            button.addEventListener('click', () => handleQuickAction(button.dataset.action));
        });
    }
}

function addUserMessage(text) {
    const messagesDiv = document.getElementById('messages');
    const messageDiv = document.createElement('div');
    messageDiv.className = 'message user';
    messageDiv.innerHTML = `
        <div class="avatar user">👤</div>
        <div class="bubble user">${text}</div>
    `;
    messagesDiv.appendChild(messageDiv);
    messagesDiv.scrollTop = messagesDiv.scrollHeight;
}

function showTypingIndicator() {
    const messagesDiv = document.getElementById('messages');
    const typingDiv = document.createElement('div');
    typingDiv.className = 'message bot';
    typingDiv.id = 'typingIndicator';
    typingDiv.innerHTML = `
        <div class="avatar bot">🤖</div>
        <div class="bubble bot">
            <div class="typing-indicator">
                <span class="typing-dot"></span>
                <span class="typing-dot"></span>
                <span class="typing-dot"></span>
            </div>
        </div>
    `;
    messagesDiv.appendChild(typingDiv);
    messagesDiv.scrollTop = messagesDiv.scrollHeight;
}

function removeTypingIndicator() {
    const indicator = document.getElementById('typingIndicator');
    if (indicator) indicator.remove();
}