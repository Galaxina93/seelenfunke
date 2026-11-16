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
        assertEquals(8, names.size)
        for (expected in FalconClientTools.LOCAL_TOOL_NAMES) {
            assertTrue("Expected local Falcone tool $expected to be injected", expected in names)
        }
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

        // Before 120s: no action
        assertEquals(FalconInactivityAction.NONE, tracker.evaluate(120_999L, isAiSpeaking = false))
        assertFalse(tracker.inactivityCheckSent)

        // Exactly at 120s of silence: Stage 1 check prompt sent
        assertEquals(FalconInactivityAction.SEND_STAGE1_CHECK, tracker.evaluate(121_000L, isAiSpeaking = false))
        assertTrue(tracker.inactivityCheckSent)

        // Falcone speaks his Stage 1 follow-up question (must NOT reset inactivityCheckSent!)
        tracker.onAiActivity(121_400L)
        assertEquals(FalconInactivityAction.NONE, tracker.evaluate(122_000L, isAiSpeaking = true))
        tracker.onAiSpeechFinished(123_500L)
        assertTrue(tracker.inactivityCheckSent)
        assertEquals(FalconInactivityStage.CHECK_SENT_WAITING_USER_REPLY, tracker.stage)

        // User replies 10s later -> resets back to ACTIVE and fresh 120s timer!
        tracker.onUserSpeech(133_500L)
        assertFalse(tracker.inactivityCheckSent)
        assertEquals(FalconInactivityStage.ACTIVE, tracker.stage)
        assertEquals(FalconInactivityAction.NONE, tracker.evaluate(250_000L, isAiSpeaking = false))

        // Another full 120s of silence from 133_500 -> triggers Stage 1 again at 253_500
        assertEquals(FalconInactivityAction.SEND_STAGE1_CHECK, tracker.evaluate(253_500L, isAiSpeaking = false))
        tracker.onAiActivity(254_000L)
        tracker.onAiSpeechFinished(256_000L)

        // 24.9s silence after question finished -> still waiting
        assertEquals(FalconInactivityAction.NONE, tracker.evaluate(280_900L, isAiSpeaking = false))

        // 25.0s silence after question finished -> Stage 2 farewell triggered!
        assertEquals(FalconInactivityAction.SEND_STAGE2_FAREWELL, tracker.evaluate(281_000L, isAiSpeaking = false))
        assertEquals(FalconInactivityStage.FAREWELL_CLOSING, tracker.stage)

        // Falcone speaks farewell from 281_400 to 283_400, then +1.2s post-speech buffer -> FORCE_DISCONNECT at 284_600
        tracker.onAiActivity(281_400L)
        tracker.onAiSpeechFinished(283_400L)
        assertEquals(FalconInactivityAction.NONE, tracker.evaluate(284_500L, isAiSpeaking = false))
        assertEquals(FalconInactivityAction.FORCE_DISCONNECT, tracker.evaluate(284_600L, isAiSpeaking = false))

        // Also verify hard 7s safety ceiling if model never emits audio or end_call
        val ceilingTracker = FalconInactivityTracker()
        ceilingTracker.onSessionStarted(0L)
        assertEquals(FalconInactivityAction.SEND_STAGE1_CHECK, ceilingTracker.evaluate(120_000L, isAiSpeaking = false))
        ceilingTracker.onAiSpeechFinished(122_000L)
        assertEquals(FalconInactivityAction.SEND_STAGE2_FAREWELL, ceilingTracker.evaluate(147_000L, isAiSpeaking = false))
        assertEquals(FalconInactivityAction.NONE, ceilingTracker.evaluate(153_900L, isAiSpeaking = false))
        assertEquals(FalconInactivityAction.FORCE_DISCONNECT, ceilingTracker.evaluate(154_000L, isAiSpeaking = false))

        // Verify Gemini 3.1 realtimeInput.text schema & zero AI en-dashes
        val p1 = FalconClientTools.buildRealtimeTextPromptPacket(FalconClientTools.STAGE1_INACTIVITY_PROMPT)
        val p2 = FalconClientTools.buildRealtimeTextPromptPacket(FalconClientTools.STAGE2_AUTO_END_PROMPT)
        assertTrue(p1.has("realtimeInput"))
        assertTrue(p2.getAsJsonObject("realtimeInput").get("text").asString.contains("Hey Falcone"))
        assertFalse(FalconClientTools.STAGE1_INACTIVITY_PROMPT.contains(" \u2013 "))
        assertFalse(FalconClientTools.STAGE2_AUTO_END_PROMPT.contains(" \u2013 "))
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

        // 2. Strict Gemini 3.1 Live schema hygiene across all 7 local Falcone tools
        val decls = JsonArray()
        FalconClientTools.appendFalconToolDeclarations(decls)
        assertEquals(7, decls.size())
        for (i in 0 until decls.size()) {
            val fn = decls.get(i).asJsonObject
            val params = fn.getAsJsonObject("parameters")
            assertEquals("OBJECT", params.get("type").asString)
            assertTrue(params.has("properties") && params.get("properties").isJsonObject)
            assertFalse(params.has("additionalProperties"))
        }

        // 3. Hard mathematical upper-arm safety clamp in falcon3d.js
        val jsCandidates = listOf(
            File("src/main/assets/falcon3d/falcon3d.js"),
            File("app/src/main/assets/falcon3d/falcon3d.js")
        )
        val jsFile = jsCandidates.firstOrNull { it.exists() }
        assertNotNull("Expected assets/falcon3d/falcon3d.js to exist", jsFile)
        val jsContent = jsFile!!.readText(Charsets.UTF_8)
        assertTrue(jsContent.contains("rArmX = clamp(rArmX, -0.18, 0.12);"))
        assertTrue(jsContent.contains("lArmX = clamp(lArmX, -0.18, 0.12);"))
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
}
