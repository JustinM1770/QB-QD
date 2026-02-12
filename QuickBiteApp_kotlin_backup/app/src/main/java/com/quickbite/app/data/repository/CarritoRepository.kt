package com.quickbite.app.data.repository

import com.quickbite.app.data.api.QuickBiteApi
import com.quickbite.app.data.model.*
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class CarritoRepository @Inject constructor(
    private val api: QuickBiteApi
) {
    suspend fun getCarrito(): Result<Carrito> {
        return try {
            val response = api.getCarrito()
            if (response.isSuccessful && response.body()?.success == true) {
                Result.success(response.body()?.carrito ?: Carrito())
            } else {
                Result.failure(Exception("Error al cargar carrito"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun agregar(idProducto: Int, cantidad: Int = 1, notas: String = ""): Result<Carrito> {
        return try {
            val response = api.agregarAlCarrito(
                AgregarCarritoRequest(idProducto, cantidad, notas)
            )
            if (response.isSuccessful && response.body()?.success == true) {
                Result.success(response.body()?.carrito ?: Carrito())
            } else {
                Result.failure(Exception(response.body()?.message ?: "Error al agregar"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun actualizarCantidad(itemId: Int, cantidad: Int): Result<Carrito> {
        return try {
            val response = api.actualizarCantidad(mapOf("id" to itemId, "cantidad" to cantidad))
            if (response.isSuccessful && response.body()?.success == true) {
                Result.success(response.body()?.carrito ?: Carrito())
            } else {
                Result.failure(Exception("Error al actualizar"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun eliminar(itemId: Int): Result<Carrito> {
        return try {
            val response = api.eliminarDelCarrito(mapOf("id" to itemId))
            if (response.isSuccessful && response.body()?.success == true) {
                Result.success(response.body()?.carrito ?: Carrito())
            } else {
                Result.failure(Exception("Error al eliminar"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun vaciar(): Result<Unit> {
        return try {
            api.vaciarCarrito()
            Result.success(Unit)
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
}
