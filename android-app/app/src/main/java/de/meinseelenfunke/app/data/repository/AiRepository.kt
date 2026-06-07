package de.meinseelenfunke.app.data.repository

import com.google.ai.client.generativeai.GenerativeModel
import com.google.ai.client.generativeai.type.content
import de.meinseelenfunke.app.data.api.ChatMessage
import de.meinseelenfunke.app.data.api.ChatRequest
import de.meinseelenfunke.app.data.api.ChatResponse
import de.meinseelenfunke.app.di.ServiceLocator
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext

class AiRepository(private val serviceLocator: ServiceLocator) {

    // Cache local generative model to avoid rebuilding
    private var localGenerativeModel: GenerativeModel? = null
    private var lastApiKey: String? = null

    // Send a message to the Laravel backend remote agents
    suspend fun sendChatMessage(
        prompt: String,
        history: List<ChatMessage>,
        agentId: String? = null
    ): Result<ChatResponse> {
        return try {
            val response = serviceLocator.getAiApi().chat(
                ChatRequest(prompt = prompt, history = history, agent_id = agentId)
            )
            Result.success(response)
        } catch (e: Exception) {
            if (e is kotlinx.coroutines.CancellationException) throw e
            Result.failure(e)
        }
    }

    // Run a prompt locally on-device using Google Gemini SDK (Gemini Nano / Flash)
    suspend fun generateLocalResponse(
        prompt: String,
        apiKey: String,
        modelName: String = "gemini-1.5-flash"
    ): Result<String> = withContext(Dispatchers.IO) {
        try {
            if (apiKey.isBlank()) {
                return@withContext Result.failure(Exception("Bitte trage einen gültigen Gemini API Key in den Einstellungen ein."))
            }

            // Initialize or reuse model
            if (localGenerativeModel == null || lastApiKey != apiKey) {
                lastApiKey = apiKey
                localGenerativeModel = GenerativeModel(
                    modelName = modelName,
                    apiKey = apiKey
                )
            }

            val response = localGenerativeModel!!.generateContent(
                content {
                    text(prompt)
                }
            )

            val text = response.text
            if (text != null) {
                Result.success(text)
            } else {
                Result.failure(Exception("Keine Antwort vom lokalen Gemini Modell erhalten."))
            }
        } catch (e: Exception) {
            if (e is kotlinx.coroutines.CancellationException) throw e
            Result.failure(e)
        }
    }

    suspend fun getLiveCredentials(agentId: String? = null, chatSessionId: String? = null): Result<de.meinseelenfunke.app.data.api.LiveCredentialsResponse> {
        return try {
            val response = serviceLocator.getAiApi().getLiveCredentials(agentId, chatSessionId)
            Result.success(response)
        } catch (e: Exception) {
            if (e is kotlinx.coroutines.CancellationException) throw e
            Result.failure(e)
        }
    }

    suspend fun executeTool(functionName: String, args: com.google.gson.JsonElement?, sessionId: String? = null): Result<de.meinseelenfunke.app.data.api.ExecuteToolResponse> {
        return try {
            val response = serviceLocator.getAiApi().executeTool(
                de.meinseelenfunke.app.data.api.ExecuteToolRequest(function = functionName, args = args, session_id = sessionId)
            )
            Result.success(response)
        } catch (e: Exception) {
            if (e is kotlinx.coroutines.CancellationException) throw e
            Result.failure(e)
        }
    }

    suspend fun getAgents(): Result<List<de.meinseelenfunke.app.data.api.AiAgent>> {
        return try {
            val response = serviceLocator.getAiApi().getAgents()
            if (response.success == "success" || response.success == "true") {
                Result.success(response.data)
            } else {
                Result.failure(Exception("Fehler beim Laden der Agenten."))
            }
        } catch (e: Exception) {
            if (e is kotlinx.coroutines.CancellationException) throw e
            Result.failure(e)
        }
    }
}

