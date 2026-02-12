package com.quickbite.app.data.repository

import com.quickbite.app.data.api.QuickBiteApi
import com.quickbite.app.data.model.*
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class NegocioRepository @Inject constructor(
    private val api: QuickBiteApi
) {
    suspend fun getNegocios(categoriaId: Int? = null, buscar: String? = null): Result<List<Negocio>> {
        return try {
            val response = api.getNegocios(categoriaId = categoriaId, buscar = buscar)
            if (response.isSuccessful && response.body()?.success == true) {
                Result.success(response.body()?.negocios ?: emptyList())
            } else {
                Result.failure(Exception("Error al cargar negocios"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun getNegocioDetalle(id: Int): Result<NegocioDetailResponse> {
        return try {
            val response = api.getNegocioDetalle(id)
            if (response.isSuccessful && response.body()?.success == true) {
                Result.success(response.body()!!)
            } else {
                Result.failure(Exception("Error al cargar detalle"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun toggleFavorito(negocioId: Int): Result<Boolean> {
        return try {
            val response = api.toggleFavorito(mapOf("id_negocio" to negocioId))
            if (response.isSuccessful) {
                val favorito = (response.body()?.get("favorito") as? Boolean) ?: false
                Result.success(favorito)
            } else {
                Result.failure(Exception("Error"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun getFavoritos(): Result<List<Negocio>> {
        return try {
            val response = api.getFavoritos()
            if (response.isSuccessful) {
                Result.success(response.body()?.negocios ?: emptyList())
            } else {
                Result.failure(Exception("Error al cargar favoritos"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
}
