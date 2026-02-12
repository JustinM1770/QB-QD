package com.quickbite.app.ui.screens.checkout

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.quickbite.app.data.model.*
import com.quickbite.app.data.repository.CarritoRepository
import com.quickbite.app.data.repository.DireccionRepository
import com.quickbite.app.data.repository.PedidoRepository
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

data class CheckoutUiState(
    val isLoading: Boolean = false,
    val carrito: Carrito = Carrito(),
    val direcciones: List<Direccion> = emptyList(),
    val selectedDireccion: Direccion? = null,
    val metodoPago: String = "efectivo",
    val notas: String = "",
    val codigoCupon: String = "",
    val pedidoCreado: Int? = null,
    val error: String? = null
)

@HiltViewModel
class CheckoutViewModel @Inject constructor(
    private val carritoRepository: CarritoRepository,
    private val direccionRepository: DireccionRepository,
    private val pedidoRepository: PedidoRepository
) : ViewModel() {

    private val _uiState = MutableStateFlow(CheckoutUiState())
    val uiState: StateFlow<CheckoutUiState> = _uiState

    init { loadData() }

    private fun loadData() {
        viewModelScope.launch {
            _uiState.value = CheckoutUiState(isLoading = true)
            val carrito = carritoRepository.getCarrito().getOrNull() ?: Carrito()
            val direcciones = direccionRepository.getDirecciones().getOrNull() ?: emptyList()
            val defaultDir = direcciones.firstOrNull { it.predeterminada } ?: direcciones.firstOrNull()
            _uiState.value = CheckoutUiState(
                carrito = carrito,
                direcciones = direcciones,
                selectedDireccion = defaultDir
            )
        }
    }

    fun selectDireccion(dir: Direccion) {
        _uiState.value = _uiState.value.copy(selectedDireccion = dir)
    }

    fun setMetodoPago(metodo: String) {
        _uiState.value = _uiState.value.copy(metodoPago = metodo)
    }

    fun setNotas(notas: String) {
        _uiState.value = _uiState.value.copy(notas = notas)
    }

    fun crearPedido() {
        val state = _uiState.value
        val dirId = state.selectedDireccion?.id ?: return
        viewModelScope.launch {
            _uiState.value = state.copy(isLoading = true, error = null)
            pedidoRepository.crearPedido(dirId, state.metodoPago, state.notas, state.codigoCupon)
                .onSuccess { id ->
                    _uiState.value = state.copy(isLoading = false, pedidoCreado = id)
                }
                .onFailure { e ->
                    _uiState.value = state.copy(isLoading = false, error = e.message)
                }
        }
    }
}
