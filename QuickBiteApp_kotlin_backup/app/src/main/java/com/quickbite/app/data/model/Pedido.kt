package com.quickbite.app.data.model

import com.google.gson.annotations.SerializedName

data class Pedido(
    val id: Int,
    val fecha: String = "",
    @SerializedName("fecha_formateada") val fechaFormateada: String = "",
    val negocio: String = "",
    @SerializedName("id_negocio") val idNegocio: Int = 0,
    @SerializedName("negocio_imagen") val negocioImagen: String? = null,
    val total: Double = 0.0,
    @SerializedName("total_formateado") val totalFormateado: String = "",
    val estado: String = "",
    @SerializedName("estado_texto") val estadoTexto: String = "",
    @SerializedName("delivery_type") val deliveryType: String = "delivery",
    val items: List<CarritoItem> = emptyList()
)

data class PedidoHistorialResponse(
    val success: Boolean,
    val historial: List<Pedido> = emptyList(),
    val total: Int = 0
)

data class PedidoStatusResponse(
    val success: Boolean,
    val status: Int = 0,
    @SerializedName("last_updated") val lastUpdated: String = "",
    @SerializedName("delivery_type") val deliveryType: String = "",
    val timestamp: String = ""
)

data class CrearPedidoRequest(
    @SerializedName("id_direccion") val idDireccion: Int,
    @SerializedName("metodo_pago") val metodoPago: String,
    val notas: String = "",
    @SerializedName("codigo_cupon") val codigoCupon: String = ""
)

data class CrearPedidoResponse(
    val success: Boolean,
    val message: String = "",
    @SerializedName("id_pedido") val idPedido: Int = 0
)
