package com.quickbite.app.ui.screens.checkout

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import com.quickbite.app.ui.components.LoadingIndicator
import com.quickbite.app.ui.theme.*

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun CheckoutScreen(
    onNavigateBack: () -> Unit,
    onPedidoCreado: (Int) -> Unit,
    viewModel: CheckoutViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()

    LaunchedEffect(uiState.pedidoCreado) {
        uiState.pedidoCreado?.let { onPedidoCreado(it) }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Checkout") },
                navigationIcon = {
                    IconButton(onClick = onNavigateBack) {
                        Icon(Icons.Default.ArrowBack, contentDescription = "Volver")
                    }
                }
            )
        }
    ) { padding ->
        if (uiState.isLoading && uiState.direcciones.isEmpty()) {
            LoadingIndicator(modifier = Modifier.padding(padding))
        } else {
            Column(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(padding)
                    .padding(16.dp)
                    .verticalScroll(rememberScrollState())
            ) {
                // Dirección de entrega
                Text("Dirección de entrega", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
                Spacer(Modifier.height(8.dp))

                if (uiState.direcciones.isEmpty()) {
                    Text("No tienes direcciones guardadas", color = Gray600)
                } else {
                    uiState.direcciones.forEach { dir ->
                        Card(
                            onClick = { viewModel.selectDireccion(dir) },
                            modifier = Modifier.fillMaxWidth().padding(vertical = 4.dp),
                            colors = CardDefaults.cardColors(
                                containerColor = if (dir == uiState.selectedDireccion)
                                    OrangeLight.copy(alpha = 0.2f) else MaterialTheme.colorScheme.surface
                            )
                        ) {
                            Row(modifier = Modifier.padding(12.dp)) {
                                RadioButton(
                                    selected = dir == uiState.selectedDireccion,
                                    onClick = { viewModel.selectDireccion(dir) }
                                )
                                Column(modifier = Modifier.padding(start = 8.dp)) {
                                    Text(dir.nombre, fontWeight = FontWeight.Medium)
                                    Text("${dir.calle} ${dir.numero}, ${dir.colonia}", style = MaterialTheme.typography.bodySmall, color = Gray600)
                                }
                            }
                        }
                    }
                }

                Spacer(Modifier.height(20.dp))

                // Método de pago
                Text("Método de pago", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
                Spacer(Modifier.height(8.dp))

                val metodos = listOf(
                    "efectivo" to "Efectivo",
                    "tarjeta" to "Tarjeta de crédito/débito",
                    "mercadopago" to "MercadoPago"
                )
                metodos.forEach { (value, label) ->
                    Row(modifier = Modifier.fillMaxWidth().padding(vertical = 4.dp)) {
                        RadioButton(
                            selected = uiState.metodoPago == value,
                            onClick = { viewModel.setMetodoPago(value) }
                        )
                        Text(label, modifier = Modifier.padding(start = 8.dp, top = 12.dp))
                    }
                }

                Spacer(Modifier.height(20.dp))

                // Notas
                OutlinedTextField(
                    value = uiState.notas,
                    onValueChange = { viewModel.setNotas(it) },
                    label = { Text("Notas para el pedido (opcional)") },
                    modifier = Modifier.fillMaxWidth(),
                    maxLines = 3
                )

                Spacer(Modifier.height(20.dp))

                // Resumen
                Text("Resumen", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
                Spacer(Modifier.height(8.dp))
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                    Text("Subtotal"); Text("$${String.format("%.2f", uiState.carrito.subtotal)}")
                }
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                    Text("Envío"); Text("$${String.format("%.2f", uiState.carrito.costoEnvio)}")
                }
                HorizontalDivider(modifier = Modifier.padding(vertical = 8.dp))
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                    Text("Total", fontWeight = FontWeight.Bold, fontSize = 18.sp)
                    Text("$${String.format("%.2f", uiState.carrito.total)}", fontWeight = FontWeight.Bold, fontSize = 18.sp, color = Orange)
                }

                if (uiState.error != null) {
                    Spacer(Modifier.height(8.dp))
                    Text(uiState.error!!, color = Red, style = MaterialTheme.typography.bodySmall)
                }

                Spacer(Modifier.height(24.dp))

                Button(
                    onClick = { viewModel.crearPedido() },
                    enabled = !uiState.isLoading && uiState.selectedDireccion != null,
                    modifier = Modifier.fillMaxWidth().height(50.dp),
                    colors = ButtonDefaults.buttonColors(containerColor = Orange)
                ) {
                    if (uiState.isLoading) {
                        CircularProgressIndicator(modifier = Modifier.size(24.dp), color = MaterialTheme.colorScheme.onPrimary)
                    } else {
                        Text("Confirmar Pedido", fontSize = 16.sp)
                    }
                }

                Spacer(Modifier.height(24.dp))
            }
        }
    }
}
