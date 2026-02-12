<?php
// perfil_puntos.php - Sección para mostrar puntos de usuario con diseño profesional
?>

<section class="card mb-4">
    <div class="card-header bg-primary text-white d-flex align-items-center">
        <i class="fas fa-star me-2"></i>
        <h5 class="mb-0">Mis Puntos</h5>
    </div>
    <div class="card-body">
        <p class="fs-4">Puntos acumulados: <span id="points-balance" class="fw-bold">Cargando...</span></p>
        <h6>Historial de puntos</h6>
        <ul id="points-history" class="list-group list-group-flush" style="max-height: 200px; overflow-y: auto;">
            <li class="list-group-item">Cargando historial...</li>
        </ul>
    </div>
</section>

<script>
    async function fetchPoints() {
        try {
            const response = await fetch('api/PointsController.php?action=getPointsBalance');
            if (response.ok) {
                const data = await response.json();
                document.getElementById('points-balance').textContent = data.puntos_acumulados;
            } else {
                document.getElementById('points-balance').textContent = 'Error al cargar';
            }
        } catch (error) {
            document.getElementById('points-balance').textContent = 'Error al cargar';
        }
    }

    async function fetchPointsHistory() {
        try {
            const response = await fetch('api/PointsController.php?action=getPointsHistory');
            if (response.ok) {
                const data = await response.json();
                const historyList = document.getElementById('points-history');
                historyList.innerHTML = '';
                if (data.history.length === 0) {
                    historyList.innerHTML = '<li class="list-group-item">No hay historial de puntos.</li>';
                } else {
                    data.history.forEach(item => {
                        const li = document.createElement('li');
                        li.className = 'list-group-item';
                        li.textContent = `Pedido ID: ${item.id_pedido || 'N/A'} - Puntos: ${item.cantidad} - Fecha: ${item.fecha_acreditacion}`;
                        historyList.appendChild(li);
                    });
                }
            } else {
                document.getElementById('points-history').innerHTML = '<li class="list-group-item">Error al cargar historial</li>';
            }
        } catch (error) {
            document.getElementById('points-history').innerHTML = '<li class="list-group-item">Error al cargar historial</li>';
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        fetchPoints();
        fetchPointsHistory();
    });
</script>
