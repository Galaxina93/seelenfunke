package de.meinseelenfunke.app.di

import android.content.Context
import android.content.SharedPreferences
import de.meinseelenfunke.app.data.api.AiApi
import de.meinseelenfunke.app.data.api.AuthApi
import de.meinseelenfunke.app.data.api.FinanceApi
import de.meinseelenfunke.app.data.api.OrganizerApi
import de.meinseelenfunke.app.data.api.EmailApi
import de.meinseelenfunke.app.data.api.OrderApi
import de.meinseelenfunke.app.data.repository.AiRepository
import de.meinseelenfunke.app.data.repository.AuthRepository
import de.meinseelenfunke.app.data.repository.FinanceRepository
import de.meinseelenfunke.app.data.repository.OrganizerRepository
import de.meinseelenfunke.app.data.repository.EmailRepository
import de.meinseelenfunke.app.data.repository.OrderRepository
import kotlinx.coroutines.flow.asStateFlow
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import java.util.concurrent.TimeUnit

object ServiceLocator {

    lateinit var context: Context
        private set
    private lateinit var sharedPreferences: SharedPreferences

    private var retrofit: Retrofit? = null
    private var authApi: AuthApi? = null
    private var aiApi: AiApi? = null
    private var financeApi: FinanceApi? = null
    private var organizerApi: OrganizerApi? = null
    private var emailApi: EmailApi? = null
    private var orderApi: OrderApi? = null

    // Repositories
    lateinit var authRepository: AuthRepository
        private set
    lateinit var aiRepository: AiRepository
        private set
    lateinit var financeRepository: FinanceRepository
        private set
    lateinit var organizerRepository: OrganizerRepository
        private set
    lateinit var emailRepository: EmailRepository
        private set
    lateinit var orderRepository: OrderRepository
        private set

    private const val PREFS_NAME = "seelenfunke_prefs"
    private const val KEY_AUTH_TOKEN = "auth_token"
    private const val KEY_BASE_URL = "base_url"
    private const val KEY_GEMINI_API_KEY = "gemini_api_key"
    private const val KEY_REMEMBER_ME = "remember_me"
    private const val KEY_SAVED_EMAIL = "saved_email"
    private const val KEY_WAKE_WORD_ENABLED = "wake_word_enabled"
    private const val KEY_FCM_TOKEN = "fcm_token"
    private const val KEY_USER_TYPE = "user_type"

    private val _userTypeState = kotlinx.coroutines.flow.MutableStateFlow<String?>(null)
    val userTypeState = _userTypeState.asStateFlow()

    fun getUserType(): String? {
        return sharedPreferences.getString(KEY_USER_TYPE, null)
    }

    fun saveUserType(type: String) {
        sharedPreferences.edit().putString(KEY_USER_TYPE, type).apply()
        _userTypeState.value = type
    }

    fun clearUserType() {
        sharedPreferences.edit().remove(KEY_USER_TYPE).apply()
        _userTypeState.value = null
    }

    fun getFcmToken(): String? {

        return sharedPreferences.getString(KEY_FCM_TOKEN, null)
    }

    fun saveFcmToken(token: String) {
        sharedPreferences.edit().putString(KEY_FCM_TOKEN, token).apply()
    }

    
    private fun isEmulator(): Boolean {
        return android.os.Build.FINGERPRINT.startsWith("generic")
                || android.os.Build.FINGERPRINT.startsWith("unknown")
                || android.os.Build.MODEL.contains("google_sdk")
                || android.os.Build.MODEL.contains("Emulator")
                || android.os.Build.MODEL.contains("Android SDK built for x86")
                || android.os.Build.MANUFACTURER.contains("Genymotion")
                || android.os.Build.PRODUCT.contains("sdk_google")
                || android.os.Build.PRODUCT.contains("google_sdk")
                || android.os.Build.PRODUCT.contains("sdk")
                || android.os.Build.PRODUCT.contains("sdk_x86")
                || android.os.Build.PRODUCT.contains("vbox86p")
                || android.os.Build.PRODUCT.contains("emulator")
                || android.os.Build.PRODUCT.contains("simulator")
    }

    fun getDynamicDefaultBaseUrl(): String {
        return if (isEmulator()) "http://10.0.2.2/api/" else "http://127.0.0.1:8081/api/"
    }

    fun init(context: Context) {
        this.context = context.applicationContext
        this.sharedPreferences = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        _userTypeState.value = sharedPreferences.getString(KEY_USER_TYPE, null)
        
        val defaultUrl = getDynamicDefaultBaseUrl()
        val currentUrl = sharedPreferences.getString(KEY_BASE_URL, null)
        if (currentUrl == null 
            || (currentUrl == "http://10.0.2.2/api/" && !isEmulator())
        ) {
            sharedPreferences.edit().putString(KEY_BASE_URL, defaultUrl).apply()
        }
        
        // Setup repositories
        authRepository = AuthRepository(this)
        aiRepository = AiRepository(this)
        financeRepository = FinanceRepository(this)
        organizerRepository = OrganizerRepository(this)
        emailRepository = EmailRepository(this)
        orderRepository = OrderRepository(this)
    }

    fun getGeminiApiKey(): String {
        return sharedPreferences.getString(KEY_GEMINI_API_KEY, "") ?: ""
    }

    fun saveGeminiApiKey(key: String) {
        sharedPreferences.edit().putString(KEY_GEMINI_API_KEY, key).apply()
    }

    fun isWakeWordEnabled(): Boolean {
        return sharedPreferences.getBoolean(KEY_WAKE_WORD_ENABLED, false)
    }

    fun setWakeWordEnabled(enabled: Boolean) {
        sharedPreferences.edit().putBoolean(KEY_WAKE_WORD_ENABLED, enabled).apply()
    }

    fun getAuthToken(): String? {
        return sharedPreferences.getString(KEY_AUTH_TOKEN, null)
    }

    fun saveAuthToken(token: String) {
        sharedPreferences.edit().putString(KEY_AUTH_TOKEN, token).apply()
        // Rebuild Retrofit with the new token context
        rebuildRetrofit()
    }

    fun clearAuthToken() {
        sharedPreferences.edit().remove(KEY_AUTH_TOKEN).apply()
        clearUserType()
        rebuildRetrofit()
    }


    fun getBaseUrl(): String {
        val defaultUrl = getDynamicDefaultBaseUrl()
        return sharedPreferences.getString(KEY_BASE_URL, defaultUrl) ?: defaultUrl
    }

    fun getWebSocketUrl(): String {
        val baseUrl = getBaseUrl()
        val wsProtocol = if (baseUrl.startsWith("https://")) "wss://" else "ws://"
        val cleanUrl = baseUrl.substringAfter("://")
        val hostPort = if (cleanUrl.endsWith("/api/")) {
            cleanUrl.substringBefore("/api/")
        } else {
            cleanUrl.removeSuffix("/")
        }
        return "$wsProtocol$hostPort/email-sync"
    }

    fun saveBaseUrl(url: String) {
        val cleanUrl = if (url.endsWith("/")) url else "$url/"
        sharedPreferences.edit().putString(KEY_BASE_URL, cleanUrl).apply()
        rebuildRetrofit()
    }

    private fun rebuildRetrofit() {
        retrofit = null
        authApi = null
        aiApi = null
        financeApi = null
        organizerApi = null
        emailApi = null
        orderApi = null
    }

    private fun getRetrofit(): Retrofit {
        if (retrofit == null) {
            val loggingInterceptor = HttpLoggingInterceptor().apply {
                level = HttpLoggingInterceptor.Level.BODY
            }

            val okHttpClient = OkHttpClient.Builder()
                .connectTimeout(30, TimeUnit.SECONDS)
                .readTimeout(30, TimeUnit.SECONDS)
                .addInterceptor(loggingInterceptor)
                .addInterceptor { chain ->
                    val original = chain.request()
                    val requestBuilder = original.newBuilder()
                    
                    // Attach Sanctum Authorization Token if saved
                    getAuthToken()?.let { token ->
                        requestBuilder.header("Authorization", "Bearer $token")
                    }
                    
                    requestBuilder.header("Accept", "application/json")
                    val response = chain.proceed(requestBuilder.build())
                    
                    if (response.code == 401) {
                        clearAuthToken()
                        de.meinseelenfunke.app.util.NavigationBridge.triggerLogout()
                    }
                    
                    response
                }
                .build()

            retrofit = Retrofit.Builder()
                .baseUrl(getBaseUrl())
                .client(okHttpClient)
                .addConverterFactory(GsonConverterFactory.create())
                .build()
        }
        return retrofit!!
    }

    fun getAuthApi(): AuthApi {
        if (authApi == null) {
            authApi = getRetrofit().create(AuthApi::class.java)
        }
        return authApi!!
    }

    fun getAiApi(): AiApi {
        if (aiApi == null) {
            aiApi = getRetrofit().create(AiApi::class.java)
        }
        return aiApi!!
    }

    fun getFinanceApi(): FinanceApi {
        if (financeApi == null) {
            financeApi = getRetrofit().create(FinanceApi::class.java)
        }
        return financeApi!!
    }

    fun getOrganizerApi(): OrganizerApi {
        if (organizerApi == null) {
            organizerApi = getRetrofit().create(OrganizerApi::class.java)
        }
        return organizerApi!!
    }

    fun getEmailApi(): EmailApi {
        if (emailApi == null) {
            emailApi = getRetrofit().create(EmailApi::class.java)
        }
        return emailApi!!
    }

    fun getOrderApi(): OrderApi {
        if (orderApi == null) {
            orderApi = getRetrofit().create(OrderApi::class.java)
        }
        return orderApi!!
    }

    fun isRememberMeEnabled(): Boolean {
        return sharedPreferences.getBoolean(KEY_REMEMBER_ME, false)
    }

    fun saveRememberMeEnabled(enabled: Boolean) {
        sharedPreferences.edit().putBoolean(KEY_REMEMBER_ME, enabled).apply()
    }

    fun getSavedEmail(): String {
        return sharedPreferences.getString(KEY_SAVED_EMAIL, "") ?: ""
    }

    fun saveSavedEmail(email: String) {
        sharedPreferences.edit().putString(KEY_SAVED_EMAIL, email).apply()
    }
}
