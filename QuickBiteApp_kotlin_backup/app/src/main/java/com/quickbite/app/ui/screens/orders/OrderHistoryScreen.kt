package com.quickbite.app.ui.screens.orders

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import coil.compose.AsyncImage
import com.quickbite.app.ui.components.LoadingIndicator
import com.quickbite.app.ui.components.ErrorMessage
import com.quickbite.app.ui.theme.*

@Composable
fun OrderHistoryScreen(
    onNavigateToDetail: (Int) -> Unit,
    viewModel: OrdersViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()

    Column(modifier = Modifier.fillMaxSize().padding(16.dp)) {
        Text(
            "Mis Pedidos",
            style = MaterialTheme.typography.headlineMedium,
            fontWeight = FontWeight.Bold
        )
        Spacer(Modifier.height(16.dp))

        when {
            uiState.isLoading -> LoadingIndicator()
            uiState.error != null -> ErrorMessage(uiState.error!!, onRetry = { viewModel.loadHistorial() })
            uiState.pedidos.isEmpty() -> {
                Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    Text("No tienes pedidos aún", color = Gray600)
                }
            }
            else -> {
                LazyColumn(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                    items(uiState.pedidos) { pedido ->
                        Card(
                            modifier = Modifier
                                .fillMaxWidth()
                                .clickable { onNavigateToDetail(pedido.id) },
                            shape = RoundedCornerShape(12.dp)
                        ) {
                            Row(modifier = Modifier.padding(12.dp)) {
                                AsyncImage(
                                    model = pedido.negocioImagen,
                                    contentDescription = null,
                                    modifier = Modifier.size(56.dp).clip(RoundedCornerShape(8.dp))
                                )
                                Spacer(Modifier.width(12.dp))
                                Column(modifier = Modifier.weight(1f)) {
                                    Text(pedido.negocio, fontWeight = FontWeight.Medium)
                                    Text(pedido.fechaFormateada, style = MaterialTheme.typography.bodySmall, color = Gray600)
                                    Text(pedido.totalFormateado, fontWeight = FontWeight.Bold, color = Orange)
                                }
                                AssistChip(
                                    onClick = {},
                                    label = { Text(pedido.estadoTexto, style = MaterialTheme.typography.labelSmall) }
                                )
                            }
                        }
                    }
                }
            }
        }
    }
}
