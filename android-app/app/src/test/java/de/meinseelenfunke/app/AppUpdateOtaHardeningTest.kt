package de.meinseelenfunke.app

import android.content.Context
import de.meinseelenfunke.app.util.AppUpdateManager
import de.meinseelenfunke.app.util.FunkenwerkAlarmManager
import de.meinseelenfunke.app.util.OtaReleaseManifest
import de.meinseelenfunke.app.util.OtaUpdateState
import okhttp3.OkHttpClient
import okhttp3.mockwebserver.MockResponse
import okhttp3.mockwebserver.MockWebServer
import okio.Buffer
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
import org.robolectric.RuntimeEnvironment
import org.robolectric.annotation.Config
import java.io.ByteArrayOutputStream
import java.io.File
import java.security.MessageDigest
import java.util.concurrent.TimeUnit
import java.util.zip.ZipEntry
import java.util.zip.ZipOutputStream

@RunWith(RobolectricTestRunner::class)
@Config(sdk = [34])
class AppUpdateOtaHardeningTest {

    private lateinit var context: Context
    private lateinit var server: MockWebServer

    @Before
    fun setUp() {
        context = RuntimeEnvironment.getApplication()
        server = MockWebServer()
        server.start()

        AppUpdateManager.resetOnLogout(context)
        AppUpdateManager.httpClientOverride = OkHttpClient.Builder()
            .connectTimeout(5, TimeUnit.SECONDS)
            .readTimeout(5, TimeUnit.SECONDS)
            .followRedirects(false)
            .followSslRedirects(false)
            .build()
        AppUpdateManager.baseUrlOverrideForTests = server.url("/api/").toString()
        AppUpdateManager.authTokenOverrideForTests = "test-admin-bearer-token-12345"
    }

    @After
    fun tearDown() {
        AppUpdateManager.httpClientOverride = null
        AppUpdateManager.baseUrlOverrideForTests = null
        AppUpdateManager.authTokenOverrideForTests = null
        AppUpdateManager.resetOnLogout(context)
        server.shutdown()
    }

    @Test
    fun startupAutoCheck_detectsUpdate_supportsDismissLater_andRePromptsOnNextAppStart() {
        val validSha = "a".repeat(64)
        val manifestJson = """
            {
              "success": true,
              "has_release": true,
              "data": {
                "package_name": "${context.packageName}",
                "version_code": 999,
                "version_name": "2.5.0",
                "sha256": "$validSha",
                "file_size": 1048576,
                "file_name": "Funkenwerk-v2.5.0-b999.apk",
                "release_notes": "Schnell-Update beim Start",
                "published_at": "2026-11-14T17:00:00+01:00"
              }
            }
        """.trimIndent()

        server.enqueue(MockResponse().setResponseCode(200).setBody(manifestJson))

        // 1. App start triggers silent check -> Available & promptDismissed == false
        AppUpdateManager.onAppStartCheck(context)
        val state1 = awaitState { it is OtaUpdateState.Available } as OtaUpdateState.Available
        assertEquals(999L, state1.manifest.versionCode)
        assertEquals("2.5.0", state1.manifest.versionName)
        assertEquals("Schnell-Update beim Start", state1.manifest.releaseNotes)
        assertFalse(AppUpdateManager.promptDismissed.value)

        // Verify Authorization: Bearer header (never ?token= in URL)
        val req1 = server.takeRequest(2, TimeUnit.SECONDS)
        assertNotNull(req1)
        assertEquals("Bearer test-admin-bearer-token-12345", req1!!.getHeader("Authorization"))
        assertFalse(req1.path!!.contains("token="))

        // 2. User taps "Später" -> prompt hides for current session
        AppUpdateManager.dismissStartupPrompt()
        assertTrue(AppUpdateManager.promptDismissed.value)

        // 3. Next app start -> resets dismissal & checks again -> prompt shows again!
        server.enqueue(MockResponse().setResponseCode(200).setBody(manifestJson))
        AppUpdateManager.onAppStartCheck(context)
        val req2 = server.takeRequest(2, TimeUnit.SECONDS)
        assertNotNull(req2)
        awaitCondition { !AppUpdateManager.promptDismissed.value && AppUpdateManager.state.value is OtaUpdateState.Available }
        assertFalse(AppUpdateManager.promptDismissed.value)
    }

    @Test
    fun startupAutoCheck_staysSilentWhenAlreadyUpToDate() {
        val (_, installedCode) = AppUpdateManager.getInstalledVersionInfo(context)
        val upToDateJson = """
            {
              "success": true,
              "has_release": true,
              "data": {
                "package_name": "${context.packageName}",
                "version_code": $installedCode,
                "version_name": "1.6.0",
                "sha256": "${"b".repeat(64)}",
                "file_size": 1048576,
                "file_name": "Funkenwerk.apk",
                "published_at": "2026-11-14T17:00:00+01:00"
              }
            }
        """.trimIndent()

        server.enqueue(MockResponse().setResponseCode(200).setBody(upToDateJson))
        AppUpdateManager.onAppStartCheck(context)

        val st = awaitState { it is OtaUpdateState.UpToDate }
        assertTrue(st is OtaUpdateState.UpToDate)
        assertNull(AppUpdateManager.getLastManifest())
    }

    @Test
    fun downloadGates_blockHttpRedirects_nonZipPayloads_sha256Mismatch_oversizedStream_andRogueCert() {
        // 1. Block 302 Redirects (MITM / Open-Redirect protection)
        val dummyManifest = OtaReleaseManifest(
            versionCode = 500,
            versionName = "2.0.0",
            sha256Hex = "c".repeat(64),
            fileSize = 600_000L,
            fileName = "Funkenwerk-v2.0.0.apk",
            publishedAt = "2026-11-14T17:00:00+01:00"
        )
        server.enqueue(
            MockResponse()
                .setResponseCode(302)
                .addHeader("Location", "https://evil.example.com/trojan.apk")
        )
        AppUpdateManager.downloadAndInstall(context, dummyManifest)
        val redirectErr = awaitState { it is OtaUpdateState.Error } as OtaUpdateState.Error
        assertTrue(redirectErr.message.contains("HTTP 302"))

        // 2. Block non-ZIP binary garbage even if byte length matches
        val nonZipBytes = ByteArray(600_000) { 0x41 }
        val nonZipSha = sha256Hex(nonZipBytes)
        val nonZipManifest = dummyManifest.copy(sha256Hex = nonZipSha, fileSize = nonZipBytes.size.toLong())
        server.enqueue(MockResponse().setResponseCode(200).setBody(Buffer().write(nonZipBytes)))

        AppUpdateManager.resetOnLogout(context)
        Thread.sleep(50)
        AppUpdateManager.downloadAndInstall(context, nonZipManifest)
        val formatErr = awaitState { it is OtaUpdateState.Error } as OtaUpdateState.Error
        assertEquals("Ungültiges APK-Format.", formatErr.message)
        assertZeroLeftoverUpdateFiles()

        // 3. Block SHA-256 mismatch on valid ZIP header
        val validZipBytes = buildValidZipBytes(600_000)
        val wrongShaManifest = dummyManifest.copy(
            sha256Hex = "f".repeat(64),
            fileSize = validZipBytes.size.toLong()
        )
        server.enqueue(MockResponse().setResponseCode(200).setBody(Buffer().write(validZipBytes)))

        AppUpdateManager.resetOnLogout(context)
        Thread.sleep(50)
        AppUpdateManager.downloadAndInstall(context, wrongShaManifest)
        val shaErr = awaitState { it is OtaUpdateState.Error } as OtaUpdateState.Error
        assertTrue(shaErr.message.contains("SHA-256"))
        assertZeroLeftoverUpdateFiles()

        // 4. Block oversized stream (> manifest.fileSize + 1024) mid-flight
        val smallExpectedManifest = dummyManifest.copy(
            sha256Hex = sha256Hex(validZipBytes),
            fileSize = 530_000L // stream will send 600KB+ -> exceeds 530KB + 1024B
        )
        server.enqueue(MockResponse().setResponseCode(200).setBody(Buffer().write(validZipBytes)))

        AppUpdateManager.resetOnLogout(context)
        Thread.sleep(50)
        AppUpdateManager.downloadAndInstall(context, smallExpectedManifest)
        val sizeAbortErr = awaitState { it is OtaUpdateState.Error } as OtaUpdateState.Error
        assertTrue(sizeAbortErr.message.isNotEmpty())
        assertZeroLeftoverUpdateFiles()

        // 5. Block ZIP with valid size + valid SHA-256 that lacks pinned 4096-bit RSA certificate (Gate 4)
        val matchingZipManifest = dummyManifest.copy(
            sha256Hex = sha256Hex(validZipBytes),
            fileSize = validZipBytes.size.toLong()
        )
        server.enqueue(MockResponse().setResponseCode(200).setBody(Buffer().write(validZipBytes)))

        AppUpdateManager.resetOnLogout(context)
        Thread.sleep(50)
        AppUpdateManager.downloadAndInstall(context, matchingZipManifest)
        val certErr = awaitState { it is OtaUpdateState.Error } as OtaUpdateState.Error
        assertTrue(certErr.message.contains("Zertifikat"))
        assertZeroLeftoverUpdateFiles()
    }

    @Test
    fun cleanupCacheAsync_purgesInstalledAndPartFiles_whileKeepingFreshPendingUpgrade() {
        val (_, installedCode) = AppUpdateManager.getInstalledVersionInfo(context)
        val dir = File(context.cacheDir, "apk_updates").apply { mkdirs() }
        val stalePart = File(dir, "funkenwerk_ota_99.apk.part").apply { writeText("part") }
        val alreadyInstalled = File(dir, "Funkenwerk_v$installedCode.apk").apply { writeText("old") }
        val pendingNewer = File(dir, "Funkenwerk_v${installedCode + 5}.apk").apply { writeText("new") }

        AppUpdateManager.cleanupCacheAsync(context)
        awaitCondition { !stalePart.exists() && !alreadyInstalled.exists() }

        assertFalse(stalePart.exists())
        assertFalse(alreadyInstalled.exists())
        assertTrue(pendingNewer.exists())

        // Logout wipes everything including pendingNewer
        AppUpdateManager.resetOnLogout(context)
        awaitCondition { !pendingNewer.exists() }
        assertFalse(pendingNewer.exists())
    }

    @Test
    fun timerPresetsSelfHealing_repairsCorruptedNullIdsFromLegacyR8Storage() {
        val corruptedJson = """[{"a":null,"b":300},{"id":"","label":"","duration_seconds":600}]"""
        val healed = FunkenwerkAlarmManager.parseTimersJsonSelfHealing(corruptedJson)
        assertTrue(healed.isNotEmpty())
        healed.forEach { item ->
            assertNotNull(item.id)
            assertTrue(item.id.isNotBlank())
            assertNotNull(item.label)
            assertTrue(item.durationSeconds > 0)
        }
    }

    private fun assertZeroLeftoverUpdateFiles() {
        val dir = File(context.cacheDir, "apk_updates")
        System.gc()
        val nonEmptyRemaining = dir.listFiles()?.filter { it.exists() && it.length() > 0L }.orEmpty()
        assertTrue("Expected 0 non-empty leftover update files, found: $nonEmptyRemaining", nonEmptyRemaining.isEmpty())
    }

    private fun buildValidZipBytes(minSizeBytes: Int): ByteArray {
        val baos = ByteArrayOutputStream()
        ZipOutputStream(baos).use { zos ->
            val entry = ZipEntry("classes.dex")
            zos.putNextEntry(entry)
            // Incompressible deterministic PRNG bytes so deflated ZIP exceeds minSizeBytes
            val buf = ByteArray(minSizeBytes + 16384)
            var s = 0x13579BDF
            for (i in buf.indices) {
                s = s xor (s shl 13)
                s = s xor (s ushr 17)
                s = s xor (s shl 5)
                buf[i] = s.toByte()
            }
            zos.write(buf)
            zos.closeEntry()
        }
        return baos.toByteArray()
    }

    private fun sha256Hex(bytes: ByteArray): String {
        return MessageDigest.getInstance("SHA-256")
            .digest(bytes)
            .joinToString("") { "%02x".format(it) }
    }

    private fun awaitState(timeoutMs: Long = 3000L, predicate: (OtaUpdateState) -> Boolean): OtaUpdateState {
        val deadline = System.currentTimeMillis() + timeoutMs
        while (System.currentTimeMillis() < deadline) {
            val cur = AppUpdateManager.state.value
            if (predicate(cur)) return cur
            Thread.sleep(25)
        }
        val finalState = AppUpdateManager.state.value
        assertTrue("Timed out waiting for expected state; actual=$finalState", predicate(finalState))
        return finalState
    }

    private fun awaitCondition(timeoutMs: Long = 3000L, condition: () -> Boolean) {
        val deadline = System.currentTimeMillis() + timeoutMs
        while (System.currentTimeMillis() < deadline) {
            if (condition()) return
            Thread.sleep(25)
        }
        assertTrue("Timed out waiting for condition", condition())
    }
}
