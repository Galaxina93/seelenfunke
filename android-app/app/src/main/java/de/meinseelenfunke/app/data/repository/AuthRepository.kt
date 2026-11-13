package de.meinseelenfunke.app.data.repository

import android.appwidget.AppWidgetManager
import android.content.ComponentName
import android.content.Context
import android.content.Intent
import de.meinseelenfunke.app.data.EmailWebSocketClient
import de.meinseelenfunke.app.data.api.FcmTokenRequest
import de.meinseelenfunke.app.data.api.FcmTokenResponse
import de.meinseelenfunke.app.data.api.ForgotPasswordRequest
import de.meinseelenfunke.app.data.api.LoginRequest
import de.meinseelenfunke.app.data.api.LoginResponse
import de.meinseelenfunke.app.data.api.User
import de.meinseelenfunke.app.di.ServiceLocator
import de.meinseelenfunke.app.services.WakeWordService
import de.meinseelenfunke.app.util.AppLogger
import de.meinseelenfunke.app.util.MusicPlayerManager
import de.meinseelenfunke.app.util.SecureFileOpener
import de.meinseelenfunke.app.widget.CalendarAppWidgetProvider
import de.meinseelenfunke.app.widget.CalendarMonthWidgetProvider
import de.meinseelenfunke.app.widget.MasterWidgetProvider
import de.meinseelenfunke.app.widget.ShoppingListWidgetProvider
import de.meinseelenfunke.app.widget.TasksWidgetProvider
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.launch
import okhttp3.MediaType.Companion.toMediaTypeOrNull
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import org.json.JSONObject
import retrofit2.HttpException
import java.io.File

class TwoFactorRequiredException(message: String) : Exception(message)

class AuthRepository(private val serviceLocator: ServiceLocator) {

    private val logoutScope = CoroutineScope(SupervisorJob() + Dispatchers.IO)

    suspend fun login(email: String, password: String, twoFactorCode: String? = null): Result<LoginResponse> {
        return try {
            val cleanCode = twoFactorCode?.trim()?.ifEmpty { null }
            val response = serviceLocator.getAuthApi().login(LoginRequest(email, password, cleanCode))
            if (response.status == "two_factor_required" || response.two_factor_required == true) {
                return Result.failure(
                    TwoFactorRequiredException(response.message ?: "Zwei-Faktor-Authentifizierung erforderlich.")
                )
            }
            if (response.status == "success" && response.token != null) {
                serviceLocator.saveAuthToken(response.token)
                response.user_type?.let { serviceLocator.saveUserType(it) }
                serviceLocator.getFcmToken()?.let { token ->
                    try {
                        updateFcmToken(token)
                    } catch (_: Exception) {
                    }
                }
                EmailWebSocketClient.startWebSocket()

                Result.success(response)
            } else {
                Result.failure(Exception(response.message ?: "Login fehlgeschlagen: Kein Token zurückgegeben."))
            }
        } catch (e: HttpException) {
            val errorBody = try { e.response()?.errorBody()?.string() } catch (_: Exception) { null }
            if (!errorBody.isNullOrBlank()) {
                try {
                    val json = JSONObject(errorBody)
                    val status = json.optString("status")
                    val twoFactorReq = json.optBoolean("two_factor_required", false)
                    val msg = json.optString("message").ifBlank { "Authentifizierung fehlgeschlagen (HTTP ${e.code()})." }
                    if (status == "two_factor_required" || twoFactorReq) {
                        return Result.failure(TwoFactorRequiredException(msg))
                    }
                    return Result.failure(Exception(msg))
                } catch (_: Exception) {
                }
            }
            AppLogger.error(serviceLocator.context, "AuthRepo", "login HTTP error: code=${e.code()}")
            Result.failure(Exception("Anmeldung abgelehnt (HTTP ${e.code()})."))
        } catch (e: Exception) {
            AppLogger.error(serviceLocator.context, "AuthRepo", "login failed", e)
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
        val ctx = serviceLocator.context
        val existingToken = serviceLocator.getAuthToken()
        val fcmToken = serviceLocator.getFcmToken()
        val baseUrl = serviceLocator.getBaseUrl().trimEnd('/')

        // 1. Immediately clear local token & stop live connections/services
        serviceLocator.clearAuthToken()
        EmailWebSocketClient.disconnect()
        MusicPlayerManager.stopAndClearSession(ctx)
        try {
            ctx.stopService(Intent(ctx, WakeWordService::class.java))
        } catch (_: Exception) {
        }

        // 2. Wipe cached personal/business data from homescreen widget SharedPreferences & temp cache directories
        listOf(
            "tasks_widget_prefs",
            "calendar_widget_prefs",
            "shopping_widget_prefs",
            "master_widget_prefs"
        ).forEach { prefName ->
            try {
                ctx.getSharedPreferences(prefName, Context.MODE_PRIVATE).edit().clear().apply()
            } catch (_: Exception) {
            }
        }

        SecureFileOpener.purgeStaleDownloads(ctx, deleteAll = true)
        listOf("mail_attachments", "camera_captures", "calendar_exports").forEach { subDir ->
            try {
                File(ctx.cacheDir, subDir).deleteRecursively()
            } catch (_: Exception) {
            }
        }

        refreshWidgetsAfterLogout(ctx)

        // 3. Revoke Sanctum PersonalAccessToken & unregister FCM token on the server
        if (!existingToken.isNullOrBlank() && ServiceLocator.isAllowedApiRequestUrl("$baseUrl/logout")) {
            logoutScope.launch {
                try {
                    val bodyJson = JSONObject().apply {
                        if (!fcmToken.isNullOrBlank()) {
                            put("fcm_token", fcmToken)
                        }
                    }.toString()
                    val req = Request.Builder()
                        .url("$baseUrl/logout")
                        .header("Authorization", "Bearer $existingToken")
                        .header("Accept", "application/json")
                        .post(bodyJson.toRequestBody("application/json".toMediaTypeOrNull()))
                        .build()
                    ServiceLocator.buildAuthenticatedDownloadClient().newCall(req).execute().close()
                } catch (_: Exception) {
                }
            }
        }
    }

    private fun refreshWidgetsAfterLogout(ctx: Context) {
        try {
            val mgr = AppWidgetManager.getInstance(ctx)
            listOf(
                TasksWidgetProvider::class.java,
                CalendarAppWidgetProvider::class.java,
                CalendarMonthWidgetProvider::class.java,
                ShoppingListWidgetProvider::class.java,
                MasterWidgetProvider::class.java
            ).forEach { providerCls ->
                val ids = mgr.getAppWidgetIds(ComponentName(ctx, providerCls))
                if (ids.isNotEmpty()) {
                    val updateIntent = Intent(ctx, providerCls).apply {
                        action = AppWidgetManager.ACTION_APPWIDGET_UPDATE
                        putExtra(AppWidgetManager.EXTRA_APPWIDGET_IDS, ids)
                    }
                    ctx.sendBroadcast(updateIntent)
                }
            }
        } catch (_: Exception) {
        }
    }

    fun isLoggedIn(): Boolean {
        return serviceLocator.getAuthToken() != null
    }

    suspend fun updateFcmToken(fcmToken: String): Result<FcmTokenResponse> {
        val context = serviceLocator.context
        return try {
            val response = serviceLocator.getAuthApi().updateFcmToken(FcmTokenRequest(fcmToken))
            if (response.success) {
                Result.success(response)
            } else {
                val err = Exception(response.message ?: "Geräteregistrierung fehlgeschlagen")
                AppLogger.error(context, "AuthRepo", "updateFcmToken failed", err)
                Result.failure(err)
            }
        } catch (e: Exception) {
            AppLogger.error(context, "AuthRepo", "updateFcmToken exception", e)
            Result.failure(e)
        }
    }

    suspend fun sendPasswordResetEmail(email: String): Result<String> {
        return try {
            val response = serviceLocator.getAuthApi().forgotPassword(ForgotPasswordRequest(email))
            if (response.status == "success") {
                Result.success(response.message)
            } else {
                Result.failure(Exception(response.message))
            }
        } catch (e: Exception) {
            AppLogger.error(serviceLocator.context, "AuthRepo", "sendPasswordResetEmail failed", e)
            Result.failure(e)
        }
    }
}
