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
import de.meinseelenfunke.app.data.api.AgDirectResolveRequest
import de.meinseelenfunke.app.data.api.AgDirectResolveResponse
import de.meinseelenfunke.app.di.ServiceLocator
import de.meinseelenfunke.app.ui.screens.isAllowedFalconAssetUrl
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
    fun toolSchemaInjection_registersAllSevenFalconLocalToolsWithoutDuplicates() {
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
        assertEquals(FalconClientTools.DECLARED_WS_LOCAL_TOOL_NAMES.size + 1, names.size)
        for (expected in FalconClientTools.DECLARED_WS_LOCAL_TOOL_NAMES) {
            assertTrue("Expected local Falcone tool $expected to be injected", expected in names)
        }
        assertFalse("falcon_set_emotion must not be injected as WS roundtrip tool", "falcon_set_emotion" in names)
        val agDecl = (0 until decls.size())
            .map { decls.get(it).asJsonObject }
            .first { it.get("name").asString == "falcon_send_to_antigravity" }
        val agProps = agDecl.getAsJsonObject("parameters").getAsJsonObject("properties")
        assertTrue("Expected channel schema property on falcon_send_to_antigravity", agProps.has("channel"))
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
                        channel = request.channel,
                        status = "pending",
                        has_attachment = !request.attachment_base64.isNullOrBlank(),
                        attachment_name = request.attachment_name,
                        attachment_mime = request.attachment_mime
                    )
                )
            }

            override suspend fun resolveBlockade(id: String, request: AgDirectResolveRequest): AgDirectResolveResponse {
                return AgDirectResolveResponse(success = true, decision = request.decision)
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
            addProperty("channel", "minipc")
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
        assertEquals("minipc", res1.get("channel").asString)
        assertEquals("🖥️ Mini-PC", res1.get("channel_label").asString)
        assertEquals(1, capturedRequests.size)
        assertEquals("Laser-Halterung anpassen", capturedRequests[0].title)
        assertEquals("direct", capturedRequests[0].execution_mode)
        assertEquals("minipc", capturedRequests[0].channel)
        assertEquals("falcon_test_shot.jpg", capturedRequests[0].attachment_name)
        assertEquals(b64, capturedRequests[0].attachment_base64)
        assertTrue(FalconCameraController.lastCapturedPhoto.value?.attachedToInbox == true)

        // Second message without explicit attach_captured_photo=true or channel defaults to "leitung" and no photo
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
        assertEquals("leitung", res2.get("channel").asString)
        assertEquals("leitung", capturedRequests[1].channel)
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

    @Test
    fun smartHomeFuzzyMatcherAndTool_resolvesGermanPlugsAndSwitchesViaExecutor() = runBlocking {
        val savedPlugs = FunkenwerkAlarmManager.defaultSmartPlugs()
        // Canonical saved plugs: "Lichterkette Fußleiste", "Packtisch Warm Licht", "Lichterkette Spiegel"

        // 1. Reversed word order + plural + umlaut/ß variants -> exact single plug
        val fuss1 = FalconClientTools.matchSmartHomeDevices(savedPlugs, "Fußleisten Lichterkette")
        assertEquals(listOf("Lichterkette Fußleiste"), fuss1.map { it.name })

        val fuss2 = FalconClientTools.matchSmartHomeDevices(savedPlugs, "Mach bitte die fussleiste an")
        assertEquals(listOf("Lichterkette Fußleiste"), fuss2.map { it.name })

        val spiegel = FalconClientTools.matchSmartHomeDevices(savedPlugs, "Spiegel Lichterkette")
        assertEquals(listOf("Lichterkette Spiegel"), spiegel.map { it.name })

        val spiegelShort = FalconClientTools.matchSmartHomeDevices(savedPlugs, "Spiegel")
        assertEquals(listOf("Lichterkette Spiegel"), spiegelShort.map { it.name })

        val packtisch = FalconClientTools.matchSmartHomeDevices(savedPlugs, "Packtisch")
        assertEquals(listOf("Packtisch Warm Licht"), packtisch.map { it.name })

        // 2. Generic category query "Lichterkette" -> matches both string lights
        val bothLights = FalconClientTools.matchSmartHomeDevices(savedPlugs, "Lichterkette")
        assertEquals(
            listOf("Lichterkette Fußleiste", "Lichterkette Spiegel"),
            bothLights.map { it.name }
        )

        // 3. End-to-end tool execution with custom executor
        val calls = mutableListOf<Pair<String, List<String>>>()
        val resOn = FalconClientTools.executeLocalFalconTool(
            context = context,
            toolName = "falcon_smarthome_control",
            argsElement = JsonObject().apply {
                addProperty("action", "turn_on")
                addProperty("device_name", "Fußleisten Lichterkette")
            },
            chatSessionId = "s_home",
            onEmotionChanged = {},
            onNavigate = {},
            smartHomeExecutorOverride = { act, ips ->
                calls.add(act to ips)
                true to "Lichterkette Fußleiste: EIN geschaltet"
            }
        )
        assertEquals("success", resOn.get("status").asString)
        assertEquals("on", resOn.get("new_state").asString)
        assertEquals(1, calls.size)
        assertEquals("turn_on", calls[0].first)
        assertEquals(listOf("192.168.188.109"), calls[0].second)

        // 4. Unknown device returns clear not_found + available_devices list
        val resUnknown = FalconClientTools.executeLocalFalconTool(
            context = context,
            toolName = "falcon_smarthome_control",
            argsElement = JsonObject().apply {
                addProperty("action", "turn_on")
                addProperty("device_name", "Gartenbrunnen")
            },
            chatSessionId = "s_home",
            onEmotionChanged = {},
            onNavigate = {},
            smartHomeExecutorOverride = { _, _ -> true to "ok" }
        )
        assertEquals("not_found", resUnknown.get("status").asString)
        assertEquals(3, resUnknown.getAsJsonArray("available_devices").size())
    }

    @Test
    fun heyFalconeWakeWordMatcher_acceptsBothFalconeAndFalconSpellingsAndDefaultMigrationIsActive() {
        val validPhrases = listOf(
            "Hey Falcone",
            "hey falcone!",
            "Hi Falcone",
            "Hallo Falcone",
            "OK Falcone",
            "Okay Falcone",
            "Hey Falcon",
            "Hey Falkon, hörst du mich?",
            "Hi Falcon",
            "Hallo Falkon",
            "OK Falcon",
            "Okay Falkone",
            "Hey Falco",
            "Hey Falken",
            "Hey Fall Cone"
        )
        for (phrase in validPhrases) {
            assertTrue(
                "Expected wake phrase '$phrase' to match",
                de.meinseelenfunke.app.services.WakeWordService.matchesHeyFalconePhrase(phrase)
            )
        }

        val invalidPhrases = listOf(
            "",
            "   ",
            "Guten Morgen Alina",
            "Wie wird das Wetter heute in Gifhorn?",
            "Ich trinke noch kurz meinen Kaffee"
        )
        for (phrase in invalidPhrases) {
            assertFalse(
                "Expected unrelated phrase '$phrase' NOT to match",
                de.meinseelenfunke.app.services.WakeWordService.matchesHeyFalconePhrase(phrase)
            )
        }

        // Verify v1.8.2 migration turns Wake Word ON by default even on upgraded legacy installs
        val prefs = context.getSharedPreferences("seelenfunke_prefs", Context.MODE_PRIVATE)
        prefs.edit()
            .putBoolean("wake_word_enabled", false)
            .remove("hey_falcon_default_migrated_v182")
            .commit()
        ServiceLocator.init(context)
        assertTrue("Wake Word must be migrated to ON by default in v1.8.2", ServiceLocator.isWakeWordEnabled())

        // User can still explicitly turn it off afterwards and have that choice respected
        ServiceLocator.setWakeWordEnabled(false)
        ServiceLocator.init(context)
        assertFalse(ServiceLocator.isWakeWordEnabled())
    }

    @Test
    fun inactivityTracker_twoStageCheckResetOnSpeechAndHardAutoDisconnect() {
        val tracker = FalconInactivityTracker()
        tracker.onSessionStarted(1_000L)

        // Before 60s: no action
        assertEquals(FalconInactivityAction.NONE, tracker.evaluate(60_999L, isAiSpeaking = false))
        assertFalse(tracker.inactivityCheckSent)

        // Exactly at 60s of silence: Stage 1 check prompt sent
        assertEquals(FalconInactivityAction.SEND_STAGE1_CHECK, tracker.evaluate(61_000L, isAiSpeaking = false))
        assertTrue(tracker.inactivityCheckSent)

        // Falcone speaks his Stage 1 follow-up question (must NOT reset inactivityCheckSent!)
        tracker.onAiActivity(61_400L)
        assertEquals(FalconInactivityAction.NONE, tracker.evaluate(62_000L, isAiSpeaking = true))
        tracker.onAiSpeechFinished(63_500L)
        assertTrue(tracker.inactivityCheckSent)
        assertEquals(FalconInactivityStage.CHECK_SENT_WAITING_USER_REPLY, tracker.stage)

        // User replies 10s later -> resets back to ACTIVE and fresh 60s timer!
        tracker.onUserSpeech(73_500L)
        assertFalse(tracker.inactivityCheckSent)
        assertEquals(FalconInactivityStage.ACTIVE, tracker.stage)
        assertEquals(FalconInactivityAction.NONE, tracker.evaluate(130_000L, isAiSpeaking = false))

        // Another full 60s of silence from 73_500 -> triggers Stage 1 again at 133_500
        assertEquals(FalconInactivityAction.SEND_STAGE1_CHECK, tracker.evaluate(133_500L, isAiSpeaking = false))
        tracker.onAiActivity(134_000L)
        tracker.onAiSpeechFinished(136_000L)

        // 24.9s silence after question finished -> still waiting
        assertEquals(FalconInactivityAction.NONE, tracker.evaluate(160_900L, isAiSpeaking = false))

        // 25.0s silence after question finished -> Stage 2 farewell triggered!
        assertEquals(FalconInactivityAction.SEND_STAGE2_FAREWELL, tracker.evaluate(161_000L, isAiSpeaking = false))
        assertEquals(FalconInactivityStage.FAREWELL_CLOSING, tracker.stage)

        // Falcone/Funkira speaks farewell from 161_400 to 163_400, then +1.4s post-speech buffer -> FORCE_DISCONNECT at 164_800
        tracker.onAiActivity(161_400L)
        tracker.onAiSpeechFinished(163_400L)
        assertEquals(FalconInactivityAction.NONE, tracker.evaluate(164_700L, isAiSpeaking = false))
        assertEquals(FalconInactivityAction.FORCE_DISCONNECT, tracker.evaluate(164_800L, isAiSpeaking = false))

        // CRITICAL REGRESSION GUARD: If Funkira speaks a longer farewell sentence (> 7s, e.g. 11s),
        // evaluate(..., isAiSpeaking = true) MUST NEVER interrupt mid-sentence!
        val longSpeechTracker = FalconInactivityTracker()
        longSpeechTracker.onSessionStarted(0L)
        assertEquals(FalconInactivityAction.SEND_STAGE1_CHECK, longSpeechTracker.evaluate(60_000L, isAiSpeaking = false))
        longSpeechTracker.onAiActivity(60_500L)
        // Even if Stage 1 question takes 9.5s (> old 6s wait), Stage 2 timer must not start until speech finishes
        assertEquals(FalconInactivityAction.NONE, longSpeechTracker.evaluate(69_500L, isAiSpeaking = true))
        longSpeechTracker.onAiSpeechFinished(70_000L)
        assertEquals(FalconInactivityAction.NONE, longSpeechTracker.evaluate(94_900L, isAiSpeaking = false))
        assertEquals(FalconInactivityAction.SEND_STAGE2_FAREWELL, longSpeechTracker.evaluate(95_000L, isAiSpeaking = false))
        longSpeechTracker.onAiActivity(95_400L)
        assertEquals(FalconInactivityAction.NONE, longSpeechTracker.evaluate(103_000L, isAiSpeaking = true)) // 8s into farewell -> still speaking!
        assertEquals(FalconInactivityAction.NONE, longSpeechTracker.evaluate(106_000L, isAiSpeaking = true)) // 11s into farewell -> still speaking!
        longSpeechTracker.onAiSpeechFinished(106_500L)
        assertEquals(FalconInactivityAction.NONE, longSpeechTracker.evaluate(107_800L, isAiSpeaking = false))
        assertEquals(FalconInactivityAction.FORCE_DISCONNECT, longSpeechTracker.evaluate(107_900L, isAiSpeaking = false))

        // Also verify 12s no-speech fallback ceiling if model never emits audio or end_call at all
        val ceilingTracker = FalconInactivityTracker()
        ceilingTracker.onSessionStarted(0L)
        assertEquals(FalconInactivityAction.SEND_STAGE1_CHECK, ceilingTracker.evaluate(60_000L, isAiSpeaking = false))
        ceilingTracker.onAiSpeechFinished(62_000L)
        assertEquals(FalconInactivityAction.SEND_STAGE2_FAREWELL, ceilingTracker.evaluate(87_000L, isAiSpeaking = false))
        assertEquals(FalconInactivityAction.NONE, ceilingTracker.evaluate(98_900L, isAiSpeaking = false))
        assertEquals(FalconInactivityAction.FORCE_DISCONNECT, ceilingTracker.evaluate(99_000L, isAiSpeaking = false))

        // Verify Gemini 3.1 realtimeInput.text schema & zero AI en-dashes
        val p1 = FalconClientTools.buildRealtimeTextPromptPacket(FalconClientTools.STAGE1_INACTIVITY_PROMPT)
        val p2 = FalconClientTools.buildRealtimeTextPromptPacket(FalconClientTools.STAGE2_AUTO_END_PROMPT)
        assertTrue(p1.has("realtimeInput"))
        assertTrue(p2.getAsJsonObject("realtimeInput").get("text").asString.contains("Hey Funkira"))
        assertFalse(FalconClientTools.STAGE1_INACTIVITY_PROMPT.contains(" \u2013 "))
        assertFalse(FalconClientTools.STAGE2_AUTO_END_PROMPT.contains(" \u2013 "))

        // Verify FunkiraLiveViewModel parses both primitive string errors and object errors without ClassCastException
        val primErr = de.meinseelenfunke.app.ui.screens.FunkiraLiveViewModel.parseServerErrorElement(
            gson.fromJson("\"Google Gemini connection error\"", com.google.gson.JsonElement::class.java)
        )
        assertEquals(1011, primErr.first)
        assertEquals("Google Gemini connection error", primErr.second)

        val objErr = de.meinseelenfunke.app.ui.screens.FunkiraLiveViewModel.parseServerErrorElement(
            gson.fromJson("""{"code":1008,"message":"Token expired"}""", com.google.gson.JsonElement::class.java)
        )
        assertEquals(1008, objErr.first)
        assertEquals("Token expired", objErr.second)

        // Verify active user speech or long AI speech > 60s is NEVER interrupted by timeout, and user speech cancels FAREWELL_CLOSING
        val midSentenceTracker = FalconInactivityTracker()
        midSentenceTracker.onSessionStarted(0L)
        // User speaks continuously from t=10s..75s (well past 60s) -> must NEVER trigger Stage-1 check!
        assertEquals(FalconInactivityAction.NONE, midSentenceTracker.evaluate(60_500L, isAiSpeaking = false, isUserSpeaking = true))
        assertEquals(FalconInactivityAction.NONE, midSentenceTracker.evaluate(75_000L, isAiSpeaking = false, isUserSpeaking = true))
        assertFalse(midSentenceTracker.inactivityCheckSent)
        // Only 60s AFTER the user finishes speaking (75s + 60s = 135s) may Stage-1 check fire
        assertEquals(FalconInactivityAction.NONE, midSentenceTracker.evaluate(134_900L, isAiSpeaking = false, isUserSpeaking = false))
        assertEquals(FalconInactivityAction.SEND_STAGE1_CHECK, midSentenceTracker.evaluate(135_000L, isAiSpeaking = false, isUserSpeaking = false))
        midSentenceTracker.onAiSpeechFinished(137_000L)
        assertEquals(FalconInactivityAction.SEND_STAGE2_FAREWELL, midSentenceTracker.evaluate(162_000L, isAiSpeaking = false, isUserSpeaking = false))
        assertEquals(FalconInactivityStage.FAREWELL_CLOSING, midSentenceTracker.stage)
        // User speaks right as farewell starts -> cancels disconnect and returns to ACTIVE!
        assertEquals(FalconInactivityAction.NONE, midSentenceTracker.evaluate(163_000L, isAiSpeaking = false, isUserSpeaking = true))
        assertEquals(FalconInactivityStage.ACTIVE, midSentenceTracker.stage)
    }

    @Test
    fun midCallAutoReconnectPolicyAnd3dArmClamp_enforceSeamlessRecoveryAndSafeGentlemanPosture() {
        // 1. Mid-call auto-reconnect decision matrix (attempts 0..3 allowed, 4 blocked)
        assertEquals(
            listOf(350L, 800L, 1500L, 2500L),
            de.meinseelenfunke.app.ui.screens.FunkiraLiveViewModel.RECONNECT_BACKOFF_MS.toList()
        )
        for (attempt in 0..3) {
            assertTrue(
                de.meinseelenfunke.app.ui.screens.FunkiraLiveViewModel.shouldAutoReconnectMidCall(
                    wasSetupComplete = true,
                    userInitiatedHangup = false,
                    shouldEndCall = false,
                    reconnectAttemptCount = attempt
                )
            )
        }
        assertFalse(
            de.meinseelenfunke.app.ui.screens.FunkiraLiveViewModel.shouldAutoReconnectMidCall(
                wasSetupComplete = true,
                userInitiatedHangup = false,
                shouldEndCall = false,
                reconnectAttemptCount = 4
            )
        )
        assertFalse(
            de.meinseelenfunke.app.ui.screens.FunkiraLiveViewModel.shouldAutoReconnectMidCall(
                wasSetupComplete = true,
                userInitiatedHangup = true,
                shouldEndCall = false,
                reconnectAttemptCount = 0
            )
        )
        assertFalse(
            de.meinseelenfunke.app.ui.screens.FunkiraLiveViewModel.shouldAutoReconnectMidCall(
                wasSetupComplete = true,
                userInitiatedHangup = false,
                shouldEndCall = true,
                reconnectAttemptCount = 0
            )
        )
        assertFalse(
            de.meinseelenfunke.app.ui.screens.FunkiraLiveViewModel.shouldAutoReconnectMidCall(
                wasSetupComplete = false,
                userInitiatedHangup = false,
                shouldEndCall = false,
                reconnectAttemptCount = 0
            )
        )

        // 2. Strict Gemini 3.1 Live schema hygiene across all local Falcone tools
        val decls = JsonArray()
        FalconClientTools.appendFalconToolDeclarations(decls)
        assertEquals(FalconClientTools.DECLARED_WS_LOCAL_TOOL_NAMES.size, decls.size())
        for (i in 0 until decls.size()) {
            val fn = decls.get(i).asJsonObject
            val params = fn.getAsJsonObject("parameters")
            assertEquals("OBJECT", params.get("type").asString)
            assertTrue(params.has("properties") && params.get("properties").isJsonObject)
            assertFalse(params.has("additionalProperties"))
        }

        // 3. Hard mathematical arm pose solver & smoothing bounds in falcon3d.js
        val jsCandidates = listOf(
            File("src/main/assets/falcon3d/falcon3d.js"),
            File("app/src/main/assets/falcon3d/falcon3d.js")
        )
        val jsFile = jsCandidates.firstOrNull { it.exists() }
        assertNotNull("Expected assets/falcon3d/falcon3d.js to exist", jsFile)
        val jsContent = jsFile!!.readText(Charsets.UTF_8)
        assertTrue(jsContent.contains("function solveArmTargets("))
        assertTrue(jsContent.contains("function applyArmSmoothing("))
        assertTrue(jsContent.contains("userPitch = clamp("))
        assertFalse(jsContent.contains("-1.04"))
        assertFalse(jsContent.contains("-0.92"))
    }

    @Test
    fun trustedFastUnlock_allowsSignedVoiceOrWidgetOnUnlockedDeviceAndRejectsLockedOrSpoofedIntents() {
        var fakeClockMs = 50_000L
        val prevClock = NavigationBridge.elapsedRealtimeProvider
        NavigationBridge.elapsedRealtimeProvider = { fakeClockMs }
        NavigationBridge.clearTrustedFastUnlock()

        try {
            // 1. NavigationBridge 15s fast-unlock window & logout clear
            assertFalse(NavigationBridge.hasRecentTrustedFastUnlock())
            NavigationBridge.grantTrustedFastUnlock()
            assertTrue(NavigationBridge.hasRecentTrustedFastUnlock())
            fakeClockMs = 64_999L
            assertTrue(NavigationBridge.hasRecentTrustedFastUnlock())
            fakeClockMs = 65_001L
            assertFalse("Fast-unlock grant must expire after 15s window", NavigationBridge.hasRecentTrustedFastUnlock())

            fakeClockMs = 80_000L
            NavigationBridge.grantTrustedFastUnlock()
            assertTrue(NavigationBridge.hasRecentTrustedFastUnlock())
            NavigationBridge.triggerLogout()
            assertFalse("Logout must immediately revoke fast-unlock", NavigationBridge.hasRecentTrustedFastUnlock())

            // 2. SecurityIntentHelper 4-factor verification
            ServiceLocator.saveAuthToken("sanctum_valid_test_token_184")

            val signedVoiceIntent = android.content.Intent(context, de.meinseelenfunke.app.MainActivity::class.java).apply {
                putExtra("start_voice_call", true)
                putExtra(SecurityIntentHelper.EXTRA_FROM_WIDGET, true)
                SecurityIntentHelper.signIntent(context, this)
            }
            assertTrue(
                SecurityIntentHelper.canGrantTrustedFastUnlock(
                    context = context,
                    intent = signedVoiceIntent,
                    isDeviceLockedOverride = false
                )
            )

            // Locked phone MUST block fast-unlock even with valid signature!
            assertFalse(
                SecurityIntentHelper.canGrantTrustedFastUnlock(
                    context = context,
                    intent = signedVoiceIntent,
                    isDeviceLockedOverride = true
                )
            )

            // Unsigned or forged third-party intent MUST be rejected!
            val spoofedWidgetIntent = android.content.Intent(context, de.meinseelenfunke.app.MainActivity::class.java).apply {
                putExtra("start_voice_call", true)
                putExtra(SecurityIntentHelper.EXTRA_FROM_WIDGET, true)
                putExtra(SecurityIntentHelper.EXTRA_SECURITY_TOKEN, "forged_3rd_party_token")
            }
            assertFalse(
                SecurityIntentHelper.canGrantTrustedFastUnlock(
                    context = context,
                    intent = spoofedWidgetIntent,
                    isDeviceLockedOverride = false
                )
            )

            // Signed plain intent without voice/widget/tab action MUST NOT bypass normal app icon biometric lock
            val plainSignedIntent = android.content.Intent(context, de.meinseelenfunke.app.MainActivity::class.java).apply {
                SecurityIntentHelper.signIntent(context, this)
            }
            assertFalse(
                SecurityIntentHelper.canGrantTrustedFastUnlock(
                    context = context,
                    intent = plainSignedIntent,
                    isDeviceLockedOverride = false
                )
            )

            // Logged-out session MUST reject fast-unlock
            ServiceLocator.clearAuthToken()
            assertFalse(
                SecurityIntentHelper.canGrantTrustedFastUnlock(
                    context = context,
                    intent = signedVoiceIntent,
                    isDeviceLockedOverride = false
                )
            )
        } finally {
            NavigationBridge.clearTrustedFastUnlock()
            NavigationBridge.elapsedRealtimeProvider = prevClock
        }
    }

    @Test
    fun importPhotoFromUri_decodesResizesAndSetsLastCapturedPhotoCleanly() = runBlocking {
        val testBitmap = Bitmap.createBitmap(400, 300, Bitmap.Config.ARGB_8888)
        val file = File(context.cacheDir, "test_gallery_screenshot.png")
        file.outputStream().use { out ->
            testBitmap.compress(Bitmap.CompressFormat.PNG, 100, out)
        }
        val uri = android.net.Uri.fromFile(file)

        val imported = FalconCameraController.importPhotoFromUri(context, uri)
        assertNotNull(imported)
        assertEquals("test_gallery_screenshot.jpg", imported!!.fileName)
        assertEquals("image/jpeg", imported.mimeType)
        assertFalse(imported.attachedToInbox)
        assertNotNull(imported.previewBitmap)
        assertEquals(imported, FalconCameraController.lastCapturedPhoto.value)

        val prepared = imported.toPreparedAgAttachment()
        assertEquals(imported.fileName, prepared.fileName)
        assertEquals(imported.base64Jpeg, prepared.base64Jpeg)
    }

    @Test
    fun photoAttachmentLifecycle_clearAndMarkAttachedFilterDockThumbnailCleanly() {
        val testBitmap = Bitmap.createBitmap(64, 64, Bitmap.Config.ARGB_8888)
        val photo = FalconCapturedPhoto(
            base64Jpeg = "dummy_base64",
            fileName = "dock_test.jpg",
            previewBitmap = testBitmap.asImageBitmap(),
            width = 64,
            height = 64,
            attachedToInbox = false
        )

        // 1. Initially set photo is present and visible
        FalconCameraController.setLastCapturedPhotoForTest(photo)
        assertEquals(photo, FalconCameraController.lastCapturedPhoto.value)
        assertNotNull(FalconCameraController.lastCapturedPhoto.value?.takeIf { !it.attachedToInbox })

        // 2. markLastPhotoAttached sets attachedToInbox = true and filters out of dock thumbnail
        FalconCameraController.markLastPhotoAttached()
        assertTrue(FalconCameraController.lastCapturedPhoto.value?.attachedToInbox == true)
        assertEquals(null, FalconCameraController.lastCapturedPhoto.value?.takeIf { !it.attachedToInbox })

        // 3. clearLastCapturedPhoto completely nulls out the state
        FalconCameraController.clearLastCapturedPhoto()
        assertEquals(null, FalconCameraController.lastCapturedPhoto.value)
        assertEquals(null, FalconCameraController.lastCapturedPhoto.value?.takeIf { !it.attachedToInbox })
    }

    @Test
    fun sendToAntigravity_respectsVoiceDirectExecutionSetting() = runBlocking {
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
                        id = "ag_test_${System.currentTimeMillis()}",
                        client_uuid = request.client_uuid,
                        title = request.title,
                        body = request.body,
                        checklist = request.checklist,
                        execution_mode = request.execution_mode,
                        channel = request.channel,
                        status = "pending",
                        has_attachment = false,
                        attachment_name = null,
                        attachment_mime = null
                    )
                )
            }

            override suspend fun resolveBlockade(id: String, request: AgDirectResolveRequest): AgDirectResolveResponse {
                return AgDirectResolveResponse(success = true, decision = request.decision)
            }

            override suspend fun deleteItem(id: String): AgDirectDeleteResponse {
                return AgDirectDeleteResponse(success = true)
            }

            override suspend fun getAttachment(id: String): ResponseBody {
                return ByteArray(0).toResponseBody("image/jpeg".toMediaType())
            }
        }

        // Case 1: Voice direct is enabled (default) -> Forces "direct" mode even if argument says "concept"
        AgDirectInboxManager.setVoiceDirectExecutionEnabled(context, true)
        val argsConcept = JsonObject().apply {
            addProperty("title", "Sprachauftrag an Antigravity")
            addProperty("body", "Bitte direkt ausführen")
            addProperty("execution_mode", "concept")
            addProperty("channel", "leitung")
        }
        val res1 = FalconClientTools.executeLocalFalconTool(
            context = context,
            toolName = "falcon_send_to_antigravity",
            argsElement = argsConcept,
            chatSessionId = "sess-voice-1",
            onEmotionChanged = {},
            onNavigate = {},
            agApiOverride = fakeAgApi
        )
        assertEquals("success", res1.get("status").asString)
        assertEquals("direct", res1.get("execution_mode").asString)
        assertEquals("direct", capturedRequests.last().execution_mode)

        // Case 2: Voice direct is disabled -> Passes through "concept" as requested
        AgDirectInboxManager.setVoiceDirectExecutionEnabled(context, false)
        val res2 = FalconClientTools.executeLocalFalconTool(
            context = context,
            toolName = "falcon_send_to_antigravity",
            argsElement = argsConcept,
            chatSessionId = "sess-voice-2",
            onEmotionChanged = {},
            onNavigate = {},
            agApiOverride = fakeAgApi
        )
        assertEquals("success", res2.get("status").asString)
        assertEquals("concept", res2.get("execution_mode").asString)
        assertEquals("concept", capturedRequests.last().execution_mode)

        // Case 3: Voice direct disabled, but argument explicitly requests "direct" -> Sets "direct"
        val argsDirect = JsonObject().apply {
            addProperty("title", "Expliziter Direktauftrag")
            addProperty("execution_mode", "direct")
        }
        val res3 = FalconClientTools.executeLocalFalconTool(
            context = context,
            toolName = "falcon_send_to_antigravity",
            argsElement = argsDirect,
            chatSessionId = "sess-voice-3",
            onEmotionChanged = {},
            onNavigate = {},
            agApiOverride = fakeAgApi
        )
        assertEquals("success", res3.get("status").asString)
        assertEquals("direct", res3.get("execution_mode").asString)
        assertEquals("direct", capturedRequests.last().execution_mode)
    }

    @Test
    fun `music control triggers automatic Ruhemodus standby and blocks mid-call reconnect`() {
        assertTrue(FalconClientTools.shouldEnterStandbyAfterTool("falcon_music_control"))
        assertTrue(FalconClientTools.shouldEnterStandbyAfterTool("  FALCON_MUSIC_CONTROL  "))
        assertTrue(FalconClientTools.shouldEnterStandbyAfterTool("music_play"))
        assertTrue(FalconClientTools.shouldEnterStandbyAfterTool("management_music_pause"))

        assertFalse(FalconClientTools.shouldEnterStandbyAfterTool("falcon_smarthome_control"))
        assertFalse(FalconClientTools.shouldEnterStandbyAfterTool("falcon_camera_control"))
        assertFalse(FalconClientTools.shouldEnterStandbyAfterTool("falcon_send_to_antigravity"))
        assertFalse(FalconClientTools.shouldEnterStandbyAfterTool(""))
        assertFalse(FalconClientTools.shouldEnterStandbyAfterTool(null))

        val decls = com.google.gson.JsonArray()
        FalconClientTools.appendFalconToolDeclarations(decls)
        val musicDecl = (0 until decls.size())
            .map { decls.get(it).asJsonObject }
            .first { it.get("name").asString == "falcon_music_control" }
        assertTrue(musicDecl.get("description").asString.contains("Ruhemodus"))

        assertFalse(
            de.meinseelenfunke.app.ui.screens.FunkiraLiveViewModel.shouldAutoReconnectMidCall(
                wasSetupComplete = true,
                userInitiatedHangup = false,
                shouldEndCall = true,
                reconnectAttemptCount = 0
            )
        )
    }

    @Test
    fun `falcon3d chest emblem uses POT mipmaps max anisotropy feathered rule and polygonOffset`() {
        val candidates = listOf(
            java.io.File("src/main/assets/falcon3d/falcon3d.js"),
            java.io.File("app/src/main/assets/falcon3d/falcon3d.js")
        )
        val assetFile = candidates.firstOrNull { it.exists() }
        assertNotNull("falcon3d.js asset must exist", assetFile)
        val js = assetFile!!.readText(Charsets.UTF_8)

        assertTrue(js.contains("THREE.LinearMipmapLinearFilter"))
        assertTrue(js.contains("getMaxAnisotropy"))
        assertTrue(js.contains("c.width = 768;\n        c.height = 384;") || js.contains("c.width = 768;\r\n        c.height = 384;"))
        assertTrue(js.contains("FUNKIRA"))
        assertTrue(js.contains("polygonOffset: true"))
        assertTrue(js.contains("polygonOffsetFactor: -2"))
    }

    @Test
    fun `smarthome control supports Connexoon Somfy shutters device groups and compound commands for Funkira and Falcone`() = runBlocking {
        FunkenwerkAlarmManager.ensureInitialized(context)
        val plugs = FunkenwerkAlarmManager.defaultSmartPlugs()
        val shutters = listOf(
            SmartShutterDevice(deviceUrl = "io://0813-5321-9574/111", label = "Wohnzimmer", closurePercent = 0),
            SmartShutterDevice(deviceUrl = "io://0813-5321-9574/222", label = "Terrassentür", closurePercent = 20)
        )
        val groups = listOf(
            SmartHomeGroupDto(
                id = "grp_garden",
                name = "Gartenfront",
                shutterTargets = listOf("Wohnzimmer", "Terrassentür"),
                plugTargets = emptyList()
            ),
            SmartHomeGroupDto(
                id = "grp_lights",
                name = "Abendlicht",
                shutterTargets = emptyList(),
                plugTargets = listOf("Lichterkette Fußleiste", "Lichterkette Spiegel")
            )
        )
        FunkenwerkAlarmManager.setSmartHomeInventoryForTests(
            plugs = plugs,
            shutters = shutters,
            groups = groups
        )

        val decls = JsonArray()
        FalconClientTools.appendFalconToolDeclarations(decls)
        val shDecl = (0 until decls.size())
            .map { decls.get(it).asJsonObject }
            .first { it.get("name").asString == "falcon_smarthome_control" }
        val props = shDecl.getAsJsonObject("parameters").getAsJsonObject("properties")
        assertTrue(props.has("position_percent"))
        assertTrue(props.getAsJsonObject("action").get("description").asString.contains("group_close"))

        val directive = FalconClientTools.buildSmartHomeLiveInventoryDirective(plugs, shutters, groups)
        assertTrue(directive.contains("Wohnzimmer"))
        assertTrue(directive.contains("Terrassentür"))
        assertTrue(directive.contains("Gartenfront"))
        assertTrue(directive.contains("Abendlicht"))

        // 1. list_devices exposes plugs, shutters, and groups
        val listRes = FalconClientTools.executeLocalFalconTool(
            context = context,
            toolName = "falcon_smarthome_control",
            argsElement = JsonObject().apply { addProperty("action", "list_devices") },
            chatSessionId = "s-sh-1",
            onEmotionChanged = {},
            onNavigate = {}
        )
        assertEquals("success", listRes.get("status").asString)
        assertEquals(2, listRes.getAsJsonArray("available_shutters").size())
        assertEquals(2, listRes.getAsJsonArray("available_groups").size())

        // 2. Shutter group command ("Gruppe Gartenfront" -> group_close)
        var capturedRelay: Triple<String, String, Int?>? = null
        val grpShutterRes = FalconClientTools.executeLocalFalconTool(
            context = context,
            toolName = "falcon_smarthome_control",
            argsElement = JsonObject().apply {
                addProperty("action", "shutter_close")
                addProperty("device_name", "Gruppe Gartenfront")
            },
            chatSessionId = "s-sh-2",
            onEmotionChanged = {},
            onNavigate = {},
            smartHomeRelayExecutorOverride = { act, target, pos ->
                capturedRelay = Triple(act, target, pos)
                Pair(true, "Gruppe Gartenfront geschlossen")
            }
        )
        assertEquals("success", grpShutterRes.get("status").asString)
        assertEquals("group_close", capturedRelay?.first)
        assertEquals("Gartenfront", capturedRelay?.second)

        // 3. Compound shutter command ("Wohnzimmer und Terassentür" at 65%)
        val compoundShutterRes = FalconClientTools.executeLocalFalconTool(
            context = context,
            toolName = "falcon_smarthome_control",
            argsElement = JsonObject().apply {
                addProperty("action", "shutter_position")
                addProperty("device_name", "Wohnzimmer und Terassentür")
                addProperty("position_percent", 65)
            },
            chatSessionId = "s-sh-3",
            onEmotionChanged = {},
            onNavigate = {},
            smartHomeRelayExecutorOverride = { act, target, pos ->
                capturedRelay = Triple(act, target, pos)
                Pair(true, "Wohnzimmer & Terrassentür auf 65 %")
            }
        )
        assertEquals("success", compoundShutterRes.get("status").asString)
        assertEquals("shutter_position", capturedRelay?.first)
        assertEquals(65, capturedRelay?.third)

        // 4. Plug-only group command ("Abendlicht" -> switches both local Tapo plugs!)
        var capturedPlugIps: List<String> = emptyList()
        val plugGroupRes = FalconClientTools.executeLocalFalconTool(
            context = context,
            toolName = "falcon_smarthome_control",
            argsElement = JsonObject().apply {
                addProperty("action", "turn_on")
                addProperty("device_name", "Abendlicht")
            },
            chatSessionId = "s-sh-4",
            onEmotionChanged = {},
            onNavigate = {},
            smartHomeExecutorOverride = { _, ips ->
                capturedPlugIps = ips
                Pair(true, "2 Steckdosen eingeschaltet")
            }
        )
        assertEquals("success", plugGroupRes.get("status").asString)
        assertEquals(listOf("192.168.188.109", "192.168.188.123"), capturedPlugIps)

        FunkenwerkAlarmManager.setSmartHomeInventoryForTests(
            plugs = plugs,
            shutters = emptyList(),
            groups = emptyList()
        )
    }

    @Test
    fun liveCallAntiDoubleResponse_prunesOverlappingServerToolsAndBatchesToolResponsesIntoSingleFrame() {
        val serverDecls = JsonArray().apply {
            add(JsonObject().apply { addProperty("name", "ag_send_to_antigravity") })
            add(JsonObject().apply { addProperty("name", "ag_check_antigravity_status") })
            add(JsonObject().apply { addProperty("name", "ag_resolve_pending_blockade") })
            add(JsonObject().apply { addProperty("name", "system_open_nav_item") })
            add(JsonObject().apply { addProperty("name", "falcon_set_emotion") })
            add(JsonObject().apply { addProperty("name", "calendar_get_events") })
        }

        FalconClientTools.appendFalconToolDeclarations(serverDecls)
        val finalNames = (0 until serverDecls.size()).map {
            serverDecls.get(it).asJsonObject.get("name").asString
        }.toSet()

        assertTrue(finalNames.contains("calendar_get_events"))
        assertTrue(finalNames.contains("falcon_send_to_antigravity"))
        assertTrue(finalNames.contains("falcon_check_antigravity_status"))
        assertTrue(finalNames.contains("falcon_resolve_antigravity_blockade"))
        assertTrue(finalNames.contains("falcon_app_navigate"))
        assertFalse("Duplicate server ag_send_to_antigravity must be pruned", finalNames.contains("ag_send_to_antigravity"))
        assertFalse("Duplicate server ag_check_antigravity_status must be pruned", finalNames.contains("ag_check_antigravity_status"))
        assertFalse("Duplicate server ag_resolve_pending_blockade must be pruned", finalNames.contains("ag_resolve_pending_blockade"))
        assertFalse("Duplicate server system_open_nav_item must be pruned", finalNames.contains("system_open_nav_item"))
        assertFalse("Roundtrip falcon_set_emotion tool must not be registered", finalNames.contains("falcon_set_emotion"))

        val rawFunctionCalls = JsonArray().apply {
            add(JsonObject().apply {
                addProperty("id", "c1")
                addProperty("name", "calendar_get_events")
                add("args", JsonObject().apply { addProperty("date", "2026-11-24") })
            })
            add(JsonObject().apply {
                addProperty("id", "c2_dup")
                addProperty("name", "calendar_get_events")
                add("args", JsonObject().apply { addProperty("date", "2026-11-24") })
            })
            add(JsonObject().apply {
                addProperty("id", "c3")
                addProperty("name", "task_get_all")
                add("args", JsonObject())
            })
        }

        val deduped = FalconClientTools.deduplicateFunctionCallsInBatch(rawFunctionCalls)
        assertEquals(2, deduped.size)
        assertEquals("c1", deduped[0].get("id").asString)
        assertEquals("c3", deduped[1].get("id").asString)

        val batchedPacket = FalconClientTools.buildBatchedToolResponsePacket(
            listOf(
                Triple("c1", "calendar_get_events", JsonObject().apply { addProperty("status", "ok") }),
                Triple("c3", "task_get_all", JsonObject().apply { addProperty("count", 3) })
            )
        )
        val fnResps = batchedPacket.getAsJsonObject("toolResponse").getAsJsonArray("functionResponses")
        assertEquals(2, fnResps.size())
        assertEquals("c1", fnResps.get(0).asJsonObject.get("id").asString)
        assertEquals("c3", fnResps.get(1).asJsonObject.get("id").asString)
    }

    @Test
    fun liveCallEchoTailAndBargeInGate_suppressesPostSpeechReverbAndSingleConsonantSpikes() {
        val speechEndedAt = 10_000L

        // Within 450ms speakerphone tail -> mic stays suppressed so AI reverb cannot trigger a 2nd answer
        assertTrue(
            FalconClientTools.shouldSuppressMicForAiPlaybackOrEchoTail(
                isAiSpeakingNow = false,
                nowUptimeMs = speechEndedAt + 280L,
                lastAiSpeechActiveUptimeMs = speechEndedAt,
                isHeadsetRouted = false
            )
        )
        // After 460ms -> mic opens cleanly
        assertFalse(
            FalconClientTools.shouldSuppressMicForAiPlaybackOrEchoTail(
                isAiSpeakingNow = false,
                nowUptimeMs = speechEndedAt + 460L,
                lastAiSpeechActiveUptimeMs = speechEndedAt,
                isHeadsetRouted = false
            )
        )
        // Headset needs only 150ms tail
        assertFalse(
            FalconClientTools.shouldSuppressMicForAiPlaybackOrEchoTail(
                isAiSpeakingNow = false,
                nowUptimeMs = speechEndedAt + 170L,
                lastAiSpeechActiveUptimeMs = speechEndedAt,
                isHeadsetRouted = true
            )
        )

        // Single speakerphone consonant spike (high peak 13000, moderate RMS 1900) must NOT trigger barge-in
        assertFalse(
            FalconClientTools.isSustainedHumanBargeInFrame(
                rms = 1900.0,
                peak = 13000,
                isHeadsetRouted = false
            )
        )
        // Real human voice close to mic (RMS 4500, peak 16500) qualifies and requires 3 consecutive frames
        assertTrue(
            FalconClientTools.isSustainedHumanBargeInFrame(
                rms = 4500.0,
                peak = 16500,
                isHeadsetRouted = false
            )
        )
        assertEquals(3, FalconClientTools.requiredBargeInConsecutiveFrames(isHeadsetRouted = false))

        // Local zero-roundtrip emotion inference
        assertEquals("proud", FalconClientTools.inferEmotionFromLiveTurn("Alles perfekt erledigt!", null))
        assertEquals("happy", FalconClientTools.inferEmotionFromLiveTurn("Mach ich super gerne für dich.", null))
        assertEquals("focused", FalconClientTools.inferEmotionFromLiveTurn(null, "falcon_send_to_antigravity"))
    }

    @Test
    fun intelligentMusicMatcher_selectsContextRoutinePlaylistsAndAnalyzesSingleSongPlaylistsWithoutFavoritesBias() {
        val tFavHardtekk = MusicTrackDto("t_fav_1", "pl_fav", "Tekk Abriss", "Die Gebrüder Brett", 190, 1000L, true, 0)
        val tFeelGood1 = MusicTrackDto("t_fg_1", "pl_life", "Billie Jean", "Michael Jackson", 290, 1000L, true, 0)
        val tFeelGood2 = MusicTrackDto("t_fg_2", "pl_life", "Return of the Mack", "Mark Morrison", 215, 1000L, false, 1)
        val tRave1 = MusicTrackDto("t_rave_1", "pl_rave", "Overdrive", "Charlotte de Witte", 310, 1000L, false, 0)
        val tSingleCalmSeed = MusicTrackDto("t_seed_1", "pl_single_vibe", "Only Time", "Enya", 218, 1000L, false, 0)
        val tCalmCompanion = MusicTrackDto("t_calm_2", "all", "River Flows Piano", "Ludovico Acoustic", 205, 1000L, false, 0)

        val playlists = listOf(
            MusicPlaylistDto("favorites", "Lieblingssongs", "Alle gemerkten Favoriten", listOf(tFavHardtekk, tFeelGood1)),
            MusicPlaylistDto("pl_life", "LifeIsGood", "Pop & Feelgood Alltag", listOf(tFeelGood1, tFeelGood2)),
            MusicPlaylistDto("pl_rave", "Hardtekk, Tekk & Rave (Abriss)", "Vollgas Bass", listOf(tRave1, tFavHardtekk)),
            MusicPlaylistDto("pl_single_vibe", "Mein Vibe", "", listOf(tSingleCalmSeed))
        )
        val allTracks = listOf(tFavHardtekk, tFeelGood1, tFeelGood2, tRave1, tSingleCalmSeed, tCalmCompanion)

        // 1. Deep analysis of a 1-song playlist with neutral title ("Mein Vibe") must infer calm_evening from 'Only Time' by Enya
        val singleProfile = FalconClientTools.analyzePlaylistSemanticProfile(playlists.last())
        assertTrue(singleProfile.isSingleSongPlaylist)
        assertFalse(singleProfile.isFavoritesPool)
        assertEquals("calm_evening", singleProfile.primaryCluster)
        assertTrue(singleProfile.seedTrackSummary.orEmpty().contains("Only Time"))
        assertTrue(singleProfile.seedTrackSummary.orEmpty().contains("Enya"))

        // 2. Live music directive exposes the 1-song playlist's exact song & artist to Funkira
        val libDirective = FalconClientTools.buildMusicLibraryLiveDirective(playlists)
        assertTrue(libDirective.contains("Mein Vibe"))
        assertTrue(libDirective.contains("Only Time"))
        assertTrue(libDirective.contains("LifeIsGood"))

        // 3. Generic morning/routine playback (08:30, blank query) selects LifeIsGood instead of static Favorites!
        val morningSmart = FalconClientTools.matchMusicForFalcon(
            playlists = playlists,
            allTracks = allTracks,
            action = "play_smart",
            query = "",
            currentHour = 8,
            routineContext = "Morgenroutine & Packtisch"
        )
        assertEquals("pl_life", morningSmart.playlistId)
        assertFalse("Must not blindly fall back to static Favorites when contextual playlist exists", morningSmart.usedFallbackFavorites)

        // 4. Late-night / calm mood request selects the 1-song calm playlist, puts 'Only Time' at #1, and appends calm companions (NEVER Hardtekk!)
        val nightCalm = FalconClientTools.matchMusicForFalcon(
            playlists = playlists,
            allTracks = allTracks,
            action = "play_smart",
            query = "ruhige Musik zum Entspannen",
            currentHour = 22
        )
        assertEquals("pl_single_vibe", nightCalm.playlistId)
        assertTrue(nightCalm.isSingleSongSeedMatch)
        assertFalse(nightCalm.shuffle)
        assertEquals("t_seed_1", nightCalm.tracks.first().id)
        assertTrue("Expected calm companion track to extend 1-song seed queue", nightCalm.tracks.any { it.id == "t_calm_2" })
        assertFalse("Hardtekk track must NEVER be appended to calm 1-song seed queue", nightCalm.tracks.any { it.id == "t_fav_1" || it.id == "t_rave_1" })

        // 5. Direct query by the single song's artist ("Enya") matches the 1-song playlist directly
        val bySingleArtist = FalconClientTools.matchMusicForFalcon(
            playlists = playlists,
            allTracks = allTracks,
            action = "play_query",
            query = "Playlist mit Enya"
        )
        assertEquals("pl_single_vibe", bySingleArtist.playlistId)
        assertEquals(listOf("t_seed_1"), bySingleArtist.tracks.map { it.id })

        // 6. Explicit Favorites request still plays Lieblingssongs intentionally
        val explicitFav = FalconClientTools.matchMusicForFalcon(
            playlists = playlists,
            allTracks = allTracks,
            action = "play_query",
            query = "Spiel meine Lieblingssongs"
        )
        assertEquals("favorites", explicitFav.playlistId)
        assertFalse(explicitFav.usedFallbackFavorites)

        // 7. analyze_playlist does not disconnect Live-Call into Ruhemodus, whereas playback does
        assertFalse(FalconClientTools.shouldEnterStandbyAfterTool("falcon_music_control", "analyze_playlist"))
        assertFalse(FalconClientTools.shouldEnterStandbyAfterTool("falcon_music_control", "list_playlists"))
        assertTrue(FalconClientTools.shouldEnterStandbyAfterTool("falcon_music_control", "play_smart"))
    }

    @Test
    fun smartHomeLiveCall_controlsLichterkettenShuttersPlugGroupsShutterGroupsHybridGroupsAndPrunesServerDuplicates() = runBlocking {
        // 1. Server duplicate smarthome_* tools must be pruned so Android Live-Call ALWAYS executes local falcon_smarthome_control
        val serverDecls = JsonArray().apply {
            add(JsonObject().apply { addProperty("name", "smarthome_control") })
            add(JsonObject().apply { addProperty("name", "smarthome_get_devices_and_groups") })
            add(JsonObject().apply { addProperty("name", "calendar_get_events") })
        }
        FalconClientTools.appendFalconToolDeclarations(serverDecls)
        val declNames = (0 until serverDecls.size()).map { serverDecls.get(it).asJsonObject.get("name").asString }.toSet()
        assertFalse(declNames.contains("smarthome_control"))
        assertFalse(declNames.contains("smarthome_get_devices_and_groups"))
        assertTrue(declNames.contains("falcon_smarthome_control"))

        // 2. Placeholder "Steckdose 1/2" self-heals to canonical "Lichterkette Fußleiste" & "Packtisch Warm Licht"
        val healedPlugs = FunkenwerkAlarmManager.normalizeSmartPlugs(
            listOf(
                SmartPlugDevice(name = "Steckdose 1", ip = "192.168.188.109"),
                SmartPlugDevice(name = "Steckdose 2", ip = "192.168.188.110"),
                SmartPlugDevice(name = "Lichterkette Spiegel", ip = "192.168.188.123")
            )
        )
        assertEquals(
            listOf("Lichterkette Fußleiste", "Packtisch Warm Licht", "Lichterkette Spiegel"),
            healedPlugs.map { it.name }
        )

        // 3. Overkiz Connexoon JSON parser extracts io & RTS shutters while ignoring sensors
        val sampleOverkizJson = """
            [
              {"deviceURL":"io://0813-5321-9574/101","label":"Wohnzimmer","controllableName":"io:RollerShutterGenericIOComponent","available":true,"definition":{"uiClass":"RollerShutter"},"states":[{"name":"core:ClosureState","value":25}]},
              {"deviceURL":"rts://0813-5321-9574/202","label":"Terrassentür","controllableName":"rts:RollerShutterRTSComponent","available":true,"definition":{"uiClass":"RollerShutter"},"states":[{"name":"core:OpenClosedState","value":"closed"}]},
              {"deviceURL":"io://0813-5321-9574/999","label":"Fenstergriff","controllableName":"io:ThreeWayWindowHandleIOSystemSensor","definition":{"uiClass":"WindowHandle"}}
            ]
        """.trimIndent()
        val parsedShutters = FunkenwerkAlarmManager.parseOverkizDevicesJson(sampleOverkizJson)
        assertEquals(2, parsedShutters.size)
        assertEquals("Terrassentür", parsedShutters[0].label)
        assertFalse(parsedShutters[0].supportsPosition)
        assertEquals(100, parsedShutters[0].closurePercent)
        assertEquals("Wohnzimmer", parsedShutters[1].label)
        assertTrue(parsedShutters[1].supportsPosition)
        assertEquals(25, parsedShutters[1].closurePercent)

        // 4. Configure inventory with Lichterketten, Shutters, Plug-Group, Shutter-Group & Hybrid-Group!
        val groups = listOf(
            SmartHomeGroupDto(
                id = "grp_lichterketten",
                name = "Gemütliches Licht",
                shutterTargets = emptyList(),
                plugTargets = listOf("Lichterkette Fußleiste", "Lichterkette Spiegel")
            ),
            SmartHomeGroupDto(
                id = "grp_hybrid_abend",
                name = "Abendmodus Wohnbereich",
                shutterTargets = listOf("Wohnzimmer", "Terrassentür"),
                plugTargets = listOf("Lichterkette Fußleiste", "Lichterkette Spiegel")
            )
        )
        FunkenwerkAlarmManager.setSmartHomeInventoryForTests(
            plugs = healedPlugs,
            shutters = parsedShutters,
            groups = groups
        )

        try {
            // 4a. "turn_all_on" with device_name="alle Lichterketten" MUST switch ONLY the 2 Lichterketten, NOT Packtisch!
            var switchedPlugIps: List<String> = emptyList()
            var relayCalledCount = 0
            val resAllLichterketten = FalconClientTools.executeLocalFalconTool(
                context = context,
                toolName = "falcon_smarthome_control",
                argsElement = JsonObject().apply {
                    addProperty("action", "turn_all_on")
                    addProperty("device_name", "alle Lichterketten")
                },
                chatSessionId = "s-sh-lk",
                onEmotionChanged = {},
                onNavigate = {},
                smartHomeExecutorOverride = { _, ips ->
                    switchedPlugIps = ips
                    Pair(true, "Lichterketten eingeschaltet")
                },
                smartHomeRelayExecutorOverride = { _, _, _ ->
                    relayCalledCount++
                    Pair(true, "relay")
                }
            )
            assertEquals("success", resAllLichterketten.get("status").asString)
            assertEquals(listOf("192.168.188.109", "192.168.188.123"), switchedPlugIps)
            assertEquals(0, relayCalledCount)

            // 4b. Plug-only group called with action="group_on" switches local plugs directly with 0 unnecessary shutter relay!
            switchedPlugIps = emptyList()
            val resPlugGroupOn = FalconClientTools.executeLocalFalconTool(
                context = context,
                toolName = "falcon_smarthome_control",
                argsElement = JsonObject().apply {
                    addProperty("action", "group_on")
                    addProperty("device_name", "Gemütliches Licht")
                },
                chatSessionId = "s-sh-pg",
                onEmotionChanged = {},
                onNavigate = {},
                smartHomeExecutorOverride = { _, ips ->
                    switchedPlugIps = ips
                    Pair(true, "Gruppe Gemütliches Licht eingeschaltet")
                },
                smartHomeRelayExecutorOverride = { _, _, _ ->
                    relayCalledCount++
                    Pair(true, "relay")
                }
            )
            assertEquals("success", resPlugGroupOn.get("status").asString)
            assertEquals("Gemütliches Licht", resPlugGroupOn.get("matched_group").asString)
            assertEquals(listOf("192.168.188.109", "192.168.188.123"), switchedPlugIps)
            assertEquals(0, relayCalledCount)

            // 4c. Hybrid Group ("Abendmodus Wohnbereich": Shutters + Lichterketten) executes BOTH plugs AND shutters!
            switchedPlugIps = emptyList()
            var lastShutterAction: Triple<String, String, Int?>? = null
            val resHybrid = FalconClientTools.executeLocalFalconTool(
                context = context,
                toolName = "falcon_smarthome_control",
                argsElement = JsonObject().apply {
                    addProperty("action", "group_close")
                    addProperty("device_name", "Abendmodus Wohnbereich")
                },
                chatSessionId = "s-sh-hyb",
                onEmotionChanged = {},
                onNavigate = {},
                smartHomeExecutorOverride = { act, ips ->
                    assertEquals("turn_off", act)
                    switchedPlugIps = ips
                    Pair(true, "Lichterketten AUS")
                },
                smartHomeRelayExecutorOverride = { act, target, pos ->
                    lastShutterAction = Triple(act, target, pos)
                    Pair(true, "Rollläden geschlossen")
                }
            )
            assertEquals("success", resHybrid.get("status").asString)
            assertEquals(listOf("192.168.188.109", "192.168.188.123"), switchedPlugIps)
            assertEquals("group_close", lastShutterAction?.first)
            assertEquals("Abendmodus Wohnbereich", lastShutterAction?.second)

            // 4d. Mixed compound command ("Rollladen Wohnzimmer und Lichterkette Spiegel" -> shutter_open + plug turn_on)
            switchedPlugIps = emptyList()
            lastShutterAction = null
            val resMixedCompound = FalconClientTools.executeLocalFalconTool(
                context = context,
                toolName = "falcon_smarthome_control",
                argsElement = JsonObject().apply {
                    addProperty("action", "shutter_open")
                    addProperty("device_name", "Rollladen Wohnzimmer und Lichterkette Spiegel")
                },
                chatSessionId = "s-sh-mix",
                onEmotionChanged = {},
                onNavigate = {},
                smartHomeExecutorOverride = { act, ips ->
                    assertEquals("turn_on", act)
                    switchedPlugIps = ips
                    Pair(true, "Lichterkette Spiegel EIN")
                },
                smartHomeRelayExecutorOverride = { act, target, pos ->
                    lastShutterAction = Triple(act, target, pos)
                    Pair(true, "Wohnzimmer geöffnet")
                }
            )
            assertEquals("success", resMixedCompound.get("status").asString)
            assertEquals(listOf("192.168.188.123"), switchedPlugIps)
            assertEquals("shutter_open", lastShutterAction?.first)
        } finally {
            FunkenwerkAlarmManager.setSmartHomeInventoryForTests(
                plugs = FunkenwerkAlarmManager.defaultSmartPlugs(),
                shutters = emptyList(),
                groups = emptyList()
            )
        }
    }

    @Test
    fun liveCallZeroThinkingSetupAndDeviceReflexFastLane_executesImmediatelyAndDeduplicatesToolCall() = runBlocking {
        // 1. Direct AI Studio 3.1 receives thinkingLevel='minimal', Portal Bridge & Vertex 3.1 omit thinkingLevel (preventing 1007!), 2.5 receives thinkingBudget=0
        val cfgBridge31 = FalconClientTools.buildLiveThinkingConfig("models/gemini-3.1-flash-live-preview", includeThinkingConfig = true, isDirectGoogleAiStudio = false)
        assertNull(cfgBridge31)

        val cfgDirect31 = FalconClientTools.buildLiveThinkingConfig("models/gemini-3.1-flash-live-preview", includeThinkingConfig = true, isDirectGoogleAiStudio = true)
        assertNotNull(cfgDirect31)
        assertEquals("minimal", cfgDirect31!!.get("thinkingLevel").asString)

        val cfgVertex31 = FalconClientTools.buildLiveThinkingConfig(
            "projects/mein-seelenfunke/locations/europe-west1/publishers/google/models/gemini-3.1-flash-live-preview",
            includeThinkingConfig = true,
            isDirectGoogleAiStudio = true
        )
        assertNull(cfgVertex31)

        val cfg25 = FalconClientTools.buildLiveThinkingConfig("models/gemini-2.5-flash-native-audio", true)
        assertNotNull(cfg25)
        assertEquals(0, cfg25!!.get("thinkingBudget").asInt)

        assertNull(FalconClientTools.buildLiveThinkingConfig("models/gemini-3.1-flash-live-preview", includeThinkingConfig = false, isDirectGoogleAiStudio = true))

        // 1b. Deterministic Sammelpaket release voice reflex matches explicit send imperatives and rejects staging/negations
        val pkgRel1 = FalconClientTools.matchDeterministicPackageReleaseReflex("Schick das Sammelpaket an Antigravity")
        assertNotNull(pkgRel1)
        assertEquals("falcon_release_package", pkgRel1!!.first)

        val pkgRelDirect = FalconClientTools.matchDeterministicPackageReleaseReflex("Bitte Sammelpaket direkt absenden")
        assertNotNull(pkgRelDirect)
        assertEquals("direct", pkgRelDirect!!.second.get("execution_mode").asString)

        assertNull(FalconClientTools.matchDeterministicPackageReleaseReflex("Leg das bitte noch ins Sammelpaket"))
        assertNull(FalconClientTools.matchDeterministicPackageReleaseReflex("Schick das Sammelpaket noch nicht ab"))
        assertNull(FalconClientTools.matchDeterministicPackageReleaseReflex("Welche Punkte sind im Sammelpaket?"))

        // 2. Deterministic reflex matches crisp German imperatives and strictly rejects negations/questions/concepts
        val lkOn = FalconClientTools.matchDeterministicDeviceReflex("Mach bitte alle Lichterketten an!")
        assertNotNull(lkOn)
        assertEquals("falcon_smarthome_control", lkOn!!.first)
        assertEquals("turn_on", lkOn.second.get("action").asString)
        assertEquals("alle Lichterketten", lkOn.second.get("device_name").asString)

        val camOn = FalconClientTools.matchDeterministicDeviceReflex("Kamera an")
        assertNotNull(camOn)
        assertEquals("falcon_camera_control", camOn!!.first)
        assertEquals("enable_vision", camOn.second.get("action").asString)

        val camOff = FalconClientTools.matchDeterministicDeviceReflex("Mach die Kamera wieder aus")
        assertNotNull(camOff)
        assertEquals("falcon_camera_control", camOff!!.first)
        assertEquals("disable_vision", camOff.second.get("action").asString)

        val shutterDown = FalconClientTools.matchDeterministicDeviceReflex("Fahr den Rollladen im Wohnzimmer runter")
        assertNotNull(shutterDown)
        assertEquals("falcon_smarthome_control", shutterDown!!.first)
        assertEquals("shutter_close", shutterDown.second.get("action").asString)
        assertEquals("Wohnzimmer", shutterDown.second.get("device_name").asString)

        assertNull(FalconClientTools.matchDeterministicDeviceReflex("Mach die Kamera nicht an"))
        assertNull(FalconClientTools.matchDeterministicDeviceReflex("Erstelle ein Konzept fuer die Kamera"))
        assertNull(FalconClientTools.matchDeterministicDeviceReflex("Sind die Lichterketten an?"))
        assertNull(FalconClientTools.matchDeterministicDeviceReflex("Schalte um 20 Uhr die Lichterketten ein"))

        // 3. Single-flight coalescing cache executes hardware ONCE when reflex fires first and Gemini toolCall arrives 200ms later,
        //    and opposite command ('aus') immediately evicts 'an' so rapid reversal works without stale cache!
        FalconClientTools.clearDeviceReflexCacheForTests()
        FunkenwerkAlarmManager.setSmartHomeInventoryForTests(
            plugs = FunkenwerkAlarmManager.defaultSmartPlugs(),
            shutters = emptyList(),
            groups = emptyList()
        )
        var hardwareExecutionCount = 0
        try {
            val t0 = 100_000L
            val reflexRes = FalconClientTools.executeDeviceToolWithReflexDedupe(
                context = context,
                toolName = lkOn.first,
                argsElement = lkOn.second,
                chatSessionId = "s-reflex-1",
                nowMs = t0,
                smartHomeExecutorOverride = { _, _ ->
                    hardwareExecutionCount++
                    Pair(true, "Lichterketten eingeschaltet")
                }
            )
            assertEquals("success", reflexRes.get("status").asString)
            assertEquals(1, hardwareExecutionCount)

            // Gemini emits slightly different phrasing ("turn_all_on", "Lichterketten") 250ms later -> coalesced in 0ms!
            val geminiCallRes = FalconClientTools.executeDeviceToolWithReflexDedupe(
                context = context,
                toolName = "falcon_smarthome_control",
                argsElement = JsonObject().apply {
                    addProperty("action", "turn_all_on")
                    addProperty("device_name", "Lichterketten")
                },
                chatSessionId = "s-reflex-1",
                nowMs = t0 + 250L,
                smartHomeExecutorOverride = { _, _ ->
                    hardwareExecutionCount++
                    Pair(true, "Lichterketten eingeschaltet")
                }
            )
            assertEquals("success", geminiCallRes.get("status").asString)
            assertTrue(geminiCallRes.get("coalesced_fast_lane").asBoolean)
            assertEquals("Hardware must execute strictly ONCE across reflex + Gemini toolCall", 1, hardwareExecutionCount)

            // Immediate opposite command ('turn_off') executes fresh hardware and evicts 'on' cache
            val offRes = FalconClientTools.executeDeviceToolWithReflexDedupe(
                context = context,
                toolName = "falcon_smarthome_control",
                argsElement = JsonObject().apply {
                    addProperty("action", "turn_off")
                    addProperty("device_name", "alle Lichterketten")
                },
                chatSessionId = "s-reflex-1",
                nowMs = t0 + 800L,
                smartHomeExecutorOverride = { _, _ ->
                    hardwareExecutionCount++
                    Pair(true, "Lichterketten ausgeschaltet")
                }
            )
            assertEquals("success", offRes.get("status").asString)
            assertEquals(2, hardwareExecutionCount)

            // Turning back ON right after OFF must execute fresh hardware (3rd call) because OFF evicted ON!
            val onAgainRes = FalconClientTools.executeDeviceToolWithReflexDedupe(
                context = context,
                toolName = "falcon_smarthome_control",
                argsElement = JsonObject().apply {
                    addProperty("action", "turn_on")
                    addProperty("device_name", "alle Lichterketten")
                },
                chatSessionId = "s-reflex-1",
                nowMs = t0 + 1400L,
                smartHomeExecutorOverride = { _, _ ->
                    hardwareExecutionCount++
                    Pair(true, "Lichterketten eingeschaltet")
                }
            )
            assertEquals("success", onAgainRes.get("status").asString)
            assertEquals(3, hardwareExecutionCount)
        } finally {
            FalconClientTools.clearDeviceReflexCacheForTests()
        }
    }
}
