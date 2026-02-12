package com.quickbite.app.data.model

import com.google.gson.annotations.SerializedName

data class Direccion(
    val id: Int = 0,
    val nombre: String = "",
    val calle: String = "",
    val numero: String = "",
    val colonia: String = "",
    val ciudad: String = "",
    val estado: String = "",
    @SerializedName("codigo_postal") val codigoPostal: String = "",
    val latitud: Double = 0.0,
    val longitud: Double = 0.0,
    val predeterminada: Boolean = false
)

data class DireccionesResponse(
    val success: Boolean,
    val direcciones: List<Direccion> = emptyList()
)

data class GuardarDireccionResponse(
    val success: Boolean,
    val message: String = "",
    val direccion: Direccion? = null
)
