package de.meinseelenfunke.app.data.api

import retrofit2.http.Body
import retrofit2.http.POST

data class ChatMessage(
    val role: String,
    val content: String
)

data class ChatRequest(
    val prompt: String? = null,
    val history: List<ChatMessage> = emptyList(),
    val agent_id: String? = null
)

data class ChatResponse(
    val status: String,
    val agent_name: String?,
    val response: String?,
    val tts_enabled: Boolean,
    val audio: String? // Base64 WAV data
)

interface AiApi {
    @POST("ai/chat")
    suspend fun chat(@Body request: ChatRequest): ChatResponse
}
