package de.meinseelenfunke.app.util

import android.content.Context
import android.content.SharedPreferences
import de.meinseelenfunke.app.data.api.VertexEphemeralResponse
import de.meinseelenfunke.app.di.ServiceLocator
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import kotlinx.coroutines.withTimeoutOrNull
import org.json.JSONObject
import java.net.URI
import java.net.URLDecoder
import java.util.Locale

data class CachedVertexBundle(
    val accessToken: String,
    val expiresAtEpochMs: Long,
    val projectId: String = VertexEphemeralResponse.DEFAULT_PROJECT_ID,
    val restModelsBaseUrl: String = VertexEphemeralResponse.DEFAULT_REST_MODELS_BASE_URL,
    val liveWsUrl: String = VertexEphemeralResponse.DEFAULT_LIVE_WS_URL,
    val liveModel: String = VertexEphemeralResponse.DEFAULT_LIVE_MODEL
) {
    fun isValid(nowEpochMs: Long = System.currentTimeMillis()): Boolean {
        return accessToken.length >= 16 &&
            !accessToken.startsWith("AIza") &&
            nowEpochMs < expiresAtEpochMs
    }
}

/**
 * Zero-Secret Client Cache for short-lived (~50 min) Google Cloud Vertex AI OAuth2 tokens (`ya29.*`).
 * Never stores any Service Account private key on the device; tokens are persisted strictly
 * AES-256-GCM encrypted (`enc_v1:`) via [ServiceLocator] and wiped on logout.
 */
object VertexClientTokenCache {

    const val PREF_KEY_VERTEX_BUNDLE = "vertex_ephemeral_bundle_v1"
    private const val PREFS_NAME = "seelenfunke_prefs"
    private const val SAFETY_MARGIN_SECONDS = 90L

    @Volatile
    private var memoryCache: CachedVertexBundle? = null

    fun store(
        dto: VertexEphemeralResponse,
        context: Context? = null,
        nowEpochMs: Long = System.currentTimeMillis()
    ): CachedVertexBundle? {
        val cleanToken = dto.access_token?.trim().orEmpty()
        if (cleanToken.length < 16 || cleanToken.startsWith("AIza")) {
            return null
        }

        val ttlSeconds = dto.expires_in_seconds.toLong().coerceIn(120L, 3540L)
        val effectiveTtlSeconds = (ttlSeconds - SAFETY_MARGIN_SECONDS).coerceAtLeast(60L)
        val expiresAt = nowEpochMs + (effectiveTtlSeconds * 1000L)

        val bundle = CachedVertexBundle(
            accessToken = cleanToken,
            expiresAtEpochMs = expiresAt,
            projectId = dto.project_id?.trim()?.takeIf { it.isNotEmpty() }
                ?: VertexEphemeralResponse.DEFAULT_PROJECT_ID,
            restModelsBaseUrl = dto.rest_models_base_url?.trim()?.takeIf { it.startsWith("https://") }
                ?: VertexEphemeralResponse.DEFAULT_REST_MODELS_BASE_URL,
            liveWsUrl = stripQueryTokenFromVertexWsUrl(dto.live_ws_url)
                ?: VertexEphemeralResponse.DEFAULT_LIVE_WS_URL,
            liveModel = dto.live_model?.trim()?.takeIf { it.startsWith("projects/") }
                ?: VertexEphemeralResponse.DEFAULT_LIVE_MODEL
        )

        memoryCache = bundle

        resolvePrefs(context)?.let { prefs ->
            val json = JSONObject().apply {
                put("access_token", bundle.accessToken)
                put("expires_at_epoch_ms", bundle.expiresAtEpochMs)
                put("project_id", bundle.projectId)
                put("rest_models_base_url", bundle.restModelsBaseUrl)
                put("live_ws_url", bundle.liveWsUrl)
                put("live_model", bundle.liveModel)
            }.toString()
            ServiceLocator.putEncryptedPref(prefs, PREF_KEY_VERTEX_BUNDLE, json)
        }

        return bundle
    }

    fun getValidCachedBundle(
        context: Context? = null,
        nowEpochMs: Long = System.currentTimeMillis()
    ): CachedVertexBundle? {
        memoryCache?.let { mem ->
            if (mem.isValid(nowEpochMs)) {
                return mem
            }
            memoryCache = null
        }

        val prefs = resolvePrefs(context) ?: return null
        val decrypted = ServiceLocator.getDecryptedPref(prefs, PREF_KEY_VERTEX_BUNDLE, null)
            ?.takeIf { it.isNotBlank() }
            ?: return null

        return try {
            val obj = JSONObject(decrypted)
            val candidate = CachedVertexBundle(
                accessToken = obj.optString("access_token", "").trim(),
                expiresAtEpochMs = obj.optLong("expires_at_epoch_ms", 0L),
                projectId = obj.optString("project_id", VertexEphemeralResponse.DEFAULT_PROJECT_ID)
                    .ifBlank { VertexEphemeralResponse.DEFAULT_PROJECT_ID },
                restModelsBaseUrl = obj.optString("rest_models_base_url", VertexEphemeralResponse.DEFAULT_REST_MODELS_BASE_URL)
                    .ifBlank { VertexEphemeralResponse.DEFAULT_REST_MODELS_BASE_URL },
                liveWsUrl = stripQueryTokenFromVertexWsUrl(obj.optString("live_ws_url", ""))
                    ?: VertexEphemeralResponse.DEFAULT_LIVE_WS_URL,
                liveModel = obj.optString("live_model", VertexEphemeralResponse.DEFAULT_LIVE_MODEL)
                    .ifBlank { VertexEphemeralResponse.DEFAULT_LIVE_MODEL }
            )
            if (candidate.isValid(nowEpochMs)) {
                memoryCache = candidate
                candidate
            } else {
                prefs.edit().remove(PREF_KEY_VERTEX_BUNDLE).apply()
                null
            }
        } catch (_: Exception) {
            null
        }
    }

    suspend fun getOrFetchValidBundle(
        context: Context? = null,
        forceRefresh: Boolean = false
    ): CachedVertexBundle? = withContext(Dispatchers.IO) {
        val appCtx = context?.applicationContext ?: try {
            ServiceLocator.context.applicationContext
        } catch (_: Throwable) {
            null
        }

        if (appCtx != null) {
            try {
                ServiceLocator.ensureInitialized(appCtx)
            } catch (_: Throwable) {
            }
        }

        if (!forceRefresh) {
            getValidCachedBundle(appCtx)?.let { return@withContext it }
        } else {
            clear(resolvePrefs(appCtx))
        }

        val sanctumToken = try {
            ServiceLocator.getAuthToken()
        } catch (_: Throwable) {
            null
        }
        if (sanctumToken.isNullOrBlank()) {
            return@withContext null
        }

        val fetched = withTimeoutOrNull(4500L) {
            try {
                ServiceLocator.getAiApi().getVertexEphemeral(refresh = if (forceRefresh) 1 else null)
            } catch (_: Throwable) {
                null
            }
        } ?: return@withContext null

        store(fetched, appCtx)
    }

    fun clear(prefs: SharedPreferences? = null) {
        memoryCache = null
        try {
            val targetPrefs = prefs ?: resolvePrefs(null)
            targetPrefs?.edit()?.remove(PREF_KEY_VERTEX_BUNDLE)?.apply()
        } catch (_: Throwable) {
        }
    }

    fun normalizeVertexRestModel(rawModel: String): String {
        var clean = rawModel.trim().lowercase(Locale.ROOT)
        if (clean.startsWith("models/")) clean = clean.removePrefix("models/")
        if (clean.startsWith("google/")) clean = clean.removePrefix("google/")
        return when (clean) {
            "",
            "gemini-2.5-flash",
            "gemini-3-flash-preview",
            "gemini-flash-latest",
            "gemini-2.0-flash",
            "gemini-2.0-flash-exp",
            "gemini-1.5-flash",
            "gemini-1.5-flash-8b" -> "gemini-3.5-flash"
            "gemini-2.5-flash-preview-tts" -> "gemini-2.5-flash-tts"
            else -> clean
        }
    }

    fun buildModelGenerateContentUrl(model: String, bundle: CachedVertexBundle? = null): String {
        val base = (bundle?.restModelsBaseUrl ?: VertexEphemeralResponse.DEFAULT_REST_MODELS_BASE_URL)
            .trim()
            .trimEnd('/')
        val resolvedModel = normalizeVertexRestModel(model)
        return "$base/$resolvedModel:generateContent"
    }

    fun extractAccessTokenFromWsUrl(wsUrl: String?): String? {
        if (wsUrl.isNullOrBlank()) return null
        return try {
            val uri = URI(wsUrl.trim())
            val rawQuery = uri.rawQuery ?: return null
            for (pair in rawQuery.split('&')) {
                val idx = pair.indexOf('=')
                if (idx <= 0) continue
                val key = URLDecoder.decode(pair.substring(0, idx), "UTF-8")
                if (key.equals("access_token", ignoreCase = true)) {
                    val value = URLDecoder.decode(pair.substring(idx + 1), "UTF-8").trim()
                    if (value.length >= 16 && !value.startsWith("AIza")) {
                        return value
                    }
                }
            }
            null
        } catch (_: Exception) {
            null
        }
    }

    fun stripQueryTokenFromVertexWsUrl(wsUrl: String?): String? {
        if (wsUrl.isNullOrBlank()) return null
        return try {
            val uri = URI(wsUrl.trim())
            val scheme = uri.scheme?.lowercase(Locale.ROOT) ?: return null
            val host = uri.host?.lowercase(Locale.ROOT) ?: return null
            if (scheme != "wss") return null
            if (host != "aiplatform.googleapis.com" && !host.endsWith("-aiplatform.googleapis.com")) {
                return null
            }
            val path = uri.rawPath?.takeIf { it.isNotBlank() }
                ?: "/ws/google.cloud.aiplatform.v1beta1.LlmBidiService/BidiGenerateContent"
            "wss://$host$path"
        } catch (_: Exception) {
            null
        }
    }

    private fun resolvePrefs(context: Context?): SharedPreferences? {
        val ctx = context?.applicationContext ?: try {
            ServiceLocator.context.applicationContext
        } catch (_: Throwable) {
            null
        } ?: return null
        return try {
            ctx.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        } catch (_: Throwable) {
            null
        }
    }
}
