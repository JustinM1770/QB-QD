package com.quickbite.app.ui.screens.cart

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.quickbite.app.data.model.Carrito
import com.quickbite.app.data.repository.CarritoRepository
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

data class CartUiState(
    val isLoading: Boolean = false,
    val carrito: Carrito = Carrito(),
    val error: String? = null
)

@HiltViewModel
class CartViewModel @Inject constructor(
    private val carritoRepository: CarritoRepository
) : ViewModel() {

    private val _uiState = MutableStateFlow(CartUiState())
    val uiState: StateFlow<CartUiState> = _uiState

    init { loadCarrito() }

    fun loadCarrito() {
        viewModelScope.launch {
            _uiState.value = CartUiState(isLoading = true)
            carritoRepository.getCarrito()
                .onSuccess { carrito -> _uiState.value = CartUiState(carrito = carrito) }
                .onFailure { e -> _uiState.value = CartUiState(error = e.message) }
        }
    }

    fun updateQuantity(itemId: Int, cantidad: Int) {
        viewModelScope.launch {
            if (cantidad <= 0) {
                carritoRepository.eliminar(itemId)
            } else {
                carritoRepository.actualizarCantidad(itemId, cantidad)
            }.onSuccess { carrito -> _uiState.value = CartUiState(carrito = carrito) }
        }
    }

    fun removeItem(itemId: Int) {
        viewModelScope.launch {
            carritoRepository.eliminar(itemId)
                .onSuccess { carrito -> _uiState.value = CartUiState(carrito = carrito) }
        }
    }

    fun clearCart() {
        viewModelScope.launch {
            carritoRepository.vaciar()
                .onSuccess { _uiState.value = CartUiState(carrito = Carrito()) }
        }
    }
}
