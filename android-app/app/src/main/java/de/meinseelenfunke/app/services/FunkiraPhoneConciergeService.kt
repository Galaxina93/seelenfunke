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
 * Foreground Phone-Concierge Service:
 * 1. Waits `ringDelaySeconds` while checking every 500 ms whether Alina answered or declined.
 * 2. Accepts the ringing Whitelist call via `TelecomManager.acceptRingingCall()`.
 * 3. Bridges cellular call audio to paired `Funkenwerk-MiniPC` over Bluetooth HFP (`TYPE_BLUETOOTH_SCO`).
 * 4. Signals `accepted` to Portal so Funkira on Mini-PC speaks immediately (with 3D Smartphone in hand)
 *    and polls `/api/system/phone-concierge/poll` to hang up cleanly once Funkira finishes.
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

        promoteToForeground("Anruf von $displayLabel", "Funkira Telefon-Assistent bereit...")

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

        try {
            // 1. Pre-notify backend that a whitelisted call is ringing
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
            } catch (_: Exception) { }

            // 2. Wait ringDelaySec in 500 ms slices; abort immediately if Alina answers or declines!
            val totalSlices = ringDelaySec * 2
            for (slice in 0 until totalSlices) {
                delay(500L)
                val stateNow = readCallStateSafe(telephony)
                if (stateNow == TelephonyManager.CALL_STATE_IDLE || stateNow == TelephonyManager.CALL_STATE_OFFHOOK) {
                    // Alina picked up herself (OFFHOOK) or caller hung up (IDLE) -> zero interference!
                    try {
                        api.signalPhoneConciergeCall(
                            PhoneConciergeSignalBody(call_id = callId, state = "cancelled")
                        )
                    } catch (_: Exception) { }
                    stopSelf()
                    return
                }
            }

            // 3. Final check that phone is still ringing, then accept call!
            if (readCallStateSafe(telephony) != TelephonyManager.CALL_STATE_RINGING) {
                stopSelf()
                return
            }

            if (!acceptIncomingCallSafe()) {
                stopSelf()
                return
            }

            promoteToForeground("Funkira telefoniert mit $displayLabel", "Anrufnotiz wird aufgenommen")
            delay(650L)

            // 4. Route call audio to paired Funkenwerk-MiniPC Bluetooth HFP gateway
            val hfpRouted = routeCallAudioToMiniPcBluetooth()

            // 5. Signal `accepted` so Mini-PC Funkira starts speaking immediately
            try {
                api.signalPhoneConciergeCall(
                    PhoneConciergeSignalBody(
                        call_id = callId,
                        state = "accepted",
                        contact_id = contactId?.takeUnless { it == "default-papa" },
                        caller_name = callerName,
                        caller_nickname = nickname,
                        relation_type = relationType,
                        caller_phone = callerPhone,
                        system_instructions = systemInstructions,
                        audio_mode = if (hfpRouted) "bluetooth_hfp" else "speaker_fallback"
                    )
                )
            } catch (_: Exception) { }

            // 6. Poll every 1.5s until Funkira finishes & requests hangup, or caller hangs up (max 95s safety cap)
            var finalSummary: String? = null
            val startedMs = System.currentTimeMillis()
            while (serviceScope.isActive && (System.currentTimeMillis() - startedMs) < 95_000L) {
                delay(1500L)

                if (readCallStateSafe(telephony) == TelephonyManager.CALL_STATE_IDLE) {
                    try {
                        api.signalPhoneConciergeCall(
                            PhoneConciergeSignalBody(call_id = callId, state = "ended")
                        )
                    } catch (_: Exception) { }
                    break
                }

                try {
                    val pollRes = api.pollPhoneConciergeState()
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
                } catch (_: Exception) { }
            }

            if (readCallStateSafe(telephony) == TelephonyManager.CALL_STATE_OFFHOOK) {
                hangUpCallIfOffhook()
            }

            if (!finalSummary.isNullOrBlank()) {
                postSummaryNotification(displayLabel, finalSummary!!)
            }
        } finally {
            restoreAudioRouting()
            stopSelf()
        }
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

    @Suppress("DEPRECATION")
    private fun routeCallAudioToMiniPcBluetooth(): Boolean {
        val am = getSystemService(Context.AUDIO_SERVICE) as? AudioManager ?: return false
        try {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                val devices = am.availableCommunicationDevices
                val preferredBt = devices.firstOrNull {
                    it.type == AudioDeviceInfo.TYPE_BLUETOOTH_SCO &&
                        it.productName.toString().contains("Funkenwerk", ignoreCase = true)
                } ?: devices.firstOrNull {
                    it.type == AudioDeviceInfo.TYPE_BLUETOOTH_SCO &&
                        !it.productName.toString().contains("Teufel", ignoreCase = true)
                }
                if (preferredBt != null) {
                    return am.setCommunicationDevice(preferredBt)
                }
            } else {
                am.startBluetoothSco()
                am.isBluetoothScoOn = true
                return true
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
                description = "Whitelist-Anrufannahme & Anrufnotizen"
            }
            nm.createNotificationChannel(ch)
        }
    }

    private fun promoteToForeground(title: String, body: String) {
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

        try {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.UPSIDE_DOWN_CAKE) {
                startForeground(ACTIVE_NOTIF_ID, notif, ServiceInfo.FOREGROUND_SERVICE_TYPE_PHONE_CALL)
            } else {
                startForeground(ACTIVE_NOTIF_ID, notif)
            }
        } catch (_: Exception) {
            try {
                startForeground(ACTIVE_NOTIF_ID, notif)
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
            .setContentTitle("📞 Anrufnotiz von $callerLabel")
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
