package de.meinseelenfunke.app.receiver

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.os.Build
import android.util.Log
import androidx.core.app.NotificationCompat
import de.meinseelenfunke.app.MainActivity
import de.meinseelenfunke.app.di.ServiceLocator
import de.meinseelenfunke.app.util.FunkenwerkAlarmManager
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.launch
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

class SmartAlarmReceiver : BroadcastReceiver() {
    companion object {
        const val TAG = "SmartAlarmReceiver"
        const val CHANNEL_ID = "funkenwerk_smart_alarm_v2_channel"
        const val CHANNEL_NAME = "Funkenwerk Wecker & Timer"

        const val ACTION_ALARM_TRIGGER = "de.meinseelenfunke.app.action.ALARM_TRIGGER"
        const val ACTION_ALARM_DISMISS = "de.meinseelenfunke.app.action.ALARM_DISMISS"
        const val ACTION_ALARM_SNOOZE = "de.meinseelenfunke.app.action.ALARM_SNOOZE"
        const val ACTION_DISABLE_ALARM = "de.meinseelenfunke.app.action.ALARM_DISABLE"
        const val ACTION_TIMER_TOGGLE = "de.meinseelenfunke.app.action.TIMER_TOGGLE"
        const val ACTION_TIMER_RESET = "de.meinseelenfunke.app.action.TIMER_RESET"
        const val ACTION_TIMER_STOP_SOUND = "de.meinseelenfunke.app.action.TIMER_STOP_SOUND"
        const val ACTION_TIMER_EXPIRED = "de.meinseelenfunke.app.action.TIMER_EXPIRED"
    }

    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.IO)

    override fun onReceive(context: Context, intent: Intent) {
        val action = intent.action ?: ACTION_ALARM_TRIGGER
        val alarmId = intent.getStringExtra("alarm_id") ?: "alarm_default"
        val alarmLabel = intent.getStringExtra("alarm_label") ?: "Funkenwerk Wecker"
        val scheduledTime = intent.getStringExtra("alarm_time")
            ?: SimpleDateFormat("HH:mm", Locale.GERMANY).format(Date())

        val notificationManager = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager

        when (action) {
            ACTION_DISABLE_ALARM -> {
                FunkenwerkAlarmManager.stopAlarmSound(context)
                notificationManager.cancel(alarmId.hashCode())
                FunkenwerkAlarmManager.ensureInitialized(context)
                val target = FunkenwerkAlarmManager.alarmsFlow.value.find { it.id == alarmId }
                if (target != null && target.isEnabled) {
                    FunkenwerkAlarmManager.toggleAlarm(context, alarmId)
                    pushPackageToBackendAsync(context)
                } else {
                    FunkenwerkAlarmManager.updateActiveAlarmStatusNotification(context)
                }
                return
            }

            ACTION_TIMER_TOGGLE -> {
                val timerId = intent.getStringExtra("timer_id") ?: return
                FunkenwerkAlarmManager.toggleTimerPlayPause(context, timerId)
                return
            }

            ACTION_TIMER_RESET -> {
                val timerId = intent.getStringExtra("timer_id") ?: return
                FunkenwerkAlarmManager.resetTimer(context, timerId)
                return
            }

            ACTION_TIMER_STOP_SOUND -> {
                FunkenwerkAlarmManager.stopAlarmSound(context)
                return
            }

            ACTION_TIMER_EXPIRED -> {
                val timerId = intent.getStringExtra("timer_id") ?: return
                FunkenwerkAlarmManager.onTimerExpiredFromReceiver(context, timerId)
                return
            }

            ACTION_ALARM_DISMISS -> {
                FunkenwerkAlarmManager.stopAlarmSound(context)
                notificationManager.cancel(alarmId.hashCode())
                val actualWakeTime = SimpleDateFormat("HH:mm", Locale.GERMANY).format(Date())
                FunkenwerkAlarmManager.recordWakeUpLog(
                    context = context,
                    scheduledTime = scheduledTime,
                    actualWakeTime = actualWakeTime
                )
                pushPackageToBackendAsync(context)
                return
            }

            ACTION_ALARM_SNOOZE -> {
                FunkenwerkAlarmManager.stopAlarmSound(context)
                notificationManager.cancel(alarmId.hashCode())
                val actualWakeTime = SimpleDateFormat("HH:mm", Locale.GERMANY).format(Date())
                FunkenwerkAlarmManager.recordWakeUpLog(
                    context = context,
                    scheduledTime = scheduledTime,
                    actualWakeTime = actualWakeTime,
                    snoozeMinutes = 5
                )
                FunkenwerkAlarmManager.scheduleSnoozeAlarm(context, alarmId, alarmLabel, scheduledTime, 5)
                return
            }

            ACTION_ALARM_TRIGGER -> {
                FunkenwerkAlarmManager.ensureInitialized(context)
                val targetAlarm = FunkenwerkAlarmManager.alarmsFlow.value.find { it.id == alarmId }
                val triggerPlugs = targetAlarm?.triggerPlugOnRing ?: intent.getBooleanExtra("trigger_plugs", true)
                val rawPlugIps = targetAlarm?.smartPlugIps?.takeIf { it.isNotEmpty() }
                    ?: intent.getStringArrayExtra("plug_ips")?.toList()
                    ?: emptyList()
                val plugIps = rawPlugIps.map { if (it.trim() == "192.168.188.106") "192.168.188.110" else it.trim() }.distinct()
                val aiBriefing = targetAlarm?.aiBriefingEnabled ?: intent.getBooleanExtra("ai_briefing", true)
                val syncRoutine = targetAlarm?.syncWithRoutine ?: intent.getBooleanExtra("sync_routine", true)
                val customTrackId = targetAlarm?.alarmSoundTrackId?.takeIf { it.isNotBlank() }
                    ?: intent.getStringExtra("alarm_sound_track_id")?.takeIf { it.isNotBlank() }

                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                    val channel = NotificationChannel(
                        CHANNEL_ID,
                        CHANNEL_NAME,
                        NotificationManager.IMPORTANCE_HIGH
                    ).apply {
                        description = "Wecker, Smart-Home Steuerung & KI-Morgen-Briefing"
                        enableLights(true)
                        enableVibration(true)
                        setSound(null, null)
                    }
                    notificationManager.createNotificationChannel(channel)
                }

                // 1. Trigger sequential alarm sound -> pause -> Funkira voice briefing -> resume alarm
                FunkenwerkAlarmManager.triggerAlarmSequence(
                    context = context,
                    alarmId = alarmId,
                    alarmLabel = alarmLabel,
                    scheduledTime = scheduledTime,
                    aiBriefing = aiBriefing,
                    plugsSwitched = triggerPlugs && plugIps.isNotEmpty(),
                    customTrackId = customTrackId
                )

                // 2. Trigger Smart-Home Wi-Fi Plugs if enabled
                if (triggerPlugs && plugIps.isNotEmpty()) {
                    FunkenwerkAlarmManager.triggerSmartPlugsAsync(context, plugIps, turnOn = true)
                }

                // 3. Record wake-up log & sync to /admin/routine if enabled
                val actualWakeTime = SimpleDateFormat("HH:mm", Locale.GERMANY).format(Date())
                FunkenwerkAlarmManager.recordWakeUpLog(
                    context = context,
                    scheduledTime = scheduledTime,
                    actualWakeTime = actualWakeTime,
                    plugsTriggered = triggerPlugs && plugIps.isNotEmpty(),
                    aiBriefingSpoken = aiBriefing
                )
                if (syncRoutine) {
                    pushPackageToBackendAsync(context)
                }

                // 4. Reschedule next repeating alarm occurrence
                FunkenwerkAlarmManager.scheduleAllActiveAlarms(context)

                // 5. Show high-priority notification with Aufstehen & Schlummern actions
                val openAppIntent = Intent(context, MainActivity::class.java).apply {
                    flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
                    putExtra("open_tab", 2)
                    putExtra("open_subtab", 6)
                    putExtra("alarm_inner_tab", 0)
                }
                val openPending = PendingIntent.getActivity(
                    context,
                    alarmId.hashCode(),
                    openAppIntent,
                    PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
                )

                val dismissIntent = Intent(context, SmartAlarmReceiver::class.java).apply {
                    this.action = ACTION_ALARM_DISMISS
                    putExtra("alarm_id", alarmId)
                    putExtra("alarm_label", alarmLabel)
                    putExtra("alarm_time", scheduledTime)
                }
                val dismissPending = PendingIntent.getBroadcast(
                    context,
                    (alarmId + "_dismiss").hashCode(),
                    dismissIntent,
                    PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
                )

                val snoozeIntent = Intent(context, SmartAlarmReceiver::class.java).apply {
                    this.action = ACTION_ALARM_SNOOZE
                    putExtra("alarm_id", alarmId)
                    putExtra("alarm_label", alarmLabel)
                    putExtra("alarm_time", scheduledTime)
                }
                val snoozePending = PendingIntent.getBroadcast(
                    context,
                    (alarmId + "_snooze_btn").hashCode(),
                    snoozeIntent,
                    PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
                )

                val dismissDeletePending = PendingIntent.getBroadcast(
                    context,
                    (alarmId + "_delete_swipe").hashCode(),
                    dismissIntent,
                    PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
                )

                val subInfo = buildList {
                    if (triggerPlugs && plugIps.isNotEmpty()) add("⚡ ${plugIps.size} Steckdose(n) EIN")
                    if (aiBriefing) add("🎙️ KI-Briefing aktiv")
                    if (syncRoutine) add("🔄 Routine-Sync")
                }.joinToString(" · ").ifBlank { "Guten Morgen!" }

                val notification = NotificationCompat.Builder(context, CHANNEL_ID)
                    .setSmallIcon(android.R.drawable.ic_lock_idle_alarm)
                    .setContentTitle("⏰ $scheduledTime Uhr · $alarmLabel")
                    .setContentText("$subInfo (Wegwischen = Wecker aus)")
                    .setPriority(NotificationCompat.PRIORITY_MAX)
                    .setCategory(NotificationCompat.CATEGORY_ALARM)
                    .setContentIntent(openPending)
                    .setDeleteIntent(dismissDeletePending)
                    .setFullScreenIntent(openPending, true)
                    .setOngoing(false)
                    .setAutoCancel(true)
                    .addAction(android.R.drawable.ic_menu_close_clear_cancel, "Aufstehen & Aus", dismissPending)
                    .addAction(android.R.drawable.ic_popup_reminder, "5 Min Schlummern", snoozePending)
                    .build()

                FunkenwerkAlarmManager.registerActiveAlarmNotification(alarmId.hashCode(), alarmId, alarmLabel, scheduledTime)
                notificationManager.notify(alarmId.hashCode(), notification)
            }
        }
    }

    private fun pushPackageToBackendAsync(context: Context) {
        FunkenwerkAlarmManager.pushAlarmPackageToAllEndpointsAsync(context)
        scope.launch {
            try {
                ServiceLocator.init(context.applicationContext)
                ServiceLocator.organizerRepository.syncAlarmPackageToPortal(context.applicationContext)
            } catch (e: Exception) {
                Log.e(TAG, "Background alarm sync failed", e)
            }
        }
    }
}
