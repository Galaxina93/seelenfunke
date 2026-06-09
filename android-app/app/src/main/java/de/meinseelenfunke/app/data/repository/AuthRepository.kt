package de.meinseelenfunke.app.data.repository

import de.meinseelenfunke.app.data.api.LoginRequest
import de.meinseelenfunke.app.data.api.LoginResponse
import de.meinseelenfunke.app.data.api.User
import de.meinseelenfunke.app.di.ServiceLocator
import de.meinseelenfunke.app.util.AppLogger

class AuthRepository(private val serviceLocator: ServiceLocator) {

    suspend fun login(email: String, password: String): Result<LoginResponse> {
        return try {
            val response = serviceLocator.getAuthApi().login(LoginRequest(email, password))
            if (response.status == "success" && response.token != null) {
                // Save token inside SharedPreferences via ServiceLocator
                serviceLocator.saveAuthToken(response.token)
                response.user_type?.let { serviceLocator.saveUserType(it) }
                // Upload cached FCM token if present
                serviceLocator.getFcmToken()?.let { token ->
                    try {
                        updateFcmToken(token)
                    } catch (e: Exception) {
                        e.printStackTrace()
                    }
                }

                Result.success(response)
            } else {
                Result.failure(Exception("Login fehlgeschlagen: Kein Token zurückgegeben."))
            }
        } catch (e: Exception) {
            AppLogger.error(serviceLocator.context, "AuthRepo", "login failed: email=$email", e)
            Result.failure(e)
        }
    }
    suspend fun getCurrentUser(): Result<User> {
        return try {
            val user = serviceLocator.getAuthApi().getUser()
            user.user_type?.let { serviceLocator.saveUserType(it) }
            Result.success(user)
        } catch (e: Exception) {
            AppLogger.error(serviceLocator.context, "AuthRepo", "getCurrentUser failed", e)
            Result.failure(e)
        }
    }
    fun logout() {
        serviceLocator.clearAuthToken()
    }

    fun isLoggedIn(): Boolean {
        return serviceLocator.getAuthToken() != null
    }

    suspend fun updateFcmToken(fcmToken: String): Result<de.meinseelenfunke.app.data.api.FcmTokenResponse> {
        val context = serviceLocator.context
        de.meinseelenfunke.app.util.AppLogger.info(context, "AuthRepo", "updateFcmToken: uploading FCM token to server")
        return try {
            val response = serviceLocator.getAuthApi().updateFcmToken(de.meinseelenfunke.app.data.api.FcmTokenRequest(fcmToken))
            if (response.success) {
                de.meinseelenfunke.app.util.AppLogger.info(context, "AuthRepo", "updateFcmToken: FCM token successfully registered on server")
                Result.success(response)
            } else {
                val err = Exception(response.message ?: "Geräteregistrierung fehlgeschlagen")
                de.meinseelenfunke.app.util.AppLogger.error(context, "AuthRepo", "updateFcmToken failed", err)
                Result.failure(err)
            }
        } catch (e: Exception) {
            de.meinseelenfunke.app.util.AppLogger.error(context, "AuthRepo", "updateFcmToken exception", e)
            Result.failure(e)
        }
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
            AppLogger.error(serviceLocator.context, "AuthRepo", "sendPasswordResetEmail failed: email=$email", e)
            Result.failure(e)
        }
    }
}
