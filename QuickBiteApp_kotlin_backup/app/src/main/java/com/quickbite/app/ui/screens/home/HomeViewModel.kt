package com.quickbite.app.ui.screens.home

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.quickbite.app.data.model.Negocio
import com.quickbite.app.data.repository.NegocioRepository
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

data class HomeUiState(
    val isLoading: Boolean = false,
    val negocios: List<Negocio> = emptyList(),
    val error: String? = null
)

@HiltViewModel
class HomeViewModel @Inject constructor(
    private val negocioRepository: NegocioRepository
) : ViewModel() {

    private val _uiState = MutableStateFlow(HomeUiState())
    val uiState: StateFlow<HomeUiState> = _uiState

    init {
        loadNegocios()
    }

    fun loadNegocios(categoriaId: Int? = null) {
        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(isLoading = true, error = null)
            negocioRepository.getNegocios(categoriaId = categoriaId)
                .onSuccess { negocios ->
                    _uiState.value = HomeUiState(negocios = negocios)
                }
                .onFailure { e ->
                    _uiState.value = HomeUiState(error = e.message)
                }
        }
    }
}
