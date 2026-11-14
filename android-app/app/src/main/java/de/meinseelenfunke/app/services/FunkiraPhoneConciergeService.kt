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
import android.telecom.TelecomManager
import android.telephony.TelephonyManager
import androidx.core.content.ContextCompat
import de.meinseelenfunke.app.MainActivity
import de.meinseelenfunke.app.R
import de.meinseelenfunke.app.data.api.PhoneConciergeSignalBody
import de.meinseelenfunke.app.di.ServiceLocator
import de.meinseelenfunke.app.util.ErrorSentinelReporter
import de.meinseelenfunke.app.util.PendingPhoneSecretaryNote
import de.meinseelenfunke.app.util.PhoneConciergeManager
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.Job
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.cancel
import kotlinx.coroutines.delay
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch
import java.util.UUID

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
        private const val ACTIVE_NOTIF_ID = 1044
        private const val SUMMARY_NOTIF_ID = 1045

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
    }

    private val serviceScope = CoroutineScope(SupervisorJob() + Dispatchers.IO)
    private var activeCallJob: Job? = null

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
            stopSelf()
            return START_NOT_STICKY
        }

        val callerName = intent?.getStringExtra(EXTRA_CALLER_NAME)?.takeIf { it.isNotBlank() } ?: "Kontakt"
        val nickname = intent?.getStringExtra(EXTRA_CALLER_NICKNAME).orEmpty()
        val displayLabel = nickname.ifBlank { callerName }

        // While phone is still RINGING in background, promote ONLY as PHONE_CALL (no background MIC start before OFFHOOK!)
        promoteToForeground("Anruf von $displayLabel", "Funkira Sekretärin bereit...", includeMicrophone = false)

        if (activeCallJob?.isActive == true) {
            return START_NOT_STICKY
        }

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
        val miniPcBtAvailableAtRing = isMiniPcBluetoothScoAvailable()
        var signalledMiniPcRinging = false
        var preWarmStarted = false

        try {
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

            // If ringDelaySec is very short (<= 3s), start pre-warming credentials immediately
            if (ringDelaySec <= 3) {
                preWarmStarted = true
                serviceScope.launch { StandalonePhoneSecretaryEngine.preWarmCredentials(applicationContext) }
            }

            // Wait ringDelaySec in 500 ms slices; abort immediately if Alina answers or declines!
            val totalSlices = ringDelaySec * 2
            val preWarmTriggerSlice = (totalSlices - 5).coerceAtLeast(0) // ~2.5s before answering
            for (slice in 0 until totalSlices) {
                if (!preWarmStarted && slice >= preWarmTriggerSlice) {
                    preWarmStarted = true
                    serviceScope.launch { StandalonePhoneSecretaryEngine.preWarmCredentials(applicationContext) }
                }
                delay(500L)
                val stateNow = readCallStateSafe(telephony)
                if (stateNow == TelephonyManager.CALL_STATE_IDLE || stateNow == TelephonyManager.CALL_STATE_OFFHOOK) {
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

            // Final check that phone is still ringing, then accept call!
            if (readCallStateSafe(telephony) != TelephonyManager.CALL_STATE_RINGING) {
                stopSelf()
                return
            }

            if (!acceptIncomingCallSafe()) {
                ErrorSentinelReporter.captureError(
                    context = applicationContext,
                    tag = "phone_secretary_telecom",
                    message = "Anruf von $displayLabel konnte nicht automatisch angenommen werden (TelecomManager.acceptRingingCall fehlgeschlagen).",
                    extraContext = mapOf("call_id" to callId)
                )
                stopSelf()
                return
            }

            // Call is now OFFHOOK -> upgrade foreground service type to PHONE_CALL | MICROPHONE
            promoteToForeground("Funkira spricht mit $displayLabel", "Anliegen wird live aufgenommen", includeMicrophone = true)
            delay(450L)

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
                        telephony = telephony
                    )
                }
                if (!handledByMiniPc) {
                    restoreAudioRouting()
                }
            }

            // Standalone Smartphone Live Secretary Mode (default whenever Mini-PC is asleep, away, or unlinked!)
            if (!handledByMiniPc && readCallStateSafe(telephony) == TelephonyManager.CALL_STATE_OFFHOOK) {
                if (signalledMiniPcRinging) {
                    serviceScope.launch {
                        try {
                            api.signalPhoneConciergeCall(PhoneConciergeSignalBody(call_id = callId, state = "cancelled"))
                        } catch (_: Exception) { }
                    }
                }

                val greetingName = nickname.trim()
                    .split(',', '/')
                    .firstOrNull()
                    ?.trim()
                    ?.takeIf { it.isNotBlank() }
                    ?: callerName.trim().split(' ').firstOrNull().orEmpty().ifBlank { displayLabel }

                val result = StandalonePhoneSecretaryEngine.runSecretaryCall(
                    context = applicationContext,
                    callId = callId,
                    callerName = callerName,
                    greetingName = greetingName,
                    relationType = relationType,
                    systemInstructions = systemInstructions,
                    isCallStillOffhook = { readCallStateSafe(telephony) == TelephonyManager.CALL_STATE_OFFHOOK }
                )

                val note = PendingPhoneSecretaryNote(
                    callId = callId,
                    contactId = contactId,
                    callerName = callerName,
                    greetingName = greetingName,
                    callerPhone = callerPhone,
                    summary = result.summary,
                    urgency = result.urgency,
                    callbackRequested = result.callbackRequested
                )
                PhoneConciergeManager.persistOrQueueSecretaryCompletion(applicationContext, note)
                postSummaryNotification(displayLabel, result.summary)

                if (readCallStateSafe(telephony) == TelephonyManager.CALL_STATE_OFFHOOK) {
                    hangUpCallIfOffhook()
                }
            }
        } catch (e: Exception) {
            ErrorSentinelReporter.captureError(
                context = applicationContext,
                tag = "phone_secretary_lifecycle",
                message = "Unerwarteter Fehler im Telefon-Sekretärinnen-Ablauf: ${e.message}",
                throwable = e,
                extraContext = mapOf("call_id" to callId)
            )
            if (readCallStateSafe(telephony) == TelephonyManager.CALL_STATE_OFFHOOK) {
                hangUpCallIfOffhook()
            }
        } finally {
            restoreAudioRouting()
            stopSelf()
        }
    }

    private suspend fun runMiniPcRelayPollingLoop(
        callId: String,
        displayLabel: String,
        telephony: TelephonyManager?
    ): Boolean {
        val api = ServiceLocator.getOrganizerApi()
        var finalSummary: String? = null
        val startedMs = System.currentTimeMillis()
        var pollErrors = 0

        while (serviceScope.isActive && (System.currentTimeMillis() - startedMs) < 95_000L) {
            delay(1500L)

            if (readCallStateSafe(telephony) == TelephonyManager.CALL_STATE_IDLE) {
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

        if (readCallStateSafe(telephony) == TelephonyManager.CALL_STATE_OFFHOOK) {
            hangUpCallIfOffhook()
        }
        if (!finalSummary.isNullOrBlank()) {
            postSummaryNotification(displayLabel, finalSummary!!)
        }
        return true
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
            tm.acceptRingingCall()
            true
        } catch (_: Exception) {
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
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val nm = getSystemService(NotificationManager::class.java) ?: return
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

    private fun promoteToForeground(title: String, body: String, includeMicrophone: Boolean) {
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

        val builder = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            Notification.Builder(this, CHANNEL_ID)
        } else {
            @Suppress("DEPRECATION")
            Notification.Builder(this)
        }

        val notif = builder
            .setSmallIcon(R.mipmap.ic_launcher)
            .setContentTitle(title)
            .setContentText(body)
            .setContentIntent(openPending)
            .setOngoing(true)
            .addAction(
                Notification.Action.Builder(null, "Auflegen", stopPending).build()
            )
            .build()

        val hasMicPerm = ContextCompat.checkSelfPermission(this, Manifest.permission.RECORD_AUDIO) == PackageManager.PERMISSION_GRANTED
        val fgType = resolveForegroundServiceType(Build.VERSION.SDK_INT, includeMicrophone, hasMicPerm)
        try {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.UPSIDE_DOWN_CAKE) {
                startForeground(ACTIVE_NOTIF_ID, notif, fgType)
            } else {
                startForeground(ACTIVE_NOTIF_ID, notif)
            }
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
        }
    }

    private fun postSummaryNotification(callerLabel: String, summary: String) {
        val nm = getSystemService(NotificationManager::class.java) ?: return
        val openPending = PendingIntent.getActivity(
            this,
            2,
            Intent(this, MainActivity::class.java),
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )
        val builder = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            Notification.Builder(this, CHANNEL_ID)
        } else {
            @Suppress("DEPRECATION")
            Notification.Builder(this)
        }
        val notif = builder
            .setSmallIcon(R.mipmap.ic_launcher)
            .setContentTitle("📞 Funkira Sekretärin · $callerLabel")
            .setContentText(summary)
            .setStyle(Notification.BigTextStyle().bigText(summary))
            .setContentIntent(openPending)
            .setAutoCancel(true)
            .build()
        try {
            nm.notify(SUMMARY_NOTIF_ID, notif)
        } catch (_: Exception) { }
    }

    override fun onDestroy() {
        activeCallJob?.cancel()
        serviceScope.cancel()
        restoreAudioRouting()
        super.onDestroy()
    }
}
