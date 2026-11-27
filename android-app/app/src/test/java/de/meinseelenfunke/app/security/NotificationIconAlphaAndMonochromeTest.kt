package de.meinseelenfunke.app.security

import android.content.Context
import android.graphics.Bitmap
import android.graphics.Canvas
import android.graphics.Color
import android.graphics.drawable.AdaptiveIconDrawable
import androidx.core.content.ContextCompat
import de.meinseelenfunke.app.R
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertTrue
import org.junit.Test
import org.junit.runner.RunWith
import org.robolectric.RobolectricTestRunner
import org.robolectric.RuntimeEnvironment
import org.robolectric.annotation.Config
import org.w3c.dom.Document
import org.w3c.dom.Element
import java.io.File
import javax.xml.parsers.DocumentBuilderFactory

@RunWith(RobolectricTestRunner::class)
@Config(sdk = [34])
class NotificationIconAlphaAndMonochromeTest {

    private val androidNs = "http://schemas.android.com/apk/res/android"

    private fun resolveProjectFile(relativeFromApp: String): File {
        val candidates = listOf(
            File(relativeFromApp),
            File("app/$relativeFromApp")
        )
        val found = candidates.firstOrNull { it.exists() && it.isFile }
        assertNotNull("Expected file to exist at $relativeFromApp", found)
        return found!!
    }

    private fun parseXml(file: File): Document {
        val factory = DocumentBuilderFactory.newInstance().apply {
            isNamespaceAware = true
        }
        return factory.newDocumentBuilder().parse(file).apply {
            documentElement.normalize()
        }
    }

    @Test
    fun icStatFunkenwerk_hasTransparentBackgroundAndSharpWhiteSilhouette() {
        val doc = parseXml(resolveProjectFile("src/main/res/drawable/ic_stat_funkenwerk.xml"))
        val root = doc.documentElement
        assertEquals("vector", root.tagName)
        assertEquals("24dp", root.getAttributeNS(androidNs, "width"))
        assertEquals("24dp", root.getAttributeNS(androidNs, "height"))
        assertEquals("24", root.getAttributeNS(androidNs, "viewportWidth"))
        assertEquals("24", root.getAttributeNS(androidNs, "viewportHeight"))

        val paths = doc.getElementsByTagName("path")
        assertTrue("Expected at least 4 vector paths for Funkenwerk emblem", paths.length >= 4)
        assertEquals("Must not contain solid background tags", 0, doc.getElementsByTagName("solid").length)

        val coordRegex = Regex("""-?\d+(?:\.\d+)?""")
        val allCoords = mutableListOf<Double>()
        var hasEvenOddHollowArch = false
        for (i in 0 until paths.length) {
            val pathEl = paths.item(i) as Element
            assertEquals(
                "Status bar vector paths must be pure white (#FFFFFFFF) with alpha cutout",
                "#FFFFFFFF",
                pathEl.getAttributeNS(androidNs, "fillColor").uppercase()
            )
            if (pathEl.getAttributeNS(androidNs, "fillType") == "evenOdd") {
                hasEvenOddHollowArch = true
            }
            val pathData = pathEl.getAttributeNS(androidNs, "pathData")
            assertTrue("pathData must not be blank", pathData.isNotBlank())
            val parsedNodes = androidx.core.graphics.PathParser.createNodesFromPathData(pathData)
            assertNotNull("PathParser must parse pathData cleanly", parsedNodes)
            assertTrue("Parsed path nodes must not be empty", parsedNodes!!.isNotEmpty())
            coordRegex.findAll(pathData).forEach { m ->
                allCoords.add(m.value.toDouble())
            }
        }
        assertTrue("Crystal arch must use evenOdd cutout for transparent interior", hasEvenOddHollowArch)
        assertTrue("Expected non-empty path coordinates", allCoords.isNotEmpty())
        // Every coordinate must stay strictly inside Safe-Zone 1.5..22.5 so corners & outer border are 100% transparent
        allCoords.forEach { c ->
            assertTrue(
                "Coordinate $c must stay inside 1.5..22.5 safe zone (transparent background)",
                c in 1.5..22.5
            )
        }

        val context: Context = RuntimeEnvironment.getApplication()
        val drawable = ContextCompat.getDrawable(context, R.drawable.ic_stat_funkenwerk)
        assertNotNull("ic_stat_funkenwerk drawable must resolve", drawable)
        assertTrue("Intrinsic width must be positive", drawable!!.intrinsicWidth > 0)
        assertTrue("Intrinsic height must be positive", drawable.intrinsicHeight > 0)
    }

    @Test
    fun adaptiveLauncherIcons_declareMonochromeLayerInSafeZone() {
        for (relPath in listOf(
            "src/main/res/mipmap-anydpi-v26/ic_launcher.xml",
            "src/main/res/mipmap-anydpi-v26/ic_launcher_round.xml"
        )) {
            val doc = parseXml(resolveProjectFile(relPath))
            val monochromeNodes = doc.getElementsByTagName("monochrome")
            assertEquals("$relPath must define <monochrome> for Android 13-16 Themed Icons", 1, monochromeNodes.length)
            val monoEl = monochromeNodes.item(0) as Element
            val directDrawable = monoEl.getAttributeNS(androidNs, "drawable")
            val insetNodes = monoEl.getElementsByTagName("inset")
            val usesStatFunkenwerk = directDrawable == "@drawable/ic_stat_funkenwerk" ||
                (insetNodes.length == 1 && (insetNodes.item(0) as Element).getAttributeNS(androidNs, "drawable") == "@drawable/ic_stat_funkenwerk")
            assertTrue("$relPath <monochrome> must reference @drawable/ic_stat_funkenwerk", usesStatFunkenwerk)
        }

        val context: Context = RuntimeEnvironment.getApplication()
        val launcherDrawable = context.getDrawable(R.mipmap.ic_launcher)
        assertTrue("ic_launcher should inflate as AdaptiveIconDrawable on API 34", launcherDrawable is AdaptiveIconDrawable)
        val adaptive = launcherDrawable as AdaptiveIconDrawable
        assertNotNull("AdaptiveIconDrawable.monochrome must not be null", adaptive.monochrome)
    }

    @Test
    fun manifestDeclaresFirebaseDefaultNotificationIconAndGoldColor() {
        val doc = parseXml(resolveProjectFile("src/main/AndroidManifest.xml"))
        val metaNodes = doc.getElementsByTagName("meta-data")
        val metaMap = mutableMapOf<String, String>()
        for (i in 0 until metaNodes.length) {
            val el = metaNodes.item(i) as Element
            val name = el.getAttributeNS(androidNs, "name")
            val res = el.getAttributeNS(androidNs, "resource")
            if (name.isNotBlank()) {
                metaMap[name] = res
            }
        }
        assertEquals(
            "@drawable/ic_stat_funkenwerk",
            metaMap["com.google.firebase.messaging.default_notification_icon"]
        )
        assertEquals(
            "@color/funkenwerk_notification_gold",
            metaMap["com.google.firebase.messaging.default_notification_color"]
        )
    }

    @Test
    fun noProductionSourceUsesLauncherMipmapOrLegacySystemIconsInSetSmallIcon() {
        val srcRoot = listOf(File("src/main/java"), File("app/src/main/java"))
            .firstOrNull { it.exists() && it.isDirectory }
        assertNotNull("Expected src/main/java directory", srcRoot)

        val smallIconRegex = Regex("""setSmallIcon\s*\(\s*([^)]+?)\s*\)""")
        var totalSmallIconCalls = 0
        val violations = mutableListOf<String>()

        srcRoot!!.walkTopDown()
            .filter { it.isFile && it.extension == "kt" }
            .forEach { ktFile ->
                val text = ktFile.readText()
                smallIconRegex.findAll(text).forEach { match ->
                    totalSmallIconCalls++
                    val arg = match.groupValues[1].trim()
                    if (arg != "R.drawable.ic_stat_funkenwerk") {
                        violations.add("${ktFile.name}: setSmallIcon($arg)")
                    }
                }
                assertFalse(
                    "${ktFile.name} must never pass R.mipmap.ic_launcher to setSmallIcon",
                    text.contains("setSmallIcon(R.mipmap.ic_launcher")
                )
            }

        assertTrue("Expected at least 15 setSmallIcon calls across services/receivers", totalSmallIconCalls >= 15)
        assertTrue(
            "All setSmallIcon calls must use R.drawable.ic_stat_funkenwerk, violations: $violations",
            violations.isEmpty()
        )
    }

    @Test
    fun stagedGalleryPhotoBlocksRedundantCameraCaptureAndAutoAttachesOnAntigravitySend() = kotlinx.coroutines.runBlocking {
        val context: Context = RuntimeEnvironment.getApplication()
        de.meinseelenfunke.app.di.ServiceLocator.init(context)
        de.meinseelenfunke.app.util.AgDirectInboxManager.resetMemoryCacheForTests()
        de.meinseelenfunke.app.util.FalconCameraController.clearLastCapturedPhoto()

        val bmp = Bitmap.createBitmap(48, 48, Bitmap.Config.ARGB_8888)
        val jpegBytes = java.io.ByteArrayOutputStream().use { out ->
            bmp.compress(Bitmap.CompressFormat.JPEG, 82, out)
            out.toByteArray()
        }
        val b64 = java.util.Base64.getEncoder().encodeToString(jpegBytes)
        val galleryAttachment = de.meinseelenfunke.app.util.PreparedAgAttachment(
            base64Jpeg = b64,
            mimeType = "image/jpeg",
            fileName = "alina_screenshot_weisser_kreis.jpg",
            previewBitmap = null,
            width = 48,
            height = 48
        )
        de.meinseelenfunke.app.util.FalconCameraController.syncFromPreparedAttachment(galleryAttachment)
        assertTrue(de.meinseelenfunke.app.util.AgDirectInboxManager.hasActiveStagedAttachment())

        // 1. System directive & silent context packet
        val directive = de.meinseelenfunke.app.util.FalconClientTools.buildAttachedMediaSystemDirective(galleryAttachment)
        assertNotNull(directive)
        assertTrue(directive!!.contains("WICHTIGER APP-STATUS: Alina hat BEREITS"))
        assertTrue(directive.contains("alina_screenshot_weisser_kreis.jpg"))
        val silentPacket = de.meinseelenfunke.app.util.FalconClientTools.buildSilentClientContextPacket(directive)
        assertTrue(silentPacket.has("realtimeInput"))
        assertFalse(silentPacket.has("clientContent"))
        val legacySilentPacket = de.meinseelenfunke.app.util.FalconClientTools.buildSilentClientContextPacket(
            directive,
            "models/gemini-2.5-flash-native-audio-latest"
        )
        assertFalse(legacySilentPacket.getAsJsonObject("clientContent").get("turnComplete").asBoolean)

        // 2. LLM tries falcon_camera_control(action="take_photo") without force_new_photo -> MUST NOT open camera!
        val takePhotoArgs = com.google.gson.JsonObject().apply {
            addProperty("action", "take_photo")
        }
        val guardRes = de.meinseelenfunke.app.util.FalconClientTools.executeLocalFalconTool(
            context = context,
            toolName = "falcon_camera_control",
            argsElement = takePhotoArgs,
            chatSessionId = "sess-guard-1",
            onEmotionChanged = {},
            onNavigate = {}
        )
        assertEquals("already_attached", guardRes.get("status").asString)
        assertTrue(guardRes.get("has_attached_image").asBoolean)
        assertEquals("alina_screenshot_weisser_kreis.jpg", guardRes.get("file_name").asString)
        // Staged attachment must remain untouched!
        assertTrue(de.meinseelenfunke.app.util.AgDirectInboxManager.hasActiveStagedAttachment())

        // 3. LLM calls falcon_send_to_antigravity even with attach_captured_photo=false -> staged gallery image MUST still be sent!
        val capturedRequests = mutableListOf<de.meinseelenfunke.app.data.api.AgDirectCreateRequest>()
        val fakeApi = object : de.meinseelenfunke.app.data.api.AgDirectApi {
            override suspend fun listInbox(limit: Int) = de.meinseelenfunke.app.data.api.AgDirectInboxListResponse(
                success = true,
                counts = de.meinseelenfunke.app.data.api.AgDirectCountsDto(0, 0, 0),
                items = emptyList()
            )
            override suspend fun createItem(request: de.meinseelenfunke.app.data.api.AgDirectCreateRequest): de.meinseelenfunke.app.data.api.AgDirectCreateResponse {
                capturedRequests.add(request)
                return de.meinseelenfunke.app.data.api.AgDirectCreateResponse(
                    success = true,
                    data = de.meinseelenfunke.app.data.api.AgDirectInboxItemDto(
                        id = "ag_test_staged_1",
                        client_uuid = request.client_uuid,
                        title = request.title,
                        body = request.body,
                        status = "pending",
                        has_attachment = !request.attachment_base64.isNullOrBlank()
                    )
                )
            }
            override suspend fun resolveBlockade(id: String, request: de.meinseelenfunke.app.data.api.AgDirectResolveRequest) =
                de.meinseelenfunke.app.data.api.AgDirectResolveResponse(success = true)
            override suspend fun deleteItem(id: String) = de.meinseelenfunke.app.data.api.AgDirectDeleteResponse(success = true)
            override suspend fun getAttachment(id: String): okhttp3.ResponseBody =
                okhttp3.ResponseBody.Companion.create(null, ByteArray(0))
        }

        val sendArgs = com.google.gson.JsonObject().apply {
            addProperty("title", "App Icon weißer Kreis")
            addProperty("channel", "android")
            addProperty("attach_captured_photo", false)
        }
        val sendRes = de.meinseelenfunke.app.util.FalconClientTools.executeLocalFalconTool(
            context = context,
            toolName = "falcon_send_to_antigravity",
            argsElement = sendArgs,
            chatSessionId = "sess-guard-1",
            onEmotionChanged = {},
            onNavigate = {},
            agApiOverride = fakeApi
        )
        assertEquals("success", sendRes.get("status").asString)
        assertTrue(sendRes.get("photo_attached").asBoolean)
        assertEquals(1, capturedRequests.size)
        assertEquals("alina_screenshot_weisser_kreis.jpg", capturedRequests[0].attachment_name)
        assertEquals(b64, capturedRequests[0].attachment_base64)
        // After send, staged attachment is cleanly cleared
        assertFalse(de.meinseelenfunke.app.util.AgDirectInboxManager.hasActiveStagedAttachment())
    }
}
