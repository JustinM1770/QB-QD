package com.quickbite.app.ui.screens.profile

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import com.quickbite.app.ui.theme.*

@Composable
fun ProfileScreen(
    onLogout: () -> Unit,
    viewModel: ProfileViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()
    val user = uiState.user

    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(24.dp),
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        Spacer(Modifier.height(24.dp))

        // Avatar
        Surface(
            modifier = Modifier.size(80.dp),
            shape = CircleShape,
            color = OrangeLight.copy(alpha = 0.3f)
        ) {
            Box(contentAlignment = Alignment.Center) {
                Icon(
                    Icons.Default.Person,
                    contentDescription = null,
                    tint = Orange,
                    modifier = Modifier.size(40.dp)
                )
            }
        }

        Spacer(Modifier.height(16.dp))
        Text(
            user?.nombre ?: "Usuario",
            style = MaterialTheme.typography.headlineMedium,
            fontWeight = FontWeight.Bold
        )
        Text(user?.email ?: "", color = Gray600)

        Spacer(Modifier.height(32.dp))

        // Opciones
        ProfileOption(Icons.Default.LocationOn, "Mis Direcciones") {}
        ProfileOption(Icons.Default.Favorite, "Favoritos") {}
        ProfileOption(Icons.Default.Receipt, "Historial de Pedidos") {}
        ProfileOption(Icons.Default.CreditCard, "Métodos de Pago") {}
        ProfileOption(Icons.Default.Help, "Ayuda") {}

        Spacer(Modifier.weight(1f))

        // Logout
        OutlinedButton(
            onClick = {
                viewModel.logout()
                onLogout()
            },
            modifier = Modifier.fillMaxWidth(),
            colors = ButtonDefaults.outlinedButtonColors(contentColor = Red)
        ) {
            Icon(Icons.Default.Logout, contentDescription = null)
            Spacer(Modifier.width(8.dp))
            Text("Cerrar Sesión")
        }

        Spacer(Modifier.height(16.dp))
    }
}

@Composable
private fun ProfileOption(icon: ImageVector, label: String, onClick: () -> Unit) {
    Surface(onClick = onClick) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(vertical = 14.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Icon(icon, contentDescription = null, tint = Orange, modifier = Modifier.size(24.dp))
            Spacer(Modifier.width(16.dp))
            Text(label, style = MaterialTheme.typography.bodyLarge, modifier = Modifier.weight(1f))
            Icon(Icons.Default.ChevronRight, contentDescription = null, tint = Gray400)
        }
    }
    HorizontalDivider(color = Gray200)
}
