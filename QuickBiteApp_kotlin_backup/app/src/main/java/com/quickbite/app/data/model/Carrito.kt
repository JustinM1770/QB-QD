package com.quickbite.app.data.model

import com.google.gson.annotations.SerializedName

data class Carrito(
    val items: List<CarritoItem> = emptyList(),
    @SerializedName("negocio_id") val negocioId: Int = 0,
    @SerializedName("negocio_nombre") val negocioNombre: String = "",
    val subtotal: Double = 0.0,
    @SerializedName("costo_envio") val costoEnvio: Double = 0.0,
    val descuento: Double = 0.0,
    val total: Double = 0.0
)

data class CarritoItem(
    val id: Int,
    @SerializedName("id_producto") val idProducto: Int,
    val nombre: String,
    val precio: Double,
    val cantidad: Int,
    val imagen: String? = null,
    val notas: String = "",
    val opciones: List<String> = emptyList(),
    val subtotal: Double = 0.0
)

data class AgregarCarritoRequest(
    @SerializedName("id_producto") val idProducto: Int,
    val cantidad: Int = 1,
    val notas: String = "",
    @SerializedName("opciones") val opciones: List<Int> = emptyList()
)

data class CarritoResponse(
    val success: Boolean,
    val message: String = "",
    val carrito: Carrito? = null
)
