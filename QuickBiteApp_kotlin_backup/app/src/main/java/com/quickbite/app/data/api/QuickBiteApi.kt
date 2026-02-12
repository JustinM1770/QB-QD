package com.quickbite.app.data.api

import com.quickbite.app.data.model.*
import retrofit2.Response
import retrofit2.http.*

interface QuickBiteApi {

    // ==================== AUTH ====================

    @POST("api/auth/login.php")
    suspend fun login(@Body request: LoginRequest): Response<AuthResponse>

    @POST("api/auth/register.php")
    suspend fun register(@Body request: RegisterRequest): Response<AuthResponse>

    @POST("api/auth/logout.php")
    suspend fun logout(): Response<AuthResponse>

    // ==================== NEGOCIOS ====================

    @GET("api/negocios/listar.php")
    suspend fun getNegocios(
        @Query("categoria") categoriaId: Int? = null,
        @Query("buscar") buscar: String? = null,
        @Query("pagina") pagina: Int = 1,
        @Query("limite") limite: Int = 20
    ): Response<NegocioListResponse>

    @GET("api/negocios/detalle.php")
    suspend fun getNegocioDetalle(
        @Query("id") negocioId: Int
    ): Response<NegocioDetailResponse>

    @GET("api/negocios/categorias.php")
    suspend fun getCategorias(): Response<Map<String, Any>>

    // ==================== CARRITO ====================

    @POST("api/carrito/agregar.php")
    suspend fun agregarAlCarrito(@Body request: AgregarCarritoRequest): Response<CarritoResponse>

    @GET("api/carrito/obtener.php")
    suspend fun getCarrito(): Response<CarritoResponse>

    @POST("api/carrito/actualizar.php")
    suspend fun actualizarCantidad(
        @Body body: Map<String, Int>
    ): Response<CarritoResponse>

    @POST("api/carrito/eliminar.php")
    suspend fun eliminarDelCarrito(
        @Body body: Map<String, Int>
    ): Response<CarritoResponse>

    @POST("api/carrito/vaciar.php")
    suspend fun vaciarCarrito(): Response<CarritoResponse>

    // ==================== PEDIDOS ====================

    @POST("api/index.php")
    suspend fun crearPedido(@Body request: CrearPedidoRequest): Response<CrearPedidoResponse>

    @GET("api/get_order_history.php")
    suspend fun getHistorialPedidos(): Response<PedidoHistorialResponse>

    @GET("api/get_order_status.php")
    suspend fun getEstadoPedido(
        @Query("id") pedidoId: Int
    ): Response<PedidoStatusResponse>

    // ==================== DIRECCIONES ====================

    @GET("api/get_addresses.php")
    suspend fun getDirecciones(): Response<DireccionesResponse>

    @POST("api/guardar_direccion.php")
    suspend fun guardarDireccion(@Body direccion: Direccion): Response<GuardarDireccionResponse>

    @POST("api/delete_address.php")
    suspend fun eliminarDireccion(@Body body: Map<String, Int>): Response<Map<String, Any>>

    // ==================== FAVORITOS ====================

    @POST("api/toggle_favorite.php")
    suspend fun toggleFavorito(@Body body: Map<String, Int>): Response<Map<String, Any>>

    @GET("api/get_favorites.php")
    suspend fun getFavoritos(): Response<NegocioListResponse>

    // ==================== CUPONES ====================

    @GET("api/cupones.php")
    suspend fun validarCupon(
        @Query("action") action: String = "validar",
        @Query("codigo") codigo: String,
        @Query("subtotal") subtotal: Double,
        @Query("negocio_id") negocioId: Int
    ): Response<CuponResponse>

    // ==================== RESEÑAS ====================

    @POST("api/resenas.php")
    suspend fun crearResena(@Body body: Map<String, Any>): Response<Map<String, Any>>

    // ==================== REORDER ====================

    @POST("api/reorder.php")
    suspend fun reordenar(@Body body: Map<String, Int>): Response<Map<String, Any>>
}
