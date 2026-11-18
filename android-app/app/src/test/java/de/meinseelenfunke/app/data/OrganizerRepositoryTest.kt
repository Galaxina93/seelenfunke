package de.meinseelenfunke.app.data

import android.app.Application
import android.content.Context
import androidx.test.core.app.ApplicationProvider
import de.meinseelenfunke.app.di.ServiceLocator
import de.meinseelenfunke.app.util.ErrorSentinelReporter
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.CompletableDeferred
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.runBlocking
import kotlinx.coroutines.test.runTest
import okhttp3.internal.http2.ConnectionShutdownException
import okhttp3.internal.http2.ErrorCode
import okhttp3.internal.http2.StreamResetException
import okhttp3.mockwebserver.Dispatcher
import okhttp3.mockwebserver.MockResponse
import okhttp3.mockwebserver.MockWebServer
import okhttp3.mockwebserver.RecordedRequest
import okhttp3.mockwebserver.SocketPolicy
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test
import org.junit.runner.RunWith
import org.robolectric.RobolectricTestRunner
import org.robolectric.annotation.Config
import java.io.File
import java.io.InterruptedIOException
import java.net.InetAddress
import java.util.concurrent.CountDownLatch
import java.util.concurrent.TimeUnit

@RunWith(RobolectricTestRunner::class)
@Config(sdk = [34], application = Application::class)
class OrganizerRepositoryTest {

    private lateinit var context: Context
    private lateinit var backendServer: MockWebServer

    @Before
    fun setUp() {
        context = ApplicationProvider.getApplicationContext()
        backendServer = MockWebServer()
        backendServer.start(InetAddress.getByName("127.0.0.1"), 0)

        ServiceLocator.init(context)
        ServiceLocator.clearAuthToken()
        context.getSharedPreferences("calendar_widget_prefs", Context.MODE_PRIVATE).edit().clear().commit()
        ErrorSentinelReporter.clearQueue(context)
        File(context.filesDir, "error_sentinel_history.json").delete()

        val mockBaseUrl = "http://127.0.0.1:${backendServer.port}/api/"
        assertTrue(ServiceLocator.saveBaseUrl(mockBaseUrl))
        ServiceLocator.saveAuthToken("66|CalendarUnitTestToken1234567890")
    }

    @After
    fun tearDown() {
        ErrorSentinelReporter.clearQueue(context)
        ServiceLocator.clearAuthToken()
        backendServer.shutdown()
    }

    @Test
    fun getCalendarEventsRethrowsCancellationExceptionAndNeverEnqueuesSentinelError() = runBlocking {
        val requestArrived = CountDownLatch(1)
        val releaseServer = CountDownLatch(1)
        backendServer.dispatcher = object : Dispatcher() {
            override fun dispatch(request: RecordedRequest): MockResponse {
                requestArrived.countDown()
                releaseServer.await(3, TimeUnit.SECONDS)
                return MockResponse()
                    .setResponseCode(200)
                    .setHeader("Content-Type", "application/json")
                    .setBody("""{"success":true,"data":[]}""")
            }
        }

        val caughtCancellation = CompletableDeferred<Throwable?>()
        val job = launch(Dispatchers.IO) {
            try {
                ServiceLocator.organizerRepository.getCalendarEvents()
                caughtCancellation.complete(null)
            } catch (t: Throwable) {
                caughtCancellation.complete(t)
                throw t
            }
        }

        assertTrue("Expected getCalendarEvents request to reach server", requestArrived.await(2, TimeUnit.SECONDS))
        job.cancel(CancellationException("Tab switched during calendar sync"))
        job.join()
        releaseServer.countDown()

        val thrown = caughtCancellation.await()
        assertTrue(
            "getCalendarEvents must rethrow CancellationException instead of swallowing it (got $thrown)",
            thrown is CancellationException
        )

        val queueFile = File(context.filesDir, "error_sentinel_queue.json")
        assertFalse("CancellationException must never enqueue a Sentinel error dossier", queueFile.exists())
    }

    @Test
    fun classifiesHttp2StreamResetAndFallsBackToEncryptedCacheWithoutSentinelError() = runTest {
        // 1. Verify HTTP/2 StreamResetException (formerly R8-obfuscated as "C"), ConnectionShutdownException & InterruptedIOException
        assertTrue(ErrorSentinelReporter.isTransientNetworkOffline(StreamResetException(ErrorCode.CANCEL)))
        assertTrue(ErrorSentinelReporter.isTransientNetworkOffline(StreamResetException(ErrorCode.REFUSED_STREAM)))
        assertTrue(ErrorSentinelReporter.isTransientNetworkOffline(ConnectionShutdownException()))
        assertTrue(ErrorSentinelReporter.isTransientNetworkOffline(InterruptedIOException("timeout")))

        // 2. Prime encrypted calendar cache via valid response
        backendServer.enqueue(
            MockResponse()
                .setResponseCode(200)
                .setHeader("Content-Type", "application/json")
                .setBody(
                    """{
                        "success": true,
                        "data": [
                            {
                                "id": "ev_101",
                                "title": "Laser-Manufaktur Wartung",
                                "start": "2026-11-20T09:00:00+01:00",
                                "end": "2026-11-20T10:00:00+01:00",
                                "is_all_day": false,
                                "category": "project",
                                "description": "Linsenreinigung",
                                "recurrence": "none",
                                "reminder_minutes": 15,
                                "priority": "high"
                            }
                        ]
                    }"""
                )
        )

        val initialRes = ServiceLocator.organizerRepository.getCalendarEvents()
        assertTrue(initialRes.isSuccess)
        assertEquals(1, initialRes.getOrNull()?.size)

        // Ensure ServiceLocator has no auth token during offline check so Sentinel queue wouldn't auto-flush
        ServiceLocator.clearAuthToken()
        ErrorSentinelReporter.clearQueue(context)

        // 3. Simulate abrupt transport drop -> cache takes over with 0 Sentinel errors
        backendServer.enqueue(MockResponse().setSocketPolicy(SocketPolicy.DISCONNECT_AT_START))

        val fallbackRes = ServiceLocator.organizerRepository.getCalendarEvents()
        assertTrue("Expected encrypted cache fallback on transport disconnect", fallbackRes.isSuccess)
        assertEquals("Laser-Manufaktur Wartung", fallbackRes.getOrNull()?.firstOrNull()?.title)

        val queueFile = File(context.filesDir, "error_sentinel_queue.json")
        assertFalse("Transient transport disconnect with cache fallback must not enqueue Sentinel error", queueFile.exists())
    }

    @Test
    fun sanitizesNullableJsonFieldsAndCapturesRealUncachedHttp500WithFullClassName() = runTest {
        ServiceLocator.clearAuthToken()
        ErrorSentinelReporter.clearQueue(context)

        // 1. Partial/nullable fields from JSON must be safely sanitized without NullPointerException
        backendServer.enqueue(
            MockResponse()
                .setResponseCode(200)
                .setHeader("Content-Type", "application/json")
                .setBody(
                    """{
                        "success": true,
                        "data": [
                            {"id": "ev_valid", "title": null, "start": "2026-11-21T12:00:00+01:00", "is_all_day": true, "category": null},
                            {"id": "", "title": "Ungueltig ohne ID", "start": "2026-11-21T12:00:00+01:00", "is_all_day": false, "category": "general"}
                        ]
                    }"""
                )
        )

        val sanitizedRes = ServiceLocator.organizerRepository.getCalendarEvents()
        assertTrue(sanitizedRes.isSuccess)
        val events = sanitizedRes.getOrNull()!!
        assertEquals(1, events.size)
        assertEquals("Termin", events[0].title)
        assertEquals("general", events[0].category)

        // 2. Wipe cache and trigger real HTTP 500 -> must fail and capture real Sentinel error with readable exception name
        context.getSharedPreferences("calendar_widget_prefs", Context.MODE_PRIVATE).edit().clear().commit()
        ErrorSentinelReporter.clearQueue(context)

        backendServer.enqueue(
            MockResponse()
                .setResponseCode(500)
                .setHeader("Content-Type", "application/json")
                .setBody("""{"success":false,"message":"Database syntax error"}""")
        )

        val failRes = ServiceLocator.organizerRepository.getCalendarEvents()
        assertTrue("Uncached HTTP 500 must return Result.failure", failRes.isFailure)

        val queueFile = File(context.filesDir, "error_sentinel_queue.json")
        assertTrue("Real uncached HTTP 500 must be queued in ErrorSentinelReporter", queueFile.exists())

        val latest = ErrorSentinelReporter.getRecentHistory(context, limit = 5).firstOrNull()
        assertNotNull(latest)
        assertEquals("error", latest!!.severity)
        assertFalse(latest.auto_healed)
        assertTrue("Sentinel title must contain readable exception name, got: ${latest.title}", latest.title.contains("HttpException"))
    }
}
