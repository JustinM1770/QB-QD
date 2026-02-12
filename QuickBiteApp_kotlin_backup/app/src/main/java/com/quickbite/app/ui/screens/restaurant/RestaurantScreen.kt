package com.quickbite.app.ui.screens.restaurant

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import coil.compose.AsyncImage
import com.quickbite.app.ui.components.LoadingIndicator
import com.quickbite.app.ui.components.ErrorMessage
import com.quickbite.app.ui.components.ProductCard
import com.quickbite.app.ui.theme.*

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun RestaurantScreen(
    negocioId: Int,
    onNavigateBack: () -> Unit,
    onNavigateToCart: () -> Unit,
    viewModel: RestaurantViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()
    val snackbarHostState = remember { SnackbarHostState() }

    LaunchedEffect(negocioId) {
        viewModel.loadNegocio(negocioId)
    }

    LaunchedEffect(uiState.addedToCart) {
        if (uiState.addedToCart) {
            snackbarHostState.showSnackbar("Agregado al carrito")
            viewModel.clearCartMessage()
        }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text(uiState.negocio?.nombre ?: "Restaurante") },
                navigationIcon = {
                    IconButton(onClick = onNavigateBack) {
                        Icon(Icons.Default.ArrowBack, contentDescription = "Volver")
                    }
                },
                actions = {
                    IconButton(onClick = onNavigateToCart) {
                        Icon(Icons.Default.ShoppingCart, contentDescription = "Carrito")
                    }
                }
            )
        },
        snackbarHost = { SnackbarHost(snackbarHostState) }
    ) { padding ->
        when {
            uiState.isLoading -> LoadingIndicator(modifier = Modifier.padding(padding))
            uiState.error != null -> ErrorMessage(
                message = uiState.error!!,
                onRetry = { viewModel.loadNegocio(negocioId) },
                modifier = Modifier.padding(padding)
            )
            else -> {
                LazyColumn(
                    modifier = Modifier
                        .fillMaxSize()
                        .padding(padding),
                    contentPadding = PaddingValues(16.dp),
                    verticalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    // Portada
                    uiState.negocio?.let { negocio ->
                        item {
                            AsyncImage(
                                model = negocio.portada,
                                contentDescription = negocio.nombre,
                                contentScale = ContentScale.Crop,
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .height(180.dp)
                                    .clip(RoundedCornerShape(12.dp))
                            )
                        }

                        // Info
                        item {
                            Column {
                                Text(
                                    text = negocio.nombre,
                                    style = MaterialTheme.typography.headlineMedium,
                                    fontWeight = FontWeight.Bold
                                )
                                Spacer(modifier = Modifier.height(4.dp))
                                Row(verticalAlignment = Alignment.CenterVertically) {
                                    Icon(Icons.Default.Star, tint = Orange, modifier = Modifier.size(18.dp), contentDescription = null)
                                    Text(" ${negocio.calificacion} (${negocio.totalResenas} reseñas)")
                                    Spacer(Modifier.width(16.dp))
                                    Icon(Icons.Default.DeliveryDining, tint = Gray600, modifier = Modifier.size(18.dp), contentDescription = null)
                                    Text(" ${negocio.tiempoEntrega}", color = Gray600)
                                }
                                if (negocio.descripcion.isNotEmpty()) {
                                    Spacer(modifier = Modifier.height(8.dp))
                                    Text(negocio.descripcion, style = MaterialTheme.typography.bodyMedium, color = Gray600)
                                }
                                HorizontalDivider(modifier = Modifier.padding(vertical = 12.dp))
                            }
                        }
                    }

                    // Menú título
                    item {
                        Text(
                            "Menú",
                            style = MaterialTheme.typography.titleLarge,
                            fontWeight = FontWeight.Bold
                        )
                    }

                    // Productos
                    if (uiState.categorias.isNotEmpty()) {
                        uiState.categorias.forEach { cat ->
                            item {
                                Text(
                                    cat.nombre,
                                    style = MaterialTheme.typography.titleMedium,
                                    fontWeight = FontWeight.SemiBold,
                                    modifier = Modifier.padding(top = 8.dp)
                                )
                            }
                            items(cat.productos) { producto ->
                                ProductCard(
                                    producto = producto,
                                    onAddToCart = { viewModel.addToCart(producto.id) }
                                )
                            }
                        }
                    } else {
                        items(uiState.productos) { producto ->
                            ProductCard(
                                producto = producto,
                                onAddToCart = { viewModel.addToCart(producto.id) }
                            )
                        }
                    }
                }
            }
        }
    }
}
