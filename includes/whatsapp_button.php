<?php
/**
 * Botón flotante de WhatsApp para soporte
 * Incluir en todas las páginas antes del cierre de </body>
 */

// Número de WhatsApp de soporte (formato internacional sin +)
$whatsapp_soporte = '524491425857'; // Número de soporte QuickBite
$mensaje_default = 'Hola, necesito ayuda en QuickBite';
?>
<!-- WhatsApp Floating Button -->
<style>
.whatsapp-float {
    position: fixed;
    bottom: 80px;
    right: 20px;
    z-index: 9999;
    transition: all 0.3s ease;
}

.whatsapp-float-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 56px;
    height: 56px;
    background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
    border-radius: 50%;
    box-shadow: 0 4px 15px rgba(37, 211, 102, 0.4);
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    animation: pulse-whatsapp 2s infinite;
}

.whatsapp-float-btn:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 20px rgba(37, 211, 102, 0.5);
}

.whatsapp-float-btn i {
    color: white;
    font-size: 28px;
}

.whatsapp-tooltip {
    position: absolute;
    right: 70px;
    top: 50%;
    transform: translateY(-50%);
    background: white;
    color: #333;
    padding: 10px 16px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    white-space: nowrap;
    box-shadow: 0 2px 15px rgba(0,0,0,0.15);
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.whatsapp-tooltip::after {
    content: '';
    position: absolute;
    right: -6px;
    top: 50%;
    transform: translateY(-50%);
    border-width: 6px;
    border-style: solid;
    border-color: transparent transparent transparent white;
}

.whatsapp-float:hover .whatsapp-tooltip {
    opacity: 1;
    visibility: visible;
}

@keyframes pulse-whatsapp {
    0% {
        box-shadow: 0 4px 15px rgba(37, 211, 102, 0.4);
    }
    50% {
        box-shadow: 0 4px 25px rgba(37, 211, 102, 0.6);
    }
    100% {
        box-shadow: 0 4px 15px rgba(37, 211, 102, 0.4);
    }
}

/* Ajuste para móviles con navbar bottom */
@media (max-width: 768px) {
    .whatsapp-float {
        bottom: 90px;
        right: 15px;
    }
    .whatsapp-float-btn {
        width: 50px;
        height: 50px;
    }
    .whatsapp-float-btn i {
        font-size: 24px;
    }
    .whatsapp-tooltip {
        display: none;
    }
}
</style>

<div class="whatsapp-float">
    <div class="whatsapp-tooltip">
        <i class="fas fa-headset me-1"></i> Soporte por WhatsApp
    </div>
    <a href="https://wa.me/<?php echo $whatsapp_soporte; ?>?text=<?php echo urlencode($mensaje_default); ?>"
       target="_blank"
       rel="noopener noreferrer"
       class="whatsapp-float-btn"
       aria-label="Contactar por WhatsApp">
        <i class="fab fa-whatsapp"></i>
    </a>
</div>
