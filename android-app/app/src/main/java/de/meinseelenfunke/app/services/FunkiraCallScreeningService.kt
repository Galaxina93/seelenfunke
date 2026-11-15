package de.meinseelenfunke.app.services

import android.os.Build
import android.telecom.Call
import android.telecom.CallScreeningService
import de.meinseelenfunke.app.di.ServiceLocator
import de.meinseelenfunke.app.util.PhoneConciergeManager

/**
 * Official Android 10-16 `CallScreeningService` for Funkira Whitelist Call-Diversion:
 * - Unknown numbers & non-whitelisted contacts are NEVER touched (phone rings normally).
 * - Whitelisted contacts + `Sofort` (`0 s`):
 *   Pre-warms Funkira's Aoede greeting WAV on Stage and returns `setDisallowCall(true).setRejectCall(true)`
 *   synchronously in `< 20 ms`. Pixel stays 100% silent while carrier `**67*` (`**004*`) routes the caller
 *   immediately to Funkira's Gifhorn Twilio Live-Bridge (`+49 5371 7269023`).
 * - Whitelisted contacts + `8 / 12 / 18 Sek.`:
 *   Allows Pixel to ring for `ringDelaySeconds`; if Alina does not pick up herself, declines cleanly
 *   via `TelecomManager.endCall()` so carrier `**67*` / `**61*` forwards to Funkira.
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
        PhoneConciergeManager.signalWhitelistDiversionAsync(applicationContext, matched, rawNumber)

        if (PhoneConciergeManager.shouldRejectImmediatelyInScreening(isWhitelisted = true, delaySeconds = delaySec)) {
            PhoneConciergeManager.appendDiagnosticTrace(applicationContext, "Sofort-Umleitung ${matched.fullName} -> Funkira")
            val rejectResponse = CallResponse.Builder()
                .setDisallowCall(true)
                .setRejectCall(true)
                .setSkipCallLog(false)
                .setSkipNotification(false)
                .build()
            respondToCall(callDetails, rejectResponse)
            return
        }

        // Delayed mode (8 / 12 / 18 s): let Pixel ring first, then divert to Funkira if unanswered
        PhoneConciergeManager.appendDiagnosticTrace(
            applicationContext,
            "Klingelt ${delaySec}s (${matched.fullName}) -> danach Funkira"
        )
        PhoneConciergeManager.scheduleDelayedCallDiversion(
            ctx = applicationContext,
            matched = matched,
            rawNumber = rawNumber,
            delaySeconds = delaySec
        )
        respondToCall(callDetails, CallResponse.Builder().build())
    }
}
