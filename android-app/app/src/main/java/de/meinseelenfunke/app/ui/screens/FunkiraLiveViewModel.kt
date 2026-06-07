package de.meinseelenfunke.app.ui.screens

import android.Manifest
import android.content.pm.PackageManager
import android.media.AudioAttributes
import android.media.AudioFormat
import android.media.AudioRecord
import android.media.AudioTrack
import android.media.MediaRecorder
import android.media.AudioManager
import android.content.Context
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

    private val _liveLogs = MutableStateFlow<List<String>>(emptyList())
    val liveLogs: StateFlow<List<String>> = _liveLogs.asStateFlow()

    private val _agents = MutableStateFlow<List<de.meinseelenfunke.app.data.api.AiAgent>>(emptyList())
    val agents: StateFlow<List<de.meinseelenfunke.app.data.api.AiAgent>> = _agents.asStateFlow()

    private val _selectedAgent = MutableStateFlow<de.meinseelenfunke.app.data.api.AiAgent?>(null)
    val selectedAgent: StateFlow<de.meinseelenfunke.app.data.api.AiAgent?> = _selectedAgent.asStateFlow()

    private var webSocket: WebSocket? = null
    private val client = OkHttpClient.Builder()
        .protocols(listOf(okhttp3.Protocol.HTTP_1_1))
        .build()
    private val gson = Gson()

    // Audio recording & playback threads
    private var recordingJob: Job? = null
    private var connectJob: Job? = null
    private var audioTrack: AudioTrack? = null
    private var isRecordingActive = false
    @Volatile private var isAiSpeaking = false

    private var shouldEndCall = false
    private var lastInterruptTime = 0L
    private val _shouldCloseScreen = MutableStateFlow(false)
    val shouldCloseScreen: StateFlow<Boolean> = _shouldCloseScreen.asStateFlow()

    init {
        loadAgents()
    }

    fun loadAgents() {
        viewModelScope.launch {
            aiRepository.getAgents()
                .onSuccess { list ->
                    _agents.value = list
                    val defaultAgent = list.find { it.name.equals("Funkira", ignoreCase = true) } ?: list.firstOrNull()
                    defaultAgent?.let { selectAgent(it) }
                }
        }
    }

    fun selectAgent(agent: de.meinseelenfunke.app.data.api.AiAgent) {
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
        
        _isConnecting.value = true
        _error.value = null
        shouldEndCall = false
        isAiSpeaking = false
        _shouldCloseScreen.value = false
        // Set live call active immediately to yield microphone from background service
        de.meinseelenfunke.app.util.NavigationBridge.isLiveCallActive = true
        addLog("Sitzungsdaten werden geladen...")
        chatSessionId = "android_live_${java.util.UUID.randomUUID()}"

        connectJob = viewModelScope.launch(Dispatchers.IO) {
            aiRepository.getLiveCredentials(_selectedAgent.value?.id, chatSessionId)
                .onSuccess { creds ->
                    if (connectJob?.isActive != true) return@onSuccess
                    addLog("Authentifiziert. Verbindung wird aufgebaut...")
                    val baseUrl = de.meinseelenfunke.app.di.ServiceLocator.getBaseUrl()
                    val parsedUri = try { java.net.URI(baseUrl) } catch(e: java.lang.Exception) { null }
                    val host = parsedUri?.host ?: "10.0.2.2"
                    val fallbackWsUrl = "ws://$host:8089/gemini-live"
                    val wsUrl = creds.ws_url ?: fallbackWsUrl
                    establishWebSocket(creds.token ?: "", wsUrl, creds.system_instruction, creds.voice_name, creds.tools?.toString())
                }
                .onFailure { err ->
                    if (connectJob?.isActive != true) return@onFailure
                    _isConnecting.value = false
                    de.meinseelenfunke.app.util.NavigationBridge.isLiveCallActive = false
                    _error.value = "Authentifizierung fehlgeschlagen: ${err.localizedMessage}"
                    addLog("Fehler: ${_error.value}")
                }
        }
    }

    private fun establishWebSocket(token: String, wsUrl: String, systemInstruction: String, voiceName: String, toolsJson: String?) {
        val url = if (wsUrl.contains("?")) {
            "$wsUrl&token=$token"
        } else {
            "$wsUrl?token=$token"
        }
        val request = Request.Builder().url(url).build()

        // Init AudioTrack for 24kHz speaker playback
        initAudioTrack()

        webSocket = client.newWebSocket(request, object : WebSocketListener() {
            override fun onOpen(ws: WebSocket, response: Response) {
                if (ws != this@FunkiraLiveViewModel.webSocket) return
                _isConnecting.value = false
                _isConnected.value = true
                val maskedUrl = if (token.isNotEmpty()) url.replace(token, "XXXX_TOKEN_XXXX") else url
                addLog("[WS-Setup]: URL: $maskedUrl")

                // Send Setup configuration payload
                val setup = JsonObject().apply {
                    add("setup", JsonObject().apply {
                        addProperty("model", "models/gemini-2.5-flash-native-audio-latest")
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
                                    addProperty("text", systemInstruction)
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
                                        var exists = false
                                        for (i in 0 until decls.size()) {
                                            if (decls.get(i).asJsonObject.get("name").asString == "end_call") {
                                                exists = true
                                                break
                                            }
                                        }
                                        if (!exists) {
                                            decls.add(JsonObject().apply {
                                                addProperty("name", "end_call")
                                                addProperty("description", "Beendet das aktuelle Live-Gespräch sofort, wenn sich der Nutzer verabschiedet hat.")
                                                add("parameters", JsonObject().apply {
                                                    addProperty("type", "OBJECT")
                                                    add("properties", JsonObject())
                                                })
                                            })
                                        }
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
                    })
                }

                val setupStr = gson.toJson(setup)
                android.util.Log.d("FunkiraLive", "[WS-Setup]: Configuration string length: ${setupStr.length}")
                setupStr.chunked(2000).forEachIndexed { i, chunk ->
                    android.util.Log.d("FunkiraLive-Payload", "Chunk $i: $chunk")
                }
                val sent = ws.send(setupStr)
                android.util.Log.d("FunkiraLive", "[WS-Setup]: send(setupStr) returned: $sent")
                val agentName = _selectedAgent.value?.name ?: "Funkira"
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
                val respBody = try { response?.body?.string() } catch(e: Exception) { null }
                val errorMsg = "Verbindungsfehler: ${t.localizedMessage}" +
                        (if (response != null) " (Code: ${response.code}, Message: ${response.message}, Body: $respBody)" else "")
                handleDisconnect(errorMsg)
            }

            override fun onClosing(ws: WebSocket, code: Int, reason: String) {
                if (ws != this@FunkiraLiveViewModel.webSocket) return
                handleDisconnect("Verbindung schließt: $reason ($code)")
            }

            override fun onClosed(ws: WebSocket, code: Int, reason: String) {
                if (ws != this@FunkiraLiveViewModel.webSocket) return
                handleDisconnect("Verbindung beendet: $reason ($code)")
            }
        })
    }

    private fun handleServerMessage(jsonText: String) {
        val sanitized = sanitizeJson(jsonText)
        android.util.Log.d("FunkiraLive", "Received (Sanitized): $sanitized")
        try {
            val root = gson.fromJson(jsonText, JsonObject::class.java) ?: return
            val agentName = _selectedAgent.value?.name ?: "Funkira"

            // Check for server-side error
            if (root.has("error")) {
                val errorObj = root.getAsJsonObject("error")
                val errMsg = errorObj.get("message")?.asString ?: "Unbekannter Serverfehler"
                val errCode = errorObj.get("code")?.asInt ?: 0
                addLog("[System-Fehler]: Server-Fehler ($errCode): $errMsg")
                handleDisconnect("Server-Fehler: $errMsg")
                return
            }

            // Check setupComplete response
            if (root.has("setupComplete")) {
                addLog("[System]: Setup vollständig abgeschlossen. Gespräch gestartet.")
                startRecording()
                return
            }

            // Check serverContent response
            if (root.has("serverContent")) {
                val serverContent = root.getAsJsonObject("serverContent")
                
                // Handle interruption/barge-in
                if (serverContent.has("interrupted") && serverContent.get("interrupted").asBoolean) {
                    addLog("[System]: Unterbrechung erkannt.")
                    isAiSpeaking = false
                    flushAudioPlayback()
                }

                // Handle turnComplete for end_call
                if (serverContent.has("turnComplete") && serverContent.get("turnComplete").asBoolean) {
                    viewModelScope.launch {
                        delay(300)
                        isAiSpeaking = false
                    }
                    if (shouldEndCall) {
                        shouldEndCall = false
                        viewModelScope.launch(Dispatchers.Main) {
                            de.meinseelenfunke.app.util.SoundManager.playSound(de.meinseelenfunke.app.R.raw.click_file_in_project_brain)
                            delay(1500)
                            disconnectLiveChat()
                            _shouldCloseScreen.value = true
                        }
                    }
                }

                if (serverContent.has("modelTurn")) {
                    isAiSpeaking = true
                    val modelTurn = serverContent.getAsJsonObject("modelTurn")
                    if (modelTurn.has("parts")) {
                        val parts = modelTurn.getAsJsonArray("parts")
                        parts.forEach { part ->
                            val partObj = part.asJsonObject
                            
                            // Check text transcript
                            if (partObj.has("text")) {
                                val textPart = partObj.get("text").asString
                                addLog("$agentName: $textPart")
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
                        
                        viewModelScope.launch(Dispatchers.IO) {
                            val args = if (callObj.has("args")) callObj.get("args") else null
                            aiRepository.executeTool(name, args, chatSessionId)
                                .onSuccess { response ->
                                    val resultElement = response.result ?: JsonObject()
                                    val resultObj = if (resultElement.isJsonObject) resultElement.asJsonObject else JsonObject()
                                    addLog("[Aktion-Ergebnis]: Tool '$name' ausgeführt.")
                                    sendToolResponse(callId, name, resultElement)

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
                                    addLog("[Aktion-Fehler]: Tool '$name' fehlgeschlagen: ${err.localizedMessage}")
                                    val errorJson = JsonObject().apply {
                                        addProperty("status", "error")
                                        addProperty("message", err.localizedMessage)
                                    }
                                    sendToolResponse(callId, name, errorJson)
                                }
                        }
                    }
                }
            }


        } catch (e: Exception) {
            e.printStackTrace()
            addLog("[System-Fehler]: Exception beim Verarbeiten der Nachricht: ${e.localizedMessage}")
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
                add("mediaChunks", JsonArray().apply {
                    add(JsonObject().apply {
                        addProperty("mimeType", "audio/pcm;rate=16000")
                        addProperty("data", base64Data)
                    })
                })
            })
        }
        val sent = ws.send(gson.toJson(audioMsg))
        if (!sent) {
            android.util.Log.e("FunkiraLive", "WebSocket send failed for audio chunk")
        }
    }

    private fun sendInterruptMessage() {
        val ws = webSocket ?: return
        val now = System.currentTimeMillis()
        if (now - lastInterruptTime < 1500) return
        lastInterruptTime = now

        val msg = JsonObject().apply {
            add("clientContent", JsonObject().apply {
                val turnsArray = JsonArray().apply {
                    add(JsonObject().apply {
                        addProperty("role", "user")
                        val partsArray = JsonArray().apply {
                            add(JsonObject().apply {
                                addProperty("text", " ")
                            })
                        }
                        add("parts", partsArray)
                    })
                }
                add("turns", turnsArray)
                addProperty("turnComplete", false)
            })
        }
        val sent = ws.send(gson.toJson(msg))
        android.util.Log.d("FunkiraLive", "Sent clientContent turn interrupt message: $sent")
    }

    private fun setCommunicationMode(enable: Boolean) {
        try {
            val audioManager = ServiceLocator.context.getSystemService(Context.AUDIO_SERVICE) as AudioManager
            if (enable) {
                audioManager.mode = AudioManager.MODE_IN_COMMUNICATION
                audioManager.isSpeakerphoneOn = true
                addLog("[Audio] Kommunikationsmodus aktiviert (Speakerphone = true)")
            } else {
                audioManager.mode = AudioManager.MODE_NORMAL
                audioManager.isSpeakerphoneOn = false
                addLog("[Audio] Normalmodus wiederhergestellt")
            }
        } catch (e: Exception) {
            addLog("[Audio] Fehler beim Einstellen des Audio-Modus: ${e.message}")
        }
    }

    private fun initAudioTrack() {
        try {
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
            
            // Apply software digital gain (e.g., 2.5x volume boost)
            val boostedBytes = ByteArray(pcmBytes.size)
            for (i in 0 until pcmBytes.size step 2) {
                if (i + 1 < pcmBytes.size) {
                    val low = pcmBytes[i].toInt() and 0xFF
                    val high = pcmBytes[i+1].toInt()
                    val sample = ((high shl 8) or low).toShort()
                    val boosted = (sample * 2.5f).toInt()
                    val clamped = maxOf(Short.MIN_VALUE.toInt(), minOf(Short.MAX_VALUE.toInt(), boosted))
                    boostedBytes[i] = (clamped and 0xFF).toByte()
                    boostedBytes[i+1] = ((clamped ushr 8) and 0xFF).toByte()
                }
            }

            val written = track.write(boostedBytes, 0, boostedBytes.size)
            if (written < 0) {
                addLog("[Audio] Schreibfehler: $written")
            } else {
                android.util.Log.d("AudioPlayback", "$written Bytes played")
            }
        } catch (e: Exception) {
            addLog("[Audio] Wiedergabeausnahme: ${e.message}")
            e.printStackTrace()
        }
    }

    private fun flushAudioPlayback() {
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
            try {
                val sampleRate = 16000
                val channelConfig = AudioFormat.CHANNEL_IN_MONO
                val audioFormat = AudioFormat.ENCODING_PCM_16BIT
                val bufferSize = AudioRecord.getMinBufferSize(sampleRate, channelConfig, audioFormat)

                val audioRecord = try {
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

                        // Local interruption check: If AI is speaking and user talks loudly, immediately silence speaker
                        if (isAiSpeaking && maxVal > 3500) {
                            android.util.Log.d("FunkiraLive", "Local user interruption detected (amplitude: $maxVal). Flushing audio playback.")
                            isAiSpeaking = false
                            flushAudioPlayback()
                            sendInterruptMessage()
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

                audioRecord.stop()
                audioRecord.release()
            } catch (e: Exception) {
                e.printStackTrace()
            }
        }
    }

    private fun stopRecording() {
        isRecordingActive = false
        _isRecording.value = false
        de.meinseelenfunke.app.util.NavigationBridge.isLiveCallActive = false
        recordingJob?.cancel()
        recordingJob = null
    }

    fun toggleMute() {
        if (_isRecording.value) {
            stopRecording()
            addLog("[System]: Mikrofon stummgeschaltet.")
        } else {
            startRecording()
            addLog("[System]: Mikrofon aktiviert.")
        }
    }

    private fun handleDisconnect(reason: String) {
        _isConnected.value = false
        _isConnecting.value = false
        isAiSpeaking = false
        connectJob?.cancel()
        connectJob = null
        stopRecording()
        releaseAudioTrack()
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
        handleDisconnect("Vom Benutzer getrennt")
        webSocket?.close(1000, "User quit")
        webSocket = null
    }

    override fun onCleared() {
        super.onCleared()
        disconnectLiveChat()
    }

    private fun createDefaultToolsWithEndCall(): JsonArray {
        return JsonArray().apply {
            add(JsonObject().apply {
                add("functionDeclarations", JsonArray().apply {
                    add(JsonObject().apply {
                        addProperty("name", "end_call")
                        addProperty("description", "Beendet das aktuelle Live-Gespräch sofort, wenn sich der Nutzer verabschiedet hat.")
                        add("parameters", JsonObject().apply {
                            addProperty("type", "OBJECT")
                            add("properties", JsonObject())
                        })
                    })
                })
            })
        }
    }

    private fun handleFrontendEvent(eventObj: JsonObject) {
        val type = eventObj.get("type")?.asString ?: return
        if (type == "navigate" && eventObj.has("url")) {
            val url = eventObj.get("url").asString
            var targetTab: Int? = null
            var subTab = 0
            when {
                url.contains("zentrum", ignoreCase = true) -> targetTab = 0
                url.contains("finance", ignoreCase = true) || url.contains("shop", ignoreCase = true) -> targetTab = 1
                url.contains("organizer", ignoreCase = true) || url.contains("kalender", ignoreCase = true) || url.contains("aufgabe", ignoreCase = true) -> {
                    targetTab = 2
                    subTab = when {
                        url.contains("routine", ignoreCase = true) -> 2
                        url.contains("calendar", ignoreCase = true) || url.contains("kalender", ignoreCase = true) -> 1
                        url.contains("task", ignoreCase = true) || url.contains("aufgabe", ignoreCase = true) -> 0
                        url.contains("shopping", ignoreCase = true) || url.contains("einkauf", ignoreCase = true) -> 3
                        else -> 0
                    }
                }
                url.contains("agent", ignoreCase = true) || url.contains("chat", ignoreCase = true) -> targetTab = 3
                url.contains("setting", ignoreCase = true) || url.contains("einstellung", ignoreCase = true) -> targetTab = 4
            }
            
            if (targetTab != null) {
                viewModelScope.launch(Dispatchers.Main) {
                    addLog("[System]: Navigiere zu Tab $targetTab (Sub-Tab $subTab)...")
                    de.meinseelenfunke.app.util.NavigationBridge.triggerNavigation(targetTab, subTab)
                    delay(1500)
                    disconnectLiveChat()
                    _shouldCloseScreen.value = true
                }
            }
        }
    }
}
