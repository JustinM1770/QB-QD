package com.quickbite.app.data.model

import com.google.gson.annotations.SerializedName

data class Producto(
    val id: Int,
    val nombre: String,
    val descripcion: String = "",
    val precio: Double,
    val imagen: String? = null,
    @SerializedName("id_negocio") val idNegocio: Int = 0,
    val categoria: String = "",
    val disponible: Boolean = true,
    val opciones: List<OpcionProducto> = emptyList()
)

data class OpcionProducto(
    val id: Int,
    val nombre: String,
    val tipo: String = "radio", // radio, checkbox
    val requerido: Boolean = false,
    val items: List<ItemOpcion> = emptyList()
)

data class ItemOpcion(
    val id: Int,
    val nombre: String,
    @SerializedName("precio_extra") val precioExtra: Double = 0.0
)
