package com.quickbite.app.data.model

import com.google.gson.annotations.SerializedName

data class Usuario(
    val id: Int = 0,
    val nombre: String = "",
    val email: String = "",
    val telefono: String = "",
    @SerializedName("tipo_usuario") val tipoUsuario: String = "cliente",
    @SerializedName("foto_perfil") val fotoPerfil: String? = null,
    @SerializedName("email_verificado") val emailVerificado: Boolean = false
)

data class LoginRequest(
    val email: String,
    val password: String
)

data class RegisterRequest(
    val nombre: String,
    val email: String,
    val password: String,
    val telefono: String
)

data class AuthResponse(
    val success: Boolean,
    val message: String,
    val usuario: Usuario? = null
)
