package de.meinseelenfunke.app.data.repository

import de.meinseelenfunke.app.data.api.LoginRequest
import de.meinseelenfunke.app.data.api.LoginResponse
import de.meinseelenfunke.app.data.api.User
import de.meinseelenfunke.app.di.ServiceLocator

class AuthRepository(private val serviceLocator: ServiceLocator) {

    suspend fun login(email: String, password: String): Result<LoginResponse> {
        return try {
            val response = serviceLocator.getAuthApi().login(LoginRequest(email, password))
            if (response.status == "success" && response.token != null) {
                // Save token inside SharedPreferences via ServiceLocator
                serviceLocator.saveAuthToken(response.token)
                Result.success(response)
            } else {
                Result.failure(Exception("Login fehlgeschlagen: Kein Token zurückgegeben."))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun getCurrentUser(): Result<User> {
        return try {
            val user = serviceLocator.getAuthApi().getUser()
            Result.success(user)
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    fun logout() {
        serviceLocator.clearAuthToken()
    }

    fun isLoggedIn(): Boolean {
        return serviceLocator.getAuthToken() != null
    }

    suspend fun sendPasswordResetEmail(email: String): Result<String> {
        return try {
            val response = serviceLocator.getAuthApi().forgotPassword(de.meinseelenfunke.app.data.api.ForgotPasswordRequest(email))
            if (response.status == "success") {
                Result.success(response.message)
            } else {
                Result.failure(Exception(response.message))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
}
