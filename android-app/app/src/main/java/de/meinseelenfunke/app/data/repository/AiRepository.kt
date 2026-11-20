package de.meinseelenfunke.app.data.repository

import android.content.Context
import android.net.Uri
import com.google.ai.client.generativeai.GenerativeModel
import com.google.ai.client.generativeai.type.content
import de.meinseelenfunke.app.data.api.ChatMessage
import de.meinseelenfunke.app.data.api.ChatRequest
import de.meinseelenfunke.app.data.api.ChatResponse
import de.meinseelenfunke.app.data.api.CameraSnapshotResponse
import de.meinseelenfunke.app.data.api.UltimateCommandResponse
import de.meinseelenfunke.app.di.ServiceLocator
import de.meinseelenfunke.app.util.AppLogger
import de.meinseelenfunke.app.util.ImageUtils
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import okhttp3.MediaType.Companion.toMediaTypeOrNull
import okhttp3.MultipartBody
import okhttp3.RequestBody.Companion.toRequestBody

class AiRepository(private val serviceLocator: ServiceLocator) {

    // Cache local generative model to avoid rebuilding
    private var localGenerativeModel: GenerativeModel? = null
    private var lastApiKey: String? = null

    suspend fun uploadSnapshot(
        context: Context,
        uriString: String,
        fileName: String
    ): Result<CameraSnapshotResponse> = withContext(Dispatchers.IO) {
        return@withContext try {
            val uri = Uri.parse(uriString)
            val bytes = ImageUtils.compressImageUri(context, uri) ?: throw Exception("Bild konnte nicht komprimiert werden.")
            val mediaType = "image/jpeg".toMediaTypeOrNull()
            val requestFile = bytes.toRequestBody(mediaType)
            val part = MultipartBody.Part.createFormData("image", fileName, requestFile)
            val response = serviceLocator.getAiApi().uploadSnapshot(part)
            Result.success(response)
        } catch (e: Exception) {
            if (e is kotlinx.coroutines.CancellationException) throw e
            AppLogger.error(serviceLocator.context, "AiRepo", "uploadSnapshot failed: uri=$uriString", e)
            Result.failure(e)
        }
    }

    data class ChatHistoryBundle(
        val sessionId: String?,
        val messages: List<ChatMessage>
    )

    companion object {
        const val LEGACY_ERROR_PLACEHOLDER = "[Fehler beim Laden der Antwort]"

        fun isRetryableChatFailure(throwable: Throwable?): Boolean {
            if (throwable == null) return false
            if (throwable is kotlinx.coroutines.CancellationException) return false
            if (throwable is javax.net.ssl.SSLHandshakeException ||
                throwable is javax.net.ssl.SSLPeerUnverifiedException
            ) {
                return false
            }
            if (throwable is retrofit2.HttpException) {
                return throwable.code() in setOf(408, 429, 500, 502, 503, 504)
            }
            return throwable is java.io.IOException ||
                de.meinseelenfunke.app.util.ErrorSentinelReporter.isTransientNetworkOffline(throwable)
        }

        /**
         * Prüft nach einem mobilen Verbindungsabbruch anhand der Server-Historie,
         * ob die KI-Antwort für [expectedPrompt] bereits vollständig gespeichert wurde.
         */
        fun reconcileRecoveredAssistantReply(
            historyItems: List<ChatMessage>,
            expectedPrompt: String
        ): String? {
            val cleanPrompt = expectedPrompt
                .substringBefore("\n[SYSTEM_IMAGE]:")
                .substringBefore("\n\n[Anhang:")
                .trim()
            if (cleanPrompt.isEmpty() || historyItems.size < 2) return null

            for (i in historyItems.lastIndex downTo 1) {
                val assistantCandidate = historyItems[i]
                val userCandidate = historyItems[i - 1]
                val userText = userCandidate.content
                    .substringBefore("\n[Bild]")
                    .trim()
                if (assistantCandidate.role == "assistant" &&
                    userCandidate.role == "user" &&
                    userText == cleanPrompt &&
                    assistantCandidate.content.isNotBlank() &&
                    assistantCandidate.content.trim() != LEGACY_ERROR_PLACEHOLDER
                ) {
                    return assistantCandidate.content
                }
            }
            return null
        }
    }

    // Send a message to the Laravel backend remote agents with idempotent transport recovery
    suspend fun sendChatMessage(
        prompt: String,
        history: List<ChatMessage>,
        agentId: String? = null,
        chatSessionId: String? = null,
        clientMessageId: String = java.util.UUID.randomUUID().toString(),
        skipTts: Boolean? = null
    ): Result<ChatResponse> = withContext(Dispatchers.IO) {
        val retryDelaysMs = longArrayOf(0L, 650L)
        var lastException: Exception? = null

        for (attempt in retryDelaysMs.indices) {
            if (retryDelaysMs[attempt] > 0L) {
                kotlinx.coroutines.delay(retryDelaysMs[attempt])
            }
            try {
                val response = serviceLocator.getAiApi().chat(
                    ChatRequest(
                        prompt = prompt,
                        history = history,
                        agent_id = agentId,
                        chat_session_id = chatSessionId,
                        client_message_id = clientMessageId,
                        skip_tts = skipTts
                    )
                )
                if (response.status == "error") {
                    val serverMsg = response.message?.takeIf { it.isNotBlank() }
                        ?: "Der KI-Server hat einen Fehler gemeldet."
                    throw IllegalStateException(serverMsg)
                }
                if (attempt > 0 && lastException != null) {
                    de.meinseelenfunke.app.util.ErrorSentinelReporter.captureAutoHeal(
                        context = serviceLocator.context,
                        component = "ai_chat_recovery",
                        title = "KI-Chat nach Verbindungs-Retry wiederhergestellt",
                        message = lastException.message ?: lastException.javaClass.simpleName,
                        healSummary = "Idempotenter Retry (client_message_id) lieferte die Antwort ohne Datenverlust.",
                        extraContext = mapOf(
                            "attempt" to (attempt + 1).toString(),
                            "deduplicated" to (response.deduplicated == true).toString()
                        )
                    )
                }
                return@withContext Result.success(response)
            } catch (e: Exception) {
                if (e is kotlinx.coroutines.CancellationException) throw e
                lastException = e
                if (e is java.io.IOException) {
                    serviceLocator.evictConnectionPool()
                }
                if (!isRetryableChatFailure(e)) {
                    break
                }
            }
        }

        // Letzte Auffang-Stufe bei Socket-Abbruch/Timeout: Prüfe Server-Historie, falls Antwort im Hintergrund fertig wurde
        if (lastException is java.io.IOException) {
            try {
                kotlinx.coroutines.delay(500L)
                val reconciledBundle = getChatHistoryBundle(sessionId = chatSessionId, limit = 6).getOrNull()
                val recoveredText = reconciledBundle?.let {
                    reconcileRecoveredAssistantReply(it.messages, prompt)
                }
                if (!recoveredText.isNullOrBlank()) {
                    de.meinseelenfunke.app.util.ErrorSentinelReporter.captureAutoHeal(
                        context = serviceLocator.context,
                        component = "ai_chat_history_reconcile",
                        title = "KI-Chat-Antwort aus Server-Historie synchronisiert",
                        message = lastException.message ?: lastException.javaClass.simpleName,
                        healSummary = "Nach mobilem Socket-Abbruch wurde die fertige Antwort verlustfrei aus AiChatMemory geladen."
                    )
                    return@withContext Result.success(
                        ChatResponse(
                            status = "success",
                            chat_session_id = reconciledBundle.sessionId ?: chatSessionId,
                            agent_name = null,
                            response = recoveredText,
                            tts_enabled = false,
                            deduplicated = true,
                            audio = null
                        )
                    )
                }
            } catch (reconcileEx: Exception) {
                if (reconcileEx is kotlinx.coroutines.CancellationException) throw reconcileEx
            }
        }

        val finalEx = lastException ?: Exception("Unbekannter Verbindungsfehler im KI-Chat.")
        val httpCode = (finalEx as? retrofit2.HttpException)?.code()?.toString()
        val extra = buildMap {
            put("agent_id", agentId ?: "default")
            put("exception_class", finalEx.javaClass.simpleName)
            if (httpCode != null) put("http_status", httpCode)
        }
        AppLogger.error(
            context = serviceLocator.context,
            tag = "AiRepo",
            message = "sendChatMessage failed: agentId=${agentId ?: "default"} (${finalEx.javaClass.simpleName})",
            throwable = finalEx,
            extraContext = extra,
            allowOfflineAutoHeal = false
        )
        return@withContext Result.failure(finalEx)
    }

    suspend fun getChatHistoryBundle(sessionId: String? = null, limit: Int = 30): Result<ChatHistoryBundle> = withContext(Dispatchers.IO) {
        var lastErr: Exception? = null
        val delays = longArrayOf(0L, 350L)
        for (attempt in delays.indices) {
            if (delays[attempt] > 0L) kotlinx.coroutines.delay(delays[attempt])
            try {
                val response = serviceLocator.getAiApi().getChatHistory(sessionId, limit)
                if (response.status == "success") {
                    val list = (response.messages ?: emptyList()).map { item ->
                        ChatMessage(
                            role = item.role,
                            content = item.content
                        )
                    }
                    return@withContext Result.success(
                        ChatHistoryBundle(
                            sessionId = response.session_id,
                            messages = list
                        )
                    )
                }
                return@withContext Result.success(ChatHistoryBundle(sessionId = response.session_id, messages = emptyList()))
            } catch (e: Exception) {
                if (e is kotlinx.coroutines.CancellationException) throw e
                lastErr = e
                if (e is java.io.IOException) {
                    serviceLocator.evictConnectionPool()
                } else {
                    break
                }
            }
        }
        val ex = lastErr ?: Exception("Chat-Historie konnte nicht geladen werden.")
        AppLogger.error(serviceLocator.context, "AiRepo", "getChatHistory failed", ex)
        return@withContext Result.failure(ex)
    }

    suspend fun getChatHistory(sessionId: String? = null, limit: Int = 30): Result<List<ChatMessage>> {
        return getChatHistoryBundle(sessionId = sessionId, limit = limit).map { it.messages }
    }

    // Run a prompt locally on-device using Google Gemini SDK (Gemini Nano / Flash)
    suspend fun generateLocalResponse(
        prompt: String,
        apiKey: String,
        modelName: String = "gemini-1.5-flash"
    ): Result<String> = withContext(Dispatchers.IO) {
        try {
            if (apiKey.isBlank()) {
                return@withContext Result.failure(Exception("Bitte trage einen gültigen Gemini API Key in den Einstellungen ein."))
            }

            // Initialize or reuse model
            if (localGenerativeModel == null || lastApiKey != apiKey) {
                lastApiKey = apiKey
                localGenerativeModel = GenerativeModel(
                    modelName = modelName,
                    apiKey = apiKey
                )
            }

            val response = localGenerativeModel!!.generateContent(
                content {
                    text(prompt)
                }
            )

            val text = response.text
            if (text != null) {
                Result.success(text)
            } else {
                Result.failure(Exception("Keine Antwort vom lokalen Gemini Modell erhalten."))
            }
        } catch (e: Exception) {
            if (e is kotlinx.coroutines.CancellationException) throw e
            AppLogger.error(serviceLocator.context, "AiRepo", "generateLocalResponse failed: modelName=$modelName", e)
            Result.failure(e)
        }
    }

    suspend fun getLiveCredentials(
        agentId: String? = null,
        chatSessionId: String? = null,
        client: String? = null,
        fallback: String? = null
    ): Result<de.meinseelenfunke.app.data.api.LiveCredentialsResponse> {
        val retryDelaysMs = longArrayOf(0L, 420L, 950L)
        var lastErr: Exception? = null
        for (attempt in retryDelaysMs.indices) {
            if (retryDelaysMs[attempt] > 0L) {
                kotlinx.coroutines.delay(retryDelaysMs[attempt])
            }
            try {
                val response = serviceLocator.getAiApi().getLiveCredentials(
                    agentId = agentId,
                    chatSessionId = chatSessionId,
                    client = client,
                    fallback = fallback
                )
                response.vertex_ephemeral?.let { bundle ->
                    de.meinseelenfunke.app.util.VertexClientTokenCache.store(bundle, serviceLocator.context)
                }
                if (attempt > 0 && lastErr != null) {
                    de.meinseelenfunke.app.util.ErrorSentinelReporter.captureAutoHeal(
                        context = serviceLocator.context,
                        component = "ai_live_credentials",
                        title = "Live-Credentials nach Netzwerk-Retry #${attempt + 1} geladen",
                        message = lastErr.message ?: "Transiente Netzwerkverzögerung",
                        healSummary = "Automatischer Retry #${attempt + 1} hat Live-Credentials erfolgreich vom Server geladen.",
                        extraContext = mapOf(
                            "attempt" to (attempt + 1).toString(),
                            "client" to (client ?: "default")
                        )
                    )
                }
                return Result.success(response)
            } catch (e: Exception) {
                if (e is kotlinx.coroutines.CancellationException) throw e
                lastErr = e
                val isAuthOrPerm = e is retrofit2.HttpException && (e.code() == 401 || e.code() == 403)
                if (isAuthOrPerm) break
                if (e is java.io.IOException) {
                    serviceLocator.evictConnectionPool()
                }
            }
        }
        val finalEx = lastErr ?: Exception("Live-Credentials konnten nicht geladen werden.")
        AppLogger.error(serviceLocator.context, "AiRepo", "getLiveCredentials failed: agentId=$agentId, client=$client", finalEx)
        return Result.failure(finalEx)
    }

    suspend fun executeTool(functionName: String, args: com.google.gson.JsonElement?, sessionId: String? = null): Result<de.meinseelenfunke.app.data.api.ExecuteToolResponse> {
        return try {
            val response = serviceLocator.getAiApi().executeTool(
                de.meinseelenfunke.app.data.api.ExecuteToolRequest(function = functionName, args = args, session_id = sessionId)
            )
            Result.success(response)
        } catch (e: Exception) {
            if (e is kotlinx.coroutines.CancellationException) throw e
            AppLogger.error(
                context = serviceLocator.context,
                tag = "AiRepo",
                message = "executeTool failed: functionName=$functionName, sessionId=$sessionId",
                throwable = e,
                allowOfflineAutoHeal = false
            )
            Result.failure(e)
        }
    }

    suspend fun getAgents(): Result<List<de.meinseelenfunke.app.data.api.AiAgent>> = withContext(Dispatchers.IO) {
        var lastErr: Exception? = null
        val delays = longArrayOf(0L, 350L)
        for (attempt in delays.indices) {
            if (delays[attempt] > 0L) kotlinx.coroutines.delay(delays[attempt])
            try {
                val response = serviceLocator.getAiApi().getAgents()
                if (response.success == "success" || response.success == "true") {
                    return@withContext Result.success(response.data)
                } else {
                    return@withContext Result.failure(Exception("Fehler beim Laden der Agenten."))
                }
            } catch (e: Exception) {
                if (e is kotlinx.coroutines.CancellationException) throw e
                lastErr = e
                if (e is java.io.IOException) {
                    serviceLocator.evictConnectionPool()
                } else {
                    break
                }
            }
        }
        val ex = lastErr ?: Exception("Fehler beim Laden der Agenten.")
        AppLogger.error(serviceLocator.context, "AiRepo", "getAgents failed", ex)
        return@withContext Result.failure(ex)
    }

    suspend fun getUltimateCommand(): Result<UltimateCommandResponse> {
        return try {
            val response = serviceLocator.getAiApi().getUltimateCommand()
            Result.success(response)
        } catch (e: Exception) {
            if (e is kotlinx.coroutines.CancellationException) throw e
            AppLogger.error(serviceLocator.context, "AiRepo", "getUltimateCommand failed", e)
            Result.failure(e)
        }
    }
}

