package de.meinseelenfunke.app.ui.screens

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import de.meinseelenfunke.app.di.ServiceLocator
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

class LocalAiViewModel : ViewModel() {

    private val aiRepository = ServiceLocator.aiRepository

    private val _apiKey = MutableStateFlow(ServiceLocator.getGeminiApiKey())
    val apiKey: StateFlow<String> = _apiKey.asStateFlow()

    private val _responseText = MutableStateFlow("")
    val responseText: StateFlow<String> = _responseText.asStateFlow()

    private val _isLoading = MutableStateFlow(false)
    val isLoading: StateFlow<Boolean> = _isLoading.asStateFlow()

    private val _error = MutableStateFlow<String?>(null)
    val error: StateFlow<String?> = _error.asStateFlow()

    fun saveApiKey(key: String) {
        _apiKey.value = key
        ServiceLocator.saveGeminiApiKey(key)
    }

    fun generateLocalResponse(prompt: String) {
        if (prompt.isBlank() || _isLoading.value) return

        val key = _apiKey.value
        if (key.isBlank()) {
            _error.value = "Fehler: Kein API-Schlüssel eingegeben! Bitte trage einen Gemini API Key in das Einstellungen-Feld ein."
            return
        }

        _responseText.value = ""
        _error.value = null

        viewModelScope.launch {
            _isLoading.value = true
            aiRepository.generateLocalResponse(prompt, key)
                .onSuccess { text ->
                    _responseText.value = text
                }
                .onFailure { err ->
                    _error.value = "Generierungsfehler: ${err.localizedMessage}"
                }
            _isLoading.value = false
        }
    }
}
