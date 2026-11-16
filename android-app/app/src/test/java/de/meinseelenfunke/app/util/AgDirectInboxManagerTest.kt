package de.meinseelenfunke.app.util

import android.app.Application
import android.content.Context
import android.graphics.Bitmap
import androidx.compose.ui.graphics.asImageBitmap
import androidx.test.core.app.ApplicationProvider
import com.google.gson.Gson
import de.meinseelenfunke.app.data.api.AgDirectApi
import de.meinseelenfunke.app.data.api.AgDirectCountsDto
import de.meinseelenfunke.app.data.api.AgDirectCreateRequest
import de.meinseelenfunke.app.data.api.AgDirectCreateResponse
import de.meinseelenfunke.app.data.api.AgDirectDeleteResponse
import de.meinseelenfunke.app.data.api.AgDirectInboxItemDto
import de.meinseelenfunke.app.data.api.AgDirectInboxListResponse
import de.meinseelenfunke.app.di.ServiceLocator
import kotlinx.coroutines.runBlocking
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.ResponseBody
import okhttp3.ResponseBody.Companion.toResponseBody
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
import java.io.IOException

@RunWith(RobolectricTestRunner::class)
@Config(sdk = [34], application = Application::class)
class AgDirectInboxManagerTest {

    private lateinit var context: Context
    private lateinit var outboxFile: File
    private val gson = Gson()

    @Before
    fun setUp() {
        context = ApplicationProvider.getApplicationContext()
        ServiceLocator.init(context)
        outboxFile = File(context.filesDir, AgDirectInboxManager.OUTBOX_FILE_NAME)
        if (outboxFile.exists()) {
            outboxFile.delete()
        }
        AgDirectInboxManager.resetMemoryCacheForTests()
    }

    @Test
    fun `maps local and server statuses deterministically to German clean badges`() {
        assertEquals(AgDirectStatus.QUEUED, AgDirectInboxManager.mapStatus("pending", isLocalOutbox = true))
        assertEquals("Wartet", AgDirectInboxManager.mapStatus("queued", isLocalOutbox = false).badgeLabel)
        assertEquals(AgDirectStatus.PENDING, AgDirectInboxManager.mapStatus("pending"))
        assertEquals("Gesendet", AgDirectInboxManager.mapStatus("pending").badgeLabel)
        assertEquals(AgDirectStatus.DELIVERED, AgDirectInboxManager.mapStatus("delivered"))
        assertEquals("Bei Falcone", AgDirectInboxManager.mapStatus("delivered").badgeLabel)
        assertEquals(AgDirectStatus.COMPLETED, AgDirectInboxManager.mapStatus("completed"))
        assertEquals("Erledigt ✓", AgDirectInboxManager.mapStatus("completed").badgeLabel)
    }

    @Test
    fun `persists offline outbox encrypted on disk and flushes automatically when back online`() = runBlocking {
        var networkOnline = false
        val serverStore = mutableListOf<AgDirectInboxItemDto>()

        val fakeApi = object : AgDirectApi {
            override suspend fun listInbox(limit: Int): AgDirectInboxListResponse {
                if (!networkOnline) throw IOException("Kein Netz")
                return AgDirectInboxListResponse(
                    success = true,
                    counts = AgDirectCountsDto(
                        pending = serverStore.count { it.status == "pending" },
                        delivered = 0,
                        completed = 0
                    ),
                    items = serverStore.toList()
                )
            }

            override suspend fun createItem(request: AgDirectCreateRequest): AgDirectCreateResponse {
                if (!networkOnline) throw IOException("Offline Timeout")
                val created = AgDirectInboxItemDto(
                    id = "ag_20261116_105200_abcd1234",
                    client_uuid = request.client_uuid,
                    title = request.title,
                    body = request.body,
                    checklist = request.checklist,
                    execution_mode = request.execution_mode,
                    channel = request.channel,
                    status = "pending",
                    has_attachment = !request.attachment_base64.isNullOrBlank(),
                    attachment_name = request.attachment_name,
                    attachment_mime = request.attachment_mime,
                    created_at = "2026-11-16T10:52:00+01:00"
                )
                serverStore.add(0, created)
                return AgDirectCreateResponse(success = true, data = created)
            }

            override suspend fun deleteItem(id: String): AgDirectDeleteResponse {
                serverStore.removeAll { it.id == id }
                return AgDirectDeleteResponse(success = true)
            }

            override suspend fun getAttachment(id: String): ResponseBody {
                return ByteArray(0).toResponseBody("image/jpeg".toMediaType())
            }
        }

        val sentLive = AgDirectInboxManager.submitNewEntry(
            context = context,
            rawText = "Produktkarte optimieren\nAbstand oben angleichen",
            checklist = listOf("Mobile prüfen", "Light-Mode prüfen"),
            executionMode = "concept",
            attachment = null,
            clientUuid = "uuid-offline-1",
            apiOverride = fakeApi
        )

        assertFalse("Must report queued when offline", sentLive)
        assertTrue("Outbox file must exist in filesDir", outboxFile.exists())

        val rawOnDisk = outboxFile.readText(Charsets.UTF_8)
        assertTrue("Outbox must be encrypted at rest with enc_v1:", rawOnDisk.startsWith("enc_v1:"))
        assertFalse(rawOnDisk.contains("Produktkarte optimieren"))

        val reloaded = AgDirectInboxManager.readOutboxFromFile(outboxFile)
        assertEquals(1, reloaded.size)
        assertEquals("uuid-offline-1", reloaded[0].client_uuid)
        assertEquals("Produktkarte optimieren", reloaded[0].title)
        assertEquals("Abstand oben angleichen", reloaded[0].body)
        assertEquals(listOf("Mobile prüfen", "Light-Mode prüfen"), reloaded[0].checklist)
        assertEquals("concept", reloaded[0].execution_mode)
        assertEquals(AgDirectStatus.QUEUED, AgDirectInboxManager.itemsFlow.value.first().status)
        assertEquals(1, AgDirectInboxManager.openCountFlow.value)

        // Network returns -> flushOutbox delivers queued item and deletes local outbox file
        networkOnline = true
        val flushedCount = AgDirectInboxManager.flushOutbox(context, apiOverride = fakeApi)
        assertEquals(1, flushedCount)
        assertFalse("Outbox file must be removed once empty", outboxFile.exists())

        AgDirectInboxManager.syncAll(context, apiOverride = fakeApi)
        val merged = AgDirectInboxManager.itemsFlow.value
        assertEquals(1, merged.size)
        assertFalse(merged[0].isLocalOutbox)
        assertEquals("ag_20261116_105200_abcd1234", merged[0].id)
        assertEquals(AgDirectStatus.PENDING, merged[0].status)
    }

    @Test
    fun `deduplicates client_uuid across local outbox and server items`() {
        val dupEntryV1 = AgDirectOutboxEntry(
            client_uuid = "same-uuid-123",
            title = "Erster Entwurf",
            execution_mode = "concept"
        )
        val dupEntryV2 = AgDirectOutboxEntry(
            client_uuid = "same-uuid-123",
            title = "Aktualisierter Entwurf",
            execution_mode = "direct"
        )
        val localOnlyEntry = AgDirectOutboxEntry(
            client_uuid = "local-only-456",
            title = "Noch im Funkloch",
            execution_mode = "concept"
        )

        val upserted = AgDirectInboxManager.upsertOutboxEntry(listOf(dupEntryV1), dupEntryV2)
        assertEquals(1, upserted.size)
        assertEquals("Aktualisierter Entwurf", upserted[0].title)
        assertEquals("direct", upserted[0].execution_mode)

        val serverItems = listOf(
            AgDirectInboxItemDto(
                id = "ag_20261116_110000_srv1",
                client_uuid = "same-uuid-123",
                title = "Aktualisierter Entwurf",
                execution_mode = "direct",
                status = "delivered",
                ag_response_summary = "Wird im Leitstand bearbeitet."
            )
        )

        val merged = AgDirectInboxManager.mergeOutboxAndServerItems(
            outboxEntries = listOf(dupEntryV2, localOnlyEntry),
            serverItems = serverItems
        )

        assertEquals(2, merged.size)
        assertEquals("local-only-456", merged[0].clientUuid)
        assertTrue(merged[0].isLocalOutbox)
        assertEquals(AgDirectStatus.QUEUED, merged[0].status)

        assertEquals("same-uuid-123", merged[1].clientUuid)
        assertFalse(merged[1].isLocalOutbox)
        assertEquals(AgDirectStatus.DELIVERED, merged[1].status)
        assertEquals("Wird im Leitstand bearbeitet.", merged[1].agResponseSummary)
    }

    @Test
    fun `serializes and deserializes API contract JSON and long voice dictations cleanly`() {
        val rawServerJson = """
            {
              "success": true,
              "counts": { "pending": 1, "delivered": 1, "completed": 2 },
              "items": [
                {
                  "id": "ag_20261116_105200_a1b2c3d4",
                  "client_uuid": "uuid-abc",
                  "title": "Hauptpunkt",
                  "body": "Details",
                  "checklist": ["Punkt 1", "Punkt 2"],
                  "execution_mode": "concept",
                  "status": "completed",
                  "has_attachment": true,
                  "attachment_name": "screen.jpg",
                  "attachment_mime": "image/jpeg",
                  "ag_response_summary": "Konzept bereitgestellt",
                  "created_at": "2026-11-16T10:52:00+01:00",
                  "delivered_at": "2026-11-16T10:53:00+01:00",
                  "completed_at": "2026-11-16T10:55:00+01:00"
                }
              ]
            }
        """.trimIndent()

        val parsed = gson.fromJson(rawServerJson, AgDirectInboxListResponse::class.java)
        assertTrue(parsed.success)
        assertNotNull(parsed.counts)
        assertEquals(2, parsed.counts?.completed)
        val first = parsed.items!!.first()
        assertEquals("ag_20261116_105200_a1b2c3d4", first.id)
        assertTrue(first.has_attachment)
        assertEquals("screen.jpg", first.attachment_name)
        assertEquals("Konzept bereitgestellt", first.ag_response_summary)

        val merged = AgDirectInboxManager.mergeOutboxAndServerItems(emptyList(), parsed.items!!)
        assertEquals(2, AgDirectInboxManager.computeOpenCount(merged, parsed.counts))
        assertEquals(AgDirectStatus.COMPLETED, merged.first().status)

        // Long voice dictation without newlines splits into compact title + full body
        val longDictation = "Bitte im Shop auf der Startseite oben rechts die neue Schnellauswahl einbauen " +
            "damit ich unterwegs im Auto oder in der Manufaktur sofort Sprachnotizen und Screenshots direkt " +
            "an Antigravity schicken kann ohne warten zu müssen"
        val (title, body) = AgDirectInboxManager.splitTitleAndBody(longDictation, emptyList(), false)
        assertTrue(title.length <= 180)
        assertTrue(title.endsWith("…"))
        assertEquals(longDictation, body)
    }

    @Test
    fun `routes all department channels persists offline with attachment and handles legacy json`() = runBlocking {
        assertEquals(
            listOf("leitung", "shop", "android", "minipc", "buchhaltung", "new"),
            AgDirectChannel.entries.map { it.code }
        )
        assertEquals("👑 Leitung", AgDirectInboxManager.resolveChannelEnum("leitung").chipLabel)
        assertEquals("🛍️ Shop", AgDirectInboxManager.resolveChannelEnum("shop").chipLabel)
        assertEquals("📱 Android", AgDirectInboxManager.resolveChannelEnum("android").chipLabel)
        assertEquals("🖥️ Mini-PC", AgDirectInboxManager.resolveChannelEnum("minipc").chipLabel)
        assertEquals("💶 Buchhaltung", AgDirectInboxManager.resolveChannelEnum("buchhaltung").chipLabel)
        assertEquals("➕ Neu", AgDirectInboxManager.resolveChannelEnum("new").chipLabel)

        // Alias & fallback normalization
        assertEquals("minipc", AgDirectInboxManager.normalizeChannel("Mini-PC"))
        assertEquals("new", AgDirectInboxManager.normalizeChannel("neu"))
        assertEquals("leitung", AgDirectInboxManager.normalizeChannel(null))
        assertEquals("leitung", AgDirectInboxManager.normalizeChannel(""))
        assertEquals("leitung", AgDirectInboxManager.normalizeChannel("unbekannt"))

        // Legacy v1.8.4 outbox JSON without "channel" field must default safely to "leitung"
        val legacyUnencryptedJson = """[{"client_uuid":"legacy-uuid-1","title":"Alter Entwurf","body":"","checklist":[],"execution_mode":"concept"}]"""
        val legacyEntries = AgDirectInboxManager.deserializeOutboxEntries(legacyUnencryptedJson)
        assertEquals(1, legacyEntries.size)
        val legacyMerged = AgDirectInboxManager.mergeOutboxAndServerItems(legacyEntries, emptyList())
        assertEquals("leitung", legacyMerged.first().channel)

        // Offline submission with channel="buchhaltung" + image attachment
        var networkOnline = false
        val capturedRequests = mutableListOf<AgDirectCreateRequest>()
        val fakeApi = object : AgDirectApi {
            override suspend fun listInbox(limit: Int): AgDirectInboxListResponse {
                if (!networkOnline) throw IOException("Offline")
                return AgDirectInboxListResponse(
                    success = true,
                    counts = AgDirectCountsDto(pending = capturedRequests.size, delivered = 0, completed = 0),
                    items = capturedRequests.mapIndexed { idx, req ->
                        AgDirectInboxItemDto(
                            id = "ag_20261116_150000_ch$idx",
                            client_uuid = req.client_uuid,
                            title = req.title,
                            body = req.body,
                            checklist = req.checklist,
                            execution_mode = req.execution_mode,
                            channel = req.channel,
                            status = "delivered",
                            has_attachment = !req.attachment_base64.isNullOrBlank(),
                            attachment_name = req.attachment_name,
                            attachment_mime = req.attachment_mime
                        )
                    }
                )
            }

            override suspend fun createItem(request: AgDirectCreateRequest): AgDirectCreateResponse {
                if (!networkOnline) throw IOException("Offline")
                capturedRequests.add(request)
                return AgDirectCreateResponse(
                    success = true,
                    data = AgDirectInboxItemDto(
                        id = "ag_20261116_150000_ch0",
                        client_uuid = request.client_uuid,
                        title = request.title,
                        body = request.body,
                        checklist = request.checklist,
                        execution_mode = request.execution_mode,
                        channel = request.channel,
                        status = "delivered",
                        has_attachment = !request.attachment_base64.isNullOrBlank(),
                        attachment_name = request.attachment_name,
                        attachment_mime = request.attachment_mime
                    )
                )
            }

            override suspend fun deleteItem(id: String): AgDirectDeleteResponse = AgDirectDeleteResponse(true)

            override suspend fun getAttachment(id: String): ResponseBody =
                ByteArray(0).toResponseBody("image/jpeg".toMediaType())
        }

        val bmp = Bitmap.createBitmap(16, 16, Bitmap.Config.ARGB_8888)
        val attachment = PreparedAgAttachment(
            base64Jpeg = "R0lGODlhAQABAIAAAAUEBA==",
            mimeType = "image/jpeg",
            fileName = "westpack_rechnung.jpg",
            previewBitmap = bmp.asImageBitmap(),
            width = 16,
            height = 16
        )

        val sentOffline = AgDirectInboxManager.submitMessage(
            context = context,
            rawText = "Westpack Beleg verbuchen",
            checklist = listOf("Cent-Betrag prüfen"),
            executionMode = "direct",
            attachment = attachment,
            channel = "buchhaltung",
            clientUuid = "uuid-buchhaltung-1",
            apiOverride = fakeApi
        )
        assertFalse(sentOffline)
        assertEquals("buchhaltung", AgDirectInboxManager.itemsFlow.value.first().channel)

        networkOnline = true
        val flushed = AgDirectInboxManager.flushOutbox(context, apiOverride = fakeApi)
        assertEquals(1, flushed)
        assertEquals(1, capturedRequests.size)
        assertEquals("buchhaltung", capturedRequests[0].channel)
        assertEquals("direct", capturedRequests[0].execution_mode)
        assertEquals("westpack_rechnung.jpg", capturedRequests[0].attachment_name)
        assertEquals("R0lGODlhAQABAIAAAAUEBA==", capturedRequests[0].attachment_base64)

        AgDirectInboxManager.syncAll(context, apiOverride = fakeApi)
        assertEquals("buchhaltung", AgDirectInboxManager.itemsFlow.value.first().channel)
    }
}
