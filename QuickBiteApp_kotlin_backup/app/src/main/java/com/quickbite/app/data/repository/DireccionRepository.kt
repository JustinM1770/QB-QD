package com.quickbite.app.data.repository

import com.quickbite.app.data.api.QuickBiteApi
import com.quickbite.app.data.model.*
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class DireccionRepository @Inject constructor(
    private val api: QuickBiteApi
) {
    suspend fun getDirecciones(): Result<List<Direccion>> {
        return try {
            val response = api.getDirecciones()
            if (response.isSuccessful && response.body()?.success == true) {
                Result.success(response.body()?.direcciones ?: emptyList())
            } else {
                Result.failure(Exception("Error al cargar direcciones"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun guardar(direccion: Direccion): Result<Direccion> {
        return try {
            val response = api.guardarDireccion(direccion)
            if (response.isSuccessful && response.body()?.success == true) {
                Result.success(response.body()?.direccion ?: direccion)
            } else {
                Result.failure(Exception(response.body()?.message ?: "Error al guardar"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun eliminar(id: Int): Result<Unit> {
        return try {
            api.eliminarDireccion(mapOf("id" to id))
            Result.success(Unit)
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
}
