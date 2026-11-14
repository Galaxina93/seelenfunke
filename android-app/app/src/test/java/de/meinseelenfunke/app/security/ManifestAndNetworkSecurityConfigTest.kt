package de.meinseelenfunke.app.security

import de.meinseelenfunke.app.ui.screens.FunkiraLiveViewModel
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertTrue
import org.junit.Test
import org.w3c.dom.Document
import org.w3c.dom.Element
import java.io.File
import javax.xml.parsers.DocumentBuilderFactory

class ManifestAndNetworkSecurityConfigTest {

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
    fun applicationDisablesBackupAndCleartextTraffic() {
        val doc = parseXml(resolveProjectFile("src/main/AndroidManifest.xml"))
        val appNodes = doc.getElementsByTagName("application")
        assertEquals(1, appNodes.length)
        val appEl = appNodes.item(0) as Element

        assertEquals("false", appEl.getAttributeNS(androidNs, "allowBackup"))
        assertEquals("false", appEl.getAttributeNS(androidNs, "usesCleartextTraffic"))
        assertEquals("@xml/network_security_config", appEl.getAttributeNS(androidNs, "networkSecurityConfig"))
    }

    @Test
    fun sensitiveServicesAndFileProviderAreNotExported() {
        val doc = parseXml(resolveProjectFile("src/main/AndroidManifest.xml"))
        val mustRemainPrivate = setOf(
            ".services.WakeWordService",
            ".services.MusicPlaybackService",
            ".services.MyFirebaseMessagingService"
        )

        val services = doc.getElementsByTagName("service")
        val foundServices = mutableMapOf<String, String>()
        for (i in 0 until services.length) {
            val el = services.item(i) as Element
            val name = el.getAttributeNS(androidNs, "name")
            val exported = el.getAttributeNS(androidNs, "exported")
            foundServices[name] = exported
        }

        for (svc in mustRemainPrivate) {
            assertEquals("Service $svc must declare android:exported=\"false\"", "false", foundServices[svc])
        }

        val providers = doc.getElementsByTagName("provider")
        for (i in 0 until providers.length) {
            val el = providers.item(i) as Element
            if (el.getAttributeNS(androidNs, "authorities") == "de.meinseelenfunke.app.fileprovider") {
                assertEquals("false", el.getAttributeNS(androidNs, "exported"))
            }
        }
    }

    @Test
    fun baseNetworkSecurityConfigForbidsCleartextTraffic() {
        val doc = parseXml(resolveProjectFile("src/main/res/xml/network_security_config.xml"))
        val baseConfigs = doc.getElementsByTagName("base-config")
        assertEquals(1, baseConfigs.length)
        val baseEl = baseConfigs.item(0) as Element
        assertEquals("false", baseEl.getAttribute("cleartextTrafficPermitted"))
    }

    @Test
    fun devApiCleartextDomainsExistStrictlyInsideDebugOverrides() {
        val doc = parseXml(resolveProjectFile("src/main/res/xml/network_security_config.xml"))
        val devApiHosts = setOf("127.0.0.1", "localhost", "10.0.2.2", "192.168.188.119", "192.168.188.123")

        val productionDomains = mutableSetOf<String>()
        val debugOverrideDomains = mutableSetOf<String>()

        val allDomains = doc.getElementsByTagName("domain")
        for (i in 0 until allDomains.length) {
            val domainEl = allDomains.item(i) as Element
            val host = domainEl.textContent.trim()
            var insideDebugOverrides = false
            var parent = domainEl.parentNode
            while (parent != null) {
                if (parent.nodeName == "debug-overrides") {
                    insideDebugOverrides = true
                    break
                }
                parent = parent.parentNode
            }
            if (insideDebugOverrides) {
                debugOverrideDomains.add(host)
            } else {
                productionDomains.add(host)
            }
        }

        for (devHost in devApiHosts) {
            assertFalse(
                "API cleartext host $devHost MUST NOT appear outside <debug-overrides>!",
                productionDomains.contains(devHost)
            )
            assertTrue(
                "Dev API host $devHost should be configured inside <debug-overrides>",
                debugOverrideDomains.contains(devHost)
            )
        }
    }

    @Test
    fun funkiraLiveWebSocketRejectsCleartextWsInRelease() {
        assertFalse(
            FunkiraLiveViewModel.isTrustedLiveWebSocketHost(
                wsUrl = "ws://192.168.188.119/ws",
                baseHost = "192.168.188.119",
                isDebugBuild = false
            )
        )
        assertTrue(
            FunkiraLiveViewModel.isTrustedLiveWebSocketHost(
                wsUrl = "ws://192.168.188.119/ws",
                baseHost = "192.168.188.119",
                isDebugBuild = true
            )
        )
        assertFalse(
            FunkiraLiveViewModel.isTrustedLiveWebSocketHost(
                wsUrl = "wss://evil.com/ws",
                baseHost = "stage.mein-seelenfunke.de",
                isDebugBuild = false
            )
        )
        assertTrue(
            FunkiraLiveViewModel.isTrustedLiveWebSocketHost(
                wsUrl = "wss://stage.mein-seelenfunke.de/ws",
                baseHost = "stage.mein-seelenfunke.de",
                isDebugBuild = false
            )
        )
    }
}
