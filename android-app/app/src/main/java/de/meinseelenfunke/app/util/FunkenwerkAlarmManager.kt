package de.meinseelenfunke.app.util

import android.app.AlarmManager
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.media.Ringtone
import android.media.RingtoneManager
import android.os.Build
import android.speech.tts.TextToSpeech
import android.util.Log
import androidx.core.app.NotificationCompat
import com.google.gson.Gson
import com.google.gson.annotations.SerializedName
import com.google.gson.reflect.TypeToken
import de.meinseelenfunke.app.MainActivity
import de.meinseelenfunke.app.data.api.CalendarEvent
import de.meinseelenfunke.app.data.api.ManagementTask
import de.meinseelenfunke.app.receiver.SmartAlarmReceiver
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.Job
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import java.net.CookieHandler
import java.net.CookieManager
import java.net.CookiePolicy
import java.net.HttpURLConnection
import java.net.URL
import java.nio.ByteBuffer
import java.security.MessageDigest
import java.security.SecureRandom
import java.text.SimpleDateFormat
import java.util.Calendar
import java.util.Date
import java.util.Locale
import javax.crypto.Cipher
import javax.crypto.spec.IvParameterSpec
import javax.crypto.spec.SecretKeySpec

data class SmartAlarmItem(
    val id: String,
    val hour: Int,
    val minute: Int,
    val label: String,
    @SerializedName("schedule_text") val scheduleText: String = "Jeden Tag",
    @SerializedName("days_of_week") val daysOfWeek: List<Int> = listOf(1, 2, 3, 4, 5, 6, 7), // 1=Mo..7=So
    @SerializedName("is_enabled") val isEnabled: Boolean = false,
    @SerializedName("smart_plug_ips") val smartPlugIps: List<String> = emptyList(),
    @SerializedName("trigger_plug_on_ring") val triggerPlugOnRing: Boolean = true,
    @SerializedName("ai_briefing_enabled") val aiBriefingEnabled: Boolean = true,
    @SerializedName("sync_with_routine") val syncWithRoutine: Boolean = true
) {
    val timeFormatted: String
        get() = String.format(Locale.GERMANY, "%02d:%02d", hour, minute)
}

data class TimerPresetItem(
    val id: String,
    val label: String,
    @SerializedName("duration_seconds") val durationSeconds: Int
)

data class SmartPlugDevice(
    val name: String,
    val ip: String,
    val room: String = "Heimnetz"
)

data class WakeUpLogEntry(
    val date: String,
    @SerializedName("scheduled_time") val scheduledTime: String,
    @SerializedName("actual_wake_time") val actualWakeTime: String,
    @SerializedName("snooze_minutes") val snoozeMinutes: Int = 0,
    @SerializedName("plugs_triggered") val plugsTriggered: Boolean = false,
    @SerializedName("ai_briefing_spoken") val aiBriefingSpoken: Boolean = false
)

object FunkenwerkAlarmManager {
    private const val TAG = "FunkenwerkAlarmMgr"
    private const val PREFS_NAME = "funkenwerk_alarm_prefs"
    private const val KEY_ALARMS = "alarms_json"
    private const val KEY_TIMERS = "timers_json"
    private const val KEY_SMART_PLUGS = "smart_plugs_json"
    private const val KEY_WAKE_LOGS = "wake_up_logs_json"
    private const val KEY_LAST_SYNC = "last_synced_at"
    private const val KEY_TAPO_EMAIL = "tapo_local_email"
    private const val KEY_TAPO_PASS = "tapo_local_password"
    private const val KEY_TIMER_END_MILLIS = "timer_end_millis_json"
    private const val KEY_TIMER_PAUSED_REM = "timer_paused_rem_json"

    const val STATUS_CHANNEL_ID = "funkenwerk_alarm_status_channel"
    const val STATUS_CHANNEL_NAME = "Aktive Wecker & Laufende Timer"
    const val NOTIFICATION_ID_ACTIVE_ALARM = 9101
    const val NOTIFICATION_ID_ACTIVE_TIMER = 9102
    const val NOTIFICATION_ID_TIMER_FINISHED = 9103

    private val gson = Gson()
    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.IO)

    private val _alarmsFlow = MutableStateFlow<List<SmartAlarmItem>>(emptyList())
    val alarmsFlow: StateFlow<List<SmartAlarmItem>> = _alarmsFlow.asStateFlow()

    private val _timersFlow = MutableStateFlow<List<TimerPresetItem>>(emptyList())
    val timersFlow: StateFlow<List<TimerPresetItem>> = _timersFlow.asStateFlow()

    private val _timerRemainingFlow = MutableStateFlow<Map<String, Int>>(emptyMap())
    val timerRemainingFlow: StateFlow<Map<String, Int>> = _timerRemainingFlow.asStateFlow()

    private val _timerRunningFlow = MutableStateFlow<Map<String, Boolean>>(emptyMap())
    val timerRunningFlow: StateFlow<Map<String, Boolean>> = _timerRunningFlow.asStateFlow()

    private val _timerEndMillisMap = mutableMapOf<String, Long>()

    // Persistent Stopwatch State (runs accurately across app minimize & tab switches)
    private val _stopwatchRunningFlow = MutableStateFlow(false)
    val stopwatchRunningFlow: StateFlow<Boolean> = _stopwatchRunningFlow.asStateFlow()

    private val _stopwatchElapsedMsFlow = MutableStateFlow(0L)
    val stopwatchElapsedMsFlow: StateFlow<Long> = _stopwatchElapsedMsFlow.asStateFlow()

    private val _stopwatchLapsFlow = MutableStateFlow<List<Long>>(emptyList())
    val stopwatchLapsFlow: StateFlow<List<Long>> = _stopwatchLapsFlow.asStateFlow()

    private var stopwatchBaseElapsedMs: Long = 0L
    private var stopwatchStartWallMs: Long = 0L
    private var stopwatchTickerJob: Job? = null

    private val _activeInnerSubTabFlow = MutableStateFlow(0)
    val activeInnerSubTabFlow: StateFlow<Int> = _activeInnerSubTabFlow.asStateFlow()

    private val _smartPlugsFlow = MutableStateFlow<List<SmartPlugDevice>>(emptyList())
    val smartPlugsFlow: StateFlow<List<SmartPlugDevice>> = _smartPlugsFlow.asStateFlow()

    private val _wakeLogsFlow = MutableStateFlow<Map<String, WakeUpLogEntry>>(emptyMap())
    val wakeLogsFlow: StateFlow<Map<String, WakeUpLogEntry>> = _wakeLogsFlow.asStateFlow()

    private val _lastSyncFlow = MutableStateFlow<String?>(null)
    val lastSyncFlow: StateFlow<String?> = _lastSyncFlow.asStateFlow()

    private var activeRingtone: Ringtone? = null
    private var activeTts: TextToSpeech? = null
    private var timerTickerJob: Job? = null
    private var isInitialized = false

    fun setActiveInnerSubTab(subTab: Int) {
        _activeInnerSubTabFlow.value = subTab.coerceIn(0, 2)
    }

    fun ensureInitialized(context: Context) {
        if (isInitialized && _alarmsFlow.value.isNotEmpty()) {
            updateActiveAlarmStatusNotification(context.applicationContext, _alarmsFlow.value)
            return
        }
        val appCtx = context.applicationContext
        val prefs = appCtx.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)

        // 1. Load or seed default Alarms matching Screenshot 2
        val alarmsJson = prefs.getString(KEY_ALARMS, null)
        val loadedAlarms: List<SmartAlarmItem> = if (!alarmsJson.isNullOrBlank()) {
            try {
                val type = object : TypeToken<List<SmartAlarmItem>>() {}.type
                gson.fromJson(alarmsJson, type) ?: defaultAlarms()
            } catch (_: Exception) {
                defaultAlarms()
            }
        } else {
            val defaults = defaultAlarms()
            prefs.edit().putString(KEY_ALARMS, gson.toJson(defaults)).apply()
            defaults
        }
        _alarmsFlow.value = loadedAlarms.sortedWith(compareBy({ it.hour }, { it.minute }))

        // 2. Load or seed default Timers matching Screenshot 3
        val timersJson = prefs.getString(KEY_TIMERS, null)
        val loadedTimers: List<TimerPresetItem> = if (!timersJson.isNullOrBlank()) {
            try {
                val type = object : TypeToken<List<TimerPresetItem>>() {}.type
                gson.fromJson(timersJson, type) ?: defaultTimers()
            } catch (_: Exception) {
                defaultTimers()
            }
        } else {
            val defaults = defaultTimers()
            prefs.edit().putString(KEY_TIMERS, gson.toJson(defaults)).apply()
            defaults
        }
        _timersFlow.value = loadedTimers
        restorePersistedTimerState(appCtx, loadedTimers)

        // 3. Load or seed default Smart Plugs
        val plugsJson = prefs.getString(KEY_SMART_PLUGS, null)
        val loadedPlugs: List<SmartPlugDevice> = if (!plugsJson.isNullOrBlank()) {
            try {
                val type = object : TypeToken<List<SmartPlugDevice>>() {}.type
                gson.fromJson(plugsJson, type) ?: defaultSmartPlugs()
            } catch (_: Exception) {
                defaultSmartPlugs()
            }
        } else {
            val defaults = defaultSmartPlugs()
            prefs.edit().putString(KEY_SMART_PLUGS, gson.toJson(defaults)).apply()
            defaults
        }
        _smartPlugsFlow.value = loadedPlugs

        // 4. Load Wake-Up Logs
        val logsJson = prefs.getString(KEY_WAKE_LOGS, null)
        val loadedLogs: Map<String, WakeUpLogEntry> = if (!logsJson.isNullOrBlank()) {
            try {
                val type = object : TypeToken<Map<String, WakeUpLogEntry>>() {}.type
                gson.fromJson(logsJson, type) ?: emptyMap()
            } catch (_: Exception) {
                emptyMap()
            }
        } else {
            emptyMap()
        }
        _wakeLogsFlow.value = loadedLogs
        _lastSyncFlow.value = prefs.getString(KEY_LAST_SYNC, null)
        isInitialized = true

        scheduleAllActiveAlarms(appCtx, _alarmsFlow.value)
    }

    private fun syncTimerMapsWithPresets(presets: List<TimerPresetItem>) {
        val remMap = _timerRemainingFlow.value.toMutableMap()
        val runMap = _timerRunningFlow.value.toMutableMap()
        presets.forEach { p ->
            if (!remMap.containsKey(p.id)) remMap[p.id] = p.durationSeconds
            if (!runMap.containsKey(p.id)) runMap[p.id] = false
        }
        _timerRemainingFlow.value = remMap
        _timerRunningFlow.value = runMap
    }

    private fun restorePersistedTimerState(context: Context, presets: List<TimerPresetItem>) {
        val prefs = context.applicationContext.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        val endJson = prefs.getString(KEY_TIMER_END_MILLIS, null)
        val pausedJson = prefs.getString(KEY_TIMER_PAUSED_REM, null)

        val savedEndMap: Map<String, Long> = if (!endJson.isNullOrBlank()) {
            try {
                val type = object : TypeToken<Map<String, Long>>() {}.type
                gson.fromJson(endJson, type) ?: emptyMap()
            } catch (_: Exception) {
                emptyMap()
            }
        } else emptyMap()

        val savedPausedMap: Map<String, Int> = if (!pausedJson.isNullOrBlank()) {
            try {
                val type = object : TypeToken<Map<String, Int>>() {}.type
                gson.fromJson(pausedJson, type) ?: emptyMap()
            } catch (_: Exception) {
                emptyMap()
            }
        } else emptyMap()

        val remMap = mutableMapOf<String, Int>()
        val runMap = mutableMapOf<String, Boolean>()
        val now = System.currentTimeMillis()
        var anyRunning = false

        _timerEndMillisMap.clear()
        presets.forEach { p ->
            val endMs = savedEndMap[p.id]
            if (endMs != null && endMs > now) {
                _timerEndMillisMap[p.id] = endMs
                remMap[p.id] = (((endMs - now) + 999L) / 1000L).toInt().coerceAtLeast(1)
                runMap[p.id] = true
                anyRunning = true
                scheduleTimerExactAlarm(context.applicationContext, p.id, p.label, endMs)
            } else {
                remMap[p.id] = savedPausedMap[p.id] ?: p.durationSeconds
                runMap[p.id] = false
                cancelTimerExactAlarm(context.applicationContext, p.id)
            }
        }
        _timerRemainingFlow.value = remMap
        _timerRunningFlow.value = runMap
        if (anyRunning) {
            ensureTimerTicker(context)
        }
        updateActiveTimerNotification(context)
    }

    private fun persistTimerState(context: Context) {
        val prefs = context.applicationContext.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        val pausedMap = _timerRemainingFlow.value.filter { (id, _) -> _timerRunningFlow.value[id] != true }
        prefs.edit()
            .putString(KEY_TIMER_END_MILLIS, gson.toJson(_timerEndMillisMap))
            .putString(KEY_TIMER_PAUSED_REM, gson.toJson(pausedMap))
            .apply()
    }

    // =========================================================================
    // PERSISTENT STOPWATCH (RUNS ACCURATELY ACROSS MINIMIZE & TAB SWITCHES)
    // =========================================================================
    fun toggleStopwatch() {
        val now = System.currentTimeMillis()
        if (_stopwatchRunningFlow.value) {
            stopwatchBaseElapsedMs += (now - stopwatchStartWallMs).coerceAtLeast(0L)
            _stopwatchElapsedMsFlow.value = stopwatchBaseElapsedMs
            _stopwatchRunningFlow.value = false
            stopwatchTickerJob?.cancel()
            stopwatchTickerJob = null
        } else {
            stopwatchStartWallMs = now
            _stopwatchRunningFlow.value = true
            ensureStopwatchTicker()
        }
    }

    fun lapStopwatch() {
        val currentMs = if (_stopwatchRunningFlow.value) {
            stopwatchBaseElapsedMs + (System.currentTimeMillis() - stopwatchStartWallMs).coerceAtLeast(0L)
        } else {
            stopwatchBaseElapsedMs
        }
        _stopwatchElapsedMsFlow.value = currentMs
        _stopwatchLapsFlow.value = listOf(currentMs) + _stopwatchLapsFlow.value
    }

    fun resetStopwatch() {
        stopwatchTickerJob?.cancel()
        stopwatchTickerJob = null
        _stopwatchRunningFlow.value = false
        stopwatchBaseElapsedMs = 0L
        stopwatchStartWallMs = 0L
        _stopwatchElapsedMsFlow.value = 0L
        _stopwatchLapsFlow.value = emptyList()
    }

    private fun ensureStopwatchTicker() {
        if (stopwatchTickerJob?.isActive == true) return
        stopwatchTickerJob = scope.launch {
            while (_stopwatchRunningFlow.value) {
                delay(33L)
                if (_stopwatchRunningFlow.value) {
                    val elapsed = stopwatchBaseElapsedMs + (System.currentTimeMillis() - stopwatchStartWallMs).coerceAtLeast(0L)
                    _stopwatchElapsedMsFlow.value = elapsed
                }
            }
        }
    }

    fun defaultAlarms(): List<SmartAlarmItem> = listOf(
        SmartAlarmItem(
            id = "alarm_0645",
            hour = 6,
            minute = 45,
            label = "Frühschicht / Manufaktur",
            scheduleText = "Jeden Tag",
            daysOfWeek = listOf(1, 2, 3, 4, 5, 6, 7),
            isEnabled = false,
            smartPlugIps = listOf("192.168.188.109"),
            triggerPlugOnRing = true,
            aiBriefingEnabled = true,
            syncWithRoutine = true
        ),
        SmartAlarmItem(
            id = "alarm_0830",
            hour = 8,
            minute = 30,
            label = "Seelenfunke Fokus-Start",
            scheduleText = "Jeden Tag",
            daysOfWeek = listOf(1, 2, 3, 4, 5, 6, 7),
            isEnabled = true,
            smartPlugIps = listOf("192.168.188.109", "192.168.188.106"),
            triggerPlugOnRing = true,
            aiBriefingEnabled = true,
            syncWithRoutine = true
        ),
        SmartAlarmItem(
            id = "alarm_1300",
            hour = 13,
            minute = 0,
            label = "Mittags-Check / Express",
            scheduleText = "Nicht geplant",
            daysOfWeek = emptyList(),
            isEnabled = false,
            smartPlugIps = emptyList(),
            triggerPlugOnRing = false,
            aiBriefingEnabled = false,
            syncWithRoutine = false
        )
    )

    fun defaultTimers(): List<TimerPresetItem> = listOf(
        TimerPresetItem(id = "timer_5", label = "Timer: 5 Min.", durationSeconds = 300),
        TimerPresetItem(id = "timer_10", label = "Timer: 10 Min.", durationSeconds = 600),
        TimerPresetItem(id = "timer_15", label = "Timer: 15 Min.", durationSeconds = 900),
        TimerPresetItem(id = "timer_20", label = "Timer: 20 Min.", durationSeconds = 1200),
        TimerPresetItem(id = "timer_30", label = "Timer: 30 Min.", durationSeconds = 1800)
    )

    fun defaultSmartPlugs(): List<SmartPlugDevice> = listOf(
        SmartPlugDevice(name = "Tapo P100 (Test-Steckdose)", ip = "192.168.188.109", room = "Heimnetz"),
        SmartPlugDevice(name = "Smart Plug #2", ip = "192.168.188.106", room = "Heimnetz"),
        SmartPlugDevice(name = "Smart Plug #3", ip = "192.168.188.123", room = "Heimnetz")
    )

    fun formatDaysScheduleText(days: List<Int>): String {
        val sorted = days.distinct().sorted()
        return when {
            sorted.isEmpty() -> "Nicht geplant"
            sorted.size == 7 -> "Jeden Tag"
            sorted == listOf(1, 2, 3, 4, 5) -> "Mo–Fr"
            sorted == listOf(6, 7) -> "Sa & So"
            else -> {
                val names = mapOf(1 to "Mo", 2 to "Di", 3 to "Mi", 4 to "Do", 5 to "Fr", 6 to "Sa", 7 to "So")
                sorted.mapNotNull { names[it] }.joinToString(", ")
            }
        }
    }

    fun getNextActiveAlarm(context: Context): SmartAlarmItem? {
        ensureInitialized(context)
        return _alarmsFlow.value
            .filter { it.isEnabled }
            .minByOrNull { it.hour * 60 + it.minute }
    }

    fun saveAlarms(context: Context, alarms: List<SmartAlarmItem>) {
        val sorted = alarms.sortedWith(compareBy({ it.hour }, { it.minute }))
        _alarmsFlow.value = sorted
        context.applicationContext.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
            .edit()
            .putString(KEY_ALARMS, gson.toJson(sorted))
            .apply()
        scheduleAllActiveAlarms(context, sorted)
    }

    fun toggleAlarm(context: Context, alarmId: String): SmartAlarmItem? {
        ensureInitialized(context)
        var toggled: SmartAlarmItem? = null
        val updated = _alarmsFlow.value.map { item ->
            if (item.id == alarmId) {
                val nextState = !item.isEnabled
                val nextDays = if (nextState && item.daysOfWeek.isEmpty()) listOf(1, 2, 3, 4, 5, 6, 7) else item.daysOfWeek
                val nextSchedule = if (nextState && item.scheduleText == "Nicht geplant") "Jeden Tag" else item.scheduleText
                val copy = item.copy(isEnabled = nextState, daysOfWeek = nextDays, scheduleText = nextSchedule)
                toggled = copy
                copy
            } else {
                item
            }
        }
        saveAlarms(context, updated)
        return toggled
    }

    fun upsertAlarm(context: Context, alarm: SmartAlarmItem) {
        ensureInitialized(context)
        val current = _alarmsFlow.value.toMutableList()
        val idx = current.indexOfFirst { it.id == alarm.id }
        if (idx >= 0) {
            current[idx] = alarm
        } else {
            current.add(alarm)
        }
        saveAlarms(context, current)
    }

    fun deleteAlarm(context: Context, alarmId: String) {
        ensureInitialized(context)
        cancelAlarm(context, alarmId)
        val updated = _alarmsFlow.value.filterNot { it.id == alarmId }
        saveAlarms(context, updated)
    }

    fun addTimerPreset(context: Context, label: String, durationSeconds: Int) {
        ensureInitialized(context)
        val newItem = TimerPresetItem(
            id = "timer_${System.currentTimeMillis()}",
            label = label.ifBlank { "Timer: ${durationSeconds / 60} Min." },
            durationSeconds = durationSeconds.coerceAtLeast(5)
        )
        val updated = (_timersFlow.value + newItem).sortedBy { it.durationSeconds }
        _timersFlow.value = updated
        syncTimerMapsWithPresets(updated)
        context.applicationContext.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
            .edit()
            .putString(KEY_TIMERS, gson.toJson(updated))
            .apply()
    }

    fun deleteTimerPreset(context: Context, timerId: String) {
        ensureInitialized(context)
        val appCtx = context.applicationContext
        val updated = _timersFlow.value.filterNot { it.id == timerId }
        _timersFlow.value = updated
        _timerEndMillisMap.remove(timerId)
        cancelTimerExactAlarm(appCtx, timerId)
        val remMap = _timerRemainingFlow.value.toMutableMap().apply { remove(timerId) }
        val runMap = _timerRunningFlow.value.toMutableMap().apply { remove(timerId) }
        _timerRemainingFlow.value = remMap
        _timerRunningFlow.value = runMap
        persistTimerState(appCtx)
        updateActiveTimerNotification(appCtx)
        appCtx.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
            .edit()
            .putString(KEY_TIMERS, gson.toJson(updated))
            .apply()
    }

    fun toggleTimerPlayPause(context: Context, timerId: String) {
        ensureInitialized(context)
        val appCtx = context.applicationContext
        stopAlarmSound(appCtx)
        val preset = _timersFlow.value.find { it.id == timerId } ?: return
        val remMap = _timerRemainingFlow.value.toMutableMap()
        val runMap = _timerRunningFlow.value.toMutableMap()
        if (!remMap.containsKey(timerId) || (remMap[timerId] ?: 0) <= 0) {
            remMap[timerId] = preset.durationSeconds
        }
        val nextRunning = !(runMap[timerId] ?: false)
        val now = System.currentTimeMillis()
        if (nextRunning) {
            val remSec = (remMap[timerId] ?: preset.durationSeconds).coerceAtLeast(1)
            val endMs = now + remSec * 1000L
            _timerEndMillisMap[timerId] = endMs
            scheduleTimerExactAlarm(appCtx, timerId, preset.label, endMs)
        } else {
            val endMs = _timerEndMillisMap.remove(timerId)
            if (endMs != null) {
                remMap[timerId] = (((endMs - now) + 999L) / 1000L).toInt().coerceAtLeast(1)
            }
            cancelTimerExactAlarm(appCtx, timerId)
        }
        runMap[timerId] = nextRunning
        _timerRemainingFlow.value = remMap
        _timerRunningFlow.value = runMap
        persistTimerState(appCtx)
        ensureTimerTicker(appCtx)
        updateActiveTimerNotification(appCtx)
    }

    fun resetTimer(context: Context, timerId: String) {
        ensureInitialized(context)
        val appCtx = context.applicationContext
        stopAlarmSound(appCtx)
        val preset = _timersFlow.value.find { it.id == timerId }
        _timerEndMillisMap.remove(timerId)
        cancelTimerExactAlarm(appCtx, timerId)
        val remMap = _timerRemainingFlow.value.toMutableMap()
        val runMap = _timerRunningFlow.value.toMutableMap()
        runMap[timerId] = false
        if (preset != null) {
            remMap[timerId] = preset.durationSeconds
        }
        _timerRemainingFlow.value = remMap
        _timerRunningFlow.value = runMap
        persistTimerState(appCtx)
        updateActiveTimerNotification(appCtx)
    }

    fun onTimerExpiredFromReceiver(context: Context, timerId: String) {
        ensureInitialized(context)
        val appCtx = context.applicationContext
        val preset = _timersFlow.value.find { it.id == timerId } ?: return
        val wasRunning = _timerRunningFlow.value[timerId] == true || _timerEndMillisMap.containsKey(timerId)
        if (!wasRunning) return

        _timerEndMillisMap.remove(timerId)
        cancelTimerExactAlarm(appCtx, timerId)
        val remMap = _timerRemainingFlow.value.toMutableMap()
        val runMap = _timerRunningFlow.value.toMutableMap()
        runMap[timerId] = false
        remMap[timerId] = preset.durationSeconds
        _timerRemainingFlow.value = remMap
        _timerRunningFlow.value = runMap
        persistTimerState(appCtx)
        updateActiveTimerNotification(appCtx)
        startAlarmSound(appCtx)
        showTimerFinishedNotification(appCtx, preset)
    }

    private fun scheduleTimerExactAlarm(context: Context, timerId: String, label: String, endMillis: Long) {
        val alarmManager = context.getSystemService(Context.ALARM_SERVICE) as? AlarmManager ?: return
        val intent = Intent(context, SmartAlarmReceiver::class.java).apply {
            action = SmartAlarmReceiver.ACTION_TIMER_EXPIRED
            putExtra("timer_id", timerId)
            putExtra("timer_label", label)
        }
        val pendingIntent = PendingIntent.getBroadcast(
            context,
            ("timer_exp_$timerId").hashCode(),
            intent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )
        try {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
                alarmManager.setExactAndAllowWhileIdle(AlarmManager.RTC_WAKEUP, endMillis, pendingIntent)
            } else {
                alarmManager.setExact(AlarmManager.RTC_WAKEUP, endMillis, pendingIntent)
            }
        } catch (se: SecurityException) {
            try {
                alarmManager.set(AlarmManager.RTC_WAKEUP, endMillis, pendingIntent)
            } catch (_: Exception) {
            }
        }
    }

    private fun cancelTimerExactAlarm(context: Context, timerId: String) {
        val alarmManager = context.getSystemService(Context.ALARM_SERVICE) as? AlarmManager ?: return
        val intent = Intent(context, SmartAlarmReceiver::class.java).apply {
            action = SmartAlarmReceiver.ACTION_TIMER_EXPIRED
        }
        val pendingIntent = PendingIntent.getBroadcast(
            context,
            ("timer_exp_$timerId").hashCode(),
            intent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )
        alarmManager.cancel(pendingIntent)
    }

    private fun ensureTimerTicker(context: Context) {
        val appCtx = context.applicationContext
        if (timerTickerJob?.isActive == true) return
        timerTickerJob = scope.launch {
            while (true) {
                delay(1000L)
                val runningIds = _timerRunningFlow.value.filterValues { it }.keys.toList()
                if (runningIds.isEmpty()) {
                    updateActiveTimerNotification(appCtx)
                    break
                }
                val now = System.currentTimeMillis()
                val remMap = _timerRemainingFlow.value.toMutableMap()
                val runMap = _timerRunningFlow.value.toMutableMap()
                val finishedPresets = mutableListOf<TimerPresetItem>()

                for (id in runningIds) {
                    val endMs = _timerEndMillisMap[id] ?: (now + ((remMap[id] ?: 1) * 1000L)).also {
                        _timerEndMillisMap[id] = it
                    }
                    val remSec = (((endMs - now) + 999L) / 1000L).toInt()
                    if (remSec > 0) {
                        remMap[id] = remSec
                    } else {
                        _timerEndMillisMap.remove(id)
                        cancelTimerExactAlarm(appCtx, id)
                        runMap[id] = false
                        val preset = _timersFlow.value.find { it.id == id }
                        remMap[id] = preset?.durationSeconds ?: 300
                        if (preset != null) {
                            finishedPresets.add(preset)
                        }
                    }
                }
                _timerRemainingFlow.value = remMap
                _timerRunningFlow.value = runMap
                updateActiveTimerNotification(appCtx)

                if (finishedPresets.isNotEmpty()) {
                    persistTimerState(appCtx)
                    startAlarmSound(appCtx)
                    finishedPresets.forEach { preset ->
                        showTimerFinishedNotification(appCtx, preset)
                    }
                }
            }
        }
    }

    fun addSmartPlug(context: Context, name: String, ip: String, room: String = "Heimnetz") {
        ensureInitialized(context)
        val cleanIp = ip.trim()
        if (cleanIp.isBlank()) return
        val current = _smartPlugsFlow.value.toMutableList()
        val existingIdx = current.indexOfFirst { it.ip == cleanIp }
        val device = SmartPlugDevice(name = name.ifBlank { "Smart Plug ($cleanIp)" }, ip = cleanIp, room = room)
        if (existingIdx >= 0) {
            current[existingIdx] = device
        } else {
            current.add(device)
        }
        _smartPlugsFlow.value = current
        context.applicationContext.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
            .edit()
            .putString(KEY_SMART_PLUGS, gson.toJson(current))
            .apply()
    }

    fun saveTapoCredentials(context: Context, email: String, password: String) {
        context.applicationContext.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
            .edit()
            .putString(KEY_TAPO_EMAIL, email.trim())
            .putString(KEY_TAPO_PASS, password)
            .apply()
    }

    fun getTapoCredentials(context: Context): Pair<String, String> {
        val prefs = context.applicationContext.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        return Pair(
            prefs.getString(KEY_TAPO_EMAIL, "") ?: "",
            prefs.getString(KEY_TAPO_PASS, "") ?: ""
        )
    }

    fun recordWakeUpLog(
        context: Context,
        scheduledTime: String,
        actualWakeTime: String,
        snoozeMinutes: Int = 0,
        plugsTriggered: Boolean = false,
        aiBriefingSpoken: Boolean = false
    ): WakeUpLogEntry {
        ensureInitialized(context)
        val todayStr = SimpleDateFormat("yyyy-MM-dd", Locale.US).format(Date())
        val existing = _wakeLogsFlow.value[todayStr]
        val entry = WakeUpLogEntry(
            date = todayStr,
            scheduledTime = scheduledTime,
            actualWakeTime = actualWakeTime,
            snoozeMinutes = (existing?.snoozeMinutes ?: 0) + snoozeMinutes,
            plugsTriggered = plugsTriggered || (existing?.plugsTriggered == true),
            aiBriefingSpoken = aiBriefingSpoken || (existing?.aiBriefingSpoken == true)
        )
        val updated = _wakeLogsFlow.value.toMutableMap()
        updated[todayStr] = entry
        _wakeLogsFlow.value = updated
        context.applicationContext.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
            .edit()
            .putString(KEY_WAKE_LOGS, gson.toJson(updated))
            .apply()
        return entry
    }

    fun markSyncCompleted(context: Context, isoTimestamp: String?) {
        val stamp = isoTimestamp ?: SimpleDateFormat("dd.MM.yyyy HH:mm", Locale.GERMANY).format(Date())
        _lastSyncFlow.value = stamp
        context.applicationContext.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
            .edit()
            .putString(KEY_LAST_SYNC, stamp)
            .apply()
    }

    fun applyRemotePackage(
        context: Context,
        alarms: List<SmartAlarmItem>?,
        timers: List<TimerPresetItem>?,
        plugs: List<SmartPlugDevice>?,
        wakeLogs: Map<String, WakeUpLogEntry>?,
        lastSyncedAt: String?
    ) {
        ensureInitialized(context)
        val prefs = context.applicationContext.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        val editor = prefs.edit()

        if (!alarms.isNullOrEmpty()) {
            val sorted = alarms.sortedWith(compareBy({ it.hour }, { it.minute }))
            _alarmsFlow.value = sorted
            editor.putString(KEY_ALARMS, gson.toJson(sorted))
            scheduleAllActiveAlarms(context, sorted)
        }
        if (!timers.isNullOrEmpty()) {
            _timersFlow.value = timers
            editor.putString(KEY_TIMERS, gson.toJson(timers))
        }
        if (!plugs.isNullOrEmpty()) {
            _smartPlugsFlow.value = plugs
            editor.putString(KEY_SMART_PLUGS, gson.toJson(plugs))
        }
        if (!wakeLogs.isNullOrEmpty()) {
            val merged = _wakeLogsFlow.value.toMutableMap()
            merged.putAll(wakeLogs)
            _wakeLogsFlow.value = merged
            editor.putString(KEY_WAKE_LOGS, gson.toJson(merged))
        }
        if (!lastSyncedAt.isNullOrBlank()) {
            _lastSyncFlow.value = lastSyncedAt
            editor.putString(KEY_LAST_SYNC, lastSyncedAt)
        }
        editor.apply()
    }

    fun exportSyncPayloadStrings(context: Context): Map<String, String> {
        ensureInitialized(context)
        val nextAlarm = getNextActiveAlarm(context)
        return mapOf(
            "alarms_json" to gson.toJson(_alarmsFlow.value),
            "timers_json" to gson.toJson(_timersFlow.value),
            "smart_plugs_json" to gson.toJson(_smartPlugsFlow.value),
            "wake_up_logs_json" to gson.toJson(_wakeLogsFlow.value),
            "active_alarm_time" to (nextAlarm?.timeFormatted ?: "08:30"),
            "device_name" to "${Build.MANUFACTURER} ${Build.MODEL}".trim()
        )
    }

    // =========================================================================
    // EXACT ANDROID ALARM CLOCK SCHEDULING & STATUS BAR NOTIFICATIONS
    // =========================================================================
    fun scheduleAllActiveAlarms(context: Context, alarms: List<SmartAlarmItem>? = null) {
        val list = alarms ?: _alarmsFlow.value
        list.forEach { alarm ->
            if (alarm.isEnabled) {
                scheduleSingleAlarm(context, alarm)
            } else {
                cancelAlarm(context, alarm.id)
            }
        }
        updateActiveAlarmStatusNotification(context.applicationContext, list)
    }

    private fun ensureStatusNotificationChannel(context: Context, notificationManager: NotificationManager) {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val channel = NotificationChannel(
                STATUS_CHANNEL_ID,
                STATUS_CHANNEL_NAME,
                NotificationManager.IMPORTANCE_LOW
            ).apply {
                description = "Zeigt gestellte Wecker und laufende Timer oben in der Statusleiste"
                setShowBadge(false)
                enableVibration(false)
                setSound(null, null)
            }
            notificationManager.createNotificationChannel(channel)
        }
    }

    fun updateActiveAlarmStatusNotification(context: Context, alarms: List<SmartAlarmItem>? = null) {
        try {
            val appCtx = context.applicationContext
            val notificationManager = appCtx.getSystemService(Context.NOTIFICATION_SERVICE) as? NotificationManager ?: return
            val list = alarms ?: _alarmsFlow.value
            val enabledAlarms = list.filter { it.isEnabled }

            if (enabledAlarms.isEmpty()) {
                notificationManager.cancel(NOTIFICATION_ID_ACTIVE_ALARM)
                return
            }

            ensureStatusNotificationChannel(appCtx, notificationManager)

            val nextAlarm = enabledAlarms.minByOrNull {
                computeNextTriggerMillis(it.hour, it.minute, it.daysOfWeek)
            } ?: enabledAlarms.first()
            val nextTriggerMs = computeNextTriggerMillis(nextAlarm.hour, nextAlarm.minute, nextAlarm.daysOfWeek)

            val openIntent = Intent(appCtx, MainActivity::class.java).apply {
                flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
                putExtra("open_tab", 2)
                putExtra("open_subtab", 6)
                putExtra("alarm_inner_tab", 0)
            }
            val openPending = PendingIntent.getActivity(
                appCtx,
                91010,
                openIntent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )

            val disableIntent = Intent(appCtx, SmartAlarmReceiver::class.java).apply {
                action = SmartAlarmReceiver.ACTION_DISABLE_ALARM
                putExtra("alarm_id", nextAlarm.id)
            }
            val disablePending = PendingIntent.getBroadcast(
                appCtx,
                91011,
                disableIntent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )

            val title = if (enabledAlarms.size == 1) {
                "⏰ Wecker gestellt: ${nextAlarm.timeFormatted} Uhr"
            } else {
                "⏰ Nächster Wecker: ${nextAlarm.timeFormatted} Uhr (${enabledAlarms.size} aktiv)"
            }

            val badges = buildList {
                add(nextAlarm.label)
                add(nextAlarm.scheduleText)
                if (nextAlarm.triggerPlugOnRing && nextAlarm.smartPlugIps.isNotEmpty()) add("⚡ Smart-Home")
                if (nextAlarm.aiBriefingEnabled) add("🎙️ KI")
            }.joinToString(" · ")

            val notification = NotificationCompat.Builder(appCtx, STATUS_CHANNEL_ID)
                .setSmallIcon(android.R.drawable.ic_lock_idle_alarm)
                .setContentTitle(title)
                .setContentText(badges)
                .setWhen(nextTriggerMs)
                .setShowWhen(true)
                .setOngoing(true)
                .setOnlyAlertOnce(true)
                .setSilent(true)
                .setPriority(NotificationCompat.PRIORITY_LOW)
                .setCategory(NotificationCompat.CATEGORY_STATUS)
                .setContentIntent(openPending)
                .addAction(android.R.drawable.ic_menu_close_clear_cancel, "Ausschalten", disablePending)
                .build()

            notificationManager.notify(NOTIFICATION_ID_ACTIVE_ALARM, notification)
        } catch (e: Exception) {
            Log.e(TAG, "Failed to update active alarm status notification", e)
        }
    }

    fun updateActiveTimerNotification(context: Context) {
        try {
            val appCtx = context.applicationContext
            val notificationManager = appCtx.getSystemService(Context.NOTIFICATION_SERVICE) as? NotificationManager ?: return
            val presets = _timersFlow.value
            val remMap = _timerRemainingFlow.value
            val runMap = _timerRunningFlow.value

            // Find any running timer, or any paused timer that has already started counting down
            val activePresets = presets.filter { p ->
                val isRun = runMap[p.id] == true
                val rem = remMap[p.id] ?: p.durationSeconds
                isRun || (rem in 1 until p.durationSeconds)
            }

            if (activePresets.isEmpty()) {
                notificationManager.cancel(NOTIFICATION_ID_ACTIVE_TIMER)
                return
            }

            ensureStatusNotificationChannel(appCtx, notificationManager)

            // Prioritize currently running timer
            val primary = activePresets.firstOrNull { runMap[it.id] == true } ?: activePresets.first()
            val isRunning = runMap[primary.id] == true
            val remSec = remMap[primary.id] ?: primary.durationSeconds
            val mins = remSec / 60
            val secs = remSec % 60
            val formattedCountdown = String.format(Locale.GERMANY, "%02d:%02d", mins, secs)

            val openIntent = Intent(appCtx, MainActivity::class.java).apply {
                flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
                putExtra("open_tab", 2)
                putExtra("open_subtab", 6)
                putExtra("alarm_inner_tab", 1)
            }
            val openPending = PendingIntent.getActivity(
                appCtx,
                91020,
                openIntent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )

            val toggleIntent = Intent(appCtx, SmartAlarmReceiver::class.java).apply {
                action = SmartAlarmReceiver.ACTION_TIMER_TOGGLE
                putExtra("timer_id", primary.id)
            }
            val togglePending = PendingIntent.getBroadcast(
                appCtx,
                91021,
                toggleIntent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )

            val resetIntent = Intent(appCtx, SmartAlarmReceiver::class.java).apply {
                action = SmartAlarmReceiver.ACTION_TIMER_RESET
                putExtra("timer_id", primary.id)
            }
            val resetPending = PendingIntent.getBroadcast(
                appCtx,
                91022,
                resetIntent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )

            val title = "⏳ ${primary.label} · $formattedCountdown"
            val subtitle = if (activePresets.size > 1) {
                if (isRunning) "Läuft (${activePresets.size} Timer aktiv)" else "Pausiert (${activePresets.size} Timer aktiv)"
            } else {
                if (isRunning) "Timer läuft im Hintergrund" else "Timer pausiert"
            }

            val endMs = _timerEndMillisMap[primary.id]
            val builder = NotificationCompat.Builder(appCtx, STATUS_CHANNEL_ID)
                .setSmallIcon(android.R.drawable.ic_lock_idle_alarm)
                .setContentTitle(title)
                .setContentText(subtitle)
                .setProgress(primary.durationSeconds, (primary.durationSeconds - remSec).coerceAtLeast(0), false)
                .setOngoing(isRunning)
                .setOnlyAlertOnce(true)
                .setSilent(true)
                .setPriority(NotificationCompat.PRIORITY_LOW)
                .setCategory(NotificationCompat.CATEGORY_PROGRESS)
                .setContentIntent(openPending)
                .addAction(
                    if (isRunning) android.R.drawable.ic_media_pause else android.R.drawable.ic_media_play,
                    if (isRunning) "Pause" else "Weiter",
                    togglePending
                )
                .addAction(
                    android.R.drawable.ic_menu_close_clear_cancel,
                    "Zurücksetzen",
                    resetPending
                )

            if (isRunning && endMs != null && endMs > System.currentTimeMillis()) {
                builder.setWhen(endMs)
                    .setShowWhen(true)
                    .setUsesChronometer(true)
                    .setChronometerCountDown(true)
            }

            notificationManager.notify(NOTIFICATION_ID_ACTIVE_TIMER, builder.build())
        } catch (e: Exception) {
            Log.e(TAG, "Failed to update active timer notification", e)
        }
    }

    private fun showTimerFinishedNotification(context: Context, preset: TimerPresetItem) {
        try {
            val appCtx = context.applicationContext
            val notificationManager = appCtx.getSystemService(Context.NOTIFICATION_SERVICE) as? NotificationManager ?: return
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                val channel = NotificationChannel(
                    SmartAlarmReceiver.CHANNEL_ID,
                    SmartAlarmReceiver.CHANNEL_NAME,
                    NotificationManager.IMPORTANCE_HIGH
                ).apply {
                    description = "Wecker, Smart-Home Steuerung & Timer-Alarm"
                    enableLights(true)
                    enableVibration(true)
                }
                notificationManager.createNotificationChannel(channel)
            }

            val openIntent = Intent(appCtx, MainActivity::class.java).apply {
                flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
                putExtra("open_tab", 2)
                putExtra("open_subtab", 6)
                putExtra("alarm_inner_tab", 1)
            }
            val openPending = PendingIntent.getActivity(
                appCtx,
                91030,
                openIntent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )

            val stopIntent = Intent(appCtx, SmartAlarmReceiver::class.java).apply {
                action = SmartAlarmReceiver.ACTION_TIMER_STOP_SOUND
            }
            val stopPending = PendingIntent.getBroadcast(
                appCtx,
                91031,
                stopIntent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )

            val notification = NotificationCompat.Builder(appCtx, SmartAlarmReceiver.CHANNEL_ID)
                .setSmallIcon(android.R.drawable.ic_lock_idle_alarm)
                .setContentTitle("⏳ ${preset.label} ist abgelaufen!")
                .setContentText("Tippe auf Ton Stopp, um den Timer-Alarm zu beenden.")
                .setPriority(NotificationCompat.PRIORITY_MAX)
                .setCategory(NotificationCompat.CATEGORY_ALARM)
                .setContentIntent(openPending)
                .setOngoing(true)
                .addAction(android.R.drawable.ic_menu_close_clear_cancel, "Ton Stopp", stopPending)
                .build()

            notificationManager.notify(NOTIFICATION_ID_TIMER_FINISHED, notification)
        } catch (e: Exception) {
            Log.e(TAG, "Failed to show timer finished notification", e)
        }
    }

    fun scheduleSingleAlarm(context: Context, alarm: SmartAlarmItem) {
        val alarmManager = context.getSystemService(Context.ALARM_SERVICE) as? AlarmManager ?: return
        val triggerAtMillis = computeNextTriggerMillis(alarm.hour, alarm.minute, alarm.daysOfWeek)

        val intent = Intent(context, SmartAlarmReceiver::class.java).apply {
            action = SmartAlarmReceiver.ACTION_ALARM_TRIGGER
            putExtra("alarm_id", alarm.id)
            putExtra("alarm_label", alarm.label)
            putExtra("alarm_time", alarm.timeFormatted)
            putExtra("trigger_plugs", alarm.triggerPlugOnRing)
            putExtra("plug_ips", alarm.smartPlugIps.toTypedArray())
            putExtra("ai_briefing", alarm.aiBriefingEnabled)
            putExtra("sync_routine", alarm.syncWithRoutine)
        }

        val pendingIntent = PendingIntent.getBroadcast(
            context,
            alarm.id.hashCode(),
            intent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )

        val showIntent = Intent(context, MainActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
            putExtra("open_tab", 2)
            putExtra("open_subtab", 6)
            putExtra("alarm_inner_tab", 0)
        }
        val showPendingIntent = PendingIntent.getActivity(
            context,
            alarm.id.hashCode() + 1000,
            showIntent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )

        try {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
                val clockInfo = AlarmManager.AlarmClockInfo(triggerAtMillis, showPendingIntent)
                alarmManager.setAlarmClock(clockInfo, pendingIntent)
            } else {
                alarmManager.setExact(AlarmManager.RTC_WAKEUP, triggerAtMillis, pendingIntent)
            }
            Log.d(TAG, "Scheduled exact alarm ${alarm.id} (${alarm.timeFormatted}) for ${Date(triggerAtMillis)}")
        } catch (se: SecurityException) {
            Log.e(TAG, "SecurityException scheduling exact alarm, using fallback", se)
            try {
                alarmManager.set(AlarmManager.RTC_WAKEUP, triggerAtMillis, pendingIntent)
            } catch (_: Exception) {
            }
        }
    }

    fun scheduleSnoozeAlarm(context: Context, alarmId: String, label: String, scheduledTime: String, minutes: Int = 5) {
        val alarmManager = context.getSystemService(Context.ALARM_SERVICE) as? AlarmManager ?: return
        val triggerAtMillis = System.currentTimeMillis() + minutes * 60_000L

        val intent = Intent(context, SmartAlarmReceiver::class.java).apply {
            action = SmartAlarmReceiver.ACTION_ALARM_TRIGGER
            putExtra("alarm_id", alarmId)
            putExtra("alarm_label", "$label (Schlummern)")
            putExtra("alarm_time", scheduledTime)
            putExtra("trigger_plugs", true)
            putExtra("ai_briefing", true)
            putExtra("sync_routine", true)
        }

        val pendingIntent = PendingIntent.getBroadcast(
            context,
            (alarmId + "_snooze").hashCode(),
            intent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )

        try {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
                alarmManager.setExactAndAllowWhileIdle(AlarmManager.RTC_WAKEUP, triggerAtMillis, pendingIntent)
            } else {
                alarmManager.setExact(AlarmManager.RTC_WAKEUP, triggerAtMillis, pendingIntent)
            }
        } catch (_: Exception) {
        }
    }

    fun cancelAlarm(context: Context, alarmId: String) {
        val alarmManager = context.getSystemService(Context.ALARM_SERVICE) as? AlarmManager ?: return
        val intent = Intent(context, SmartAlarmReceiver::class.java).apply {
            action = SmartAlarmReceiver.ACTION_ALARM_TRIGGER
        }
        val pendingIntent = PendingIntent.getBroadcast(
            context,
            alarmId.hashCode(),
            intent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )
        alarmManager.cancel(pendingIntent)
    }

    private fun computeNextTriggerMillis(hour: Int, minute: Int, daysOfWeek: List<Int>): Long {
        val now = Calendar.getInstance()
        val candidate = Calendar.getInstance().apply {
            set(Calendar.HOUR_OF_DAY, hour)
            set(Calendar.MINUTE, minute)
            set(Calendar.SECOND, 0)
            set(Calendar.MILLISECOND, 0)
        }

        if (candidate.timeInMillis <= now.timeInMillis) {
            candidate.add(Calendar.DAY_OF_YEAR, 1)
        }

        if (daysOfWeek.isNotEmpty() && daysOfWeek.size < 7) {
            for (i in 0..7) {
                val calDow = candidate.get(Calendar.DAY_OF_WEEK)
                // Convert Calendar (Sunday=1..Saturday=7) to ISO (Monday=1..Sunday=7)
                val isoDow = if (calDow == Calendar.SUNDAY) 7 else calDow - 1
                if (daysOfWeek.contains(isoDow)) {
                    break
                }
                candidate.add(Calendar.DAY_OF_YEAR, 1)
            }
        }

        return candidate.timeInMillis
    }

    // =========================================================================
    // ALARM RINGTONE & AI MORNING VOICE BRIEFING (TTS)
    // =========================================================================
    fun startAlarmSound(context: Context) {
        try {
            stopAlarmSound()
            val uri = RingtoneManager.getDefaultUri(RingtoneManager.TYPE_ALARM)
                ?: RingtoneManager.getDefaultUri(RingtoneManager.TYPE_NOTIFICATION)
            val r = RingtoneManager.getRingtone(context.applicationContext, uri)
            r?.play()
            activeRingtone = r
        } catch (e: Exception) {
            Log.e(TAG, "Failed to play alarm sound", e)
        }
    }

    fun stopAlarmSound(context: Context? = null) {
        try {
            activeRingtone?.stop()
        } catch (_: Exception) {
        }
        activeRingtone = null
        try {
            activeTts?.stop()
        } catch (_: Exception) {
        }
        if (context != null) {
            try {
                val nm = context.applicationContext.getSystemService(Context.NOTIFICATION_SERVICE) as? NotificationManager
                nm?.cancel(NOTIFICATION_ID_TIMER_FINISHED)
            } catch (_: Exception) {
            }
        }
    }

    fun buildMorningBriefingText(context: Context, alarmLabel: String = "Seelenfunke Fokus-Start", plugsSwitched: Boolean = false): String {
        val timeStr = SimpleDateFormat("HH:mm", Locale.GERMANY).format(Date())

        // Read cached tasks & calendar events for personalized briefing
        var openTasksCount = 0
        var highPrioTasksCount = 0
        try {
            val taskPrefs = context.applicationContext.getSharedPreferences("tasks_widget_prefs", Context.MODE_PRIVATE)
            val tasksJson = taskPrefs.getString("tasks_cache", null)
            if (!tasksJson.isNullOrBlank()) {
                val type = object : TypeToken<List<ManagementTask>>() {}.type
                val tasks: List<ManagementTask> = gson.fromJson(tasksJson, type) ?: emptyList()
                val open = tasks.filter { !it.is_completed && it.parent_id == null }
                openTasksCount = open.size
                highPrioTasksCount = open.count { it.priority.equals("hoch", true) || it.priority.equals("high", true) }
            }
        } catch (_: Exception) {
        }

        var todayEventsCount = 0
        var firstEventTitle: String? = null
        try {
            val calPrefs = context.applicationContext.getSharedPreferences("calendar_widget_prefs", Context.MODE_PRIVATE)
            val calJson = calPrefs.getString("calendar_events_cache", null)
            val todayIso = SimpleDateFormat("yyyy-MM-dd", Locale.US).format(Date())
            if (!calJson.isNullOrBlank()) {
                val type = object : TypeToken<List<CalendarEvent>>() {}.type
                val events: List<CalendarEvent> = gson.fromJson(calJson, type) ?: emptyList()
                val todayEvents = events.filter { it.start.startsWith(todayIso) }
                todayEventsCount = todayEvents.size
                firstEventTitle = todayEvents.firstOrNull()?.title
            }
        } catch (_: Exception) {
        }

        val sb = StringBuilder()
        sb.append("Guten Morgen Alina! Es ist $timeStr Uhr. ")
        if (alarmLabel.isNotBlank()) {
            sb.append("Dein Wecker $alarmLabel ist aktiv. ")
        }
        if (plugsSwitched) {
            sb.append("Deine Smart-Home Steckdosen wurden im WLAN eingeschaltet. ")
        }
        if (todayEventsCount > 0) {
            sb.append("Heute stehen $todayEventsCount Termine im Kalender")
            if (!firstEventTitle.isNullOrBlank()) {
                sb.append(", darunter $firstEventTitle")
            }
            sb.append(". ")
        } else {
            sb.append("Heute sind keine festen Kalendentermine eingetragen. ")
        }

        if (openTasksCount > 0) {
            sb.append("Auf deiner To-do Liste warten $openTasksCount offene Aufgaben")
            if (highPrioTasksCount > 0) {
                sb.append(", davon $highPrioTasksCount mit hoher Priorität")
            }
            sb.append(". ")
        }

        sb.append("Deine Tagesroutine wird auf $timeStr Uhr getaktet. Starte entspannt und erfolgreich in deinen Tag!")
        return sb.toString()
    }

    fun speakMorningBriefing(context: Context, alarmLabel: String = "Seelenfunke Fokus-Start", plugsSwitched: Boolean = false) {
        val appCtx = context.applicationContext
        val text = buildMorningBriefingText(appCtx, alarmLabel, plugsSwitched)
        try {
            activeTts?.stop()
            activeTts?.shutdown()
        } catch (_: Exception) {
        }

        var ttsInstance: TextToSpeech? = null
        ttsInstance = TextToSpeech(appCtx) { status ->
            if (status == TextToSpeech.SUCCESS) {
                ttsInstance?.language = Locale.GERMANY
                ttsInstance?.setSpeechRate(1.0f)
                ttsInstance?.speak(text, TextToSpeech.QUEUE_FLUSH, null, "funkenwerk_morning_briefing")
            }
        }
        activeTts = ttsInstance
    }

    // =========================================================================
    // LOCAL WI-FI SMART-HOME PLUG CONTROL (TAPO P100/P110 KLAP v2 PROTOCOL)
    // =========================================================================
    fun triggerSmartPlugsAsync(
        context: Context,
        ips: List<String>,
        turnOn: Boolean = true,
        onResult: ((Boolean, String) -> Unit)? = null
    ) {
        if (ips.isEmpty()) {
            onResult?.invoke(false, "Keine Smart-Home Steckdose ausgewählt.")
            return
        }
        val (email, pass) = getTapoCredentials(context)
        scope.launch {
            val results = mutableListOf<String>()
            var anySuccess = false
            for (ip in ips) {
                val (ok, msg) = setPlugStateKlapV2(ip.trim(), email, pass, turnOn)
                if (ok) anySuccess = true
                results.add("$ip: $msg")
            }
            val summary = results.joinToString(" | ")
            withContext(Dispatchers.Main) {
                onResult?.invoke(anySuccess, summary)
            }
        }
    }

    suspend fun setPlugStateKlapV2(ip: String, email: String, password: String, turnOn: Boolean): Pair<Boolean, String> =
        withContext(Dispatchers.IO) {
            if (!ip.startsWith("192.168.") && !ip.startsWith("10.") && !ip.startsWith("172.")) {
                return@withContext Pair(false, "Nur lokale Heimnetz-IPs erlaubt.")
            }

            // If Tapo credentials are not yet set, probe the local plug HTTP endpoint so the user gets live LAN status feedback
            if (email.isBlank() || password.isBlank()) {
                return@withContext try {
                    val url = URL("http://$ip/app")
                    val conn = (url.openConnection() as HttpURLConnection).apply {
                        requestMethod = "POST"
                        connectTimeout = 1800
                        readTimeout = 1800
                        doOutput = true
                        setRequestProperty("Content-Type", "application/json")
                    }
                    conn.outputStream.use { it.write("{}".toByteArray(Charsets.UTF_8)) }
                    val code = conn.responseCode
                    conn.disconnect()
                    if (code in 200..499) {
                        Pair(true, "Steckdose im WLAN erreichbar (HTTP $code). Für KLAP-Schaltung Tapo-Login hinterlegen.")
                    } else {
                        Pair(false, "HTTP $code")
                    }
                } catch (e: Exception) {
                    Pair(false, "Nicht erreichbar (${e.javaClass.simpleName})")
                }
            }

            try {
                val cookieManager = CookieManager(null, CookiePolicy.ACCEPT_ALL)
                CookieHandler.setDefault(cookieManager)

                val localSeed = ByteArray(16)
                SecureRandom().nextBytes(localSeed)

                // 1. Handshake 1
                val hs1Bytes = postRawBytes("http://$ip/app/handshake1", localSeed, "application/octet-stream")
                    ?: return@withContext Pair(false, "Handshake1 fehlgeschlagen")
                if (hs1Bytes.size < 48) {
                    return@withContext Pair(false, "Ungültige KLAP v2 Antwort")
                }

                val remoteSeed = hs1Bytes.copyOfRange(0, 16)
                val serverHash = hs1Bytes.copyOfRange(16, 48)

                val userSha1 = sha1(email.trim().toByteArray(Charsets.UTF_8))
                val passSha1 = sha1(password.toByteArray(Charsets.UTF_8))
                val authHash = sha256(combine(userSha1, passSha1))

                val expectedServerHash = sha256(combine(localSeed, remoteSeed, authHash))
                if (!MessageDigest.isEqual(expectedServerHash, serverHash)) {
                    return@withContext Pair(false, "Tapo Zugangsdaten stimmen nicht überein")
                }

                // 2. Handshake 2
                val clientHash = sha256(combine(remoteSeed, localSeed, authHash))
                postRawBytes("http://$ip/app/handshake2", clientHash, "application/octet-stream")
                    ?: return@withContext Pair(false, "Handshake2 fehlgeschlagen")

                // 3. Derive AES-128-CBC Key, IV, Seq, SigKey
                val key = sha256(combine("lsk".toByteArray(Charsets.UTF_8), localSeed, remoteSeed, authHash)).copyOfRange(0, 16)
                val ivFull = sha256(combine("iv".toByteArray(Charsets.UTF_8), localSeed, remoteSeed, authHash))
                val ivPrefix = ivFull.copyOfRange(0, 12)
                val seq = ByteBuffer.wrap(ivFull.copyOfRange(ivFull.size - 4, ivFull.size)).int + 1
                val sigKey = sha256(combine("ldk".toByteArray(Charsets.UTF_8), localSeed, remoteSeed, authHash)).copyOfRange(0, 28)

                val seqBytes = ByteBuffer.allocate(4).putInt(seq).array()
                val iv = combine(ivPrefix, seqBytes)

                val payloadJson = """{"method":"set_device_info","params":{"device_on":$turnOn},"requestTimeMils":${System.currentTimeMillis()}}"""
                val cipher = Cipher.getInstance("AES/CBC/PKCS5Padding")
                cipher.init(Cipher.ENCRYPT_MODE, SecretKeySpec(key, "AES"), IvParameterSpec(iv))
                val cipherBytes = cipher.doFinal(payloadJson.toByteArray(Charsets.UTF_8))

                val sig = sha256(combine(sigKey, seqBytes, cipherBytes))
                val reqBody = combine(sig, cipherBytes)

                val resp = postRawBytes("http://$ip/app/request?seq=$seq", reqBody, "application/octet-stream")
                if (resp != null) {
                    Pair(true, if (turnOn) "EIN geschaltet" else "AUS geschaltet")
                } else {
                    Pair(false, "Befehl fehlgeschlagen")
                }
            } catch (e: Exception) {
                Pair(false, "Fehler: ${e.localizedMessage ?: e.javaClass.simpleName}")
            }
        }

    private fun postRawBytes(urlStr: String, body: ByteArray, contentType: String): ByteArray? {
        val conn = (URL(urlStr).openConnection() as HttpURLConnection).apply {
            requestMethod = "POST"
            connectTimeout = 2500
            readTimeout = 2500
            doOutput = true
            setRequestProperty("Content-Type", contentType)
        }
        conn.outputStream.use { it.write(body) }
        val code = conn.responseCode
        if (code !in 200..299) {
            conn.disconnect()
            return null
        }
        val bytes = conn.inputStream.use { it.readBytes() }
        conn.disconnect()
        return bytes
    }

    private fun sha1(data: ByteArray): ByteArray = MessageDigest.getInstance("SHA-1").digest(data)
    private fun sha256(data: ByteArray): ByteArray = MessageDigest.getInstance("SHA-256").digest(data)

    private fun combine(vararg arrays: ByteArray): ByteArray {
        val total = arrays.sumOf { it.size }
        val out = ByteArray(total)
        var pos = 0
        for (a in arrays) {
            System.arraycopy(a, 0, out, pos, a.size)
            pos += a.size
        }
        return out
    }
}
