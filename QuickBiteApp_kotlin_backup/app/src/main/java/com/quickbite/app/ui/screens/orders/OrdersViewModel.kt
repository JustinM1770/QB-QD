package com.quickbite.app.ui.screens.orders

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.quickbite.app.data.model.Pedido
import com.quickbite.app.data.model.PedidoStatusResponse
import com.quickbite.app.data.repository.PedidoRepository
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

data class OrdersUiState(
    val isLoading: Boolean = false,
    val pedidos: List<Pedido> = emptyList(),
    val error: String? = null
)

data class OrderDetailUiState(
    val isLoading: Boolean = false,
    val status: PedidoStatusResponse? = null,
    val error: String? = null
)

@HiltViewModel
class OrdersViewModel @Inject constructor(
    private val pedidoRepository: PedidoRepository
) : ViewModel() {

    private val _uiState = MutableStateFlow(OrdersUiState())
    val uiState: StateFlow<OrdersUiState> = _uiState

    private val _detailState = MutableStateFlow(OrderDetailUiState())
    val detailState: StateFlow<OrderDetailUiState> = _detailState

    init { loadHistorial() }

    fun loadHistorial() {
        viewModelScope.launch {
            _uiState.value = OrdersUiState(isLoading = true)
            pedidoRepository.getHistorial()
                .onSuccess { pedidos -> _uiState.value = OrdersUiState(pedidos = pedidos) }
                .onFailure { e -> _uiState.value = OrdersUiState(error = e.message) }
        }
    }

    fun loadOrderStatus(pedidoId: Int) {
        viewModelScope.launch {
            _detailState.value = OrderDetailUiState(isLoading = true)
            pedidoRepository.getEstado(pedidoId)
                .onSuccess { status -> _detailState.value = OrderDetailUiState(status = status) }
                .onFailure { e -> _detailState.value = OrderDetailUiState(error = e.message) }
        }
    }

    // Polling para tracking en tiempo real
    fun startTracking(pedidoId: Int) {
        viewModelScope.launch {
            while (true) {
                loadOrderStatus(pedidoId)
                delay(15_000) // cada 15 segundos
                val status = _detailState.value.status?.status ?: break
                if (status >= 6) break // entregado o cancelado
            }
        }
    }
}
