package de.meinseelenfunke.app.services

import android.content.Intent
import android.os.Build
import android.telecom.Call
import android.telecom.CallScreeningService
import de.meinseelenfunke.app.di.ServiceLocator
import de.meinseelenfunke.app.util.PhoneConciergeManager

/**
 * Screens incoming cellular calls in < 20 ms against Alina's local Whitelist cache.
 * - Unknown numbers & non-whitelisted contacts are NEVER touched (phone rings normally).
 * - Whitelisted contacts (e.g. Papa) ring normally for `ringDelaySeconds`; if Alina does not
 *   pick up herself, `FunkiraPhoneConciergeService` accepts the call and bridges Bluetooth HFP
 *   to Funkira on the Mini-PC (`0,00 €` Twilio-free architecture).
 */
class FunkiraCallScreeningService : CallScreeningService() {

    override fun onScreenCall(callDetails: Call.Details) {
        val isIncoming = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
            callDetails.callDirection == Call.Details.DIRECTION_INCOMING
        } else {
            true
        }

        // Always allow the call through normally (never block or silence normal incoming calls!)
        respondToCall(callDetails, CallResponse.Builder().build())

        if (!isIncoming) return

        ServiceLocator.ensureInitialized(applicationContext)
        if (ServiceLocator.getAuthToken().isNullOrBlank()) return
        if (!PhoneConciergeManager.isEnabled(applicationContext)) return
        if (!PhoneConciergeManager.hasRequiredPermissions(applicationContext)) return

        val rawNumber = callDetails.handle?.schemeSpecificPart?.trim().orEmpty()
        if (rawNumber.isEmpty()) return

        val matched = PhoneConciergeManager.matchIncomingWhitelistedCaller(applicationContext, rawNumber) ?: return

        val svcIntent = Intent(applicationContext, FunkiraPhoneConciergeService::class.java).apply {
            action = FunkiraPhoneConciergeService.ACTION_INCOMING_WHITELIST_CALL
            putExtra(FunkiraPhoneConciergeService.EXTRA_CONTACT_ID, matched.id)
            putExtra(FunkiraPhoneConciergeService.EXTRA_CALLER_NAME, matched.fullName)
            putExtra(FunkiraPhoneConciergeService.EXTRA_CALLER_NICKNAME, matched.nickname)
            putExtra(FunkiraPhoneConciergeService.EXTRA_RELATION_TYPE, matched.relationType)
            putExtra(FunkiraPhoneConciergeService.EXTRA_CALLER_PHONE, rawNumber)
            putExtra(FunkiraPhoneConciergeService.EXTRA_SYSTEM_INSTRUCTIONS, matched.systemInstructions)
            putExtra(FunkiraPhoneConciergeService.EXTRA_RING_DELAY_SEC, PhoneConciergeManager.getRingDelaySeconds(applicationContext))
        }

        try {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                applicationContext.startForegroundService(svcIntent)
            } else {
                applicationContext.startService(svcIntent)
            }
        } catch (_: Exception) { }
    }
}
