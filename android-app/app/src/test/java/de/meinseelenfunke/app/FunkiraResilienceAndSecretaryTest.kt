package de.meinseelenfunke.app

import android.content.pm.ServiceInfo
import de.meinseelenfunke.app.services.FunkiraPhoneConciergeService
import de.meinseelenfunke.app.services.StandalonePhoneSecretaryEngine
import de.meinseelenfunke.app.ui.screens.FunkiraLiveViewModel
import de.meinseelenfunke.app.util.ErrorSentinelReporter
import de.meinseelenfunke.app.util.PendingPhoneSecretaryNote
import de.meinseelenfunke.app.util.PhoneConciergeManager
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

class FunkiraResilienceAndSecretaryTest {

    @Test
    fun trustedLiveWebSocketHost_allowsPortalBridgeSubdomainsAndDirectGemini_rejectsUntrustedAndCleartext() {
        assertTrue(
            FunkiraLiveViewModel.isTrustedLiveWebSocketHost(
                "wss://stage.mein-seelenfunke.de/gemini-live",
                "stage.mein-seelenfunke.de"
            )
        )
        assertTrue(
            FunkiraLiveViewModel.isTrustedLiveWebSocketHost(
                "wss://api-live-bridge.mein-seelenfunke.de/gemini-live",
                "stage.mein-seelenfunke.de"
            )
        )
        assertTrue(
            FunkiraLiveViewModel.isTrustedLiveWebSocketHost(
                "wss://mein-seelenfunke.de/gemini-live",
                "stage.mein-seelenfunke.de"
            )
        )
        assertTrue(
            FunkiraLiveViewModel.isTrustedLiveWebSocketHost(
                "wss://generativelanguage.googleapis.com/ws/google.ai.generativelanguage.v1beta.GenerativeService.BidiGenerateContent?key=AIzaSyTest1234567890",
                "stage.mein-seelenfunke.de"
            )
        )

        // Block untrusted external hosts & cleartext ws:// on public domains
        assertFalse(
            FunkiraLiveViewModel.isTrustedLiveWebSocketHost(
                "wss://evil-proxy.example.com/gemini-live",
                "stage.mein-seelenfunke.de"
            )
        )
        assertFalse(
            FunkiraLiveViewModel.isTrustedLiveWebSocketHost(
                "ws://stage.mein-seelenfunke.de/gemini-live",
                "stage.mein-seelenfunke.de"
            )
        )
        assertFalse(
            FunkiraLiveViewModel.isTrustedLiveWebSocketHost(
                "https://stage.mein-seelenfunke.de/gemini-live",
                "stage.mein-seelenfunke.de"
            )
        )
    }

    @Test
    fun buildDirectGeminiWsUrl_validatesKeyLengthAndBuildsEndpoint() {
        assertNull(FunkiraLiveViewModel.buildDirectGeminiWsUrl(""))
        assertNull(FunkiraLiveViewModel.buildDirectGeminiWsUrl("short"))
        val url = FunkiraLiveViewModel.buildDirectGeminiWsUrl("AIzaSyValidTestKey1234567890")
        assertNotNull(url)
        assertTrue(url!!.startsWith("wss://generativelanguage.googleapis.com/ws/"))
        assertTrue(url.contains("key=AIzaSyValidTestKey1234567890"))
    }

    @Test
    fun standaloneSecretaryPromptAndTools_enforceGermanFriendlyReassuranceAndZeroPiiSingleTool() {
        val prompt = StandalonePhoneSecretaryEngine.buildSecretarySystemInstruction(
            callerName = "Jan Steinhauer",
            greetingName = "Papa",
            relationType = "Vater",
            contactInstructions = "Fragt manchmal nach Sonntags-Kaffee."
        )

        assertTrue(prompt.contains("AUSSCHLIESSLICH AUF DEUTSCH"))
        assertTrue(prompt.contains("Papa"))
        assertTrue(prompt.contains("Sekretärin"))
        assertTrue(prompt.contains("Nachricht sofort bekommen wird"))
        assertTrue(prompt.contains("ZERO-PII SANDBOX"))
        // Enforce Clean-Design rule: zero en-dashes surrounded by spaces
        assertFalse(prompt.contains(" \u2013 "))

        val tools = StandalonePhoneSecretaryEngine.buildSecretaryToolsJson()
        assertEquals(1, tools.size())
        val decls = tools.get(0).asJsonObject.getAsJsonArray("functionDeclarations")
        assertEquals(1, decls.size())
        assertEquals("phone_secretary_finish", decls.get(0).asJsonObject.get("name").asString)

        val fallbackSpoken = StandalonePhoneSecretaryEngine.buildFallbackSummaryFromTranscript(
            "  Hallo Funkira, sag Alina bitte ich komme um 16 Uhr vorbei!  ",
            "Papa"
        )
        assertEquals("Hallo Funkira, sag Alina bitte ich komme um 16 Uhr vorbei!", fallbackSpoken)

        val fallbackEmpty = StandalonePhoneSecretaryEngine.buildFallbackSummaryFromTranscript(" ", "Papa")
        assertTrue(fallbackEmpty.contains("Papa hat angerufen"))
    }

    @Test
    fun errorSentinelScrubber_redactsBearerGeminiKeyTokensAndEncryptedPayloads() {
        val raw =
            "Error at wss://generativelanguage.googleapis.com/ws?key=AIzaSySuperSecretKey123456789 " +
                "Auth: Bearer 77|sanctumSecretTokenAbcdefghijklmnop token=liveTok99 enc_v1:IV123:CIPHER456"

        val scrubbed = ErrorSentinelReporter.scrubSecrets(raw)
        assertFalse(scrubbed.contains("AIzaSySuperSecretKey123456789"))
        assertFalse(scrubbed.contains("sanctumSecretTokenAbcdefghijklmnop"))
        assertFalse(scrubbed.contains("liveTok99"))
        assertFalse(scrubbed.contains("IV123:CIPHER456"))
        assertTrue(scrubbed.contains("[REDACTED]"))

        val fp = ErrorSentinelReporter.computeFingerprint("phone_secretary_ws", "Timeout")
        assertEquals(64, fp.length)
    }

    @Test
    fun phoneConciergeManager_normalizesNumbersAndEncryptsPendingSecretaryNotes() {
        assertEquals(
            PhoneConciergeManager.normalizePhoneDigits("+49 176 57793016"),
            PhoneConciergeManager.normalizePhoneDigits("0176-57793016")
        )
        assertEquals("57793016", PhoneConciergeManager.extractMatchSuffix("0176 / 57793016"))
        assertEquals("491761112223", PhoneConciergeManager.normalizePhoneDigits("01761112223 / 05371998877"))
        assertEquals("hoch", PhoneConciergeManager.normalizeUrgency("dringend"))
        assertEquals("niedrig", PhoneConciergeManager.normalizeUrgency("LOW"))
        assertEquals("mittel", PhoneConciergeManager.normalizeUrgency(null))

        val sampleNote = PendingPhoneSecretaryNote(
            callId = "call-sec-99",
            contactId = null,
            callerName = "Jan Steinhauer",
            greetingName = "Papa",
            callerPhone = "+4917657793016",
            summary = "Bitte ruf mich heute Abend kurz wegen Sonntag zurück.",
            urgency = "mittel",
            callbackRequested = true,
            createdAtIso = "2026-11-14T10:30:00Z"
        )

        val encrypted = PhoneConciergeManager.serializeEncryptedPendingNotes(listOf(sampleNote))
        assertTrue(encrypted.startsWith("enc_v1:"))
        assertFalse(encrypted.contains("Sonntag"))
        assertFalse(encrypted.contains("Steinhauer"))

        val restored = PhoneConciergeManager.deserializeEncryptedPendingNotes(encrypted)
        assertEquals(1, restored.size)
        assertEquals("call-sec-99", restored[0].callId)
        assertEquals("Papa", restored[0].greetingName)
        assertEquals("Bitte ruf mich heute Abend kurz wegen Sonntag zurück.", restored[0].summary)
    }

    @Test
    fun standaloneSecretaryWirePayloads_useGemini31RealtimeInputTextAndValidToolResponseSchema() {
        val kickoffObj = StandalonePhoneSecretaryEngine.buildKickoffPacketJson("Papa")
        assertTrue(kickoffObj.has("realtimeInput"))
        assertFalse(kickoffObj.has("clientContent"))
        val kickoffText = kickoffObj.getAsJsonObject("realtimeInput").get("text").asString
        assertTrue(kickoffText.contains("Papa"))
        assertTrue(kickoffText.contains("Assistentin"))
        assertFalse(kickoffText.contains(" \u2013 "))

        val toolRespObj = StandalonePhoneSecretaryEngine.buildToolResponsePacketJson(
            fnId = "fc_live_42",
            fnName = "phone_secretary_finish",
            greetingName = "Papa"
        )
        val responses = toolRespObj.getAsJsonObject("toolResponse").getAsJsonArray("functionResponses")
        assertEquals(1, responses.size())
        val first = responses.get(0).asJsonObject
        assertEquals("fc_live_42", first.get("id").asString)
        assertEquals("phone_secretary_finish", first.get("name").asString)
        val innerResponse = first.getAsJsonObject("response")
        assertEquals("success", innerResponse.get("status").asString)
        assertTrue(innerResponse.get("note").asString.contains("Papa"))
        assertTrue(innerResponse.get("note").asString.contains("Alina"))
        assertFalse(innerResponse.get("note").asString.contains(" \u2013 "))
    }

    @Test
    fun conciergeForegroundServiceType_preventsAndroid14BackgroundMicCrashUntilCallAccepted() {
        // Android 14+ (API 34): During RINGING (before OFFHOOK), must NOT request MICROPHONE FGS type
        assertEquals(
            ServiceInfo.FOREGROUND_SERVICE_TYPE_PHONE_CALL,
            FunkiraPhoneConciergeService.resolveForegroundServiceType(
                sdkInt = 34,
                includeMicrophone = false,
                hasMicPermission = true
            )
        )

        // Android 14+ (API 34): After acceptIncomingCallSafe() (OFFHOOK) + RECORD_AUDIO granted -> PHONE_CALL | MICROPHONE
        assertEquals(
            ServiceInfo.FOREGROUND_SERVICE_TYPE_PHONE_CALL or ServiceInfo.FOREGROUND_SERVICE_TYPE_MICROPHONE,
            FunkiraPhoneConciergeService.resolveForegroundServiceType(
                sdkInt = 34,
                includeMicrophone = true,
                hasMicPermission = true
            )
        )

        // Android 14+ (API 34): After OFFHOOK without RECORD_AUDIO -> falls back to PHONE_CALL only
        assertEquals(
            ServiceInfo.FOREGROUND_SERVICE_TYPE_PHONE_CALL,
            FunkiraPhoneConciergeService.resolveForegroundServiceType(
                sdkInt = 34,
                includeMicrophone = true,
                hasMicPermission = false
            )
        )

        // Android 13 (API 33) and below -> legacy 0
        assertEquals(
            0,
            FunkiraPhoneConciergeService.resolveForegroundServiceType(
                sdkInt = 33,
                includeMicrophone = true,
                hasMicPermission = true
            )
        )
    }
}
