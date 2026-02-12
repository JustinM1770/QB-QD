package com.quickbite.app.ui.screens.home

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Search
import androidx.compose.material.icons.filled.ShoppingCart
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import com.quickbite.app.ui.components.LoadingIndicator
import com.quickbite.app.ui.components.ErrorMessage
import com.quickbite.app.ui.components.RestaurantCard
import com.quickbite.app.ui.theme.Orange

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun HomeScreen(
    onNavigateToRestaurant: (Int) -> Unit,
    onNavigateToSearch: (String) -> Unit,
    onNavigateToCart: () -> Unit,
    viewModel: HomeViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()
    var searchQuery by remember { mutableStateOf("") }

    Scaffold(
        topBar = {
            TopAppBar(
                title = {
                    Text(
                        "QuickBite",
                        fontWeight = FontWeight.Bold,
                        color = Orange,
                        fontSize = 22.sp
                    )
                },
                actions = {
                    IconButton(onClick = onNavigateToCart) {
                        Icon(Icons.Default.ShoppingCart, contentDescription = "Carrito")
                    }
                }
            )
        }
    ) { padding ->
        LazyColumn(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding),
            contentPadding = PaddingValues(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            // Barra de búsqueda
            item {
                OutlinedTextField(
                    value = searchQuery,
                    onValueChange = { searchQuery = it },
                    placeholder = { Text("¿Qué se te antoja hoy?") },
                    leadingIcon = { Icon(Icons.Default.Search, contentDescription = null) },
                    shape = RoundedCornerShape(24.dp),
                    singleLine = true,
                    modifier = Modifier
                        .fillMaxWidth()
                        .clickable { onNavigateToSearch("") }
                )
            }

            // Categorías rápidas
            item {
                Text(
                    text = "Categorías",
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Bold
                )
                Spacer(modifier = Modifier.height(8.dp))
                LazyRow(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    val categorias = listOf("Todos", "Comida", "Bebidas", "Postres", "Saludable", "Rápida")
                    items(categorias) { cat ->
                        FilterChip(
                            selected = false,
                            onClick = { onNavigateToSearch(cat) },
                            label = { Text(cat) }
                        )
                    }
                }
            }

            // Título sección
            item {
                Spacer(modifier = Modifier.height(8.dp))
                Text(
                    text = "Restaurantes cerca de ti",
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Bold
                )
            }

            // Estado
            when {
                uiState.isLoading -> item { LoadingIndicator() }
                uiState.error != null -> item {
                    ErrorMessage(
                        message = uiState.error!!,
                        onRetry = { viewModel.loadNegocios() }
                    )
                }
                uiState.negocios.isEmpty() -> item {
                    Text(
                        "No hay restaurantes disponibles",
                        style = MaterialTheme.typography.bodyLarge,
                        modifier = Modifier.padding(32.dp)
                    )
                }
                else -> {
                    items(uiState.negocios) { negocio ->
                        RestaurantCard(
                            negocio = negocio,
                            onClick = { onNavigateToRestaurant(negocio.id) }
                        )
                    }
                }
            }
        }
    }
}
