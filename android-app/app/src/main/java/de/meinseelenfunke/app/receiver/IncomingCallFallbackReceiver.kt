package de.meinseelenfunke.app.receiver

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.telephony.TelephonyManager
import de.meinseelenfunke.app.di.ServiceLocator
import de.meinseelenfunke.app.services.FunkiraPhoneConciergeService
import de.meinseelenfunke.app.services.StandalonePhoneSecretaryEngine
import de.meinseelenfunke.app.util.CallRoutingDecision
import de.meinseelenfunke.app.util.PhoneConciergeManager
import java.util.UUID

/**
 * Secondary safety net for protected `android.intent.action.PHONE_STATE` broadcasts:
 * If `ROLE_CALL_SCREENING` was temporarily unchecked in Android system settings, catches incoming
 * Whitelist calls via `EXTRA_INCOMING_NUMBER` and routes them cleanly according to `AnswerMode`.
 */
class IncomingCallFallbackReceiver : BroadcastReceiver() {

    @Suppress("DEPRECATION")
    override fun onReceive(context: Context?, intent: Intent?) {
        val ctx = context?.applicationContext ?: return
        if (intent?.action != TelephonyManager.ACTION_PHONE_STATE_CHANGED) return

        val state = intent.getStringExtra(TelephonyManager.EXTRA_STATE) ?: return
        if (state != TelephonyManager.EXTRA_STATE_RINGING) return

        // Primary CallScreeningService already handled this call within the last 5 seconds
        if (PhoneConciergeManager.wasScreenedRecently()) return

        val incomingNumber = intent.getStringExtra(TelephonyManager.EXTRA_INCOMING_NUMBER)?.trim().orEmpty()
        if (incomingNumber.isEmpty()) return

        ServiceLocator.ensureInitialized(ctx)
        if (!PhoneConciergeManager.isEnabled(ctx)) return

        val matched = PhoneConciergeManager.matchIncomingWhitelistedCaller(ctx, incomingNumber) ?: return
        val delaySec = PhoneConciergeManager.getRingDelaySeconds(ctx)
        val decision = PhoneConciergeManager.resolveCallRoutingDecision(
            answerMode = PhoneConciergeManager.getAnswerMode(ctx),
            carrierForwardingConfirmed = PhoneConciergeManager.isCarrierForwardingConfirmed(ctx),
            delaySeconds = delaySec,
            hasAnswerPerms = PhoneConciergeManager.hasAnswerExecutionPermissions(ctx)
        )

        if (decision == CallRoutingDecision.ON_DEVICE_SECRETARY) {
            StandalonePhoneSecretaryEngine.preWarmCredentialsAsync(ctx)
            PhoneConciergeManager.appendDiagnosticTrace(ctx, "Fallback Direkt-Annahme (${matched.fullName}, ${delaySec}s)")
            FunkiraPhoneConciergeService.startForIncomingWhitelistCall(
                context = ctx,
                matched = matched,
                rawNumber = incomingNumber,
                sourceTag = "IncomingCallFallback"
            )
            return
        }

        val signalCallId = UUID.randomUUID().toString()
        PhoneConciergeManager.appendDiagnosticTrace(ctx, "Fallback-Umleitung ${matched.fullName} (${delaySec}s)")
        PhoneConciergeManager.signalWhitelistDiversionAsync(
            ctx = ctx,
            matched = matched,
            rawNumber = incomingNumber,
            callId = signalCallId,
            audioMode = "twilio_divert"
        )
        PhoneConciergeManager.scheduleDelayedCallDiversion(
            ctx = ctx,
            matched = matched,
            rawNumber = incomingNumber,
            delaySeconds = delaySec,
            signalCallId = signalCallId
        )
    }
}
