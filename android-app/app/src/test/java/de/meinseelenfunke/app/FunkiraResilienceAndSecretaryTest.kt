package de.meinseelenfunke.app

import android.content.pm.ServiceInfo
import android.media.AudioDeviceInfo
import de.meinseelenfunke.app.data.api.CallDiscrepancyDto
import de.meinseelenfunke.app.data.api.CallFactCheckDto
import de.meinseelenfunke.app.data.api.CallVerificationReportDto
import de.meinseelenfunke.app.data.api.PhoneSecretaryCallSummaryDto
import de.meinseelenfunke.app.services.CallNoteVerificationEngine
import de.meinseelenfunke.app.services.CallVerificationInput
import de.meinseelenfunke.app.services.FunkiraPhoneConciergeService
import de.meinseelenfunke.app.services.StandalonePhoneSecretaryEngine
import de.meinseelenfunke.app.ui.screens.FunkiraLiveViewModel
import de.meinseelenfunke.app.util.CallRoutingDecision
import de.meinseelenfunke.app.util.ErrorSentinelReporter
import de.meinseelenfunke.app.util.LocalNoteEdit
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
    fun buildDirectVertexWsUrl_andVertexTokenHelpers_enforceOAuthBearerAndCleanEuEndpoint() {
        assertNull(FunkiraLiveViewModel.buildDirectVertexWsUrl(""))
        assertNull(FunkiraLiveViewModel.buildDirectVertexWsUrl("short"))
        assertNull(FunkiraLiveViewModel.buildDirectVertexWsUrl("AIzaSyLegacyKeyMustBeRejected12345"))

        val cleanVertexUrl = FunkiraLiveViewModel.buildDirectVertexWsUrl(
            accessToken = "ya29.ValidVertexAccessToken1234567890",
            customLiveWsUrl = "wss://europe-west4-aiplatform.googleapis.com/ws/google.cloud.aiplatform.v1beta1.LlmBidiService/BidiGenerateContent?access_token=ya29.LeakGuard1234567890"
        )
        assertEquals(
            "wss://europe-west4-aiplatform.googleapis.com/ws/google.cloud.aiplatform.v1beta1.LlmBidiService/BidiGenerateContent",
            cleanVertexUrl
        )
        assertTrue(
            FunkiraLiveViewModel.isTrustedLiveWebSocketHost(
                cleanVertexUrl,
                "stage.mein-seelenfunke.de"
            )
        )
        assertEquals(
            "https://aiplatform.googleapis.com/v1beta1/projects/gen-lang-client-0670786367/locations/global/publishers/google/models/gemini-3.5-flash:generateContent",
            de.meinseelenfunke.app.util.VertexClientTokenCache.buildModelGenerateContentUrl("gemini-2.5-flash")
        )
        assertEquals(
            "https://aiplatform.googleapis.com/v1beta1/projects/gen-lang-client-0670786367/locations/global/publishers/google/models/gemini-2.5-flash-tts:generateContent",
            de.meinseelenfunke.app.util.VertexClientTokenCache.buildModelGenerateContentUrl("gemini-2.5-flash-preview-tts")
        )
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
    fun resolveLiveVoiceName_blocksMaleVoiceFallbackForFunkiraAndPreservesFemaleOrExplicitAgentVoices() {
        assertEquals("Aoede", FunkiraLiveViewModel.resolveLiveVoiceName(null, isFunkiraSelected = true))
        assertEquals("Aoede", FunkiraLiveViewModel.resolveLiveVoiceName("", isFunkiraSelected = true))
        assertEquals("Aoede", FunkiraLiveViewModel.resolveLiveVoiceName("Orus", isFunkiraSelected = true))
        assertEquals("Aoede", FunkiraLiveViewModel.resolveLiveVoiceName("orus", isFunkiraSelected = true))
        assertEquals("Aoede", FunkiraLiveViewModel.resolveLiveVoiceName("Charon", isFunkiraSelected = true))
        assertEquals("Aoede", FunkiraLiveViewModel.resolveLiveVoiceName("Puck", isFunkiraSelected = true))
        assertEquals("Aoede", FunkiraLiveViewModel.resolveLiveVoiceName("Fenrir", isFunkiraSelected = true))
        assertEquals("Aoede", FunkiraLiveViewModel.resolveLiveVoiceName("Iapetus", isFunkiraSelected = true))
        assertEquals("Kore", FunkiraLiveViewModel.resolveLiveVoiceName("Kore", isFunkiraSelected = true))
        assertEquals("Aoede", FunkiraLiveViewModel.resolveLiveVoiceName("Aoede", isFunkiraSelected = true))
        assertEquals(
            "Kore",
            FunkiraLiveViewModel.resolveLiveVoiceName("Orus", isFunkiraSelected = true, fallbackVoice = "Kore")
        )
        assertEquals("Orus", FunkiraLiveViewModel.resolveLiveVoiceName("Orus", isFunkiraSelected = false))
        assertEquals("Aoede", FunkiraLiveViewModel.resolveLiveVoiceName(null, isFunkiraSelected = false))
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

    @Test
    fun formatWebSocketFailureMessage_preventsLiteralNullAndFormatsCleanGermanStatusTexts() {
        val eofAfterSetup = FunkiraLiveViewModel.formatWebSocketFailureMessage(
            t = java.io.EOFException(),
            httpStatusCode = null,
            setupWasComplete = true
        )
        assertEquals("Verbindung beendet", eofAfterSetup)
        assertFalse(eofAfterSetup.contains("null", ignoreCase = true))

        val eofBeforeSetup = FunkiraLiveViewModel.formatWebSocketFailureMessage(
            t = java.io.EOFException(),
            httpStatusCode = null,
            setupWasComplete = false
        )
        assertEquals("Verbindung getrennt", eofBeforeSetup)

        val sslAbort = FunkiraLiveViewModel.formatWebSocketFailureMessage(
            t = javax.net.ssl.SSLException("Software caused connection abort"),
            httpStatusCode = null,
            setupWasComplete = true
        )
        assertEquals("Verbindung kurz unterbrochen", sslAbort)

        val offlineDns = FunkiraLiveViewModel.formatWebSocketFailureMessage(
            t = java.net.UnknownHostException(),
            httpStatusCode = null,
            setupWasComplete = false
        )
        assertEquals("Keine Internetverbindung", offlineDns)

        val timeoutMsg = FunkiraLiveViewModel.formatWebSocketFailureMessage(
            t = java.net.SocketTimeoutException(),
            httpStatusCode = null,
            setupWasComplete = false
        )
        assertEquals("Zeitüberschreitung der Verbindung", timeoutMsg)

        // Real exception with null message must never render "Verbindungsfehler: null"
        val nullMsgBug = FunkiraLiveViewModel.formatWebSocketFailureMessage(
            t = IllegalStateException(),
            httpStatusCode = null,
            setupWasComplete = true
        )
        assertEquals("Verbindungsfehler (IllegalStateException)", nullMsgBug)
        assertFalse(nullMsgBug.contains("null", ignoreCase = true))

        val http401NullMsg = FunkiraLiveViewModel.formatWebSocketFailureMessage(
            t = java.io.EOFException(),
            httpStatusCode = 401,
            setupWasComplete = false
        )
        assertEquals("Verbindungsfehler (HTTP 401)", http401NullMsg)
        assertFalse(http401NullMsg.contains("null", ignoreCase = true))

        val tlsFail = FunkiraLiveViewModel.formatWebSocketFailureMessage(
            t = javax.net.ssl.SSLHandshakeException("Pinning mismatch"),
            httpStatusCode = null,
            setupWasComplete = false
        )
        assertEquals("Verbindungsfehler: Pinning mismatch", tlsFail)
        assertFalse(tlsFail.contains(" \u2013 "))
    }

    @Test
    fun selectPreferredCommunicationDeviceType_prioritizesBluetoothScoAndBleOverSpeakerAndWired() {
        // Soundcore P30i Bluetooth Headset scenario: SCO preferred over speaker & earpiece
        val btAndSpeaker = listOf(
            AudioDeviceInfo.TYPE_BUILTIN_EARPIECE,
            AudioDeviceInfo.TYPE_BUILTIN_SPEAKER,
            AudioDeviceInfo.TYPE_BLUETOOTH_SCO
        )
        assertEquals(
            AudioDeviceInfo.TYPE_BLUETOOTH_SCO,
            FunkiraLiveViewModel.selectPreferredCommunicationDeviceType(btAndSpeaker)
        )

        // BLE Headset preferred over built-in speaker
        val bleAndSpeaker = listOf(
            AudioDeviceInfo.TYPE_BUILTIN_SPEAKER,
            AudioDeviceInfo.TYPE_BLE_HEADSET
        )
        assertEquals(
            AudioDeviceInfo.TYPE_BLE_HEADSET,
            FunkiraLiveViewModel.selectPreferredCommunicationDeviceType(bleAndSpeaker)
        )

        // Wired Headset preferred over built-in speaker if no Bluetooth
        val wiredAndSpeaker = listOf(
            AudioDeviceInfo.TYPE_BUILTIN_SPEAKER,
            AudioDeviceInfo.TYPE_WIRED_HEADSET
        )
        assertEquals(
            AudioDeviceInfo.TYPE_WIRED_HEADSET,
            FunkiraLiveViewModel.selectPreferredCommunicationDeviceType(wiredAndSpeaker)
        )

        // Fallback to speaker when no headset connected
        val speakerOnly = listOf(AudioDeviceInfo.TYPE_BUILTIN_SPEAKER)
        assertEquals(
            AudioDeviceInfo.TYPE_BUILTIN_SPEAKER,
            FunkiraLiveViewModel.selectPreferredCommunicationDeviceType(speakerOnly)
        )

        // Empty list fallback
        assertEquals(
            AudioDeviceInfo.TYPE_UNKNOWN,
            FunkiraLiveViewModel.selectPreferredCommunicationDeviceType(emptyList())
        )
    }

    @Test
    fun callRoutingDecision_preventsBlackHoleDropWithoutCarrierConfirmationAndRespectsExplicitModes() {
        // AUTO without confirmed carrier forwarding -> answers safely on-device
        assertEquals(
            CallRoutingDecision.ON_DEVICE_SECRETARY,
            PhoneConciergeManager.resolveCallRoutingDecision(
                answerMode = PhoneConciergeManager.ANSWER_MODE_AUTO,
                carrierForwardingConfirmed = false,
                delaySeconds = 0,
                hasAnswerPerms = true
            )
        )

        // AUTO with confirmed carrier forwarding + Sofort -> instant silent carrier reject
        assertEquals(
            CallRoutingDecision.REJECT_TO_CARRIER,
            PhoneConciergeManager.resolveCallRoutingDecision(
                answerMode = PhoneConciergeManager.ANSWER_MODE_AUTO,
                carrierForwardingConfirmed = true,
                delaySeconds = 0,
                hasAnswerPerms = true
            )
        )

        // AUTO with confirmed carrier forwarding + 12s -> delayed carrier diversion
        assertEquals(
            CallRoutingDecision.DELAYED_CARRIER_DIVERT,
            PhoneConciergeManager.resolveCallRoutingDecision(
                answerMode = PhoneConciergeManager.ANSWER_MODE_AUTO,
                carrierForwardingConfirmed = true,
                delaySeconds = 12,
                hasAnswerPerms = true
            )
        )

        // Explicit ON_DEVICE -> always answers on smartphone when permissions granted
        assertEquals(
            CallRoutingDecision.ON_DEVICE_SECRETARY,
            PhoneConciergeManager.resolveCallRoutingDecision(
                answerMode = PhoneConciergeManager.ANSWER_MODE_ON_DEVICE,
                carrierForwardingConfirmed = true,
                delaySeconds = 0,
                hasAnswerPerms = true
            )
        )

        // Explicit CARRIER -> always uses carrier route
        assertEquals(
            CallRoutingDecision.REJECT_TO_CARRIER,
            PhoneConciergeManager.resolveCallRoutingDecision(
                answerMode = PhoneConciergeManager.ANSWER_MODE_CARRIER,
                carrierForwardingConfirmed = false,
                delaySeconds = 0,
                hasAnswerPerms = true
            )
        )
    }

    @Test
    fun callNoteVerificationEngine_detectsTimeAndCallbackDiscrepanciesAndParsesAiCorrections() {
        val mismatchInput = CallVerificationInput(
            callerName = "Philip Goik",
            greetingName = "Philip",
            transcriptLines = listOf(
                "Funkira: Hallo Philip, hier ist Funkira. Worum geht es?",
                "Anrufer: Sag Alina bitte, ich komme heute um 19 Uhr vorbei. Du brauchst nicht zurückrufen."
            ),
            understoodSummary = "Philip kommt heute um 17:00 Uhr vorbei.",
            understoodRahmenbedingungen = listOf("Heute, 17:00 Uhr"),
            understoodActionRequired = "Philip zurückrufen.",
            understoodUrgency = "mittel",
            understoodCallbackRequested = true
        )

        val (detDiffs, _) = CallNoteVerificationEngine.runDeterministicCrossCheck(mismatchInput)
        assertTrue(detDiffs.any { it.category == "Uhrzeit" && it.severity == "critical" })
        assertTrue(detDiffs.any { it.category == "Rückruf" && it.severity == "warning" })

        val aiJson = """
            ```json
            {
              "status": "corrected",
              "confidence_score": 92,
              "verification_summary": "Uhrzeit und Rückrufwunsch anhand des Gesprächs korrigiert.",
              "verified_facts": [
                {"fact": "Uhrzeit 19:00 Uhr", "evidence_quote": "ich komme heute um 19 Uhr vorbei", "matched": true}
              ],
              "discrepancies": [
                {"category": "Uhrzeit", "understood": "17:00 Uhr", "spoken": "19 Uhr", "severity": "critical", "resolved_by_correction": true},
                {"category": "Rückruf", "understood": "Rückruf erbeten", "spoken": "Du brauchst nicht zurückrufen", "severity": "warning", "resolved_by_correction": true}
              ],
              "corrected_summary": "Philip kommt heute um 19:00 Uhr vorbei.",
              "corrected_rahmenbedingungen": ["Heute, 19:00 Uhr", "Kein Rückruf nötig"],
              "corrected_action_required": "Zur Kenntnis nehmen.",
              "corrected_urgency": "niedrig",
              "corrected_callback_requested": false
            }
            ```
        """.trimIndent()

        val report = CallNoteVerificationEngine.parseVerificationJson(aiJson, mismatchInput, detDiffs, emptyList())
        assertEquals(CallNoteVerificationEngine.STATUS_CORRECTED, report.status)
        assertEquals(92, report.confidence_score)
        assertEquals("Philip kommt heute um 19:00 Uhr vorbei.", report.corrected_summary)
        assertEquals("Zur Kenntnis nehmen.", report.corrected_action_required)
        assertEquals("niedrig", report.corrected_urgency)
        assertFalse(report.corrected_callback_requested)
        assertFalse(report.verification_summary.contains(" \u2013 "))
    }

    @Test
    fun mergeAndServerSummaryHelpers_applyVerifiedCorrectionsAndPreserveLocalCalls() {
        val localCall = PhoneSecretaryCallSummaryDto(
            id = "local-call-1",
            caller_name = "Papa (Jan Steinhauer)",
            created_at_label = "Heute, 18:10",
            duration_seconds = 38,
            summary = "Vorläufig: Kommt um 15 Uhr",
            rahmenbedingungen = listOf("15:00 Uhr"),
            action_required = "Bestätigen",
            handled_by = "android_secretary"
        )
        val remoteCall = PhoneSecretaryCallSummaryDto(
            id = "remote-call-2",
            caller_name = "Philip Goik",
            created_at_label = "Heute, 17:00",
            duration_seconds = 25,
            summary = "Abendessen geplant",
            rahmenbedingungen = listOf("Samstag, 19:00 Uhr"),
            action_required = "Zusagen"
        )
        val verifications = mapOf(
            "local-call-1" to CallVerificationReportDto(
                status = CallNoteVerificationEngine.STATUS_CORRECTED,
                confidence_score = 94,
                corrected_summary = "Papa kommt morgen um 16:00 Uhr vorbei.",
                corrected_rahmenbedingungen = listOf("Morgen, 16:00 Uhr"),
                corrected_action_required = "Kaffee vorbereiten",
                corrected_urgency = "mittel",
                corrected_callback_requested = false,
                verified_facts = listOf(CallFactCheckDto("16:00 Uhr", "morgen um 16 Uhr", true)),
                discrepancies = listOf(CallDiscrepancyDto("Uhrzeit", "15:00", "16 Uhr", "critical", true))
            )
        )
        val edits = mapOf(
            "remote-call-2" to LocalNoteEdit(
                summary = "Abendessen beim Italiener bestätigt.",
                actionRequired = "Tisch für 19:00 Uhr merken",
                rahmenbedingungen = listOf("Samstag, 19:00 Uhr", "Italiener")
            )
        )

        val merged = PhoneConciergeManager.applyLocalEnhancementsToCalls(
            remoteCalls = listOf(remoteCall),
            localCalls = listOf(localCall),
            verifications = verifications,
            edits = edits
        )

        assertEquals(2, merged.size)
        val mergedLocal = merged.first { it.id == "local-call-1" }
        assertEquals("Papa kommt morgen um 16:00 Uhr vorbei.", mergedLocal.summary)
        assertEquals("Kaffee vorbereiten", mergedLocal.action_required)
        assertEquals(listOf("Morgen, 16:00 Uhr"), mergedLocal.rahmenbedingungen)

        val mergedRemote = merged.first { it.id == "remote-call-2" }
        assertEquals("Abendessen beim Italiener bestätigt.", mergedRemote.summary)
        assertEquals("Tisch für 19:00 Uhr merken", mergedRemote.action_required)

        val note = PendingPhoneSecretaryNote(
            callId = "note-1",
            contactId = null,
            callerName = "Jan Steinhauer",
            greetingName = "Papa",
            callerPhone = "+4917657793016",
            summary = "Papa kommt morgen vorbei",
            urgency = "mittel",
            callbackRequested = false,
            rahmenbedingungen = listOf("Morgen, 16:00 Uhr"),
            actionRequired = "Kaffee vorbereiten"
        )
        val serverSummary = PhoneConciergeManager.buildEnrichedServerSummary(note)
        assertTrue(serverSummary.contains("Papa kommt morgen vorbei"))
        assertTrue(serverSummary.contains("Morgen, 16:00 Uhr"))
        assertTrue(serverSummary.contains("Kaffee vorbereiten"))
        assertFalse(serverSummary.contains(" \u2013 "))
    }

    @Test
    fun softOffhookAndClientCallIdDeduplication_preventMissedVoWifiPickupAndDuplicateLogs() {
        // 1. Soft-OFFHOOK accepts VoWiFi/Bluetooth calls staying in MODE_NORMAL after 2.2s when Telecom stays active,
        //    but strictly rejects MODE_RINGTONE or inactive Telecom.
        assertTrue(
            FunkiraPhoneConciergeService.shouldTreatAsSoftOffhookAfterAccept(
                telecomInCall = true,
                audioMode = android.media.AudioManager.MODE_NORMAL,
                unifiedState = android.telephony.TelephonyManager.CALL_STATE_RINGING,
                elapsedSinceAcceptMs = 2_300L
            )
        )
        assertFalse(
            FunkiraPhoneConciergeService.shouldTreatAsSoftOffhookAfterAccept(
                telecomInCall = true,
                audioMode = android.media.AudioManager.MODE_RINGTONE,
                unifiedState = android.telephony.TelephonyManager.CALL_STATE_RINGING,
                elapsedSinceAcceptMs = 2_500L
            )
        )
        assertFalse(
            FunkiraPhoneConciergeService.shouldTreatAsSoftOffhookAfterAccept(
                telecomInCall = false,
                audioMode = android.media.AudioManager.MODE_NORMAL,
                unifiedState = android.telephony.TelephonyManager.CALL_STATE_IDLE,
                elapsedSinceAcceptMs = 2_500L
            )
        )

        // 2. Local UUID call is deduplicated when the backend returns the persisted SupportTelephonyCall row with client_call_id,
        //    while keeping local verification & user edits mapped via client_call_id!
        val uuid = "550e8400-e29b-41d4-a716-446655440000"
        val localEntry = PhoneSecretaryCallSummaryDto(
            id = uuid,
            client_call_id = uuid,
            caller_name = "Papa (Jan Steinhauer)",
            summary = "Vorläufige Notiz",
            handled_by = "android_secretary"
        )
        val serverEntry = PhoneSecretaryCallSummaryDto(
            id = "42",
            client_call_id = uuid,
            caller_name = "Papa (Jan Steinhauer)",
            summary = "Server-Notiz",
            duration_seconds = 31,
            transcript = listOf("Funkira: Hallo Papa", "Anrufer: Komm heute um 18 Uhr"),
            handled_by = "android_secretary"
        )
        val verifications = mapOf(
            uuid to CallVerificationReportDto(
                status = CallNoteVerificationEngine.STATUS_VERIFIED,
                confidence_score = 98,
                verification_summary = "Geprüft"
            )
        )
        val edits = mapOf(
            uuid to LocalNoteEdit(
                summary = "Alinas korrigierte Notiz",
                actionRequired = "18:00 Uhr zurückrufen",
                rahmenbedingungen = listOf("Heute, 18:00 Uhr")
            )
        )

        val merged = PhoneConciergeManager.applyLocalEnhancementsToCalls(
            remoteCalls = listOf(serverEntry),
            localCalls = listOf(localEntry),
            verifications = verifications,
            edits = edits
        )

        assertEquals(1, merged.size)
        assertEquals("42", merged[0].id)
        assertEquals("Alinas korrigierte Notiz", merged[0].summary)
        assertEquals("18:00 Uhr zurückrufen", merged[0].action_required)
        assertEquals(CallNoteVerificationEngine.STATUS_VERIFIED, merged[0].verification?.status)
    }

    @Test
    fun phoneSecretaryMicWatchdogAndAudioMode_recoverFromZeroSamplesAndNormalizeTrunkZeroNumbers() {
        // 1. Preserve MODE_IN_CALL during active cellular calls instead of overwriting with MODE_IN_COMMUNICATION
        assertEquals(
            android.media.AudioManager.MODE_IN_CALL,
            StandalonePhoneSecretaryEngine.resolveDesiredAudioModeForSecretary(android.media.AudioManager.MODE_IN_CALL)
        )
        assertEquals(
            android.media.AudioManager.MODE_IN_COMMUNICATION,
            StandalonePhoneSecretaryEngine.resolveDesiredAudioModeForSecretary(android.media.AudioManager.MODE_NORMAL)
        )

        // 2. AudioSource profile order during MODE_IN_CALL prioritizes raw MIC without conflicting hardware DSP first
        val inCallProfiles = StandalonePhoneSecretaryEngine.resolveAudioSourceProfiles(android.media.AudioManager.MODE_IN_CALL)
        assertEquals(4, inCallProfiles.size)
        assertEquals(android.media.MediaRecorder.AudioSource.MIC, inCallProfiles[0].audioSource)
        assertFalse(inCallProfiles[0].enableHardwareEffects)
        assertEquals(android.media.MediaRecorder.AudioSource.VOICE_COMMUNICATION, inCallProfiles[1].audioSource)
        assertEquals(android.media.MediaRecorder.AudioSource.VOICE_RECOGNITION, inCallProfiles[2].audioSource)
        assertEquals(android.media.MediaRecorder.AudioSource.UNPROCESSED, inCallProfiles[3].audioSource)

        // 3. Zero-sample watchdog rotates audio source at 28 zero frames (~1.1s), resets on real PCM, and only reports permanent silence after >= 65 frames
        val watchdog = StandalonePhoneSecretaryEngine.MicZeroSampleWatchdog(rotateThresholdFrames = 28, reportThresholdFrames = 65)
        val silentFrame = ShortArray(640)
        val activeFrame = ShortArray(640).apply { this[12] = 420 }

        repeat(27) {
            assertEquals(
                StandalonePhoneSecretaryEngine.MicFrameVerdict.SILENT_WAIT,
                watchdog.onFrame(silentFrame, silentFrame.size, hasMoreCandidates = true)
            )
        }
        assertEquals(
            StandalonePhoneSecretaryEngine.MicFrameVerdict.ROTATE_SOURCE,
            watchdog.onFrame(silentFrame, silentFrame.size, hasMoreCandidates = true)
        )
        assertEquals(1, watchdog.recoveryRotations)
        // Non-zero speech after rotation resets all silence counters immediately
        assertEquals(
            StandalonePhoneSecretaryEngine.MicFrameVerdict.ACTIVE,
            watchdog.onFrame(activeFrame, activeFrame.size, hasMoreCandidates = true)
        )
        assertEquals(0, watchdog.totalZeroFramesAcrossAttempts)

        // Exhausting all sources for 65 frames triggers single PERMANENT_SILENCE report
        var permanentCount = 0
        repeat(70) {
            if (watchdog.onFrame(silentFrame, silentFrame.size, hasMoreCandidates = false) ==
                StandalonePhoneSecretaryEngine.MicFrameVerdict.PERMANENT_SILENCE
            ) {
                permanentCount++
            }
        }
        assertEquals(1, permanentCount)

        // 4. German trunk-zero phone normalization ("+49 (0) 176...") matches national & E.164 formats
        assertEquals("4917657793016", PhoneConciergeManager.normalizePhoneDigits("+49 (0) 176 57793016"))
        assertEquals("4953717269023", PhoneConciergeManager.normalizePhoneDigits("+49 (0) 5371 7269023"))
        assertEquals(
            PhoneConciergeManager.normalizePhoneDigits("0176 57793016"),
            PhoneConciergeManager.normalizePhoneDigits("+49 (0) 176 57793016")
        )

        // 5. Onboarding permission request includes RECORD_AUDIO by default for direct smartphone secretary
        val permsWithMic = PhoneConciergeManager.buildRuntimePermissionRequestArray(sdkInt = 34, includeMicrophone = true).toSet()
        assertTrue(permsWithMic.contains(android.Manifest.permission.RECORD_AUDIO))
        val permsWithoutMic = PhoneConciergeManager.buildRuntimePermissionRequestArray(sdkInt = 34, includeMicrophone = false).toSet()
        assertFalse(permsWithoutMic.contains(android.Manifest.permission.RECORD_AUDIO))
    }

    @Test
    fun customerServiceCallHandling_andPromptContract_areConsistent() {
        // 1. Incoming call eligibility matrix (master switch + whitelist + full customer service)
        assertFalse(PhoneConciergeManager.shouldHandleIncomingCall(isEnabled = false, isCustomerServiceEnabled = true, isWhitelisted = true))
        assertTrue(PhoneConciergeManager.shouldHandleIncomingCall(isEnabled = true, isCustomerServiceEnabled = false, isWhitelisted = true))
        assertFalse(PhoneConciergeManager.shouldHandleIncomingCall(isEnabled = true, isCustomerServiceEnabled = false, isWhitelisted = false))
        assertTrue(PhoneConciergeManager.shouldHandleIncomingCall(isEnabled = true, isCustomerServiceEnabled = true, isWhitelisted = false))

        // 2. Customer service prompt enforces EU AI Act transparency, formal Sie, zero KI dashes and spam guard
        val prompt = StandalonePhoneSecretaryEngine.buildCustomerServiceSystemInstruction()
        assertTrue(prompt.contains("KI-Assistentin"))
        assertTrue(prompt.contains("per 'Sie'"))
        assertTrue(prompt.contains("SPAM-SCHUTZ"))
        assertFalse(prompt.contains(" – "))

        // 3. Kickoff & tool schema expose ticket + spam + suggested_reply parameters in customer mode
        val kickoffJson = StandalonePhoneSecretaryEngine.buildKickoffPacketJson(
            greetingName = "Kunde",
            isCustomerService = true
        ).toString()
        assertTrue(kickoffJson.contains("Mein-Seelenfunke Kundenservice"))

        val toolsJson = StandalonePhoneSecretaryEngine.buildSecretaryToolsJson(isCustomerService = true).toString()
        assertTrue(toolsJson.contains("suggested_reply"))
        assertTrue(toolsJson.contains("is_spam"))
        assertTrue(toolsJson.contains("ticket_subject"))
        assertTrue(toolsJson.contains("order_number"))
    }

    @Test
    fun android14PlusMicFgsElevation_andSilenceAlertPolicy_handleOsDenialAndLiveResetCleanly() {
        // 1. OS-denied background FGS mic maps to AUTO_HEAL_OS_FGS_DENIED (no false-alarm Sentinel ERROR spam),
        //    whereas granted FGS mic with pure zeros still escalates to ERROR_HARDWARE_OR_HAL_SILENCE.
        assertEquals(
            StandalonePhoneSecretaryEngine.MicSilenceAlertPolicy.AUTO_HEAL_OS_FGS_DENIED,
            StandalonePhoneSecretaryEngine.resolveMicSilenceAlertPolicy(micFgsGranted = false)
        )
        assertEquals(
            StandalonePhoneSecretaryEngine.MicSilenceAlertPolicy.ERROR_HARDWARE_OR_HAL_SILENCE,
            StandalonePhoneSecretaryEngine.resolveMicSilenceAlertPolicy(micFgsGranted = true)
        )

        // 2. Watchdog default headroom (112 frames) allows all 4 audio sources to rotate before reporting silence,
        //    and resetForRetry() clears state cleanly when PhoneSecretaryCallActivity elevates FGS mid-call.
        val watchdog = StandalonePhoneSecretaryEngine.MicZeroSampleWatchdog()
        val silentFrame = ShortArray(640)

        var rotations = 0
        var permanentBeforeAllFour = 0
        repeat(84) {
            when (watchdog.onFrame(silentFrame, silentFrame.size, hasMoreCandidates = rotations < 3)) {
                StandalonePhoneSecretaryEngine.MicFrameVerdict.ROTATE_SOURCE -> rotations++
                StandalonePhoneSecretaryEngine.MicFrameVerdict.PERMANENT_SILENCE -> permanentBeforeAllFour++
                else -> Unit
            }
        }
        assertEquals(3, rotations)
        assertEquals(0, permanentBeforeAllFour)

        // Exhausting 4th source hits PERMANENT_SILENCE once
        var permanentHits = 0
        repeat(35) {
            if (watchdog.onFrame(silentFrame, silentFrame.size, hasMoreCandidates = false) ==
                StandalonePhoneSecretaryEngine.MicFrameVerdict.PERMANENT_SILENCE
            ) {
                permanentHits++
            }
        }
        assertEquals(1, permanentHits)
        assertTrue(watchdog.hasReportedWarning)

        // Mid-call FGS elevation resets watchdog cleanly so fresh AudioRecord starts from zero
        watchdog.resetForRetry()
        assertEquals(0, watchdog.consecutiveZeroFramesInAttempt)
        assertEquals(0, watchdog.totalZeroFramesAcrossAttempts)
        assertEquals(0, watchdog.recoveryRotations)
        assertFalse(watchdog.hasReportedWarning)
    }
}
