package de.meinseelenfunke.app.di

import android.content.Context
import android.content.SharedPreferences
import android.content.pm.ApplicationInfo
import android.security.keystore.KeyGenParameterSpec
import android.security.keystore.KeyProperties
import android.util.Base64
import de.meinseelenfunke.app.data.api.AiApi
import de.meinseelenfunke.app.data.api.AuthApi
import de.meinseelenfunke.app.data.api.EmailApi
import de.meinseelenfunke.app.data.api.FinanceApi
import de.meinseelenfunke.app.data.api.OrderApi
import de.meinseelenfunke.app.data.api.OrganizerApi
import de.meinseelenfunke.app.data.repository.AiRepository
import de.meinseelenfunke.app.data.repository.AuthRepository
import de.meinseelenfunke.app.data.repository.EmailRepository
import de.meinseelenfunke.app.data.repository.FinanceRepository
import de.meinseelenfunke.app.data.repository.OrderRepository
import de.meinseelenfunke.app.data.repository.OrganizerRepository
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.asStateFlow
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import java.net.URI
import java.security.KeyStore
import java.security.SecureRandom
import java.util.Locale
import java.util.concurrent.ConcurrentHashMap
import java.util.concurrent.TimeUnit
import javax.crypto.Cipher
import javax.crypto.KeyGenerator
import javax.crypto.SecretKey
import javax.crypto.spec.GCMParameterSpec

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
    private const val KEY_TAPO_EMAIL = "tapo_encrypted_email"
    private const val KEY_TAPO_PASS = "tapo_encrypted_password"
    private const val KEY_INTERNAL_INTENT_TOKEN = "internal_intent_token_v1"
    private const val KEY_TLS_URL_MIGRATED_V1 = "tls_url_migrated_v1"
    private const val KEYSTORE_ALIAS = "seelenfunke_master_key_v1"
    private const val ENC_PREFIX = "enc_v1:"

    private val ALLOWED_HTTPS_HOSTS = setOf(
        "mein-seelenfunke.de",
        "www.mein-seelenfunke.de",
        "stage.mein-seelenfunke.de"
    )

    private val ALLOWED_CLEARTEXT_HOSTS = setOf(
        "127.0.0.1",
        "localhost",
        "10.0.2.2",
        "192.168.188.119",
        "seelenfunke.test"
    )

    // Volatile RAM-only fallback if AndroidKeyStore is ever temporarily unavailable;
    // NEVER writes secrets in plaintext to disk.
    private val sessionFallbackSecrets = ConcurrentHashMap<String, String>()

    @Synchronized
    fun ensureInitialized(ctx: Context) {
        if (!::sharedPreferences.isInitialized) {
            init(ctx.applicationContext)
        }
    }

    private fun getOrCreateSecretKey(recreateIfCorrupted: Boolean = false): SecretKey {
        val keyStore = KeyStore.getInstance("AndroidKeyStore").apply { load(null) }
        if (recreateIfCorrupted && keyStore.containsAlias(KEYSTORE_ALIAS)) {
            keyStore.deleteEntry(KEYSTORE_ALIAS)
        }
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
            sessionFallbackSecrets.remove(key)
            sharedPreferences.edit().remove(key).apply()
            return
        }
        try {
            encryptAndPersist(key, value, recreateKey = false)
            sessionFallbackSecrets.remove(key)
        } catch (_: Exception) {
            try {
                encryptAndPersist(key, value, recreateKey = true)
                sessionFallbackSecrets.remove(key)
            } catch (_: Exception) {
                // Keep secret strictly in volatile RAM; never persist plaintext to disk!
                sessionFallbackSecrets[key] = value
                sharedPreferences.edit().remove(key).apply()
            }
        }
    }

    private fun encryptAndPersist(key: String, value: String, recreateKey: Boolean) {
        val cipher = Cipher.getInstance("AES/GCM/NoPadding")
        cipher.init(Cipher.ENCRYPT_MODE, getOrCreateSecretKey(recreateIfCorrupted = recreateKey))
        val iv = Base64.encodeToString(cipher.iv, Base64.NO_WRAP)
        val encrypted = Base64.encodeToString(cipher.doFinal(value.toByteArray(Charsets.UTF_8)), Base64.NO_WRAP)
        sharedPreferences.edit().putString(key, "$ENC_PREFIX$iv:$encrypted").apply()
    }

    private fun getDecryptedString(key: String, default: String? = null): String? {
        sessionFallbackSecrets[key]?.let { return it }
        val raw = sharedPreferences.getString(key, null) ?: return default
        if (raw.isEmpty()) return raw
        if (!raw.startsWith(ENC_PREFIX)) {
            // Migrate legacy plaintext value immediately to AndroidKeyStore AES-256-GCM
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
        } catch (_: Exception) {
            default
        }
    }

    private val _userTypeState = MutableStateFlow<String?>(null)
    val userTypeState = _userTypeState.asStateFlow()

    private val _themeModeState = MutableStateFlow(ThemeMode.LIGHT)
    val themeModeState = _themeModeState.asStateFlow()

    fun getThemeMode(): ThemeMode {
        if (!::sharedPreferences.isInitialized) return ThemeMode.LIGHT
        val saved = sharedPreferences.getString(KEY_THEME_MODE, ThemeMode.LIGHT.name)
        return try {
            ThemeMode.valueOf(saved ?: ThemeMode.LIGHT.name)
        } catch (_: Exception) {
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
        return getDecryptedString(KEY_FCM_TOKEN, null)?.ifEmpty { null }
    }

    fun saveFcmToken(token: String) {
        putEncryptedString(KEY_FCM_TOKEN, token)
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
        // Default physical devices to TLS-encrypted HTTPS so Bearer tokens are never sent in cleartext on foreign Wi-Fi.
        return if (isEmulator()) "http://10.0.2.2/api/" else "https://stage.mein-seelenfunke.de/api/"
    }

    fun isAllowedCleartextHost(host: String): Boolean {
        return ALLOWED_CLEARTEXT_HOSTS.contains(host.lowercase(Locale.ROOT))
    }

    fun isAllowedBaseUrl(rawUrl: String?): Boolean {
        if (rawUrl.isNullOrBlank()) return false
        val trimmed = rawUrl.trim()
        val uri = try {
            URI(trimmed)
        } catch (_: Exception) {
            return false
        }
        if (uri.userInfo != null) return false
        val scheme = uri.scheme?.lowercase(Locale.ROOT) ?: return false
        val host = uri.host?.lowercase(Locale.ROOT) ?: return false
        return when (scheme) {
            "https" -> ALLOWED_HTTPS_HOSTS.contains(host)
            "http" -> ALLOWED_CLEARTEXT_HOSTS.contains(host)
            else -> false
        }
    }

    fun isAllowedApiRequestUrl(fullUrl: String): Boolean {
        return isAllowedBaseUrl(fullUrl)
    }

    fun sanitizeBaseUrl(rawUrl: String?): String {
        val fallback = getDynamicDefaultBaseUrl()
        if (rawUrl.isNullOrBlank()) return fallback
        val normalized = if (rawUrl.trim().endsWith("/")) rawUrl.trim() else "${rawUrl.trim()}/"
        return if (isAllowedBaseUrl(normalized)) normalized else fallback
    }

    @Synchronized
    fun init(context: Context) {
        this.context = context.applicationContext
        this.sharedPreferences = this.context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)

        // Security: Wipe any legacy persisted user password immediately on startup
        if (sharedPreferences.contains(KEY_SAVED_PASSWORD)) {
            sharedPreferences.edit().remove(KEY_SAVED_PASSWORD).apply()
        }
        sessionFallbackSecrets.remove(KEY_SAVED_PASSWORD)

        _userTypeState.value = sharedPreferences.getString(KEY_USER_TYPE, null)
        _themeModeState.value = getThemeMode()

        val defaultUrl = getDynamicDefaultBaseUrl()
        val currentUrl = sharedPreferences.getString(KEY_BASE_URL, null)
        val alreadyMigratedTls = sharedPreferences.getBoolean(KEY_TLS_URL_MIGRATED_V1, false)

        if (currentUrl == null
            || (currentUrl == "http://10.0.2.2/api/" && !isEmulator())
            || currentUrl.contains("8081")
            || !isAllowedBaseUrl(currentUrl)
            || (!alreadyMigratedTls && !isEmulator() && currentUrl == "http://192.168.188.119/api/")
        ) {
            sharedPreferences.edit()
                .putString(KEY_BASE_URL, defaultUrl)
                .putBoolean(KEY_TLS_URL_MIGRATED_V1, true)
                .apply()
        } else if (!alreadyMigratedTls) {
            sharedPreferences.edit().putBoolean(KEY_TLS_URL_MIGRATED_V1, true).apply()
        }

        // Ensure internal PendingIntent security token exists
        getOrCreateInternalIntentToken()

        // Setup repositories
        authRepository = AuthRepository(this)
        aiRepository = AiRepository(this)
        financeRepository = FinanceRepository(this)
        organizerRepository = OrganizerRepository(this)
        emailRepository = EmailRepository(this)
        orderRepository = OrderRepository(this)
    }

    @Synchronized
    fun getOrCreateInternalIntentToken(): String {
        val existing = getDecryptedString(KEY_INTERNAL_INTENT_TOKEN, null)
        if (!existing.isNullOrBlank() && existing.length >= 32) {
            return existing
        }
        val bytes = ByteArray(32)
        SecureRandom().nextBytes(bytes)
        val generated = Base64.encodeToString(bytes, Base64.URL_SAFE or Base64.NO_WRAP or Base64.NO_PADDING)
        putEncryptedString(KEY_INTERNAL_INTENT_TOKEN, generated)
        return generated
    }

    fun getTapoEmail(): String {
        return getDecryptedString(KEY_TAPO_EMAIL, "") ?: ""
    }

    fun saveTapoEmail(email: String) {
        putEncryptedString(KEY_TAPO_EMAIL, email.trim())
    }

    fun getTapoPassword(): String {
        return getDecryptedString(KEY_TAPO_PASS, "") ?: ""
    }

    fun saveTapoPassword(password: String) {
        putEncryptedString(KEY_TAPO_PASS, password)
    }

    fun getGeminiApiKey(): String {
        return getDecryptedString(KEY_GEMINI_API_KEY, "") ?: ""
    }

    fun saveGeminiApiKey(key: String) {
        putEncryptedString(KEY_GEMINI_API_KEY, key.trim())
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
        rebuildRetrofit()
    }

    fun clearAuthToken() {
        sessionFallbackSecrets.remove(KEY_AUTH_TOKEN)
        sharedPreferences.edit().remove(KEY_AUTH_TOKEN).remove(KEY_SAVED_PASSWORD).apply()
        clearUserType()
        rebuildRetrofit()
    }

    fun getBaseUrl(): String {
        val defaultUrl = getDynamicDefaultBaseUrl()
        val raw = sharedPreferences.getString(KEY_BASE_URL, defaultUrl) ?: defaultUrl
        return sanitizeBaseUrl(raw)
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

    fun saveBaseUrl(url: String): Boolean {
        val trimmed = url.trim()
        val candidate = if (trimmed.endsWith("/")) trimmed else "$trimmed/"
        if (!isAllowedBaseUrl(candidate)) {
            return false
        }
        sharedPreferences.edit()
            .putString(KEY_BASE_URL, candidate)
            .putBoolean(KEY_TLS_URL_MIGRATED_V1, true)
            .apply()
        rebuildRetrofit()
        return true
    }

    fun buildAuthenticatedDownloadClient(): OkHttpClient {
        return OkHttpClient.Builder()
            .connectTimeout(12, TimeUnit.SECONDS)
            .readTimeout(45, TimeUnit.SECONDS)
            .followRedirects(false)
            .followSslRedirects(false)
            .build()
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
                redactHeader("x-goog-api-key")
                level = if (isDebuggable) HttpLoggingInterceptor.Level.BASIC else HttpLoggingInterceptor.Level.NONE
            }

            val okHttpClient = OkHttpClient.Builder()
                .connectTimeout(10, TimeUnit.SECONDS)
                .writeTimeout(30, TimeUnit.SECONDS)
                .readTimeout(45, TimeUnit.SECONDS)
                .addInterceptor(loggingInterceptor)
                .addInterceptor { chain ->
                    val original = chain.request()
                    val requestBuilder = original.newBuilder()

                    getAuthToken()?.let { token ->
                        requestBuilder.header("Authorization", "Bearer $token")
                    }

                    requestBuilder.header("Accept", "application/json")
                    val builtRequest = requestBuilder.build()

                    val response = try {
                        chain.proceed(builtRequest)
                    } catch (e: java.io.IOException) {
                        val host = builtRequest.url.host
                        if (host == "192.168.188.119" && (e is java.net.ConnectException || e is java.net.SocketTimeoutException || e is java.net.NoRouteToHostException)) {
                            val stageUrl = builtRequest.url.newBuilder()
                                .scheme("https")
                                .host("stage.mein-seelenfunke.de")
                                .port(443)
                                .build()
                            val recovered = chain.proceed(builtRequest.newBuilder().url(stageUrl).build())
                            de.meinseelenfunke.app.util.ErrorSentinelReporter.captureAutoHeal(
                                context = context,
                                component = "network.lan_fallback",
                                title = "Lokaler LAN-Endpunkt nicht erreichbar",
                                message = "192.168.188.119 antwortete nicht (${e.javaClass.simpleName}).",
                                healSummary = "Automatisch auf TLS-Endpunkt stage.mein-seelenfunke.de umgeleitet."
                            )
                            recovered
                        } else {
                            throw e
                        }
                    }

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
        return getDecryptedString(KEY_SAVED_EMAIL, "") ?: ""
    }

    fun saveSavedEmail(email: String) {
        putEncryptedString(KEY_SAVED_EMAIL, email.trim())
    }

    @Deprecated("Passwords are never persisted; Sanctum token + BiometricLock handles unlock.")
    fun getSavedPassword(): String = ""

    @Deprecated("Passwords are never persisted; Sanctum token + BiometricLock handles unlock.")
    fun saveSavedPassword(@Suppress("UNUSED_PARAMETER") password: String) {
        sessionFallbackSecrets.remove(KEY_SAVED_PASSWORD)
        sharedPreferences.edit().remove(KEY_SAVED_PASSWORD).apply()
    }

    fun isBiometricEnabled(): Boolean {
        return sharedPreferences.getBoolean(KEY_BIOMETRIC_ENABLED, true)
    }

    fun setBiometricEnabled(enabled: Boolean) {
        sharedPreferences.edit().putBoolean(KEY_BIOMETRIC_ENABLED, enabled).apply()
    }
}
