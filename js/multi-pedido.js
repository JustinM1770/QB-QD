/**
 * Sistema Multi-Pedido para Repartidores
 * QuickBite - Sistema Ganar-Ganar
 */

class MultiPedidoManager {
    constructor(options = {}) {
        this.apiBase = options.apiBase || '/api/repartidor_pedidos.php';
        this.map = options.map || null;
        this.onUpdate = options.onUpdate || (() => {});
        this.markers = [];
        this.rutaActiva = null;
        this.pedidosCercanos = [];
        this.ubicacionActual = { lat: 0, lng: 0 };
        
        this.init();
    }
    
    init() {
        // Obtener ubicación inicial
        this.obtenerUbicacion();
        
        // Actualizar cada 30 segundos
        setInterval(() => this.actualizarDatos(), 30000);
        
        // Crear UI
        this.crearUI();
    }
    
    // ===========================================
    // UBICACIÓN
    // ===========================================
    
    obtenerUbicacion() {
        if (!navigator.geolocation) {
            console.warn('Geolocalización no disponible');
            return;
        }
        
        navigator.geolocation.watchPosition(
            (pos) => {
                this.ubicacionActual = {
                    lat: pos.coords.latitude,
                    lng: pos.coords.longitude
                };
                this.enviarUbicacion();
            },
            (error) => console.error('Error de ubicación:', error),
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 30000 }
        );
    }
    
    async enviarUbicacion() {
        try {
            const formData = new FormData();
            formData.append('action', 'actualizar_ubicacion');
            formData.append('lat', this.ubicacionActual.lat);
            formData.append('lng', this.ubicacionActual.lng);
            
            await fetch(this.apiBase, { method: 'POST', body: formData });
        } catch (e) {
            console.error('Error enviando ubicación:', e);
        }
    }
    
    // ===========================================
    // DATOS
    // ===========================================
    
    async actualizarDatos() {
        await Promise.all([
            this.cargarRutaActiva(),
            this.cargarPedidosCercanos(),
            this.cargarSugerenciasBatch(),
            this.cargarEstadisticas()
        ]);
        
        this.onUpdate({
            rutaActiva: this.rutaActiva,
            pedidosCercanos: this.pedidosCercanos,
            estadisticas: this.estadisticas
        });
    }
    
    async cargarRutaActiva() {
        try {
            const res = await fetch(`${this.apiBase}?action=ruta_activa`);
            const data = await res.json();
            this.rutaActiva = data.ruta;
            this.actualizarUIRuta();
        } catch (e) {
            console.error('Error cargando ruta:', e);
        }
    }
    
    async cargarPedidosCercanos() {
        try {
            const url = `${this.apiBase}?action=pedidos_cercanos&lat=${this.ubicacionActual.lat}&lng=${this.ubicacionActual.lng}&radio=5`;
            const res = await fetch(url);
            const data = await res.json();
            this.pedidosCercanos = data.pedidos || [];
            this.actualizarUIPedidosCercanos();
        } catch (e) {
            console.error('Error cargando pedidos cercanos:', e);
        }
    }
    
    async cargarSugerenciasBatch() {
        try {
            const res = await fetch(`${this.apiBase}?action=sugerencias_batch`);
            const data = await res.json();
            this.sugerenciasBatch = data.sugerencias || [];
            this.mostrarSugerenciasBatch();
        } catch (e) {
            console.error('Error cargando sugerencias:', e);
        }
    }
    
    async cargarEstadisticas() {
        try {
            const res = await fetch(`${this.apiBase}?action=estadisticas`);
            const data = await res.json();
            this.estadisticas = data.estadisticas || {};
            this.actualizarUIEstadisticas();
        } catch (e) {
            console.error('Error cargando estadísticas:', e);
        }
    }
    
    // ===========================================
    // ACCIONES DE PEDIDOS
    // ===========================================
    
    async aceptarPedido(idPedido) {
        try {
            const formData = new FormData();
            formData.append('action', 'aceptar');
            formData.append('id_pedido', idPedido);
            
            const res = await fetch(this.apiBase, { method: 'POST', body: formData });
            const data = await res.json();
            
            if (data.success) {
                this.mostrarNotificacion('success', '¡Pedido aceptado!', 'Dirígete al negocio para recogerlo');
                this.actualizarDatos();
            } else {
                this.mostrarNotificacion('error', 'Error', data.error);
            }
            
            return data;
        } catch (e) {
            this.mostrarNotificacion('error', 'Error', 'No se pudo aceptar el pedido');
            return { success: false, error: e.message };
        }
    }
    
    async confirmarRecogida(idPedido) {
        try {
            const formData = new FormData();
            formData.append('action', 'confirmar_recogida');
            formData.append('id_pedido', idPedido);
            
            const res = await fetch(this.apiBase, { method: 'POST', body: formData });
            const data = await res.json();
            
            if (data.success) {
                this.mostrarNotificacion('success', '¡Recogida confirmada!', 'Ahora lleva el pedido al cliente');
                this.actualizarDatos();
            } else {
                this.mostrarNotificacion('error', 'Error', data.error);
            }
            
            return data;
        } catch (e) {
            return { success: false, error: e.message };
        }
    }
    
    async confirmarEntrega(idPedido) {
        try {
            const formData = new FormData();
            formData.append('action', 'confirmar_entrega');
            formData.append('id_pedido', idPedido);
            
            const res = await fetch(this.apiBase, { method: 'POST', body: formData });
            const data = await res.json();
            
            if (data.success) {
                this.mostrarNotificacion('success', '¡Entrega completada!', `Tiempo total: ${data.tiempo_total_min} min`);
                this.actualizarDatos();
                this.verificarLogros();
            } else {
                this.mostrarNotificacion('error', 'Error', data.error);
            }
            
            return data;
        } catch (e) {
            return { success: false, error: e.message };
        }
    }
    
    async abandonarPedido(idPedido, motivo = 'abandono_voluntario', notas = '') {
        const confirmacion = await Swal.fire({
            title: '¿Abandonar pedido?',
            html: `
                <p>Selecciona el motivo:</p>
                <select id="motivo-abandono" class="swal2-select" style="width: 100%; margin-top: 10px;">
                    <option value="abandono_voluntario">No puedo ir por él</option>
                    <option value="problema_vehiculo">Problema con mi vehículo</option>
                    <option value="emergencia">Emergencia personal</option>
                </select>
                <textarea id="notas-abandono" class="swal2-textarea" placeholder="Notas adicionales (opcional)" style="margin-top: 10px;"></textarea>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, abandonar',
            cancelButtonText: 'Cancelar',
            preConfirm: () => {
                return {
                    motivo: document.getElementById('motivo-abandono').value,
                    notas: document.getElementById('notas-abandono').value
                };
            }
        });
        
        if (!confirmacion.isConfirmed) return { success: false, cancelled: true };
        
        try {
            const formData = new FormData();
            formData.append('action', 'abandonar');
            formData.append('id_pedido', idPedido);
            formData.append('motivo', confirmacion.value.motivo);
            formData.append('notas', confirmacion.value.notas);
            
            const res = await fetch(this.apiBase, { method: 'POST', body: formData });
            const data = await res.json();
            
            if (data.success) {
                this.mostrarNotificacion('info', 'Pedido liberado', 'Se buscará otro repartidor');
                this.actualizarDatos();
            } else {
                this.mostrarNotificacion('error', 'Error', data.error);
            }
            
            return data;
        } catch (e) {
            return { success: false, error: e.message };
        }
    }
    
    // ===========================================
    // MULTI-PEDIDO / BATCH
    // ===========================================
    
    async agregarARuta(idPedido) {
        try {
            const formData = new FormData();
            formData.append('action', 'agregar_a_ruta');
            formData.append('id_pedido', idPedido);
            
            const res = await fetch(this.apiBase, { method: 'POST', body: formData });
            const data = await res.json();
            
            if (data.success) {
                this.mostrarNotificacion('success', '¡Pedido agregado a tu ruta!', `Total: ${data.total_pedidos} pedidos`);
                this.actualizarDatos();
            } else {
                this.mostrarNotificacion('error', 'Error', data.error);
            }
            
            return data;
        } catch (e) {
            return { success: false, error: e.message };
        }
    }
    
    async crearRutaBatch(idsPedidos) {
        try {
            const formData = new FormData();
            formData.append('action', 'crear_ruta_batch');
            formData.append('pedidos', JSON.stringify(idsPedidos));
            
            const res = await fetch(this.apiBase, { method: 'POST', body: formData });
            const data = await res.json();
            
            if (data.success) {
                this.mostrarNotificacion('success', '¡Ruta batch creada!', 
                    `${data.total_pedidos} pedidos, bonificación: $${data.bonificacion}`);
                this.actualizarDatos();
            } else {
                this.mostrarNotificacion('error', 'Error', data.error);
            }
            
            return data;
        } catch (e) {
            return { success: false, error: e.message };
        }
    }
    
    async aceptarSugerenciaBatch(sugerencia) {
        const confirmacion = await Swal.fire({
            title: '🚀 Ruta Batch Sugerida',
            html: `
                <div style="text-align: left;">
                    <p><strong>${sugerencia.mensaje}</strong></p>
                    <p>💰 Ganancia estimada: <strong>$${sugerencia.ganancia_estimada.toFixed(2)}</strong></p>
                    <p>🎁 Bonificación batch: <strong>$${sugerencia.bonificacion.toFixed(2)}</strong></p>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#00c853',
            confirmButtonText: '¡Aceptar ruta!',
            cancelButtonText: 'Ahora no'
        });
        
        if (confirmacion.isConfirmed) {
            return this.crearRutaBatch(sugerencia.pedidos);
        }
        
        return { success: false, cancelled: true };
    }
    
    // ===========================================
    // LOGROS
    // ===========================================
    
    async verificarLogros() {
        try {
            const res = await fetch(`${this.apiBase}?action=logros`);
            const data = await res.json();
            
            // Buscar logros recién desbloqueados (en los últimos 5 segundos)
            const ahora = new Date();
            const logrosRecientes = (data.logros || []).filter(l => {
                if (!l.desbloqueado || !l.fecha_desbloqueo) return false;
                const fechaDesbloqueo = new Date(l.fecha_desbloqueo);
                return (ahora - fechaDesbloqueo) < 5000;
            });
            
            logrosRecientes.forEach(logro => {
                this.mostrarLogroDesbloqueado(logro);
            });
            
        } catch (e) {
            console.error('Error verificando logros:', e);
        }
    }
    
    mostrarLogroDesbloqueado(logro) {
        Swal.fire({
            title: '🏆 ¡Logro Desbloqueado!',
            html: `
                <div style="font-size: 48px; margin: 20px 0;">${logro.icono}</div>
                <h3 style="margin: 10px 0;">${logro.nombre}</h3>
                <p style="color: #666;">${logro.descripcion}</p>
                ${logro.bonificacion > 0 ? `<p style="color: #00c853; font-weight: bold; margin-top: 15px;">+$${logro.bonificacion.toFixed(2)} de bonificación</p>` : ''}
            `,
            icon: 'success',
            confirmButtonText: '¡Genial!',
            background: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
            color: '#fff'
        });
    }
    
    // ===========================================
    // UI
    // ===========================================
    
    crearUI() {
        // Crear contenedor flotante para multi-pedido
        const container = document.createElement('div');
        container.id = 'multi-pedido-panel';
        container.innerHTML = `
            <style>
                #multi-pedido-panel {
                    position: fixed;
                    bottom: 80px;
                    right: 20px;
                    z-index: 1000;
                }
                
                .mp-toggle-btn {
                    width: 56px;
                    height: 56px;
                    border-radius: 50%;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    border: none;
                    color: white;
                    font-size: 24px;
                    cursor: pointer;
                    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
                    transition: transform 0.3s, box-shadow 0.3s;
                }
                
                .mp-toggle-btn:hover {
                    transform: scale(1.1);
                    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
                }
                
                .mp-toggle-btn .badge {
                    position: absolute;
                    top: -5px;
                    right: -5px;
                    background: #ff5252;
                    color: white;
                    border-radius: 50%;
                    width: 22px;
                    height: 22px;
                    font-size: 12px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                
                .mp-panel {
                    position: absolute;
                    bottom: 70px;
                    right: 0;
                    width: 320px;
                    max-height: 70vh;
                    background: white;
                    border-radius: 16px;
                    box-shadow: 0 10px 40px rgba(0,0,0,0.15);
                    display: none;
                    overflow: hidden;
                }
                
                .mp-panel.active {
                    display: block;
                    animation: slideUp 0.3s ease;
                }
                
                @keyframes slideUp {
                    from { opacity: 0; transform: translateY(20px); }
                    to { opacity: 1; transform: translateY(0); }
                }
                
                .mp-header {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 15px;
                    font-weight: 600;
                }
                
                .mp-tabs {
                    display: flex;
                    border-bottom: 1px solid #eee;
                }
                
                .mp-tab {
                    flex: 1;
                    padding: 12px;
                    text-align: center;
                    cursor: pointer;
                    font-size: 13px;
                    color: #666;
                    border-bottom: 2px solid transparent;
                    transition: all 0.2s;
                }
                
                .mp-tab.active {
                    color: #667eea;
                    border-bottom-color: #667eea;
                }
                
                .mp-content {
                    max-height: 50vh;
                    overflow-y: auto;
                    padding: 15px;
                }
                
                .mp-pedido-card {
                    background: #f8f9fa;
                    border-radius: 12px;
                    padding: 12px;
                    margin-bottom: 10px;
                    cursor: pointer;
                    transition: all 0.2s;
                }
                
                .mp-pedido-card:hover {
                    background: #e9ecef;
                    transform: translateX(5px);
                }
                
                .mp-pedido-title {
                    font-weight: 600;
                    font-size: 14px;
                    margin-bottom: 5px;
                }
                
                .mp-pedido-info {
                    font-size: 12px;
                    color: #666;
                }
                
                .mp-pedido-badge {
                    display: inline-block;
                    background: #e3f2fd;
                    color: #1976d2;
                    padding: 3px 8px;
                    border-radius: 20px;
                    font-size: 11px;
                    margin-top: 8px;
                }
                
                .mp-sugerencia {
                    background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ec 100%);
                    border: 2px dashed #667eea;
                    border-radius: 12px;
                    padding: 15px;
                    margin-bottom: 10px;
                    cursor: pointer;
                }
                
                .mp-sugerencia:hover {
                    border-style: solid;
                    background: linear-gradient(135deg, #667eea10 0%, #764ba210 100%);
                }
                
                .mp-ruta-item {
                    display: flex;
                    align-items: center;
                    padding: 10px;
                    border-left: 3px solid #667eea;
                    margin-bottom: 8px;
                    background: #f8f9fa;
                    border-radius: 0 8px 8px 0;
                }
                
                .mp-ruta-icon {
                    width: 32px;
                    height: 32px;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin-right: 10px;
                    font-size: 14px;
                }
                
                .mp-ruta-icon.recoleccion {
                    background: #fff3e0;
                    color: #f57c00;
                }
                
                .mp-ruta-icon.entrega {
                    background: #e8f5e9;
                    color: #388e3c;
                }
                
                .mp-empty {
                    text-align: center;
                    padding: 30px;
                    color: #999;
                }
                
                .mp-empty i {
                    font-size: 48px;
                    margin-bottom: 15px;
                    opacity: 0.5;
                }
                
                .mp-stats {
                    display: grid;
                    grid-template-columns: repeat(2, 1fr);
                    gap: 10px;
                    margin-bottom: 15px;
                }
                
                .mp-stat-card {
                    background: #f8f9fa;
                    border-radius: 10px;
                    padding: 12px;
                    text-align: center;
                }
                
                .mp-stat-value {
                    font-size: 20px;
                    font-weight: 700;
                    color: #667eea;
                }
                
                .mp-stat-label {
                    font-size: 11px;
                    color: #666;
                    margin-top: 3px;
                }
                
                .mp-btn {
                    width: 100%;
                    padding: 10px;
                    border: none;
                    border-radius: 8px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.2s;
                }
                
                .mp-btn-primary {
                    background: #667eea;
                    color: white;
                }
                
                .mp-btn-primary:hover {
                    background: #5a6fd6;
                }
                
                .mp-btn-success {
                    background: #00c853;
                    color: white;
                }
                
                .mp-btn-danger {
                    background: #ff5252;
                    color: white;
                }
            </style>
            
            <button class="mp-toggle-btn" onclick="multiPedido.togglePanel()">
                <i class="fas fa-layer-group"></i>
                <span class="badge" id="mp-badge" style="display: none;">0</span>
            </button>
            
            <div class="mp-panel" id="mp-panel-content">
                <div class="mp-header">
                    <i class="fas fa-route"></i> Multi-Pedido
                </div>
                
                <div class="mp-tabs">
                    <div class="mp-tab active" data-tab="ruta" onclick="multiPedido.switchTab('ruta')">
                        <i class="fas fa-map-marked-alt"></i> Mi Ruta
                    </div>
                    <div class="mp-tab" data-tab="cercanos" onclick="multiPedido.switchTab('cercanos')">
                        <i class="fas fa-search-location"></i> Cercanos
                    </div>
                    <div class="mp-tab" data-tab="stats" onclick="multiPedido.switchTab('stats')">
                        <i class="fas fa-chart-bar"></i> Stats
                    </div>
                </div>
                
                <div class="mp-content" id="mp-content">
                    <!-- Contenido dinámico -->
                </div>
            </div>
        `;
        
        document.body.appendChild(container);
    }
    
    togglePanel() {
        const panel = document.getElementById('mp-panel-content');
        panel.classList.toggle('active');
        
        if (panel.classList.contains('active')) {
            this.actualizarDatos();
        }
    }
    
    switchTab(tab) {
        document.querySelectorAll('.mp-tab').forEach(t => t.classList.remove('active'));
        document.querySelector(`.mp-tab[data-tab="${tab}"]`).classList.add('active');
        
        switch(tab) {
            case 'ruta':
                this.actualizarUIRuta();
                break;
            case 'cercanos':
                this.actualizarUIPedidosCercanos();
                break;
            case 'stats':
                this.actualizarUIEstadisticas();
                break;
        }
    }
    
    actualizarUIRuta() {
        const content = document.getElementById('mp-content');
        
        if (!this.rutaActiva || !this.rutaActiva.paradas || this.rutaActiva.paradas.length === 0) {
            content.innerHTML = `
                <div class="mp-empty">
                    <i class="fas fa-route"></i>
                    <p>No tienes una ruta activa</p>
                    <p style="font-size: 12px; margin-top: 10px;">Acepta pedidos cercanos para crear tu ruta</p>
                </div>
            `;
            return;
        }
        
        let html = `
            <div style="background: #e8f5e9; padding: 10px; border-radius: 8px; margin-bottom: 15px;">
                <strong style="color: #388e3c;"><i class="fas fa-check-circle"></i> Ruta activa</strong>
                <p style="font-size: 12px; color: #666; margin-top: 5px;">
                    ${this.rutaActiva.total_pedidos} pedidos · ${this.rutaActiva.paradas.length} paradas
                </p>
            </div>
        `;
        
        this.rutaActiva.paradas.forEach((parada, index) => {
            const iconClass = parada.tipo === 'recoleccion' ? 'recoleccion' : 'entrega';
            const iconEmoji = parada.tipo === 'recoleccion' ? '📦' : '🏠';
            const estadoClass = parada.estado === 'entregado' || parada.estado === 'recogido' ? 'opacity: 0.5;' : '';
            
            html += `
                <div class="mp-ruta-item" style="${estadoClass}">
                    <div class="mp-ruta-icon ${iconClass}">${iconEmoji}</div>
                    <div style="flex: 1;">
                        <div style="font-weight: 600; font-size: 13px;">
                            ${parada.tipo === 'recoleccion' ? 'Recoger' : 'Entregar'} #${parada.id_pedido}
                        </div>
                        <div style="font-size: 11px; color: #666;">${parada.direccion || 'Ver mapa'}</div>
                    </div>
                    <span style="font-size: 11px; background: #eee; padding: 3px 8px; border-radius: 10px;">
                        ${index + 1}
                    </span>
                </div>
            `;
        });
        
        if (this.rutaActiva.bonificacion_batch > 0) {
            html += `
                <div style="background: linear-gradient(135deg, #667eea20 0%, #764ba220 100%); padding: 10px; border-radius: 8px; text-align: center; margin-top: 15px;">
                    <span style="color: #667eea; font-weight: 600;">
                        🎁 Bonificación batch: +$${parseFloat(this.rutaActiva.bonificacion_batch).toFixed(2)}
                    </span>
                </div>
            `;
        }
        
        content.innerHTML = html;
    }
    
    actualizarUIPedidosCercanos() {
        const content = document.getElementById('mp-content');
        
        // Mostrar sugerencias de batch primero
        let html = '';
        
        if (this.sugerenciasBatch && this.sugerenciasBatch.length > 0) {
            html += '<div style="margin-bottom: 15px;"><strong style="color: #667eea;">💡 Sugerencias Batch</strong></div>';
            
            this.sugerenciasBatch.forEach(sug => {
                html += `
                    <div class="mp-sugerencia" onclick="multiPedido.aceptarSugerenciaBatch(${JSON.stringify(sug).replace(/"/g, '&quot;')})">
                        <div style="font-weight: 600;">${sug.mensaje}</div>
                        <div style="font-size: 12px; color: #666; margin-top: 5px;">
                            💰 $${sug.ganancia_estimada.toFixed(2)} + 🎁 $${sug.bonificacion.toFixed(2)} bonus
                        </div>
                    </div>
                `;
            });
        }
        
        if (this.pedidosCercanos.length === 0) {
            html += `
                <div class="mp-empty">
                    <i class="fas fa-search-location"></i>
                    <p>No hay pedidos cercanos</p>
                    <p style="font-size: 12px; margin-top: 10px;">Los pedidos aparecerán cuando estén listos</p>
                </div>
            `;
        } else {
            html += '<div style="margin-bottom: 10px;"><strong>📍 Pedidos disponibles</strong></div>';
            
            this.pedidosCercanos.forEach(pedido => {
                html += `
                    <div class="mp-pedido-card" onclick="multiPedido.mostrarDetallePedido(${pedido.id_pedido})">
                        <div class="mp-pedido-title">${pedido.nombre_negocio}</div>
                        <div class="mp-pedido-info">
                            📍 ${pedido.distancia_negocio_km ? pedido.distancia_negocio_km.toFixed(1) + ' km' : 'Cerca'}
                            · 💰 $${parseFloat(pedido.total).toFixed(2)}
                        </div>
                        <div class="mp-pedido-badge">${pedido.estado}</div>
                    </div>
                `;
            });
        }
        
        content.innerHTML = html;
        
        // Actualizar badge
        const badge = document.getElementById('mp-badge');
        if (this.pedidosCercanos.length > 0) {
            badge.textContent = this.pedidosCercanos.length;
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }
    }
    
    actualizarUIEstadisticas() {
        const content = document.getElementById('mp-content');
        const stats = this.estadisticas || {};
        
        content.innerHTML = `
            <div class="mp-stats">
                <div class="mp-stat-card">
                    <div class="mp-stat-value">${stats.total_pedidos_completados || 0}</div>
                    <div class="mp-stat-label">Entregas</div>
                </div>
                <div class="mp-stat-card">
                    <div class="mp-stat-value">${stats.score_confiabilidad || 100}</div>
                    <div class="mp-stat-label">Score</div>
                </div>
                <div class="mp-stat-card">
                    <div class="mp-stat-value">${parseFloat(stats.tasa_cumplimiento || 100).toFixed(0)}%</div>
                    <div class="mp-stat-label">Cumplimiento</div>
                </div>
                <div class="mp-stat-card">
                    <div class="mp-stat-value">⭐ ${parseFloat(stats.calificacion_promedio || 5).toFixed(1)}</div>
                    <div class="mp-stat-label">Calificación</div>
                </div>
            </div>
            
            ${stats.bonificaciones_pendientes > 0 ? `
            <div style="background: #fff3e0; padding: 12px; border-radius: 8px; margin-bottom: 15px;">
                <div style="font-weight: 600; color: #f57c00;">
                    🎁 ${stats.bonificaciones_pendientes} bonificaciones pendientes
                </div>
                <div style="font-size: 20px; font-weight: 700; color: #f57c00; margin-top: 5px;">
                    +$${parseFloat(stats.monto_bonificaciones || 0).toFixed(2)}
                </div>
            </div>
            ` : ''}
            
            <button class="mp-btn mp-btn-primary" onclick="multiPedido.verLogros()">
                <i class="fas fa-trophy"></i> Ver mis logros (${stats.logros_desbloqueados || 0})
            </button>
        `;
    }
    
    async mostrarDetallePedido(idPedido) {
        const pedido = this.pedidosCercanos.find(p => p.id_pedido === idPedido);
        if (!pedido) return;
        
        const result = await Swal.fire({
            title: pedido.nombre_negocio,
            html: `
                <div style="text-align: left;">
                    <p><strong>📍 Distancia:</strong> ${pedido.distancia_negocio_km ? pedido.distancia_negocio_km.toFixed(1) + ' km' : 'Cerca'}</p>
                    <p><strong>💰 Total pedido:</strong> $${parseFloat(pedido.total).toFixed(2)}</p>
                    <p><strong>📦 Entregar en:</strong> ${pedido.direccion_entrega}</p>
                    <p><strong>🕐 Estado:</strong> ${pedido.estado}</p>
                </div>
            `,
            showCancelButton: true,
            showDenyButton: this.rutaActiva != null,
            confirmButtonText: 'Aceptar pedido',
            denyButtonText: 'Agregar a mi ruta',
            cancelButtonText: 'Cerrar',
            confirmButtonColor: '#00c853',
            denyButtonColor: '#667eea'
        });
        
        if (result.isConfirmed) {
            this.aceptarPedido(idPedido);
        } else if (result.isDenied) {
            this.agregarARuta(idPedido);
        }
    }
    
    async verLogros() {
        try {
            const res = await fetch(`${this.apiBase}?action=logros`);
            const data = await res.json();
            
            let html = '<div style="max-height: 400px; overflow-y: auto;">';
            
            (data.logros || []).forEach(logro => {
                const desbloqueado = logro.desbloqueado == 1;
                const opacity = desbloqueado ? '1' : '0.4';
                const bg = desbloqueado ? '#e8f5e9' : '#f5f5f5';
                
                html += `
                    <div style="display: flex; align-items: center; padding: 10px; margin-bottom: 8px; background: ${bg}; border-radius: 10px; opacity: ${opacity};">
                        <span style="font-size: 32px; margin-right: 12px;">${logro.icono}</span>
                        <div style="flex: 1;">
                            <div style="font-weight: 600;">${logro.nombre}</div>
                            <div style="font-size: 12px; color: #666;">${logro.descripcion}</div>
                            ${logro.bonificacion > 0 ? `<div style="font-size: 11px; color: #00c853;">+$${logro.bonificacion}</div>` : ''}
                        </div>
                        ${desbloqueado ? '<span style="color: #00c853;">✓</span>' : ''}
                    </div>
                `;
            });
            
            html += '</div>';
            
            Swal.fire({
                title: '🏆 Mis Logros',
                html: html,
                width: 400,
                confirmButtonText: 'Cerrar'
            });
            
        } catch (e) {
            console.error('Error cargando logros:', e);
        }
    }
    
    mostrarSugerenciasBatch() {
        if (!this.sugerenciasBatch || this.sugerenciasBatch.length === 0) return;
        
        // Mostrar notificación discreta si hay sugerencias
        const badge = document.getElementById('mp-badge');
        if (badge && this.sugerenciasBatch.length > 0) {
            badge.style.background = '#667eea';
            badge.textContent = '💡';
            badge.style.display = 'flex';
        }
    }
    
    mostrarNotificacion(tipo, titulo, mensaje) {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
        
        Toast.fire({
            icon: tipo,
            title: titulo,
            text: mensaje
        });
    }
}

// Inicializar cuando el DOM esté listo
let multiPedido;
document.addEventListener('DOMContentLoaded', () => {
    multiPedido = new MultiPedidoManager({
        apiBase: '/api/repartidor_pedidos.php',
        onUpdate: (data) => {
            console.log('Multi-pedido actualizado:', data);
        }
    });
});
