package de.meinseelenfunke.app.services

import android.os.Build
import android.telecom.Call
import android.telecom.CallScreeningService
import de.meinseelenfunke.app.di.ServiceLocator
import de.meinseelenfunke.app.util.CallRoutingDecision
import de.meinseelenfunke.app.util.PhoneConciergeManager
import java.util.UUID

/**
 * Official Android 10-16 `CallScreeningService` for Funkira Whitelist Call-Handling:
 * - Unknown numbers & non-whitelisted contacts are NEVER touched (phone rings normally).
 * - Whitelisted contacts are routed cleanly according to `AnswerMode` & `ringDelaySeconds`:
 *   1. `REJECT_TO_CARRIER`: Silent `< 20 ms` reject -> carrier `**004*` routes immediately to Gifhorn Live-Bridge.
 *   2. `DELAYED_CARRIER_DIVERT`: Rings for `ringDelaySeconds`, then `TelecomManager.endCall()` hands off to Gifhorn Live-Bridge.
 *   3. `ON_DEVICE_SECRETARY`: Pre-warms Gemini Live + Vertex tokens immediately and starts `FunkiraPhoneConciergeService`
 *      directly on the smartphone (zero carrier forwarding required).
 */
class FunkiraCallScreeningService : CallScreeningService() {

    override fun onScreenCall(callDetails: Call.Details) {
        val isIncoming = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
            callDetails.callDirection == Call.Details.DIRECTION_INCOMING
        } else {
            true
        }

        if (!isIncoming) {
            respondToCall(callDetails, CallResponse.Builder().build())
            return
        }

        ServiceLocator.ensureInitialized(applicationContext)
        if (!PhoneConciergeManager.isEnabled(applicationContext)) {
            respondToCall(callDetails, CallResponse.Builder().build())
            return
        }

        val rawNumber = (callDetails.handle ?: callDetails.gatewayInfo?.originalAddress)
            ?.schemeSpecificPart
            ?.trim()
            .orEmpty()
        val tail = rawNumber.filter { it.isDigit() }.takeLast(4)
        PhoneConciergeManager.markScreenedNow()
        PhoneConciergeManager.appendDiagnosticTrace(applicationContext, "Screening Eingang (**$tail)")

        if (rawNumber.isEmpty()) {
            respondToCall(callDetails, CallResponse.Builder().build())
            return
        }

        val matched = PhoneConciergeManager.matchIncomingWhitelistedCaller(applicationContext, rawNumber)
        if (matched == null) {
            PhoneConciergeManager.appendDiagnosticTrace(applicationContext, "Kein Whitelist-Treffer (**$tail)")
            respondToCall(callDetails, CallResponse.Builder().build())
            return
        }

        val delaySec = PhoneConciergeManager.getRingDelaySeconds(applicationContext)
        val answerMode = PhoneConciergeManager.getAnswerMode(applicationContext)
        val carrierConfirmed = PhoneConciergeManager.isCarrierForwardingConfirmed(applicationContext)
        val hasAnswerPerms = PhoneConciergeManager.hasAnswerExecutionPermissions(applicationContext)
        val decision = PhoneConciergeManager.resolveCallRoutingDecision(
            answerMode = answerMode,
            carrierForwardingConfirmed = carrierConfirmed,
            delaySeconds = delaySec,
            hasAnswerPerms = hasAnswerPerms
        )
        val signalCallId = UUID.randomUUID().toString()

        when (decision) {
            CallRoutingDecision.REJECT_TO_CARRIER -> {
                PhoneConciergeManager.signalWhitelistDiversionAsync(
                    ctx = applicationContext,
                    matched = matched,
                    rawNumber = rawNumber,
                    callId = signalCallId,
                    audioMode = "twilio_divert"
                )
                PhoneConciergeManager.appendDiagnosticTrace(applicationContext, "Sofort-Umleitung ${matched.fullName} -> Funkira")
                val rejectResponse = CallResponse.Builder()
                    .setDisallowCall(true)
                    .setRejectCall(true)
                    .setSkipCallLog(false)
                    .setSkipNotification(false)
                    .build()
                respondToCall(callDetails, rejectResponse)
            }

            CallRoutingDecision.DELAYED_CARRIER_DIVERT -> {
                PhoneConciergeManager.signalWhitelistDiversionAsync(
                    ctx = applicationContext,
                    matched = matched,
                    rawNumber = rawNumber,
                    callId = signalCallId,
                    audioMode = "twilio_divert"
                )
                PhoneConciergeManager.appendDiagnosticTrace(
                    applicationContext,
                    "Klingelt ${delaySec}s (${matched.fullName}) -> danach Funkira"
                )
                PhoneConciergeManager.scheduleDelayedCallDiversion(
                    ctx = applicationContext,
                    matched = matched,
                    rawNumber = rawNumber,
                    delaySeconds = delaySec,
                    signalCallId = signalCallId
                )
                respondToCall(callDetails, CallResponse.Builder().build())
            }

            CallRoutingDecision.ON_DEVICE_SECRETARY -> {
                StandalonePhoneSecretaryEngine.preWarmCredentialsAsync(applicationContext)
                PhoneConciergeManager.appendDiagnosticTrace(
                    applicationContext,
                    "Direkt-Annahme (${delaySec}s, ${matched.fullName}) am Handy"
                )
                FunkiraPhoneConciergeService.startForIncomingWhitelistCall(
                    context = applicationContext,
                    matched = matched,
                    rawNumber = rawNumber,
                    sourceTag = "CallScreening"
                )
                respondToCall(callDetails, CallResponse.Builder().build())
            }
        }
    }
}
