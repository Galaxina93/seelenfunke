package de.meinseelenfunke.app.util

import android.app.Application
import android.content.Context
import android.graphics.Bitmap
import androidx.compose.ui.graphics.asImageBitmap
import androidx.test.core.app.ApplicationProvider
import com.google.gson.JsonArray
import com.google.gson.JsonObject
import de.meinseelenfunke.app.data.api.AgDirectApi
import de.meinseelenfunke.app.data.api.AgDirectCountsDto
import de.meinseelenfunke.app.data.api.AgDirectCreateRequest
import de.meinseelenfunke.app.data.api.AgDirectCreateResponse
import de.meinseelenfunke.app.data.api.AgDirectDeleteResponse
import de.meinseelenfunke.app.data.api.AgDirectInboxItemDto
import de.meinseelenfunke.app.data.api.AgDirectInboxListResponse
import de.meinseelenfunke.app.data.api.AgDirectResolveRequest
import de.meinseelenfunke.app.data.api.AgDirectResolveResponse
import de.meinseelenfunke.app.di.ServiceLocator
import kotlinx.coroutines.launch
import kotlinx.coroutines.runBlocking
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.ResponseBody
import okhttp3.ResponseBody.Companion.toResponseBody
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test
import org.junit.runner.RunWith
import org.robolectric.RobolectricTestRunner
import org.robolectric.annotation.Config
import java.io.ByteArrayOutputStream
import java.io.File
import java.io.IOException
import java.util.Base64

@RunWith(RobolectricTestRunner::class)
@Config(sdk = [34], application = Application::class)
class FunkiraEloquestParityTest {

    private lateinit var context: Context

    @Before
    fun setUp() {
        context = ApplicationProvider.getApplicationContext()
        ServiceLocator.init(context)
        File(context.filesDir, AgDirectInboxManager.OUTBOX_FILE_NAME).delete()
        AgDirectInboxManager.resetMemoryCacheForTests()
        FalconCameraController.clearLastCapturedPhoto()
        FunkiraLocalMemoryStore.clearMemory(context)
        FunkiraPackageStore.clearAllForTests(context)
    }

    @Test
    fun `local memory encrypts payload applies noise gate dedupe and rolling digest`() {
        val baseMs = 1_760_000_000_000L

        // Noise gate skips trivial acks
        assertFalse(FunkiraLocalMemoryStore.recordTurn(context, "user", "ok", "live_call", baseMs))
        assertFalse(FunkiraLocalMemoryStore.recordTurn(context, "user", "alles klar", "live_call", baseMs + 1000L))
        assertEquals(0, FunkiraLocalMemoryStore.memoryCountFlow.value)

        // Meaningful turn strips AI dashes and is stored encrypted
        assertTrue(
            FunkiraLocalMemoryStore.recordTurn(
                context,
                "user",
                "Deadline fuer Laser-Versand \u2013 bitte morgen pruefen.",
                "live_call",
                baseMs + 2000L
            )
        )
        // 15s deduplication prevents duplicate turn spam
        assertFalse(
            FunkiraLocalMemoryStore.recordTurn(
                context,
                "user",
                "Deadline fuer Laser-Versand \u2013 bitte morgen pruefen.",
                "live_call",
                baseMs + 5000L
            )
        )

        val prefs = context.getSharedPreferences(FunkiraLocalMemoryStore.PREFS_NAME, Context.MODE_PRIVATE)
        val rawStored = prefs.getString("enc_turns_json", "").orEmpty()
        assertTrue(rawStored.startsWith("enc_v1:"))
        assertFalse(rawStored.contains("Laser-Versand"))

        // Exceed MAX_RAW_TURNS (30) to trigger rolling digest compaction
        for (i in 1..34) {
            FunkiraLocalMemoryStore.recordTurn(
                context,
                if (i % 2 == 1) "user" else "funkira",
                "Wichtiges Thema Nummer $i rund um Shop Paket und Rechnung",
                "live_call",
                baseMs + 30_000L + (i * 20_000L)
            )
        }

        val hydrated = FunkiraLocalMemoryStore.hydrate(context)
        assertEquals(FunkiraLocalMemoryStore.MAX_RAW_TURNS, hydrated.turns.size)
        assertTrue(hydrated.rollingDigest.isNotBlank())
        assertTrue(hydrated.lastTopicSummary.contains("Nummer 34"))

        val prompt = FunkiraLocalMemoryStore.buildMemoryContextPromptDirective(context)
        assertTrue(prompt.length <= FunkiraLocalMemoryStore.MAX_MEMORY_PROMPT_CHARS)
        assertTrue(prompt.contains("LOKALES KURZZEITGEDAECHTNIS"))
        assertTrue(prompt.contains("GEMERKTE KERNFAKTEN"))
        assertTrue(prompt.contains("LETZTES THEMA"))

        FunkiraLocalMemoryStore.clearMemory(context)
        assertEquals(0, FunkiraLocalMemoryStore.hydrate(context).turns.size)
    }

    @Test
    fun `package store stages bundles attaches multiple photos and dispatches consolidated order`() = runBlocking {
        val postedRequests = mutableListOf<AgDirectCreateRequest>()
        val fakeApi = object : AgDirectApi {
            override suspend fun listInbox(limit: Int): AgDirectInboxListResponse =
                AgDirectInboxListResponse(success = true, counts = AgDirectCountsDto(), items = emptyList())

            override suspend fun createItem(request: AgDirectCreateRequest): AgDirectCreateResponse {
                postedRequests.add(request)
                return AgDirectCreateResponse(
                    success = true,
                    data = AgDirectInboxItemDto(
                        id = "ag_pkg_${postedRequests.size}",
                        client_uuid = request.client_uuid,
                        title = request.title,
                        body = request.body,
                        checklist = request.checklist,
                        execution_mode = request.execution_mode,
                        channel = request.channel,
                        status = "pending",
                        has_attachment = !request.attachment_base64.isNullOrBlank(),
                        attachment_name = request.attachment_name,
                        attachment_mime = request.attachment_mime
                    )
                )
            }

            override suspend fun resolveBlockade(id: String, request: AgDirectResolveRequest): AgDirectResolveResponse =
                AgDirectResolveResponse(success = true)

            override suspend fun deleteItem(id: String): AgDirectDeleteResponse =
                AgDirectDeleteResponse(success = true)

            override suspend fun getAttachment(id: String): ResponseBody =
                ByteArray(0).toResponseBody("image/jpeg".toMediaType())
        }

        val pkg1 = FunkiraPackageStore.stageRequirement(
            context = context,
            item = "Produkt-Konfigurator Button groesser",
            channel = "shop",
            executionMode = "concept",
            source = "voice"
        )
        assertNotNull(pkg1)
        val pkg2 = FunkiraPackageStore.stageRequirement(
            context = context,
            item = "Warenkorb Badge vergolden",
            channel = "shop",
            executionMode = "direct",
            source = "voice"
        )
        assertEquals(pkg1!!.uuid, pkg2!!.uuid)
        assertEquals(2, pkg2.checklist.size)

        // Duplicate bullet is ignored
        val pkgDup = FunkiraPackageStore.stageRequirement(
            context = context,
            item = "warenkorb badge vergolden",
            channel = "shop"
        )
        assertEquals(2, pkgDup!!.checklist.size)

        // Attach 2 distinct photos to package
        val photo1 = buildPreparedTestPhoto("screen_1.jpg", android.graphics.Color.RED)
        val photo2 = buildPreparedTestPhoto("screen_2.jpg", android.graphics.Color.BLUE)
        FunkiraPackageStore.attachMediaToPackage(context, photo1, "Startseite", pkg1.uuid)
        val withTwoPhotos = FunkiraPackageStore.attachMediaToPackage(context, photo2, "Warenkorb", pkg1.uuid)
        assertEquals(2, withTwoPhotos!!.attachmentCount)

        // Encrypted on disk
        val rawDisk = File(context.filesDir, FunkiraPackageStore.PACKAGES_FILE_NAME).readText(Charsets.UTF_8)
        assertTrue(rawDisk.startsWith("enc_v1:"))
        assertFalse(rawDisk.contains("Warenkorb Badge"))

        // Proactive kick available
        val kick = FunkiraPackageStore.buildProactivePackageKick(context)
        assertNotNull(kick)
        assertTrue(kick!!.contains("2 Punkten"))
        assertTrue(kick.contains("2 Foto(s)"))

        // Release consolidated package -> dispatches master order + linked extra attachment
        val release = FunkiraPackageStore.releasePackage(context, pkg1.uuid, apiOverride = fakeApi)
        assertTrue(release.ok)
        assertTrue(release.sentLive)
        assertEquals(2, postedRequests.size)
        assertEquals("shop", postedRequests[0].channel)
        assertEquals(2, postedRequests[0].checklist.size)
        assertEquals("screen_1.jpg", postedRequests[0].attachment_name)
        assertTrue(postedRequests[1].title.contains("Anhang #2/2"))
        assertEquals("screen_2.jpg", postedRequests[1].attachment_name)
        assertTrue(FunkiraPackageStore.hydrate(context).isEmpty())
    }

    @Test
    fun `package release falls back to encrypted offline outbox when network fails`() = runBlocking {
        val offlineApi = object : AgDirectApi {
            override suspend fun listInbox(limit: Int): AgDirectInboxListResponse = throw IOException("Offline")
            override suspend fun createItem(request: AgDirectCreateRequest): AgDirectCreateResponse = throw IOException("Offline")
            override suspend fun resolveBlockade(id: String, request: AgDirectResolveRequest): AgDirectResolveResponse = throw IOException("Offline")
            override suspend fun deleteItem(id: String): AgDirectDeleteResponse = throw IOException("Offline")
            override suspend fun getAttachment(id: String): ResponseBody = throw IOException("Offline")
        }

        val pkg = FunkiraPackageStore.stageRequirement(
            context = context,
            item = "Offline-Testpunkt fuer Android Widget",
            channel = "android"
        )!!
        val release = FunkiraPackageStore.releasePackage(context, pkg.uuid, apiOverride = offlineApi)
        assertTrue(release.ok)
        assertFalse(release.sentLive)

        AgDirectInboxManager.hydrateLocalState(context)
        val queued = AgDirectInboxManager.itemsFlow.value
        assertEquals(1, queued.size)
        assertTrue(queued.first().isLocalOutbox)
        assertEquals("android", queued.first().channel)
    }

    @Test
    fun `falcon client tools declare and execute stage list release and memory tools`() = runBlocking {
        val decls = JsonArray()
        FalconClientTools.appendFalconToolDeclarations(decls)
        val names = (0 until decls.size()).mapNotNull { decls.get(it).asJsonObject.get("name")?.asString }.toSet()
        assertTrue("falcon_stage_to_package" in names)
        assertTrue("falcon_list_draft_packages" in names)
        assertTrue("falcon_release_package" in names)
        assertTrue("falcon_get_recent_memory" in names)

        // Stage captured camera photo into package via voice tool
        val bmp = Bitmap.createBitmap(16, 16, Bitmap.Config.ARGB_8888)
        val baos = ByteArrayOutputStream()
        bmp.compress(Bitmap.CompressFormat.JPEG, 80, baos)
        val b64 = Base64.getEncoder().encodeToString(baos.toByteArray())
        FalconCameraController.setLastCapturedPhotoForTest(
            FalconCapturedPhoto(
                base64Jpeg = b64,
                fileName = "voice_cam.jpg",
                previewBitmap = bmp.asImageBitmap(),
                width = 16,
                height = 16,
                sessionId = "s1",
                attachedToInbox = false
            )
        )

        val stageArgs = JsonObject().apply {
            addProperty("item", "Laser-Fokus pruefen")
            addProperty("channel", "shop")
            addProperty("attach_captured_photo", true)
        }
        val stageRes = FalconClientTools.executeLocalFalconTool(
            context = context,
            toolName = "falcon_stage_to_package",
            argsElement = stageArgs,
            chatSessionId = "s1",
            onEmotionChanged = {},
            onNavigate = {}
        )
        assertEquals("success", stageRes.get("status").asString)
        assertTrue(stageRes.get("photo_attached").asBoolean)
        assertTrue(FalconCameraController.lastCapturedPhoto.value!!.attachedToInbox)

        val listRes = FalconClientTools.executeLocalFalconTool(
            context = context,
            toolName = "falcon_list_draft_packages",
            argsElement = JsonObject(),
            chatSessionId = "s1",
            onEmotionChanged = {},
            onNavigate = {}
        )
        assertEquals(1, listRes.get("count").asInt)

        FunkiraLocalMemoryStore.recordTurn(context, "user", "Bitte merke dir die Shop-Aenderung", "live_call")
        val memRes = FalconClientTools.executeLocalFalconTool(
            context = context,
            toolName = "falcon_get_recent_memory",
            argsElement = JsonObject(),
            chatSessionId = "s1",
            onEmotionChanged = {},
            onNavigate = {}
        )
        assertEquals("success", memRes.get("status").asString)
        assertEquals(1, memRes.get("memory_count").asInt)
    }

    @Test
    fun `video 4-keyframe storyboard pool camera guard and explicit concept override work end to end`() = runBlocking {
        // 1. Create a valid MP4 ISO-BMFF header stub file (<= 20s) and extract 4 keyframes into 2x2 Storyboard + MediaPool
        val mp4File = File(context.cacheDir, "test_20s_clip.mp4")
        val ftypBytes = byteArrayOf(
            0x00, 0x00, 0x00, 0x18,
            'f'.code.toByte(), 't'.code.toByte(), 'y'.code.toByte(), 'p'.code.toByte(),
            'i'.code.toByte(), 's'.code.toByte(), 'o'.code.toByte(), 'm'.code.toByte(),
            0x00, 0x00, 0x02, 0x00,
            'i'.code.toByte(), 's'.code.toByte(), 'o'.code.toByte(), 'm'.code.toByte(),
            'm'.code.toByte(), 'p'.code.toByte(), '4'.code.toByte(), '2'.code.toByte()
        )
        mp4File.writeBytes(ftypBytes)

        val frames = listOf(
            Bitmap.createBitmap(80, 60, Bitmap.Config.ARGB_8888).apply { eraseColor(android.graphics.Color.RED) },
            Bitmap.createBitmap(80, 60, Bitmap.Config.ARGB_8888).apply { eraseColor(android.graphics.Color.GREEN) },
            Bitmap.createBitmap(80, 60, Bitmap.Config.ARGB_8888).apply { eraseColor(android.graphics.Color.BLUE) },
            Bitmap.createBitmap(80, 60, Bitmap.Config.ARGB_8888).apply { eraseColor(android.graphics.Color.YELLOW) }
        )
        val storyboard = FalconCameraController.importVideoWithKeyframes(
            context = context,
            uri = android.net.Uri.fromFile(mp4File),
            sessionId = "s-vid"
        ) { _ ->
            Pair(12_000L, frames)
        }
        assertNotNull(storyboard)
        assertEquals(1080, storyboard!!.width)
        assertEquals(720, storyboard.height)

        val pool = FalconCameraController.mediaPoolFlow.value
        assertEquals(2, pool.size)
        assertTrue(pool.any { it.mimeType == "image/jpeg" && it.fileName.startsWith("video_keyframes_") })
        assertTrue(pool.any { it.mimeType == "video/mp4" && it.fileName.endsWith(".mp4") })

        // Camera protection directive informs Funkira about both video & keyframes
        val directive = FalconClientTools.buildAttachedMediaSystemDirective(AgDirectInboxManager.getActiveStagedAttachment())
        assertNotNull(directive)
        assertTrue(directive!!.contains("20s-Video & Keyframes"))

        // Over-limit video (> 20s tolerance) is rejected cleanly
        val overLimit = FalconCameraController.importVideoWithKeyframes(
            context = context,
            uri = android.net.Uri.fromFile(mp4File)
        ) { _ ->
            Pair(35_000L, frames)
        }
        assertNull(overLimit)

        // Stage pooled video + keyframe storyboard into a Sammelpaket in 1 tool call
        val stagePkgRes = FalconClientTools.executeLocalFalconTool(
            context = context,
            toolName = "falcon_stage_to_package",
            argsElement = JsonObject().apply {
                addProperty("item", "Bitte Laser-Vorschub im Video pruefen")
                addProperty("channel", "minipc")
                addProperty("attach_captured_photo", true)
            },
            chatSessionId = "s-vid",
            onEmotionChanged = {},
            onNavigate = {}
        )
        assertEquals("success", stagePkgRes.get("status").asString)
        assertEquals(2, stagePkgRes.get("attachment_count").asInt)
        assertTrue(FalconCameraController.mediaPoolFlow.value.isEmpty())

        // 2. Explicit "Konzept" / "erst planen" phrasing honors execution_mode="concept" even when isVoiceDirect=true
        AgDirectInboxManager.setVoiceDirectExecutionEnabled(context, true)
        val postedModes = mutableListOf<String>()
        val fakeApi = object : AgDirectApi {
            override suspend fun listInbox(limit: Int): AgDirectInboxListResponse =
                AgDirectInboxListResponse(success = true, counts = AgDirectCountsDto(), items = emptyList())
            override suspend fun createItem(request: AgDirectCreateRequest): AgDirectCreateResponse {
                postedModes.add(request.execution_mode)
                return AgDirectCreateResponse(
                    success = true,
                    data = AgDirectInboxItemDto(
                        id = "ag_c1",
                        client_uuid = request.client_uuid,
                        title = request.title,
                        body = request.body,
                        checklist = request.checklist,
                        execution_mode = request.execution_mode,
                        channel = request.channel,
                        status = "pending",
                        has_attachment = false
                    )
                )
            }
            override suspend fun resolveBlockade(id: String, request: AgDirectResolveRequest): AgDirectResolveResponse =
                AgDirectResolveResponse(success = true)
            override suspend fun deleteItem(id: String): AgDirectDeleteResponse =
                AgDirectDeleteResponse(success = true)
            override suspend fun getAttachment(id: String): ResponseBody =
                ByteArray(0).toResponseBody("image/jpeg".toMediaType())
        }

        val conceptRes = FalconClientTools.executeLocalFalconTool(
            context = context,
            toolName = "falcon_send_to_antigravity",
            argsElement = JsonObject().apply {
                addProperty("title", "Neues Konzept fuer Verpackungs-Automatik")
                addProperty("body", "Bitte erst planen und auf mein Go warten")
                addProperty("execution_mode", "concept")
                addProperty("channel", "shop")
            },
            chatSessionId = "s-conc",
            onEmotionChanged = {},
            onNavigate = {},
            agApiOverride = fakeApi
        )
        assertEquals("concept", conceptRes.get("execution_mode").asString)
        assertEquals(listOf("concept"), postedModes)
    }

    @Test
    fun `realtime antigravity status sync emits transitions classifies working vs waiting and adapts poll speed`() = runBlocking {
        var currentPhase = 0
        val fakeApi = object : AgDirectApi {
            override suspend fun listInbox(limit: Int): AgDirectInboxListResponse {
                val itemStatus = when (currentPhase) {
                    0 -> "pending"
                    1 -> "delivered"
                    2 -> "awaiting_go"
                    else -> "completed"
                }
                return AgDirectInboxListResponse(
                    success = true,
                    counts = AgDirectCountsDto(
                        pending = if (itemStatus == "pending") 1 else 0,
                        delivered = if (itemStatus == "delivered") 1 else 0,
                        awaiting_go = if (itemStatus == "awaiting_go") 1 else 0,
                        completed = if (itemStatus == "completed") 1 else 0
                    ),
                    items = listOf(
                        AgDirectInboxItemDto(
                            id = "ag_rt_1",
                            client_uuid = "uuid-rt-1",
                            title = "Konzept & Smart-Home Update",
                            body = "Test",
                            checklist = emptyList(),
                            execution_mode = "concept",
                            channel = "android",
                            status = itemStatus,
                            pending_question = if (itemStatus == "awaiting_go") "Soll ich ausrollen?" else null,
                            ag_response_summary = if (itemStatus == "completed") "Fertig ausgerollt" else null
                        )
                    )
                )
            }
            override suspend fun createItem(request: AgDirectCreateRequest): AgDirectCreateResponse =
                AgDirectCreateResponse(success = true)
            override suspend fun resolveBlockade(id: String, request: AgDirectResolveRequest): AgDirectResolveResponse =
                AgDirectResolveResponse(success = true)
            override suspend fun deleteItem(id: String): AgDirectDeleteResponse =
                AgDirectDeleteResponse(success = true)
            override suspend fun getAttachment(id: String): ResponseBody =
                ByteArray(0).toResponseBody("image/jpeg".toMediaType())
        }

        val capturedTransitions = mutableListOf<AgStatusTransitionEvent>()
        val collectJob = kotlinx.coroutines.CoroutineScope(kotlinx.coroutines.Dispatchers.Unconfined).launch {
            AgDirectInboxManager.statusTransitionEvents.collect { capturedTransitions.add(it) }
        }

        try {
            // Phase 0 (pending concept): MUST be classified as actively working (NOT waiting_go!) and use fast 2800ms poll!
            currentPhase = 0
            AgDirectInboxManager.syncAll(context, apiOverride = fakeApi)
            val s0 = AgDirectInboxManager.realtimeStatusFlow.value
            assertEquals("working", s0.primaryState)
            assertEquals(1, s0.activeWorkingCount)
            assertEquals(0, s0.waitingForGoCount)
            assertEquals(AgDirectInboxManager.FAST_POLL_INTERVAL_MS, AgDirectInboxManager.computeRecommendedPollIntervalMs())

            // Phase 1 (delivered -> Antigravity actively working): emits DELIVERED transition!
            currentPhase = 1
            AgDirectInboxManager.syncAll(context, apiOverride = fakeApi)
            assertEquals(1, capturedTransitions.size)
            assertEquals(AgDirectStatus.DELIVERED, capturedTransitions.last().newStatus)
            assertTrue(capturedTransitions.last().toHumanEventLine().contains("arbeitet aktiv"))

            // Phase 2 (awaiting_go -> waiting for Alina): emits AWAITING_GO transition and switches to idle poll once boost expires!
            currentPhase = 2
            AgDirectInboxManager.syncAll(context, apiOverride = fakeApi)
            val s2 = AgDirectInboxManager.realtimeStatusFlow.value
            assertEquals("waiting_go", s2.primaryState)
            assertEquals(0, s2.activeWorkingCount)
            assertEquals(1, s2.waitingForGoCount)
            assertEquals(AgDirectStatus.AWAITING_GO, capturedTransitions.last().newStatus)
            assertEquals(AgDirectInboxManager.IDLE_POLL_INTERVAL_MS, AgDirectInboxManager.computeRecommendedPollIntervalMs(Long.MAX_VALUE / 2))

            // Phase 3 (completed): emits COMPLETED transition + falcon_check_antigravity_status exposes working_count & primary_state
            currentPhase = 3
            val toolStatus = FalconClientTools.executeLocalFalconTool(
                context = context,
                toolName = "falcon_check_antigravity_status",
                argsElement = JsonObject(),
                chatSessionId = "s-rt",
                onEmotionChanged = {},
                onNavigate = {},
                agApiOverride = fakeApi
            )
            assertEquals("success", toolStatus.get("status").asString)
            assertEquals(0, toolStatus.get("working_count").asInt)
            assertEquals("completed", toolStatus.get("primary_state").asString)
            assertEquals(AgDirectStatus.COMPLETED, capturedTransitions.last().newStatus)

            // Verify ChatViewModel.sanitizeHistoryForApi strips live status bubbles so API context stays 100% lean
            val rawChat = listOf(
                de.meinseelenfunke.app.data.api.ChatMessage("user", "Wie ist der Stand?"),
                de.meinseelenfunke.app.data.api.ChatMessage("assistant", "${de.meinseelenfunke.app.ui.screens.ChatViewModel.AG_LIVE_STATUS_PREFIX} ⚡ Antigravity arbeitet aktiv"),
                de.meinseelenfunke.app.data.api.ChatMessage("assistant", "Ich bin dran, Alina!")
            )
            val cleanedChat = de.meinseelenfunke.app.ui.screens.ChatViewModel.sanitizeHistoryForApi(rawChat)
            assertEquals(2, cleanedChat.size)
            assertFalse(cleanedChat.any { it.content.contains("Antigravity Echtzeit") })
        } finally {
            collectJob.cancel()
        }
    }

    private fun buildPreparedTestPhoto(fileName: String, color: Int): PreparedAgAttachment {
        val bmp = Bitmap.createBitmap(12, 12, Bitmap.Config.ARGB_8888).apply { eraseColor(color) }
        val baos = ByteArrayOutputStream()
        bmp.compress(Bitmap.CompressFormat.JPEG, 85, baos)
        val b64 = Base64.getEncoder().encodeToString(baos.toByteArray())
        return PreparedAgAttachment(
            base64Jpeg = b64,
            mimeType = "image/jpeg",
            fileName = fileName,
            previewBitmap = bmp.asImageBitmap(),
            width = 12,
            height = 12
        )
    }
}
