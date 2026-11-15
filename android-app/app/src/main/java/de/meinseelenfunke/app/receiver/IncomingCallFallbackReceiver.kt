package de.meinseelenfunke.app.receiver

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.telephony.TelephonyManager
import de.meinseelenfunke.app.di.ServiceLocator
import de.meinseelenfunke.app.util.PhoneConciergeManager

/**
 * Secondary safety net for protected `android.intent.action.PHONE_STATE` broadcasts:
 * If `ROLE_CALL_SCREENING` was temporarily unchecked in Android system settings, catches incoming
 * Whitelist calls via `EXTRA_INCOMING_NUMBER` and diverts to Funkira after `ringDelaySeconds`
 * (`0 s` = immediate `TelecomManager.endCall()` -> carrier `**67*` forwarding to `+49 5371 7269023`).
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
        PhoneConciergeManager.appendDiagnosticTrace(ctx, "Fallback-Umleitung ${matched.fullName} (${delaySec}s)")
        PhoneConciergeManager.signalWhitelistDiversionAsync(ctx, matched, incomingNumber)
        PhoneConciergeManager.scheduleDelayedCallDiversion(
            ctx = ctx,
            matched = matched,
            rawNumber = incomingNumber,
            delaySeconds = delaySec
        )
    }
}
