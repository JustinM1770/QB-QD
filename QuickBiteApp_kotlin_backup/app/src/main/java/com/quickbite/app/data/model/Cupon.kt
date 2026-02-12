package com.quickbite.app.data.model

data class Cupon(
    val id: Int,
    val codigo: String,
    val tipo: String, // "porcentaje" o "fijo"
    val valor: Double,
    val descuento: Double = 0.0
)

data class CuponResponse(
    val success: Boolean,
    val valido: Boolean = false,
    val descuento: Double = 0.0,
    val mensaje: String = "",
    val cupon: Cupon? = null
)
