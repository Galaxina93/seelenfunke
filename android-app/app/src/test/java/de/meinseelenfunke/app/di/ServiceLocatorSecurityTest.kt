package de.meinseelenfunke.app.di

import android.app.Application
import android.content.Context
import androidx.test.core.app.ApplicationProvider
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNotEquals
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test
import org.junit.runner.RunWith
import org.robolectric.RobolectricTestRunner
import org.robolectric.annotation.Config
import java.util.Base64

@RunWith(RobolectricTestRunner::class)
@Config(sdk = [34], application = Application::class)
class ServiceLocatorSecurityTest {

    private lateinit var context: Context

    @Before
    fun setUp() {
        context = ApplicationProvider.getApplicationContext()
        ServiceLocator.init(context)
    }

    @Test
    fun blocksUntrustedHostsSchemesAndUserInfoSpoofing() {
        val maliciousUrls = listOf(
            "https://evil.com/api/",
            "javascript:alert(1)",
            "file:///data/data/de.meinseelenfunke.app/shared_prefs/seelenfunke_prefs.xml",
            "https://mein-seelenfunke.de@evil.com/api/",
            "https://stage.mein-seelenfunke.de:secret@evil.org/api/",
            "ftp://mein-seelenfunke.de/api/",
            ""
        )

        for (badUrl in maliciousUrls) {
            assertFalse("Expected URL to be blocked in release: $badUrl", ServiceLocator.isAllowedBaseUrl(badUrl, isDebugBuild = false))
            assertFalse("Expected URL to be blocked in debug: $badUrl", ServiceLocator.isAllowedBaseUrl(badUrl, isDebugBuild = true))
            assertEquals(
                "https://stage.mein-seelenfunke.de/api/",
                ServiceLocator.sanitizeBaseUrl(badUrl, isDebugBuild = false)
            )
        }
    }

    @Test
    fun allowsOfficialHttpsDomainsInDebugAndRelease() {
        val validHttpsUrls = listOf(
            "https://mein-seelenfunke.de/api/",
            "https://www.mein-seelenfunke.de/api/",
            "https://stage.mein-seelenfunke.de/api/"
        )

        for (url in validHttpsUrls) {
            assertTrue(ServiceLocator.isAllowedBaseUrl(url, isDebugBuild = false))
            assertTrue(ServiceLocator.isAllowedBaseUrl(url, isDebugBuild = true))
            assertEquals(url, ServiceLocator.sanitizeBaseUrl(url, isDebugBuild = false))
        }
    }

    @Test
    fun blocksCleartextDevHostsInReleaseBuilds() {
        val devCleartextUrls = listOf(
            "http://192.168.188.119/api/",
            "http://192.168.188.123/api/",
            "http://127.0.0.1/api/",
            "http://localhost/api/",
            "http://10.0.2.2/api/"
        )

        for (url in devCleartextUrls) {
            assertFalse("Cleartext dev URL must be blocked in release: $url", ServiceLocator.isAllowedBaseUrl(url, isDebugBuild = false))
            assertEquals(
                "https://stage.mein-seelenfunke.de/api/",
                ServiceLocator.sanitizeBaseUrl(url, isDebugBuild = false)
            )
            assertTrue("Cleartext dev URL should be allowed in debug: $url", ServiceLocator.isAllowedBaseUrl(url, isDebugBuild = true))
            assertEquals(url, ServiceLocator.sanitizeBaseUrl(url, isDebugBuild = true))
        }
    }

    @Test
    fun neverAttachesBearerTokenOverCleartextInRelease() {
        assertFalse(ServiceLocator.shouldAttachBearerToken("http://192.168.188.119/api/user", isDebugBuild = false))
        assertFalse(ServiceLocator.shouldAttachBearerToken("http://127.0.0.1/api/user", isDebugBuild = false))
        assertFalse(ServiceLocator.shouldAttachBearerToken("http://mein-seelenfunke.de/api/user", isDebugBuild = false))
        assertFalse(ServiceLocator.shouldAttachBearerToken("http://mein-seelenfunke.de/api/user", isDebugBuild = true))
        assertFalse(ServiceLocator.shouldAttachBearerToken("https://evil.com/api/user", isDebugBuild = true))
        assertFalse(ServiceLocator.shouldAttachBearerToken("https://mein-seelenfunke.de@evil.com/api/user", isDebugBuild = true))

        assertTrue(ServiceLocator.shouldAttachBearerToken("https://mein-seelenfunke.de/api/user", isDebugBuild = false))
        assertTrue(ServiceLocator.shouldAttachBearerToken("https://stage.mein-seelenfunke.de/api/user", isDebugBuild = false))
        assertTrue(ServiceLocator.shouldAttachBearerToken("http://127.0.0.1/api/user", isDebugBuild = true))
        assertTrue(ServiceLocator.shouldAttachBearerToken("http://192.168.188.119/api/user", isDebugBuild = true))
    }

    @Test
    fun aes256GcmEncryptDecryptRoundtripUsesUniqueIv() {
        val secretJson = """{"kunde":"Alina Steinhauer","betrag_cents":14900,"notiz":"Manufaktur Gifhorn"}"""
        val encrypted1 = ServiceLocator.encryptPayloadForPrefs(secretJson)
        val encrypted2 = ServiceLocator.encryptPayloadForPrefs(secretJson)

        assertTrue(encrypted1.startsWith("enc_v1:"))
        assertTrue(encrypted2.startsWith("enc_v1:"))
        assertNotEquals("Each AES-GCM encryption must generate a fresh random IV", encrypted1, encrypted2)

        assertEquals(secretJson, ServiceLocator.decryptPayloadFromPrefs(encrypted1))
        assertEquals(secretJson, ServiceLocator.decryptPayloadFromPrefs(encrypted2))
    }

    @Test
    fun aes256GcmRejectsTamperedCiphertextWithoutDestroyingKey() {
        val validPlaintext = """[{"id":"task_1","title":"Laserauftrag gravieren"}]"""
        val validEncrypted = ServiceLocator.encryptPayloadForPrefs(validPlaintext)

        val parts = validEncrypted.removePrefix("enc_v1:").split(":", limit = 2)
        val cipherBytes = Base64.getMimeDecoder().decode(parts[1])
        cipherBytes[cipherBytes.lastIndex] = (cipherBytes[cipherBytes.lastIndex].toInt() xor 0x55).toByte()
        val tampered = "enc_v1:${parts[0]}:${Base64.getEncoder().encodeToString(cipherBytes)}"

        assertNull("Tampered AES-GCM payload must fail authentication and return null", ServiceLocator.decryptPayloadFromPrefs(tampered))
        assertEquals(
            "Master key must NOT be wiped when a single tampered ciphertext fails decryption",
            validPlaintext,
            ServiceLocator.decryptPayloadFromPrefs(validEncrypted)
        )
    }

    @Test
    fun getDecryptedPrefTransparentlyMigratesLegacyPlaintextJson() {
        val prefs = context.getSharedPreferences("tasks_widget_prefs", Context.MODE_PRIVATE)
        prefs.edit().clear().commit()

        val legacyPlainJson = """[{"id":"legacy_1","title":"Alte Klartext-Aufgabe"}]"""
        prefs.edit().putString("tasks_cache", legacyPlainJson).commit()

        val readBack = ServiceLocator.getDecryptedPref(prefs, "tasks_cache", null)
        assertEquals(legacyPlainJson, readBack)

        val rawOnDiskAfterRead = prefs.getString("tasks_cache", null)
        assertNotNull(rawOnDiskAfterRead)
        assertTrue("Legacy plaintext JSON must be migrated to enc_v1: on first read", rawOnDiskAfterRead!!.startsWith("enc_v1:"))
        assertFalse(rawOnDiskAfterRead.contains("Alte Klartext-Aufgabe"))
    }
}
