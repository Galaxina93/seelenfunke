package de.meinseelenfunke.app.data.api

import com.google.gson.JsonElement
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.POST
import retrofit2.http.Query

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

data class LiveCredentialsResponse(
    val token: String?,
    val ws_url: String?,
    val system_instruction: String,
    val voice_name: String,
    val tools: JsonElement?
)

data class AiAgent(
    val id: String,
    val name: String,
    val wake_word: String?,
    val role_description: String?,
    val color: String?,
    val icon: String?,
    val profile_picture: String?,
    val tts_enabled: Boolean,
    val tts_voice: String?
)

data class AgentsResponse(
    val success: String,
    val data: List<AiAgent>
)

data class ExecuteToolRequest(
    val function: String,
    val args: JsonElement?,
    val session_id: String? = null
)

data class ExecuteToolResponse(
    val status: String,
    val function: String,
    val result: JsonElement?
)

interface AiApi {
    @POST("ai/chat")
    suspend fun chat(@Body request: ChatRequest): ChatResponse

    @GET("ai/live-credentials")
    suspend fun getLiveCredentials(
        @Query("agent_id") agentId: String? = null,
        @Query("chat_session_id") chatSessionId: String? = null
    ): LiveCredentialsResponse

    @GET("ai/agents")
    suspend fun getAgents(): AgentsResponse

    @POST("ai/execute")
    suspend fun executeTool(@Body request: ExecuteToolRequest): ExecuteToolResponse
}

