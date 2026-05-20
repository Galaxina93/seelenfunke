package de.meinseelenfunke.app.di

import android.content.Context
import android.content.SharedPreferences
import de.meinseelenfunke.app.data.api.AiApi
import de.meinseelenfunke.app.data.api.AuthApi
import de.meinseelenfunke.app.data.repository.AiRepository
import de.meinseelenfunke.app.data.repository.AuthRepository
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

    // Repositories
    lateinit var authRepository: AuthRepository
        private set
    lateinit var aiRepository: AiRepository
        private set

    private const val PREFS_NAME = "seelenfunke_prefs"
    private const val KEY_AUTH_TOKEN = "auth_token"
    private const val KEY_BASE_URL = "base_url"
    private const val KEY_GEMINI_API_KEY = "gemini_api_key"
    
    // Default Staging URL (can be customized inside the app UI)
    private const val DEFAULT_BASE_URL = "https://stage.mein-seelenfunke.de/api/"

    fun init(context: Context) {
        this.context = context.applicationContext
        this.sharedPreferences = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        
        // Setup repositories
        authRepository = AuthRepository(this)
        aiRepository = AiRepository(this)
    }

    fun getGeminiApiKey(): String {
        return sharedPreferences.getString(KEY_GEMINI_API_KEY, "") ?: ""
    }

    fun saveGeminiApiKey(key: String) {
        sharedPreferences.edit().putString(KEY_GEMINI_API_KEY, key).apply()
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
        rebuildRetrofit()
    }

    fun getBaseUrl(): String {
        return sharedPreferences.getString(KEY_BASE_URL, DEFAULT_BASE_URL) ?: DEFAULT_BASE_URL
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
                    chain.proceed(requestBuilder.build())
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
}
