/**
 * QuickBite - Módulo de Pago SPEI (Transferencia Bancaria)
 * Integración con MercadoPago bank_transfer
 *
 * @version 1.0.0
 * @date 2026-01-24
 */

const QuickBiteSPEI = (function() {
    'use strict';

    // Configuración
    const config = {
        apiEndpoint: '/api/mercadopago/spei_payment.php',
        pollInterval: 10000, // 10 segundos
        maxPollAttempts: 180 // 30 minutos máximo
    };

    // Estado interno
    let currentPayment = null;
    let pollTimer = null;
    let pollAttempts = 0;

    /**
     * Crea un nuevo pago SPEI
     * @param {Object} params - Parámetros del pago
     * @param {number} params.pedidoId - ID del pedido
     * @param {number} params.amount - Monto a pagar (opcional, usa el del pedido)
     * @param {string} params.email - Email del pagador (opcional)
     * @returns {Promise<Object>} Resultado del pago
     */
    async function createPayment(params) {
        try {
            if (!params.pedidoId) {
                throw new Error('Se requiere el ID del pedido');
            }

            const response = await fetch(config.apiEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    pedido_id: params.pedidoId,
                    amount: params.amount || null,
                    email: params.email || null,
                    description: params.description || null
                })
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.error || data.message || 'Error al crear pago SPEI');
            }

            currentPayment = data;
            return data;

        } catch (error) {
            console.error('Error creando pago SPEI:', error);
            throw error;
        }
    }

    /**
     * Consulta el estado de un pago SPEI
     * @param {number} pedidoId - ID del pedido
     * @returns {Promise<Object>} Estado del pago
     */
    async function getPaymentStatus(pedidoId) {
        try {
            const response = await fetch(`${config.apiEndpoint}?pedido_id=${pedidoId}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                }
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error || 'Error al consultar estado');
            }

            return data;

        } catch (error) {
            console.error('Error consultando estado SPEI:', error);
            throw error;
        }
    }

    /**
     * Inicia el polling para verificar el estado del pago
     * @param {number} pedidoId - ID del pedido
     * @param {Function} onStatusChange - Callback cuando cambia el estado
     * @param {Function} onApproved - Callback cuando el pago es aprobado
     */
    function startPolling(pedidoId, onStatusChange, onApproved) {
        stopPolling();
        pollAttempts = 0;

        async function poll() {
            try {
                pollAttempts++;

                if (pollAttempts > config.maxPollAttempts) {
                    console.warn('SPEI: Máximo de intentos de polling alcanzado');
                    stopPolling();
                    return;
                }

                const status = await getPaymentStatus(pedidoId);

                if (onStatusChange) {
                    onStatusChange(status);
                }

                if (status.is_approved) {
                    stopPolling();
                    if (onApproved) {
                        onApproved(status);
                    }
                    return;
                }

                if (status.is_rejected) {
                    stopPolling();
                    return;
                }

                // Continuar polling si está pendiente
                pollTimer = setTimeout(poll, config.pollInterval);

            } catch (error) {
                console.error('Error en polling SPEI:', error);
                pollTimer = setTimeout(poll, config.pollInterval);
            }
        }

        poll();
    }

    /**
     * Detiene el polling
     */
    function stopPolling() {
        if (pollTimer) {
            clearTimeout(pollTimer);
            pollTimer = null;
        }
    }

    /**
     * Renderiza la UI de pago SPEI
     * @param {HTMLElement} container - Contenedor donde renderizar
     * @param {Object} paymentData - Datos del pago SPEI
     */
    function renderPaymentUI(container, paymentData) {
        if (!container || !paymentData) return;

        const bankInfo = paymentData.bank_info || {};

        const html = `
            <div class="spei-payment-container">
                <div class="spei-header">
                    <div class="spei-icon">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="5" width="18" height="14" rx="2"/>
                            <path d="M3 10h18"/>
                            <path d="M7 15h4"/>
                        </svg>
                    </div>
                    <h3>Transferencia SPEI</h3>
                    <p class="spei-subtitle">Realiza la transferencia desde tu banca en línea</p>
                </div>

                <div class="spei-amount">
                    <span class="label">Monto a transferir:</span>
                    <span class="amount">$${formatNumber(paymentData.amount)} MXN</span>
                </div>

                <div class="spei-bank-data">
                    <div class="spei-field clabe-field">
                        <label>CLABE Interbancaria:</label>
                        <div class="value-copy">
                            <span class="value" id="spei-clabe">${paymentData.clabe || 'Cargando...'}</span>
                            <button type="button" class="btn-copy" onclick="QuickBiteSPEI.copyToClipboard('spei-clabe')">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="9" y="9" width="13" height="13" rx="2"/>
                                    <path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    ${bankInfo.bank_name ? `
                    <div class="spei-field">
                        <label>Banco destino:</label>
                        <span class="value">${bankInfo.bank_name}</span>
                    </div>
                    ` : ''}

                    ${bankInfo.beneficiary ? `
                    <div class="spei-field">
                        <label>Beneficiario:</label>
                        <span class="value">${bankInfo.beneficiary}</span>
                    </div>
                    ` : ''}

                    <div class="spei-field">
                        <label>Referencia:</label>
                        <div class="value-copy">
                            <span class="value" id="spei-reference">${paymentData.external_reference}</span>
                            <button type="button" class="btn-copy" onclick="QuickBiteSPEI.copyToClipboard('spei-reference')">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="9" y="9" width="13" height="13" rx="2"/>
                                    <path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="spei-field">
                        <label>Vigencia:</label>
                        <span class="value expires">${formatDate(paymentData.expires_at)}</span>
                    </div>
                </div>

                <div class="spei-instructions">
                    <h4>Instrucciones:</h4>
                    <ol>
                        ${paymentData.instructions.map(inst => `<li>${inst}</li>`).join('')}
                    </ol>
                </div>

                ${paymentData.ticket_url ? `
                <div class="spei-actions">
                    <a href="${paymentData.ticket_url}" target="_blank" class="btn btn-primary">
                        Ver comprobante de pago
                    </a>
                </div>
                ` : ''}

                <div class="spei-status" id="spei-status">
                    <div class="status-indicator pending">
                        <span class="pulse"></span>
                        <span class="text">Esperando transferencia...</span>
                    </div>
                </div>

                <div class="spei-note">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/>
                    </svg>
                    <span>Tu pago se acreditará automáticamente. No es necesario enviar comprobante.</span>
                </div>
            </div>
        `;

        container.innerHTML = html;
    }

    /**
     * Actualiza el indicador de estado
     * @param {string} status - Estado del pago
     */
    function updateStatusIndicator(status) {
        const statusEl = document.getElementById('spei-status');
        if (!statusEl) return;

        let statusClass = 'pending';
        let statusText = 'Esperando transferencia...';

        switch (status) {
            case 'approved':
                statusClass = 'approved';
                statusText = '¡Pago confirmado!';
                break;
            case 'in_process':
                statusClass = 'processing';
                statusText = 'Procesando transferencia...';
                break;
            case 'rejected':
            case 'cancelled':
                statusClass = 'rejected';
                statusText = 'Pago rechazado o cancelado';
                break;
        }

        statusEl.innerHTML = `
            <div class="status-indicator ${statusClass}">
                <span class="${statusClass === 'pending' ? 'pulse' : 'icon'}"></span>
                <span class="text">${statusText}</span>
            </div>
        `;
    }

    /**
     * Copia texto al portapapeles
     * @param {string} elementId - ID del elemento con el texto
     */
    function copyToClipboard(elementId) {
        const element = document.getElementById(elementId);
        if (!element) return;

        const text = element.textContent;

        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(() => {
                showCopyFeedback(element, true);
            }).catch(() => {
                fallbackCopy(text, element);
            });
        } else {
            fallbackCopy(text, element);
        }
    }

    function fallbackCopy(text, element) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();

        try {
            document.execCommand('copy');
            showCopyFeedback(element, true);
        } catch (err) {
            showCopyFeedback(element, false);
        }

        document.body.removeChild(textarea);
    }

    function showCopyFeedback(element, success) {
        const parent = element.closest('.value-copy');
        if (!parent) return;

        parent.classList.add(success ? 'copied' : 'copy-failed');

        setTimeout(() => {
            parent.classList.remove('copied', 'copy-failed');
        }, 2000);
    }

    /**
     * Formatea un número con separadores de miles
     */
    function formatNumber(num) {
        return parseFloat(num).toLocaleString('es-MX', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    /**
     * Formatea una fecha
     */
    function formatDate(dateStr) {
        if (!dateStr) return 'No especificada';

        const date = new Date(dateStr);
        const options = {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        };

        return date.toLocaleDateString('es-MX', options);
    }

    /**
     * Inyecta los estilos CSS necesarios
     */
    function injectStyles() {
        if (document.getElementById('spei-styles')) return;

        const styles = `
            .spei-payment-container {
                max-width: 480px;
                margin: 0 auto;
                padding: 24px;
                background: #fff;
                border-radius: 12px;
                box-shadow: 0 2px 12px rgba(0,0,0,0.1);
            }

            .spei-header {
                text-align: center;
                margin-bottom: 24px;
            }

            .spei-icon {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 64px;
                height: 64px;
                background: linear-gradient(135deg, #6366f1, #8b5cf6);
                border-radius: 50%;
                margin-bottom: 12px;
                color: #fff;
            }

            .spei-header h3 {
                margin: 0 0 4px;
                font-size: 1.5rem;
                color: #1f2937;
            }

            .spei-subtitle {
                margin: 0;
                color: #6b7280;
                font-size: 0.9rem;
            }

            .spei-amount {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 16px;
                background: #f3f4f6;
                border-radius: 8px;
                margin-bottom: 20px;
            }

            .spei-amount .label {
                color: #6b7280;
            }

            .spei-amount .amount {
                font-size: 1.5rem;
                font-weight: 700;
                color: #1f2937;
            }

            .spei-bank-data {
                background: #fafafa;
                border: 1px solid #e5e7eb;
                border-radius: 8px;
                padding: 16px;
                margin-bottom: 20px;
            }

            .spei-field {
                display: flex;
                flex-direction: column;
                margin-bottom: 12px;
            }

            .spei-field:last-child {
                margin-bottom: 0;
            }

            .spei-field label {
                font-size: 0.75rem;
                color: #6b7280;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                margin-bottom: 4px;
            }

            .spei-field .value {
                font-size: 1rem;
                color: #1f2937;
                font-weight: 500;
            }

            .clabe-field .value {
                font-family: monospace;
                font-size: 1.1rem;
                letter-spacing: 1px;
            }

            .value-copy {
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .btn-copy {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 32px;
                height: 32px;
                border: 1px solid #d1d5db;
                border-radius: 6px;
                background: #fff;
                cursor: pointer;
                transition: all 0.2s;
                color: #6b7280;
            }

            .btn-copy:hover {
                background: #f3f4f6;
                border-color: #9ca3af;
            }

            .value-copy.copied .btn-copy {
                background: #10b981;
                border-color: #10b981;
                color: #fff;
            }

            .spei-instructions {
                margin-bottom: 20px;
            }

            .spei-instructions h4 {
                margin: 0 0 12px;
                font-size: 0.9rem;
                color: #374151;
            }

            .spei-instructions ol {
                margin: 0;
                padding-left: 20px;
                color: #6b7280;
                font-size: 0.9rem;
                line-height: 1.6;
            }

            .spei-actions {
                text-align: center;
                margin-bottom: 20px;
            }

            .spei-actions .btn {
                display: inline-block;
                padding: 12px 24px;
                background: linear-gradient(135deg, #6366f1, #8b5cf6);
                color: #fff;
                text-decoration: none;
                border-radius: 8px;
                font-weight: 500;
                transition: transform 0.2s, box-shadow 0.2s;
            }

            .spei-actions .btn:hover {
                transform: translateY(-1px);
                box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
            }

            .spei-status {
                padding: 16px;
                background: #f9fafb;
                border-radius: 8px;
                margin-bottom: 16px;
            }

            .status-indicator {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 12px;
            }

            .status-indicator.pending .pulse {
                width: 12px;
                height: 12px;
                background: #f59e0b;
                border-radius: 50%;
                animation: pulse 1.5s infinite;
            }

            .status-indicator.approved {
                color: #10b981;
            }

            .status-indicator.approved .icon::before {
                content: '\\2713';
                font-size: 1.2rem;
            }

            .status-indicator.processing {
                color: #3b82f6;
            }

            .status-indicator.rejected {
                color: #ef4444;
            }

            @keyframes pulse {
                0%, 100% { opacity: 1; transform: scale(1); }
                50% { opacity: 0.5; transform: scale(1.1); }
            }

            .spei-note {
                display: flex;
                align-items: flex-start;
                gap: 8px;
                padding: 12px;
                background: #eff6ff;
                border-radius: 8px;
                font-size: 0.85rem;
                color: #1e40af;
            }

            .spei-note svg {
                flex-shrink: 0;
                margin-top: 2px;
            }

            @media (max-width: 480px) {
                .spei-payment-container {
                    padding: 16px;
                    border-radius: 0;
                }

                .spei-amount .amount {
                    font-size: 1.25rem;
                }
            }
        `;

        const styleEl = document.createElement('style');
        styleEl.id = 'spei-styles';
        styleEl.textContent = styles;
        document.head.appendChild(styleEl);
    }

    // API Pública
    return {
        createPayment,
        getPaymentStatus,
        startPolling,
        stopPolling,
        renderPaymentUI,
        updateStatusIndicator,
        copyToClipboard,
        injectStyles,
        getCurrentPayment: () => currentPayment
    };

})();

// Auto-inyectar estilos cuando se carga el módulo
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', QuickBiteSPEI.injectStyles);
} else {
    QuickBiteSPEI.injectStyles();
}
