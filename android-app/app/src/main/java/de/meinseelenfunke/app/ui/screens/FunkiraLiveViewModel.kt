package de.meinseelenfunke.app.ui.screens

import android.Manifest
import android.content.Context
import android.content.pm.PackageManager
import android.media.AudioAttributes
import android.media.AudioDeviceCallback
import android.media.AudioDeviceInfo
import android.media.AudioFormat
import android.media.AudioManager
import android.media.AudioRecord
import android.media.AudioTrack
import android.media.MediaRecorder
import android.os.Build
import androidx.core.content.ContextCompat
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.google.gson.Gson
import com.google.gson.JsonArray
import com.google.gson.JsonObject
import de.meinseelenfunke.app.di.ServiceLocator
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.Response
import okhttp3.WebSocket
import okhttp3.WebSocketListener

class FunkiraLiveViewModel : ViewModel() {

    private val aiRepository = ServiceLocator.aiRepository

    private val _isConnecting = MutableStateFlow(false)
    val isConnecting: StateFlow<Boolean> = _isConnecting.asStateFlow()

    private val _isConnected = MutableStateFlow(false)
    val isConnected: StateFlow<Boolean> = _isConnected.asStateFlow()

    private val _isRecording = MutableStateFlow(false)
    val isRecording: StateFlow<Boolean> = _isRecording.asStateFlow()

    private val _error = MutableStateFlow<String?>(null)
    val error: StateFlow<String?> = _error.asStateFlow()

    private val _voiceAmplitude = MutableStateFlow(0f)
    val voiceAmplitude: StateFlow<Float> = _voiceAmplitude.asStateFlow()

    private val _aiSpeechAmplitude = MutableStateFlow(0f)
    val aiSpeechAmplitude: StateFlow<Float> = _aiSpeechAmplitude.asStateFlow()

    private val _isAiSpeakingFlow = MutableStateFlow(false)
    val isAiSpeakingFlow: StateFlow<Boolean> = _isAiSpeakingFlow.asStateFlow()

    private val _falconEmotion = MutableStateFlow("idle")
    val falconEmotion: StateFlow<String> = _falconEmotion.asStateFlow()

    private val _liveLogs = MutableStateFlow<List<String>>(emptyList())
    val liveLogs: StateFlow<List<String>> = _liveLogs.asStateFlow()

    private val _liveSubtitle = MutableStateFlow<String?>(null)
    val liveSubtitle: StateFlow<String?> = _liveSubtitle.asStateFlow()

    private val aiTurnTranscript = java.lang.StringBuilder()
    private val userTurnTranscript = java.lang.StringBuilder()

    private val _agents = MutableStateFlow<List<de.meinseelenfunke.app.data.api.AiAgent>>(emptyList())
    val agents: StateFlow<List<de.meinseelenfunke.app.data.api.AiAgent>> = _agents.asStateFlow()

    private val _selectedAgent = MutableStateFlow<de.meinseelenfunke.app.data.api.AiAgent?>(null)
    val selectedAgent: StateFlow<de.meinseelenfunke.app.data.api.AiAgent?> = _selectedAgent.asStateFlow()

    private var webSocket: WebSocket? = null
    private val client = OkHttpClient.Builder()
        .protocols(listOf(okhttp3.Protocol.HTTP_1_1))
        .connectTimeout(8, java.util.concurrent.TimeUnit.SECONDS)
        .readTimeout(0, java.util.concurrent.TimeUnit.MILLISECONDS)
        .pingInterval(15, java.util.concurrent.TimeUnit.SECONDS)
        .dns(object : okhttp3.Dns {
            override fun lookup(hostname: String): List<java.net.InetAddress> {
                return okhttp3.Dns.SYSTEM.lookup(hostname).sortedBy { if (it is java.net.Inet4Address) 0 else 1 }
            }
        })
        .build()
    private val gson = Gson()

    // Audio recording & playback threads
    private var recordingJob: Job? = null
    private var connectJob: Job? = null
    private var reconnectJob: Job? = null
    private var visionStreamJob: Job? = null
    private var inactivityWatchJob: Job? = null
    private var turnCompleteSettleJob: Job? = null
    private var speechDecayJob: Job? = null
    private var audioTrack: AudioTrack? = null
    private var audioDeviceCallback: AudioDeviceCallback? = null
    @Volatile private var isRecordingActive = false
    @Volatile private var isAiSpeaking = false
    @Volatile private var playbackActiveUntilUptimeMs = 0L
    @Volatile private var setupCompletedForCurrentSocket = false
    @Volatile private var currentSocketSetupAtUptimeMs = 0L
    @Volatile private var userInitiatedHangup = false
    @Volatile private var userMutedMic = false
    @Volatile private var midCallReconnectCount = 0
    private val activeToolCallCount = java.util.concurrent.atomic.AtomicInteger(0)
    private val inactivityTracker = de.meinseelenfunke.app.util.FalconInactivityTracker()
    private var userManuallyPickedNonFalcon: Boolean = false
    private var fallbackDirectUrl: String? = null
    private var fallbackOriginUrl: String = "https://mein-seelenfunke.de"
    private var activeLiveModel: String = "models/gemini-3.1-flash-live-preview"
    private var activeSysInstruction: String = ""
    private var activeVoiceName: String = "Orus"
    private var activeToolsJson: String? = null
    @Volatile private var pendingProactiveKickPrompt: String? = null

    @Volatile private var shouldEndCall = false
    private val _shouldCloseScreen = MutableStateFlow(false)
    val shouldCloseScreen: StateFlow<Boolean> = _shouldCloseScreen.asStateFlow()

    init {
        loadAgents()
    }

    private fun isFalconeAgentName(name: String?): Boolean {
        return name.equals("Falcone", ignoreCase = true) || name.equals("Falcon", ignoreCase = true)
    }

    private fun displayAgentName(): String {
        val raw = _selectedAgent.value?.name?.trim().orEmpty()
        return if (raw.isBlank() || raw.equals("Falcon", ignoreCase = true)) "Falcone" else raw
    }

    private fun isAiEffectivelySpeaking(): Boolean {
        return isAiSpeaking || android.os.SystemClock.uptimeMillis() < playbackActiveUntilUptimeMs
    }

    fun loadAgents() {
        viewModelScope.launch {
            aiRepository.getAgents()
                .onSuccess { list ->
                    _agents.value = list
                    val defaultAgent = list.find { it.name.equals("Falcone", ignoreCase = true) }
                        ?: list.find { it.name.equals("Falcon", ignoreCase = true) }
                        ?: list.find { it.name.equals("Funkira", ignoreCase = true) }
                        ?: list.firstOrNull()
                    defaultAgent?.let { selectAgent(it, userInitiated = false) }
                }
        }
    }

    fun selectAgent(agent: de.meinseelenfunke.app.data.api.AiAgent, userInitiated: Boolean = true) {
        if (userInitiated) {
            userManuallyPickedNonFalcon = !isFalconeAgentName(agent.name)
        }
        if (_selectedAgent.value?.id == agent.id) return
        
        val wasNull = _selectedAgent.value == null

        if (!wasNull) {
            de.meinseelenfunke.app.util.SoundManager.playSound(de.meinseelenfunke.app.R.raw.top_secret_sound_4)
        }

        _selectedAgent.value = agent
        
        if (!wasNull && (_isConnected.value || _isConnecting.value)) {
            viewModelScope.launch(Dispatchers.Main) {
                disconnectLiveChat()
                delay(300) // Allow previous socket and coroutine cleanup to settle
                startLiveChat()
            }
        }
    }

    fun addLog(text: String) {
        viewModelScope.launch(Dispatchers.Main) {
            val current = _liveLogs.value.toMutableList()
            current.add(text)
            _liveLogs.value = current.takeLast(300) // keep last 300 logs for extensive debugging
        }
    }

    fun clearLogs() {
        _liveLogs.value = emptyList()
    }

    private fun sanitizeAiTranscript(raw: String): String {
        return raw
            .replace(Regex("""(?i)\*?\*?Confirming Operational Context\*?\*?.*?(?:API\.*|$)"""), "")
            .replace(Regex("""(?i)I acknowledge I am operating within.*?(?:API\.*|$)"""), "")
            .trim()
    }

    private fun sanitizeJson(json: String): String {
        try {
            val element = gson.fromJson(json, com.google.gson.JsonElement::class.java)
            if (element.isJsonObject) {
                val obj = element.asJsonObject
                sanitizeObject(obj)
                return gson.toJson(obj)
            }
        } catch (e: Exception) {
            // ignore
        }
        return json
    }

    private fun sanitizeObject(obj: JsonObject) {
        if (obj.has("inlineData")) {
            val inline = obj.getAsJsonObject("inlineData")
            if (inline.has("data")) {
                val len = inline.get("data").asString.length
                inline.addProperty("data", "<Base64 Audio ($len chars)>")
            }
        }
        for (entry in obj.entrySet()) {
            if (entry.value.isJsonObject) {
                sanitizeObject(entry.value.asJsonObject)
            } else if (entry.value.isJsonArray) {
                entry.value.asJsonArray.forEach { el ->
                    if (el.isJsonObject) {
                        sanitizeObject(el.asJsonObject)
                    }
                }
            }
        }
    }

    private var chatSessionId: String = ""

    fun startLiveChat() {
        if (_isConnected.value || _isConnecting.value) return

        reconnectJob?.cancel()
        reconnectJob = null
        userInitiatedHangup = false
        userMutedMic = false
        midCallReconnectCount = 0
        currentSocketSetupAtUptimeMs = 0L
        _isConnecting.value = true
        _error.value = null
        shouldEndCall = false
        setAiSpeakingState(false)
        _falconEmotion.value = "idle"
        _shouldCloseScreen.value = false
        _liveSubtitle.value = null
        aiTurnTranscript.clear()
        userTurnTranscript.clear()
        // Set live call active immediately to yield microphone from background service
        de.meinseelenfunke.app.util.NavigationBridge.isLiveCallActive = true
        addLog("Sitzungsdaten werden geladen...")
        chatSessionId = "android_live_${java.util.UUID.randomUUID()}"

        launchCredentialAndSocketConnect(isMidCallReconnect = false)
    }

    private fun launchCredentialAndSocketConnect(isMidCallReconnect: Boolean) {
        connectJob?.cancel()
        connectJob = viewModelScope.launch(Dispatchers.IO) {
            if (_selectedAgent.value == null) {
                aiRepository.getAgents().onSuccess { list ->
                    _agents.value = list
                    val preferred = list.find { it.name.equals("Falcone", ignoreCase = true) }
                        ?: list.find { it.name.equals("Falcon", ignoreCase = true) }
                        ?: list.find { it.name.equals("Funkira", ignoreCase = true) }
                        ?: list.firstOrNull()
                    if (preferred != null) {
                        _selectedAgent.value = preferred
                    }
                }
            }

            val baseUrl = de.meinseelenfunke.app.di.ServiceLocator.getBaseUrl()
            val parsedUri = try { java.net.URI(baseUrl) } catch (_: Exception) { null }
            val host = parsedUri?.host ?: "stage.mein-seelenfunke.de"
            val defaultOrigin = if (baseUrl.startsWith("https://", ignoreCase = true)) "https://$host" else "https://mein-seelenfunke.de"
            val localGeminiKey = de.meinseelenfunke.app.di.ServiceLocator.getGeminiApiKey().trim()

            aiRepository.getLiveCredentials(
                agentId = _selectedAgent.value?.id,
                chatSessionId = chatSessionId,
                client = "android_native"
            )
                .onSuccess { creds ->
                    if (connectJob?.isActive != true || userInitiatedHangup) return@onSuccess
                    if (!isMidCallReconnect) {
                        addLog("Authentifiziert. Verbindung wird aufgebaut...")
                    } else {
                        addLog("[Auto-Reconnect]: Sitzung mit Gesprächsgedächtnis wiederhergestellt.")
                    }
                    val useSecureWs = baseUrl.startsWith("https://", ignoreCase = true)
                    val defaultScheme = if (useSecureWs) "wss" else "ws"
                    val fallbackWsUrl = if (useSecureWs) "$defaultScheme://$host/gemini-live" else "$defaultScheme://$host:8089/gemini-live"
                    var rawWsUrl = creds.ws_url ?: fallbackWsUrl
                    if (host != "127.0.0.1" && host != "localhost") {
                        rawWsUrl = rawWsUrl
                            .replace("127.0.0.1", host)
                            .replace("localhost", host)
                    }
                    if (useSecureWs && rawWsUrl.startsWith("ws://", ignoreCase = true)) {
                        rawWsUrl = "wss://" + rawWsUrl.substring(5)
                    }

                    fallbackOriginUrl = creds.origin_url?.takeIf { it.isNotBlank() } ?: defaultOrigin
                    fallbackDirectUrl = creds.direct_ws_url?.takeIf { it.isNotBlank() }
                        ?: buildDirectGeminiWsUrl(localGeminiKey)
                    activeLiveModel = creds.live_model?.takeIf { it.isNotBlank() } ?: "models/gemini-3.1-flash-live-preview"
                    val isFalconSelected = _selectedAgent.value == null ||
                        isFalconeAgentName(_selectedAgent.value?.name) ||
                        (!userManuallyPickedNonFalcon && _agents.value.none { isFalconeAgentName(it.name) })
                    activeSysInstruction = if (isFalconSelected && !creds.system_instruction.contains("Falcone", ignoreCase = true)) {
                        "Du bist Falcone (geschrieben F-A-L-C-O-N-E, mit e am Ende), der technische Mitgründer, Chef-Architekt und die direkte rechte Hand von Alina Steinhauer bei Mein-Seelenfunke und Funkenwerk.\n\n" + creds.system_instruction
                    } else {
                        creds.system_instruction
                    }
                    activeVoiceName = if (isFalconSelected) {
                        val serverVoice = creds.voice_name.trim()
                        if (serverVoice.isBlank() || serverVoice.equals("Kore", ignoreCase = true) || serverVoice.equals("Aoede", ignoreCase = true)) {
                            "Orus"
                        } else {
                            serverVoice
                        }
                    } else {
                        creds.voice_name.ifBlank { "Kore" }
                    }
                    activeToolsJson = creds.tools?.toString()
                    pendingProactiveKickPrompt = if (!isMidCallReconnect) {
                        val serverKick = creds.proactive_kick_prompt?.trim()?.takeIf { it.isNotEmpty() }
                        val serverBlockades = creds.pending_ag_blockades.orEmpty()
                        val localBlockades = de.meinseelenfunke.app.util.AgDirectInboxManager.pendingBlockadesFlow.value
                        when {
                            serverKick != null -> serverKick
                            serverBlockades.isNotEmpty() -> {
                                val first = serverBlockades.first()
                                val q = first.pending_question ?: first.ag_response_summary ?: "Wartet auf deine Freigabe."
                                "SYSTEM-STARTIMPULS FÜR FALCONE: Begrüße Alina kurz und sprich SOFORT von dir aus die offene Antigravity-Blockade an " +
                                    "(${serverBlockades.size} wartende Freigabe(n), zuerst: \"${first.title}\", Frage: \"$q\"). " +
                                    "Bestehe freundlich und beharrlich auf einer klaren Antwort oder einem Go, damit du sofort ag_resolve_pending_blockade aufrufen kannst."
                            }
                            isFalconSelected && localBlockades.isNotEmpty() -> {
                                val first = localBlockades.first()
                                val q = first.pendingQuestion ?: first.agResponseSummary ?: "Wartet auf deine Freigabe."
                                "SYSTEM-STARTIMPULS FÜR FALCONE: Begrüße Alina kurz und sprich SOFORT von dir aus die offene Antigravity-Blockade an " +
                                    "(${localBlockades.size} wartende Freigabe(n), zuerst: \"${first.title}\", Frage: \"$q\"). " +
                                    "Bestehe freundlich und beharrlich auf einer klaren Antwort oder einem Go, damit du sofort ag_resolve_pending_blockade aufrufen kannst."
                            }
                            else -> null
                        }
                    } else {
                        null
                    }

                    if (!isTrustedLiveWebSocketHost(rawWsUrl, host)) {
                        val directCandidate = fallbackDirectUrl
                        if (!directCandidate.isNullOrBlank()) {
                            fallbackDirectUrl = null
                            de.meinseelenfunke.app.util.ErrorSentinelReporter.captureAutoHeal(
                                context = ServiceLocator.context,
                                component = "funkira_live_ws",
                                title = "Unbekannter Bridge-Host durch Direkt-WebSocket geheilt",
                                message = "Bridge-URL '$rawWsUrl' wich ab; Direktverbindung aktiviert.",
                                healSummary = "Automatischer Wechsel auf direkte Gemini-Live-Verbindung durchgeführt.",
                                extraContext = mapOf("base_host" to host)
                            )
                            establishWebSocket("", directCandidate, activeSysInstruction, activeVoiceName, activeToolsJson, isDirectGoogle = true)
                            return@onSuccess
                        }
                        _isConnecting.value = false
                        de.meinseelenfunke.app.util.NavigationBridge.isLiveCallActive = false
                        _error.value = "Ungültiger Live-Server-Host blockiert."
                        addLog("Sicherheitsabbruch: Abweichender Live-Host blockiert.")
                        de.meinseelenfunke.app.util.ErrorSentinelReporter.captureError(
                            context = ServiceLocator.context,
                            tag = "funkira_live_ws",
                            message = "Abweichender Live-Server-Host blockiert: $rawWsUrl",
                            extraContext = mapOf("base_host" to host)
                        )
                        return@onSuccess
                    }

                    establishWebSocket(creds.token ?: "", rawWsUrl, activeSysInstruction, activeVoiceName, activeToolsJson, isDirectGoogle = false)
                }
                .onFailure { err ->
                    if (connectJob?.isActive != true || userInitiatedHangup) return@onFailure
                    val directCandidate = buildDirectGeminiWsUrl(localGeminiKey)
                    if (!directCandidate.isNullOrBlank()) {
                        fallbackDirectUrl = null
                        fallbackOriginUrl = defaultOrigin
                        val useFalconFallback = !userManuallyPickedNonFalcon
                        if (activeSysInstruction.isBlank()) {
                            activeSysInstruction = if (useFalconFallback) {
                                "STRIKTE SPRACHREGEL (HÖCHSTE PRIORITÄT): Sprich und antworte ZU 100% AUSSCHLIESSLICH auf DEUTSCH! Du bist Falcone (geschrieben F-A-L-C-O-N-E, mit e am Ende), der technische Mitgründer, Chef-Architekt und die direkte rechte Hand von Alina Steinhauer bei Mein-Seelenfunke und Funkenwerk. Antworte souverän, herzlich, klar und prägnant auf Deutsch."
                            } else {
                                "STRIKTE SPRACHREGEL (HÖCHSTE PRIORITÄT): Sprich und antworte ZU 100% AUSSCHLIESSLICH auf DEUTSCH! Du bist Funkira, Alinas KI-Assistentin von Mein-Seelenfunke. Antworte herzlich, natürlich und prägnant auf Deutsch."
                            }
                        }
                        if (activeVoiceName.isBlank()) {
                            activeVoiceName = if (useFalconFallback) "Orus" else "Kore"
                        }
                        de.meinseelenfunke.app.util.ErrorSentinelReporter.captureAutoHeal(
                            context = ServiceLocator.context,
                            component = "funkira_live_credentials",
                            title = "Portal-Verbindungsproblem per lokalem Gemini-Schlüssel überbrückt",
                            message = err.message ?: "Live-Credentials nicht erreichbar",
                            healSummary = "Falcone wurde auf dem Smartphone trotz Server-Fehler nahtlos über den verschlüsselten lokalen Schlüssel verbunden.",
                            extraContext = mapOf("base_host" to host)
                        )
                        addLog("[Auto-Heilung]: Server kurzzeitig nicht erreichbar. Starte Direkt-Modus...")
                        establishWebSocket("", directCandidate, activeSysInstruction, activeVoiceName, activeToolsJson, isDirectGoogle = true)
                        return@onFailure
                    }

                    if (isMidCallReconnect && shouldAutoReconnectMidCall(
                            wasSetupComplete = true,
                            userInitiatedHangup = userInitiatedHangup,
                            shouldEndCall = shouldEndCall,
                            reconnectAttemptCount = midCallReconnectCount
                        )
                    ) {
                        tryScheduleMidCallReconnect("Netzwerk noch im Wechsel")
                        return@onFailure
                    }

                    _isConnecting.value = false
                    de.meinseelenfunke.app.util.NavigationBridge.isLiveCallActive = false
                    val credErrDetail = safeErrorDetail(err) ?: err.javaClass.simpleName.ifBlank { "Unbekannter Fehler" }
                    _error.value = if (de.meinseelenfunke.app.util.ErrorSentinelReporter.isTransientWebSocketDisconnect(err)) {
                        formatWebSocketFailureMessage(err, httpStatusCode = null, setupWasComplete = false)
                    } else {
                        "Authentifizierung fehlgeschlagen: $credErrDetail"
                    }
                    addLog("Fehler: ${_error.value}")
                    if (!de.meinseelenfunke.app.util.ErrorSentinelReporter.isTransientWebSocketDisconnect(err)) {
                        de.meinseelenfunke.app.util.ErrorSentinelReporter.captureError(
                            context = ServiceLocator.context,
                            tag = "funkira_live_credentials",
                            message = "Funkira Live-Start fehlgeschlagen: $credErrDetail",
                            throwable = err,
                            extraContext = mapOf("base_host" to host)
                        )
                    }
                    handleDisconnect(_error.value ?: "Verbindungsfehler")
                }
        }
    }

    private fun establishWebSocket(
        token: String,
        wsUrl: String,
        systemInstruction: String,
        voiceName: String,
        toolsJson: String?,
        isDirectGoogle: Boolean = false
    ) {
        setupCompletedForCurrentSocket = false
        val encodedToken = android.net.Uri.encode(token)
        val url = if (isDirectGoogle || token.isBlank()) {
            wsUrl
        } else if (wsUrl.contains("?")) {
            "$wsUrl&token=$encodedToken"
        } else {
            "$wsUrl?token=$encodedToken"
        }
        val reqBuilder = Request.Builder().url(url)
        if (isDirectGoogle) {
            reqBuilder.header("Origin", fallbackOriginUrl)
            reqBuilder.header("Referer", fallbackOriginUrl.trimEnd('/') + "/")
        }
        val request = reqBuilder.build()

        // Init AudioTrack for 24kHz speaker playback
        initAudioTrack()

        webSocket = client.newWebSocket(request, object : WebSocketListener() {
            override fun onOpen(ws: WebSocket, response: Response) {
                if (ws != this@FunkiraLiveViewModel.webSocket) return
                _isConnecting.value = false
                _isConnected.value = true
                val maskedUrl = de.meinseelenfunke.app.util.ErrorSentinelReporter.scrubSecrets(url)
                addLog("[WS-Setup]: URL: $maskedUrl")

                // Send Setup configuration payload
                val resolvedSetupModel = if (url.contains("aiplatform.googleapis.com", ignoreCase = true) && activeLiveModel.startsWith("projects/")) {
                    activeLiveModel
                } else {
                    "models/gemini-3.1-flash-live-preview"
                }
                val setup = JsonObject().apply {
                    add("setup", JsonObject().apply {
                        addProperty("model", resolvedSetupModel)
                        add("generationConfig", JsonObject().apply {
                            val modalities = JsonArray()
                            modalities.add("AUDIO")
                            add("responseModalities", modalities)
                            add("speechConfig", JsonObject().apply {
                                add("voiceConfig", JsonObject().apply {
                                    add("prebuiltVoiceConfig", JsonObject().apply {
                                        addProperty("voiceName", voiceName)
                                    })
                                })
                            })
                        })
                        add("systemInstruction", JsonObject().apply {
                            add("parts", JsonArray().apply {
                                add(JsonObject().apply {
                                    val walkRule = "WETTER & SPAZIERGANG-REGEL: Wenn es um das Wetter oder einen Spaziergang geht, sage nur kurz auf Basis des Wetters, ob ein Spaziergang machbar ist und wie man sich für die Temperatur anziehen sollte. Schlage NIEMALS Orte, Seen, Parks oder Routen für einen Spaziergang vor, außer Alina fragt ausdrücklich danach."
                                    val brevityRule = "SPRACHSTIL & TOKEN-SPAR-DISZIPLIN: Bleibe genauso herzlich, freundlich, sympathisch und cool wie immer, aber sprich KURZ, ZACKIG und KNACKIG auf den Punkt (1 bis maximal 3 kurze Sätze ohne Füllwörter oder lange Einleitungen). Antworte NIEMALS mit Floskeln wie 'Ich bin nur ein Sprachmodell und kann dabei nicht helfen', sondern nenne bei Einschränkungen oder Fehlern immer ehrlich den exakten konkreten Grund (z. B. Login abgelaufen, Server nicht erreichbar, Berechtigung oder Werkzeug fehlt) samt Lösung! Strategische 'Was tun wir als Nächstes?'-Tipps (master_get_next_best_actions) gibst du ausschließlich auf Nachfrage von Alina."
                                    var strictPrompt = if (!systemInstruction.contains("STRIKTE SPRACHREGEL")) {
                                        "STRIKTE SPRACHREGEL (HÖCHSTE PRIORITÄT): Sprich und antworte ZU 100% AUSSCHLIESSLICH auf DEUTSCH! Gib NIEMALS englische Statusmeldungen, Bestätigungen oder Floskeln wie 'Confirming Operational Context' oder 'I acknowledge...' aus. Antworte sofort direkt und natürlich auf Deutsch.\n\n" + systemInstruction
                                    } else {
                                        systemInstruction
                                    }
                                    if (!strictPrompt.contains("SPAZIERGANG-REGEL")) {
                                        strictPrompt = "$strictPrompt\n\n$walkRule"
                                    }
                                    if (!strictPrompt.contains("TOKEN-SPAR-DISZIPLIN")) {
                                        strictPrompt = "$strictPrompt\n\n$brevityRule"
                                    }
                                    val falconDeviceToolsRule = "LOKALE SMARTPHONE-SUPERTOOLS: Nutze bei Musikwünschen sofort falcon_music_control (z.B. action='play_sport' für Sport-/Workout-Musik), für Smart-Home-Steckdosen und Lichterketten (wie Fußleisten Lichterkette, Spiegel Lichterkette oder Packtisch) sofort falcon_smarthome_control (action='turn_on', 'turn_off', 'toggle', 'turn_all_on', 'turn_all_off', 'list_devices'), für Kamera-Sicht oder ein Foto falcon_camera_control (action='enable_vision', 'disable_vision', 'switch_camera', 'take_photo'), für Entwicklungsaufträge/Ideen/Bugs an deinen Leitstand Antigravity falcon_send_to_antigravity (setze attach_captured_photo=true wenn gerade ein Foto geschossen wurde), für Statusabfragen falcon_check_antigravity_status, zum Öffnen von App-Bereichen falcon_app_navigate und für deinen 3D-Ausdruck falcon_set_emotion."
                                    if (!strictPrompt.contains("LOKALE SMARTPHONE-SUPERTOOLS")) {
                                        strictPrompt = "$strictPrompt\n\n$falconDeviceToolsRule"
                                    }
                                    addProperty("text", strictPrompt)
                                })
                            })
                        })
                        val toolsElement = if (!toolsJson.isNullOrBlank()) {
                            try {
                                val parsed = gson.fromJson(toolsJson, com.google.gson.JsonElement::class.java)
                                if (parsed.isJsonArray && parsed.asJsonArray.size() > 0) {
                                    val firstTool = parsed.asJsonArray.get(0).asJsonObject
                                    if (firstTool.has("functionDeclarations")) {
                                        val decls = firstTool.getAsJsonArray("functionDeclarations")
                                        appendLocalClientDeclarations(decls)
                                    }
                                }
                                parsed
                            } catch (e: Exception) {
                                createDefaultToolsWithEndCall()
                            }
                        } else {
                            createDefaultToolsWithEndCall()
                        }
                        add("tools", toolsElement)
                        add("inputAudioTranscription", JsonObject())
                        add("outputAudioTranscription", JsonObject())
                    })
                }

                val setupStr = gson.toJson(setup)
                val sent = ws.send(setupStr)
                val agentName = displayAgentName()
                addLog("$agentName-Sitzung wird konfiguriert...")
                if (!sent) {
                    addLog("Fehler: Konfiguration konnte nicht gesendet werden (Send-Buffer voll?)")
                }
            }

            override fun onMessage(ws: WebSocket, text: String) {
                if (ws != this@FunkiraLiveViewModel.webSocket) return
                handleServerMessage(text)
            }

            override fun onMessage(ws: WebSocket, bytes: okio.ByteString) {
                if (ws != this@FunkiraLiveViewModel.webSocket) return
                handleServerMessage(bytes.utf8())
            }

            override fun onFailure(ws: WebSocket, t: Throwable, response: Response?) {
                if (ws != this@FunkiraLiveViewModel.webSocket) return
                val httpCode = response?.code
                val wasSetupComplete = setupCompletedForCurrentSocket
                val disconnectMsg = formatWebSocketFailureMessage(
                    t = t,
                    httpStatusCode = httpCode,
                    setupWasComplete = wasSetupComplete
                )
                if (!wasSetupComplete && !isDirectGoogle && tryFailoverToDirectGemini(disconnectMsg, t)) {
                    return
                }
                refreshReconnectBudgetIfStable(wasSetupComplete)
                if (shouldAutoReconnectMidCall(
                        wasSetupComplete = wasSetupComplete,
                        userInitiatedHangup = userInitiatedHangup,
                        shouldEndCall = shouldEndCall,
                        reconnectAttemptCount = midCallReconnectCount
                    )
                ) {
                    tryScheduleMidCallReconnect(disconnectMsg)
                    return
                }
                val isTransient = de.meinseelenfunke.app.util.ErrorSentinelReporter.isTransientWebSocketDisconnect(t, httpCode)
                if (!isTransient) {
                    _error.value = disconnectMsg
                    de.meinseelenfunke.app.util.ErrorSentinelReporter.captureError(
                        context = ServiceLocator.context,
                        tag = "funkira_live_ws",
                        message = disconnectMsg,
                        throwable = t,
                        extraContext = mapOf(
                            "mode" to if (isDirectGoogle) "direct_google" else "portal_bridge",
                            "http_status" to (httpCode?.toString() ?: "0")
                        )
                    )
                } else if (!shouldEndCall && !(wasSetupComplete && t is java.io.EOFException)) {
                    _error.value = disconnectMsg
                }
                handleDisconnect(disconnectMsg)
            }

            override fun onClosing(ws: WebSocket, code: Int, reason: String) {
                if (ws != this@FunkiraLiveViewModel.webSocket) return
                val wasSetupComplete = setupCompletedForCurrentSocket
                if (!wasSetupComplete && code != 1000 && !isDirectGoogle &&
                    tryFailoverToDirectGemini("Bridge geschlossen ($code: $reason)", null)
                ) {
                    return
                }
                refreshReconnectBudgetIfStable(wasSetupComplete)
                if (shouldAutoReconnectMidCall(
                        wasSetupComplete = wasSetupComplete,
                        userInitiatedHangup = userInitiatedHangup,
                        shouldEndCall = shouldEndCall,
                        reconnectAttemptCount = midCallReconnectCount
                    )
                ) {
                    tryScheduleMidCallReconnect("Socket schließt ($code)")
                    return
                }
                if (code != 1000 && code != 1001) {
                    de.meinseelenfunke.app.util.ErrorSentinelReporter.captureError(
                        context = ServiceLocator.context,
                        tag = "funkira_live_ws_close",
                        message = "WebSocket geschlossen ($code): $reason",
                        extraContext = mapOf("close_code" to code.toString(), "reason" to reason)
                    )
                }
                handleDisconnect("Verbindung schließt: $reason ($code)")
            }

            override fun onClosed(ws: WebSocket, code: Int, reason: String) {
                if (ws != this@FunkiraLiveViewModel.webSocket) return
                val wasSetupComplete = setupCompletedForCurrentSocket
                refreshReconnectBudgetIfStable(wasSetupComplete)
                if (shouldAutoReconnectMidCall(
                        wasSetupComplete = wasSetupComplete,
                        userInitiatedHangup = userInitiatedHangup,
                        shouldEndCall = shouldEndCall,
                        reconnectAttemptCount = midCallReconnectCount
                    )
                ) {
                    tryScheduleMidCallReconnect("Socket beendet ($code)")
                    return
                }
                handleDisconnect("Verbindung beendet: $reason ($code)")
            }
        })
    }

    private fun refreshReconnectBudgetIfStable(wasSetupComplete: Boolean) {
        if (wasSetupComplete &&
            currentSocketSetupAtUptimeMs > 0L &&
            (android.os.SystemClock.uptimeMillis() - currentSocketSetupAtUptimeMs) >= STABLE_CONNECTION_RESET_MS
        ) {
            midCallReconnectCount = 0
        }
    }

    private fun tryScheduleMidCallReconnect(triggerReason: String): Boolean {
        if (userInitiatedHangup || shouldEndCall) return false
        val attemptIdx = midCallReconnectCount
        if (attemptIdx >= RECONNECT_BACKOFF_MS.size) return false
        midCallReconnectCount = attemptIdx + 1
        val delayMs = RECONNECT_BACKOFF_MS[attemptIdx]

        val staleWs = webSocket
        webSocket = null
        setupCompletedForCurrentSocket = false
        try { staleWs?.cancel() } catch (_: Exception) {}

        stopInactivityWatchdog()
        stopVisionStreamLoop()
        stopRecording()
        setAiSpeakingState(false)
        _isConnected.value = false
        _isConnecting.value = true
        _error.value = null
        de.meinseelenfunke.app.util.NavigationBridge.isLiveCallActive = true

        addLog("[Auto-Reconnect]: Kurze Unterbrechung ($triggerReason). Verbinde nahtlos neu (#${attemptIdx + 1})...")
        reconnectJob?.cancel()
        reconnectJob = viewModelScope.launch(Dispatchers.IO) {
            delay(delayMs)
            if (userInitiatedHangup || shouldEndCall) return@launch
            launchCredentialAndSocketConnect(isMidCallReconnect = true)
        }
        return true
    }

    private fun tryFailoverToDirectGemini(triggerReason: String, throwable: Throwable?): Boolean {
        val directUrl = fallbackDirectUrl?.takeIf { it.isNotBlank() } ?: return false
        fallbackDirectUrl = null
        try { webSocket?.cancel() } catch (_: Exception) { }
        webSocket = null
        addLog("[Auto-Heilung]: Bridge gestört ($triggerReason). Wechsle sofort auf Direkt-Verbindung...")
        de.meinseelenfunke.app.util.ErrorSentinelReporter.captureAutoHeal(
            context = ServiceLocator.context,
            component = "funkira_live_ws",
            title = "Bridge-Fehler automatisch per Direkt-WebSocket geheilt",
            message = triggerReason + (throwable?.message?.let { " | $it" } ?: ""),
            healSummary = "Automatischer Failover von der WebSocket-Bridge auf die direkte Gemini-Live-Verbindung erfolgreich ausgelöst.",
            extraContext = mapOf("trigger" to triggerReason.take(140))
        )
        _isConnecting.value = true
        _error.value = null
        establishWebSocket(
            token = "",
            wsUrl = directUrl,
            systemInstruction = activeSysInstruction,
            voiceName = activeVoiceName,
            toolsJson = activeToolsJson,
            isDirectGoogle = true
        )
        return true
    }

    private fun handleServerMessage(jsonText: String) {
        try {
            val root = gson.fromJson(jsonText, JsonObject::class.java) ?: return
            val agentName = displayAgentName()

            // Check if server sent a persisted transcript notice
            if (root.has("type") && root.get("type").asString == "liveTranscriptPersisted") {
                val role = root.get("role")?.asString ?: "system"
                val content = root.get("content")?.asString ?: ""
                val speaker = if (role == "user") "Du" else agentName
                addLog("[Transkript gesichert]: $speaker: $content")
                return
            }

            // Check for server-side error
            if (root.has("error")) {
                val errorObj = root.getAsJsonObject("error")
                val errMsg = errorObj.get("message")?.asString ?: "Unbekannter Serverfehler"
                val errCode = errorObj.get("code")?.asInt ?: 0
                addLog("[System-Fehler]: Server-Fehler ($errCode): $errMsg")
                handleDisconnect("Server-Fehler: $errMsg")
                return
            }

            // Check Gemini Live goAway worker-rotation signal -> seamless reconnect with same chatSessionId!
            if (root.has("goAway")) {
                refreshReconnectBudgetIfStable(setupCompletedForCurrentSocket)
                if (shouldAutoReconnectMidCall(
                        wasSetupComplete = true,
                        userInitiatedHangup = userInitiatedHangup,
                        shouldEndCall = shouldEndCall,
                        reconnectAttemptCount = midCallReconnectCount
                    )
                ) {
                    tryScheduleMidCallReconnect("Gemini goAway")
                    return
                }
            }

            // Check setupComplete response
            if (root.has("setupComplete")) {
                setupCompletedForCurrentSocket = true
                currentSocketSetupAtUptimeMs = android.os.SystemClock.uptimeMillis()
                _error.value = null
                activeToolCallCount.set(0)
                inactivityTracker.onSessionStarted(currentSocketSetupAtUptimeMs)
                addLog("[System]: Setup vollständig abgeschlossen. Gespräch bereit.")
                if (!userMutedMic) {
                    startRecording()
                }
                val kickPrompt = pendingProactiveKickPrompt?.trim()?.takeIf { it.isNotEmpty() }
                pendingProactiveKickPrompt = null
                if (kickPrompt != null) {
                    try {
                        val kickPacket = de.meinseelenfunke.app.util.FalconClientTools.buildClientContentKickPacket(kickPrompt)
                        webSocket?.send(gson.toJson(kickPacket))
                        addLog("[Proaktiv]: Falcone spricht offene Antigravity-Freigabe ab Sekunde 0 an.")
                    } catch (_: Throwable) {
                    }
                }
                startVisionStreamLoop()
                startInactivityWatchdog()
                return
            }

            // Check serverContent response
            if (root.has("serverContent")) {
                val serverContent = root.getAsJsonObject("serverContent")
                
                // Transcription deltas from Gemini Live API
                val otObj = when {
                    serverContent.has("outputTranscription") -> serverContent.getAsJsonObject("outputTranscription")
                    serverContent.has("outputAudioTranscription") -> serverContent.getAsJsonObject("outputAudioTranscription")
                    else -> null
                }
                if (otObj != null && otObj.has("text")) {
                    val delta = otObj.get("text").asString
                    if (delta.isNotBlank()) {
                        inactivityTracker.onAiActivity(android.os.SystemClock.uptimeMillis())
                    }
                    aiTurnTranscript.append(delta)
                    val cleanText = sanitizeAiTranscript(aiTurnTranscript.toString().trim())
                    if (cleanText.isNotBlank()) {
                        _liveSubtitle.value = "$agentName: $cleanText"
                    }
                }

                val itObj = when {
                    serverContent.has("inputTranscription") -> serverContent.getAsJsonObject("inputTranscription")
                    serverContent.has("inputAudioTranscription") -> serverContent.getAsJsonObject("inputAudioTranscription")
                    else -> null
                }
                if (itObj != null && itObj.has("text")) {
                    val delta = itObj.get("text").asString
                    if (delta.isNotBlank()) {
                        inactivityTracker.onUserSpeech(android.os.SystemClock.uptimeMillis())
                    }
                    userTurnTranscript.append(delta)
                    _liveSubtitle.value = "Du: ${userTurnTranscript.toString().trim()}"
                }

                // Handle interruption/barge-in
                if (serverContent.has("interrupted") && serverContent.get("interrupted").asBoolean) {
                    addLog("[System]: Unterbrechung erkannt.")
                    turnCompleteSettleJob?.cancel()
                    playbackActiveUntilUptimeMs = 0L
                    setAiSpeakingState(false)
                    flushAudioPlayback()
                    if (aiTurnTranscript.isNotBlank()) {
                        val cleanText = sanitizeAiTranscript(aiTurnTranscript.toString().trim())
                        if (cleanText.isNotBlank()) {
                            addLog("$agentName (unterbrochen): $cleanText")
                        }
                        aiTurnTranscript.clear()
                    }
                }

                // Handle turnComplete for end_call & inactivity phase transitions
                if (serverContent.has("turnComplete") && serverContent.get("turnComplete").asBoolean) {
                    val remainingPlaybackMs = (playbackActiveUntilUptimeMs - android.os.SystemClock.uptimeMillis())
                        .coerceIn(280L, 6000L)
                    turnCompleteSettleJob?.cancel()
                    turnCompleteSettleJob = viewModelScope.launch {
                        delay(remainingPlaybackMs)
                        setAiSpeakingState(false)
                        inactivityTracker.onAiSpeechFinished(android.os.SystemClock.uptimeMillis())
                    }
                    if (userTurnTranscript.isNotBlank()) {
                        addLog("Du: ${userTurnTranscript.toString().trim()}")
                        userTurnTranscript.clear()
                    }
                    if (aiTurnTranscript.isNotBlank()) {
                        val cleanText = sanitizeAiTranscript(aiTurnTranscript.toString().trim())
                        if (cleanText.isNotBlank()) {
                            addLog("$agentName: $cleanText")
                        }
                        aiTurnTranscript.clear()
                    }
                    if (shouldEndCall) {
                        shouldEndCall = false
                        viewModelScope.launch(Dispatchers.Main) {
                            de.meinseelenfunke.app.util.SoundManager.playSound(de.meinseelenfunke.app.R.raw.click_file_in_project_brain)
                            delay(maxOf(1200L, remainingPlaybackMs))
                            disconnectLiveChat()
                            _shouldCloseScreen.value = true
                        }
                    }
                }

                if (serverContent.has("modelTurn")) {
                    turnCompleteSettleJob?.cancel()
                    setAiSpeakingState(true)
                    inactivityTracker.onAiActivity(android.os.SystemClock.uptimeMillis())
                    val modelTurn = serverContent.getAsJsonObject("modelTurn")
                    if (modelTurn.has("parts")) {
                        val parts = modelTurn.getAsJsonArray("parts")
                        parts.forEach { part ->
                            val partObj = part.asJsonObject
                            
                            // Check text transcript
                            if (partObj.has("text")) {
                                val textPart = partObj.get("text").asString
                                aiTurnTranscript.append(textPart)
                                _liveSubtitle.value = "$agentName: ${aiTurnTranscript.toString().trim()}"
                            }

                            // Check audio data
                            if (partObj.has("inlineData")) {
                                val inlineData = partObj.getAsJsonObject("inlineData")
                                val dataBase64 = inlineData.get("data").asString
                                val rawPcm = android.util.Base64.decode(dataBase64, android.util.Base64.NO_WRAP)
                                writeAudioPlayback(rawPcm)
                            }
                        }
                    }
                }
            }

            // Check toolCall triggers
            if (root.has("toolCall")) {
                val toolCall = root.getAsJsonObject("toolCall")
                if (toolCall.has("functionCalls")) {
                    val calls = toolCall.getAsJsonArray("functionCalls")
                    calls.forEach { call ->
                        val callObj = call.asJsonObject
                        val name = callObj.get("name").asString
                        val callId = callObj.get("id").asString
                        addLog("[Aktion]: $agentName ruft Tool '$name' auf.")

                        if (name == "end_call") {
                            shouldEndCall = true
                            val resultObj = JsonObject().apply {
                                add("output", JsonObject().apply {
                                    addProperty("status", "success")
                                })
                            }
                            sendToolResponse(callId, name, resultObj)
                            // Backup timeout to prevent hanging if turnComplete is missed
                            viewModelScope.launch(Dispatchers.Main) {
                                delay(3500)
                                if (shouldEndCall) {
                                    shouldEndCall = false
                                    de.meinseelenfunke.app.util.SoundManager.playSound(de.meinseelenfunke.app.R.raw.click_file_in_project_brain)
                                    delay(1000)
                                    disconnectLiveChat()
                                    _shouldCloseScreen.value = true
                                }
                            }
                            return@forEach
                        }

                        if (name == "alarm_shift_or_snooze") {
                            inactivityTracker.onToolActivity(android.os.SystemClock.uptimeMillis())
                            val argsObj = if (callObj.has("args") && callObj.get("args").isJsonObject) callObj.getAsJsonObject("args") else null
                            val mins = argsObj?.get("minutes")?.asInt ?: 10
                            val msg = de.meinseelenfunke.app.util.FunkenwerkAlarmManager.shiftActiveOrNextAlarmFromTool(ServiceLocator.context, mins)
                            addLog("[Wecker-Steuerung]: $msg")
                            val resultObj = JsonObject().apply {
                                addProperty("status", "success")
                                addProperty("message", msg)
                            }
                            sendToolResponse(callId, name, resultObj)
                            return@forEach
                        }

                        if (name == "alarm_disable_today") {
                            inactivityTracker.onToolActivity(android.os.SystemClock.uptimeMillis())
                            val msg = de.meinseelenfunke.app.util.FunkenwerkAlarmManager.disableAlarmTodayFromTool(ServiceLocator.context)
                            addLog("[Wecker-Steuerung]: $msg")
                            val resultObj = JsonObject().apply {
                                addProperty("status", "success")
                                addProperty("message", msg)
                            }
                            sendToolResponse(callId, name, resultObj)
                            return@forEach
                        }

                        if (de.meinseelenfunke.app.util.FalconClientTools.isLocalFalconTool(name)) {
                            val localArgs = if (callObj.has("args")) callObj.get("args") else null
                            activeToolCallCount.incrementAndGet()
                            inactivityTracker.onToolActivity(android.os.SystemClock.uptimeMillis())
                            viewModelScope.launch(Dispatchers.IO) {
                                try {
                                    val resObj = de.meinseelenfunke.app.util.FalconClientTools.executeLocalFalconTool(
                                        context = ServiceLocator.context,
                                        toolName = name,
                                        argsElement = localArgs,
                                        chatSessionId = chatSessionId,
                                        onEmotionChanged = { emo -> _falconEmotion.value = emo },
                                        onNavigate = { _ -> }
                                    )
                                    addLog("[Falcone-Tool]: '$name' lokal ausgeführt.")
                                    sendToolResponse(callId, name, resObj)
                                    if (name == "falcon_send_to_antigravity" ||
                                        name == "falcon_resolve_antigravity_blockade" ||
                                        name == "falcon_check_antigravity_status"
                                    ) {
                                        de.meinseelenfunke.app.util.AgDirectInboxManager.refreshFromServer(ServiceLocator.context)
                                    }
                                } finally {
                                    activeToolCallCount.decrementAndGet()
                                    inactivityTracker.onToolActivity(android.os.SystemClock.uptimeMillis())
                                }
                            }
                            return@forEach
                        }
                        
                        activeToolCallCount.incrementAndGet()
                        inactivityTracker.onToolActivity(android.os.SystemClock.uptimeMillis())
                        viewModelScope.launch(Dispatchers.IO) {
                            try {
                                val args = if (callObj.has("args")) callObj.get("args") else null
                                aiRepository.executeTool(name, args, chatSessionId)
                                    .onSuccess { response ->
                                        val resultElement = response.result ?: JsonObject()
                                        val resultObj = if (resultElement.isJsonObject) resultElement.asJsonObject else JsonObject()
                                        addLog("[Aktion-Ergebnis]: Tool '$name' ausgeführt.")
                                        sendToolResponse(callId, name, resultElement)
                                        if (name == "ag_resolve_pending_blockade" ||
                                            name == "ag_list_pending_blockades" ||
                                            name == "system_prepare_antigravity_handoff"
                                        ) {
                                            de.meinseelenfunke.app.util.AgDirectInboxManager.refreshFromServer(ServiceLocator.context)
                                        }

                                        // Process agent switching logic
                                        if (name == "system_switch_agent" && resultObj.has("agent_id")) {
                                            val newAgentId = resultObj.get("agent_id")?.asString
                                            if (!newAgentId.isNullOrEmpty()) {
                                                val targetAgent = _agents.value.find { it.id == newAgentId }
                                                if (targetAgent != null) {
                                                    viewModelScope.launch(Dispatchers.Main) {
                                                        addLog("[System]: Wechsle zu Agent '${targetAgent.name}'...")
                                                        selectAgent(targetAgent)
                                                    }
                                                }
                                            }
                                        }

                                        // Process UI / Navigation events
                                        if (resultObj.has("_frontend_events") && resultObj.get("_frontend_events").isJsonArray) {
                                            val arr = resultObj.getAsJsonArray("_frontend_events")
                                            arr.forEach { el ->
                                                if (el.isJsonObject) {
                                                    handleFrontendEvent(el.asJsonObject)
                                                }
                                            }
                                        }
                                        val eventObj = when {
                                            resultObj.has("_frontend_event") && resultObj.get("_frontend_event").isJsonObject -> resultObj.getAsJsonObject("_frontend_event")
                                            resultObj.has("_event") && resultObj.get("_event").isJsonObject -> resultObj.getAsJsonObject("_event")
                                            else -> null
                                        }
                                        eventObj?.let { handleFrontendEvent(it) }
                                    }
                                    .onFailure { err ->
                                        val toolErrDetail = safeErrorDetail(err) ?: err.javaClass.simpleName.ifBlank { "Tool-Fehler" }
                                        addLog("[Aktion-Fehler]: Tool '$name' fehlgeschlagen: $toolErrDetail")
                                        val errorJson = JsonObject().apply {
                                            addProperty("status", "error")
                                            addProperty("message", toolErrDetail)
                                        }
                                        sendToolResponse(callId, name, errorJson)
                                    }
                            } finally {
                                activeToolCallCount.decrementAndGet()
                                inactivityTracker.onToolActivity(android.os.SystemClock.uptimeMillis())
                            }
                        }
                    }
                }
            }


        } catch (e: Exception) {
            e.printStackTrace()
            val parseErrDetail = safeErrorDetail(e) ?: e.javaClass.simpleName.ifBlank { "Verarbeitungsfehler" }
            addLog("[System-Fehler]: Exception beim Verarbeiten der Nachricht: $parseErrDetail")
        }
    }

    private fun sendToolResponse(callId: String, functionName: String, result: com.google.gson.JsonElement) {
        val response = JsonObject().apply {
            add("toolResponse", JsonObject().apply {
                add("functionResponses", JsonArray().apply {
                    add(JsonObject().apply {
                        add("response", result)
                        addProperty("id", callId)
                        addProperty("name", functionName)
                    })
                })
            })
        }
        webSocket?.send(gson.toJson(response))
    }

    private fun sendAudioMessage(base64Data: String) {
        val ws = webSocket
        if (ws == null) {
            android.util.Log.w("FunkiraLive", "WebSocket is null, cannot send audio")
            return
        }
        val audioMsg = JsonObject().apply {
            add("realtimeInput", JsonObject().apply {
                add("audio", JsonObject().apply {
                    addProperty("mimeType", "audio/pcm;rate=16000")
                    addProperty("data", base64Data)
                })
            })
        }
        val sent = ws.send(gson.toJson(audioMsg))
        if (!sent) {
            android.util.Log.e("FunkiraLive", "WebSocket send failed for audio chunk")
        }
    }

    fun refreshAudioRouting() {
        if (_isConnected.value) {
            setCommunicationMode(true)
        }
    }

    private fun setCommunicationMode(enable: Boolean) {
        try {
            val audioManager = ServiceLocator.context.getSystemService(Context.AUDIO_SERVICE) as AudioManager
            if (enable) {
                audioManager.mode = AudioManager.MODE_IN_COMMUNICATION

                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                    val devices = audioManager.availableCommunicationDevices
                    val btHeadset = devices.firstOrNull {
                        it.type == AudioDeviceInfo.TYPE_BLUETOOTH_SCO || it.type == AudioDeviceInfo.TYPE_BLE_HEADSET
                    }
                    val wiredHeadset = devices.firstOrNull {
                        it.type == AudioDeviceInfo.TYPE_WIRED_HEADSET ||
                            it.type == AudioDeviceInfo.TYPE_WIRED_HEADPHONES ||
                            it.type == AudioDeviceInfo.TYPE_USB_HEADSET
                    }
                    val speaker = devices.firstOrNull { it.type == AudioDeviceInfo.TYPE_BUILTIN_SPEAKER }

                    val targetHeadset = btHeadset ?: wiredHeadset
                    if (targetHeadset != null) {
                        audioManager.setCommunicationDevice(targetHeadset)
                        audioManager.isSpeakerphoneOn = false
                        val devName = targetHeadset.productName?.toString()?.takeIf { it.isNotBlank() }
                            ?: targetHeadset.type.toString()
                        addLog("[Audio] Audio auf Headset geroutet: $devName")
                    } else {
                        speaker?.let { audioManager.setCommunicationDevice(it) }
                        audioManager.isSpeakerphoneOn = true
                        addLog("[Audio] Kein Headset gefunden. Lautsprecher aktiviert.")
                    }
                } else {
                    val isBt = try {
                        audioManager.isBluetoothScoAvailableOffCall && (audioManager.isBluetoothA2dpOn || audioManager.isBluetoothScoOn)
                    } catch (_: Exception) { false }
                    if (isBt) {
                        audioManager.startBluetoothSco()
                        audioManager.isBluetoothScoOn = true
                        audioManager.isSpeakerphoneOn = false
                        addLog("[Audio] Audio auf Bluetooth geroutet (Legacy SCO)")
                    } else {
                        audioManager.isSpeakerphoneOn = true
                        addLog("[Audio] Kein Bluetooth Headset gefunden. Lautsprecher aktiviert.")
                    }
                }

                if (audioDeviceCallback == null) {
                    val callback = object : AudioDeviceCallback() {
                        override fun onAudioDevicesAdded(addedDevices: Array<out AudioDeviceInfo>?) {
                            if (_isConnected.value) {
                                refreshAudioRouting()
                            }
                        }

                        override fun onAudioDevicesRemoved(removedDevices: Array<out AudioDeviceInfo>?) {
                            if (_isConnected.value) {
                                refreshAudioRouting()
                            }
                        }
                    }
                    audioManager.registerAudioDeviceCallback(callback, null)
                    audioDeviceCallback = callback
                }
            } else {
                audioDeviceCallback?.let {
                    try {
                        audioManager.unregisterAudioDeviceCallback(it)
                    } catch (_: Exception) {}
                    audioDeviceCallback = null
                }

                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                    try {
                        audioManager.clearCommunicationDevice()
                    } catch (_: Exception) {}
                } else {
                    try {
                        if (audioManager.isBluetoothScoOn) {
                            audioManager.stopBluetoothSco()
                            audioManager.isBluetoothScoOn = false
                        }
                    } catch (_: Exception) {}
                }

                audioManager.isSpeakerphoneOn = false
                audioManager.mode = AudioManager.MODE_NORMAL
                addLog("[Audio] Normalmodus wiederhergestellt")
            }
        } catch (e: Exception) {
            addLog("[Audio] Fehler beim Einstellen des Audio-Modus: ${e.message}")
        }
    }

    private fun initAudioTrack() {
        try {
            val existing = audioTrack
            if (existing != null && existing.state == AudioTrack.STATE_INITIALIZED) {
                if (existing.playState != AudioTrack.PLAYSTATE_PLAYING) {
                    existing.play()
                }
                return
            }
            try {
                existing?.release()
            } catch (_: Exception) {
            }
            audioTrack = null

            setCommunicationMode(true)
            
            val sampleRate = 24000
            val channelConfig = AudioFormat.CHANNEL_OUT_MONO
            val audioFormat = AudioFormat.ENCODING_PCM_16BIT
            val minBufSize = AudioTrack.getMinBufferSize(sampleRate, channelConfig, audioFormat)
            val bufferSize = maxOf(minBufSize, 16384)

            audioTrack = AudioTrack.Builder()
                .setAudioAttributes(
                    AudioAttributes.Builder()
                        .setUsage(AudioAttributes.USAGE_VOICE_COMMUNICATION)
                        .setContentType(AudioAttributes.CONTENT_TYPE_SPEECH)
                        .build()
                )
                .setAudioFormat(
                    AudioFormat.Builder()
                        .setChannelMask(channelConfig)
                        .setEncoding(audioFormat)
                        .setSampleRate(sampleRate)
                        .build()
                )
                .setBufferSizeInBytes(bufferSize)
                .setTransferMode(AudioTrack.MODE_STREAM)
                .build()
            
            val state = audioTrack?.state
            addLog("[Audio] Initialisiert: State = $state")
            if (state == AudioTrack.STATE_INITIALIZED) {
                audioTrack?.setVolume(1.0f)
                audioTrack?.play()
                addLog("[Audio] Wiedergabe gestartet (PlayState = ${audioTrack?.playState})")
            } else {
                addLog("[Audio] Fehler: Initialisierung fehlgeschlagen!")
            }
        } catch (e: Exception) {
            addLog("[Audio] Initialisierungsfehler: ${e.message}")
            e.printStackTrace()
        }
    }

    private fun writeAudioPlayback(pcmBytes: ByteArray) {
        try {
            val track = audioTrack
            if (track == null) {
                addLog("[Audio] Fehler: Track ist null")
                return
            }
            if (track.state != AudioTrack.STATE_INITIALIZED) {
                addLog("[Audio] Fehler: Track nicht initialisiert")
                return
            }
            if (track.playState != AudioTrack.PLAYSTATE_PLAYING) {
                addLog("[Audio] Warnung: Track spielte nicht (State: ${track.playState})")
                track.play()
            }
            
            // Apply software digital gain (e.g., 2.5x volume boost) & compute speech amplitude for 3D lip-sync
            val boostedBytes = ByteArray(pcmBytes.size)
            var peakSample = 0
            for (i in 0 until pcmBytes.size step 2) {
                if (i + 1 < pcmBytes.size) {
                    val low = pcmBytes[i].toInt() and 0xFF
                    val high = pcmBytes[i+1].toInt()
                    val sample = ((high shl 8) or low).toShort()
                    val absRaw = kotlin.math.abs(sample.toInt())
                    if (absRaw > peakSample) peakSample = absRaw
                    val boosted = (sample * 2.5f).toInt()
                    val clamped = maxOf(Short.MIN_VALUE.toInt(), minOf(Short.MAX_VALUE.toInt(), boosted))
                    boostedBytes[i] = (clamped and 0xFF).toByte()
                    boostedBytes[i+1] = ((clamped ushr 8) and 0xFF).toByte()
                }
            }
            _aiSpeechAmplitude.value = (peakSample / 15000f).coerceIn(0f, 1f)
            speechDecayJob?.cancel()
            speechDecayJob = viewModelScope.launch {
                delay(280L)
                _aiSpeechAmplitude.value = 0f
            }

            val written = track.write(boostedBytes, 0, boostedBytes.size)
            if (written < 0) {
                addLog("[Audio] Schreibfehler: $written")
            } else {
                val chunkDurationMs = (written * 1000L) / (24000L * 2L)
                val nowUptime = android.os.SystemClock.uptimeMillis()
                val baseEnd = if (playbackActiveUntilUptimeMs > nowUptime) playbackActiveUntilUptimeMs else nowUptime
                playbackActiveUntilUptimeMs = baseEnd + chunkDurationMs
                inactivityTracker.onAiActivity(nowUptime)
                android.util.Log.d("AudioPlayback", "$written Bytes played")
            }
        } catch (e: Exception) {
            addLog("[Audio] Wiedergabeausnahme: ${e.message}")
            e.printStackTrace()
        }
    }

    private fun flushAudioPlayback() {
        playbackActiveUntilUptimeMs = 0L
        try {
            audioTrack?.apply {
                flush()
                stop()
                play()
            }
        } catch (e: Exception) {
            e.printStackTrace()
        }
    }

    private fun startRecording() {
        if (isRecordingActive) return

        if (ContextCompat.checkSelfPermission(ServiceLocator.context, Manifest.permission.RECORD_AUDIO) != PackageManager.PERMISSION_GRANTED) {
            addLog("[System]: Keine Mikrofon-Berechtigung!")
            _error.value = "Mikrofon-Berechtigung fehlt."
            return
        }

        isRecordingActive = true
        _isRecording.value = true
        de.meinseelenfunke.app.util.NavigationBridge.isLiveCallActive = true

        recordingJob = viewModelScope.launch(Dispatchers.IO) {
            var audioRecord: AudioRecord? = null
            try {
                val sampleRate = 16000
                val channelConfig = AudioFormat.CHANNEL_IN_MONO
                val audioFormat = AudioFormat.ENCODING_PCM_16BIT
                val bufferSize = AudioRecord.getMinBufferSize(sampleRate, channelConfig, audioFormat)

                audioRecord = try {
                    AudioRecord(
                        MediaRecorder.AudioSource.VOICE_COMMUNICATION,
                        sampleRate,
                        channelConfig,
                        audioFormat,
                        bufferSize
                    )
                } catch (e: Exception) {
                    AudioRecord(
                        MediaRecorder.AudioSource.MIC,
                        sampleRate,
                        channelConfig,
                        audioFormat,
                        bufferSize
                    )
                }

                if (audioRecord.state != AudioRecord.STATE_INITIALIZED) {
                    addLog("[System]: Fehler beim Initialisieren des Mikrofons (State: ${audioRecord.state})")
                    return@launch
                }

                try {
                    audioRecord.startRecording()
                } catch (e: Exception) {
                    addLog("[System]: Fehler beim Starten des Mikrofons: ${e.message}")
                    return@launch
                }

                if (audioRecord.recordingState != AudioRecord.RECORDSTATE_RECORDING) {
                    addLog("[System]: Mikrofon konnte nicht gestartet werden (State: ${audioRecord.recordingState})")
                    return@launch
                }

                val buffer = ShortArray(1024)
                val userSpeechGate = de.meinseelenfunke.app.services.WakeWordService.Companion.SpeechCadenceGate(
                    requiredVoicedFrames = 3,
                    windowSize = 8
                )

                while (isRecordingActive) {
                    if (webSocket == null) {
                        break
                    }
                    val read = audioRecord.read(buffer, 0, buffer.size)
                    if (read > 0) {
                        var maxVal = 0
                        for (i in 0 until read) {
                            val v = Math.abs(buffer[i].toInt())
                            if (v > maxVal) maxVal = v
                        }

                        // Echo-Schutz & lokale Barge-In-Erkennung:
                        // Während die KI über den Lautsprecher spricht, senden wir kein Echo zurück,
                        // es sei denn, der Nutzer unterbricht deutlich hörbar (Google VAD übernimmt das Interrupt-Handling nativ über realtimeInput.audio).
                        if (isAiEffectivelySpeaking()) {
                            userSpeechGate.reset()
                            if (maxVal > 12000) {
                                android.util.Log.d("FunkiraLive", "Local user interruption detected (amplitude: $maxVal). Flushing audio playback.")
                                turnCompleteSettleJob?.cancel()
                                setAiSpeakingState(false)
                                flushAudioPlayback()
                                inactivityTracker.onUserSpeech(android.os.SystemClock.uptimeMillis())
                            } else {
                                _voiceAmplitude.value = 0f
                                delay(20)
                                continue
                            }
                        } else if (userSpeechGate.onFrame(buffer, read)) {
                            inactivityTracker.onUserSpeech(android.os.SystemClock.uptimeMillis())
                        }

                        _voiceAmplitude.value = maxVal.toFloat() / 32768f

                        // convert ShortArray to ByteArray (little-endian)
                        val bytes = ByteArray(read * 2)
                        for (i in 0 until read) {
                            val sh = buffer[i]
                            bytes[i * 2] = (sh.toInt() and 0xFF).toByte()
                            bytes[i * 2 + 1] = ((sh.toInt() shr 8) and 0xFF).toByte()
                        }

                        // Send base64 pcm packet to server
                        val base64 = android.util.Base64.encodeToString(bytes, android.util.Base64.NO_WRAP)
                        sendAudioMessage(base64)
                    } else if (read < 0) {
                        android.util.Log.e("FunkiraLive", "AudioRecord read error: $read")
                    }
                    delay(20)
                }
            } catch (e: Exception) {
                e.printStackTrace()
            } finally {
                try {
                    if (audioRecord?.recordingState == AudioRecord.RECORDSTATE_RECORDING) {
                        audioRecord.stop()
                    }
                } catch (_: Exception) {
                }
                try {
                    audioRecord?.release()
                } catch (_: Exception) {
                }
                isRecordingActive = false
                _isRecording.value = false
            }
        }
    }

    private fun stopRecording() {
        isRecordingActive = false
        _isRecording.value = false
        _voiceAmplitude.value = 0f
        recordingJob?.cancel()
        recordingJob = null
    }

    private fun sendRealtimeSystemPrompt(prompt: String) {
        val ws = webSocket ?: return
        val packet = de.meinseelenfunke.app.util.FalconClientTools.buildRealtimeTextPromptPacket(prompt)
        ws.send(gson.toJson(packet))
    }

    private fun startInactivityWatchdog() {
        inactivityWatchJob?.cancel()
        inactivityWatchJob = viewModelScope.launch(Dispatchers.IO) {
            while (_isConnected.value && setupCompletedForCurrentSocket) {
                delay(500L)
                if (!_isConnected.value || !setupCompletedForCurrentSocket) break
                refreshReconnectBudgetIfStable(setupCompletedForCurrentSocket)
                val action = inactivityTracker.evaluate(
                    nowMs = android.os.SystemClock.uptimeMillis(),
                    isAiSpeaking = isAiEffectivelySpeaking(),
                    activeToolCalls = activeToolCallCount.get()
                )
                when (action) {
                    de.meinseelenfunke.app.util.FalconInactivityAction.NONE -> {}
                    de.meinseelenfunke.app.util.FalconInactivityAction.SEND_STAGE1_CHECK -> {
                        addLog("[Inaktivität]: 2 Minuten Stille erkannt. Falcone fragt kurz nach...")
                        sendRealtimeSystemPrompt(de.meinseelenfunke.app.util.FalconClientTools.STAGE1_INACTIVITY_PROMPT)
                    }
                    de.meinseelenfunke.app.util.FalconInactivityAction.SEND_STAGE2_FAREWELL -> {
                        addLog("[Inaktivität]: Keine Antwort. Falcone verabschiedet sich automatisch...")
                        sendRealtimeSystemPrompt(de.meinseelenfunke.app.util.FalconClientTools.STAGE2_AUTO_END_PROMPT)
                    }
                    de.meinseelenfunke.app.util.FalconInactivityAction.FORCE_DISCONNECT -> {
                        shouldEndCall = false
                        viewModelScope.launch(Dispatchers.Main) {
                            de.meinseelenfunke.app.util.SoundManager.playSound(de.meinseelenfunke.app.R.raw.click_file_in_project_brain)
                            disconnectLiveChat()
                            _shouldCloseScreen.value = true
                        }
                        break
                    }
                }
            }
        }
    }

    private fun stopInactivityWatchdog() {
        inactivityWatchJob?.cancel()
        inactivityWatchJob = null
        turnCompleteSettleJob?.cancel()
        turnCompleteSettleJob = null
    }

    fun toggleMute() {
        if (_isRecording.value) {
            userMutedMic = true
            stopRecording()
            addLog("[System]: Mikrofon stummgeschaltet.")
        } else {
            userMutedMic = false
            startRecording()
            addLog("[System]: Mikrofon aktiviert.")
        }
    }

    private fun setAiSpeakingState(speaking: Boolean) {
        isAiSpeaking = speaking
        _isAiSpeakingFlow.value = speaking
        if (!speaking) {
            speechDecayJob?.cancel()
            _aiSpeechAmplitude.value = 0f
        }
        try {
            de.meinseelenfunke.app.util.MusicPlayerManager.setVoiceDucking(speaking)
        } catch (_: Throwable) { }
    }

    private fun startVisionStreamLoop() {
        visionStreamJob?.cancel()
        visionStreamJob = viewModelScope.launch(Dispatchers.IO) {
            while (_isConnected.value && setupCompletedForCurrentSocket) {
                try {
                    if (de.meinseelenfunke.app.util.FalconCameraController.isVisionActive.value) {
                        val b64 = de.meinseelenfunke.app.util.FalconCameraController.getLatestVisionFrameBase64()
                        val ws = webSocket
                        if (!b64.isNullOrBlank() && ws != null) {
                            val packet = de.meinseelenfunke.app.util.FalconClientTools.buildRealtimeVideoFramePacket(b64)
                            ws.send(gson.toJson(packet))
                        }
                    }
                } catch (_: Throwable) {
                }
                delay(1000L)
            }
        }
    }

    private fun stopVisionStreamLoop() {
        visionStreamJob?.cancel()
        visionStreamJob = null
    }

    private fun handleDisconnect(reason: String) {
        _isConnected.value = false
        _isConnecting.value = false
        setupCompletedForCurrentSocket = false
        pendingProactiveKickPrompt = null
        playbackActiveUntilUptimeMs = 0L
        activeToolCallCount.set(0)
        setAiSpeakingState(false)
        _falconEmotion.value = "idle"
        stopInactivityWatchdog()
        stopVisionStreamLoop()
        connectJob?.cancel()
        connectJob = null
        stopRecording()
        // Immediately yield microphone back to "Hey Falcone" WakeWordService!
        de.meinseelenfunke.app.util.NavigationBridge.isLiveCallActive = false
        releaseAudioTrack()
        if (aiTurnTranscript.isNotBlank()) {
            val agentName = displayAgentName()
            addLog("$agentName: ${aiTurnTranscript.toString().trim()}")
            aiTurnTranscript.clear()
        }
        if (userTurnTranscript.isNotBlank()) {
            addLog("Du: ${userTurnTranscript.toString().trim()}")
            userTurnTranscript.clear()
        }
        _liveSubtitle.value = null
        addLog("[System]: Getrennt ($reason)")
    }

    private fun releaseAudioTrack() {
        try {
            audioTrack?.apply {
                stop()
                release()
            }
            audioTrack = null
            setCommunicationMode(false)
        } catch (e: Exception) {
            e.printStackTrace()
        }
    }

    fun disconnectLiveChat() {
        userInitiatedHangup = true
        reconnectJob?.cancel()
        reconnectJob = null
        val ws = webSocket
        webSocket = null
        try {
            ws?.close(1000, "User quit")
        } catch (_: Exception) {
        }
        handleDisconnect("Vom Benutzer getrennt")
    }

    override fun onCleared() {
        super.onCleared()
        disconnectLiveChat()
        setCommunicationMode(false)
    }

    private fun appendLocalClientDeclarations(decls: JsonArray) {
        val existingNames = mutableSetOf<String>()
        for (i in 0 until decls.size()) {
            val n = decls.get(i)?.asJsonObject?.get("name")?.asString
            if (!n.isNullOrBlank()) existingNames.add(n)
        }
        if (!existingNames.contains("end_call")) {
            decls.add(JsonObject().apply {
                addProperty("name", "end_call")
                addProperty("description", "Beendet das aktuelle Live-Gespräch sofort, wenn sich der Nutzer verabschiedet hat.")
                add("parameters", JsonObject().apply {
                    addProperty("type", "OBJECT")
                    add("properties", JsonObject())
                })
            })
        }
        if (!existingNames.contains("alarm_shift_or_snooze")) {
            decls.add(JsonObject().apply {
                addProperty("name", "alarm_shift_or_snooze")
                addProperty("description", "Verschiebt den aktuellen oder nächsten Wecker in der Android-App um die gewünschte Anzahl an Minuten nach hinten (Schlummern/Verschieben) und synchronisiert die neue Aufstehzeit mit /admin/routine.")
                add("parameters", JsonObject().apply {
                    addProperty("type", "OBJECT")
                    add("properties", JsonObject().apply {
                        add("minutes", JsonObject().apply {
                            addProperty("type", "INTEGER")
                            addProperty("description", "Anzahl der Minuten, um die der Wecker nach hinten verschoben werden soll (z.B. 10, 15, 20, 30).")
                        })
                    })
                    add("required", JsonArray().apply { add("minutes") })
                })
            })
        }
        if (!existingNames.contains("alarm_disable_today")) {
            decls.add(JsonObject().apply {
                addProperty("name", "alarm_disable_today")
                addProperty("description", "Schaltet den klingelnden oder verschobenen Wecker für heute komplett aus, sodass er heute überhaupt nicht mehr klingelt, und synchronisiert die Aufstehzeit mit /admin/routine.")
                add("parameters", JsonObject().apply {
                    addProperty("type", "OBJECT")
                    add("properties", JsonObject())
                })
            })
        }
        de.meinseelenfunke.app.util.FalconClientTools.appendFalconToolDeclarations(decls)
    }

    private fun createDefaultToolsWithEndCall(): JsonArray {
        return JsonArray().apply {
            add(JsonObject().apply {
                val decls = JsonArray()
                appendLocalClientDeclarations(decls)
                add("functionDeclarations", decls)
            })
        }
    }

    private fun handleFrontendEvent(eventObj: JsonObject) {
        val type = eventObj.get("type")?.asString ?: return
        if (type == "navigate" && eventObj.has("url")) {
            val url = eventObj.get("url").asString
            var target: de.meinseelenfunke.app.util.NavigationBridge.NavTarget? = null
            var subTab = 0
            when {
                url.contains("zentrum", ignoreCase = true) -> {
                    target = de.meinseelenfunke.app.util.NavigationBridge.NavTarget.ZENTRUM
                }
                url.contains("order", ignoreCase = true) || url.contains("bestellung", ignoreCase = true) -> {
                    target = de.meinseelenfunke.app.util.NavigationBridge.NavTarget.ORDERS
                }
                url.contains("finance", ignoreCase = true) || url.contains("finanz", ignoreCase = true) || url.contains("buchhaltung", ignoreCase = true) || url.contains("shop", ignoreCase = true) -> {
                    target = de.meinseelenfunke.app.util.NavigationBridge.NavTarget.FINANCES
                }
                url.contains("email", ignoreCase = true) || url.contains("mail", ignoreCase = true) -> {
                    target = de.meinseelenfunke.app.util.NavigationBridge.NavTarget.ORGANIZER
                    subTab = 4
                }
                url.contains("organizer", ignoreCase = true) || url.contains("kalender", ignoreCase = true) || url.contains("aufgabe", ignoreCase = true) || url.contains("routine", ignoreCase = true) || url.contains("einkauf", ignoreCase = true) -> {
                    target = de.meinseelenfunke.app.util.NavigationBridge.NavTarget.ORGANIZER
                    subTab = when {
                        url.contains("routine", ignoreCase = true) -> 2
                        url.contains("calendar", ignoreCase = true) || url.contains("kalender", ignoreCase = true) -> 1
                        url.contains("task", ignoreCase = true) || url.contains("aufgabe", ignoreCase = true) -> 0
                        url.contains("shopping", ignoreCase = true) || url.contains("einkauf", ignoreCase = true) -> 3
                        else -> 0
                    }
                }
                url.contains("agent", ignoreCase = true) || url.contains("chat", ignoreCase = true) -> {
                    target = de.meinseelenfunke.app.util.NavigationBridge.NavTarget.CHAT
                }
                url.contains("setting", ignoreCase = true) || url.contains("einstellung", ignoreCase = true) -> {
                    target = de.meinseelenfunke.app.util.NavigationBridge.NavTarget.SETTINGS
                }
            }

            if (target != null) {
                viewModelScope.launch(Dispatchers.Main) {
                    addLog("[System]: Navigiere zu ${target.route} (Sub-Tab $subTab)...")
                    de.meinseelenfunke.app.util.NavigationBridge.triggerNavigation(target, subTab)
                    delay(1500)
                    disconnectLiveChat()
                    _shouldCloseScreen.value = true
                }
            }
        }
    }

    companion object {
        val RECONNECT_BACKOFF_MS = longArrayOf(350L, 800L, 1500L, 2500L)
        const val STABLE_CONNECTION_RESET_MS = 10_000L

        fun shouldAutoReconnectMidCall(
            wasSetupComplete: Boolean,
            userInitiatedHangup: Boolean,
            shouldEndCall: Boolean,
            reconnectAttemptCount: Int,
            maxAttempts: Int = RECONNECT_BACKOFF_MS.size
        ): Boolean {
            return wasSetupComplete &&
                !userInitiatedHangup &&
                !shouldEndCall &&
                reconnectAttemptCount in 0 until maxAttempts
        }

        fun isTrustedLiveWebSocketHost(
            wsUrl: String?,
            baseHost: String,
            isDebugBuild: Boolean = de.meinseelenfunke.app.BuildConfig.DEBUG
        ): Boolean {
            if (wsUrl.isNullOrBlank()) return false
            val uri = try { java.net.URI(wsUrl.trim()) } catch (_: Exception) { return false }
            val scheme = uri.scheme?.lowercase(java.util.Locale.ROOT) ?: return false
            val wsHost = uri.host?.lowercase(java.util.Locale.ROOT) ?: return false
            val cleanBase = baseHost.trim().lowercase(java.util.Locale.ROOT)

            if (scheme != "wss") {
                val allowDevWs = isDebugBuild && scheme == "ws" && ServiceLocator.isAllowedCleartextHost(wsHost)
                if (!allowDevWs) return false
            }

            return wsHost == cleanBase ||
                wsHost == "mein-seelenfunke.de" ||
                wsHost.endsWith(".mein-seelenfunke.de") ||
                wsHost == "generativelanguage.googleapis.com" ||
                wsHost == "aiplatform.googleapis.com" ||
                wsHost.endsWith("-aiplatform.googleapis.com")
        }

        fun buildDirectGeminiWsUrl(apiKey: String?): String? {
            val clean = apiKey?.trim().orEmpty()
            if (clean.length < 16) return null
            val encoded = java.net.URLEncoder.encode(clean, "UTF-8")
            return "wss://generativelanguage.googleapis.com/ws/google.ai.generativelanguage.v1beta.GenerativeService.BidiGenerateContent?key=$encoded"
        }

        fun safeErrorDetail(t: Throwable?): String? {
            if (t == null) return null
            return t.localizedMessage?.trim()?.takeIf {
                it.isNotBlank() && !it.equals("null", ignoreCase = true)
            } ?: t.message?.trim()?.takeIf {
                it.isNotBlank() && !it.equals("null", ignoreCase = true)
            }
        }

        fun selectPreferredCommunicationDeviceType(deviceTypes: List<Int>): Int {
            if (deviceTypes.contains(AudioDeviceInfo.TYPE_BLUETOOTH_SCO)) return AudioDeviceInfo.TYPE_BLUETOOTH_SCO
            if (deviceTypes.contains(AudioDeviceInfo.TYPE_BLE_HEADSET)) return AudioDeviceInfo.TYPE_BLE_HEADSET
            if (deviceTypes.contains(AudioDeviceInfo.TYPE_WIRED_HEADSET)) return AudioDeviceInfo.TYPE_WIRED_HEADSET
            if (deviceTypes.contains(AudioDeviceInfo.TYPE_WIRED_HEADPHONES)) return AudioDeviceInfo.TYPE_WIRED_HEADPHONES
            if (deviceTypes.contains(AudioDeviceInfo.TYPE_USB_HEADSET)) return AudioDeviceInfo.TYPE_USB_HEADSET
            if (deviceTypes.contains(AudioDeviceInfo.TYPE_BUILTIN_SPEAKER)) return AudioDeviceInfo.TYPE_BUILTIN_SPEAKER
            return deviceTypes.firstOrNull() ?: AudioDeviceInfo.TYPE_UNKNOWN
        }

        fun formatWebSocketFailureMessage(
            t: Throwable,
            httpStatusCode: Int? = null,
            setupWasComplete: Boolean = false
        ): String {
            if (de.meinseelenfunke.app.util.ErrorSentinelReporter.isTransientWebSocketDisconnect(t, httpStatusCode)) {
                var cur: Throwable? = t
                var depth = 0
                while (cur != null && depth < 6) {
                    when (cur) {
                        is java.io.EOFException -> {
                            return if (setupWasComplete) "Verbindung beendet" else "Verbindung getrennt"
                        }
                        is java.net.UnknownHostException,
                        is java.net.ConnectException,
                        is java.net.NoRouteToHostException -> {
                            return "Keine Internetverbindung"
                        }
                        is java.net.SocketTimeoutException -> {
                            return "Zeitüberschreitung der Verbindung"
                        }
                        is java.net.SocketException,
                        is javax.net.ssl.SSLException -> {
                            return "Verbindung kurz unterbrochen"
                        }
                    }
                    cur = cur.cause
                    depth++
                }
                return "Verbindung getrennt"
            }

            val detail = safeErrorDetail(t)
            val httpSuffix = if (httpStatusCode != null && httpStatusCode > 0) " (HTTP $httpStatusCode)" else ""
            return when {
                detail != null -> "Verbindungsfehler: $detail$httpSuffix"
                httpSuffix.isNotEmpty() -> "Verbindungsfehler$httpSuffix"
                else -> "Verbindungsfehler (${t.javaClass.simpleName.ifBlank { "Unbekannt" }})"
            }
        }
    }
}
