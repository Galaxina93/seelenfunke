package de.meinseelenfunke.app.ui.screens

import android.media.MediaPlayer
import android.util.Base64
import android.util.Log
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import de.meinseelenfunke.app.data.api.ChatMessage
import de.meinseelenfunke.app.di.ServiceLocator
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import java.io.File
import java.io.FileOutputStream

class ChatViewModel : ViewModel() {

    private val aiRepository = ServiceLocator.aiRepository
    private val authRepository = ServiceLocator.authRepository

    private val _messages = MutableStateFlow<List<ChatMessage>>(
        listOf(ChatMessage("assistant", "Hallo Alina! Ich bin bereit. Wie kann ich dir heute im Seelenfunke Dashboard helfen?"))
    )
    val messages: StateFlow<List<ChatMessage>> = _messages.asStateFlow()

    private val _isLoading = MutableStateFlow(false)
    val isLoading: StateFlow<Boolean> = _isLoading.asStateFlow()

    private val _error = MutableStateFlow<String?>(null)
    val error: StateFlow<String?> = _error.asStateFlow()

    private val _canRetryLastMessage = MutableStateFlow(false)
    val canRetryLastMessage: StateFlow<Boolean> = _canRetryLastMessage.asStateFlow()

    private val _agents = MutableStateFlow<List<de.meinseelenfunke.app.data.api.AiAgent>>(emptyList())
    val agents: StateFlow<List<de.meinseelenfunke.app.data.api.AiAgent>> = _agents.asStateFlow()

    private val _selectedAgent = MutableStateFlow<de.meinseelenfunke.app.data.api.AiAgent?>(null)
    val selectedAgent: StateFlow<de.meinseelenfunke.app.data.api.AiAgent?> = _selectedAgent.asStateFlow()

    private var activeChatSessionId: String? = null
    private var pendingRetryPayload: PendingRetryPayload? = null
    private var mediaPlayer: MediaPlayer? = null
    private var currentTempAudioFile: File? = null

    private data class PendingRetryPayload(
        val formattedPrompt: String,
        val apiHistory: List<ChatMessage>,
        val agentId: String?,
        val clientMessageId: String
    )

    companion object {
        private const val LEGACY_ERROR_BUBBLE = "[Fehler beim Laden der Antwort]"
        const val AG_LIVE_STATUS_PREFIX = "[Antigravity Echtzeit]"

        fun cleanAssistantText(raw: String): String {
            return raw
                .replace(Regex("<speak>", RegexOption.IGNORE_CASE), "")
                .replace(Regex("</speak>", RegexOption.IGNORE_CASE), "")
                .trim()
        }

        fun sanitizeHistoryForApi(
            messages: List<ChatMessage>,
            overrideLastUserPrompt: String? = null
        ): List<ChatMessage> {
            val filtered = messages.filterNot { msg ->
                (msg.role.equals("assistant", ignoreCase = true) &&
                    (msg.content.trim() == LEGACY_ERROR_BUBBLE || msg.content.trim().startsWith(AG_LIVE_STATUS_PREFIX)))
            }
            if (filtered.isEmpty()) {
                return if (!overrideLastUserPrompt.isNullOrBlank()) {
                    listOf(ChatMessage(role = "user", content = overrideLastUserPrompt))
                } else {
                    emptyList()
                }
            }
            return filtered.mapIndexed { index, msg ->
                if (index == filtered.lastIndex && overrideLastUserPrompt != null && msg.role == "user") {
                    ChatMessage(role = "user", content = overrideLastUserPrompt)
                } else {
                    ChatMessage(role = msg.role, content = cleanAssistantText(msg.content))
                }
            }
        }

        fun formatUserFacingError(err: Throwable): String {
            if (err is retrofit2.HttpException) {
                return when (err.code()) {
                    401 -> "Sitzung abgelaufen. Bitte kurz neu anmelden."
                    403 -> "Keine Berechtigung für diesen KI-Agenten."
                    413 -> "Anhang zu groß für die Übertragung."
                    422 -> "Eingabe konnte vom Server nicht verarbeitet werden."
                    429 -> "KI-Server kurz ausgelastet. Bitte erneut senden."
                    in 500..599 -> "Server vorübergehend ausgelastet (HTTP ${err.code()}). Bitte erneut senden."
                    else -> "Verbindungsfehler (HTTP ${err.code()}). Bitte erneut senden."
                }
            }
            var cur: Throwable? = err
            var depth = 0
            while (cur != null && depth < 5) {
                when (cur) {
                    is java.net.UnknownHostException,
                    is java.net.ConnectException,
                    is java.net.NoRouteToHostException ->
                        return "Keine Verbindung zum Server. Bitte WLAN oder Mobilfunk prüfen."
                    is java.net.SocketTimeoutException ->
                        return "Antwort dauerte zu lange (Zeitüberschreitung). Bitte erneut senden."
                    is javax.net.ssl.SSLHandshakeException,
                    is javax.net.ssl.SSLPeerUnverifiedException ->
                        return "Sichere TLS-Verbindung fehlgeschlagen. Bitte Netzwerk prüfen."
                    is javax.net.ssl.SSLException,
                    is java.io.EOFException,
                    is java.net.SocketException ->
                        return "Verbindung beim Netzwechsel unterbrochen. Bitte erneut senden."
                }
                cur = cur.cause
                depth++
            }
            val msg = err.localizedMessage?.trim().orEmpty()
            if (msg.isNotEmpty() && !msg.startsWith("java.") && !msg.startsWith("okhttp3.") && msg.length <= 140) {
                return msg
            }
            return "Antwort konnte gerade nicht geladen werden. Bitte erneut senden."
        }
    }

    init {
        try {
            de.meinseelenfunke.app.util.FunkiraLocalMemoryStore.hydrate(ServiceLocator.context)
            de.meinseelenfunke.app.util.FunkiraPackageStore.hydrate(ServiceLocator.context)
        } catch (_: Throwable) {
        }
        viewModelScope.launch {
            de.meinseelenfunke.app.util.AgDirectInboxManager.statusTransitionEvents.collect { ev ->
                val text = "$AG_LIVE_STATUS_PREFIX ${ev.toHumanEventLine()}"
                val current = _messages.value
                if (current.lastOrNull()?.content != text) {
                    _messages.value = current + ChatMessage(role = "assistant", content = text)
                }
            }
        }
        loadAgents()
        loadChatHistory()
    }

    fun loadChatHistory() {
        viewModelScope.launch {
            aiRepository.getChatHistoryBundle(sessionId = activeChatSessionId, limit = 40)
                .onSuccess { bundle ->
                    if (!bundle.sessionId.isNullOrBlank()) {
                        activeChatSessionId = bundle.sessionId
                    }
                    val cleanedList = bundle.messages
                        .filterNot { msg ->
                            msg.role.equals("assistant", ignoreCase = true) &&
                                msg.content.trim() == LEGACY_ERROR_BUBBLE
                        }
                        .map { msg ->
                            ChatMessage(role = msg.role, content = cleanAssistantText(msg.content))
                        }
                    if (cleanedList.isNotEmpty()) {
                        _messages.value = cleanedList
                    }
                }
                .onFailure {
                    // Silently keep default greeting on initial history failure
                }
        }
    }

    fun loadAgents() {
        viewModelScope.launch {
            aiRepository.getAgents()
                .onSuccess { list ->
                    val funkiraOnly = list.filter { it.name.equals("Funkira", ignoreCase = true) }
                    val filtered = if (funkiraOnly.isNotEmpty()) {
                        funkiraOnly
                    } else {
                        list.filterNot {
                            it.name.equals("Falcone", ignoreCase = true) ||
                                it.name.equals("Falcon", ignoreCase = true) ||
                                it.name.equals("Funki", ignoreCase = true)
                        }
                    }
                    _agents.value = filtered
                    val defaultAgent = filtered.find { it.name.equals("Funkira", ignoreCase = true) } ?: filtered.firstOrNull()
                    if (defaultAgent != null) {
                        selectAgent(defaultAgent, updateGreeting = false)
                    }
                }
                .onFailure { err ->
                    if (_messages.value.size <= 1) {
                        _error.value = formatUserFacingError(err)
                    }
                }
        }
    }

    fun selectAgent(agent: de.meinseelenfunke.app.data.api.AiAgent, updateGreeting: Boolean = true) {
        if (_selectedAgent.value?.id == agent.id) return
        
        if (_selectedAgent.value != null) {
            de.meinseelenfunke.app.util.SoundManager.playSound(de.meinseelenfunke.app.R.raw.top_secret_sound_4)
        }

        stopAudio()
        _selectedAgent.value = agent
        
        if (updateGreeting && _messages.value.size <= 1) {
            val greeting = "Hallo Alina! Ich bin ${agent.name}. ${agent.role_description ?: "Wie kann ich dir heute helfen?"}"
            _messages.value = listOf(ChatMessage("assistant", greeting))
        }
    }

    private val _selectedFile = MutableStateFlow<SelectedFile?>(null)
    val selectedFile: StateFlow<SelectedFile?> = _selectedFile.asStateFlow()

    fun selectFile(file: SelectedFile?) {
        _selectedFile.value = file
    }

    fun sendMessage(content: String) {
        if (content.isBlank() && _selectedFile.value == null) return
        if (_isLoading.value) return

        de.meinseelenfunke.app.util.SoundManager.playSound(de.meinseelenfunke.app.R.raw.sent_message_1)

        val file = _selectedFile.value
        val formattedContent = buildString {
            append(content)
            if (file != null) {
                if (file.mimeType?.startsWith("image/") == true && file.base64Content != null) {
                    append("\n[SYSTEM_IMAGE]: data:${file.mimeType};base64,${file.base64Content}")
                } else if (file.textContent != null) {
                    append("\n\n[Anhang: ${file.name}]\n```\n${file.textContent}\n```")
                } else {
                    append("\n\n[Anhang: ${file.name} (${file.sizeBytes} Bytes)]")
                }
            }
        }

        val displayContent = buildString {
            append(content)
            if (file != null) {
                if (content.isNotBlank()) append("\n")
                if (file.mimeType?.startsWith("image/") == true) {
                    append("🖼️ Bild: ${file.name}")
                } else {
                    append("📎 Anhang: ${file.name}")
                }
            }
        }

        val userMessage = ChatMessage("user", displayContent)
        val currentHistory = _messages.value
        val updatedMessages = currentHistory + userMessage
        _messages.value = updatedMessages
        _error.value = null
        _canRetryLastMessage.value = false
        _selectedFile.value = null // Clear selected file

        try {
            de.meinseelenfunke.app.util.FunkiraLocalMemoryStore.recordTurn(
                context = ServiceLocator.context,
                role = "user",
                body = displayContent,
                mode = "chat"
            )
        } catch (_: Throwable) {
        }

        if (_agents.value.isEmpty()) {
            loadAgents()
        }

        val apiHistory = sanitizeHistoryForApi(
            messages = updatedMessages,
            overrideLastUserPrompt = formattedContent
        )
        val clientMessageId = java.util.UUID.randomUUID().toString()
        val payload = PendingRetryPayload(
            formattedPrompt = formattedContent,
            apiHistory = apiHistory,
            agentId = _selectedAgent.value?.id,
            clientMessageId = clientMessageId
        )
        executeChatRequest(payload)
    }

    fun retryLastMessage() {
        val payload = pendingRetryPayload ?: return
        if (_isLoading.value) return
        _error.value = null
        _canRetryLastMessage.value = false
        executeChatRequest(payload.copy(agentId = _selectedAgent.value?.id ?: payload.agentId))
    }

    private fun executeChatRequest(payload: PendingRetryPayload) {
        viewModelScope.launch {
            _isLoading.value = true
            try {
                aiRepository.sendChatMessage(
                    prompt = payload.formattedPrompt,
                    history = payload.apiHistory,
                    agentId = payload.agentId,
                    chatSessionId = activeChatSessionId,
                    clientMessageId = payload.clientMessageId
                )
                    .onSuccess { chatResponse ->
                        pendingRetryPayload = null
                        _canRetryLastMessage.value = false
                        _error.value = null
                        if (!chatResponse.chat_session_id.isNullOrBlank()) {
                            activeChatSessionId = chatResponse.chat_session_id
                        }
                        val rawResponse = chatResponse.response?.takeIf { it.isNotBlank() }
                            ?: "Keine Antwort erhalten."
                        val cleanResponse = cleanAssistantText(rawResponse)
                        _messages.value = _messages.value + ChatMessage("assistant", cleanResponse)
                        try {
                            de.meinseelenfunke.app.util.FunkiraLocalMemoryStore.recordTurn(
                                context = ServiceLocator.context,
                                role = "funkira",
                                body = cleanResponse,
                                mode = "chat"
                            )
                        } catch (_: Throwable) {
                        }

                        // If base64 TTS audio exists, play it!
                        chatResponse.audio?.let { audioBase64 ->
                            playTtsAudio(audioBase64)
                        }
                    }
                    .onFailure { err ->
                        pendingRetryPayload = payload
                        _canRetryLastMessage.value = true
                        _error.value = formatUserFacingError(err)
                    }
            } finally {
                _isLoading.value = false
            }
        }
    }


    private fun playTtsAudio(base64Wav: String) {
        try {
            stopAudio()

            // Decode base64 string
            val audioBytes = Base64.decode(base64Wav, Base64.DEFAULT)

            // Save to scoped temp file
            val ttsDir = File(ServiceLocator.context.cacheDir, "tts_temp").apply { mkdirs() }
            val tempFile = File.createTempFile("seelenfunke_tts_", ".wav", ttsDir)
            tempFile.deleteOnExit()
            currentTempAudioFile = tempFile

            FileOutputStream(tempFile).use { fos ->
                fos.write(audioBytes)
            }

            // Play WAV file
            mediaPlayer = MediaPlayer().apply {
                setDataSource(tempFile.absolutePath)
                prepare()
                start()
                setOnCompletionListener {
                    it.release()
                    mediaPlayer = null
                    try { tempFile.delete() } catch (e: Exception) {}
                    if (currentTempAudioFile == tempFile) {
                        currentTempAudioFile = null
                    }
                }
            }
        } catch (e: Exception) {
            Log.e("ChatViewModel", "Fehler bei der Audio-Wiedergabe")
        }
    }

    fun stopAudio() {
        mediaPlayer?.let {
            try {
                if (it.isPlaying) {
                    it.stop()
                }
            } catch (_: Exception) {}
            try { it.release() } catch (_: Exception) {}
        }
        mediaPlayer = null
        currentTempAudioFile?.let { file ->
            try { file.delete() } catch (_: Exception) {}
        }
        currentTempAudioFile = null
    }

    fun logout() {
        stopAudio()
        authRepository.logout()
    }

    override fun onCleared() {
        super.onCleared()
        stopAudio()
    }
}

data class SelectedFile(
    val uriString: String,
    val name: String,
    val mimeType: String?,
    val sizeBytes: Long,
    val base64Content: String?,
    val textContent: String?
)
