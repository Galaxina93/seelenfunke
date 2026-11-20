package de.meinseelenfunke.app.ui.screens

import de.meinseelenfunke.app.data.api.ChatMessage
import de.meinseelenfunke.app.data.repository.AiRepository
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.ResponseBody.Companion.toResponseBody
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test
import retrofit2.HttpException
import retrofit2.Response
import java.io.EOFException
import java.net.SocketTimeoutException
import java.net.UnknownHostException
import javax.net.ssl.SSLException
import javax.net.ssl.SSLHandshakeException

class ChatResilienceTest {

    @Test
    fun sanitizeHistoryForApi_stripsLegacyErrorBubblesAndSpeakTags() {
        val raw = listOf(
            ChatMessage("assistant", "<speak>Hallo Alina!</speak> Bereit."),
            ChatMessage("user", "funkira ?"),
            ChatMessage("assistant", "[Fehler beim Laden der Antwort]"),
            ChatMessage("user", "Neuer Test")
        )

        val cleaned = ChatViewModel.sanitizeHistoryForApi(
            messages = raw,
            overrideLastUserPrompt = "Neuer Test mit Kontext"
        )

        assertEquals(3, cleaned.size)
        assertEquals("Hallo Alina! Bereit.", cleaned[0].content)
        assertEquals("funkira ?", cleaned[1].content)
        assertEquals("Neuer Test mit Kontext", cleaned[2].content)
        assertTrue(cleaned.none { it.content.contains("[Fehler beim Laden der Antwort]") })
    }

    @Test
    fun formatUserFacingError_returnsActionableGermanKlartext() {
        assertEquals(
            "Antwort dauerte zu lange (Zeitüberschreitung). Bitte erneut senden.",
            ChatViewModel.formatUserFacingError(SocketTimeoutException("timeout"))
        )
        assertEquals(
            "Keine Verbindung zum Server. Bitte WLAN oder Mobilfunk prüfen.",
            ChatViewModel.formatUserFacingError(UnknownHostException("stage.mein-seelenfunke.de"))
        )
        assertEquals(
            "Verbindung beim Netzwechsel unterbrochen. Bitte erneut senden.",
            ChatViewModel.formatUserFacingError(SSLException("Software caused connection abort"))
        )

        val http503 = HttpException(
            Response.error<Any>(503, """{"status":"error"}""".toResponseBody("application/json".toMediaType()))
        )
        assertEquals(
            "Server vorübergehend ausgelastet (HTTP 503). Bitte erneut senden.",
            ChatViewModel.formatUserFacingError(http503)
        )

        val http401 = HttpException(
            Response.error<Any>(401, """{"error":"Unauthenticated"}""".toResponseBody("application/json".toMediaType()))
        )
        assertEquals(
            "Sitzung abgelaufen. Bitte kurz neu anmelden.",
            ChatViewModel.formatUserFacingError(http401)
        )
    }

    @Test
    fun isRetryableChatFailure_distinguishesTransientFromPermanentErrors() {
        assertTrue(AiRepository.isRetryableChatFailure(SocketTimeoutException("timeout")))
        assertTrue(AiRepository.isRetryableChatFailure(EOFException("unexpected end of stream")))

        val http502 = HttpException(
            Response.error<Any>(502, "Bad Gateway".toResponseBody("text/plain".toMediaType()))
        )
        assertTrue(AiRepository.isRetryableChatFailure(http502))

        val http403 = HttpException(
            Response.error<Any>(403, "Forbidden".toResponseBody("text/plain".toMediaType()))
        )
        assertFalse(AiRepository.isRetryableChatFailure(http403))
        assertFalse(AiRepository.isRetryableChatFailure(SSLHandshakeException("Pinning mismatch")))
    }

    @Test
    fun reconcileRecoveredAssistantReply_recoversPersistedAnswerAndIgnoresLegacyPlaceholder() {
        val serverHistory = listOf(
            ChatMessage("user", "Alte Frage"),
            ChatMessage("assistant", "Alte Antwort"),
            ChatMessage("user", "funkira ?"),
            ChatMessage("assistant", "Ja, bin voll da, Alina! Was gibt es zu tun?")
        )

        val recovered = AiRepository.reconcileRecoveredAssistantReply(serverHistory, "funkira ?")
        assertEquals("Ja, bin voll da, Alina! Was gibt es zu tun?", recovered)

        val unrecovered = listOf(
            ChatMessage("user", "funkira ?"),
            ChatMessage("assistant", "[Fehler beim Laden der Antwort]")
        )
        assertNull(AiRepository.reconcileRecoveredAssistantReply(unrecovered, "funkira ?"))
    }
}
