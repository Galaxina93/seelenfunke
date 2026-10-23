package de.meinseelenfunke.app.di

import android.content.Context
import android.content.SharedPreferences
import android.content.pm.ApplicationInfo
import android.security.keystore.KeyGenParameterSpec
import android.security.keystore.KeyProperties
import android.util.Base64
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
import java.security.KeyStore
import java.util.concurrent.TimeUnit
import javax.crypto.Cipher
import javax.crypto.KeyGenerator
import javax.crypto.SecretKey
import javax.crypto.spec.GCMParameterSpec
import kotlinx.coroutines.flow.MutableStateFlow

enum class ThemeMode {
    DARK, LIGHT, SYSTEM
}

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
    private const val KEY_THEME_MODE = "theme_mode"
    private const val KEY_SAVED_PASSWORD = "saved_password"
    private const val KEY_BIOMETRIC_ENABLED = "biometric_enabled"
    private const val KEYSTORE_ALIAS = "seelenfunke_master_key_v1"
    private const val ENC_PREFIX = "enc_v1:"

    private fun getOrCreateSecretKey(): SecretKey {
        val keyStore = KeyStore.getInstance("AndroidKeyStore").apply { load(null) }
        val existingKey = keyStore.getEntry(KEYSTORE_ALIAS, null) as? KeyStore.SecretKeyEntry
        if (existingKey != null) {
            return existingKey.secretKey
        }
        val keyGenerator = KeyGenerator.getInstance(KeyProperties.KEY_ALGORITHM_AES, "AndroidKeyStore")
        val spec = KeyGenParameterSpec.Builder(
            KEYSTORE_ALIAS,
            KeyProperties.PURPOSE_ENCRYPT or KeyProperties.PURPOSE_DECRYPT
        )
            .setBlockModes(KeyProperties.BLOCK_MODE_GCM)
            .setEncryptionPaddings(KeyProperties.ENCRYPTION_PADDING_NONE)
            .setKeySize(256)
            .build()
        keyGenerator.init(spec)
        return keyGenerator.generateKey()
    }

    private fun putEncryptedString(key: String, value: String) {
        if (value.isEmpty()) {
            sharedPreferences.edit().putString(key, "").apply()
            return
        }
        try {
            val cipher = Cipher.getInstance("AES/GCM/NoPadding")
            cipher.init(Cipher.ENCRYPT_MODE, getOrCreateSecretKey())
            val iv = Base64.encodeToString(cipher.iv, Base64.NO_WRAP)
            val encrypted = Base64.encodeToString(cipher.doFinal(value.toByteArray(Charsets.UTF_8)), Base64.NO_WRAP)
            sharedPreferences.edit().putString(key, "$ENC_PREFIX$iv:$encrypted").apply()
        } catch (e: Exception) {
            // Fallback if Keystore is unavailable on an emulator
            sharedPreferences.edit().putString(key, value).apply()
        }
    }

    private fun getDecryptedString(key: String, default: String? = null): String? {
        val raw = sharedPreferences.getString(key, null) ?: return default
        if (raw.isEmpty()) return raw
        if (!raw.startsWith(ENC_PREFIX)) {
            // Migrate legacy plaintext value transparently to AndroidKeyStore
            putEncryptedString(key, raw)
            return raw
        }
        return try {
            val payload = raw.removePrefix(ENC_PREFIX)
            val parts = payload.split(":", limit = 2)
            if (parts.size != 2) return default
            val iv = Base64.decode(parts[0], Base64.NO_WRAP)
            val cipherBytes = Base64.decode(parts[1], Base64.NO_WRAP)
            val cipher = Cipher.getInstance("AES/GCM/NoPadding")
            cipher.init(Cipher.DECRYPT_MODE, getOrCreateSecretKey(), GCMParameterSpec(128, iv))
            String(cipher.doFinal(cipherBytes), Charsets.UTF_8)
        } catch (e: Exception) {
            default
        }
    }

    private val _userTypeState = kotlinx.coroutines.flow.MutableStateFlow<String?>(null)
    val userTypeState = _userTypeState.asStateFlow()

    private val _themeModeState = MutableStateFlow(ThemeMode.LIGHT)
    val themeModeState = _themeModeState.asStateFlow()

    fun getThemeMode(): ThemeMode {
        if (!::sharedPreferences.isInitialized) return ThemeMode.LIGHT
        val saved = sharedPreferences.getString(KEY_THEME_MODE, ThemeMode.LIGHT.name)
        return try {
            ThemeMode.valueOf(saved ?: ThemeMode.LIGHT.name)
        } catch (e: Exception) {
            ThemeMode.LIGHT
        }
    }

    fun setThemeMode(mode: ThemeMode) {
        if (::sharedPreferences.isInitialized) {
            sharedPreferences.edit().putString(KEY_THEME_MODE, mode.name).apply()
        }
        _themeModeState.value = mode
    }

    fun toggleTheme() {
        val current = _themeModeState.value
        val next = if (current == ThemeMode.DARK) ThemeMode.LIGHT else ThemeMode.DARK
        setThemeMode(next)
    }

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
        return if (isEmulator()) "http://10.0.2.2/api/" else "http://192.168.188.119/api/"
    }

    fun init(context: Context) {
        this.context = context.applicationContext
        this.sharedPreferences = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        _userTypeState.value = sharedPreferences.getString(KEY_USER_TYPE, null)
        _themeModeState.value = getThemeMode()
        
        val defaultUrl = getDynamicDefaultBaseUrl()
        val currentUrl = sharedPreferences.getString(KEY_BASE_URL, null)
        if (currentUrl == null 
            || (currentUrl == "http://10.0.2.2/api/" && !isEmulator())
            || currentUrl.contains("8081")
            || currentUrl.contains("127.0.0.1")
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
        return getDecryptedString(KEY_GEMINI_API_KEY, "") ?: ""
    }

    fun saveGeminiApiKey(key: String) {
        putEncryptedString(KEY_GEMINI_API_KEY, key)
    }

    fun isWakeWordEnabled(): Boolean {
        return sharedPreferences.getBoolean(KEY_WAKE_WORD_ENABLED, false)
    }

    fun setWakeWordEnabled(enabled: Boolean) {
        sharedPreferences.edit().putBoolean(KEY_WAKE_WORD_ENABLED, enabled).apply()
    }

    fun getAuthToken(): String? {
        return getDecryptedString(KEY_AUTH_TOKEN, null)?.ifEmpty { null }
    }

    fun saveAuthToken(token: String) {
        putEncryptedString(KEY_AUTH_TOKEN, token)
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
            val isDebuggable = (context.applicationInfo.flags and ApplicationInfo.FLAG_DEBUGGABLE) != 0
            val loggingInterceptor = HttpLoggingInterceptor().apply {
                redactHeader("Authorization")
                redactHeader("Cookie")
                level = if (isDebuggable) HttpLoggingInterceptor.Level.BASIC else HttpLoggingInterceptor.Level.NONE
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
        return sharedPreferences.getBoolean(KEY_REMEMBER_ME, true)
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

    fun getSavedPassword(): String {
        return getDecryptedString(KEY_SAVED_PASSWORD, "") ?: ""
    }

    fun saveSavedPassword(password: String) {
        putEncryptedString(KEY_SAVED_PASSWORD, password)
    }

    fun isBiometricEnabled(): Boolean {
        return sharedPreferences.getBoolean(KEY_BIOMETRIC_ENABLED, true)
    }

    fun setBiometricEnabled(enabled: Boolean) {
        sharedPreferences.edit().putBoolean(KEY_BIOMETRIC_ENABLED, enabled).apply()
    }
}
