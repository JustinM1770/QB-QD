package com.quickbite.app.data.model

import com.google.gson.annotations.SerializedName

data class Negocio(
    val id: Int,
    val nombre: String,
    val descripcion: String = "",
    val direccion: String = "",
    val telefono: String = "",
    val logo: String? = null,
    val portada: String? = null,
    val categoria: String = "",
    @SerializedName("id_categoria") val idCategoria: Int = 0,
    val calificacion: Float = 0f,
    @SerializedName("total_resenas") val totalResenas: Int = 0,
    @SerializedName("tiempo_entrega") val tiempoEntrega: String = "",
    @SerializedName("costo_envio") val costoEnvio: Double = 0.0,
    @SerializedName("pedido_minimo") val pedidoMinimo: Double = 0.0,
    val abierto: Boolean = true,
    @SerializedName("es_favorito") val esFavorito: Boolean = false
)

data class NegocioListResponse(
    val success: Boolean,
    val negocios: List<Negocio> = emptyList(),
    val total: Int = 0
)

data class NegocioDetailResponse(
    val success: Boolean,
    val negocio: Negocio? = null,
    val productos: List<Producto> = emptyList(),
    val categorias: List<CategoriaMenu> = emptyList()
)

data class CategoriaMenu(
    val id: Int,
    val nombre: String,
    val productos: List<Producto> = emptyList()
)

data class Categoria(
    val id: Int,
    val nombre: String,
    val icono: String? = null,
    @SerializedName("total_negocios") val totalNegocios: Int = 0
)
