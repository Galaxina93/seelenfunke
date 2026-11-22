package de.meinseelenfunke.app.util

import android.content.Context
import android.graphics.Bitmap
import android.graphics.Color
import androidx.test.core.app.ApplicationProvider
import de.meinseelenfunke.app.di.ServiceLocator
import kotlinx.coroutines.runBlocking
import okhttp3.mockwebserver.MockResponse
import okhttp3.mockwebserver.MockWebServer
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test
import org.junit.runner.RunWith
import org.robolectric.RobolectricTestRunner
import java.io.ByteArrayOutputStream
import java.io.File

@RunWith(RobolectricTestRunner::class)
class KdriveTrichterScannerTest {

    private lateinit var context: Context
    private lateinit var server: MockWebServer

    @Before
    fun setUp() {
        context = ApplicationProvider.getApplicationContext()
        ServiceLocator.init(context)
        File(context.filesDir, "kdrive_trichter_queue").deleteRecursively()
        KdriveTrichterOfflineQueue.refreshCount(context)
        server = MockWebServer()
        server.start()
    }

    @After
    fun tearDown() {
        File(context.filesDir, "kdrive_trichter_queue").deleteRecursively()
        try {
            server.shutdown()
        } catch (_: Exception) {}
    }

    private fun makeSampleJpeg(color: Int): ByteArray {
        val bmp = Bitmap.createBitmap(320, 460, Bitmap.Config.ARGB_8888)
        bmp.eraseColor(color)
        val out = ByteArrayOutputStream()
        bmp.compress(Bitmap.CompressFormat.JPEG, 85, out)
        bmp.recycle()
        return out.toByteArray()
    }

    @Test
    fun `batch scanner compiles valid individual and merged PDFs`() {
        val page1 = makeSampleJpeg(Color.WHITE)
        val page2 = makeSampleJpeg(Color.LTGRAY)
        val page3 = makeSampleJpeg(Color.GRAY)

        val stubPdfBuilder: (List<ByteArray>) -> ByteArray = { pages ->
            ("%PDF-1.4 pages=${pages.size}\n%%EOF").toByteArray(Charsets.UTF_8)
        }

        val splitItems = de.meinseelenfunke.app.ui.components.compileScannedPagesForTrichter(
            pages = listOf(page1, page2, page3),
            splitIntoSinglePdfs = true,
            cachedMergedPdf = null,
            stamp = "2026-11-20_193000",
            pdfBuilder = stubPdfBuilder
        )
        assertEquals(3, splitItems.size)
        assertEquals("Scan_2026-11-20_193000_01.pdf", splitItems[0].fileName)
        assertEquals("Scan_2026-11-20_193000_02.pdf", splitItems[1].fileName)
        assertEquals("Scan_2026-11-20_193000_03.pdf", splitItems[2].fileName)
        assertTrue(splitItems.all { it.mimeType == "application/pdf" && it.bytes.isNotEmpty() })

        val fakeNativePdf = "%PDF-1.4\nMultiPage\n%%EOF".toByteArray(Charsets.UTF_8)
        val mergedItems = de.meinseelenfunke.app.ui.components.compileScannedPagesForTrichter(
            pages = listOf(page1, page2, page3),
            splitIntoSinglePdfs = false,
            cachedMergedPdf = fakeNativePdf,
            stamp = "2026-11-20_193000"
        )
        assertEquals(1, mergedItems.size)
        assertEquals("Scan_2026-11-20_193000.pdf", mergedItems[0].fileName)
        assertTrue(String(mergedItems[0].bytes, Charsets.UTF_8).startsWith("%PDF-1.4"))
    }

    @Test
    fun `offline queue encrypts payload at rest and flushes to kdrive trichter endpoint`() = runBlocking {
        val baseUrl = server.url("/api/").toString()
        ServiceLocator.saveBaseUrl(baseUrl)
        ServiceLocator.saveAuthToken("test-sanctum-token-123")

        val samplePdf = "%PDF-1.4\nSecret Invoice Content\n%%EOF".toByteArray(Charsets.UTF_8)
        val queued = KdriveTrichterOfflineQueue.enqueue(
            context = context,
            bytes = samplePdf,
            fileName = "Scan_2026-11-20_01.pdf",
            mimeType = "application/pdf",
            customTitle = "Lieferantenrechnung",
            source = "android_scanner",
            batchIndex = 1,
            batchTotal = 2
        )

        assertTrue(queued)
        assertEquals(1, KdriveTrichterOfflineQueue.pendingCount.value)

        // Verify encrypted on disk (zero plaintext PDF header or content)
        val queueDir = File(context.filesDir, "kdrive_trichter_queue")
        val binFile = queueDir.listFiles { f -> f.name.endsWith(".bin") }?.firstOrNull()
        assertNotNull(binFile)
        val rawDiskStr = String(binFile!!.readBytes(), Charsets.ISO_8859_1)
        assertFalse(rawDiskStr.contains("%PDF"))
        assertFalse(rawDiskStr.contains("Secret Invoice Content"))

        server.enqueue(
            MockResponse()
                .setResponseCode(200)
                .setHeader("Content-Type", "application/json")
                .setBody("""{"success":true,"message":"Im kDrive Eingang-Trichter bereitgestellt."}""")
        )

        val flushedCount = ServiceLocator.financeRepository.flushOfflineTrichterQueue()
        assertEquals(1, flushedCount)
        assertEquals(0, KdriveTrichterOfflineQueue.pendingCount.value)

        val recorded = server.takeRequest()
        assertEquals("/api/funki/kdrive-trichter/enqueue", recorded.path)
    }

    @Test
    fun `native DCTDecode PDF builder embeds JPEGs directly without uncompressed bitmap bloat`() {
        val page1 = makeSampleJpeg(Color.WHITE)
        val page2 = makeSampleJpeg(Color.LTGRAY)
        val page3 = makeSampleJpeg(Color.GRAY)

        val pdfBytes = ImageUtils.buildPdfFromJpegPages(listOf(page1, page2, page3))
        assertNotNull(pdfBytes)
        val rawPdf = String(pdfBytes!!, Charsets.ISO_8859_1)

        assertTrue(rawPdf.startsWith("%PDF-1.4"))
        assertTrue(rawPdf.contains("/Count 3"))
        assertTrue(rawPdf.contains("/Filter /DCTDecode"))
        assertTrue(rawPdf.contains("xref\n0 12"))
        assertTrue(rawPdf.trimEnd().endsWith("%%EOF"))

        val totalInputBytes = page1.size + page2.size + page3.size
        assertTrue(
            "Expected compact DCTDecode PDF (< ${totalInputBytes + 4096} bytes), got ${pdfBytes.size}",
            pdfBytes.size < totalInputBytes + 4096
        )
    }

    @Test
    fun `kdrive webdav config save and status roundtrip via FinanceRepository`() = runBlocking {
        val baseUrl = server.url("/api/").toString()
        ServiceLocator.saveBaseUrl(baseUrl)
        ServiceLocator.saveAuthToken("test-sanctum-token-123")

        server.enqueue(
            MockResponse()
                .setResponseCode(200)
                .setHeader("Content-Type", "application/json")
                .setBody(
                    """{
                        "success": true,
                        "message": "kDrive verbunden! 1 wartende(r) Scan(s) direkt verarbeitet und einsortiert.",
                        "data": {
                            "configured": true,
                            "drive_id": "654321",
                            "email": "kontakt@mein-seelenfunke.de",
                            "has_password": true,
                            "direct_sort": true,
                            "pending_relay_count": 0,
                            "flushed_count": 1
                        }
                    }"""
                )
        )

        val saveRes = ServiceLocator.financeRepository.saveKdriveTrichterConfig(
            driveId = "654321",
            email = "kontakt@mein-seelenfunke.de",
            appPassword = "SecretAppPassword123",
            directSort = true
        )
        assertTrue(saveRes.isSuccess)
        val payload = saveRes.getOrThrow()
        assertEquals(true, payload.data?.configured)
        assertEquals("654321", payload.data?.drive_id)
        assertEquals(1, payload.data?.flushed_count)

        val recorded = server.takeRequest()
        assertEquals("/api/funki/kdrive-trichter/config", recorded.path)
        assertEquals("POST", recorded.method)
    }
}
