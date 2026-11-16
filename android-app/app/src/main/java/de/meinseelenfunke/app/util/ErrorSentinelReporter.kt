package de.meinseelenfunke.app.util

import android.content.Context
import android.os.Build
import com.google.gson.Gson
import com.google.gson.reflect.TypeToken
import de.meinseelenfunke.app.di.ServiceLocator
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.launch
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import java.io.File
import java.io.PrintWriter
import java.io.StringWriter
import java.security.MessageDigest
import java.time.Instant
import java.util.Locale
import java.util.concurrent.ConcurrentHashMap
import java.util.concurrent.TimeUnit
import java.util.concurrent.atomic.AtomicBoolean

data class SentinelQueuedEvent(
    val platform: String = "android",
    val severity: String = "error",
    val component: String = "android_app",
    val title: String = "",
    val message: String = "",
    val stacktrace: String? = null,
    val fingerprint: String = "",
    val app_version: String = "${de.meinseelenfunke.app.BuildConfig.VERSION_NAME} (${de.meinseelenfunke.app.BuildConfig.VERSION_CODE})",
    val device: String = "${Build.MANUFACTURER} ${Build.MODEL}".trim(),
    val auto_healed: Boolean = false,
    val heal_summary: String? = null,
    val occurred_at: String = Instant.now().toString(),
    val context: Map<String, String> = emptyMap()
)

object ErrorSentinelReporter {
    private const val QUEUE_FILE_NAME = "error_sentinel_queue.json"
    private const val HISTORY_FILE_NAME = "error_sentinel_history.json"
    private const val MAX_QUEUE_SIZE = 50
    private const val MAX_HISTORY_SIZE = 100
    private val APP_VERSION_LABEL: String
        get() = "${de.meinseelenfunke.app.BuildConfig.VERSION_NAME} (${de.meinseelenfunke.app.BuildConfig.VERSION_CODE})"

    private val installed = AtomicBoolean(false)
    private val flushing = AtomicBoolean(false)
    private val queueLock = Any()
    private val gson = Gson()
    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.IO)
    private val recentFingerprints = ConcurrentHashMap<String, Long>()

    private val httpClient: OkHttpClient by lazy {
        OkHttpClient.Builder()
            .connectTimeout(10, TimeUnit.SECONDS)
            .writeTimeout(15, TimeUnit.SECONDS)
            .readTimeout(15, TimeUnit.SECONDS)
            .followRedirects(false)
            .followSslRedirects(false)
            .build()
    }

    fun install(context: Context) {
        val appCtx = context.applicationContext
        if (installed.compareAndSet(false, true)) {
            val previousHandler = Thread.getDefaultUncaughtExceptionHandler()
            Thread.setDefaultUncaughtExceptionHandler { thread, throwable ->
                try {
                    enqueueSynchronously(
                        appCtx = appCtx,
                        component = "crash.${thread.name.take(24)}",
                        title = "App-Absturz: ${throwable.javaClass.simpleName}",
                        message = throwable.message ?: "Unbehandelte Ausnahme",
                        throwable = throwable,
                        autoHealed = false,
                        healSummary = null,
                        severity = "error"
                    )
                } catch (_: Throwable) {
                } finally {
                    previousHandler?.uncaughtException(thread, throwable)
                }
            }
        }
        triggerAsyncFlush(appCtx)
    }

    fun isBenignCancellation(throwable: Throwable?, message: String? = null): Boolean {
        var cur: Throwable? = throwable
        var depth = 0
        while (cur != null && depth < 6) {
            if (cur is java.util.concurrent.CancellationException) {
                return true
            }
            val cls = cur.javaClass.name.lowercase(Locale.ROOT)
            if (cls.contains("cancellationexception") || cls.contains("mutationinterruptedexception")) {
                return true
            }
            cur = cur.cause
            depth++
        }
        val msgLower = (message ?: "").lowercase(Locale.ROOT)
        return msgLower.contains("leftcompositioncancellationexception") ||
            msgLower.contains("standalonecoroutine was cancelled") ||
            msgLower.contains("job was cancelled")
    }

    private fun isTransientSslTransportAbort(t: Throwable): Boolean {
        if (t is javax.net.ssl.SSLHandshakeException ||
            t is javax.net.ssl.SSLPeerUnverifiedException
        ) {
            return false
        }
        if (t !is javax.net.ssl.SSLException) return false
        val msg = (t.message ?: "").lowercase(Locale.ROOT)
        return msg.contains("connection reset") ||
            msg.contains("software caused connection abort") ||
            msg.contains("connection abort") ||
            msg.contains("broken pipe") ||
            msg.contains("socket closed") ||
            msg.contains("i/o error during system call") ||
            msg.contains("timed out")
    }

    fun isTransientNetworkOffline(throwable: Throwable?): Boolean {
        var cur: Throwable? = throwable
        var depth = 0
        while (cur != null && depth < 6) {
            if (cur is javax.net.ssl.SSLHandshakeException ||
                cur is javax.net.ssl.SSLPeerUnverifiedException
            ) {
                return false
            }
            if (cur is java.net.UnknownHostException ||
                cur is java.net.ConnectException ||
                cur is java.net.NoRouteToHostException ||
                cur is java.net.SocketTimeoutException ||
                cur is java.net.SocketException ||
                isTransientSslTransportAbort(cur)
            ) {
                return true
            }
            cur = cur.cause
            depth++
        }
        return false
    }

    fun isTransientWebSocketDisconnect(throwable: Throwable?, httpStatus: Int? = null): Boolean {
        if (httpStatus != null && httpStatus >= 400) return false
        var cur: Throwable? = throwable
        var depth = 0
        while (cur != null && depth < 6) {
            if (cur is javax.net.ssl.SSLHandshakeException ||
                cur is javax.net.ssl.SSLPeerUnverifiedException
            ) {
                return false
            }
            if (cur is java.io.EOFException ||
                cur is java.net.SocketException ||
                cur is java.net.SocketTimeoutException ||
                cur is java.net.UnknownHostException ||
                cur is java.net.ConnectException ||
                cur is java.net.NoRouteToHostException ||
                isTransientSslTransportAbort(cur)
            ) {
                return true
            }
            cur = cur.cause
            depth++
        }
        return false
    }

    fun captureError(
        context: Context?,
        tag: String,
        message: String,
        throwable: Throwable? = null,
        extraContext: Map<String, String> = emptyMap()
    ) {
        if (isBenignCancellation(throwable, message)) return
        val cleanTag = tag.trim().ifEmpty { "android_app" }
        val httpStatus = extraContext["http_status"]?.toIntOrNull()
        if (cleanTag.endsWith("_ws", ignoreCase = true) && isTransientWebSocketDisconnect(throwable, httpStatus)) {
            return
        }
        val appCtx = resolveContext(context) ?: return
        val shortTitle = if (throwable != null) {
            "$cleanTag: ${throwable.javaClass.simpleName}"
        } else {
            "$cleanTag: ${message.lineSequence().firstOrNull()?.take(90) ?: "Fehler"}"
        }
        val transientOffline = isTransientNetworkOffline(throwable)
        val added = enqueueSynchronously(
            appCtx = appCtx,
            component = cleanTag,
            title = shortTitle,
            message = message,
            throwable = throwable,
            autoHealed = transientOffline,
            healSummary = if (transientOffline) "Offline-Modus / lokaler Cache übernahm bei kurzzeitigem Funkloch." else null,
            severity = if (transientOffline) "warning" else "error",
            extraContext = extraContext
        )
        if (added) {
            triggerAsyncFlush(appCtx)
        }
    }

    fun captureAutoHeal(
        context: Context?,
        component: String,
        title: String,
        message: String,
        healSummary: String,
        extraContext: Map<String, String> = emptyMap()
    ) {
        val appCtx = resolveContext(context) ?: return
        val added = enqueueSynchronously(
            appCtx = appCtx,
            component = component,
            title = title,
            message = message,
            throwable = null,
            autoHealed = true,
            healSummary = healSummary,
            severity = "warning",
            extraContext = extraContext
        )
        if (added) {
            triggerAsyncFlush(appCtx)
        }
    }

    fun scrubSecrets(raw: String?): String {
        if (raw.isNullOrEmpty()) return ""
        var s = raw
        s = s.replace(Regex("(?i)(Bearer\\s+)[A-Za-z0-9\\-._~+/=|]+"), "$1[REDACTED]")
        s = s.replace(Regex("enc_v1:[A-Za-z0-9+/=:]+"), "enc_v1:[REDACTED]")
        s = s.replace(Regex("AIza[A-Za-z0-9\\-_]{16,}"), "AIza[REDACTED]")
        s = s.replace(Regex("ya29\\.[A-Za-z0-9\\-._]{16,}"), "ya29.[REDACTED]")
        s = s.replace(Regex("(?i)([?&](?:api[_-]?key|key|token|access_token|password|passwd|secret)=)[^&\\s\"']+"), "$1[REDACTED]")
        s = s.replace(Regex("(?i)(\\b(?:api[_-]?key|key|token|password|passwd|secret|authorization)[\"']?\\s*[:=]\\s*[\"']?)([^\\s\"'&,;}{]+)"), "$1[REDACTED]")
        s = s.replace(Regex("\\b\\d+\\|[A-Za-z0-9]{16,}"), "[REDACTED]")
        return if (s.length > 3800) s.take(3800) else s
    }

    fun clearQueue(context: Context?) {
        recentFingerprints.clear()
        val appCtx = resolveContext(context) ?: return
        synchronized(queueLock) {
            try {
                val file = File(appCtx.filesDir, QUEUE_FILE_NAME)
                if (file.exists()) {
                    file.delete()
                }
            } catch (_: Throwable) {
            }
        }
    }

    fun computeFingerprint(component: String, title: String): String {
        val raw = "android|${component.trim().lowercase(Locale.ROOT)}|${title.trim().lowercase(Locale.ROOT)}"
        val digest = MessageDigest.getInstance("SHA-256").digest(raw.toByteArray(Charsets.UTF_8))
        return digest.joinToString("") { "%02x".format(it) }
    }

    private fun resolveContext(ctx: Context?): Context? {
        if (ctx != null) return ctx.applicationContext
        return try {
            ServiceLocator.context.applicationContext
        } catch (_: Throwable) {
            null
        }
    }

    private fun formatStackTrace(t: Throwable?): String? {
        if (t == null) return null
        return try {
            val sw = StringWriter()
            t.printStackTrace(PrintWriter(sw))
            scrubSecrets(sw.toString())
        } catch (_: Throwable) {
            null
        }
    }

    private fun enqueueSynchronously(
        appCtx: Context,
        component: String,
        title: String,
        message: String,
        throwable: Throwable?,
        autoHealed: Boolean,
        healSummary: String?,
        severity: String,
        extraContext: Map<String, String> = emptyMap()
    ): Boolean {
        val safeComp = scrubSecrets(component).take(100)
        val safeTitle = scrubSecrets(title).take(220)
        if (safeTitle.isBlank()) return false

        val fp = computeFingerprint(safeComp, safeTitle)
        val throttleKey = if (autoHealed) "$fp:healed" else fp
        val nowMs = System.currentTimeMillis()
        val prev = recentFingerprints[throttleKey]
        if (prev != null && (nowMs - prev) < 180_000L) {
            return false
        }
        recentFingerprints[throttleKey] = nowMs

        val mergedCtx = LinkedHashMap<String, String>().apply {
            put("sdk_int", Build.VERSION.SDK_INT.toString())
            put("model", Build.MODEL)
            for ((k, v) in extraContext) {
                val ck = scrubSecrets(k).take(60)
                if (ck.isNotBlank()) {
                    put(ck, scrubSecrets(v).take(300))
                }
            }
        }

        val event = SentinelQueuedEvent(
            platform = "android",
            severity = if (autoHealed) "warning" else severity,
            component = safeComp,
            title = safeTitle,
            message = scrubSecrets(message).ifBlank { safeTitle },
            stacktrace = formatStackTrace(throwable),
            fingerprint = fp,
            app_version = APP_VERSION_LABEL,
            device = "${Build.MANUFACTURER} ${Build.MODEL} (SDK ${Build.VERSION.SDK_INT})".trim(),
            auto_healed = autoHealed,
            heal_summary = healSummary?.let { scrubSecrets(it) },
            occurred_at = Instant.now().toString(),
            context = mergedCtx
        )

        synchronized(queueLock) {
            appendHistoryUnsafe(appCtx, event)
            val list = loadQueueUnsafe(appCtx)
            list.removeAll { it.fingerprint == event.fingerprint && it.auto_healed == event.auto_healed }
            list.add(event)
            while (list.size > MAX_QUEUE_SIZE) {
                list.removeAt(0)
            }
            saveQueueUnsafe(appCtx, list)
        }
        return true
    }

    fun getRecentHistory(context: Context?, limit: Int = 30): List<SentinelQueuedEvent> {
        val appCtx = resolveContext(context) ?: return emptyList()
        val capped = limit.coerceIn(1, MAX_HISTORY_SIZE)
        return synchronized(queueLock) {
            val all = loadJsonListUnsafe(appCtx, HISTORY_FILE_NAME)
            all.takeLast(capped).asReversed()
        }
    }

    private fun appendHistoryUnsafe(appCtx: Context, event: SentinelQueuedEvent) {
        val hist = loadJsonListUnsafe(appCtx, HISTORY_FILE_NAME)
        hist.add(event)
        while (hist.size > MAX_HISTORY_SIZE) {
            hist.removeAt(0)
        }
        saveJsonListUnsafe(appCtx, HISTORY_FILE_NAME, hist)
    }

    fun triggerAsyncFlush(context: Context?) {
        val appCtx = resolveContext(context) ?: return
        scope.launch {
            flushQueueNow(appCtx)
        }
    }

    private fun flushQueueNow(appCtx: Context) {
        if (!flushing.compareAndSet(false, true)) return
        try {
            val batch: List<SentinelQueuedEvent> = synchronized(queueLock) {
                loadQueueUnsafe(appCtx).take(20)
            }
            if (batch.isEmpty()) return

            val token = try { ServiceLocator.getAuthToken() } catch (_: Throwable) { null }
            if (token.isNullOrBlank()) return

            val baseUrl = try { ServiceLocator.getBaseUrl() } catch (_: Throwable) { return }
            val endpoint = "${baseUrl.trimEnd('/')}/system/error-ingest"
            if (!ServiceLocator.isAllowedApiRequestUrl(endpoint) || !ServiceLocator.shouldAttachBearerToken(endpoint)) return

            val bodyJson = gson.toJson(mapOf("events" to batch))
            val req = Request.Builder()
                .url(endpoint)
                .header("Authorization", "Bearer $token")
                .header("Accept", "application/json")
                .post(bodyJson.toRequestBody("application/json; charset=utf-8".toMediaType()))
                .build()

            httpClient.newCall(req).execute().use { resp ->
                if (resp.isSuccessful) {
                    val sentKeys = batch.map { "${it.fingerprint}|${it.occurred_at}" }.toSet()
                    synchronized(queueLock) {
                        val current = loadQueueUnsafe(appCtx)
                        current.removeAll { "${it.fingerprint}|${it.occurred_at}" in sentKeys }
                        saveQueueUnsafe(appCtx, current)
                    }
                }
            }
        } catch (_: Throwable) {
        } finally {
            flushing.set(false)
        }
    }

    private fun loadQueueUnsafe(appCtx: Context): MutableList<SentinelQueuedEvent> =
        loadJsonListUnsafe(appCtx, QUEUE_FILE_NAME)

    private fun saveQueueUnsafe(appCtx: Context, list: List<SentinelQueuedEvent>) =
        saveJsonListUnsafe(appCtx, QUEUE_FILE_NAME, list)

    private fun loadJsonListUnsafe(appCtx: Context, fileName: String): MutableList<SentinelQueuedEvent> {
        return try {
            val file = File(appCtx.filesDir, fileName)
            if (!file.exists()) return mutableListOf()
            val raw = file.readText(Charsets.UTF_8)
            val json = ServiceLocator.decryptPayloadFromPrefs(raw) ?: return mutableListOf()
            val type = object : TypeToken<MutableList<SentinelQueuedEvent>>() {}.type
            gson.fromJson<MutableList<SentinelQueuedEvent>>(json, type) ?: mutableListOf()
        } catch (_: Throwable) {
            mutableListOf()
        }
    }

    private fun saveJsonListUnsafe(appCtx: Context, fileName: String, list: List<SentinelQueuedEvent>) {
        try {
            val file = File(appCtx.filesDir, fileName)
            if (list.isEmpty()) {
                if (file.exists()) file.delete()
                return
            }
            val encrypted = ServiceLocator.encryptPayloadForPrefs(gson.toJson(list))
            file.writeText(encrypted, Charsets.UTF_8)
        } catch (_: Throwable) {
        }
    }
}
