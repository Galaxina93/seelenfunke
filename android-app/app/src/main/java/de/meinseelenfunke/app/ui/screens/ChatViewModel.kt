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

    private val _agents = MutableStateFlow<List<de.meinseelenfunke.app.data.api.AiAgent>>(emptyList())
    val agents: StateFlow<List<de.meinseelenfunke.app.data.api.AiAgent>> = _agents.asStateFlow()

    private val _selectedAgent = MutableStateFlow<de.meinseelenfunke.app.data.api.AiAgent?>(null)
    val selectedAgent: StateFlow<de.meinseelenfunke.app.data.api.AiAgent?> = _selectedAgent.asStateFlow()

    private var mediaPlayer: MediaPlayer? = null

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
                .onFailure { err ->
                    _error.value = "Fehler beim Laden der Agenten: ${err.localizedMessage}"
                }
        }
    }

    fun selectAgent(agent: de.meinseelenfunke.app.data.api.AiAgent) {
        if (_selectedAgent.value?.id == agent.id) return
        
        if (_selectedAgent.value != null) {
            de.meinseelenfunke.app.util.SoundManager.playSound(de.meinseelenfunke.app.R.raw.top_secret_sound_4)
        }

        stopAudio()
        _selectedAgent.value = agent
        
        val greeting = "Hallo Alina! Ich bin ${agent.name}. ${agent.role_description ?: "Wie kann ich dir heute helfen?"}"
        _messages.value = listOf(ChatMessage("assistant", greeting))
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
                append("📎 Anhang: ${file.name}")
            }
        }

        val userMessage = ChatMessage("user", displayContent)
        val currentHistory = _messages.value
        _messages.value = currentHistory + userMessage
        _error.value = null
        _selectedFile.value = null // Clear selected file

        viewModelScope.launch {
            _isLoading.value = true
            
            // Build cleaned request history for the Laravel API structure
            val apiHistory = _messages.value.mapIndexed { index, msg ->
                if (index == _messages.value.lastIndex) {
                    ChatMessage(role = msg.role, content = formattedContent)
                } else {
                    ChatMessage(role = msg.role, content = msg.content)
                }
            }

            aiRepository.sendChatMessage(
                prompt = formattedContent,
                history = apiHistory,
                agentId = _selectedAgent.value?.id
            )
                .onSuccess { chatResponse ->
                    val assistantResponseText = chatResponse.response ?: "Keine Antwort erhalten."
                    _messages.value = _messages.value + ChatMessage("assistant", assistantResponseText)

                    // If base64 TTS audio exists, play it!
                    chatResponse.audio?.let { audioBase64 ->
                        playTtsAudio(audioBase64)
                    }
                }
                .onFailure { err ->
                    _error.value = "Verbindungsfehler: ${err.localizedMessage}"
                    _messages.value = _messages.value + ChatMessage("assistant", "[Fehler beim Laden der Antwort]")
                }
            
            _isLoading.value = false
        }
    }


    private fun playTtsAudio(base64Wav: String) {
        try {
            stopAudio()

            // Decode base64 string
            val audioBytes = Base64.decode(base64Wav, Base64.DEFAULT)

            // Save to temp file
            val tempFile = File.createTempFile("seelenfunke_tts_", ".wav", ServiceLocator.context.cacheDir)
            tempFile.deleteOnExit()

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
                    try { tempFile.delete() } catch(e: Exception) {}
                }
            }
        } catch (e: Exception) {
            Log.e("ChatViewModel", "Fehler bei der Audio-Wiedergabe", e)
        }
    }

    fun stopAudio() {
        mediaPlayer?.let {
            if (it.isPlaying) {
                it.stop()
            }
            it.release()
        }
        mediaPlayer = null
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
