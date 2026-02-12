/**
 * Orez Floristeria - JavaScript especializado
 * Selector de disenos, variantes de cantidad, complementos
 * v1.2 - Sin WhatsApp, sin campo de mensaje (ya esta en checkout)
 */

// Configuracion de Orez
const OREZ_CONFIG = {
    negocioId: 9,
    disenosPath: 'assets/img/productos/OREZ/ROSAS/',
    totalDisenos: 12,
    // Variantes de cantidad para ramos de rosas dinamicos
    variantesRosas: [
        { cantidad: 12, precio: 449, label: '12 Rosas' },
        { cantidad: 25, precio: 875, label: '25 Rosas' },
        { cantidad: 50, precio: 1719, label: '50 Rosas' },
        { cantidad: 75, precio: 2399, label: '75 Rosas' },
        { cantidad: 100, precio: 3199, label: '100 Rosas' },
        { cantidad: 150, precio: 4599, label: '150 Rosas' },
        { cantidad: 200, precio: 5999, label: '200 Rosas' }
    ],
    complementos: []
};

// Estado del modal
let orezModalState = {
    productoBase: null,
    esRamoDinamico: false,
    disenoSeleccionado: 1,
    cantidadSeleccionada: 12,
    precioBase: 0,
    varianteSeleccionada: null, // {tipo, id, precio}
    complementosSeleccionados: [],
    cantidad: 1
};

/**
 * Generar HTML del selector de disenos (solo para ramos de rosas dinamicos)
 * Carrusel horizontal con scroll responsivo
 */
function generarSelectorDisenos() {
    let html = `
        <div class="orez-disenos-section">
            <div class="orez-disenos-title">
                <i class="fas fa-palette"></i> Elige tu diseño favorito
            </div>
            <div class="orez-disenos-carousel">
                <button class="orez-carousel-btn orez-carousel-prev" onclick="scrollDisenosCarousel(-1)" aria-label="Anterior">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <div class="orez-disenos-scroll" id="disenosScrollContainer">
    `;

    for (let i = 1; i <= OREZ_CONFIG.totalDisenos; i++) {
        const selected = i === orezModalState.disenoSeleccionado ? 'selected' : '';
        html += `
            <div class="orez-diseno-card ${selected}"
                 onclick="seleccionarDiseno(${i})"
                 data-diseno="${i}">
                <img src="${OREZ_CONFIG.disenosPath}rosas${i}.jpeg"
                     alt="Diseño ${i}"
                     loading="lazy"
                     onerror="this.src='assets/icons/rose.png'">
                <span class="diseno-number">${i}</span>
            </div>
        `;
    }

    html += `
                </div>
                <button class="orez-carousel-btn orez-carousel-next" onclick="scrollDisenosCarousel(1)" aria-label="Siguiente">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            <div class="orez-disenos-dots" id="disenosDots"></div>
        </div>
    `;
    return html;
}

/**
 * Scroll del carrusel de diseños
 */
function scrollDisenosCarousel(direction) {
    const container = document.getElementById('disenosScrollContainer');
    if (!container) return;

    const cardWidth = container.querySelector('.orez-diseno-card')?.offsetWidth || 100;
    const scrollAmount = cardWidth + 12; // card width + gap
    container.scrollBy({ left: direction * scrollAmount * 2, behavior: 'smooth' });
}

/**
 * Generar HTML del selector de cantidad (solo para ramos de rosas dinamicos)
 */
function generarSelectorCantidad() {
    let html = `
        <div class="orez-cantidad-section">
            <div class="orez-cantidad-title">
                <i class="fas fa-layer-group"></i> Cantidad de rosas
            </div>
            <div class="orez-cantidad-grid">
    `;

    let tieneRecargo = false;
    OREZ_CONFIG.variantesRosas.forEach(variante => {
        // Aplica recargo del 5% a todas las variantes
        let precioConRecargo = Math.round(variante.precio * 1.05);
        tieneRecargo = true;
        const selected = variante.cantidad === orezModalState.cantidadSeleccionada ? 'selected' : '';
        html += `
            <div class="orez-cantidad-chip ${selected}"
                 onclick="seleccionarCantidadRosas(${variante.cantidad}, ${precioConRecargo})"
                 data-cantidad="${variante.cantidad}"
                 data-precio="${precioConRecargo}">
                <span class="cantidad-label">${variante.label}</span>
                <span class="cantidad-precio">$${precioConRecargo.toLocaleString()}</span>
            </div>
        `;
    });
    // Mostrar leyenda si hay recargo
    if (tieneRecargo) {
        html += `<div style=\"font-size:0.95em;color:#E91E63;margin-top:8px;\"><i class='fas fa-info-circle'></i> Todos los precios incluyen recargo del 5%</div>`;
    }

    html += '</div></div>';
    return html;
}

/**
 * Generar HTML del selector de tamano (Completo/Mitad/Doble)
 * Solo se muestra si el producto tiene variantes en BD
 */
function generarSelectorTamano(productData) {
    const variantes = productData.variantes_tamano || [];
    if (variantes.length === 0) return '';

    // Guardar datos originales del producto en variable global temporal
    window._orezVariantesData = {
        completo: {
            tipo: 'completo',
            id: productData.id_producto,
            precio: parseFloat(productData.precio),
            nombre: productData.nombre || 'Producto',
            imagen: productData.imagen || 'assets/icons/rose.png',
            descripcion: productData.descripcion || ''
        }
    };

    // Agregar variantes al objeto global
    variantes.forEach((v, idx) => {
        let precioVariante = parseFloat(v.precio);
        // Aplica recargo del 5% solo a Mitad y Doble
        if (v.tipo === 'mitad' || v.tipo === 'doble') {
            precioVariante = Math.round(precioVariante * 1.05);
        }
        window._orezVariantesData[v.tipo] = {
            tipo: v.tipo,
            id: v.id,
            precio: precioVariante,
            nombre: v.nombre || productData.nombre,
            imagen: v.imagen || productData.imagen || 'assets/icons/rose.png',
            descripcion: v.descripcion || ''
        };
    });

    let html = `
        <div class="orez-tamano-section">
            <div class="orez-tamano-title">
                <i class="fas fa-ruler"></i> Tamaño del ramo
            </div>
            <div class="orez-tamano-options">
    `;

    // Opcion Completo (producto principal)
    const precioCompleto = parseFloat(productData.precio);
    const completoSeleccionado = !orezModalState.varianteSeleccionada ||
                                  orezModalState.varianteSeleccionada.tipo === 'completo';

    html += `
        <label class="orez-tamano-option ${completoSeleccionado ? 'selected' : ''}"
               onclick="seleccionarVarianteByTipo('completo')">
            <input type="radio" name="tamano_ramo" value="completo" ${completoSeleccionado ? 'checked' : ''}>
            <div class="tamano-icon">&#x1F339;</div>
            <span class="tamano-label">Completo</span>
            <span class="tamano-precio">$${precioCompleto.toLocaleString()}</span>
        </label>
    `;

    // Opciones de variantes (Mitad/Doble)
    let tieneRecargo = false;
    variantes.forEach(v => {
        if (v.tipo === 'completo') return;

        const esSeleccionada = orezModalState.varianteSeleccionada &&
                               orezModalState.varianteSeleccionada.tipo === v.tipo;
        const icon = v.tipo === 'mitad' ? '&#x1F337;' : '&#x1F490;';
        const label = v.tipo === 'mitad' ? 'Mitad' : 'Doble';
        let precioVariante = parseFloat(v.precio);
        if (v.tipo === 'mitad' || v.tipo === 'doble') {
            precioVariante = Math.round(precioVariante * 1.05);
            tieneRecargo = true;
        }
        html += `
            <label class="orez-tamano-option ${esSeleccionada ? 'selected' : ''}"
                   onclick="seleccionarVarianteByTipo('${v.tipo}')">
                <input type="radio" name="tamano_ramo" value="${v.tipo}" ${esSeleccionada ? 'checked' : ''}>
                <div class="tamano-icon">${icon}</div>
                <span class="tamano-label">${label}</span>
                <span class="tamano-precio">$${precioVariante.toLocaleString()}</span>
            </label>
        `;
    });
    // Mostrar leyenda si hay recargo
    if (tieneRecargo) {
        html += `<div style="font-size:0.95em;color:#E91E63;margin-top:8px;"><i class='fas fa-info-circle'></i> Mitad y Doble incluyen recargo del 5%</div>`;
    }

    html += '</div></div>';
    return html;
}

/**
 * Seleccionar variante por tipo (usa datos del objeto global)
 */
function seleccionarVarianteByTipo(tipo) {
    const data = window._orezVariantesData?.[tipo];
    if (!data) {
        console.error('No se encontró variante:', tipo);
        return;
    }
    seleccionarVariante(data.tipo, data.id, data.precio, data.nombre, data.imagen, data.descripcion);
}

/**
 * Generar HTML de grupos de opciones (colores, tamaños personalizados, etc.)
 */
function generarGruposOpciones(gruposOpciones) {
    if (!gruposOpciones || gruposOpciones.length === 0) return '';

    let html = '';

    gruposOpciones.forEach(grupo => {
        html += `
            <div class="orez-opciones-section" style="margin-bottom: 20px;">
                <div class="orez-opciones-title" style="font-size: 0.95rem; font-weight: 700; color: var(--orez-primary, #E91E63); margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-palette"></i> ${grupo.nombre}
                    ${grupo.es_obligatorio ? '<span style="background: #dc3545; color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.7rem;">Requerido</span>' : ''}
                </div>
                <div class="orez-opciones-grid" style="display: flex; flex-wrap: wrap; gap: 10px;">
        `;

        grupo.opciones.forEach(opcion => {
            const selected = opcion.por_defecto ? 'selected' : '';
            const borderColor = opcion.por_defecto ? 'var(--orez-primary, #E91E63)' : '#e9ecef';
            const bgColor = opcion.por_defecto ? 'rgba(233, 30, 99, 0.05)' : 'white';

            html += `
                <div class="orez-opcion-chip ${selected}"
                     onclick="seleccionarOpcionGrupo(${grupo.id_grupo_opcion}, ${opcion.id_opcion}, ${opcion.precio_adicional}, this)"
                     data-grupo="${grupo.id_grupo_opcion}"
                     data-opcion="${opcion.id_opcion}"
                     data-precio="${opcion.precio_adicional}"
                     style="padding: 12px 20px; border: 2px solid ${borderColor}; border-radius: 12px; background: ${bgColor}; cursor: pointer; transition: all 0.2s ease; text-align: center; min-width: 100px;">
                    <div style="font-weight: 600; font-size: 0.9rem; color: #333;">${opcion.nombre}</div>
                    ${opcion.precio_adicional > 0 ? `<div style="font-size: 0.8rem; color: var(--orez-primary, #E91E63);">+$${parseFloat(opcion.precio_adicional).toFixed(0)}</div>` : ''}
                    <input type="radio" name="opcion_grupo_${grupo.id_grupo_opcion}" value="${opcion.id_opcion}" ${opcion.por_defecto ? 'checked' : ''} style="display: none;" ${grupo.es_obligatorio ? 'required' : ''}>
                </div>
            `;
        });

        html += '</div></div>';
    });

    return html;
}

/**
 * Seleccionar opción de un grupo
 */
function seleccionarOpcionGrupo(grupoId, opcionId, precioAdicional, element) {
    // Deseleccionar otras opciones del mismo grupo
    document.querySelectorAll(`.orez-opcion-chip[data-grupo="${grupoId}"]`).forEach(chip => {
        chip.classList.remove('selected');
        chip.style.borderColor = '#e9ecef';
        chip.style.background = 'white';
    });

    // Seleccionar esta opción
    element.classList.add('selected');
    element.style.borderColor = 'var(--orez-primary, #E91E63)';
    element.style.background = 'rgba(233, 30, 99, 0.05)';

    // Marcar el radio button
    const radio = element.querySelector('input[type="radio"]');
    if (radio) radio.checked = true;

    // Guardar en estado
    if (!orezModalState.opcionesSeleccionadas) {
        orezModalState.opcionesSeleccionadas = {};
    }
    orezModalState.opcionesSeleccionadas[grupoId] = {
        id: opcionId,
        precio: parseFloat(precioAdicional) || 0
    };

    actualizarResumenPrecio();
    actualizarPrecioModal();
}

/**
 * Generar HTML de complementos/extras con imágenes
 */
function generarComplementos(complementos) {
    if (!complementos || complementos.length === 0) return '';

    let html = `
        <div class="orez-complementos-section">
            <div class="orez-complementos-title">
                <i class="fas fa-gift"></i> Complementos opcionales
            </div>
            <div class="orez-complementos-grid">
    `;

    complementos.forEach(comp => {
        const selected = orezModalState.complementosSeleccionados.some(c => c.id == comp.id_producto) ? 'selected' : '';
        const checked = selected ? 'checked' : '';
        const imagenSrc = comp.imagen || 'assets/icons/gift.png';

        html += `
            <label class="orez-complemento-card ${selected}"
                   onclick="toggleComplemento(${comp.id_producto}, ${comp.precio}, '${comp.nombre.replace(/'/g, "\\'")}', this); event.preventDefault();">
                <input type="checkbox" name="complementos[]"
                       value="${comp.id_producto}"
                       data-precio="${comp.precio}"
                       data-nombre="${comp.nombre}"
                       ${checked}>
                <div class="orez-complemento-img">
                    <img src="${imagenSrc}" alt="${comp.nombre}" loading="lazy"
                         onerror="this.src='assets/icons/gift.png'">
                    <span class="orez-complemento-check"><i class="fas fa-check"></i></span>
                </div>
                <div class="orez-complemento-info">
                    <div class="orez-complemento-nombre">${comp.nombre}</div>
                    <div class="orez-complemento-precio">+$${parseFloat(comp.precio).toFixed(0)}</div>
                </div>
            </label>
        `;
    });

    html += '</div></div>';
    return html;
}

/**
 * Generar resumen de precio
 */
function generarResumenPrecio() {
    let precioProducto = orezModalState.precioBase;
    let nombreProducto = orezModalState.productoBase?.nombre || 'Producto';

    // Si es ramo dinamico, usar precio de cantidad seleccionada
    if (orezModalState.esRamoDinamico) {
        precioProducto = orezModalState.precioBase;
        nombreProducto = `Ramo de ${orezModalState.cantidadSeleccionada} Rosas`;
    }

    // Si hay variante seleccionada (Mitad/Doble), usar nombre real de la variante
    if (orezModalState.varianteSeleccionada && orezModalState.varianteSeleccionada.tipo !== 'completo') {
        precioProducto = orezModalState.varianteSeleccionada.precio;
        // Usar nombre real de la variante si está disponible
        if (orezModalState.varianteSeleccionada.nombre) {
            nombreProducto = orezModalState.varianteSeleccionada.nombre;
        } else {
            const tipoLabel = orezModalState.varianteSeleccionada.tipo === 'mitad' ? ' (Mitad)' : ' (Doble)';
            nombreProducto += tipoLabel;
        }
    }

    let subtotalComplementos = 0;
    orezModalState.complementosSeleccionados.forEach(c => {
        subtotalComplementos += parseFloat(c.precio);
    });

    const subtotal = precioProducto + subtotalComplementos;
    const total = subtotal * orezModalState.cantidad;

    let html = `
        <div class="orez-precio-resumen">
            <div class="orez-precio-item">
                <span>${nombreProducto}</span>
                <span>$${precioProducto.toLocaleString()}</span>
            </div>
    `;

    if (subtotalComplementos > 0) {
        html += `
            <div class="orez-precio-item">
                <span>Complementos (${orezModalState.complementosSeleccionados.length})</span>
                <span>+$${subtotalComplementos.toFixed(0)}</span>
            </div>
        `;
    }

    if (orezModalState.cantidad > 1) {
        html += `
            <div class="orez-precio-item">
                <span>Cantidad</span>
                <span>x${orezModalState.cantidad}</span>
            </div>
        `;
    }

    html += `
            <div class="orez-precio-item total">
                <span>Total</span>
                <span class="precio-valor">$${total.toLocaleString()}</span>
            </div>
        </div>
    `;

    return html;
}

/**
 * Seleccionar diseno (para ramos de rosas dinamicos)
 */
function seleccionarDiseno(numero) {
    orezModalState.disenoSeleccionado = numero;

    // Actualizar UI
    document.querySelectorAll('.orez-diseno-card').forEach(card => {
        card.classList.remove('selected');
        if (parseInt(card.dataset.diseno) === numero) {
            card.classList.add('selected');
        }
    });

    // Cambiar imagen principal del modal
    const imagenModal = document.getElementById('modalProductImage');
    if (imagenModal) {
        imagenModal.src = `${OREZ_CONFIG.disenosPath}rosas${numero}.jpeg`;
    }

    // Actualizar campo oculto
    const inputDiseno = document.getElementById('orezDisenoInput');
    if (inputDiseno) inputDiseno.value = numero;
}

/**
 * Seleccionar cantidad de rosas (para ramos dinamicos)
 */
function seleccionarCantidadRosas(cantidad, precio) {
    console.log('[Orez] Seleccionando cantidad:', cantidad, 'precio:', precio);
    orezModalState.cantidadSeleccionada = cantidad;
    orezModalState.precioBase = precio;

    // Actualizar UI chips
    document.querySelectorAll('.orez-cantidad-chip').forEach(chip => {
        chip.classList.remove('selected');
        if (parseInt(chip.dataset.cantidad) === cantidad) {
            chip.classList.add('selected');
        }
    });

    // Actualizar campos ocultos
    const inputCantidad = document.getElementById('orezCantidadInput');
    if (inputCantidad) inputCantidad.value = cantidad;

    const inputPrecio = document.getElementById('orezPrecioInput');
    if (inputPrecio) inputPrecio.value = precio;

    actualizarResumenPrecio();
    actualizarPrecioModal();
}

/**
 * Seleccionar variante (Completo/Mitad/Doble)
 * Actualiza nombre, descripción, imagen y precio según la variante seleccionada
 */
function seleccionarVariante(tipo, idProducto, precio, nombreVariante, imagenVariante, descripcionVariante) {
    orezModalState.varianteSeleccionada = {
        tipo: tipo,
        id: idProducto,
        precio: parseFloat(precio),
        nombre: nombreVariante || null,
        imagen: imagenVariante || null,
        descripcion: descripcionVariante || null
    };

    // Actualizar UI de opciones
    document.querySelectorAll('.orez-tamano-option').forEach(opt => {
        opt.classList.remove('selected');
        const radio = opt.querySelector('input[type="radio"]');
        if (radio && radio.value === tipo) {
            opt.classList.add('selected');
            radio.checked = true;
        }
    });

    // Actualizar nombre del producto en el modal
    const nombreModal = document.getElementById('modalProductName');
    if (nombreModal) {
        if (tipo === 'completo') {
            nombreModal.textContent = orezModalState.productoBase?.nombre || 'Producto';
        } else if (nombreVariante) {
            nombreModal.textContent = nombreVariante;
        }
    }

    // Actualizar descripción del producto en el modal
    const descModal = document.getElementById('modalProductDesc');
    if (descModal) {
        if (tipo === 'completo') {
            descModal.textContent = orezModalState.productoBase?.descripcion || '';
        } else if (descripcionVariante) {
            descModal.textContent = descripcionVariante;
        }
    }

    // Actualizar imagen si la variante tiene una diferente
    const imagenModal = document.getElementById('modalProductImage');
    if (imagenModal) {
        if (tipo === 'completo') {
            imagenModal.src = orezModalState.productoBase?.imagen || 'assets/icons/rose.png';
        } else if (imagenVariante) {
            imagenModal.src = imagenVariante;
        }
    }

    // Actualizar campo oculto con ID del producto seleccionado
    const inputProductId = document.getElementById('modalProductId');
    if (inputProductId) inputProductId.value = idProducto;

    // Actualizar campo de tamano
    const inputTamano = document.getElementById('orezTamanoInput');
    if (inputTamano) inputTamano.value = tipo;

    actualizarResumenPrecio();
    actualizarPrecioModal();
}

/**
 * Toggle complemento
 */
function toggleComplemento(idProducto, precio, nombre, element) {
    const checkbox = element.querySelector('input[type="checkbox"]');
    const index = orezModalState.complementosSeleccionados.findIndex(c => c.id == idProducto);

    if (index > -1) {
        orezModalState.complementosSeleccionados.splice(index, 1);
        element.classList.remove('selected');
        checkbox.checked = false;
    } else {
        orezModalState.complementosSeleccionados.push({
            id: idProducto,
            precio: parseFloat(precio),
            nombre: nombre
        });
        element.classList.add('selected');
        checkbox.checked = true;
    }

    actualizarResumenPrecio();
    actualizarPrecioModal();
}

/**
 * Actualizar resumen de precio en el modal
 */
function actualizarResumenPrecio() {
    const resumenContainer = document.querySelector('.orez-precio-resumen');
    if (resumenContainer) {
        resumenContainer.outerHTML = generarResumenPrecio();
    }
}

/**
 * Actualizar precio en el boton del modal
 */
function actualizarPrecioModal() {
    const precioBtn = document.getElementById('modalTotalPrice');
    if (precioBtn) {
        const total = calcularTotalOrez();
        precioBtn.textContent = '$' + total.toLocaleString();
    }

    // Actualizar precio visible
    const precioVisible = document.getElementById('modalProductPrice');
    if (precioVisible) {
        let precioUnitario = orezModalState.precioBase;
        if (orezModalState.varianteSeleccionada && orezModalState.varianteSeleccionada.tipo !== 'completo') {
            precioUnitario = orezModalState.varianteSeleccionada.precio;
        }
        precioVisible.textContent = '$' + precioUnitario.toLocaleString();
    }
}

/**
 * Calcular total
 */
function calcularTotalOrez() {
    let precioBase = orezModalState.precioBase;

    // Si hay variante seleccionada, usar su precio
    if (orezModalState.varianteSeleccionada && orezModalState.varianteSeleccionada.tipo !== 'completo') {
        precioBase = orezModalState.varianteSeleccionada.precio;
    }

    // Sumar precios de opciones seleccionadas (colores, etc.)
    let subtotalOpciones = 0;
    if (orezModalState.opcionesSeleccionadas) {
        Object.values(orezModalState.opcionesSeleccionadas).forEach(opt => {
            subtotalOpciones += opt.precio || 0;
        });
    }

    let subtotalComplementos = 0;
    orezModalState.complementosSeleccionados.forEach(c => {
        subtotalComplementos += c.precio;
    });

    return (precioBase + subtotalOpciones + subtotalComplementos) * orezModalState.cantidad;
}

/**
 * Abrir modal especializado de Orez
 * @param {Object} productData - Datos del producto
 * @param {Array} complementos - Lista de complementos disponibles
 * @param {boolean} esRamoDinamico - Si es un ramo de rosas con selector de cantidad/diseno
 */
function openOrezProductModal(productData, complementos, esRamoDinamico = false) {
    console.log('[Orez Modal] Abriendo modal:', {
        producto: productData.nombre,
        esRamoDinamico: esRamoDinamico,
        complementos: complementos?.length || 0
    });

    // Guardar complementos en config
    OREZ_CONFIG.complementos = complementos || [];

    // Resetear estado
    orezModalState = {
        productoBase: productData,
        esRamoDinamico: esRamoDinamico,
        disenoSeleccionado: 1,
        cantidadSeleccionada: 12,
        precioBase: parseFloat(productData.precio) || 449,
        varianteSeleccionada: null,
        complementosSeleccionados: [],
        opcionesSeleccionadas: {},
        cantidad: 1
    };

    // Para ramos dinamicos, detectar cantidad inicial
    if (esRamoDinamico) {
        const nombreMatch = productData.nombre.match(/(\d+)\s*Rosas/i);
        if (nombreMatch) {
            const cantidad = parseInt(nombreMatch[1]);
            const variante = OREZ_CONFIG.variantesRosas.find(v => v.cantidad === cantidad);
            if (variante) {
                orezModalState.cantidadSeleccionada = variante.cantidad;
                orezModalState.precioBase = variante.precio;
            }
        }
    }

    // Establecer datos basicos del modal
    let imagenSrc = productData.imagen || `${OREZ_CONFIG.disenosPath}rosas1.jpeg`;
    let nombreModal = productData.nombre;
    let descripcionModal = productData.descripcion || 'Hermoso arreglo floral de Orez Floristeria.';

    if (esRamoDinamico) {
        imagenSrc = `${OREZ_CONFIG.disenosPath}rosas1.jpeg`;
        nombreModal = 'Ramo de Rosas - Elige tu Diseno';
        descripcionModal = 'Hermoso ramo de rosas frescas. Selecciona la cantidad y el diseno que mas te guste.';
    }

    document.getElementById('modalProductImage').src = imagenSrc;
    document.getElementById('modalProductName').textContent = nombreModal;
    document.getElementById('modalProductDesc').textContent = descripcionModal;
    document.getElementById('modalProductPrice').textContent = '$' + orezModalState.precioBase.toLocaleString();
    document.getElementById('modalProductId').value = productData.id_producto;
    document.getElementById('modalQuantity').value = 1;

    // Generar contenido especializado
    const optionsContainer = document.getElementById('modalOptionsContainer');

    let html = '';

    // Solo mostrar selector de disenos y cantidad para ramos dinamicos
    if (esRamoDinamico) {
        html += generarSelectorDisenos();
        html += generarSelectorCantidad();
    }

    // Selector de tamano solo si hay variantes en BD
    if (productData.tiene_variantes_tamano && productData.variantes_tamano && productData.variantes_tamano.length > 0) {
        html += generarSelectorTamano(productData);
    }

    // Nombre del producto en minúsculas para comparaciones
    const nombreLower = (productData.nombre || '').toLowerCase();

    // Grupos de opciones personalizados (colores, etc.) - solo para productos como orquídeas
    // NO mostrar para ramos de rosas (tienen su propio selector de diseño)
    const esRamoRosas = nombreLower.includes('ramo') && nombreLower.includes('rosas');
    if (productData.grupos_opciones && productData.grupos_opciones.length > 0 && !esRamoRosas && !esRamoDinamico) {
        html += generarGruposOpciones(productData.grupos_opciones);
    }

    // Complementos (Extras para Ramos) - SOLO para productos que sean ramos
    const esRamo = nombreLower.includes('ramo') || nombreLower.includes('rosas') ||
                   nombreLower.includes('tulipan') || nombreLower.includes('gerbera');

    if (OREZ_CONFIG.complementos.length > 0 && esRamo) {
        html += generarComplementos(OREZ_CONFIG.complementos);
    }

    // Resumen de precio
    html += generarResumenPrecio();

    // Campos ocultos para datos del pedido
    html += `
        <input type="hidden" name="orez_diseno" id="orezDisenoInput" value="${orezModalState.disenoSeleccionado}">
        <input type="hidden" name="orez_cantidad_rosas" id="orezCantidadInput" value="${orezModalState.cantidadSeleccionada}">
        <input type="hidden" name="orez_tamano" id="orezTamanoInput" value="completo">
        <input type="hidden" name="orez_precio_calculado" id="orezPrecioInput" value="${orezModalState.precioBase}">
    `;

    optionsContainer.innerHTML = html;

    console.log('[Orez Modal] HTML generado:', {
        tieneDisenos: html.includes('orez-disenos-section'),
        tieneCantidad: html.includes('orez-cantidad-section'),
        tieneVariantes: html.includes('orez-tamano-section'),
        tieneComplementos: html.includes('orez-complementos-section'),
        esRamoDinamico: esRamoDinamico
    });

    // Actualizar precio total inicial
    actualizarPrecioModal();

    // Abrir modal
    const modal = new bootstrap.Modal(document.getElementById('productModal'));
    modal.show();
}

/**
 * Actualizar cantidad desde el modal estandar
 */
function actualizarCantidadOrez(nuevaCantidad) {
    orezModalState.cantidad = parseInt(nuevaCantidad) || 1;
    actualizarResumenPrecio();
    actualizarPrecioModal();
}

// Escuchar cambios en el selector de cantidad del modal
document.addEventListener('DOMContentLoaded', function() {
    const quantityInput = document.getElementById('modalQuantity');
    if (quantityInput) {
        quantityInput.addEventListener('change', function() {
            if (orezModalState.productoBase) {
                actualizarCantidadOrez(this.value);
            }
        });
    }
    console.log('Orez Floristeria - Modulo cargado v1.4');
});

// Exportar funciones para uso global
window.openOrezProductModal = openOrezProductModal;
window.seleccionarDiseno = seleccionarDiseno;
window.seleccionarCantidadRosas = seleccionarCantidadRosas;
window.seleccionarVariante = seleccionarVariante;
window.seleccionarVarianteByTipo = seleccionarVarianteByTipo;
window.toggleComplemento = toggleComplemento;
window.calcularTotalOrez = calcularTotalOrez;
window.actualizarCantidadOrez = actualizarCantidadOrez;
window.scrollDisenosCarousel = scrollDisenosCarousel;
window.seleccionarOpcionGrupo = seleccionarOpcionGrupo;
