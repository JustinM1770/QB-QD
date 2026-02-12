package com.quickbite.app.ui.components

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.DeliveryDining
import androidx.compose.material.icons.filled.Star
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import coil.compose.AsyncImage
import com.quickbite.app.data.model.Negocio
import com.quickbite.app.ui.theme.*

@Composable
fun RestaurantCard(
    negocio: Negocio,
    onClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    Card(
        modifier = modifier
            .fillMaxWidth()
            .clickable(onClick = onClick),
        shape = RoundedCornerShape(12.dp),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
    ) {
        Column {
            // Imagen portada
            AsyncImage(
                model = negocio.portada ?: negocio.logo,
                contentDescription = negocio.nombre,
                contentScale = ContentScale.Crop,
                modifier = Modifier
                    .fillMaxWidth()
                    .height(140.dp)
                    .clip(RoundedCornerShape(topStart = 12.dp, topEnd = 12.dp))
            )

            Column(modifier = Modifier.padding(12.dp)) {
                Text(
                    text = negocio.nombre,
                    style = MaterialTheme.typography.titleMedium,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis
                )

                Spacer(modifier = Modifier.height(4.dp))

                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(
                        Icons.Default.Star,
                        contentDescription = null,
                        tint = Orange,
                        modifier = Modifier.size(16.dp)
                    )
                    Text(
                        text = " ${negocio.calificacion}",
                        style = MaterialTheme.typography.bodySmall,
                        fontWeight = FontWeight.Medium
                    )
                    Text(
                        text = " (${negocio.totalResenas})",
                        style = MaterialTheme.typography.bodySmall,
                        color = Gray600
                    )
                    Spacer(modifier = Modifier.width(12.dp))
                    Icon(
                        Icons.Default.DeliveryDining,
                        contentDescription = null,
                        tint = Gray600,
                        modifier = Modifier.size(16.dp)
                    )
                    Text(
                        text = " ${negocio.tiempoEntrega}",
                        style = MaterialTheme.typography.bodySmall,
                        color = Gray600
                    )
                }

                if (negocio.costoEnvio > 0) {
                    Text(
                        text = "Envío \$${String.format("%.2f", negocio.costoEnvio)}",
                        style = MaterialTheme.typography.bodySmall,
                        color = Gray600
                    )
                } else {
                    Text(
                        text = "Envío gratis",
                        style = MaterialTheme.typography.bodySmall,
                        color = Green,
                        fontWeight = FontWeight.Medium
                    )
                }

                if (!negocio.abierto) {
                    Text(
                        text = "Cerrado",
                        style = MaterialTheme.typography.bodySmall,
                        color = Red,
                        fontWeight = FontWeight.Medium
                    )
                }
            }
        }
    }
}
