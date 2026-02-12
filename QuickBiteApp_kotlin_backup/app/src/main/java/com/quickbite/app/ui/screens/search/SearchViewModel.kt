package com.quickbite.app.ui.screens.search

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.quickbite.app.data.model.Negocio
import com.quickbite.app.data.repository.NegocioRepository
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

data class SearchUiState(
    val isLoading: Boolean = false,
    val results: List<Negocio> = emptyList(),
    val query: String = "",
    val error: String? = null
)

@HiltViewModel
class SearchViewModel @Inject constructor(
    private val negocioRepository: NegocioRepository
) : ViewModel() {

    private val _uiState = MutableStateFlow(SearchUiState())
    val uiState: StateFlow<SearchUiState> = _uiState
    private var searchJob: Job? = null

    fun search(query: String) {
        _uiState.value = _uiState.value.copy(query = query)
        searchJob?.cancel()
        if (query.isBlank()) {
            _uiState.value = SearchUiState(query = query)
            return
        }
        searchJob = viewModelScope.launch {
            delay(400) // debounce
            _uiState.value = _uiState.value.copy(isLoading = true)
            negocioRepository.getNegocios(buscar = query)
                .onSuccess { results ->
                    _uiState.value = SearchUiState(results = results, query = query)
                }
                .onFailure { e ->
                    _uiState.value = SearchUiState(error = e.message, query = query)
                }
        }
    }
}
