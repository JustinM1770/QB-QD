package com.quickbite.app.ui.screens.restaurant

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.quickbite.app.data.model.*
import com.quickbite.app.data.repository.CarritoRepository
import com.quickbite.app.data.repository.NegocioRepository
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

data class RestaurantUiState(
    val isLoading: Boolean = false,
    val negocio: Negocio? = null,
    val productos: List<Producto> = emptyList(),
    val categorias: List<CategoriaMenu> = emptyList(),
    val error: String? = null,
    val addedToCart: Boolean = false
)

@HiltViewModel
class RestaurantViewModel @Inject constructor(
    private val negocioRepository: NegocioRepository,
    private val carritoRepository: CarritoRepository
) : ViewModel() {

    private val _uiState = MutableStateFlow(RestaurantUiState())
    val uiState: StateFlow<RestaurantUiState> = _uiState

    fun loadNegocio(id: Int) {
        viewModelScope.launch {
            _uiState.value = RestaurantUiState(isLoading = true)
            negocioRepository.getNegocioDetalle(id)
                .onSuccess { detail ->
                    _uiState.value = RestaurantUiState(
                        negocio = detail.negocio,
                        productos = detail.productos,
                        categorias = detail.categorias
                    )
                }
                .onFailure { e ->
                    _uiState.value = RestaurantUiState(error = e.message)
                }
        }
    }

    fun addToCart(productoId: Int, cantidad: Int = 1) {
        viewModelScope.launch {
            carritoRepository.agregar(productoId, cantidad)
                .onSuccess {
                    _uiState.value = _uiState.value.copy(addedToCart = true)
                }
        }
    }

    fun clearCartMessage() {
        _uiState.value = _uiState.value.copy(addedToCart = false)
    }
}
