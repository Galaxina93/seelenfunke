package de.meinseelenfunke.app.services

import android.Manifest
import android.content.Context
import android.content.pm.PackageManager
import android.media.AudioAttributes
import android.media.AudioDeviceInfo
import android.media.AudioFormat
import android.media.AudioManager
import android.media.AudioRecord
import android.media.AudioTrack
import android.media.MediaRecorder
import android.media.audiofx.AcousticEchoCanceler
import android.media.audiofx.NoiseSuppressor
import android.net.Uri
import android.os.Build
import android.os.SystemClock
import android.speech.tts.TextToSpeech
import android.speech.tts.UtteranceProgressListener
import android.util.Base64
import androidx.core.content.ContextCompat
import com.google.gson.Gson
import com.google.gson.JsonArray
import com.google.gson.JsonObject
import de.meinseelenfunke.app.data.api.LiveCredentialsResponse
import de.meinseelenfunke.app.di.ServiceLocator
import de.meinseelenfunke.app.ui.screens.FunkiraLiveViewModel
import de.meinseelenfunke.app.util.ErrorSentinelReporter
import de.meinseelenfunke.app.util.NavigationBridge
import de.meinseelenfunke.app.util.PhoneConciergeManager
import kotlinx.coroutines.CompletableDeferred
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.Job
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.cancel
import kotlinx.coroutines.delay
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch
import kotlinx.coroutines.withTimeoutOrNull
import okhttp3.Dns
import okhttp3.OkHttpClient
import okhttp3.Protocol
import okhttp3.Request
import okhttp3.Response
import okhttp3.WebSocket
import okhttp3.WebSocketListener
import okio.ByteString
import java.net.Inet4Address
import java.net.InetAddress
import java.net.URI
import java.util.Locale
import java.util.UUID
import java.util.concurrent.TimeUnit
import java.util.concurrent.atomic.AtomicBoolean
import java.util.concurrent.atomic.AtomicLong
import java.util.concurrent.atomic.AtomicReference
import kotlin.math.max
import kotlin.math.min

data class SecretarySessionResult(
    val summary: String,
    val urgency: String,
    val callbackRequested: Boolean,
    val usedLiveAi: Boolean
)

/**
 * Standalone, low-latency Gemini Live Phone Secretary Engine running directly on the Android smartphone.
 * Works 100% independently of the workshop Mini-PC (e.g. during night/weekend sleep or on the go).
 */
object StandalonePhoneSecretaryEngine {

    private data class WarmCredentials(
        val fetchedAtUptimeMs: Long,
        val response: LiveCredentialsResponse
    )

    private data class WsCandidate(
        val label: String,
        val url: String,
        val isDirectGoogle: Boolean,
        val originUrl: String,
        val voiceName: String
    )

    private const val WARM_CACHE_TTL_MS = 75_000L
    private const val ECHO_TAIL_GUARD_MS = 320L

    @Volatile
    private var preWarmed: WarmCredentials? = null

    private val gson = Gson()

    private val wsHttpClient: OkHttpClient by lazy {
        OkHttpClient.Builder()
            .protocols(listOf(Protocol.HTTP_1_1))
            .connectTimeout(5, TimeUnit.SECONDS)
            .readTimeout(0, TimeUnit.MILLISECONDS)
            .pingInterval(12, TimeUnit.SECONDS)
            .dns(object : Dns {
                override fun lookup(hostname: String): List<InetAddress> {
                    return Dns.SYSTEM.lookup(hostname).sortedBy { if (it is Inet4Address) 0 else 1 }
                }
            })
            .build()
    }

    /**
     * Pre-warms Gemini Live credentials while the phone is still ringing so WebSocket handshake
     * starts in 0 ms the instant the call is answered.
     */
    suspend fun preWarmCredentials(context: Context) {
        try {
            ServiceLocator.ensureInitialized(context.applicationContext)
            val existing = preWarmed
            if (existing != null && (SystemClock.uptimeMillis() - existing.fetchedAtUptimeMs) < WARM_CACHE_TTL_MS) {
                return
            }
            if (ServiceLocator.getAuthToken().isNullOrBlank()) return
            val res = ServiceLocator.aiRepository.getLiveCredentials(
                agentId = null,
                chatSessionId = "android_secretary_warm_${UUID.randomUUID()}",
                client = "android_secretary",
                fallback = "direct"
            )
            res.getOrNull()?.let { creds ->
                preWarmed = WarmCredentials(SystemClock.uptimeMillis(), creds)
            }
        } catch (_: Exception) {
        }
    }

    fun buildSecretarySystemInstruction(
        callerName: String,
        greetingName: String,
        relationType: String?,
        contactInstructions: String?
    ): String {
        val cleanCaller = callerName.trim().ifBlank { "Vertrauter Kontakt" }
        val cleanGreeting = greetingName.trim().ifBlank { cleanCaller }
        val cleanRelation = relationType?.trim().orEmpty()
        val extraHint = contactInstructions?.trim()?.take(500).orEmpty()

        val relationLine = if (cleanRelation.isNotBlank()) {
            "- Beziehung zu Alina: $cleanRelation.\n"
        } else {
            ""
        }
        val extraLine = if (extraHint.isNotBlank()) {
            "- Persönlicher Hinweis zum Kontakt: $extraHint\n"
        } else {
            ""
        }

        return buildString {
            append("STRIKTE SPRACHREGEL & SEKRETÄRINNEN-ROLLE (HÖCHSTE PRIORITÄT):\n")
            append("- Du sprichst ZU 100% AUSSCHLIESSLICH AUF DEUTSCH. Gib niemals englische Wörter, Gedanken oder Systemmeldungen aus.\n")
            append("- Du bist Funkira, Alinas herzliche, schnelle, aufmerksame persönliche Sekretärin und KI-Assistentin von Mein-Seelenfunke.\n")
            append("- Du gehst gerade direkt an Alinas Smartphone für einen vertrauten Whitelist-Kontakt ans Telefon.\n")
            append("- Anrufer: $cleanCaller (persönliche Anrede: \"$cleanGreeting\").\n")
            append(relationLine)
            append(extraLine)
            append("- Deine Aufgabe:\n")
            append("  1. Begrüße $cleanGreeting sofort freundlich, warmherzig und natürlich beim Namen als Funkira, Alinas Assistentin. Sag kurz, dass Alina gerade nicht persönlich ans Telefon gehen kann, du aber sehr gerne alles für sie aufnimmst.\n")
            append("  2. Höre aufmerksam zu, worum es geht. Stelle höchstens EINE kurze, freundliche Rückfrage, falls etwas Wichtiges unklar ist.\n")
            append("  3. Sobald das Anliegen klar ist, rufe SOFORT das Tool `phone_secretary_finish` mit einer präzisen deutschen Zusammenfassung (`summary`), der Dringlichkeit (`urgency`: hoch, mittel oder niedrig) und `callback_requested` auf.\n")
            append("  4. Vermittle $cleanGreeting im selben Atemzug bzw. direkt nach dem Tool-Aufruf super nett, herzlich und verbindlich, dass du alles genau notiert hast und Alina die Nachricht sofort bekommen wird, und verabschiede dich freundlich.\n")
            append("- Halte deine Sätze kurz, lebendig und telefonfreundlich (jeweils 1 bis 2 kurze Sätze ohne Aufzählungen).\n")
            append("- ZERO-PII SANDBOX: Gib am Telefon NIEMALS interne Geschäftsdaten, Bestellungen, Kundendaten, Finanzen oder E-Mails preis.")
        }
    }

    fun buildSecretaryToolsJson(): JsonArray {
        return JsonArray().apply {
            add(JsonObject().apply {
                add("functionDeclarations", JsonArray().apply {
                    add(JsonObject().apply {
                        addProperty("name", "phone_secretary_finish")
                        addProperty(
                            "description",
                            "Speichert die aufgenommene Telefonnachricht sofort als Aufgabe für Alina und bereitet das freundliche Gesprächsende vor."
                        )
                        add("parameters", JsonObject().apply {
                            addProperty("type", "OBJECT")
                            add("properties", JsonObject().apply {
                                add("summary", JsonObject().apply {
                                    addProperty("type", "STRING")
                                    addProperty("description", "Klare, vollständige deutsche Zusammenfassung des Anliegens für Alina.")
                                })
                                add("urgency", JsonObject().apply {
                                    addProperty("type", "STRING")
                                    addProperty("description", "Dringlichkeit: hoch, mittel oder niedrig.")
                                })
                                add("callback_requested", JsonObject().apply {
                                    addProperty("type", "BOOLEAN")
                                    addProperty("description", "True, wenn Alina zurückrufen soll.")
                                })
                            })
                            add("required", JsonArray().apply {
                                add("summary")
                            })
                        })
                    })
                })
            })
        }
    }

    fun buildFallbackSummaryFromTranscript(callerTranscript: String?, greetingName: String): String {
        val cleaned = callerTranscript
            ?.replace(Regex("\\s+"), " ")
            ?.trim()
            .orEmpty()
        if (cleaned.length >= 6) {
            return if (cleaned.length > 700) cleaned.take(700) + "..." else cleaned
        }
        val who = greetingName.trim().ifBlank { "Kontakt" }
        return "$who hat angerufen (kurzes Gespräch ohne nähere Nachricht) und bittet bei Gelegenheit um Rückruf."
    }

    internal fun buildKickoffPacketJson(greetingName: String): JsonObject {
        val who = greetingName.trim().ifBlank { "Kontakt" }
        return JsonObject().apply {
            add("realtimeInput", JsonObject().apply {
                addProperty(
                    "text",
                    "[TELEFONAT SOEBEN ANGENOMMEN: $who ist jetzt in der Leitung. Begrüße $who jetzt sofort herzlich und freundlich auf Deutsch als Funkira, Alinas persönliche Assistentin, sag kurz dass Alina gerade nicht persönlich ans Telefon gehen kann, frage freundlich nach dem Anliegen und versichere warmherzig, dass Alina die Nachricht sofort bekommt.]"
                )
            })
        }
    }

    internal fun buildToolResponsePacketJson(
        fnId: String,
        fnName: String,
        greetingName: String
    ): JsonObject {
        val who = greetingName.trim().ifBlank { "Kontakt" }
        return JsonObject().apply {
            add("toolResponse", JsonObject().apply {
                add("functionResponses", JsonArray().apply {
                    add(JsonObject().apply {
                        addProperty("id", fnId)
                        addProperty("name", fnName)
                        add("response", JsonObject().apply {
                            addProperty("status", "success")
                            addProperty(
                                "note",
                                "Nachricht gespeichert. Verabschiede dich jetzt herzlich und freundlich in einem kurzen Satz von $who und bestätige nett, dass Alina die Nachricht sofort bekommt."
                            )
                        })
                    })
                })
            })
        }
    }

    suspend fun runSecretaryCall(
        context: Context,
        callId: String,
        callerName: String,
        greetingName: String,
        relationType: String?,
        systemInstructions: String?,
        isCallStillOffhook: () -> Boolean
    ): SecretarySessionResult {
        val appCtx = context.applicationContext
        ServiceLocator.ensureInitialized(appCtx)

        val audioManager = appCtx.getSystemService(Context.AUDIO_SERVICE) as? AudioManager
        val prevMode = audioManager?.mode ?: AudioManager.MODE_NORMAL
        @Suppress("DEPRECATION")
        val prevSpeaker = audioManager?.isSpeakerphoneOn ?: false
        val prevCallVol = try {
            audioManager?.getStreamVolume(AudioManager.STREAM_VOICE_CALL) ?: -1
        } catch (_: Exception) {
            -1
        }

        NavigationBridge.isLiveCallActive = true
        configureSpeakerphoneForSecretaryCall(audioManager)

        try {
            val candidates = buildWsCandidates(appCtx, callId)
            val prompt = buildSecretarySystemInstruction(
                callerName = callerName,
                greetingName = greetingName,
                relationType = relationType,
                contactInstructions = systemInstructions
            )

            for ((idx, candidate) in candidates.withIndex()) {
                if (!isCallStillOffhook()) {
                    return SecretarySessionResult(
                        summary = buildFallbackSummaryFromTranscript("", greetingName),
                        urgency = "mittel",
                        callbackRequested = true,
                        usedLiveAi = false
                    )
                }

                val liveResult = attemptLiveCandidate(
                    appCtx = appCtx,
                    callId = callId,
                    greetingName = greetingName,
                    systemInstruction = prompt,
                    candidate = candidate,
                    attemptIndex = idx,
                    isCallStillOffhook = isCallStillOffhook
                )
                if (liveResult != null) {
                    if (idx > 0) {
                        ErrorSentinelReporter.captureAutoHeal(
                            context = appCtx,
                            component = "phone_secretary",
                            title = "Smartphone-Sekretärin über Fallback-Endpunkt (${candidate.label}) verbunden",
                            message = "Primärer Endpunkt nicht bereit; Kandidat #${idx + 1} hat übernommen.",
                            healSummary = "Anruf von $greetingName nahtlos über ${candidate.label} live angenommen.",
                            extraContext = mapOf("call_id" to callId, "endpoint" to candidate.label)
                        )
                    }
                    return liveResult
                }
            }

            // Offline / Dead-Zone Emergency Fallback: Friendly native German TTS on device!
            ErrorSentinelReporter.captureAutoHeal(
                context = appCtx,
                component = "phone_secretary",
                title = "Smartphone-Sekretärin im Offline-Sprachmodus eingesprungen",
                message = "Keine mobile Datenverbindung zu Gemini Live während des Anrufs verfügbar.",
                healSummary = "Lokale deutsche Sprachausgabe hat $greetingName freundlich begrüßt und Rückruf-Notiz gesichert.",
                extraContext = mapOf("call_id" to callId)
            )
            return runOfflineNativeTtsSecretary(
                appCtx = appCtx,
                greetingName = greetingName,
                isCallStillOffhook = isCallStillOffhook
            )
        } finally {
            NavigationBridge.isLiveCallActive = false
            restoreCallAudio(audioManager, prevMode, prevSpeaker, prevCallVol)
        }
    }

    private suspend fun buildWsCandidates(appCtx: Context, callId: String): List<WsCandidate> {
        val baseUrl = ServiceLocator.getBaseUrl()
        val baseHost = try { URI(baseUrl).host } catch (_: Exception) { null } ?: "stage.mein-seelenfunke.de"
        val defaultOrigin = if (baseUrl.startsWith("https://", ignoreCase = true)) {
            "https://$baseHost"
        } else {
            "https://mein-seelenfunke.de"
        }

        val warm = preWarmed?.takeIf { (SystemClock.uptimeMillis() - it.fetchedAtUptimeMs) < WARM_CACHE_TTL_MS }
        preWarmed = null

        val creds: LiveCredentialsResponse? = warm?.response ?: run {
            if (ServiceLocator.getAuthToken().isNullOrBlank()) {
                null
            } else {
                ServiceLocator.aiRepository.getLiveCredentials(
                    agentId = null,
                    chatSessionId = "android_secretary_$callId",
                    client = "android_secretary",
                    fallback = "direct"
                ).getOrNull()
            }
        }

        val origin = creds?.origin_url?.takeIf { it.isNotBlank() } ?: defaultOrigin
        val voice = creds?.voice_name?.takeIf { it.isNotBlank() } ?: "Kore"
        val list = mutableListOf<WsCandidate>()

        // 1. Fastest path: Direct Gemini Live WebSocket (skips proxy hop for ~250 ms faster greeting)
        val serverDirect = creds?.direct_ws_url?.takeIf { it.isNotBlank() }
        val localDirect = FunkiraLiveViewModel.buildDirectGeminiWsUrl(ServiceLocator.getGeminiApiKey())
        val bestDirect = serverDirect ?: localDirect
        if (!bestDirect.isNullOrBlank()) {
            list.add(
                WsCandidate(
                    label = "direct_gemini_live",
                    url = bestDirect,
                    isDirectGoogle = true,
                    originUrl = origin,
                    voiceName = voice
                )
            )
        }

        // 2. Fallback path: Seelenfunke Bridge WebSocket
        val rawBridge = creds?.ws_url?.takeIf { it.isNotBlank() }
        val token = creds?.token?.takeIf { it.isNotBlank() }
        if (!rawBridge.isNullOrBlank() && !token.isNullOrBlank()) {
            var bridgeUrl = rawBridge
            if (baseUrl.startsWith("https://", ignoreCase = true) && bridgeUrl.startsWith("ws://", ignoreCase = true)) {
                bridgeUrl = "wss://" + bridgeUrl.substring(5)
            }
            if (FunkiraLiveViewModel.isTrustedLiveWebSocketHost(bridgeUrl, baseHost)) {
                val sep = if (bridgeUrl.contains("?")) "&" else "?"
                val fullBridgeUrl = "$bridgeUrl${sep}token=${Uri.encode(token)}"
                list.add(
                    WsCandidate(
                        label = "portal_bridge",
                        url = fullBridgeUrl,
                        isDirectGoogle = false,
                        originUrl = origin,
                        voiceName = voice
                    )
                )
            }
        }

        return list
    }

    private suspend fun attemptLiveCandidate(
        appCtx: Context,
        callId: String,
        greetingName: String,
        systemInstruction: String,
        candidate: WsCandidate,
        attemptIndex: Int,
        isCallStillOffhook: () -> Boolean
    ): SecretarySessionResult? {
        val scope = CoroutineScope(SupervisorJob() + Dispatchers.IO)
        val setupReady = CompletableDeferred<Boolean>()
        val sessionFinished = CompletableDeferred<Unit>()
        val closedFlag = AtomicBoolean(false)
        val playbackActiveUntilMs = AtomicLong(0L)
        val kickoffSentUptimeMs = AtomicLong(0L)
        val firstModelAudioArrived = AtomicBoolean(false)
        val goodbyeAudioArrived = AtomicBoolean(false)
        val finishToolTriggered = AtomicBoolean(false)
        val goodbyeTurnDone = AtomicBoolean(false)

        val callerFullTranscript = StringBuilder()
        val completedSummary = AtomicReference<String?>(null)
        val completedUrgency = AtomicReference("mittel")
        val completedCallback = AtomicBoolean(true)

        val audioTrack = createSecretaryAudioTrack()
        var recordJob: Job? = null

        val reqBuilder = Request.Builder().url(candidate.url)
        if (candidate.isDirectGoogle) {
            reqBuilder.header("Origin", candidate.originUrl)
            reqBuilder.header("Referer", candidate.originUrl.trimEnd('/') + "/")
        }

        var wsRef: WebSocket? = null

        fun writePcm24k(rawPcm: ByteArray) {
            val track = audioTrack ?: return
            if (rawPcm.isEmpty() || track.state != AudioTrack.STATE_INITIALIZED) return
            try {
                if (track.playState != AudioTrack.PLAYSTATE_PLAYING) {
                    track.play()
                }
                val boosted = ByteArray(rawPcm.size)
                var i = 0
                while (i + 1 < rawPcm.size) {
                    val low = rawPcm[i].toInt() and 0xFF
                    val high = rawPcm[i + 1].toInt()
                    val sample = ((high shl 8) or low).toShort()
                    val scaled = max(Short.MIN_VALUE.toInt(), min(Short.MAX_VALUE.toInt(), (sample * 1.85f).toInt()))
                    boosted[i] = (scaled and 0xFF).toByte()
                    boosted[i + 1] = ((scaled ushr 8) and 0xFF).toByte()
                    i += 2
                }
                val written = track.write(boosted, 0, boosted.size)
                if (written > 0) {
                    val chunkDurationMs = (written * 1000L) / (24000L * 2L)
                    val now = SystemClock.uptimeMillis()
                    val prevEnd = playbackActiveUntilMs.get()
                    val base = if (prevEnd > now) prevEnd else now
                    playbackActiveUntilMs.set(base + chunkDurationMs)
                }
            } catch (_: Exception) {
            }
        }

        val listener = object : WebSocketListener() {
            override fun onOpen(webSocket: WebSocket, response: Response) {
                val setup = JsonObject().apply {
                    add("setup", JsonObject().apply {
                        addProperty("model", "models/gemini-3.1-flash-live-preview")
                        add("generationConfig", JsonObject().apply {
                            add("responseModalities", JsonArray().apply { add("AUDIO") })
                            add("speechConfig", JsonObject().apply {
                                add("voiceConfig", JsonObject().apply {
                                    add("prebuiltVoiceConfig", JsonObject().apply {
                                        addProperty("voiceName", candidate.voiceName)
                                    })
                                })
                            })
                        })
                        add("systemInstruction", JsonObject().apply {
                            add("parts", JsonArray().apply {
                                add(JsonObject().apply { addProperty("text", systemInstruction) })
                            })
                        })
                        add("tools", buildSecretaryToolsJson())
                        add("inputAudioTranscription", JsonObject())
                        add("outputAudioTranscription", JsonObject())
                    })
                }
                webSocket.send(gson.toJson(setup))
            }

            override fun onMessage(webSocket: WebSocket, text: String) {
                handleJsonMessage(webSocket, text)
            }

            override fun onMessage(webSocket: WebSocket, bytes: ByteString) {
                handleJsonMessage(webSocket, bytes.utf8())
            }

            private fun handleJsonMessage(webSocket: WebSocket, jsonText: String) {
                try {
                    val root = gson.fromJson(jsonText, JsonObject::class.java) ?: return
                    if (root.has("error")) {
                        val errMsg = root.getAsJsonObject("error")?.get("message")?.asString ?: "Server-Fehler"
                        ErrorSentinelReporter.captureError(
                            context = appCtx,
                            tag = "phone_secretary_ws",
                            message = "Gemini-Fehler (${candidate.label}): $errMsg",
                            extraContext = mapOf("call_id" to callId, "endpoint" to candidate.label)
                        )
                        if (!setupReady.isCompleted) setupReady.complete(false)
                        sessionFinished.complete(Unit)
                        return
                    }

                    if (root.has("setupComplete")) {
                        if (!setupReady.isCompleted) {
                            setupReady.complete(true)
                        }
                        // Trigger immediate warm greeting via Gemini 3.1 realtimeInput.text without waiting for caller silence!
                        kickoffSentUptimeMs.set(SystemClock.uptimeMillis())
                        val kickoff = buildKickoffPacketJson(greetingName)
                        webSocket.send(gson.toJson(kickoff))
                        return
                    }

                    if (root.has("serverContent")) {
                        val sc = root.getAsJsonObject("serverContent")
                        val inTr = when {
                            sc.has("inputTranscription") -> sc.getAsJsonObject("inputTranscription")
                            sc.has("inputAudioTranscription") -> sc.getAsJsonObject("inputAudioTranscription")
                            else -> null
                        }
                        if (inTr != null && inTr.has("text")) {
                            val t = inTr.get("text").asString
                            if (!t.isNullOrBlank()) {
                                callerFullTranscript.append(' ').append(t.trim())
                            }
                        }

                        if (sc.has("interrupted") && sc.get("interrupted").asBoolean) {
                            playbackActiveUntilMs.set(0L)
                            try {
                                audioTrack?.pause()
                                audioTrack?.flush()
                                audioTrack?.play()
                            } catch (_: Exception) {
                            }
                        }

                        if (sc.has("modelTurn")) {
                            val parts = sc.getAsJsonObject("modelTurn")?.getAsJsonArray("parts")
                            parts?.forEach { p ->
                                val pObj = p.asJsonObject
                                if (pObj.has("inlineData")) {
                                    val b64 = pObj.getAsJsonObject("inlineData")?.get("data")?.asString
                                    if (!b64.isNullOrBlank()) {
                                        val pcm = Base64.decode(b64, Base64.NO_WRAP)
                                        firstModelAudioArrived.set(true)
                                        if (finishToolTriggered.get()) {
                                            goodbyeAudioArrived.set(true)
                                        }
                                        writePcm24k(pcm)
                                    }
                                }
                            }
                        }

                        if (sc.has("turnComplete") && sc.get("turnComplete").asBoolean) {
                            if (finishToolTriggered.get()) {
                                goodbyeTurnDone.set(true)
                            }
                        }
                    }

                    if (root.has("toolCall")) {
                        val calls = root.getAsJsonObject("toolCall")?.getAsJsonArray("functionCalls")
                        calls?.forEach { c ->
                            val cObj = c.asJsonObject
                            val fnName = cObj.get("name")?.asString.orEmpty()
                            val fnId = cObj.get("id")?.asString.orEmpty()
                            if (fnName == "phone_secretary_finish") {
                                val args = if (cObj.has("args") && cObj.get("args").isJsonObject) {
                                    cObj.getAsJsonObject("args")
                                } else null
                                val sum = args?.get("summary")?.asString?.trim().orEmpty()
                                val urg = PhoneConciergeManager.normalizeUrgency(args?.get("urgency")?.asString)
                                val cb = if (args != null && args.has("callback_requested")) {
                                    try { args.get("callback_requested").asBoolean } catch (_: Exception) { true }
                                } else true

                                if (sum.isNotBlank()) {
                                    completedSummary.set(sum)
                                }
                                completedUrgency.set(urg)
                                completedCallback.set(cb)
                                finishToolTriggered.set(true)

                                val toolResp = buildToolResponsePacketJson(fnId, fnName, greetingName)
                                webSocket.send(gson.toJson(toolResp))
                            }
                        }
                    }
                } catch (_: Exception) {
                }
            }

            override fun onFailure(webSocket: WebSocket, t: Throwable, response: Response?) {
                ErrorSentinelReporter.captureError(
                    context = appCtx,
                    tag = "phone_secretary_ws",
                    message = "Sekretärinnen-WebSocket Fehler (${candidate.label}, Versuch #${attemptIndex + 1}): ${t.message}",
                    throwable = t,
                    extraContext = mapOf(
                        "call_id" to callId,
                        "endpoint" to candidate.label,
                        "http_status" to (response?.code?.toString() ?: "0")
                    )
                )
                if (!setupReady.isCompleted) setupReady.complete(false)
                sessionFinished.complete(Unit)
            }

            override fun onClosing(webSocket: WebSocket, code: Int, reason: String) {
                if (!setupReady.isCompleted) setupReady.complete(false)
                sessionFinished.complete(Unit)
            }

            override fun onClosed(webSocket: WebSocket, code: Int, reason: String) {
                if (!setupReady.isCompleted) setupReady.complete(false)
                sessionFinished.complete(Unit)
            }
        }

        try {
            wsRef = wsHttpClient.newWebSocket(reqBuilder.build(), listener)

            val ready = withTimeoutOrNull(4_500L) { setupReady.await() } == true
            if (!ready) {
                return null
            }

            recordJob = scope.launch {
                streamCallerMicrophoneLoop(
                    appCtx = appCtx,
                    ws = wsRef,
                    closedFlag = closedFlag,
                    playbackActiveUntilMs = playbackActiveUntilMs,
                    kickoffSentUptimeMs = kickoffSentUptimeMs,
                    firstModelAudioArrived = firstModelAudioArrived,
                    isCallStillOffhook = isCallStillOffhook
                )
            }

            val startedUptime = SystemClock.uptimeMillis()
            var finishDetectedAtMs = 0L

            while (scope.isActive && !sessionFinished.isCompleted) {
                delay(250L)
                val now = SystemClock.uptimeMillis()

                if (!isCallStillOffhook()) {
                    break
                }
                if ((now - startedUptime) >= 90_000L) {
                    break
                }

                if (finishToolTriggered.get()) {
                    if (finishDetectedAtMs == 0L) finishDetectedAtMs = now
                    val audioFinished = now >= (playbackActiveUntilMs.get() + 550L)
                    val elapsedSinceFinish = now - finishDetectedAtMs
                    val silentFinishTimeout = !goodbyeAudioArrived.get() && elapsedSinceFinish >= 4_500L
                    if ((goodbyeTurnDone.get() && audioFinished) || silentFinishTimeout || elapsedSinceFinish >= 16_000L) {
                        break
                    }
                }
            }

            val finalSummary = completedSummary.get()?.takeIf { it.isNotBlank() }
                ?: buildFallbackSummaryFromTranscript(callerFullTranscript.toString(), greetingName)

            return SecretarySessionResult(
                summary = finalSummary,
                urgency = completedUrgency.get(),
                callbackRequested = completedCallback.get(),
                usedLiveAi = true
            )
        } finally {
            closedFlag.set(true)
            recordJob?.cancel()
            try { wsRef?.close(1000, "secretary_done") } catch (_: Exception) { }
            try {
                audioTrack?.stop()
                audioTrack?.release()
            } catch (_: Exception) { }
            scope.cancel()
        }
    }

    private suspend fun streamCallerMicrophoneLoop(
        appCtx: Context,
        ws: WebSocket?,
        closedFlag: AtomicBoolean,
        playbackActiveUntilMs: AtomicLong,
        kickoffSentUptimeMs: AtomicLong,
        firstModelAudioArrived: AtomicBoolean,
        isCallStillOffhook: () -> Boolean
    ) {
        if (ws == null) return
        if (ContextCompat.checkSelfPermission(appCtx, Manifest.permission.RECORD_AUDIO) != PackageManager.PERMISSION_GRANTED) {
            ErrorSentinelReporter.captureError(
                context = appCtx,
                tag = "phone_secretary_mic",
                message = "RECORD_AUDIO Berechtigung fehlt für Smartphone-Sekretärin."
            )
            return
        }

        val sampleRate = 16000
        val channelConfig = AudioFormat.CHANNEL_IN_MONO
        val encoding = AudioFormat.ENCODING_PCM_16BIT
        val minBuf = max(AudioRecord.getMinBufferSize(sampleRate, channelConfig, encoding), 4096)

        val record = createBestAudioRecord(sampleRate, channelConfig, encoding, minBuf) ?: run {
            ErrorSentinelReporter.captureError(
                context = appCtx,
                tag = "phone_secretary_mic",
                message = "AudioRecord konnte während des Telefonats nicht initialisiert werden."
            )
            return
        }

        var aec: AcousticEchoCanceler? = null
        var ns: NoiseSuppressor? = null

        try {
            if (AcousticEchoCanceler.isAvailable()) {
                aec = AcousticEchoCanceler.create(record.audioSessionId)?.apply { enabled = true }
            }
            if (NoiseSuppressor.isAvailable()) {
                ns = NoiseSuppressor.create(record.audioSessionId)?.apply { enabled = true }
            }

            record.startRecording()
            if (record.recordingState != AudioRecord.RECORDSTATE_RECORDING) return

            // 640 samples @ 16 kHz = crisp 40 ms real-time frames with zero extra sleep latency
            val pcmShortBuf = ShortArray(640)
            while (!closedFlag.get() && isCallStillOffhook()) {
                val read = record.read(pcmShortBuf, 0, pcmShortBuf.size)
                if (read <= 0) {
                    delay(15L)
                    continue
                }

                // Opening-Greeting Anti-Barge-In Guard + Software Echo Gate:
                // 1) During the first ~1.8s after pickup while Gemini generates its opening greeting, do not let
                //    call-connect clicks or an early "Hallo?" abort Funkira's self-introduction before she speaks.
                // 2) While Funkira's 24 kHz voice is playing on speakerphone, suppress echo back into Gemini VAD.
                val now = SystemClock.uptimeMillis()
                val kickAt = kickoffSentUptimeMs.get()
                if (!firstModelAudioArrived.get() && kickAt > 0L && (now - kickAt) < 1_800L) {
                    continue
                }
                if (now < (playbackActiveUntilMs.get() + ECHO_TAIL_GUARD_MS)) {
                    continue
                }

                val outBytes = ByteArray(read * 2)
                for (i in 0 until read) {
                    val s = pcmShortBuf[i].toInt()
                    outBytes[i * 2] = (s and 0xFF).toByte()
                    outBytes[i * 2 + 1] = ((s shr 8) and 0xFF).toByte()
                }
                val b64 = Base64.encodeToString(outBytes, Base64.NO_WRAP)
                val packet = JsonObject().apply {
                    add("realtimeInput", JsonObject().apply {
                        add("audio", JsonObject().apply {
                            addProperty("mimeType", "audio/pcm;rate=16000")
                            addProperty("data", b64)
                        })
                    })
                }
                if (!ws.send(gson.toJson(packet))) {
                    break
                }
            }
        } catch (_: Exception) {
        } finally {
            try { aec?.release() } catch (_: Exception) { }
            try { ns?.release() } catch (_: Exception) { }
            try { record.stop() } catch (_: Exception) { }
            try { record.release() } catch (_: Exception) { }
        }
    }

    @Suppress("MissingPermission")
    private fun createBestAudioRecord(
        sampleRate: Int,
        channelConfig: Int,
        encoding: Int,
        bufferSize: Int
    ): AudioRecord? {
        val sources = intArrayOf(
            MediaRecorder.AudioSource.VOICE_RECOGNITION,
            MediaRecorder.AudioSource.VOICE_COMMUNICATION,
            MediaRecorder.AudioSource.MIC
        )
        for (src in sources) {
            try {
                val candidate = AudioRecord(src, sampleRate, channelConfig, encoding, bufferSize)
                if (candidate.state == AudioRecord.STATE_INITIALIZED) {
                    return candidate
                }
                candidate.release()
            } catch (_: Exception) {
            }
        }
        return null
    }

    private fun createSecretaryAudioTrack(): AudioTrack? {
        return try {
            val sampleRate = 24000
            val channelConfig = AudioFormat.CHANNEL_OUT_MONO
            val encoding = AudioFormat.ENCODING_PCM_16BIT
            val minBuf = max(AudioTrack.getMinBufferSize(sampleRate, channelConfig, encoding), 12288)
            val builder = AudioTrack.Builder()
                .setAudioAttributes(
                    AudioAttributes.Builder()
                        .setUsage(AudioAttributes.USAGE_VOICE_COMMUNICATION)
                        .setContentType(AudioAttributes.CONTENT_TYPE_SPEECH)
                        .build()
                )
                .setAudioFormat(
                    AudioFormat.Builder()
                        .setSampleRate(sampleRate)
                        .setChannelMask(channelConfig)
                        .setEncoding(encoding)
                        .build()
                )
                .setBufferSizeInBytes(minBuf)
                .setTransferMode(AudioTrack.MODE_STREAM)

            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                builder.setPerformanceMode(AudioTrack.PERFORMANCE_MODE_LOW_LATENCY)
            }

            builder.build().also { track ->
                if (track.state == AudioTrack.STATE_INITIALIZED) {
                    track.setVolume(1.0f)
                    track.play()
                }
            }
        } catch (_: Exception) {
            null
        }
    }

    private suspend fun runOfflineNativeTtsSecretary(
        appCtx: Context,
        greetingName: String,
        isCallStillOffhook: () -> Boolean
    ): SecretarySessionResult {
        val done = CompletableDeferred<Unit>()
        var ttsRef: TextToSpeech? = null
        val spokenText =
            "Hallo $greetingName, hier ist Funkira, Alinas persönliche Assistentin. Alina kann gerade leider nicht ans Telefon gehen, aber ich habe deinen Anruf sofort für sie notiert und gebe ihr direkt Bescheid, damit sie sich bei dir meldet. Hab noch einen wunderschönen Tag, tschüss!"

        try {
            ttsRef = TextToSpeech(appCtx) { status ->
                if (status == TextToSpeech.SUCCESS) {
                    try {
                        ttsRef?.language = Locale.GERMAN
                        ttsRef?.setOnUtteranceProgressListener(object : UtteranceProgressListener() {
                            override fun onStart(utteranceId: String?) {}
                            override fun onDone(utteranceId: String?) {
                                done.complete(Unit)
                            }
                            @Deprecated("Deprecated in Java")
                            override fun onError(utteranceId: String?) {
                                done.complete(Unit)
                            }
                        })
                        val attrs = AudioAttributes.Builder()
                            .setUsage(AudioAttributes.USAGE_VOICE_COMMUNICATION)
                            .setContentType(AudioAttributes.CONTENT_TYPE_SPEECH)
                            .build()
                        ttsRef?.setAudioAttributes(attrs)
                        val ttsParams = android.os.Bundle().apply {
                            putInt(TextToSpeech.Engine.KEY_PARAM_STREAM, AudioManager.STREAM_VOICE_CALL)
                            putFloat(TextToSpeech.Engine.KEY_PARAM_VOLUME, 1.0f)
                        }
                        ttsRef?.speak(spokenText, TextToSpeech.QUEUE_FLUSH, ttsParams, "funkira_offline_sec")
                    } catch (_: Exception) {
                        done.complete(Unit)
                    }
                } else {
                    done.complete(Unit)
                }
            }

            withTimeoutOrNull(14_000L) {
                while (!done.isCompleted && isCallStillOffhook()) {
                    delay(250L)
                }
            }
            delay(400L)
        } catch (_: Exception) {
        } finally {
            try {
                ttsRef?.stop()
                ttsRef?.shutdown()
            } catch (_: Exception) { }
        }

        return SecretarySessionResult(
            summary = "$greetingName hat angerufen (während Funkloch per Offline-Sekretärin angenommen) und bittet um Rückruf.",
            urgency = "mittel",
            callbackRequested = true,
            usedLiveAi = false
        )
    }

    @Suppress("DEPRECATION")
    private fun configureSpeakerphoneForSecretaryCall(am: AudioManager?) {
        if (am == null) return
        try {
            am.mode = AudioManager.MODE_IN_COMMUNICATION
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                val speaker = am.availableCommunicationDevices.firstOrNull {
                    it.type == AudioDeviceInfo.TYPE_BUILTIN_SPEAKER
                }
                if (speaker != null) {
                    am.setCommunicationDevice(speaker)
                }
            }
            am.isSpeakerphoneOn = true

            val maxVol = am.getStreamMaxVolume(AudioManager.STREAM_VOICE_CALL)
            if (maxVol > 0) {
                val targetVol = max(1, (maxVol * 0.80f).toInt())
                val curVol = am.getStreamVolume(AudioManager.STREAM_VOICE_CALL)
                if (curVol < targetVol) {
                    am.setStreamVolume(AudioManager.STREAM_VOICE_CALL, targetVol, 0)
                }
            }
        } catch (_: Exception) {
        }
    }

    @Suppress("DEPRECATION")
    private fun restoreCallAudio(am: AudioManager?, prevMode: Int, prevSpeaker: Boolean, prevCallVol: Int) {
        if (am == null) return
        try {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                am.clearCommunicationDevice()
            }
            am.isSpeakerphoneOn = prevSpeaker
            am.mode = prevMode
            if (prevCallVol >= 0) {
                am.setStreamVolume(AudioManager.STREAM_VOICE_CALL, prevCallVol, 0)
            }
        } catch (_: Exception) {
        }
    }
}
