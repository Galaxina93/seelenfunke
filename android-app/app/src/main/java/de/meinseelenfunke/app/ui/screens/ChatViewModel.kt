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

    private val _agentName = MutableStateFlow("Funkira")
    val agentName: StateFlow<String> = _agentName.asStateFlow()

    private val _userFirstName = MutableStateFlow("Alina")
    val userFirstName: StateFlow<String> = _userFirstName.asStateFlow()

    private val _messages = MutableStateFlow<List<ChatMessage>>(
        listOf(ChatMessage("assistant", "Hallo Alina! Ich bin bereit. Wie kann ich dir heute im Seelenfunke Dashboard helfen?"))
    )
    val messages: StateFlow<List<ChatMessage>> = _messages.asStateFlow()

    private val _isLoading = MutableStateFlow(false)
    val isLoading: StateFlow<Boolean> = _isLoading.asStateFlow()

    private val _error = MutableStateFlow<String?>(null)
    val error: StateFlow<String?> = _error.asStateFlow()

    private var mediaPlayer: MediaPlayer? = null

    init {
        loadUserAndInitializeGreeting()
    }

    private fun loadUserAndInitializeGreeting() {
        viewModelScope.launch {
            authRepository.getCurrentUser().onSuccess { user ->
                val isCustomer = user.user_type == "customer"
                _userFirstName.value = user.first_name ?: (if (isCustomer) "Kunde" else "Alina")
                _agentName.value = if (isCustomer) "Funki" else "Funkira"
                
                val greeting = if (isCustomer) {
                    "Hallo ${_userFirstName.value}! Ich bin Funki, dein Seelenfunke Support-Assistent. Wie kann ich dir heute bei deinen Bestellungen helfen?"
                } else {
                    "Hallo ${_userFirstName.value}! Ich bin bereit. Wie kann ich dir heute im Seelenfunke Dashboard helfen?"
                }
                _messages.value = listOf(ChatMessage("assistant", greeting))
            }.onFailure {
                Log.e("ChatViewModel", "Fehler beim Laden des Benutzers: ${it.message}")
            }
        }
    }

    fun sendMessage(content: String) {
        if (content.isBlank() || _isLoading.value) return

        val userMessage = ChatMessage("user", content)
        val currentHistory = _messages.value
        _messages.value = currentHistory + userMessage
        _error.value = null

        viewModelScope.launch {
            _isLoading.value = true
            
            // Build cleaned request history for the Laravel API structure
            val apiHistory = _messages.value.map { msg ->
                ChatMessage(role = msg.role, content = msg.content)
            }

            aiRepository.sendChatMessage(prompt = content, history = apiHistory)
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
