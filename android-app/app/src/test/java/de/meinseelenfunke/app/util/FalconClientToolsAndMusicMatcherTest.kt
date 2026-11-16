package de.meinseelenfunke.app.util

import android.app.Application
import android.content.Context
import android.graphics.Bitmap
import androidx.compose.ui.graphics.asImageBitmap
import androidx.test.core.app.ApplicationProvider
import com.google.gson.Gson
import com.google.gson.JsonArray
import com.google.gson.JsonObject
import de.meinseelenfunke.app.data.api.AgDirectApi
import de.meinseelenfunke.app.data.api.AgDirectCountsDto
import de.meinseelenfunke.app.data.api.AgDirectCreateRequest
import de.meinseelenfunke.app.data.api.AgDirectCreateResponse
import de.meinseelenfunke.app.data.api.AgDirectDeleteResponse
import de.meinseelenfunke.app.data.api.AgDirectInboxItemDto
import de.meinseelenfunke.app.data.api.AgDirectInboxListResponse
import de.meinseelenfunke.app.di.ServiceLocator
import de.meinseelenfunke.app.ui.screens.isAllowedFalconAssetUrl
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
import java.io.ByteArrayOutputStream
import java.io.File
import java.util.Base64

@RunWith(RobolectricTestRunner::class)
@Config(sdk = [34], application = Application::class)
class FalconClientToolsAndMusicMatcherTest {

    private lateinit var context: Context
    private val gson = Gson()

    @Before
    fun setUp() {
        context = ApplicationProvider.getApplicationContext()
        ServiceLocator.init(context)
        File(context.filesDir, AgDirectInboxManager.OUTBOX_FILE_NAME).delete()
        AgDirectInboxManager.resetMemoryCacheForTests()
        FalconCameraController.clearLastCapturedPhoto()
        NavigationBridge.consumePendingTarget()
        NavigationBridge.pendingOpenQuickEntry = false
    }

    @Test
    fun sportMusicMatcher_selectsSportPlaylistOrTrackAndFallsBackToFavoritesShuffleWhenUnmatched() {
        val tSport1 = MusicTrackDto("t1", "pl_gym", "Power Push", "Workout Crew", 180, 1000L, false, 0)
        val tSport2 = MusicTrackDto("t2", "pl_gym", "Cardio Rush", "Runner", 200, 1000L, true, 1)
        val tCalm = MusicTrackDto("t3", "pl_chill", "Abendruhe", "Piano", 210, 1000L, false, 0)
        val tFav = MusicTrackDto("t4", "pl_chill", "Lieblingslied", "Alina Fav", 195, 1000L, true, 1)

        val playlistsWithGym = listOf(
            MusicPlaylistDto("__sync", "__FUNKENWERK_FAV_SYNC__", "meta", listOf(tFav)),
            MusicPlaylistDto("pl_chill", "Entspannung", "Ruhig", listOf(tCalm, tFav)),
            MusicPlaylistDto("pl_gym", "Mein Workout Mix", "Volle Energie", listOf(tSport1, tSport2))
        )
        val allTracks = listOf(tSport1, tSport2, tCalm, tFav)

        // 1. Direct sport playlist match
        val sportMatch = FalconClientTools.matchMusicForFalcon(
            playlists = playlistsWithGym,
            allTracks = allTracks,
            action = "play_sport",
            query = null
        )
        assertEquals("pl_gym", sportMatch.playlistId)
        assertEquals(2, sportMatch.tracks.size)
        assertTrue(sportMatch.shuffle)
        assertFalse(sportMatch.usedFallbackFavorites)

        // 2. Fallback to Favorites shuffle when no sport playlist or sport track exists
        val nonSportPlaylists = listOf(
            MusicPlaylistDto("pl_chill", "Entspannung", "Ruhig", listOf(tCalm, tFav))
        )
        val fallbackMatch = FalconClientTools.matchMusicForFalcon(
            playlists = nonSportPlaylists,
            allTracks = listOf(tCalm, tFav),
            action = "play_sport",
            query = "fitness"
        )
        assertTrue(fallbackMatch.usedFallbackFavorites)
        assertTrue(fallbackMatch.shuffle)
        assertEquals(listOf("t4"), fallbackMatch.tracks.map { it.id })
    }

    @Test
    fun musicVoiceDucking_lowersVolumeDuringSpeechAndRestoresCleanly() {
        val normalVol = MusicPlayerManager.computeEffectiveVolume(
            alarmDucked = false,
            voiceDucked = false,
            baseVolume = 0.90f
        )
        val duckedVol = MusicPlayerManager.computeEffectiveVolume(
            alarmDucked = false,
            voiceDucked = true,
            baseVolume = 0.90f
        )
        val alarmVol = MusicPlayerManager.computeEffectiveVolume(
            alarmDucked = true,
            voiceDucked = true,
            baseVolume = 0.90f
        )
        assertEquals(0.90f, normalVol, 0.001f)
        assertTrue("Voice ducked volume must be significantly lower than normal", duckedVol in 0.15f..0.25f)
        assertEquals(0.12f, alarmVol, 0.001f)
    }

    @Test
    fun toolSchemaInjection_registersAllSixFalconLocalToolsWithoutDuplicates() {
        val decls = JsonArray().apply {
            add(JsonObject().apply {
                addProperty("name", "master_get_dashboard")
            })
        }

        FalconClientTools.appendFalconToolDeclarations(decls)
        // Calling a second time must be idempotent
        FalconClientTools.appendFalconToolDeclarations(decls)

        val names = (0 until decls.size()).map {
            decls.get(it).asJsonObject.get("name").asString
        }
        assertEquals(7, names.size)
        for (expected in FalconClientTools.LOCAL_TOOL_NAMES) {
            assertTrue("Expected local Falcon tool $expected to be injected", expected in names)
        }
    }

    @Test
    fun sendToAntigravity_attachesFreshlyCapturedCameraPhotoEndToEndAndMarksAttached() = runBlocking {
        val capturedRequests = mutableListOf<AgDirectCreateRequest>()
        val fakeAgApi = object : AgDirectApi {
            override suspend fun listInbox(limit: Int): AgDirectInboxListResponse {
                return AgDirectInboxListResponse(
                    success = true,
                    counts = AgDirectCountsDto(pending = capturedRequests.size, delivered = 0, completed = 0),
                    items = emptyList()
                )
            }

            override suspend fun createItem(request: AgDirectCreateRequest): AgDirectCreateResponse {
                capturedRequests.add(request)
                return AgDirectCreateResponse(
                    success = true,
                    data = AgDirectInboxItemDto(
                        id = "ag_20261116_130000_falcon1",
                        client_uuid = request.client_uuid,
                        title = request.title,
                        body = request.body,
                        checklist = request.checklist,
                        execution_mode = request.execution_mode,
                        status = "pending",
                        has_attachment = !request.attachment_base64.isNullOrBlank(),
                        attachment_name = request.attachment_name,
                        attachment_mime = request.attachment_mime
                    )
                )
            }

            override suspend fun deleteItem(id: String): AgDirectDeleteResponse {
                return AgDirectDeleteResponse(success = true)
            }

            override suspend fun getAttachment(id: String): ResponseBody {
                return ByteArray(0).toResponseBody("image/jpeg".toMediaType())
            }
        }

        val bmp = Bitmap.createBitmap(64, 64, Bitmap.Config.ARGB_8888)
        val jpegBytes = ByteArrayOutputStream().use { out ->
            bmp.compress(Bitmap.CompressFormat.JPEG, 84, out)
            out.toByteArray()
        }
        val b64 = Base64.getEncoder().encodeToString(jpegBytes)
        FalconCameraController.setLastCapturedPhotoForTest(
            FalconCapturedPhoto(
                base64Jpeg = b64,
                fileName = "falcon_test_shot.jpg",
                previewBitmap = bmp.asImageBitmap(),
                width = 64,
                height = 64,
                sessionId = "live-sess-99",
                attachedToInbox = false
            )
        )

        val args = JsonObject().apply {
            addProperty("title", "Laser-Halterung anpassen")
            addProperty("body", "Siehe Foto direkt aus der Werkstatt")
            add("checklist", JsonArray().apply { add("Maße prüfen") })
            addProperty("execution_mode", "direct")
        }

        val res1 = FalconClientTools.executeLocalFalconTool(
            context = context,
            toolName = "falcon_send_to_antigravity",
            argsElement = args,
            chatSessionId = "live-sess-99",
            onEmotionChanged = {},
            onNavigate = {},
            agApiOverride = fakeAgApi
        )

        assertEquals("success", res1.get("status").asString)
        assertTrue(res1.get("photo_attached").asBoolean)
        assertEquals(1, capturedRequests.size)
        assertEquals("Laser-Halterung anpassen", capturedRequests[0].title)
        assertEquals("direct", capturedRequests[0].execution_mode)
        assertEquals("falcon_test_shot.jpg", capturedRequests[0].attachment_name)
        assertEquals(b64, capturedRequests[0].attachment_base64)
        assertTrue(FalconCameraController.lastCapturedPhoto.value?.attachedToInbox == true)

        // Second message without explicit attach_captured_photo=true must NOT re-attach the already-sent photo
        val args2 = JsonObject().apply {
            addProperty("title", "Zweiter Gedanke ohne Foto")
        }
        val res2 = FalconClientTools.executeLocalFalconTool(
            context = context,
            toolName = "falcon_send_to_antigravity",
            argsElement = args2,
            chatSessionId = "live-sess-99",
            onEmotionChanged = {},
            onNavigate = {},
            agApiOverride = fakeAgApi
        )
        assertFalse(res2.get("photo_attached").asBoolean)
        assertEquals(null, capturedRequests[1].attachment_base64)
    }

    @Test
    fun falcon3dStateAndRealtimeVideoPacket_serializeDeterministicallyAndEnforceZeroTrustAssetLock() {
        val stateJson = FalconClientTools.buildFalconSceneStateJson(
            mode = "speaking",
            audioLevel = 1.45f,
            emotion = "PROUD",
            isDark = false,
            cameraActive = true
        )
        val parsed = gson.fromJson(stateJson, JsonObject::class.java)
        assertEquals("speaking", parsed.get("mode").asString)
        assertEquals(1.0f, parsed.get("audioLevel").asFloat, 0.001f)
        assertEquals("proud", parsed.get("emotion").asString)
        assertFalse(parsed.get("isDark").asBoolean)
        assertTrue(parsed.get("cameraActive").asBoolean)

        val videoPacket = FalconClientTools.buildRealtimeVideoFramePacket("BASE64_JPEG_TEST")
        val videoInner = videoPacket.getAsJsonObject("realtimeInput").getAsJsonObject("video")
        assertEquals("image/jpeg", videoInner.get("mimeType").asString)
        assertEquals("BASE64_JPEG_TEST", videoInner.get("data").asString)

        // Zero-Trust WebView asset lock
        assertTrue(isAllowedFalconAssetUrl("file:///android_asset/falcon3d/index.html"))
        assertTrue(isAllowedFalconAssetUrl("file:///android_asset/falcon3d/three.min.js"))
        assertTrue(isAllowedFalconAssetUrl("file:///android_asset/falcon3d/falcon3d.js"))
        assertFalse(isAllowedFalconAssetUrl("https://cdn.jsdelivr.net/npm/three/build/three.min.js"))
        assertFalse(isAllowedFalconAssetUrl("http://evil.example.com/x.js"))
        assertFalse(isAllowedFalconAssetUrl("file:///sdcard/Download/secret.txt"))
        assertFalse(isAllowedFalconAssetUrl("file:///android_asset/falcon3d/../../etc/hosts"))

        // Verify local 3D assets exist on disk with zero external CDN URLs
        val indexAssetCandidates = listOf(
            File("src/main/assets/falcon3d/index.html"),
            File("app/src/main/assets/falcon3d/index.html")
        )
        val indexFile = indexAssetCandidates.firstOrNull { it.exists() }
        assertNotNull("Expected assets/falcon3d/index.html to exist", indexFile)
        val html = indexFile!!.readText(Charsets.UTF_8)
        assertFalse("index.html must not contain external http/https CDN links", html.contains("http://") || html.contains("https://"))
    }

    @Test
    fun appNavigateAndEmotionTools_routeAllDestinationsIncludingReceiptScanner() = runBlocking {
        var capturedEmotion = "idle"
        FalconClientTools.executeLocalFalconTool(
            context = context,
            toolName = "falcon_set_emotion",
            argsElement = JsonObject().apply { addProperty("emotion", "thinking") },
            chatSessionId = "s1",
            onEmotionChanged = { capturedEmotion = it },
            onNavigate = {}
        )
        assertEquals("thinking", capturedEmotion)

        var lastNav: FalconResolvedDestination? = null
        FalconClientTools.executeLocalFalconTool(
            context = context,
            toolName = "falcon_app_navigate",
            argsElement = JsonObject().apply { addProperty("destination", "receipt_scanner") },
            chatSessionId = "s1",
            onEmotionChanged = {},
            onNavigate = { lastNav = it }
        )
        assertNotNull(lastNav)
        assertEquals(NavigationBridge.NavTarget.ZENTRUM, lastNav?.target)
        assertTrue(lastNav!!.openReceiptScanner)
        assertTrue(NavigationBridge.pendingOpenQuickEntry)

        val musicDest = FalconClientTools.resolveFalconAppDestination("music")
        assertEquals(NavigationBridge.NavTarget.ORGANIZER, musicDest.target)
        assertEquals(5, musicDest.organizerSubTab)

        val phoneDest = FalconClientTools.resolveFalconAppDestination("phone")
        assertEquals(NavigationBridge.NavTarget.ORGANIZER, phoneDest.target)
        assertEquals(7, phoneDest.organizerSubTab)
    }
}
