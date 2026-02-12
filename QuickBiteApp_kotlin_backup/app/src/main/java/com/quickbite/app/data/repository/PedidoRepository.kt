package com.quickbite.app.data.repository

import com.quickbite.app.data.api.QuickBiteApi
import com.quickbite.app.data.model.*
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class PedidoRepository @Inject constructor(
    private val api: QuickBiteApi
) {
    suspend fun crearPedido(
        idDireccion: Int,
        metodoPago: String,
        notas: String = "",
        codigoCupon: String = ""
    ): Result<Int> {
        return try {
            val response = api.crearPedido(
                CrearPedidoRequest(idDireccion, metodoPago, notas, codigoCupon)
            )
            if (response.isSuccessful && response.body()?.success == true) {
                Result.success(response.body()?.idPedido ?: 0)
            } else {
                Result.failure(Exception(response.body()?.message ?: "Error al crear pedido"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun getHistorial(): Result<List<Pedido>> {
        return try {
            val response = api.getHistorialPedidos()
            if (response.isSuccessful && response.body()?.success == true) {
                Result.success(response.body()?.historial ?: emptyList())
            } else {
                Result.failure(Exception("Error al cargar historial"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun getEstado(pedidoId: Int): Result<PedidoStatusResponse> {
        return try {
            val response = api.getEstadoPedido(pedidoId)
            if (response.isSuccessful && response.body()?.success == true) {
                Result.success(response.body()!!)
            } else {
                Result.failure(Exception("Error al cargar estado"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun reordenar(pedidoId: Int): Result<String> {
        return try {
            val response = api.reordenar(mapOf("pedido_id" to pedidoId))
            if (response.isSuccessful) {
                Result.success(response.body()?.get("message")?.toString() ?: "Reordenado")
            } else {
                Result.failure(Exception("Error al reordenar"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
}
