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
        val devApiHosts = setOf("127.0.0.1", "localhost", "10.0.2.2", "192.168.188.119")
        val tapoSmartPlugHosts = setOf("192.168.188.109", "192.168.188.110", "192.168.188.123")

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

        for (plugHost in tapoSmartPlugHosts) {
            assertTrue(
                "Tapo KLAP v2 Smart-Home plug $plugHost must be allowed in release LAN config",
                productionDomains.contains(plugHost)
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

    @Test
    fun seelenfunkeDomainPinsLetsEncryptRootX1AndRootYrCertificatesAndVerifiesChain() {
        val doc = parseXml(resolveProjectFile("src/main/res/xml/network_security_config.xml"))
        val domainConfigs = doc.getElementsByTagName("domain-config")
        var foundSfConfig: Element? = null
        for (i in 0 until domainConfigs.length) {
            val dc = domainConfigs.item(i) as Element
            val domains = dc.getElementsByTagName("domain")
            for (j in 0 until domains.length) {
                val d = domains.item(j) as Element
                if (d.textContent.trim() == "mein-seelenfunke.de") {
                    assertEquals("true", d.getAttribute("includeSubdomains"))
                    foundSfConfig = dc
                }
            }
        }
        assertNotNull("Expected domain-config for mein-seelenfunke.de", foundSfConfig)
        assertEquals("false", foundSfConfig!!.getAttribute("cleartextTrafficPermitted"))

        val certSrcs = mutableSetOf<String>()
        val certNodes = foundSfConfig.getElementsByTagName("certificates")
        for (i in 0 until certNodes.length) {
            certSrcs.add((certNodes.item(i) as Element).getAttribute("src"))
        }
        assertTrue(certSrcs.contains("system"))
        assertTrue(certSrcs.contains("@raw/isrg_root_x1"))
        assertTrue(certSrcs.contains("@raw/isrg_root_yr"))

        val cf = java.security.cert.CertificateFactory.getInstance("X.509")
        val x1Cert = resolveProjectFile("src/main/res/raw/isrg_root_x1.pem").inputStream().use {
            cf.generateCertificate(it) as java.security.cert.X509Certificate
        }
        val yrCert = resolveProjectFile("src/main/res/raw/isrg_root_yr.pem").inputStream().use {
            cf.generateCertificate(it) as java.security.cert.X509Certificate
        }
        assertTrue(x1Cert.subjectX500Principal.name.contains("ISRG Root X1"))
        assertTrue(yrCert.subjectX500Principal.name.contains("Root YR"))
        // Cryptographically verify Root YR is signed by ISRG Root X1
        yrCert.verify(x1Cert.publicKey)
    }

    @Test
    fun sslCertPathFailureIsRetryableAndFormatsCleanGermanMessageWithoutJavaClassLeak() {
        val certEx = java.security.cert.CertPathValidatorException("Trust anchor for certification path not found.")
        val sslEx = javax.net.ssl.SSLHandshakeException(
            "java.security.cert.CertPathValidatorException: Trust anchor for certification path not found."
        ).apply { initCause(certEx) }

        assertTrue(FunkiraLiveViewModel.isSslOrCertificateFailure(sslEx))
        assertTrue(FunkiraLiveViewModel.isRetryableCredentialIoFailure(sslEx))

        val userMsg = FunkiraLiveViewModel.formatCredentialFailureForUser(sslEx)
        assertEquals(FunkiraLiveViewModel.FRIENDLY_SSL_INTERRUPTED_MESSAGE, userMsg)
        assertFalse(userMsg.contains("java.security"))
        assertFalse(userMsg.contains("CertPathValidator"))
        assertFalse(userMsg.contains(" – "))
    }
}
