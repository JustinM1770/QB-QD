<?php
/**
 * QuickBite - Modal de Resena
 * Incluir este archivo en las paginas donde se quiera mostrar el modal de resena
 * Requiere: Bootstrap 5, FontAwesome
 */
?>

<!-- Modal de Resena -->
<div class="modal fade" id="modalResena" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 20px; overflow: hidden;">
            <div class="modal-header" style="background: linear-gradient(135deg, #00D1B2, #00A78E); color: white; border: none;">
                <h5 class="modal-title">
                    <i class="fas fa-star me-2"></i>
                    <span id="resenaTitle">Califica tu pedido</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding: 1.5rem;">
                <form id="formResena">
                    <input type="hidden" name="id_pedido" id="resenaIdPedido">

                    <!-- Calificacion del Negocio -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">
                            <i class="fas fa-store me-1"></i>
                            <span id="resenaBusinessName">El negocio</span>
                        </label>
                        <div class="star-rating" id="starsNegocio" data-target="calificacion_negocio">
                            <i class="fas fa-star star" data-value="1"></i>
                            <i class="fas fa-star star" data-value="2"></i>
                            <i class="fas fa-star star" data-value="3"></i>
                            <i class="fas fa-star star" data-value="4"></i>
                            <i class="fas fa-star star" data-value="5"></i>
                        </div>
                        <input type="hidden" name="calificacion_negocio" id="calificacion_negocio" value="0">
                        <textarea name="comentario" class="form-control mt-2" rows="2"
                                  placeholder="Cuéntanos tu experiencia con el restaurante (opcional)"
                                  style="border-radius: 12px;"></textarea>
                    </div>

                    <!-- Calificacion del Repartidor (solo si aplica) -->
                    <div class="mb-4" id="seccionRepartidor" style="display: none;">
                        <label class="form-label fw-bold">
                            <i class="fas fa-motorcycle me-1"></i>
                            <span id="resenaDriverName">El repartidor</span>
                        </label>
                        <div class="star-rating" id="starsRepartidor" data-target="calificacion_repartidor">
                            <i class="fas fa-star star" data-value="1"></i>
                            <i class="fas fa-star star" data-value="2"></i>
                            <i class="fas fa-star star" data-value="3"></i>
                            <i class="fas fa-star star" data-value="4"></i>
                            <i class="fas fa-star star" data-value="5"></i>
                        </div>
                        <input type="hidden" name="calificacion_repartidor" id="calificacion_repartidor" value="0">

                        <!-- Tiempo de entrega percibido -->
                        <div class="mt-2">
                            <small class="text-muted">¿Cómo fue el tiempo de entrega?</small>
                            <div class="btn-group w-100 mt-1" role="group">
                                <input type="radio" class="btn-check" name="tiempo_entrega" id="tiempo1" value="muy_rapido">
                                <label class="btn btn-outline-success btn-sm" for="tiempo1">Muy rápido</label>

                                <input type="radio" class="btn-check" name="tiempo_entrega" id="tiempo2" value="rapido">
                                <label class="btn btn-outline-success btn-sm" for="tiempo2">Rápido</label>

                                <input type="radio" class="btn-check" name="tiempo_entrega" id="tiempo3" value="normal" checked>
                                <label class="btn btn-outline-secondary btn-sm" for="tiempo3">Normal</label>

                                <input type="radio" class="btn-check" name="tiempo_entrega" id="tiempo4" value="lento">
                                <label class="btn btn-outline-warning btn-sm" for="tiempo4">Lento</label>
                            </div>
                        </div>

                        <textarea name="comentario_repartidor" class="form-control mt-2" rows="2"
                                  placeholder="Comentario sobre el repartidor (opcional)"
                                  style="border-radius: 12px;"></textarea>
                    </div>

                    <!-- Estado del pedido -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">
                            <i class="fas fa-box me-1"></i>
                            ¿Cómo llegó tu pedido?
                        </label>
                        <div class="d-flex gap-2 flex-wrap">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="estado_pedido" id="estado1" value="perfecto" checked>
                                <label class="form-check-label" for="estado1">Perfecto</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="estado_pedido" id="estado2" value="bien">
                                <label class="form-check-label" for="estado2">Bien</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="estado_pedido" id="estado3" value="con_problemas">
                                <label class="form-check-label" for="estado3">Con problemas</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="estado_pedido" id="estado4" value="danado">
                                <label class="form-check-label" for="estado4">Dañado</label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer" style="border: none; padding: 0 1.5rem 1.5rem;">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="border-radius: 12px;">
                    Más tarde
                </button>
                <button type="button" class="btn btn-primary" id="btnEnviarResena" style="border-radius: 12px; background: #00D1B2; border: none;">
                    <i class="fas fa-paper-plane me-1"></i>
                    Enviar reseña
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.star-rating {
    display: flex;
    gap: 8px;
    font-size: 2rem;
}

.star-rating .star {
    color: #E5E7EB;
    cursor: pointer;
    transition: all 0.2s ease;
}

.star-rating .star:hover,
.star-rating .star.active {
    color: #FFD700;
    transform: scale(1.1);
}

.star-rating .star:hover ~ .star {
    color: #E5E7EB;
}
</style>

<script>
// Sistema de estrellas interactivo
document.querySelectorAll('.star-rating').forEach(container => {
    const stars = container.querySelectorAll('.star');
    const targetInput = document.getElementById(container.dataset.target);

    stars.forEach(star => {
        star.addEventListener('click', () => {
            const value = star.dataset.value;
            targetInput.value = value;

            stars.forEach(s => {
                s.classList.toggle('active', s.dataset.value <= value);
            });
        });

        star.addEventListener('mouseenter', () => {
            const value = star.dataset.value;
            stars.forEach(s => {
                s.style.color = s.dataset.value <= value ? '#FFD700' : '#E5E7EB';
            });
        });

        star.addEventListener('mouseleave', () => {
            const currentValue = targetInput.value;
            stars.forEach(s => {
                s.style.color = s.dataset.value <= currentValue ? '#FFD700' : '#E5E7EB';
            });
        });
    });
});

// Funcion para abrir modal de resena
function abrirModalResena(pedido) {
    document.getElementById('resenaIdPedido').value = pedido.id_pedido;
    document.getElementById('resenaBusinessName').textContent = pedido.nombre_negocio;

    // Mostrar seccion de repartidor si aplica
    if (pedido.id_repartidor && pedido.tipo_pedido === 'delivery') {
        document.getElementById('seccionRepartidor').style.display = 'block';
        document.getElementById('resenaDriverName').textContent = pedido.nombre_repartidor || 'El repartidor';
    } else {
        document.getElementById('seccionRepartidor').style.display = 'none';
    }

    // Reset estrellas
    document.querySelectorAll('.star-rating .star').forEach(s => s.classList.remove('active'));
    document.getElementById('calificacion_negocio').value = 0;
    document.getElementById('calificacion_repartidor').value = 0;

    new bootstrap.Modal(document.getElementById('modalResena')).show();
}

// Enviar resena
document.getElementById('btnEnviarResena')?.addEventListener('click', async () => {
    const form = document.getElementById('formResena');
    const calNegocio = document.getElementById('calificacion_negocio').value;

    if (calNegocio < 1) {
        alert('Por favor califica al negocio');
        return;
    }

    const formData = new FormData(form);
    formData.append('action', 'crear');

    try {
        const response = await fetch('api/resenas.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('modalResena')).hide();
            mostrarNotificacionResena('¡Gracias por tu reseña!', 'success');
        } else {
            alert(data.message || 'Error al enviar reseña');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error de conexión');
    }
});

function mostrarNotificacionResena(mensaje, tipo) {
    const toast = document.createElement('div');
    toast.className = 'position-fixed top-0 start-50 translate-middle-x mt-3 p-3';
    toast.style.zIndex = '9999';
    toast.innerHTML = `
        <div class="alert alert-${tipo === 'success' ? 'success' : 'danger'} mb-0" style="border-radius: 12px;">
            <i class="fas ${tipo === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} me-2"></i>
            ${mensaje}
        </div>
    `;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

// Verificar si hay resenas pendientes al cargar
document.addEventListener('DOMContentLoaded', async () => {
    try {
        const response = await fetch('api/resenas.php?action=verificar_pendiente');
        const data = await response.json();

        if (data.success && data.pendiente) {
            // Esperar un poco antes de mostrar el modal
            setTimeout(() => {
                abrirModalResena(data.pendiente);
            }, 2000);
        }
    } catch (error) {
        console.error('Error verificando resenas pendientes:', error);
    }
});
</script>
