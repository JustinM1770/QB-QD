<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Depositar a Wallet - QuickBite</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
<?php include_once 'includes/valentine.php'; ?>
    <div class="container mx-auto px-4 py-8 max-w-2xl">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h1 class="text-2xl font-bold text-gray-800 mb-2">💰 Depositar a mi Wallet</h1>
            <p class="text-gray-600">Agrega saldo a tu billetera QuickBite usando OXXO</p>
        </div>

        <!-- Balance Actual -->
        <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-lg shadow-md p-6 mb-6 text-white">
            <p class="text-sm opacity-90 mb-1">Balance disponible</p>
            <h2 class="text-4xl font-bold" id="currentBalance">$0.00</h2>
            <p class="text-sm mt-2 opacity-75" id="walletType">Wallet de usuario</p>
        <!-- Formulario de Depósito -->
            <h3 class="text-xl font-semibold mb-4">Generar Ticket OXXO</h3>
            
            <div class="mb-4">
                <label class="block text-gray-700 font-medium mb-2">Monto a depositar</label>
                <div class="relative">
                    <span class="absolute left-3 top-3 text-gray-500 text-xl">$</span>
                    <input 
                        type="number" 
                        id="depositAmount" 
                        class="w-full pl-8 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent text-xl"
                        placeholder="100.00"
                        min="10"
                        max="10000"
                        step="0.01"
                    >
                </div>
                <p class="text-sm text-gray-500 mt-1">Mínimo: $10.00 MXN | Máximo: $10,000.00 MXN</p>
            </div>
                <label class="block text-gray-700 font-medium mb-2">Email (para recibir confirmación)</label>
                <input 
                    type="email" 
                    id="depositEmail" 
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                    placeholder="tu@email.com"
                >
            <button 
                id="btnGenerateTicket" 
                class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 rounded-lg transition-colors"
            >
                🎫 Generar Ticket OXXO
            </button>
        <!-- Resultado -->
        <div id="resultSection" class="hidden">
            <!-- Se llena dinámicamente -->
        <!-- Historial de Depósitos -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-xl font-semibold mb-4">📋 Historial de Depósitos</h3>
            <div id="depositHistory" class="space-y-3">
                <p class="text-gray-500 text-center py-4">Cargando...</p>
    </div>
    <script>
        // Configuración
        const API_BASE = '/api';
        
        // Cargar balance al iniciar
        async function loadBalance() {
            try {
                const response = await fetch(`${API_BASE}/wallet_api.php?action=balance`);
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('currentBalance').textContent = `$${data.balance}`;
                    document.getElementById('walletType').textContent = `Wallet de ${data.type}`;
                }
            } catch (error) {
                console.error('Error al cargar balance:', error);
            }
        }
        // Cargar historial de depósitos
        async function loadDepositHistory() {
                const response = await fetch(`${API_BASE}/wallet_deposit_api.php?action=history&limit=10`);
                const container = document.getElementById('depositHistory');
                if (data.success && data.deposits.length > 0) {
                    container.innerHTML = data.deposits.map(deposit => {
                        const statusBadge = getStatusBadge(deposit.status);
                        return `
                            <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                                <div class="flex justify-between items-center">
}
                                    <div>
                                        <p class="font-semibold text-lg">$${deposit.amount} MXN</p>
                                        <p class="text-sm text-gray-600">${deposit.method.toUpperCase()}</p>
                                        <p class="text-xs text-gray-400">${formatDate(deposit.created_at)}</p>
                                    </div>
                                    <div class="text-right">
                                        ${statusBadge}
                                        ${deposit.status === 'pending' ? 
                                            `<button onclick="viewTicket('${deposit.id}')" class="text-xs text-blue-600 hover:underline mt-1">Ver ticket</button>` 
                                            : ''
                                        }
                                </div>
                            </div>
                        `;
                    }).join('');
                } else {
                    container.innerHTML = '<p class="text-gray-500 text-center py-4">No hay depósitos recientes</p>';
                console.error('Error al cargar historial:', error);
        // Generar ticket OXXO
        document.getElementById('btnGenerateTicket').addEventListener('click', async () => {
            const amount = parseFloat(document.getElementById('depositAmount').value);
            const email = document.getElementById('depositEmail').value;
            // Validaciones
            if (!amount || amount < 10) {
                alert('El monto mínimo es $10.00 MXN');
                return;
            if (amount > 10000) {
                alert('El monto máximo es $10,000.00 MXN');
            if (!email || !email.includes('@')) {
                alert('Por favor ingresa un email válido');
            const btn = document.getElementById('btnGenerateTicket');
            btn.disabled = true;
}
            btn.textContent = '⏳ Generando ticket...';
                const response = await fetch(`${API_BASE}/wallet_deposit_api.php?action=create_oxxo`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ amount, email })
                });
                    showTicketResult(data);
                    loadDepositHistory(); // Recargar historial
                    alert('Error: ' + data.message);
                alert('Error al generar ticket: ' + error.message);
            } finally {
                btn.disabled = false;
                btn.textContent = '🎫 Generar Ticket OXXO';
        });
        // Mostrar resultado del ticket
        function showTicketResult(data) {
            const resultSection = document.getElementById('resultSection');
            resultSection.className = 'bg-blue-50 border-2 border-blue-200 rounded-lg p-6 mb-6';
            resultSection.innerHTML = `
                <h3 class="text-xl font-bold text-blue-800 mb-4">✅ Ticket OXXO Generado</h3>
                <div class="bg-white rounded-lg p-4 mb-4">
                    <p class="text-sm text-gray-600 mb-1">Monto a pagar</p>
                    <p class="text-3xl font-bold text-gray-800">$${data.amount} MXN</p>
                    <p class="text-sm text-gray-600 mb-2">Código de barras</p>
                    <p class="font-mono text-lg font-semibold">${data.barcode || 'Ver en ticket'}</p>
                <div class="mb-4">
                    <p class="text-sm text-gray-600 mb-2">Válido hasta</p>
                    <p class="font-semibold">${formatDate(data.expires_at)}</p>
                <a href="${data.ticket_url}" target="_blank" class="block w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg text-center mb-3">
                    📄 Descargar/Imprimir Ticket
                </a>
                <div class="bg-gray-50 rounded-lg p-4">
                    <p class="font-semibold mb-2">📝 Instrucciones:</p>
                    <ol class="list-decimal list-inside space-y-1 text-sm text-gray-700">
                        ${data.instructions.map(i => `<li>${i}</li>`).join('')}
                    </ol>
            `;
        // Ver ticket existente
        async function viewTicket(depositId) {
                const response = await fetch(`${API_BASE}/wallet_deposit_api.php?action=status&deposit_id=${depositId}`);
                if (data.success && data.deposit.ticket_url) {
                    window.open(data.deposit.ticket_url, '_blank');
                    alert('No se pudo obtener el ticket');
                alert('Error: ' + error.message);
        // Utilidades
        function getStatusBadge(status) {
            const badges = {
                'pending': '<span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs font-semibold">⏳ Pendiente</span>',
                'completed': '<span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-semibold">✅ Completado</span>',
                'cancelled': '<span class="px-2 py-1 bg-red-100 text-red-800 rounded-full text-xs font-semibold">❌ Cancelado</span>',
}
                'expired': '<span class="px-2 py-1 bg-gray-100 text-gray-800 rounded-full text-xs font-semibold">⏱️ Expirado</span>'
            };
            return badges[status] || badges.pending;
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleString('es-MX', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        // Inicializar
        loadBalance();
        loadDepositHistory();
        // Recargar historial cada 30 segundos para ver si se completó un pago
        setInterval(loadDepositHistory, 30000);
    </script>
</body>
</html>
