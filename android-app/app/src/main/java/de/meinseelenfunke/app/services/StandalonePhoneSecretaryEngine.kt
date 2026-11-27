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
import de.meinseelenfunke.app.data.api.CallVerificationReportDto
import de.meinseelenfunke.app.data.api.LiveCredentialsResponse
import de.meinseelenfunke.app.di.ServiceLocator
import de.meinseelenfunke.app.ui.screens.FunkiraLiveViewModel
import de.meinseelenfunke.app.util.ErrorSentinelReporter
import de.meinseelenfunke.app.util.NavigationBridge
import de.meinseelenfunke.app.util.PhoneConciergeManager
import de.meinseelenfunke.app.util.VertexClientTokenCache
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
import java.time.ZoneId
import java.time.ZonedDateTime
import java.time.format.DateTimeFormatter
import java.util.Collections
import java.util.Locale
import java.util.UUID
import java.util.concurrent.TimeUnit
import java.util.concurrent.atomic.AtomicBoolean
import java.util.concurrent.atomic.AtomicLong
import java.util.concurrent.atomic.AtomicReference
import kotlin.math.max
import kotlin.math.min
import kotlin.math.tanh

data class SecretarySessionResult(
    val summary: String,
    val urgency: String,
    val callbackRequested: Boolean,
    val usedLiveAi: Boolean,
    val rahmenbedingungen: List<String> = emptyList(),
    val actionRequired: String = "",
    val durationSeconds: Int = 0,
    val transcript: List<String> = emptyList(),
    val verification: CallVerificationReportDto? = null
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
        val voiceName: String,
        val liveModel: String = de.meinseelenfunke.app.data.api.VertexEphemeralResponse.DEFAULT_LIVE_MODEL,
        val directBearerToken: String? = null
    )

    internal data class MicCaptureProfile(
        val audioSource: Int,
        val label: String,
        val enableHardwareEffects: Boolean
    )

    internal enum class MicFrameVerdict {
        ACTIVE,
        SILENT_WAIT,
        ROTATE_SOURCE,
        PERMANENT_SILENCE
    }

    internal class MicZeroSampleWatchdog(
        private val rotateThresholdFrames: Int = 28,
        private val reportThresholdFrames: Int = 65
    ) {
        var consecutiveZeroFramesInAttempt: Int = 0
            private set
        var totalZeroFramesAcrossAttempts: Int = 0
            private set
        var recoveryRotations: Int = 0
            private set
        var hasReportedWarning: Boolean = false
            private set

        fun onFrame(samples: ShortArray, length: Int, hasMoreCandidates: Boolean): MicFrameVerdict {
            val n = length.coerceIn(0, samples.size)
            var hasNonZero = false
            for (i in 0 until n) {
                if (samples[i].toInt() != 0) {
                    hasNonZero = true
                    break
                }
            }
            if (hasNonZero) {
                consecutiveZeroFramesInAttempt = 0
                totalZeroFramesAcrossAttempts = 0
                return MicFrameVerdict.ACTIVE
            }

            consecutiveZeroFramesInAttempt++
            totalZeroFramesAcrossAttempts++

            if (hasMoreCandidates && consecutiveZeroFramesInAttempt >= rotateThresholdFrames) {
                consecutiveZeroFramesInAttempt = 0
                recoveryRotations++
                return MicFrameVerdict.ROTATE_SOURCE
            }

            if (!hasReportedWarning &&
                (totalZeroFramesAcrossAttempts >= reportThresholdFrames ||
                    (!hasMoreCandidates && consecutiveZeroFramesInAttempt >= reportThresholdFrames))
            ) {
                hasReportedWarning = true
                return MicFrameVerdict.PERMANENT_SILENCE
            }

            return MicFrameVerdict.SILENT_WAIT
        }
    }

    internal fun resolveDesiredAudioModeForSecretary(currentMode: Int): Int {
        return if (currentMode == AudioManager.MODE_IN_CALL) {
            AudioManager.MODE_IN_CALL
        } else {
            AudioManager.MODE_IN_COMMUNICATION
        }
    }

    internal fun resolveAudioSourceProfiles(audioMode: Int): List<MicCaptureProfile> {
        return if (audioMode == AudioManager.MODE_IN_CALL) {
            listOf(
                MicCaptureProfile(MediaRecorder.AudioSource.MIC, "MIC_RAW", enableHardwareEffects = false),
                MicCaptureProfile(MediaRecorder.AudioSource.VOICE_COMMUNICATION, "VOICE_COMM_RAW", enableHardwareEffects = false),
                MicCaptureProfile(MediaRecorder.AudioSource.VOICE_RECOGNITION, "VOICE_REC_FX", enableHardwareEffects = true),
                MicCaptureProfile(MediaRecorder.AudioSource.UNPROCESSED, "UNPROCESSED_RAW", enableHardwareEffects = false)
            )
        } else {
            listOf(
                MicCaptureProfile(MediaRecorder.AudioSource.VOICE_COMMUNICATION, "VOICE_COMM_FX", enableHardwareEffects = true),
                MicCaptureProfile(MediaRecorder.AudioSource.MIC, "MIC_RAW", enableHardwareEffects = false),
                MicCaptureProfile(MediaRecorder.AudioSource.VOICE_RECOGNITION, "VOICE_REC_FX", enableHardwareEffects = true),
                MicCaptureProfile(MediaRecorder.AudioSource.UNPROCESSED, "UNPROCESSED_RAW", enableHardwareEffects = false)
            )
        }
    }

    private const val WARM_CACHE_TTL_MS = 75_000L
    private const val ECHO_TAIL_GUARD_MS = 380L
    private const val OPENING_BARGE_IN_GUARD_MS = 1_200L

    @Volatile
    private var preWarmed: WarmCredentials? = null

    private val preWarmScope = CoroutineScope(SupervisorJob() + Dispatchers.IO)
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

    fun preWarmCredentialsAsync(context: Context) {
        val appCtx = context.applicationContext
        preWarmScope.launch {
            preWarmCredentials(appCtx)
        }
    }

    /**
     * Pre-warms Gemini Live credentials & Vertex token while the phone is still ringing so WebSocket handshake
     * and post-call verification start in 0 ms.
     */
    suspend fun preWarmCredentials(context: Context) {
        try {
            val appCtx = context.applicationContext
            ServiceLocator.ensureInitialized(appCtx)
            if (ServiceLocator.getAuthToken().isNullOrBlank()) return

            val existing = preWarmed
            if (existing == null || (SystemClock.uptimeMillis() - existing.fetchedAtUptimeMs) >= WARM_CACHE_TTL_MS) {
                val res = ServiceLocator.aiRepository.getLiveCredentials(
                    agentId = null,
                    chatSessionId = "android_secretary_warm_${UUID.randomUUID()}",
                    client = "android_secretary",
                    fallback = "direct"
                )
                res.getOrNull()?.let { creds ->
                    preWarmed = WarmCredentials(SystemClock.uptimeMillis(), creds)
                    creds.vertex_ephemeral?.let { VertexClientTokenCache.store(it, appCtx) }
                }
            }
            if (VertexClientTokenCache.getValidCachedBundle(appCtx) == null) {
                VertexClientTokenCache.getOrFetchValidBundle(appCtx)
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
        val nowLabel = try {
            ZonedDateTime.now(ZoneId.of("Europe/Berlin"))
                .format(DateTimeFormatter.ofPattern("EEEE, dd.MM.yyyy, HH:mm", Locale.GERMAN))
        } catch (_: Exception) {
            ""
        }

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
        val timeLine = if (nowLabel.isNotBlank()) {
            "- Aktuelles Datum & Uhrzeit: $nowLabel Uhr.\n"
        } else {
            ""
        }

        return buildString {
            append("STRIKTE SPRACHREGEL & SEKRETÄRINNEN-ROLLE (HÖCHSTE PRIORITÄT):\n")
            append("- Du sprichst ZU 100% AUSSCHLIESSLICH AUF DEUTSCH. Gib niemals englische Wörter, Gedanken oder Systemmeldungen aus.\n")
            append("- Du bist Funkira, Alinas herzliche, schnelle, aufmerksame persönliche Sekretärin und KI-Assistentin von Mein-Seelenfunke.\n")
            append("- Du gehst gerade direkt an Alinas Smartphone für einen vertrauten Whitelist-Kontakt ans Telefon.\n")
            append("- Anrufer: $cleanCaller (persönliche Anrede: \"$cleanGreeting\").\n")
            append(timeLine)
            append(relationLine)
            append(extraLine)
            append("- Deine Aufgabe:\n")
            append("  1. Begrüße $cleanGreeting sofort freundlich, warmherzig und natürlich beim Namen als Funkira, Alinas Assistentin. Sag kurz, dass Alina gerade nicht persönlich ans Telefon gehen kann, du aber sehr gerne alles für sie aufnimmst.\n")
            append("  2. Höre aufmerksam zu, worum es geht. Kläre gezielt wichtige Rahmenbedingungen (Datum, Uhrzeit, Ort, Frist oder Aufgabe) mit höchstens EINER kurzen Rückfrage auf einmal.\n")
            append("  3. WICHTIG ZUR FEHLERVERMEIDUNG: Wiederhole bei konkreten Zahlen, Uhrzeiten, Daten, Namen oder Orten das Verstandene kurz zur Bestätigung (z. B. 'Also Samstag um 19 Uhr beim Italiener, richtig?'), bevor du abschließt.\n")
            append("  4. Sobald das Anliegen klar ist, rufe SOFORT das Tool `phone_secretary_finish` mit präziser `summary`, `rahmenbedingungen` (Array), `action_required`, `urgency` (`hoch`, `mittel` oder `niedrig`) und `callback_requested` auf.\n")
            append("  5. Vermittle $cleanGreeting direkt nach dem Tool-Aufruf super nett, herzlich und verbindlich, dass du alles genau notiert hast und Alina die Nachricht sofort bekommen wird, und verabschiede dich freundlich.\n")
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
                            "Speichert die aufgenommene Telefonnachricht samt Eckdaten sofort als geprüfte Aufgabe für Alina und bereitet das freundliche Gesprächsende vor."
                        )
                        add("parameters", JsonObject().apply {
                            addProperty("type", "OBJECT")
                            add("properties", JsonObject().apply {
                                add("summary", JsonObject().apply {
                                    addProperty("type", "STRING")
                                    addProperty("description", "Klare, vollständige deutsche Zusammenfassung des Anliegens für Alina (1-2 Sätze).")
                                })
                                add("rahmenbedingungen", JsonObject().apply {
                                    addProperty("type", "ARRAY")
                                    add("items", JsonObject().apply { addProperty("type", "STRING") })
                                    addProperty("description", "Stichpunkte zu geklärten Eckdaten (Datum, Uhrzeit, Ort, Frist, Betrag).")
                                })
                                add("action_required", JsonObject().apply {
                                    addProperty("type", "STRING")
                                    addProperty("description", "Konkrete Handlungsanweisung für Alina (z. B. 'Heute ab 18 Uhr zurückrufen').")
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

    internal fun buildKickoffPacketJson(
        greetingName: String,
        modelName: String = "models/gemini-3.1-flash-live-preview"
    ): JsonObject {
        val who = greetingName.trim().ifBlank { "Kontakt" }
        val prompt = "[TELEFONAT SOEBEN ANGENOMMEN: $who ist jetzt in der Leitung. Begrüße $who jetzt sofort herzlich und freundlich auf Deutsch als Funkira, Alinas persönliche Assistentin, sag kurz dass Alina gerade nicht persönlich ans Telefon gehen kann, frage freundlich nach dem Anliegen und versichere warmherzig, dass Alina die Nachricht sofort bekommt.]"
        return de.meinseelenfunke.app.util.FalconClientTools.buildClientContentKickPacket(
            prompt = prompt,
            modelName = modelName
        )
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

    private fun optStringSafe(obj: JsonObject?, key: String): String {
        if (obj == null || !obj.has(key)) return ""
        val el = obj.get(key) ?: return ""
        if (el.isJsonNull || !el.isJsonPrimitive) return ""
        return try { el.asString.orEmpty() } catch (_: Exception) { "" }
    }

    private fun optBooleanSafe(obj: JsonObject?, key: String, default: Boolean): Boolean {
        if (obj == null || !obj.has(key)) return default
        val el = obj.get(key) ?: return default
        if (el.isJsonNull || !el.isJsonPrimitive) return default
        return try { el.asBoolean } catch (_: Exception) { default }
    }

    suspend fun runSecretaryCall(
        context: Context,
        callId: String,
        callerName: String,
        greetingName: String,
        relationType: String?,
        systemInstructions: String?,
        micFgsGranted: Boolean = true,
        onTranscriptUpdated: ((List<String>) -> Unit)? = null,
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
                    micFgsGranted = micFgsGranted,
                    onTranscriptUpdated = onTranscriptUpdated,
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
        val voice = FunkiraLiveViewModel.resolveLiveVoiceName(
            requestedVoice = creds?.voice_name,
            isFunkiraSelected = true,
            fallbackVoice = "Aoede"
        )
        val list = mutableListOf<WsCandidate>()

        // 1. Fastest path: Direct EU Vertex AI Live WebSocket (skips proxy hop for ~250 ms faster greeting)
        val cachedVertex = creds?.vertex_ephemeral?.let {
            de.meinseelenfunke.app.util.VertexClientTokenCache.store(it, appCtx)
        } ?: de.meinseelenfunke.app.util.VertexClientTokenCache.getOrFetchValidBundle(appCtx)

        val directBearer = cachedVertex?.accessToken
            ?: de.meinseelenfunke.app.util.VertexClientTokenCache.extractAccessTokenFromWsUrl(creds?.direct_ws_url)
        val bestDirect = FunkiraLiveViewModel.buildDirectVertexWsUrl(
            accessToken = directBearer,
            customLiveWsUrl = cachedVertex?.liveWsUrl ?: creds?.direct_ws_url
        )
        if (!bestDirect.isNullOrBlank() && !directBearer.isNullOrBlank()) {
            val directModel = creds?.live_model?.takeIf { it.startsWith("projects/") }
                ?: cachedVertex?.liveModel
                ?: de.meinseelenfunke.app.data.api.VertexEphemeralResponse.DEFAULT_LIVE_MODEL
            list.add(
                WsCandidate(
                    label = "direct_vertex_live",
                    url = bestDirect,
                    isDirectGoogle = true,
                    originUrl = origin,
                    voiceName = voice,
                    liveModel = directModel,
                    directBearerToken = directBearer
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
        micFgsGranted: Boolean,
        onTranscriptUpdated: ((List<String>) -> Unit)? = null,
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
        val micSilencedFlag = AtomicBoolean(false)
        val silencePromptSent = AtomicBoolean(false)

        val callerFullTranscript = StringBuilder()
        val currentCallerTurn = StringBuilder()
        val currentAiTurn = StringBuilder()
        val transcriptLines = Collections.synchronizedList(mutableListOf<String>())
        val completedSummary = AtomicReference<String?>(null)
        val completedActionRequired = AtomicReference<String?>(null)
        val completedRahmen = AtomicReference<List<String>>(emptyList())
        val completedUrgency = AtomicReference("mittel")
        val completedCallback = AtomicBoolean(true)

        fun snapshotTranscriptWithPending(): List<String> {
            val out = synchronized(transcriptLines) { transcriptLines.toMutableList() }
            val uPending = currentCallerTurn.toString().replace(Regex("\\s+"), " ").trim()
            if (uPending.isNotEmpty()) out.add("Anrufer: $uPending")
            val aPending = currentAiTurn.toString().replace(Regex("\\s+"), " ").trim()
            if (aPending.isNotEmpty()) out.add("Funkira: $aPending")
            return out
        }

        fun flushPendingTurns(interruptedAi: Boolean = false) {
            val uTxt = currentCallerTurn.toString().replace(Regex("\\s+"), " ").trim()
            if (uTxt.isNotEmpty()) {
                transcriptLines.add("Anrufer: $uTxt")
                currentCallerTurn.setLength(0)
            }
            val aTxt = currentAiTurn.toString().replace(Regex("\\s+"), " ").trim()
            if (aTxt.isNotEmpty()) {
                transcriptLines.add(if (interruptedAi) "Funkira: $aTxt (unterbrochen)" else "Funkira: $aTxt")
                currentAiTurn.setLength(0)
            }
            onTranscriptUpdated?.invoke(synchronized(transcriptLines) { transcriptLines.toList() })
        }

        val audioTrack = createSecretaryAudioTrack()
        var recordJob: Job? = null

        val reqBuilder = Request.Builder().url(candidate.url)
        if (candidate.isDirectGoogle) {
            reqBuilder.header("Origin", candidate.originUrl)
            reqBuilder.header("Referer", candidate.originUrl.trimEnd('/') + "/")
            if (!candidate.directBearerToken.isNullOrBlank()) {
                reqBuilder.header("Authorization", "Bearer ${candidate.directBearerToken}")
            }
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
                    // Smooth soft-limiter avoids harsh digital clipping at high speaker volume
                    val normalized = (sample / 32768.0) * 1.45
                    val softLimited = tanh(normalized) * 32767.0
                    val scaled = max(Short.MIN_VALUE.toInt(), min(Short.MAX_VALUE.toInt(), softLimited.toInt()))
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
                        addProperty("model", candidate.liveModel)
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
                        // Trigger immediate warm greeting using the active model's wire schema
                        kickoffSentUptimeMs.set(SystemClock.uptimeMillis())
                        val kickoff = buildKickoffPacketJson(greetingName, candidate.liveModel)
                        webSocket.send(gson.toJson(kickoff))
                        return
                    }

                    if (root.has("serverContent")) {
                        val sc = root.getAsJsonObject("serverContent")
                        val inTr = when {
                            sc.has("inputTranscription") && sc.get("inputTranscription").isJsonObject -> sc.getAsJsonObject("inputTranscription")
                            sc.has("inputAudioTranscription") && sc.get("inputAudioTranscription").isJsonObject -> sc.getAsJsonObject("inputAudioTranscription")
                            else -> null
                        }
                        val inText = optStringSafe(inTr, "text")
                        if (inText.isNotEmpty()) {
                            callerFullTranscript.append(inText)
                            currentCallerTurn.append(inText)
                            onTranscriptUpdated?.invoke(snapshotTranscriptWithPending())
                        }

                        val outTr = when {
                            sc.has("outputTranscription") && sc.get("outputTranscription").isJsonObject -> sc.getAsJsonObject("outputTranscription")
                            sc.has("outputAudioTranscription") && sc.get("outputAudioTranscription").isJsonObject -> sc.getAsJsonObject("outputAudioTranscription")
                            else -> null
                        }
                        val outText = optStringSafe(outTr, "text")
                        if (outText.isNotEmpty()) {
                            currentAiTurn.append(outText)
                            onTranscriptUpdated?.invoke(snapshotTranscriptWithPending())
                        }

                        if (optBooleanSafe(sc, "interrupted", false)) {
                            playbackActiveUntilMs.set(0L)
                            flushPendingTurns(interruptedAi = true)
                            try {
                                audioTrack?.pause()
                                audioTrack?.flush()
                                audioTrack?.play()
                            } catch (_: Exception) {
                            }
                        }

                        if (sc.has("modelTurn") && sc.get("modelTurn").isJsonObject) {
                            val parts = sc.getAsJsonObject("modelTurn")?.getAsJsonArray("parts")
                            parts?.forEach { p ->
                                if (p.isJsonObject) {
                                    val pObj = p.asJsonObject
                                    if (pObj.has("inlineData") && pObj.get("inlineData").isJsonObject) {
                                        val b64 = optStringSafe(pObj.getAsJsonObject("inlineData"), "data")
                                        if (b64.isNotBlank()) {
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
                        }

                        if (optBooleanSafe(sc, "turnComplete", false)) {
                            flushPendingTurns(interruptedAi = false)
                            if (finishToolTriggered.get()) {
                                goodbyeTurnDone.set(true)
                            }
                        }
                    }

                    if (root.has("toolCall") && root.get("toolCall").isJsonObject) {
                        val calls = root.getAsJsonObject("toolCall")?.getAsJsonArray("functionCalls")
                        calls?.forEach { c ->
                            if (!c.isJsonObject) return@forEach
                            val cObj = c.asJsonObject
                            val fnName = optStringSafe(cObj, "name")
                            val fnId = optStringSafe(cObj, "id")
                            if (fnName == "phone_secretary_finish") {
                                val args = if (cObj.has("args") && cObj.get("args").isJsonObject) {
                                    cObj.getAsJsonObject("args")
                                } else null
                                val sum = CallNoteVerificationEngine.sanitizeCleanText(optStringSafe(args, "summary"))
                                val actionReq = CallNoteVerificationEngine.sanitizeCleanText(optStringSafe(args, "action_required"))
                                val rahmen = if (args != null && args.has("rahmenbedingungen") && args.get("rahmenbedingungen").isJsonArray) {
                                    args.getAsJsonArray("rahmenbedingungen")
                                        .filter { el -> !el.isJsonNull && el.isJsonPrimitive }
                                        .mapNotNull { el -> CallNoteVerificationEngine.sanitizeCleanText(el.asString).takeIf { it.isNotBlank() } }
                                } else {
                                    emptyList()
                                }
                                val urg = PhoneConciergeManager.normalizeUrgency(optStringSafe(args, "urgency"))
                                val cb = optBooleanSafe(args, "callback_requested", true)

                                if (sum.isNotBlank()) {
                                    completedSummary.set(sum)
                                }
                                if (actionReq.isNotBlank()) {
                                    completedActionRequired.set(actionReq)
                                }
                                if (rahmen.isNotEmpty()) {
                                    completedRahmen.set(rahmen)
                                }
                                completedUrgency.set(urg)
                                completedCallback.set(cb)
                                if (!finishToolTriggered.getAndSet(true)) {
                                    val toolResp = buildToolResponsePacketJson(fnId, fnName, greetingName)
                                    webSocket.send(gson.toJson(toolResp))
                                }
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

            val ready = withTimeoutOrNull(5_500L) { setupReady.await() } == true
            if (!ready) {
                return null
            }

            recordJob = scope.launch {
                streamCallerMicrophoneLoop(
                    appCtx = appCtx,
                    callId = callId,
                    micFgsGranted = micFgsGranted,
                    ws = wsRef,
                    closedFlag = closedFlag,
                    micSilencedFlag = micSilencedFlag,
                    playbackActiveUntilMs = playbackActiveUntilMs,
                    kickoffSentUptimeMs = kickoffSentUptimeMs,
                    firstModelAudioArrived = firstModelAudioArrived,
                    isCallStillOffhook = isCallStillOffhook
                )
            }

            val startedUptime = SystemClock.uptimeMillis()
            var finishDetectedAtMs = 0L

            while (scope.isActive && !sessionFinished.isCompleted) {
                delay(200L)
                val now = SystemClock.uptimeMillis()

                if (!isCallStillOffhook()) {
                    break
                }
                if ((now - startedUptime) >= 90_000L) {
                    break
                }

                if (micSilencedFlag.get() && !finishToolTriggered.get() && callerFullTranscript.isEmpty()) {
                    if (!firstModelAudioArrived.get() && (now - startedUptime) >= 3_500L) {
                        return null
                    }
                    if (firstModelAudioArrived.get() &&
                        now >= (playbackActiveUntilMs.get() + ECHO_TAIL_GUARD_MS) &&
                        !silencePromptSent.getAndSet(true)
                    ) {
                        val hintPacket = de.meinseelenfunke.app.util.FalconClientTools.buildClientContentKickPacket(
                            prompt = "[SYSTEM-HINWEIS: Das Mikrofon ist systemseitig stummgeschaltet. Sag $greetingName jetzt bitte kurz, herzlich und natürlich, dass du die Verbindung notiert hast und Alina sich umgehend meldet, und rufe sofort phone_secretary_finish auf.]",
                            modelName = candidate.liveModel
                        )
                        wsRef?.send(gson.toJson(hintPacket))
                    }
                }

                if (finishToolTriggered.get()) {
                    if (finishDetectedAtMs == 0L) finishDetectedAtMs = now
                    val audioFinished = now >= (playbackActiveUntilMs.get() + 450L)
                    val elapsedSinceFinish = now - finishDetectedAtMs
                    val silentFinishTimeout = !goodbyeAudioArrived.get() && elapsedSinceFinish >= 4_000L
                    if ((goodbyeTurnDone.get() && audioFinished) || silentFinishTimeout || elapsedSinceFinish >= 14_000L) {
                        break
                    }
                }
            }

            flushPendingTurns(interruptedAi = false)
            val durationSec = max(1, ((SystemClock.uptimeMillis() - startedUptime) / 1000L).toInt())
            val rawTranscript = synchronized(transcriptLines) { transcriptLines.toList() }

            val initialSummary = completedSummary.get()?.takeIf { it.isNotBlank() }
                ?: buildFallbackSummaryFromTranscript(callerFullTranscript.toString(), greetingName)
            val initialUrgency = completedUrgency.get()
            val initialCallback = completedCallback.get()
            val initialRahmen = completedRahmen.get()
            val initialAction = completedActionRequired.get()?.takeIf { it.isNotBlank() }
                ?: if (initialCallback) "$greetingName zurückrufen." else "Zur Kenntnis nehmen."

            // KI-gestützte Gegenprüfung des Verstandenen vs. Gesagten (max. 5,5s)
            val verification = if (PhoneConciergeManager.isAutoVerifyEnabled(appCtx)) {
                withTimeoutOrNull(5_500L) {
                    CallNoteVerificationEngine.verifyCallNotes(
                        context = appCtx,
                        input = CallVerificationInput(
                            callerName = greetingName,
                            greetingName = greetingName,
                            transcriptLines = rawTranscript,
                            understoodSummary = initialSummary,
                            understoodRahmenbedingungen = initialRahmen,
                            understoodActionRequired = initialAction,
                            understoodUrgency = initialUrgency,
                            understoodCallbackRequested = initialCallback
                        ),
                        allowNetwork = true
                    )
                }
            } else {
                null
            }

            val useCorrected = verification != null && (
                verification.status == CallNoteVerificationEngine.STATUS_CORRECTED ||
                    (!finishToolTriggered.get() && verification.corrected_summary.isNotBlank())
                )
            val finalSummary = if (useCorrected && verification!!.corrected_summary.isNotBlank()) {
                verification.corrected_summary
            } else {
                initialSummary
            }
            val finalRahmen = if (useCorrected && !verification!!.corrected_rahmenbedingungen.isNullOrEmpty()) {
                verification.corrected_rahmenbedingungen
            } else {
                initialRahmen
            }
            val finalAction = if (useCorrected && verification!!.corrected_action_required.isNotBlank()) {
                verification.corrected_action_required
            } else {
                initialAction
            }
            val finalUrgency = if (useCorrected && verification!!.corrected_urgency.isNotBlank()) {
                verification.corrected_urgency
            } else {
                initialUrgency
            }
            val finalCallback = if (useCorrected) {
                verification!!.corrected_callback_requested
            } else {
                initialCallback
            }

            return SecretarySessionResult(
                summary = finalSummary,
                urgency = finalUrgency,
                callbackRequested = finalCallback,
                usedLiveAi = true,
                rahmenbedingungen = finalRahmen,
                actionRequired = finalAction,
                durationSeconds = durationSec,
                transcript = rawTranscript,
                verification = verification
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

    @Suppress("MissingPermission")
    private suspend fun streamCallerMicrophoneLoop(
        appCtx: Context,
        callId: String,
        micFgsGranted: Boolean,
        ws: WebSocket?,
        closedFlag: AtomicBoolean,
        micSilencedFlag: AtomicBoolean,
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

        // Ensure WakeWordService releases hardware mic & SpeechRecognizer synchronously before opening secretary AudioRecord
        WakeWordService.releaseMicrophoneForCall(timeoutMs = 700L)

        val audioManager = appCtx.getSystemService(Context.AUDIO_SERVICE) as? AudioManager
        val initialMode = try { audioManager?.mode ?: AudioManager.MODE_NORMAL } catch (_: Exception) { AudioManager.MODE_NORMAL }
        val profiles = resolveAudioSourceProfiles(initialMode)

        val sampleRate = 16000
        val channelConfig = AudioFormat.CHANNEL_IN_MONO
        val encoding = AudioFormat.ENCODING_PCM_16BIT
        val minBuf = max(AudioRecord.getMinBufferSize(sampleRate, channelConfig, encoding), 4096)

        // 640 samples @ 16 kHz = crisp 40 ms real-time frames with zero extra sleep latency
        val pcmShortBuf = ShortArray(640)
        val watchdog = MicZeroSampleWatchdog(rotateThresholdFrames = 28, reportThresholdFrames = 65)
        var profileIdx = 0
        var activeRecord: AudioRecord? = null
        var activeProfile: MicCaptureProfile? = null
        var aec: AcousticEchoCanceler? = null
        var ns: NoiseSuppressor? = null
        val testedLabels = mutableListOf<String>()

        fun releaseCurrentRecorder() {
            try { aec?.release() } catch (_: Exception) { }
            try { ns?.release() } catch (_: Exception) { }
            aec = null
            ns = null
            val rec = activeRecord
            activeRecord = null
            if (rec != null) {
                try {
                    if (rec.recordingState == AudioRecord.RECORDSTATE_RECORDING) {
                        rec.stop()
                    }
                } catch (_: Exception) { }
                try { rec.release() } catch (_: Exception) { }
            }
        }

        fun openNextAvailableRecorder(): Boolean {
            releaseCurrentRecorder()
            while (profileIdx < profiles.size) {
                val prof = profiles[profileIdx++]
                testedLabels.add(prof.label)
                val candidate = try {
                    AudioRecord(prof.audioSource, sampleRate, channelConfig, encoding, minBuf)
                } catch (_: Exception) {
                    null
                }
                if (candidate != null && candidate.state == AudioRecord.STATE_INITIALIZED) {
                    try {
                        if (prof.enableHardwareEffects) {
                            if (AcousticEchoCanceler.isAvailable()) {
                                aec = AcousticEchoCanceler.create(candidate.audioSessionId)?.apply { enabled = true }
                            }
                            if (NoiseSuppressor.isAvailable()) {
                                ns = NoiseSuppressor.create(candidate.audioSessionId)?.apply { enabled = true }
                            }
                        }
                        candidate.startRecording()
                        if (candidate.recordingState == AudioRecord.RECORDSTATE_RECORDING) {
                            activeRecord = candidate
                            activeProfile = prof
                            return true
                        }
                    } catch (_: Exception) {
                    }
                }
                try { aec?.release() } catch (_: Exception) { }
                try { ns?.release() } catch (_: Exception) { }
                aec = null
                ns = null
                try { candidate?.release() } catch (_: Exception) { }
            }
            return false
        }

        try {
            if (!openNextAvailableRecorder()) {
                micSilencedFlag.set(true)
                ErrorSentinelReporter.captureError(
                    context = appCtx,
                    tag = "phone_secretary_mic",
                    message = "AudioRecord konnte während des Telefonats nicht initialisiert werden.",
                    extraContext = mapOf(
                        "call_id" to callId,
                        "audio_mode" to initialMode.toString(),
                        "mic_fgs_granted" to micFgsGranted.toString(),
                        "tested_sources" to testedLabels.joinToString(",")
                    )
                )
                return
            }

            while (!closedFlag.get() && isCallStillOffhook()) {
                val rec = activeRecord ?: break
                val read = rec.read(pcmShortBuf, 0, pcmShortBuf.size)
                if (read <= 0) {
                    delay(15L)
                    continue
                }

                // Opening-Greeting Anti-Barge-In Guard + Software Echo Gate:
                // 1) During the first ~1.2s after pickup while Gemini generates its opening greeting, do not let
                //    call-connect clicks or an early "Hallo?" abort Funkira's self-introduction before she speaks.
                // 2) While Funkira's 24 kHz voice is playing on speakerphone, suppress echo back into Gemini VAD.
                val now = SystemClock.uptimeMillis()
                val kickAt = kickoffSentUptimeMs.get()
                if (!firstModelAudioArrived.get() && kickAt > 0L && (now - kickAt) < OPENING_BARGE_IN_GUARD_MS) {
                    continue
                }
                if (now < (playbackActiveUntilMs.get() + ECHO_TAIL_GUARD_MS)) {
                    continue
                }

                val hasMoreCandidates = profileIdx < profiles.size
                when (watchdog.onFrame(pcmShortBuf, read, hasMoreCandidates)) {
                    MicFrameVerdict.ROTATE_SOURCE -> {
                        val failedLabel = activeProfile?.label ?: "unknown"
                        configureSpeakerphoneForSecretaryCall(audioManager)
                        if (openNextAvailableRecorder()) {
                            PhoneConciergeManager.appendDiagnosticTrace(
                                appCtx,
                                "Mikro-Stille auf $failedLabel -> Wechsel auf ${activeProfile?.label ?: "Fallback"}"
                            )
                            ErrorSentinelReporter.captureAutoHeal(
                                context = appCtx,
                                component = "phone_secretary_mic",
                                title = "Mikrofon-Quelle im Anruf automatisch gewechselt",
                                message = "Quelle $failedLabel lieferte >1,1s Stille; auf ${activeProfile?.label} umgeschaltet.",
                                healSummary = "AudioRecord während laufendem Telefonat nahtlos neu initialisiert.",
                                extraContext = mapOf(
                                    "call_id" to callId,
                                    "failed_source" to failedLabel,
                                    "new_source" to (activeProfile?.label ?: ""),
                                    "audio_mode" to (audioManager?.mode?.toString() ?: "-1"),
                                    "mic_fgs_granted" to micFgsGranted.toString()
                                )
                            )
                            continue
                        }
                    }
                    MicFrameVerdict.PERMANENT_SILENCE -> {
                        micSilencedFlag.set(true)
                        PhoneConciergeManager.appendDiagnosticTrace(
                            appCtx,
                            "Warnung: Mikrofon liefert reine Stille (fgsMic=$micFgsGranted, mode=${audioManager?.mode ?: -1})"
                        )
                        ErrorSentinelReporter.captureError(
                            context = appCtx,
                            tag = "phone_secretary_mic",
                            message = "AudioRecord lieferte >2,5s lang ausschließlich Null-Samples während des Anrufs.",
                            extraContext = mapOf(
                                "call_id" to callId,
                                "audio_mode" to (audioManager?.mode?.toString() ?: "-1"),
                                "mic_fgs_granted" to micFgsGranted.toString(),
                                "tested_sources" to testedLabels.joinToString(","),
                                "rotations" to watchdog.recoveryRotations.toString()
                            )
                        )
                    }
                    MicFrameVerdict.ACTIVE, MicFrameVerdict.SILENT_WAIT -> Unit
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
            releaseCurrentRecorder()
        }
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
            val desiredMode = resolveDesiredAudioModeForSecretary(am.mode)
            if (am.mode != desiredMode) {
                am.mode = desiredMode
            }
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
            if (am.mode != AudioManager.MODE_IN_CALL) {
                am.mode = prevMode
            }
            if (prevCallVol >= 0) {
                am.setStreamVolume(AudioManager.STREAM_VOICE_CALL, prevCallVol, 0)
            }
        } catch (_: Exception) {
        }
    }
}
