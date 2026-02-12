/**
 * Componente de Personalización por Unidad
 * 
 * Permite personalizar cada unidad de un producto individualmente
 * Ejemplo: 3 tacos, cada uno con diferente tipo de carne y modificadores
 */

class PersonalizadorUnidad {
    constructor(options = {}) {
        this.containerId = options.containerId || 'personalizador-container';
        this.producto = null;
        this.cantidad = options.cantidad || 1;
        this.unidades = [];
        this.elegibles = [];
        this.gruposOpciones = [];
        this.onPrecioChange = options.onPrecioChange || null;
        this.onConfirm = options.onConfirm || null;
        // Configuración de mensajes personalizados
        this.permiteMensajeTarjeta = false;
        this.permiteTextoProducto = false;
        this.limiteTextoProducto = 50;
    }
    
    /**
     * Cargar datos del producto desde la API
     */
    async cargarProducto(idProducto) {
        try {
            const response = await fetch(`/api/producto_opciones.php?id_producto=${idProducto}`);
            const data = await response.json();

            if (!data.success) {
                throw new Error(data.error || 'Error cargando producto');
            }

            this.producto = data.producto;
            this.elegibles = data.elegibles || [];
            this.gruposOpciones = data.grupos_opciones || [];

            // Configuración de mensajes personalizados
            this.permiteMensajeTarjeta = data.producto?.permite_mensaje_tarjeta || false;
            this.permiteTextoProducto = data.producto?.permite_texto_producto || false;
            this.limiteTextoProducto = data.producto?.limite_texto_producto || 50;

            // Inicializar unidades vacías
            this.inicializarUnidades();

            return data;
        } catch (error) {
            console.error('Error cargando producto:', error);
            throw error;
        }
    }
    
    /**
     * Inicializar array de unidades según cantidad
     */
    inicializarUnidades() {
        this.unidades = [];
        for (let i = 1; i <= this.cantidad; i++) {
            this.unidades.push({
                numero_unidad: i,
                id_elegible: this.elegibles.length > 0 ? this.elegibles[0].id_elegible : null,
                opciones: [],
                notas: '',
                mensaje_tarjeta: '',
                texto_producto: ''
            });
        }
    }
    
    /**
     * Actualizar cantidad y reinicializar unidades
     */
    setCantidad(cantidad) {
        this.cantidad = Math.max(1, parseInt(cantidad) || 1);

        // Mantener configuraciones existentes si es posible
        const unidadesExistentes = [...this.unidades];
        this.inicializarUnidades();

        // Copiar configuraciones existentes (incluyendo mensajes)
        for (let i = 0; i < Math.min(unidadesExistentes.length, this.cantidad); i++) {
            this.unidades[i] = {
                ...unidadesExistentes[i],
                numero_unidad: i + 1,
                mensaje_tarjeta: unidadesExistentes[i].mensaje_tarjeta || '',
                texto_producto: unidadesExistentes[i].texto_producto || ''
            };
        }

        this.render();
        this.calcularPrecio();
    }
    
    /**
     * Renderizar el personalizador en el contenedor
     */
    render() {
        const container = document.getElementById(this.containerId);
        if (!container) {
            console.error('Contenedor no encontrado:', this.containerId);
            return;
        }
        
        let html = `
            <div class="personalizador-unidad">
                <div class="personalizador-header">
                    <h5 class="mb-2">${this.producto?.nombre || 'Producto'}</h5>
                    <p class="text-muted small mb-3">Personaliza cada unidad individualmente</p>
                    
                    <!-- Selector de cantidad -->
                    <div class="cantidad-selector mb-4">
                        <label class="form-label fw-semibold">Cantidad:</label>
                        <div class="input-group" style="max-width: 150px;">
                            <button class="btn btn-outline-secondary" type="button" onclick="personalizador.setCantidad(${this.cantidad - 1})">
                                <i class="fas fa-minus"></i>
                            </button>
                            <input type="number" class="form-control text-center" value="${this.cantidad}" min="1" 
                                   onchange="personalizador.setCantidad(this.value)">
                            <button class="btn btn-outline-secondary" type="button" onclick="personalizador.setCantidad(${this.cantidad + 1})">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Tabs para cada unidad -->
                <ul class="nav nav-tabs mb-3" id="unidadesTabs" role="tablist">
                    ${this.unidades.map((u, i) => `
                        <li class="nav-item" role="presentation">
                            <button class="nav-link ${i === 0 ? 'active' : ''}" 
                                    id="unidad-tab-${u.numero_unidad}" 
                                    data-bs-toggle="tab" 
                                    data-bs-target="#unidad-${u.numero_unidad}" 
                                    type="button" role="tab">
                                #${u.numero_unidad}
                                ${this.getResumenUnidad(u)}
                            </button>
                        </li>
                    `).join('')}
                </ul>
                
                <!-- Contenido de cada tab -->
                <div class="tab-content" id="unidadesContent">
                    ${this.unidades.map((u, i) => this.renderUnidadTab(u, i === 0)).join('')}
                </div>
                
                <!-- Resumen y precio -->
                <div class="personalizador-footer mt-4 pt-3 border-top">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="fw-semibold">Precio total:</span>
                        <span class="precio-total h4 mb-0 text-primary" id="precioTotalPersonalizado">
                            $${this.calcularPrecioSync().toFixed(2)}
                        </span>
                    </div>
                    
                    <div class="resumen-personalizacion mb-3" id="resumenPersonalizacion">
                        ${this.renderResumen()}
                    </div>
                    
                    <button class="btn btn-primary w-100" onclick="personalizador.confirmar()">
                        <i class="fas fa-cart-plus me-2"></i>
                        Agregar al carrito
                    </button>
                </div>
            </div>
        `;
        
        container.innerHTML = html;
    }
    
    /**
     * Renderizar tab de una unidad
     */
    renderUnidadTab(unidad, activo) {
        return `
            <div class="tab-pane fade ${activo ? 'show active' : ''}" 
                 id="unidad-${unidad.numero_unidad}" 
                 role="tabpanel">
                
                <!-- Elegibles (tipo de carne, etc.) -->
                ${this.elegibles.length > 0 ? `
                    <div class="elegibles-section mb-4">
                        <label class="form-label fw-semibold">
                            <i class="fas fa-drumstick-bite me-2"></i>Tipo:
                        </label>
                        <div class="elegibles-grid">
                            ${this.elegibles.map(e => `
                                <div class="form-check elegible-option ${unidad.id_elegible == e.id_elegible ? 'selected' : ''}">
                                    <input class="form-check-input" type="radio" 
                                           name="elegible-${unidad.numero_unidad}" 
                                           id="elegible-${unidad.numero_unidad}-${e.id_elegible}"
                                           value="${e.id_elegible}"
                                           ${unidad.id_elegible == e.id_elegible ? 'checked' : ''}
                                           onchange="personalizador.setElegible(${unidad.numero_unidad}, ${e.id_elegible})">
                                    <label class="form-check-label" for="elegible-${unidad.numero_unidad}-${e.id_elegible}">
                                        ${e.nombre}
                                        ${e.precio_adicional > 0 ? `<span class="badge bg-warning text-dark ms-1">+$${parseFloat(e.precio_adicional).toFixed(2)}</span>` : ''}
                                    </label>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                ` : ''}
                
                <!-- Grupos de opciones (modificadores) -->
                ${this.gruposOpciones.map(grupo => `
                    <div class="opciones-grupo mb-4">
                        <label class="form-label fw-semibold">
                            <i class="fas fa-sliders-h me-2"></i>${grupo.nombre}
                            ${grupo.obligatorio ? '<span class="text-danger">*</span>' : '<span class="text-muted small">(opcional)</span>'}
                        </label>
                        ${grupo.descripcion ? `<small class="text-muted d-block mb-2">${grupo.descripcion}</small>` : ''}
                        
                        <div class="opciones-grid">
                            ${grupo.opciones.map(opcion => {
                                const isChecked = unidad.opciones.includes(opcion.id_opcion);
                                const inputType = grupo.tipo_seleccion === 'unica' ? 'radio' : 'checkbox';
                                
                                return `
                                    <div class="form-check opcion-item ${isChecked ? 'selected' : ''}">
                                        <input class="form-check-input" type="${inputType}" 
                                               name="grupo-${grupo.id_grupo_opcion}-unidad-${unidad.numero_unidad}" 
                                               id="opcion-${unidad.numero_unidad}-${opcion.id_opcion}"
                                               value="${opcion.id_opcion}"
                                               ${isChecked ? 'checked' : ''}
                                               onchange="personalizador.toggleOpcion(${unidad.numero_unidad}, ${opcion.id_opcion}, '${grupo.tipo_seleccion}')">
                                        <label class="form-check-label" for="opcion-${unidad.numero_unidad}-${opcion.id_opcion}">
                                            ${opcion.nombre}
                                            ${opcion.precio_adicional > 0 ? `<span class="badge bg-info ms-1">+$${parseFloat(opcion.precio_adicional).toFixed(2)}</span>` : ''}
                                        </label>
                                    </div>
                                `;
                            }).join('')}
                        </div>
                    </div>
                `).join('')}
                
                <!-- Notas específicas -->
                <div class="notas-unidad">
                    <label class="form-label fw-semibold">
                        <i class="fas fa-sticky-note me-2"></i>Notas (opcional):
                    </label>
                    <input type="text" class="form-control"
                           placeholder="Ej: bien dorado, sin limón..."
                           value="${unidad.notas || ''}"
                           onchange="personalizador.setNotas(${unidad.numero_unidad}, this.value)"
                           maxlength="100">
                </div>

                ${this.renderCamposMensaje(unidad)}
            </div>
        `;
    }

    /**
     * Renderizar campos de mensaje personalizado (diseño minimalista)
     */
    renderCamposMensaje(unidad) {
        if (!this.permiteTextoProducto && !this.permiteMensajeTarjeta) {
            return '';
        }

        return `
            <div class="mensaje-personalizado-section">
                ${this.permiteTextoProducto ? `
                <div class="campo-mensaje">
                    <label class="campo-mensaje-label">
                        <span class="campo-mensaje-icon">✍️</span>
                        Texto en el producto
                    </label>
                    <input type="text" class="campo-mensaje-input"
                           placeholder="Ej: Feliz Cumpleaños María"
                           value="${unidad.texto_producto || ''}"
                           onchange="personalizador.setTextoProducto(${unidad.numero_unidad}, this.value)"
                           maxlength="${this.limiteTextoProducto}">
                    <span class="campo-mensaje-contador">${(unidad.texto_producto || '').length}/${this.limiteTextoProducto}</span>
                </div>
                ` : ''}

                ${this.permiteMensajeTarjeta ? `
                <div class="campo-mensaje">
                    <label class="campo-mensaje-label">
                        <span class="campo-mensaje-icon">💌</span>
                        Mensaje de tarjeta
                    </label>
                    <textarea class="campo-mensaje-textarea"
                              placeholder="Ej: Felicidades hijo, te amamos mucho..."
                              onchange="personalizador.setMensajeTarjeta(${unidad.numero_unidad}, this.value)"
                              maxlength="500">${unidad.mensaje_tarjeta || ''}</textarea>
                    <span class="campo-mensaje-contador">${(unidad.mensaje_tarjeta || '').length}/500</span>
                </div>
                ` : ''}
            </div>
        `;
    }
    
    /**
     * Obtener resumen corto de una unidad para el tab
     */
    getResumenUnidad(unidad) {
        if (!unidad.id_elegible && unidad.opciones.length === 0) {
            return '';
        }
        
        let resumen = [];
        
        if (unidad.id_elegible) {
            const elegible = this.elegibles.find(e => e.id_elegible == unidad.id_elegible);
            if (elegible) {
                resumen.push(elegible.nombre.substring(0, 8));
            }
        }
        
        if (unidad.opciones.length > 0) {
            resumen.push(`+${unidad.opciones.length}`);
        }
        
        return resumen.length > 0 ? `<small class="ms-1 text-muted">(${resumen.join(', ')})</small>` : '';
    }
    
    /**
     * Renderizar resumen de todas las personalizaciones
     */
    renderResumen() {
        if (this.unidades.length === 0) return '';

        let html = '<div class="small">';

        this.unidades.forEach(unidad => {
            let items = [];

            if (unidad.id_elegible) {
                const elegible = this.elegibles.find(e => e.id_elegible == unidad.id_elegible);
                if (elegible) items.push(elegible.nombre);
            }

            unidad.opciones.forEach(idOpcion => {
                for (const grupo of this.gruposOpciones) {
                    const opcion = grupo.opciones.find(o => o.id_opcion == idOpcion);
                    if (opcion) {
                        items.push(opcion.nombre);
                        break;
                    }
                }
            });

            if (unidad.notas) {
                items.push(`"${unidad.notas}"`);
            }

            html += `<div class="mb-1"><strong>#${unidad.numero_unidad}:</strong> ${items.join(', ') || 'Sin personalizar'}</div>`;

            // Mostrar mensajes personalizados
            if (unidad.texto_producto) {
                html += `<div class="resumen-mensaje">✍️ "${unidad.texto_producto}"</div>`;
            }
            if (unidad.mensaje_tarjeta) {
                const msgCorto = unidad.mensaje_tarjeta.length > 40
                    ? unidad.mensaje_tarjeta.substring(0, 40) + '...'
                    : unidad.mensaje_tarjeta;
                html += `<div class="resumen-mensaje">💌 "${msgCorto}"</div>`;
            }
        });

        html += '</div>';
        return html;
    }
    
    /**
     * Establecer elegible para una unidad
     */
    setElegible(numeroUnidad, idElegible) {
        const unidad = this.unidades.find(u => u.numero_unidad === numeroUnidad);
        if (unidad) {
            unidad.id_elegible = parseInt(idElegible);
            this.actualizarUI();
        }
    }
    
    /**
     * Toggle opción para una unidad
     */
    toggleOpcion(numeroUnidad, idOpcion, tipoSeleccion) {
        const unidad = this.unidades.find(u => u.numero_unidad === numeroUnidad);
        if (!unidad) return;
        
        idOpcion = parseInt(idOpcion);
        
        if (tipoSeleccion === 'unica') {
            // Radio: reemplazar
            // Encontrar el grupo de esta opción
            for (const grupo of this.gruposOpciones) {
                const opcionEnGrupo = grupo.opciones.find(o => o.id_opcion === idOpcion);
                if (opcionEnGrupo) {
                    // Quitar otras opciones de este grupo
                    const idsGrupo = grupo.opciones.map(o => o.id_opcion);
                    unidad.opciones = unidad.opciones.filter(id => !idsGrupo.includes(id));
                    // Agregar la nueva
                    unidad.opciones.push(idOpcion);
                    break;
                }
            }
        } else {
            // Checkbox: toggle
            const index = unidad.opciones.indexOf(idOpcion);
            if (index > -1) {
                unidad.opciones.splice(index, 1);
            } else {
                unidad.opciones.push(idOpcion);
            }
        }
        
        this.actualizarUI();
    }
    
    /**
     * Establecer notas para una unidad
     */
    setNotas(numeroUnidad, notas) {
        const unidad = this.unidades.find(u => u.numero_unidad === numeroUnidad);
        if (unidad) {
            unidad.notas = notas.trim();
        }
    }

    /**
     * Establecer texto del producto para una unidad
     */
    setTextoProducto(numeroUnidad, texto) {
        const unidad = this.unidades.find(u => u.numero_unidad === numeroUnidad);
        if (unidad) {
            unidad.texto_producto = texto.trim();
            this.actualizarContador(numeroUnidad, 'texto');
        }
    }

    /**
     * Establecer mensaje de tarjeta para una unidad
     */
    setMensajeTarjeta(numeroUnidad, mensaje) {
        const unidad = this.unidades.find(u => u.numero_unidad === numeroUnidad);
        if (unidad) {
            unidad.mensaje_tarjeta = mensaje.trim();
            this.actualizarContador(numeroUnidad, 'tarjeta');
        }
    }

    /**
     * Actualizar contador de caracteres
     */
    actualizarContador(numeroUnidad, tipo) {
        const unidad = this.unidades.find(u => u.numero_unidad === numeroUnidad);
        if (!unidad) return;

        const container = document.getElementById(`unidad-${numeroUnidad}`);
        if (!container) return;

        const contadores = container.querySelectorAll('.campo-mensaje-contador');
        contadores.forEach(contador => {
            const campo = contador.previousElementSibling;
            if (campo) {
                const max = campo.getAttribute('maxlength') || 500;
                contador.textContent = `${campo.value.length}/${max}`;
            }
        });
    }
    
    /**
     * Actualizar UI después de cambios
     */
    actualizarUI() {
        // Actualizar precio
        const precioElement = document.getElementById('precioTotalPersonalizado');
        if (precioElement) {
            precioElement.textContent = `$${this.calcularPrecioSync().toFixed(2)}`;
        }
        
        // Actualizar resumen
        const resumenElement = document.getElementById('resumenPersonalizacion');
        if (resumenElement) {
            resumenElement.innerHTML = this.renderResumen();
        }
        
        // Actualizar tabs
        this.unidades.forEach(u => {
            const tab = document.getElementById(`unidad-tab-${u.numero_unidad}`);
            if (tab) {
                tab.innerHTML = `#${u.numero_unidad} ${this.getResumenUnidad(u)}`;
            }
        });
        
        // Callback de precio
        if (this.onPrecioChange) {
            this.onPrecioChange(this.calcularPrecioSync());
        }
    }
    
    /**
     * Calcular precio sincrónicamente
     */
    calcularPrecioSync() {
        let total = (this.producto?.precio || 0) * this.cantidad;
        
        this.unidades.forEach(unidad => {
            // Precio de elegible
            if (unidad.id_elegible) {
                const elegible = this.elegibles.find(e => e.id_elegible == unidad.id_elegible);
                if (elegible) {
                    total += parseFloat(elegible.precio_adicional) || 0;
                }
            }
            
            // Precio de opciones
            unidad.opciones.forEach(idOpcion => {
                for (const grupo of this.gruposOpciones) {
                    const opcion = grupo.opciones.find(o => o.id_opcion == idOpcion);
                    if (opcion) {
                        total += parseFloat(opcion.precio_adicional) || 0;
                        break;
                    }
                }
            });
        });
        
        return total;
    }
    
    /**
     * Calcular precio llamando a la API
     */
    async calcularPrecio() {
        try {
            const response = await fetch('/api/personalizacion_carrito.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'calcular_precio',
                    id_producto: this.producto.id_producto,
                    cantidad: this.cantidad,
                    unidades: this.unidades
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                const precioElement = document.getElementById('precioTotalPersonalizado');
                if (precioElement) {
                    precioElement.textContent = `$${data.precio_total.toFixed(2)}`;
                }
                
                if (this.onPrecioChange) {
                    this.onPrecioChange(data.precio_total);
                }
                
                return data.precio_total;
            }
        } catch (error) {
            console.error('Error calculando precio:', error);
        }
        
        return this.calcularPrecioSync();
    }
    
    /**
     * Obtener datos para agregar al carrito
     */
    getDatosCarrito() {
        return {
            id_producto: this.producto.id_producto,
            nombre: this.producto.nombre,
            cantidad: this.cantidad,
            precio_unitario: this.producto.precio,
            precio_total: this.calcularPrecioSync(),
            unidades: this.unidades,
            resumen: this.generarResumenTexto()
        };
    }
    
    /**
     * Generar resumen en texto para mostrar en carrito
     */
    generarResumenTexto() {
        let lineas = [];

        this.unidades.forEach(unidad => {
            let items = [];

            if (unidad.id_elegible) {
                const elegible = this.elegibles.find(e => e.id_elegible == unidad.id_elegible);
                if (elegible) items.push(elegible.nombre);
            }

            unidad.opciones.forEach(idOpcion => {
                for (const grupo of this.gruposOpciones) {
                    const opcion = grupo.opciones.find(o => o.id_opcion == idOpcion);
                    if (opcion) {
                        items.push(opcion.nombre);
                        break;
                    }
                }
            });

            if (unidad.notas) items.push(`[${unidad.notas}]`);

            lineas.push(`#${unidad.numero_unidad}: ${items.join(', ') || 'Estándar'}`);

            // Incluir mensajes personalizados
            if (unidad.texto_producto) {
                lineas.push(`  ✍️ "${unidad.texto_producto}"`);
            }
            if (unidad.mensaje_tarjeta) {
                lineas.push(`  💌 "${unidad.mensaje_tarjeta}"`);
            }
        });

        return lineas.join('\n');
    }
    
    /**
     * Confirmar y agregar al carrito
     */
    confirmar() {
        const datos = this.getDatosCarrito();
        
        if (this.onConfirm) {
            this.onConfirm(datos);
        } else {
            console.log('Datos para carrito:', datos);
            // Implementar lógica de agregar al carrito aquí
        }
    }
    
    /**
     * Aplicar todos los mismos valores a todas las unidades
     */
    aplicarATodas() {
        if (this.unidades.length < 2) return;

        const primera = this.unidades[0];

        for (let i = 1; i < this.unidades.length; i++) {
            this.unidades[i].id_elegible = primera.id_elegible;
            this.unidades[i].opciones = [...primera.opciones];
            this.unidades[i].notas = primera.notas;
            this.unidades[i].texto_producto = primera.texto_producto;
            this.unidades[i].mensaje_tarjeta = primera.mensaje_tarjeta;
        }

        this.render();
    }
}

// Estilos CSS para el personalizador
const personalizadorStyles = `
<style>
.personalizador-unidad {
    font-family: 'Inter', -apple-system, sans-serif;
}

.elegibles-grid, .opciones-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 0.5rem;
}

.elegible-option, .opcion-item {
    background: #f8f9fa;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    padding: 0.75rem;
    cursor: pointer;
    transition: all 0.2s ease;
}

.elegible-option:hover, .opcion-item:hover {
    border-color: #adb5bd;
    background: #e9ecef;
}

.elegible-option.selected, .opcion-item.selected {
    border-color: #667eea;
    background: #eef2ff;
}

.elegible-option .form-check-input,
.opcion-item .form-check-input {
    margin-top: 0.25rem;
}

.elegible-option .form-check-label,
.opcion-item .form-check-label {
    cursor: pointer;
    font-size: 0.9rem;
}

.nav-tabs .nav-link {
    font-size: 0.875rem;
    padding: 0.5rem 1rem;
}

.nav-tabs .nav-link.active {
    font-weight: 600;
    color: #667eea;
    border-color: #667eea #667eea #fff;
}

.resumen-personalizacion {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 1rem;
}

.cantidad-selector .form-control {
    font-weight: 600;
}

/* Estilos minimalistas para mensajes personalizados */
.mensaje-personalizado-section {
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid #e9ecef;
}

.campo-mensaje {
    position: relative;
    margin-bottom: 1rem;
}

.campo-mensaje-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    font-weight: 500;
    color: #374151;
    margin-bottom: 0.5rem;
}

.campo-mensaje-icon {
    font-size: 1rem;
}

.campo-mensaje-input,
.campo-mensaje-textarea {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    font-size: 0.875rem;
    transition: border-color 0.2s, box-shadow 0.2s;
    background: #fafafa;
}

.campo-mensaje-input:focus,
.campo-mensaje-textarea:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    background: #fff;
}

.campo-mensaje-input::placeholder,
.campo-mensaje-textarea::placeholder {
    color: #9ca3af;
}

.campo-mensaje-textarea {
    min-height: 80px;
    resize: vertical;
}

.campo-mensaje-contador {
    position: absolute;
    right: 0.75rem;
    bottom: 0.5rem;
    font-size: 0.7rem;
    color: #9ca3af;
}

.resumen-mensaje {
    margin-left: 1rem;
    padding: 0.25rem 0;
    font-size: 0.8rem;
    color: #6b7280;
    font-style: italic;
}

@media (max-width: 576px) {
    .elegibles-grid, .opciones-grid {
        grid-template-columns: 1fr 1fr;
    }

    .nav-tabs {
        flex-wrap: nowrap;
        overflow-x: auto;
    }

    .nav-tabs .nav-link {
        white-space: nowrap;
    }

    .mensaje-personalizado-section {
        margin-top: 1rem;
        padding-top: 1rem;
    }
}
</style>
`;

// Inyectar estilos al cargar
if (typeof document !== 'undefined') {
    document.head.insertAdjacentHTML('beforeend', personalizadorStyles);
}

// Exportar para uso global
if (typeof window !== 'undefined') {
    window.PersonalizadorUnidad = PersonalizadorUnidad;
}
