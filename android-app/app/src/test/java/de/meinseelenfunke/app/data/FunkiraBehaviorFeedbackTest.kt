package de.meinseelenfunke.app.data

import android.app.Application
import android.content.Context
import androidx.test.core.app.ApplicationProvider
import de.meinseelenfunke.app.data.api.ChatMessage
import de.meinseelenfunke.app.data.repository.AiRepository
import de.meinseelenfunke.app.di.ServiceLocator
import de.meinseelenfunke.app.ui.screens.ChatViewModel
import kotlinx.coroutines.test.runTest
import okhttp3.mockwebserver.MockResponse
import okhttp3.mockwebserver.MockWebServer
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test
import org.junit.runner.RunWith
import org.robolectric.RobolectricTestRunner
import org.robolectric.annotation.Config
import java.net.InetAddress

@RunWith(RobolectricTestRunner::class)
@Config(sdk = [34], application = Application::class)
class FunkiraBehaviorFeedbackTest {

    private lateinit var context: Context
    private lateinit var server: MockWebServer

    @Before
    fun setUp() {
        context = ApplicationProvider.getApplicationContext()
        server = MockWebServer()
        server.start(InetAddress.getByName("127.0.0.1"), 0)

        ServiceLocator.init(context)
        assertTrue(ServiceLocator.saveBaseUrl("http://127.0.0.1:${server.port}/api/"))
        ServiceLocator.saveAuthToken("77|AdminTestTokenForBehaviorApi123")
        ServiceLocator.saveUserType("admin")
        ServiceLocator.aiRepository.clearBehaviorCache()
    }

    @After
    fun tearDown() {
        ServiceLocator.aiRepository.clearBehaviorCache()
        server.shutdown()
    }

    @Test
    fun quickChipsAndFunkiGuardAndMessageKeyWorkAsExpected() {
        val codes = AiRepository.DEFAULT_BEHAVIOR_QUICK_CHIPS.map { it.code }
        assertEquals(
            listOf("too_long", "wrong_tool_or_fact", "unnecessary_question", "wrong_tone"),
            codes
        )
        assertTrue(AiRepository.isExcludedSupportAgent("Funki"))
        assertTrue(AiRepository.isExcludedSupportAgent(" funki "))
        assertFalse(AiRepository.isExcludedSupportAgent("Funkira"))

        val withId = ChatMessage(role = "assistant", content = "Hallo", memoryId = "mem-123")
        assertEquals("mem-123", ChatViewModel.messageFeedbackKey(1, withId))
    }

    @Test
    fun behaviorSummaryCachesPromptBlockAndFeedbackBlocksFunki() = runTest {
        server.enqueue(
            MockResponse()
                .setResponseCode(200)
                .setHeader("Content-Type", "application/json")
                .setBody(
                    """{
                        "status":"success",
                        "window_days":30,
                        "prompt_block":"[AKTIVE VERHALTENSREGELN VON ALINA (VERBINDLICH)]:\n- Kurz antworten."
                    }"""
                )
        )

        val summaryRes = ServiceLocator.aiRepository.getBehaviorSummary(channel = "android_live_call")
        assertTrue(summaryRes.isSuccess)
        assertTrue(
            ServiceLocator.aiRepository.getCachedBehaviorPromptBlock()
                .contains("AKTIVE VERHALTENSREGELN VON ALINA")
        )

        // Client-seitiger Funki-Schutz darf nicht einmal einen HTTP-Call fuer Funki absenden
        val funkiRes = ServiceLocator.aiRepository.submitExplicitBehaviorFeedback(
            polarity = "criticism",
            channel = "android_app",
            reasonCode = "too_long",
            agentName = "Funki"
        )
        assertTrue(funkiRes.isFailure)
        assertEquals(1, server.requestCount)

        // Explizites Feedback fuer Funkira trifft den Mobile-Endpunkt
        server.enqueue(
            MockResponse()
                .setResponseCode(200)
                .setHeader("Content-Type", "application/json")
                .setBody("""{"status":"success","detected":true,"polarity":"criticism","category":"tone_length"}""")
        )
        val okRes = ServiceLocator.aiRepository.submitExplicitBehaviorFeedback(
            polarity = "criticism",
            channel = "android_app",
            reasonCode = "too_long",
            agentName = "Funkira",
            memoryId = "mem-456"
        )
        assertTrue(okRes.isSuccess)
        val recordedReq = server.takeRequest()
        assertEquals("/api/ai/funkira/behavior/summary?channel=android_live_call&days=30", recordedReq.path)
        val fbReq = server.takeRequest()
        assertEquals("/api/ai/funkira/behavior/feedback", fbReq.path)
        assertTrue(fbReq.body.readUtf8().contains("too_long"))
    }
}
