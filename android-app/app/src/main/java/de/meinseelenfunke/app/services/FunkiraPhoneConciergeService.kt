package de.meinseelenfunke.app.services

import android.Manifest
import android.annotation.SuppressLint
import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.app.Service
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.content.pm.ServiceInfo
import android.media.AudioDeviceInfo
import android.media.AudioManager
import android.os.Build
import android.os.IBinder
import android.os.PowerManager
import android.telecom.TelecomManager
import android.telecom.VideoProfile
import android.telephony.SubscriptionManager
import android.telephony.TelephonyCallback
import android.telephony.TelephonyManager
import androidx.core.app.NotificationCompat
import androidx.core.content.ContextCompat
import java.util.concurrent.ConcurrentHashMap
import de.meinseelenfunke.app.MainActivity
import de.meinseelenfunke.app.R
import de.meinseelenfunke.app.data.api.PhoneConciergeSignalBody
import de.meinseelenfunke.app.di.ServiceLocator
import de.meinseelenfunke.app.util.CachedWhitelistContact
import de.meinseelenfunke.app.util.ErrorSentinelReporter
import de.meinseelenfunke.app.util.NavigationBridge
import de.meinseelenfunke.app.util.PendingPhoneSecretaryNote
import de.meinseelenfunke.app.util.PhoneConciergeManager
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.Job
import kotlinx.coroutines.NonCancellable
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.cancel
import kotlinx.coroutines.delay
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import java.util.UUID
import java.util.concurrent.atomic.AtomicReference

/**
 * Foreground Phone-Concierge & Standalone Smartphone Secretary Service:
 * 1. Waits `ringDelaySeconds` while checking every 500 ms whether Alina answered or declined.
 * 2. Pre-warms Gemini Live credentials ~2.5s before answering so Funkira speaks with near-zero latency.
 * 3. Accepts the ringing Whitelist call via `TelecomManager.acceptRingingCall()`.
 * 4. Primary Standalone Mode (independent of Mini-PC, e.g. sleep/weekend/on-the-go):
 *    Runs `StandalonePhoneSecretaryEngine` directly on the smartphone as Alina's warm, friendly secretary,
 *    records the caller's request, kindly assures them Alina will get the message, saves a Dashboard To-Do,
 *    and hangs up cleanly.
 * 5. Workshop Hybrid Mode: If `Funkenwerk-MiniPC` Bluetooth HFP is actively connected AND reachable,
 *    bridges audio to Mini-PC (with automatic instant failover back to smartphone if Mini-PC is asleep).
 */
class FunkiraPhoneConciergeService : Service() {

    companion object {
        const val ACTION_INCOMING_WHITELIST_CALL = "de.meinseelenfunke.app.ACTION_INCOMING_WHITELIST_CALL"
        const val ACTION_MANUAL_STOP = "de.meinseelenfunke.app.ACTION_MANUAL_STOP_CONCIERGE"

        const val EXTRA_CONTACT_ID = "extra_contact_id"
        const val EXTRA_CALLER_NAME = "extra_caller_name"
        const val EXTRA_CALLER_NICKNAME = "extra_caller_nickname"
        const val EXTRA_RELATION_TYPE = "extra_relation_type"
        const val EXTRA_CALLER_PHONE = "extra_caller_phone"
        const val EXTRA_SYSTEM_INSTRUCTIONS = "extra_system_instructions"
        const val EXTRA_RING_DELAY_SEC = "extra_ring_delay_sec"

        private const val CHANNEL_ID = "funkira_phone_concierge_channel"
        private const val STATUS_CHANNEL_ID = de.meinseelenfunke.app.util.NotificationBadgeGuard.PHONE_CONCIERGE_STATUS_CHANNEL_ID
        private const val ACTIVE_NOTIF_ID = 1044
        internal const val SUMMARY_NOTIF_ID = 1045
        internal const val SOFORT_RINGING_SETTLE_MS = 280L

        internal fun computeEffectiveRingWaitMs(ringDelaySec: Int): Long {
            val clamped = ringDelaySec.coerceIn(0, 30)
            return if (clamped == 0) SOFORT_RINGING_SETTLE_MS else clamped * 1_000L
        }

        /**
         * Unified call-state resolver across physical SIMs, Dual-SIM / eSIM profiles,
         * and Wi-Fi Calling (`Eingehender WLAN-Anruf` / IMS), where legacy `TelephonyManager.getCallState()`
         * frequently returns `0` (`CALL_STATE_IDLE`) even while ringing or active!
         */
        internal fun resolveUnifiedCallState(
            telecomInCall: Boolean,
            audioMode: Int,
            legacyTelephonyState: Int,
            subscriptionStates: Collection<Int>,
            secretaryAudioOverrideActive: Boolean
        ): Int {
            if (legacyTelephonyState == TelephonyManager.CALL_STATE_OFFHOOK ||
                subscriptionStates.any { it == TelephonyManager.CALL_STATE_OFFHOOK }
            ) {
                return TelephonyManager.CALL_STATE_OFFHOOK
            }
            if (!telecomInCall &&
                legacyTelephonyState == TelephonyManager.CALL_STATE_IDLE &&
                subscriptionStates.all { it == TelephonyManager.CALL_STATE_IDLE } &&
                audioMode != AudioManager.MODE_IN_CALL
            ) {
                return TelephonyManager.CALL_STATE_IDLE
            }
            if (audioMode == AudioManager.MODE_IN_CALL) {
                return TelephonyManager.CALL_STATE_OFFHOOK
            }
            if (!secretaryAudioOverrideActive && audioMode == AudioManager.MODE_IN_COMMUNICATION && telecomInCall) {
                return TelephonyManager.CALL_STATE_OFFHOOK
            }
            if (secretaryAudioOverrideActive && telecomInCall) {
                return TelephonyManager.CALL_STATE_OFFHOOK
            }
            if (legacyTelephonyState == TelephonyManager.CALL_STATE_RINGING ||
                subscriptionStates.any { it == TelephonyManager.CALL_STATE_RINGING } ||
                audioMode == AudioManager.MODE_RINGTONE ||
                telecomInCall
            ) {
                return TelephonyManager.CALL_STATE_RINGING
            }
            return TelephonyManager.CALL_STATE_IDLE
        }

        internal fun resolveForegroundServiceType(
            sdkInt: Int,
            includeMicrophone: Boolean,
            hasMicPermission: Boolean
        ): Int {
            if (sdkInt < Build.VERSION_CODES.UPSIDE_DOWN_CAKE) {
                return 0
            }
            return if (includeMicrophone && hasMicPermission) {
                ServiceInfo.FOREGROUND_SERVICE_TYPE_PHONE_CALL or ServiceInfo.FOREGROUND_SERVICE_TYPE_MICROPHONE
            } else {
                ServiceInfo.FOREGROUND_SERVICE_TYPE_PHONE_CALL
            }
        }

        internal fun shouldTreatAsSoftOffhookAfterAccept(
            telecomInCall: Boolean,
            audioMode: Int,
            unifiedState: Int,
            elapsedSinceAcceptMs: Long
        ): Boolean {
            if (unifiedState == TelephonyManager.CALL_STATE_OFFHOOK) return true
            if (!telecomInCall) return false
            if (unifiedState == TelephonyManager.CALL_STATE_IDLE) return false
            if (audioMode == AudioManager.MODE_RINGTONE) return false
            return elapsedSinceAcceptMs >= 2_200L
        }

        fun startForIncomingWhitelistCall(
            context: Context,
            matched: CachedWhitelistContact,
            rawNumber: String,
            sourceTag: String
        ) {
            val appCtx = context.applicationContext
            val pm = appCtx.getSystemService(PowerManager::class.java)
            val handoffWakeLock = try {
                pm?.newWakeLock(PowerManager.PARTIAL_WAKE_LOCK, "funkenwerk:concierge_handoff")?.apply {
                    setReferenceCounted(false)
                    acquire(6_000L)
                }
            } catch (_: Exception) {
                null
            }
            val svcIntent = Intent(appCtx, FunkiraPhoneConciergeService::class.java).apply {
                action = ACTION_INCOMING_WHITELIST_CALL
                putExtra(EXTRA_CONTACT_ID, matched.id)
                putExtra(EXTRA_CALLER_NAME, matched.fullName)
                putExtra(EXTRA_CALLER_NICKNAME, matched.nickname)
                putExtra(EXTRA_RELATION_TYPE, matched.relationType)
                putExtra(EXTRA_CALLER_PHONE, rawNumber)
                putExtra(EXTRA_SYSTEM_INSTRUCTIONS, matched.systemInstructions)
                putExtra(EXTRA_RING_DELAY_SEC, PhoneConciergeManager.getRingDelaySeconds(appCtx))
            }

            try {
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                    appCtx.startForegroundService(svcIntent)
                } else {
                    appCtx.startService(svcIntent)
                }
            } catch (e: Exception) {
                try {
                    if (handoffWakeLock?.isHeld == true) handoffWakeLock.release()
                } catch (_: Exception) { }
                PhoneConciergeManager.appendDiagnosticTrace(
                    appCtx,
                    "Service-Start fehlgeschlagen ($sourceTag): ${e.javaClass.simpleName}"
                )
                ErrorSentinelReporter.captureError(
                    context = appCtx,
                    tag = sourceTag,
                    message = "FunkiraPhoneConciergeService konnte bei eingehendem Whitelist-Anruf nicht gestartet werden: ${e.message}",
                    throwable = e
                )
            }
        }
    }

    private val serviceScope = CoroutineScope(SupervisorJob() + Dispatchers.IO)
    private var activeCallJob: Job? = null
    @Volatile
    private var currentFgsIncludesMic: Boolean = false

    override fun onCreate() {
        super.onCreate()
        ensureChannel()
    }

    override fun onBind(intent: Intent?): IBinder? = null

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        ServiceLocator.ensureInitialized(applicationContext)

        if (intent?.action == ACTION_MANUAL_STOP) {
            hangUpCallIfOffhook()
            restoreAudioRouting()
            currentFgsIncludesMic = false
            stopSelf()
            return START_NOT_STICKY
        }

        val callerName = intent?.getStringExtra(EXTRA_CALLER_NAME)?.takeIf { it.isNotBlank() } ?: "Kontakt"
        val nickname = intent?.getStringExtra(EXTRA_CALLER_NICKNAME).orEmpty()
        val displayLabel = nickname.ifBlank { callerName }

        if (activeCallJob?.isActive == true) {
            // Preserve current foreground service type if a fallback broadcast arrives mid-session
            val title = if (currentFgsIncludesMic) "Funkira spricht mit $displayLabel" else "Anruf von $displayLabel"
            val sub = if (currentFgsIncludesMic) "Anliegen wird live aufgenommen" else "Funkira Sekretärin bereit..."
            promoteToForeground(title, sub, includeMicrophone = currentFgsIncludesMic)
            return START_NOT_STICKY
        }

        // While phone is still RINGING in background, promote ONLY as PHONE_CALL (no background MIC start before OFFHOOK!)
        currentFgsIncludesMic = false
        promoteToForeground("Anruf von $displayLabel", "Funkira Sekretärin bereit...", includeMicrophone = false)

        val contactId = intent?.getStringExtra(EXTRA_CONTACT_ID)
        val relationType = intent?.getStringExtra(EXTRA_RELATION_TYPE)
        val callerPhone = intent?.getStringExtra(EXTRA_CALLER_PHONE)
        val sysInstructions = intent?.getStringExtra(EXTRA_SYSTEM_INSTRUCTIONS)
        val ringDelaySec = (intent?.getIntExtra(EXTRA_RING_DELAY_SEC, 12) ?: 12).coerceIn(0, 30)

        activeCallJob = serviceScope.launch {
            runCallLifecycle(
                callId = UUID.randomUUID().toString(),
                contactId = contactId,
                callerName = callerName,
                nickname = nickname,
                displayLabel = displayLabel,
                relationType = relationType,
                callerPhone = callerPhone,
                systemInstructions = sysInstructions,
                ringDelaySec = ringDelaySec
            )
        }

        return START_NOT_STICKY
    }

    private suspend fun runCallLifecycle(
        callId: String,
        contactId: String?,
        callerName: String,
        nickname: String,
        displayLabel: String,
        relationType: String?,
        callerPhone: String?,
        systemInstructions: String?,
        ringDelaySec: Int
    ) {
        val api = ServiceLocator.getOrganizerApi()
        val telephony = getSystemService(Context.TELEPHONY_SERVICE) as? TelephonyManager
        val telecom = getSystemService(Context.TELECOM_SERVICE) as? TelecomManager
        val audioManager = getSystemService(Context.AUDIO_SERVICE) as? AudioManager
        val powerManager = getSystemService(Context.POWER_SERVICE) as? PowerManager
        val lifecycleWakeLock = try {
            powerManager?.newWakeLock(PowerManager.PARTIAL_WAKE_LOCK, "funkenwerk:concierge_lifecycle")?.apply {
                setReferenceCounted(false)
                acquire(130_000L)
            }
        } catch (_: Exception) {
            null
        }
        val subStates = ConcurrentHashMap<Int, Int>()
        val registeredCallbacks = registerAllSubscriptionCallStateListeners(telephony, subStates)
        var secretaryAudioOverrideActive = false
        val miniPcBtAvailableAtRing = isMiniPcBluetoothScoAvailable()
        var signalledMiniPcRinging = false
        var preWarmStarted = false
        var reachedOffhook = false
        var notePersisted = false
        val partialTranscriptRef = AtomicReference<List<String>>(emptyList())
        val greetingName = nickname.trim()
            .split(',', '/')
            .firstOrNull()
            ?.trim()
            ?.takeIf { it.isNotBlank() }
            ?: callerName.trim().split(' ').firstOrNull().orEmpty().ifBlank { displayLabel }
        val callStartedAtMs = android.os.SystemClock.elapsedRealtime()

        try {
            PhoneConciergeManager.appendDiagnosticTrace(
                applicationContext,
                "Service aktiv (delay=${ringDelaySec}s, inCall=${readTelecomInCallSafe(telecom)}, mode=${audioManager?.mode ?: -1})"
            )

            // If ringDelaySec is short (e.g. "Sofort" = 0s), pre-warm Gemini Live credentials immediately in parallel!
            if (ringDelaySec <= 4) {
                preWarmStarted = true
                serviceScope.launch { StandalonePhoneSecretaryEngine.preWarmCredentials(applicationContext) }
            }

            if (miniPcBtAvailableAtRing) {
                try {
                    api.signalPhoneConciergeCall(
                        PhoneConciergeSignalBody(
                            call_id = callId,
                            state = "ringing",
                            contact_id = contactId?.takeUnless { it == "default-papa" },
                            caller_name = callerName,
                            caller_nickname = nickname,
                            relation_type = relationType,
                            caller_phone = callerPhone,
                            system_instructions = systemInstructions,
                            audio_mode = "bluetooth_hfp"
                        )
                    )
                    signalledMiniPcRinging = true
                } catch (_: Exception) {
                }
            }

            // Phase 1: Wait up to 4.0s for Telecom / eSIM / Wi-Fi Calling to report RINGING (via TelecomManager.isInCall,
            // AudioManager, or per-subscription TelephonyCallback).
            var ringingStartedElapsedMs = 0L
            var sawRingingOnce = false
            val waitRingStartMs = android.os.SystemClock.elapsedRealtime()
            while ((android.os.SystemClock.elapsedRealtime() - waitRingStartMs) < 4_000L) {
                val st = readUnifiedCallState(telephony, telecom, audioManager, subStates, secretaryAudioOverrideActive)
                if (st == TelephonyManager.CALL_STATE_RINGING) {
                    ringingStartedElapsedMs = android.os.SystemClock.elapsedRealtime()
                    sawRingingOnce = true
                    break
                }
                if (st == TelephonyManager.CALL_STATE_OFFHOOK) {
                    PhoneConciergeManager.appendDiagnosticTrace(applicationContext, "Abbruch: manuell angenommen")
                    if (signalledMiniPcRinging) {
                        try {
                            api.signalPhoneConciergeCall(PhoneConciergeSignalBody(call_id = callId, state = "cancelled"))
                        } catch (_: Exception) { }
                    }
                    stopSelf()
                    return
                }
                delay(90L)
            }

            if (ringingStartedElapsedMs == 0L) {
                if (ringDelaySec > 0) {
                    PhoneConciergeManager.appendDiagnosticTrace(applicationContext, "Kein Klingelsignal erkannt -> Ende")
                    if (signalledMiniPcRinging) {
                        try {
                            api.signalPhoneConciergeCall(PhoneConciergeSignalBody(call_id = callId, state = "cancelled"))
                        } catch (_: Exception) { }
                    }
                    stopSelf()
                    return
                } else {
                    // For "Sofort" (0s) on special VoWiFi/IMS stacks, never abort before trying acceptRingingCall()!
                    ringingStartedElapsedMs = android.os.SystemClock.elapsedRealtime()
                }
            }

            // Phase 2: Wait effective ring duration ("Sofort" = settle so Telecom finishes binding InCallService;
            // >0s = exact ringDelaySec countdown), aborting immediately if Alina answers or declines!
            val targetRingWaitMs = computeEffectiveRingWaitMs(ringDelaySec)
            val preWarmAtRemainingMs = 3_500L
            while (true) {
                val elapsedRingingMs = android.os.SystemClock.elapsedRealtime() - ringingStartedElapsedMs
                val remainingMs = targetRingWaitMs - elapsedRingingMs
                if (!preWarmStarted && remainingMs <= preWarmAtRemainingMs) {
                    preWarmStarted = true
                    serviceScope.launch { StandalonePhoneSecretaryEngine.preWarmCredentials(applicationContext) }
                }
                if (remainingMs <= 0L) break

                val stepMs = remainingMs.coerceAtMost(200L).coerceAtLeast(50L)
                delay(stepMs)

                val stateNow = readUnifiedCallState(telephony, telecom, audioManager, subStates, secretaryAudioOverrideActive)
                if (stateNow == TelephonyManager.CALL_STATE_RINGING) {
                    sawRingingOnce = true
                }
                if (stateNow == TelephonyManager.CALL_STATE_OFFHOOK || (sawRingingOnce && stateNow == TelephonyManager.CALL_STATE_IDLE)) {
                    PhoneConciergeManager.appendDiagnosticTrace(applicationContext, "Vor Annahme beendet (state=$stateNow)")
                    if (signalledMiniPcRinging) {
                        try {
                            api.signalPhoneConciergeCall(
                                PhoneConciergeSignalBody(call_id = callId, state = "cancelled")
                            )
                        } catch (_: Exception) { }
                    }
                    stopSelf()
                    return
                }
            }

            // Abort only if Alina already answered (OFFHOOK) or caller definitively hung up after ringing started
            val preAcceptState = readUnifiedCallState(telephony, telecom, audioManager, subStates, secretaryAudioOverrideActive)
            if (preAcceptState == TelephonyManager.CALL_STATE_OFFHOOK ||
                (sawRingingOnce && preAcceptState == TelephonyManager.CALL_STATE_IDLE)
            ) {
                PhoneConciergeManager.appendDiagnosticTrace(applicationContext, "Anruf vor Annahme beendet ($preAcceptState)")
                stopSelf()
                return
            }

            // Release WakeWordService hardware mic synchronously before picking up so AudioRecord has zero contention
            WakeWordService.releaseMicrophoneForCall(timeoutMs = 700L)
            if (!acceptIncomingCallSafe()) {
                NavigationBridge.isLiveCallActive = false
                PhoneConciergeManager.appendDiagnosticTrace(applicationContext, "Fehler: acceptRingingCall fehlgeschlagen")
                ErrorSentinelReporter.captureError(
                    context = applicationContext,
                    tag = "phone_secretary_telecom",
                    message = "Anruf von $displayLabel konnte nicht automatisch angenommen werden (TelecomManager.acceptRingingCall fehlgeschlagen).",
                    extraContext = mapOf("call_id" to callId)
                )
                stopSelf()
                return
            }
            PhoneConciergeManager.appendDiagnosticTrace(applicationContext, "acceptRingingCall() gesendet")

            // Phase 3: Wait up to 5.0s for 5G/VoLTE/Wi-Fi Calling IMS to confirm OFFHOOK (AudioManager.MODE_IN_CALL,
            // sub OFFHOOK, or soft-OFFHOOK when Telecom stays active after acceptRingingCall).
            // Retry acceptIncomingCallSafe() at 950ms, 2100ms, 3400ms if still ringing!
            var retryCount = 0
            val acceptStartMs = android.os.SystemClock.elapsedRealtime()
            while ((android.os.SystemClock.elapsedRealtime() - acceptStartMs) < 5_000L) {
                val inCallNow = readTelecomInCallSafe(telecom)
                val modeNow = try { audioManager?.mode ?: AudioManager.MODE_NORMAL } catch (_: Exception) { AudioManager.MODE_NORMAL }
                val st = readUnifiedCallState(telephony, telecom, audioManager, subStates, secretaryAudioOverrideActive)
                val elapsedAccept = android.os.SystemClock.elapsedRealtime() - acceptStartMs
                if (st == TelephonyManager.CALL_STATE_OFFHOOK ||
                    shouldTreatAsSoftOffhookAfterAccept(inCallNow, modeNow, st, elapsedAccept)
                ) {
                    reachedOffhook = true
                    break
                }
                if (sawRingingOnce && st == TelephonyManager.CALL_STATE_IDLE) {
                    break
                }
                if (retryCount == 0 && elapsedAccept >= 950L && st == TelephonyManager.CALL_STATE_RINGING) {
                    retryCount = 1
                    acceptIncomingCallSafe()
                } else if (retryCount == 1 && elapsedAccept >= 2_100L && st == TelephonyManager.CALL_STATE_RINGING) {
                    retryCount = 2
                    acceptIncomingCallSafe()
                } else if (retryCount == 2 && elapsedAccept >= 3_400L && st == TelephonyManager.CALL_STATE_RINGING) {
                    retryCount = 3
                    acceptIncomingCallSafe()
                }
                delay(120L)
            }

            if (!reachedOffhook) {
                PhoneConciergeManager.appendDiagnosticTrace(
                    applicationContext,
                    "Kein OFFHOOK erreicht (inCall=${readTelecomInCallSafe(telecom)}, mode=${audioManager?.mode ?: -1})"
                )
                ErrorSentinelReporter.captureError(
                    context = applicationContext,
                    tag = "phone_secretary_telecom",
                    message = "Anruf von $displayLabel wechselte nach Annahme nicht auf OFFHOOK.",
                    extraContext = mapOf("call_id" to callId, "ring_delay_sec" to ringDelaySec.toString())
                )
                stopSelf()
                return
            }

            PhoneConciergeManager.appendDiagnosticTrace(applicationContext, "Verbunden -> Funkira Sekretärin startet")

            // Call is confirmed OFFHOOK -> upgrade foreground service type to PHONE_CALL | MICROPHONE
            currentFgsIncludesMic = true
            val micFgsGranted = promoteToForeground(
                "Funkira spricht mit $displayLabel",
                "Anliegen wird live aufgenommen",
                includeMicrophone = true
            )
            delay(250L)

            // Check whether Funkenwerk-MiniPC Bluetooth SCO is actively connected AND Portal relay accepts it
            val useMiniPcWorkshopBridge = isMiniPcBluetoothScoAvailable() && routeCallAudioToMiniPcBluetooth()
            var handledByMiniPc = false

            if (useMiniPcWorkshopBridge) {
                val signalOk = try {
                    val res = api.signalPhoneConciergeCall(
                        PhoneConciergeSignalBody(
                            call_id = callId,
                            state = "accepted",
                            contact_id = contactId?.takeUnless { it == "default-papa" },
                            caller_name = callerName,
                            caller_nickname = nickname,
                            relation_type = relationType,
                            caller_phone = callerPhone,
                            system_instructions = systemInstructions,
                            audio_mode = "bluetooth_hfp"
                        )
                    )
                    res.success
                } catch (_: Exception) {
                    false
                }

                if (signalOk) {
                    handledByMiniPc = runMiniPcRelayPollingLoop(
                        callId = callId,
                        displayLabel = displayLabel,
                        isCallActive = {
                            readUnifiedCallState(telephony, telecom, audioManager, subStates, false) != TelephonyManager.CALL_STATE_IDLE
                        }
                    )
                    if (handledByMiniPc) {
                        notePersisted = true
                        serviceScope.launch {
                            try { PhoneConciergeManager.fetchRecentCalls(applicationContext) } catch (_: Exception) { }
                        }
                    }
                }
                if (!handledByMiniPc) {
                    restoreAudioRouting()
                }
            }

            // Standalone Smartphone Live Secretary Mode (default whenever Mini-PC is asleep, away, or unlinked!)
            val activeBeforeSecretary = readUnifiedCallState(telephony, telecom, audioManager, subStates, true) != TelephonyManager.CALL_STATE_IDLE
            if (!handledByMiniPc && activeBeforeSecretary) {
                if (signalledMiniPcRinging) {
                    serviceScope.launch {
                        try {
                            api.signalPhoneConciergeCall(PhoneConciergeSignalBody(call_id = callId, state = "cancelled"))
                        } catch (_: Exception) { }
                    }
                }

                secretaryAudioOverrideActive = true
                val result = StandalonePhoneSecretaryEngine.runSecretaryCall(
                    context = applicationContext,
                    callId = callId,
                    callerName = callerName,
                    greetingName = greetingName,
                    relationType = relationType,
                    systemInstructions = systemInstructions,
                    micFgsGranted = micFgsGranted,
                    onTranscriptUpdated = { lines -> partialTranscriptRef.set(lines) },
                    isCallStillOffhook = {
                        readUnifiedCallState(telephony, telecom, audioManager, subStates, true) == TelephonyManager.CALL_STATE_OFFHOOK
                    }
                )
                secretaryAudioOverrideActive = false

                val note = PendingPhoneSecretaryNote(
                    callId = callId,
                    contactId = contactId,
                    callerName = callerName,
                    greetingName = greetingName,
                    callerPhone = callerPhone,
                    summary = result.summary,
                    urgency = result.urgency,
                    callbackRequested = result.callbackRequested,
                    rahmenbedingungen = result.rahmenbedingungen,
                    actionRequired = result.actionRequired,
                    durationSeconds = result.durationSeconds,
                    transcript = result.transcript,
                    verification = result.verification
                )
                withContext(NonCancellable) {
                    PhoneConciergeManager.persistOrQueueSecretaryCompletion(applicationContext, note)
                    notePersisted = true
                }
                val statusHint = when (result.verification?.status) {
                    CallNoteVerificationEngine.STATUS_CORRECTED -> " (KI-korrigiert)"
                    CallNoteVerificationEngine.STATUS_UNCERTAIN -> " (Bitte prüfen)"
                    CallNoteVerificationEngine.STATUS_VERIFIED -> " (Geprüft)"
                    else -> ""
                }
                postSummaryNotification(displayLabel + statusHint, result.summary)
                PhoneConciergeManager.appendDiagnosticTrace(
                    applicationContext,
                    "Gespräch beendet & Notiz gespeichert$statusHint"
                )

                if (readUnifiedCallState(telephony, telecom, audioManager, subStates, false) != TelephonyManager.CALL_STATE_IDLE) {
                    hangUpCallIfOffhook()
                }
            }
        } catch (e: Exception) {
            PhoneConciergeManager.appendDiagnosticTrace(applicationContext, "Fehler: ${e.javaClass.simpleName}: ${e.message}")
            ErrorSentinelReporter.captureError(
                context = applicationContext,
                tag = "phone_secretary_lifecycle",
                message = "Unerwarteter Fehler im Telefon-Sekretärinnen-Ablauf: ${e.message}",
                throwable = e,
                extraContext = mapOf("call_id" to callId)
            )
            if (readUnifiedCallState(telephony, telecom, audioManager, subStates, false) != TelephonyManager.CALL_STATE_IDLE) {
                hangUpCallIfOffhook()
            }
        } finally {
            // Sicherheitsnetz: Jeder angenommene Anruf MUSS protokolliert werden, selbst bei Abbruch oder Exception!
            if (reachedOffhook && !notePersisted) {
                withContext(NonCancellable) {
                    try {
                        val partialLines = partialTranscriptRef.get().orEmpty()
                        val callerRaw = CallNoteVerificationEngine.extractCallerLines(partialLines).joinToString(" ")
                        val fallbackSum = StandalonePhoneSecretaryEngine.buildFallbackSummaryFromTranscript(callerRaw, greetingName)
                        val durSec = kotlin.math.max(1, ((android.os.SystemClock.elapsedRealtime() - callStartedAtMs) / 1000L).toInt())
                        val fallbackNote = PendingPhoneSecretaryNote(
                            callId = callId,
                            contactId = contactId,
                            callerName = callerName,
                            greetingName = greetingName,
                            callerPhone = callerPhone,
                            summary = fallbackSum,
                            urgency = "mittel",
                            callbackRequested = true,
                            actionRequired = "$greetingName zurückrufen.",
                            durationSeconds = durSec,
                            transcript = partialLines
                        )
                        PhoneConciergeManager.persistOrQueueSecretaryCompletion(applicationContext, fallbackNote)
                        postSummaryNotification(displayLabel, fallbackSum)
                        PhoneConciergeManager.appendDiagnosticTrace(
                            applicationContext,
                            "Notfall-Protokoll gespeichert (${partialLines.size} Zeilen)"
                        )
                    } catch (_: Exception) { }
                }
            }
            NavigationBridge.isLiveCallActive = false
            unregisterAllSubscriptionCallStateListeners(registeredCallbacks)
            currentFgsIncludesMic = false
            restoreAudioRouting()
            try {
                if (lifecycleWakeLock?.isHeld == true) lifecycleWakeLock.release()
            } catch (_: Exception) { }
            stopSelf()
        }
    }

    private suspend fun runMiniPcRelayPollingLoop(
        callId: String,
        displayLabel: String,
        isCallActive: () -> Boolean
    ): Boolean {
        val api = ServiceLocator.getOrganizerApi()
        var finalSummary: String? = null
        val startedMs = System.currentTimeMillis()
        var pollErrors = 0

        while (serviceScope.isActive && (System.currentTimeMillis() - startedMs) < 95_000L) {
            delay(1500L)

            if (!isCallActive()) {
                try {
                    api.signalPhoneConciergeCall(PhoneConciergeSignalBody(call_id = callId, state = "ended"))
                } catch (_: Exception) { }
                if (!finalSummary.isNullOrBlank()) {
                    postSummaryNotification(displayLabel, finalSummary!!)
                }
                return true
            }

            // If Bluetooth SCO to Mini-PC dropped right after answering (e.g. Mini-PC is actually asleep),
            // fail over immediately within the first 4.5s to StandalonePhoneSecretaryEngine on the phone!
            if ((System.currentTimeMillis() - startedMs) < 4_500L && !isMiniPcBluetoothScoAvailable()) {
                return false
            }

            try {
                val pollRes = api.pollPhoneConciergeState()
                pollErrors = 0
                val poll = pollRes.data
                if (poll != null) {
                    val lastDone = poll.last_completed_call
                    if (lastDone != null && (lastDone.call_id == callId || !lastDone.summary.isNullOrBlank())) {
                        finalSummary = lastDone.summary
                    }
                    if (poll.hangup_requested || poll.active_call?.hangup_requested == true || poll.active_call?.state == "completed") {
                        if (finalSummary.isNullOrBlank()) {
                            finalSummary = poll.active_call?.summary
                        }
                        hangUpCallIfOffhook()
                        break
                    }
                }
            } catch (_: Exception) {
                pollErrors++
                if (pollErrors >= 2 && (System.currentTimeMillis() - startedMs) < 6_000L) {
                    return false
                }
            }
        }

        if (isCallActive()) {
            hangUpCallIfOffhook()
        }
        if (!finalSummary.isNullOrBlank()) {
            postSummaryNotification(displayLabel, finalSummary!!)
        }
        return true
    }

    @SuppressLint("MissingPermission")
    private fun registerAllSubscriptionCallStateListeners(
        baseTelephony: TelephonyManager?,
        subStates: ConcurrentHashMap<Int, Int>
    ): List<Pair<TelephonyManager, TelephonyCallback>> {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.S || baseTelephony == null) return emptyList()
        if (ContextCompat.checkSelfPermission(this, Manifest.permission.READ_PHONE_STATE) != PackageManager.PERMISSION_GRANTED) {
            return emptyList()
        }
        val list = mutableListOf<Pair<TelephonyManager, TelephonyCallback>>()
        val subIds = mutableSetOf<Int>()
        try {
            val sm = getSystemService(SubscriptionManager::class.java)
            sm?.activeSubscriptionInfoList?.forEach { info ->
                subIds.add(info.subscriptionId)
            }
        } catch (_: Exception) { }

        val targetManagers: List<Pair<Int, TelephonyManager>> = if (subIds.isNotEmpty()) {
            subIds.map { id -> id to baseTelephony.createForSubscriptionId(id) }
        } else {
            listOf(-1 to baseTelephony)
        }

        for ((subId, tmForSub) in targetManagers) {
            try {
                val cb = object : TelephonyCallback(), TelephonyCallback.CallStateListener {
                    override fun onCallStateChanged(state: Int) {
                        subStates[subId] = state
                    }
                }
                tmForSub.registerTelephonyCallback(mainExecutor, cb)
                list.add(tmForSub to cb)
            } catch (_: Exception) { }
        }
        return list
    }

    private fun unregisterAllSubscriptionCallStateListeners(
        registered: List<Pair<TelephonyManager, TelephonyCallback>>
    ) {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.S) return
        for ((tm, cb) in registered) {
            try {
                tm.unregisterTelephonyCallback(cb)
            } catch (_: Exception) { }
        }
    }

    @SuppressLint("MissingPermission")
    private fun readTelecomInCallSafe(telecom: TelecomManager?): Boolean {
        if (telecom == null) return false
        if (ContextCompat.checkSelfPermission(this, Manifest.permission.READ_PHONE_STATE) != PackageManager.PERMISSION_GRANTED) {
            return false
        }
        return try {
            telecom.isInCall
        } catch (_: Exception) {
            false
        }
    }

    private fun readUnifiedCallState(
        telephony: TelephonyManager?,
        telecom: TelecomManager?,
        audioManager: AudioManager?,
        subStates: Map<Int, Int>,
        secretaryAudioOverrideActive: Boolean
    ): Int {
        val inCall = readTelecomInCallSafe(telecom)
        val mode = try { audioManager?.mode ?: AudioManager.MODE_NORMAL } catch (_: Exception) { AudioManager.MODE_NORMAL }
        val legacy = readCallStateSafe(telephony)
        return resolveUnifiedCallState(
            telecomInCall = inCall,
            audioMode = mode,
            legacyTelephonyState = legacy,
            subscriptionStates = subStates.values,
            secretaryAudioOverrideActive = secretaryAudioOverrideActive
        )
    }

    @Suppress("DEPRECATION")
    private fun readCallStateSafe(telephony: TelephonyManager?): Int {
        if (telephony == null) return TelephonyManager.CALL_STATE_IDLE
        if (ContextCompat.checkSelfPermission(this, Manifest.permission.READ_PHONE_STATE) != PackageManager.PERMISSION_GRANTED) {
            return TelephonyManager.CALL_STATE_IDLE
        }
        return try {
            telephony.callState
        } catch (_: SecurityException) {
            TelephonyManager.CALL_STATE_IDLE
        }
    }

    @SuppressLint("MissingPermission")
    @Suppress("DEPRECATION")
    private fun acceptIncomingCallSafe(): Boolean {
        if (ContextCompat.checkSelfPermission(this, Manifest.permission.ANSWER_PHONE_CALLS) != PackageManager.PERMISSION_GRANTED) {
            return false
        }
        val tm = getSystemService(Context.TELECOM_SERVICE) as? TelecomManager ?: return false
        return try {
            try {
                tm.acceptRingingCall(VideoProfile.STATE_AUDIO_ONLY)
            } catch (_: Exception) {
                tm.acceptRingingCall()
            }
            true
        } catch (e: Exception) {
            PhoneConciergeManager.appendDiagnosticTrace(
                applicationContext,
                "acceptRingingCall Exception: ${e.javaClass.simpleName}: ${e.message}"
            )
            false
        }
    }

    @SuppressLint("MissingPermission")
    @Suppress("DEPRECATION")
    private fun hangUpCallIfOffhook() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.P) return
        if (ContextCompat.checkSelfPermission(this, Manifest.permission.ANSWER_PHONE_CALLS) != PackageManager.PERMISSION_GRANTED) {
            return
        }
        val tm = getSystemService(Context.TELECOM_SERVICE) as? TelecomManager ?: return
        try {
            tm.endCall()
        } catch (_: Exception) { }
    }

    private fun isMiniPcBluetoothScoAvailable(): Boolean {
        val am = getSystemService(Context.AUDIO_SERVICE) as? AudioManager ?: return false
        return try {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                am.availableCommunicationDevices.any {
                    it.type == AudioDeviceInfo.TYPE_BLUETOOTH_SCO &&
                        it.productName.toString().contains("Funkenwerk", ignoreCase = true)
                }
            } else {
                false
            }
        } catch (_: Exception) {
            false
        }
    }

    @Suppress("DEPRECATION")
    private fun routeCallAudioToMiniPcBluetooth(): Boolean {
        val am = getSystemService(Context.AUDIO_SERVICE) as? AudioManager ?: return false
        try {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                val preferredBt = am.availableCommunicationDevices.firstOrNull {
                    it.type == AudioDeviceInfo.TYPE_BLUETOOTH_SCO &&
                        it.productName.toString().contains("Funkenwerk", ignoreCase = true)
                }
                if (preferredBt != null) {
                    return am.setCommunicationDevice(preferredBt)
                }
            }
        } catch (_: Exception) { }
        return false
    }

    @Suppress("DEPRECATION")
    private fun restoreAudioRouting() {
        val am = getSystemService(Context.AUDIO_SERVICE) as? AudioManager ?: return
        try {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                am.clearCommunicationDevice()
            } else {
                am.isBluetoothScoOn = false
                am.stopBluetoothSco()
            }
        } catch (_: Exception) { }
    }

    private fun ensureChannel() {
        de.meinseelenfunke.app.util.NotificationBadgeGuard.ensureSilentBackgroundChannels(this)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val nm = getSystemService(NotificationManager::class.java) ?: return
            val statusCh = NotificationChannel(
                STATUS_CHANNEL_ID,
                "Funkira Anruf-Status",
                NotificationManager.IMPORTANCE_LOW
            ).apply {
                description = "Aktiver Telefon-Assistent Status während eines Gesprächs"
                setShowBadge(false)
                enableLights(false)
                enableVibration(false)
                setSound(null, null)
            }
            nm.createNotificationChannel(statusCh)

            val ch = NotificationChannel(
                CHANNEL_ID,
                "Funkira Telefon-Assistent",
                NotificationManager.IMPORTANCE_HIGH
            ).apply {
                description = "Whitelist-Sekretärin & Anrufnotizen"
            }
            nm.createNotificationChannel(ch)
        }
    }

    private fun promoteToForeground(title: String, body: String, includeMicrophone: Boolean): Boolean {
        ensureChannel()
        val stopIntent = Intent(this, FunkiraPhoneConciergeService::class.java).apply {
            action = ACTION_MANUAL_STOP
        }
        val stopPending = PendingIntent.getService(
            this,
            1,
            stopIntent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )
        val openPending = PendingIntent.getActivity(
            this,
            0,
            Intent(this, MainActivity::class.java),
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )

        val notif = NotificationCompat.Builder(this, STATUS_CHANNEL_ID)
            .setSmallIcon(R.drawable.ic_stat_funkenwerk)
            .setColor(0xFFC5A059.toInt())
            .setContentTitle(title)
            .setContentText(body)
            .setContentIntent(openPending)
            .setOngoing(true)
            .setSilent(true)
            .addAction(0, "Auflegen", stopPending)
            .build()

        val hasMicPerm = ContextCompat.checkSelfPermission(this, Manifest.permission.RECORD_AUDIO) == PackageManager.PERMISSION_GRANTED
        val fgType = resolveForegroundServiceType(Build.VERSION.SDK_INT, includeMicrophone, hasMicPerm)
        try {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.UPSIDE_DOWN_CAKE) {
                startForeground(ACTIVE_NOTIF_ID, notif, fgType)
            } else {
                startForeground(ACTIVE_NOTIF_ID, notif)
            }
            return includeMicrophone && hasMicPerm
        } catch (e: Exception) {
            ErrorSentinelReporter.captureAutoHeal(
                context = applicationContext,
                component = "phone_secretary_fgs",
                title = "Foreground-Service-Typ auf PHONE_CALL zurückgestuft",
                message = e.message ?: "Mikrofon-FGS-Upgrade vom Betriebssystem abgelehnt",
                healSummary = "Dienst läuft stabil im PHONE_CALL-Modus weiter.",
                extraContext = mapOf("include_mic" to includeMicrophone.toString())
            )
            try {
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.UPSIDE_DOWN_CAKE) {
                    startForeground(ACTIVE_NOTIF_ID, notif, ServiceInfo.FOREGROUND_SERVICE_TYPE_PHONE_CALL)
                } else {
                    startForeground(ACTIVE_NOTIF_ID, notif)
                }
            } catch (_: Exception) { }
            return false
        }
    }

    private fun postSummaryNotification(callerLabel: String, summary: String) {
        val cleanCaller = callerLabel.trim()
        val cleanSummary = summary.trim()
        if (cleanCaller.isEmpty() && cleanSummary.isEmpty()) return
        ensureChannel()
        val nm = getSystemService(NotificationManager::class.java) ?: return
        val tapIntent = Intent(this, MainActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP
            putExtra("navigate_to", "organizer")
            putExtra("open_subtab", 7)
            de.meinseelenfunke.app.util.SecurityIntentHelper.signIntent(this@FunkiraPhoneConciergeService, this)
        }
        val openPending = PendingIntent.getActivity(
            this,
            2,
            tapIntent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )
        val notif = NotificationCompat.Builder(this, CHANNEL_ID)
            .setSmallIcon(R.drawable.ic_stat_funkenwerk)
            .setColor(0xFFC5A059.toInt())
            .setContentTitle("📞 Funkira Sekretärin · ${cleanCaller.ifBlank { "Anruf" }}")
            .setContentText(cleanSummary)
            .setStyle(NotificationCompat.BigTextStyle().bigText(cleanSummary))
            .setContentIntent(openPending)
            .setAutoCancel(true)
            .build()
        try {
            if (Build.VERSION.SDK_INT < Build.VERSION_CODES.TIRAMISU ||
                ContextCompat.checkSelfPermission(this, Manifest.permission.POST_NOTIFICATIONS) == PackageManager.PERMISSION_GRANTED
            ) {
                nm.notify(SUMMARY_NOTIF_ID, notif)
            }
        } catch (_: Exception) { }
    }

    override fun onDestroy() {
        activeCallJob?.cancel()
        serviceScope.cancel()
        restoreAudioRouting()
        super.onDestroy()
    }
}
