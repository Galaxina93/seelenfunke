package de.meinseelenfunke.app.util

import android.app.Application
import android.content.Context
import androidx.test.core.app.ApplicationProvider
import de.meinseelenfunke.app.di.ServiceLocator
import de.meinseelenfunke.app.ui.navigation.Screen
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test
import org.junit.runner.RunWith
import org.robolectric.RobolectricTestRunner
import org.robolectric.annotation.Config
import java.io.File

@RunWith(RobolectricTestRunner::class)
@Config(sdk = [34], application = Application::class)
class ErrorSentinelAndNavigationTest {

    private lateinit var context: Context

    @Before
    fun setUp() {
        context = ApplicationProvider.getApplicationContext()
        ServiceLocator.init(context)
        ErrorSentinelReporter.clearQueue(context)
        NavigationBridge.consumePendingTarget()
    }

    @Test
    fun scrubsBearerTokensEncryptedBlobsApiKeysPasswordsAndSanctumTokens() {
        val rawLog = """
            Request failed: Authorization: Bearer 42|SuperSecretSanctumTokenAbcDef123456789
            Cached pref=enc_v1:aGVsbG8gd29ybGQ=:Y2lwaGVydGV4dDEyMzQ1Ng==
            Gemini key=AIzaSySecretGeminiApiKeyValue123456789
            Query https://stage.mein-seelenfunke.de/api/sync?token=QueryLeak98765&api_key=KeyLeak54321
            Payload {"password":"MyRawPassword!2026","bare_token":"17|BareSanctumTokenValueAbcDef987654321"}
        """.trimIndent()

        val scrubbed = ErrorSentinelReporter.scrubSecrets(rawLog)

        assertFalse(scrubbed.contains("SuperSecretSanctumTokenAbcDef123456789"))
        assertFalse(scrubbed.contains("Y2lwaGVydGV4dDEyMzQ1Ng=="))
        assertFalse(scrubbed.contains("SecretGeminiApiKeyValue123456789"))
        assertFalse(scrubbed.contains("QueryLeak98765"))
        assertFalse(scrubbed.contains("KeyLeak54321"))
        assertFalse(scrubbed.contains("MyRawPassword!2026"))
        assertFalse(scrubbed.contains("BareSanctumTokenValueAbcDef987654321"))
        assertTrue(scrubbed.contains("[REDACTED]"))
    }

    @Test
    fun sentinelQueuePersistsEncryptedOnDiskAndWipesOnClearQueue() {
        ServiceLocator.clearAuthToken()
        ErrorSentinelReporter.captureError(
            context = context,
            tag = "UnitTestComponent",
            message = "Testausnahme mit Passwort password=Geheim123"
        )

        val queueFile = File(context.filesDir, "error_sentinel_queue.json")
        assertTrue("Queue file should be written to filesDir", queueFile.exists())

        val onDisk = queueFile.readText(Charsets.UTF_8)
        assertTrue("Sentinel queue on disk must be encrypted with enc_v1:", onDisk.startsWith("enc_v1:"))
        assertFalse(onDisk.contains("UnitTestComponent"))
        assertFalse(onDisk.contains("Geheim123"))

        ErrorSentinelReporter.clearQueue(context)
        assertFalse("clearQueue must remove error_sentinel_queue.json", queueFile.exists())
    }

    @Test
    fun resolvesFinancesDeterministicallyForBothAdminAndEmployee() {
        assertEquals(
            Screen.Finances,
            NavigationBridge.resolveScreen(NavigationBridge.NavTarget.FINANCES, isAdmin = true)
        )
        assertEquals(
            2,
            NavigationBridge.resolvePageIndex(NavigationBridge.NavTarget.FINANCES, isAdmin = true)
        )

        assertEquals(
            Screen.Finances,
            NavigationBridge.resolveScreen(NavigationBridge.NavTarget.FINANCES, isAdmin = false)
        )
        assertEquals(
            1,
            NavigationBridge.resolvePageIndex(NavigationBridge.NavTarget.FINANCES, isAdmin = false)
        )
    }

    @Test
    fun resolvesOrdersToOrdersForAdminAndSafeZentrumFallbackForEmployee() {
        assertEquals(
            Screen.Orders,
            NavigationBridge.resolveScreen(NavigationBridge.NavTarget.ORDERS, isAdmin = true)
        )
        assertEquals(
            1,
            NavigationBridge.resolvePageIndex(NavigationBridge.NavTarget.ORDERS, isAdmin = true)
        )

        assertEquals(
            Screen.Zentrum,
            NavigationBridge.resolveScreen(NavigationBridge.NavTarget.ORDERS, isAdmin = false)
        )
        assertEquals(
            0,
            NavigationBridge.resolvePageIndex(NavigationBridge.NavTarget.ORDERS, isAdmin = false)
        )
    }

    @Test
    fun parsesDeepLinkTargetsAndConsumesPendingNavigationAtomically() {
        assertEquals(NavigationBridge.NavTarget.ORDERS, NavigationBridge.parseTarget("orders"))
        assertEquals(NavigationBridge.NavTarget.ORDERS, NavigationBridge.parseTarget("Bestellungen"))
        assertEquals(NavigationBridge.NavTarget.FINANCES, NavigationBridge.parseTarget("finances"))
        assertEquals(NavigationBridge.NavTarget.FINANCES, NavigationBridge.parseTarget("buchhaltung"))
        assertEquals(NavigationBridge.NavTarget.ORGANIZER, NavigationBridge.parseTarget("email"))
        assertEquals(NavigationBridge.NavTarget.CHAT, NavigationBridge.parseTarget("agenten"))
        assertEquals(NavigationBridge.NavTarget.SETTINGS, NavigationBridge.parseTarget("settings"))

        NavigationBridge.triggerNavigation(NavigationBridge.NavTarget.ORDERS, subTab = 0)
        assertEquals(NavigationBridge.NavTarget.ORDERS, NavigationBridge.consumePendingTarget())
        assertNull("consumePendingTarget must clear pending state after consumption", NavigationBridge.consumePendingTarget())
    }
}
