package de.meinseelenfunke.app.data.api

import com.google.gson.JsonElement
import okhttp3.MultipartBody
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.Multipart
import retrofit2.http.POST
import retrofit2.http.Part
import retrofit2.http.Query

data class ChatMessage(
    val role: String,
    val content: String
)

data class ChatRequest(
    val prompt: String? = null,
    val history: List<ChatMessage> = emptyList(),
    val agent_id: String? = null,
    val chat_session_id: String? = null,
    val client_message_id: String? = null,
    val skip_tts: Boolean? = null
)

data class ChatResponse(
    val status: String? = null,
    val chat_session_id: String? = null,
    val agent_name: String? = null,
    val response: String? = null,
    val message: String? = null,
    val tts_enabled: Boolean = false,
    val deduplicated: Boolean? = false,
    val audio: String? = null // Base64 WAV data
)

data class PendingAgBlockadeDto(
    val id: String = "",
    val channel: String? = "leitung",
    val title: String = "",
    val execution_mode: String? = "concept",
    val status: String? = "awaiting_go",
    val pending_question: String? = null,
    val ag_response_summary: String? = null,
    val waiting_minutes: Int? = 0
)

data class VertexEphemeralResponse(
    val access_token: String? = null,
    val expires_in_seconds: Int = 3000,
    val project_id: String? = DEFAULT_PROJECT_ID,
    val rest_models_base_url: String? = DEFAULT_REST_MODELS_BASE_URL,
    val live_ws_url: String? = DEFAULT_LIVE_WS_URL,
    val live_model: String? = DEFAULT_LIVE_MODEL
) {
    companion object {
        const val DEFAULT_PROJECT_ID = "gen-lang-client-0670786367"
        const val DEFAULT_REST_MODELS_BASE_URL =
            "https://aiplatform.googleapis.com/v1beta1/projects/gen-lang-client-0670786367/locations/global/publishers/google/models"
        const val DEFAULT_LIVE_WS_URL =
            "wss://europe-west4-aiplatform.googleapis.com/ws/google.cloud.aiplatform.v1beta1.LlmBidiService/BidiGenerateContent"
        const val DEFAULT_LIVE_MODEL =
            "projects/gen-lang-client-0670786367/locations/europe-west4/publishers/google/models/gemini-live-2.5-flash-native-audio"
    }
}

data class LiveCredentialsResponse(
    val token: String?,
    val ws_url: String?,
    val direct_ws_url: String? = null,
    val live_model: String? = null,
    val origin_url: String? = null,
    val system_instruction: String,
    val voice_name: String,
    val tools: JsonElement?,
    val pending_ag_blockades: List<PendingAgBlockadeDto>? = emptyList(),
    val proactive_kick_prompt: String? = null,
    val vertex_ephemeral: VertexEphemeralResponse? = null
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

data class CameraSnapshotResponse(
    val status: String,
    val file_path: String? = null,
    val url: String? = null
)

data class ChatHistoryItem(
    val id: String?,
    val role: String,
    val content: String,
    val agent_name: String?,
    val created_at: String?
)

data class ChatHistoryResponse(
    val status: String? = null,
    val session_id: String? = null,
    val messages: List<ChatHistoryItem>? = emptyList()
)

data class CommandFlow(
    val title: String?,
    val step: String?,
    val type: String?,
    val icon: String?
)

data class CommandRecommendation(
    val score: Int = 0,
    val title: String?,
    val message: String?,
    val action_label: String?,
    val action_route: String?,
    val icon: String?
)

data class UltimateCommandResponse(
    val flow: CommandFlow?,
    val recommendation: CommandRecommendation?,
    val alternatives: List<CommandRecommendation>? = emptyList()
)

interface AiApi {
    @GET("funki/command")
    suspend fun getUltimateCommand(): UltimateCommandResponse

    @POST("ai/chat")
    suspend fun chat(@Body request: ChatRequest): ChatResponse

    @GET("ai/history")
    suspend fun getChatHistory(
        @Query("chat_session_id") sessionId: String? = null,
        @Query("limit") limit: Int = 30
    ): ChatHistoryResponse

    @GET("ai/live-credentials")
    suspend fun getLiveCredentials(
        @Query("agent_id") agentId: String? = null,
        @Query("chat_session_id") chatSessionId: String? = null,
        @Query("client") client: String? = null,
        @Query("fallback") fallback: String? = null
    ): LiveCredentialsResponse

    @GET("ai/vertex-ephemeral")
    suspend fun getVertexEphemeral(
        @Query("refresh") refresh: Int? = null
    ): VertexEphemeralResponse

    @GET("ai/agents")
    suspend fun getAgents(): AgentsResponse

    @POST("ai/execute")
    suspend fun executeTool(@Body request: ExecuteToolRequest): ExecuteToolResponse

    @Multipart
    @POST("ai/camera/snapshot")
    suspend fun uploadSnapshot(@Part image: MultipartBody.Part): CameraSnapshotResponse
}

