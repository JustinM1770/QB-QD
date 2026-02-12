package com.quickbite.app.data.repository

import com.quickbite.app.data.api.QuickBiteApi
import com.quickbite.app.data.api.SessionCookieJar
import com.quickbite.app.data.model.*
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class AuthRepository @Inject constructor(
    private val api: QuickBiteApi,
    private val cookieJar: SessionCookieJar
) {
    var currentUser: Usuario? = null
        private set

    val isLoggedIn: Boolean
        get() = currentUser != null && cookieJar.hasSession()

    suspend fun login(email: String, password: String): Result<Usuario> {
        return try {
            val response = api.login(LoginRequest(email, password))
            if (response.isSuccessful && response.body()?.success == true) {
                currentUser = response.body()?.usuario
                Result.success(currentUser!!)
            } else {
                Result.failure(Exception(response.body()?.message ?: "Error al iniciar sesión"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun register(nombre: String, email: String, password: String, telefono: String): Result<Usuario> {
        return try {
            val response = api.register(RegisterRequest(nombre, email, password, telefono))
            if (response.isSuccessful && response.body()?.success == true) {
                currentUser = response.body()?.usuario
                Result.success(currentUser!!)
            } else {
                Result.failure(Exception(response.body()?.message ?: "Error al registrarse"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun logout() {
        try { api.logout() } catch (_: Exception) {}
        currentUser = null
        cookieJar.clear()
    }
}
