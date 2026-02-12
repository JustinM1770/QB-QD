package com.quickbite.app.ui.screens.orders

import androidx.compose.foundation.layout.*
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import com.quickbite.app.ui.components.LoadingIndicator
import com.quickbite.app.ui.theme.*

private val statusLabels = mapOf(
    1 to "Pendiente",
    2 to "Confirmado",
    3 to "Preparando",
    4 to "Listo",
    5 to "En camino",
    6 to "Entregado",
    7 to "Cancelado"
)

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun OrderDetailScreen(
    pedidoId: Int,
    onNavigateBack: () -> Unit,
    viewModel: OrdersViewModel = hiltViewModel()
) {
    val detailState by viewModel.detailState.collectAsState()

    LaunchedEffect(pedidoId) {
        viewModel.startTracking(pedidoId)
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Pedido #$pedidoId") },
                navigationIcon = {
                    IconButton(onClick = onNavigateBack) {
                        Icon(Icons.Default.ArrowBack, contentDescription = "Volver")
                    }
                }
            )
        }
    ) { padding ->
        when {
            detailState.isLoading && detailState.status == null -> {
                LoadingIndicator(modifier = Modifier.padding(padding))
            }
            detailState.error != null -> {
                Box(Modifier.fillMaxSize().padding(padding), contentAlignment = Alignment.Center) {
                    Text(detailState.error!!, color = Red)
                }
            }
            else -> {
                val status = detailState.status
                Column(
                    modifier = Modifier
                        .fillMaxSize()
                        .padding(padding)
                        .padding(24.dp),
                    horizontalAlignment = Alignment.CenterHorizontally
                ) {
                    // Icono grande según estado
                    val icon = when (status?.status) {
                        1 -> Icons.Default.HourglassEmpty
                        2 -> Icons.Default.CheckCircle
                        3 -> Icons.Default.Restaurant
                        4 -> Icons.Default.DoneAll
                        5 -> Icons.Default.DeliveryDining
                        6 -> Icons.Default.Verified
                        7 -> Icons.Default.Cancel
                        else -> Icons.Default.Info
                    }
                    Icon(
                        icon,
                        contentDescription = null,
                        tint = if (status?.status == 7) Red else Orange,
                        modifier = Modifier.size(80.dp)
                    )

                    Spacer(Modifier.height(16.dp))
                    Text(
                        statusLabels[status?.status] ?: "Desconocido",
                        style = MaterialTheme.typography.headlineMedium,
                        fontWeight = FontWeight.Bold
                    )

                    Spacer(Modifier.height(8.dp))
                    Text(
                        "Última actualización: ${status?.lastUpdated ?: ""}",
                        style = MaterialTheme.typography.bodySmall,
                        color = Gray600
                    )

                    Spacer(Modifier.height(32.dp))

                    // Progress steps
                    val currentStep = status?.status ?: 0
                    val steps = listOf(1 to "Pendiente", 2 to "Confirmado", 3 to "Preparando", 4 to "Listo", 5 to "En camino", 6 to "Entregado")

                    steps.forEach { (step, label) ->
                        Row(
                            modifier = Modifier.fillMaxWidth().padding(vertical = 6.dp),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Icon(
                                if (currentStep >= step) Icons.Default.CheckCircle else Icons.Default.RadioButtonUnchecked,
                                contentDescription = null,
                                tint = if (currentStep >= step) Green else Gray400,
                                modifier = Modifier.size(24.dp)
                            )
                            Spacer(Modifier.width(12.dp))
                            Text(
                                label,
                                fontWeight = if (currentStep == step) FontWeight.Bold else FontWeight.Normal,
                                color = if (currentStep >= step) Gray900 else Gray400
                            )
                        }
                    }
                }
            }
        }
    }
}
