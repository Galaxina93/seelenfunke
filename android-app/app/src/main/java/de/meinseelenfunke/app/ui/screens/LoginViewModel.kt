package de.meinseelenfunke.app.ui.screens

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import de.meinseelenfunke.app.di.ServiceLocator
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

sealed class LoginState {
    object Idle : LoginState()
    object Loading : LoginState()
    object Success : LoginState()
    data class Error(val message: String) : LoginState()
}

class LoginViewModel : ViewModel() {

    private val authRepository = ServiceLocator.authRepository

    private val _loginState = MutableStateFlow<LoginState>(LoginState.Idle)
    val loginState: StateFlow<LoginState> = _loginState.asStateFlow()

    private val _baseUrl = MutableStateFlow(ServiceLocator.getBaseUrl())
    val baseUrl: StateFlow<String> = _baseUrl.asStateFlow()

    private val _passwordResetState = MutableStateFlow<Result<String>?>(null)
    val passwordResetState: StateFlow<Result<String>?> = _passwordResetState.asStateFlow()

    fun updateBaseUrl(url: String) {
        _baseUrl.value = url
        ServiceLocator.saveBaseUrl(url)
    }

    fun isRememberMeEnabled(): Boolean = ServiceLocator.isRememberMeEnabled()
    fun getSavedEmail(): String = ServiceLocator.getSavedEmail()

    fun clearPasswordResetState() {
        _passwordResetState.value = null
    }

    fun login(email: String, password: String, rememberMe: Boolean) {
        if (email.isBlank() || password.isBlank()) {
            _loginState.value = LoginState.Error("Bitte fülle alle Felder aus.")
            return
        }

        viewModelScope.launch {
            _loginState.value = LoginState.Loading
            authRepository.login(email, password)
                .onSuccess {
                    ServiceLocator.saveRememberMeEnabled(rememberMe)
                    if (rememberMe) {
                        ServiceLocator.saveSavedEmail(email)
                    } else {
                        ServiceLocator.saveSavedEmail("")
                    }
                    _loginState.value = LoginState.Success
                }
                .onFailure { error ->
                    _loginState.value = LoginState.Error(
                        error.localizedMessage ?: "Authentifizierung fehlgeschlagen."
                    )
                }
        }
    }

    fun sendPasswordResetEmail(email: String) {
        if (email.isBlank()) {
            _passwordResetState.value = Result.failure(Exception("Bitte gib eine E-Mail-Adresse ein."))
            return
        }

        viewModelScope.launch {
            _passwordResetState.value = null
            authRepository.sendPasswordResetEmail(email)
                .onSuccess { message ->
                    _passwordResetState.value = Result.success(message)
                }
                .onFailure { error ->
                    _passwordResetState.value = Result.failure(error)
                }
        }
    }
}
