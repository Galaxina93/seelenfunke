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

    @Test
    fun savedContactScreeningAndSofortTiming_matchesPhilipGoikAndBuildsCarrierDivertMmi() {
        // 1. Saved Google Contacts require READ_CONTACTS in runtime permission request so Android Telecom invokes CallScreeningService
        val requestedPerms34 = PhoneConciergeManager.buildRuntimePermissionRequestArray(sdkInt = 34).toSet()
        assertTrue(requestedPerms34.contains(android.Manifest.permission.READ_CONTACTS))
        assertTrue(requestedPerms34.contains(android.Manifest.permission.READ_PHONE_STATE))
        assertTrue(requestedPerms34.contains(android.Manifest.permission.ANSWER_PHONE_CALLS))
        assertTrue(requestedPerms34.contains(android.Manifest.permission.POST_NOTIFICATIONS))

        // 2. Carrier GSM conditional call-diversion MMI codes (**004* covers 67 Busy + 61 No Answer + 62 Unreachable)
        assertEquals(
            "**004*+4953717269023**20#",
            PhoneConciergeManager.buildCarrierForwardingActivateMmi("+49 5371 7269023", 20)
        )
        assertEquals("##004#", PhoneConciergeManager.buildCarrierForwardingDisableMmi())
        assertTrue(PhoneConciergeManager.shouldRejectImmediatelyInScreening(isWhitelisted = true, delaySeconds = 0))
        assertFalse(PhoneConciergeManager.shouldRejectImmediatelyInScreening(isWhitelisted = true, delaySeconds = 8))
        assertFalse(PhoneConciergeManager.shouldRejectImmediatelyInScreening(isWhitelisted = false, delaySeconds = 0))

        // Legacy ring wait helper still valid
        assertEquals(
            FunkiraPhoneConciergeService.SOFORT_RINGING_SETTLE_MS,
            FunkiraPhoneConciergeService.computeEffectiveRingWaitMs(0)
        )
        assertEquals(8_000L, FunkiraPhoneConciergeService.computeEffectiveRingWaitMs(8))
        assertEquals(12_000L, FunkiraPhoneConciergeService.computeEffectiveRingWaitMs(12))

        // 3. Exact Whitelist caller matching for Philip Goik (+49 176 52784439) across all German 5G/VoLTE incoming formats
        val philip = de.meinseelenfunke.app.util.CachedWhitelistContact(
            id = "contact-philip",
            fullName = "Philip Goik",
            nickname = "Philip",
            relationType = "Privat",
            phone = "+49 176 52784439",
            normalizedPhone = PhoneConciergeManager.normalizePhoneDigits("+49 176 52784439"),
            matchSuffix = PhoneConciergeManager.extractMatchSuffix("+49 176 52784439"),
            systemInstructions = "",
            whitelisted = true
        )
        val contacts = listOf(philip)

        assertEquals("Philip Goik", PhoneConciergeManager.matchWhitelistedContactInList(contacts, "+4917652784439")?.fullName)
        assertEquals("Philip Goik", PhoneConciergeManager.matchWhitelistedContactInList(contacts, "0176 52784439")?.fullName)
        assertEquals("Philip Goik", PhoneConciergeManager.matchWhitelistedContactInList(contacts, "+4917652784439;verstat=TN-Validation-Passed")?.fullName)
        // VoWiFi / IMS SIP URIs ("Eingehender WLAN-Anruf" on Pixel Android 16)
        assertEquals(
            "4917652784439",
            PhoneConciergeManager.normalizePhoneDigits("sip:+4917652784439@ims.mnc001.mcc262.3gppnetwork.org;user=phone")
        )
        assertEquals(
            "Philip Goik",
            PhoneConciergeManager.matchWhitelistedContactInList(
                contacts,
                "sip:+4917652784439@ims.mnc001.mcc262.3gppnetwork.org;user=phone"
            )?.fullName
        )
        assertEquals(
            "Philip Goik",
            PhoneConciergeManager.matchWhitelistedContactInList(
                contacts,
                "%2B4917652784439@ims.vodafone.de"
            )?.fullName
        )
        assertEquals(null, PhoneConciergeManager.matchWhitelistedContactInList(contacts, "+4917699998888"))
    }

    @Test
    fun unifiedCallState_detectsWifiCallingAndEsimWhenLegacyTelephonyReportsIdle() {
        // 1. Incoming WLAN-Anruf (VoWiFi) on eSIM + Silent Mode (MODE_NORMAL):
        //    legacy telephony.callState == 0 (IDLE), telecom.isInCall == true -> must resolve to RINGING (1)!
        assertEquals(
            android.telephony.TelephonyManager.CALL_STATE_RINGING,
            FunkiraPhoneConciergeService.resolveUnifiedCallState(
                telecomInCall = true,
                audioMode = android.media.AudioManager.MODE_NORMAL,
                legacyTelephonyState = android.telephony.TelephonyManager.CALL_STATE_IDLE,
                subscriptionStates = emptyList(),
                secretaryAudioOverrideActive = false
            )
        )

        // 2. Call accepted via acceptRingingCall() on VoWiFi -> AudioManager switches to MODE_IN_CALL (2),
        //    even if legacy telephony.callState stays 0 -> must resolve to OFFHOOK (2)!
        assertEquals(
            android.telephony.TelephonyManager.CALL_STATE_OFFHOOK,
            FunkiraPhoneConciergeService.resolveUnifiedCallState(
                telecomInCall = true,
                audioMode = android.media.AudioManager.MODE_IN_CALL,
                legacyTelephonyState = android.telephony.TelephonyManager.CALL_STATE_IDLE,
                subscriptionStates = emptyList(),
                secretaryAudioOverrideActive = false
            )
        )

        // 3. During StandalonePhoneSecretaryEngine (MODE_IN_COMMUNICATION override active),
        //    while telecomInCall == true -> stays OFFHOOK (2)!
        assertEquals(
            android.telephony.TelephonyManager.CALL_STATE_OFFHOOK,
            FunkiraPhoneConciergeService.resolveUnifiedCallState(
                telecomInCall = true,
                audioMode = android.media.AudioManager.MODE_IN_COMMUNICATION,
                legacyTelephonyState = android.telephony.TelephonyManager.CALL_STATE_IDLE,
                subscriptionStates = emptyList(),
                secretaryAudioOverrideActive = true
            )
        )

        // 4. Caller hangs up during secretary session -> telecomInCall flips false -> resolves immediately to IDLE (0)!
        assertEquals(
            android.telephony.TelephonyManager.CALL_STATE_IDLE,
            FunkiraPhoneConciergeService.resolveUnifiedCallState(
                telecomInCall = false,
                audioMode = android.media.AudioManager.MODE_IN_COMMUNICATION,
                legacyTelephonyState = android.telephony.TelephonyManager.CALL_STATE_IDLE,
                subscriptionStates = emptyList(),
                secretaryAudioOverrideActive = true
            )
        )
    }

    @Test
    fun unseenPhoneSecretaryBadgeAndOrganizerPhoneTabRouting_tracksUnseenCallsDeterministically() {
        val sampleCalls = listOf(
            de.meinseelenfunke.app.data.api.PhoneSecretaryCallSummaryDto(
                id = "call-101",
                caller_name = "Philip Goik",
                summary = "Fragt wegen Abendessen am Samstag um 19 Uhr.",
                rahmenbedingungen = listOf("Samstag, 19:00 Uhr", "Italiener in Gifhorn"),
                action_required = "Kurz bestätigen, ob Samstag 19 Uhr passt"
            ),
            de.meinseelenfunke.app.data.api.PhoneSecretaryCallSummaryDto(
                id = "call-102",
                caller_name = "Papa",
                summary = "Wollte wissen, wann das Paket ankommt.",
                rahmenbedingungen = listOf("Sendungsnummer per WhatsApp"),
                action_required = "Sendungsnummer schicken"
            ),
            // Duplicate ID + blank ID should not inflate unseen badge count
            de.meinseelenfunke.app.data.api.PhoneSecretaryCallSummaryDto(
                id = "call-102",
                caller_name = "Papa"
            ),
            de.meinseelenfunke.app.data.api.PhoneSecretaryCallSummaryDto(
                id = "",
                caller_name = "Unbekannt"
            )
        )

        assertEquals(2, PhoneConciergeManager.countUnseenCallsInSet(sampleCalls, emptySet()))
        assertEquals(1, PhoneConciergeManager.countUnseenCallsInSet(sampleCalls, setOf("call-101")))
        assertEquals(0, PhoneConciergeManager.countUnseenCallsInSet(sampleCalls, setOf("call-101", "call-102")))

        // Deep-link route aliases resolve deterministically to NavTarget.ORGANIZER
        assertEquals(
            de.meinseelenfunke.app.util.NavigationBridge.NavTarget.ORGANIZER,
            de.meinseelenfunke.app.util.NavigationBridge.parseTarget("telefon")
        )
        assertEquals(
            de.meinseelenfunke.app.util.NavigationBridge.NavTarget.ORGANIZER,
            de.meinseelenfunke.app.util.NavigationBridge.parseTarget("anrufbeantworter")
        )
        assertEquals(
            de.meinseelenfunke.app.util.NavigationBridge.NavTarget.ORGANIZER,
            de.meinseelenfunke.app.util.NavigationBridge.parseTarget("phone_secretary")
        )
    }
}
