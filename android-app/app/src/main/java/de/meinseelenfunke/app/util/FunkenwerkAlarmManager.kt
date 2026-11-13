package de.meinseelenfunke.app.util

import android.Manifest
import android.app.AlarmManager
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.media.AudioAttributes
import android.media.AudioFocusRequest
import android.media.AudioFormat
import android.media.AudioManager
import android.media.AudioRecord
import android.media.MediaPlayer
import android.media.MediaRecorder
import android.media.Ringtone
import android.media.RingtoneManager
import android.os.Build
import android.os.Bundle
import android.os.PowerManager
import android.os.VibrationEffect
import android.os.Vibrator
import android.os.VibratorManager
import android.speech.RecognitionListener
import android.speech.RecognizerIntent
import android.speech.SpeechRecognizer
import android.speech.tts.TextToSpeech
import android.speech.tts.UtteranceProgressListener
import android.util.Base64
import android.util.Log
import androidx.core.app.NotificationCompat
import androidx.core.content.ContextCompat
import com.google.gson.Gson
import com.google.gson.annotations.SerializedName
import com.google.gson.reflect.TypeToken
import de.meinseelenfunke.app.MainActivity
import de.meinseelenfunke.app.R
import de.meinseelenfunke.app.data.api.CalendarEvent
import de.meinseelenfunke.app.data.api.ManagementTask
import de.meinseelenfunke.app.di.ServiceLocator
import de.meinseelenfunke.app.receiver.SmartAlarmReceiver
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.Job
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.async
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import kotlinx.coroutines.suspendCancellableCoroutine
import kotlinx.coroutines.withContext
import kotlinx.coroutines.withTimeoutOrNull
import org.json.JSONArray
import org.json.JSONObject
import java.io.ByteArrayOutputStream
import java.io.File
import java.io.FileOutputStream
import java.net.CookieHandler
import java.net.CookieManager
import java.net.CookiePolicy
import java.net.HttpURLConnection
import java.net.URL
import java.nio.ByteBuffer
import java.nio.ByteOrder
import java.security.MessageDigest
import java.security.SecureRandom
import java.text.SimpleDateFormat
import java.util.Calendar
import java.util.Date
import java.util.Locale
import javax.crypto.Cipher
import android.net.wifi.WifiManager
import javax.crypto.spec.IvParameterSpec
import javax.crypto.spec.SecretKeySpec
import kotlin.coroutines.resume
import kotlin.math.roundToInt
import kotlin.math.sqrt
import de.meinseelenfunke.app.services.MusicPlaybackService

sealed class AlarmVoiceIntent {
    data class ShiftMinutes(val minutes: Int) : AlarmVoiceIntent()
    object DisableToday : AlarmVoiceIntent()
    object YesAskDuration : AlarmVoiceIntent()
    object No : AlarmVoiceIntent()
    object Unknown : AlarmVoiceIntent()
}

data class SmartAlarmItem(
    @SerializedName("id") val id: String = "",
    @SerializedName("hour") val hour: Int = 7,
    @SerializedName("minute") val minute: Int = 30,
    @SerializedName("label") val label: String = "Wecker",
    @SerializedName("schedule_text") val scheduleText: String = "Jeden Tag",
    @SerializedName("days_of_week") val daysOfWeek: List<Int> = listOf(1, 2, 3, 4, 5, 6, 7), // 1=Mo..7=So
    @SerializedName("is_enabled") val isEnabled: Boolean = false,
    @SerializedName("smart_plug_ips") val smartPlugIps: List<String> = emptyList(),
    @SerializedName("trigger_plug_on_ring") val triggerPlugOnRing: Boolean = true,
    @SerializedName("ai_briefing_enabled") val aiBriefingEnabled: Boolean = true,
    @SerializedName("sync_with_routine") val syncWithRoutine: Boolean = true,
    @SerializedName("alarm_sound_track_id") val alarmSoundTrackId: String? = null,
    @SerializedName("alarm_sound_title") val alarmSoundTitle: String? = null
) {
    val timeFormatted: String
        get() = String.format(Locale.GERMANY, "%02d:%02d", hour, minute)

    val effectiveSoundDisplay: String
        get() = when {
            alarmSoundTrackId == "random_music" -> "Zufallslied (Musikbibliothek)"
            !alarmSoundTitle.isNullOrBlank() -> alarmSoundTitle
            else -> "Standard-Weckton"
        }
}

data class TimerPresetItem(
    @SerializedName("id") val id: String = "",
    @SerializedName("label") val label: String = "",
    @SerializedName("duration_seconds") val durationSeconds: Int = 300
)

data class SmartPlugDevice(
    @SerializedName("name") val name: String = "",
    @SerializedName("ip") val ip: String = "",
    @SerializedName("room") val room: String = "easy-AP · Heimnetz",
    @SerializedName("model") val model: String = "Tapo P100/P110"
)

data class SmartCameraDevice(
    @SerializedName("name") val name: String = "",
    @SerializedName("ip") val ip: String = "",
    @SerializedName("model") val model: String = "",
    @SerializedName("description") val description: String = ""
)

data class WakeUpLogEntry(
    @SerializedName("date") val date: String = "",
    @SerializedName("scheduled_time") val scheduledTime: String = "",
    @SerializedName("actual_wake_time") val actualWakeTime: String = "",
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
    private const val KEY_LAST_WEATHER_SENTENCE = "gifhorn_weather_sentence_v2"
    private const val KEY_LAST_WEATHER_DATE = "gifhorn_weather_date"

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

    private val _isAlarmRingingFlow = MutableStateFlow(false)
    val isAlarmRingingFlow: StateFlow<Boolean> = _isAlarmRingingFlow.asStateFlow()

    private val _activeRingingAlarmInfoFlow = MutableStateFlow<Triple<String, String, String>?>(null)
    val activeRingingAlarmInfoFlow: StateFlow<Triple<String, String, String>?> = _activeRingingAlarmInfoFlow.asStateFlow()

    private val _alarmDialogStateFlow = MutableStateFlow<String?>(null)
    val alarmDialogStateFlow: StateFlow<String?> = _alarmDialogStateFlow.asStateFlow()

    private val _snoozedUntilTimeFlow = MutableStateFlow<String?>(null)
    val snoozedUntilTimeFlow: StateFlow<String?> = _snoozedUntilTimeFlow.asStateFlow()

    private val activeAlarmNotificationIds = mutableSetOf<Int>()

    @Volatile
    private var cachedGifhornWeatherSentence: String? = null
    @Volatile
    private var cachedGifhornWeatherAtMs: Long = 0L
    @Volatile
    private var lastTriggerPlugsSwitched: Boolean = false

    fun registerActiveAlarmNotification(notificationId: Int, alarmId: String, alarmLabel: String, scheduledTime: String) {
        synchronized(activeAlarmNotificationIds) {
            activeAlarmNotificationIds.add(notificationId)
        }
        _activeRingingAlarmInfoFlow.value = Triple(alarmId, alarmLabel, scheduledTime)
        _isAlarmRingingFlow.value = true
    }

    private var activeRingtone: Ringtone? = null
    private var activeAlarmPlayer: MediaPlayer? = null
    private var activeBriefingPlayer: MediaPlayer? = null
    private var activeTts: TextToSpeech? = null
    private var alarmSequenceJob: Job? = null
    private var alarmWakeLock: PowerManager.WakeLock? = null
    private var alarmWifiLock: WifiManager.WifiLock? = null
    private var alarmAudioFocusRequest: AudioFocusRequest? = null
    @Volatile
    private var activeAlarmCustomTrackId: String? = null
    @Volatile
    private var isAlarmDismissed: Boolean = false
    private var timerTickerJob: Job? = null
    private var isInitialized = false

    fun setActiveInnerSubTab(subTab: Int) {
        _activeInnerSubTabFlow.value = subTab.coerceIn(0, 2)
    }

    fun normalizeSmartPlugs(raw: List<SmartPlugDevice>?): List<SmartPlugDevice> {
        val canonicalByIp = defaultSmartPlugs().associateBy { it.ip }
        val result = LinkedHashMap<String, SmartPlugDevice>()
        // Always include the 3 canonical PC Funkenwerk Smart-Home plugs in exact order
        for (def in defaultSmartPlugs()) {
            result[def.ip] = def
        }
        for (item in raw.orEmpty()) {
            val rawIp = (item.ip as String?)?.trim().orEmpty()
            val rawName = (item.name as String?)?.trim().orEmpty()
            val cleanIp = when (rawIp) {
                "192.168.188.106" -> "192.168.188.110"
                else -> rawIp
            }
            if (cleanIp.isBlank()) continue
            val canonical = canonicalByIp[cleanIp]
            val isLegacyName = rawName.contains("Test-Steckdose", ignoreCase = true) ||
                rawName.startsWith("Smart Plug #", ignoreCase = true) ||
                rawName.isBlank()
            if (canonical != null) {
                result[cleanIp] = if (isLegacyName) canonical else canonical.copy(name = canonical.name)
            } else if (!isLegacyName) {
                result[cleanIp] = item.copy(
                    name = rawName,
                    ip = cleanIp,
                    room = (item.room as String?)?.ifBlank { "easy-AP · Heimnetz" } ?: "easy-AP · Heimnetz",
                    model = (item.model as String?)?.ifBlank { "Tapo P100/P110" } ?: "Tapo P100/P110"
                )
            }
        }
        return result.values.toList()
    }

    fun normalizeAlarms(raw: List<SmartAlarmItem>): List<SmartAlarmItem> {
        return raw.mapIndexed { idx, alarm ->
            val safeId = (alarm.id as String?)?.trim()?.takeIf { it.isNotBlank() }
                ?: String.format(Locale.US, "alarm_%02d%02d_%d", alarm.hour, alarm.minute, idx)
            val safeLabel = (alarm.label as String?)?.trim()?.takeIf { it.isNotBlank() } ?: "Wecker"
            val safeSchedule = (alarm.scheduleText as String?)?.trim()?.takeIf { it.isNotBlank() } ?: "Jeden Tag"
            val safeDays = (alarm.daysOfWeek as List<Int>?) ?: listOf(1, 2, 3, 4, 5, 6, 7)
            val safePlugList = (alarm.smartPlugIps as List<String>?) ?: emptyList()
            val fixedIps = safePlugList.map { ip ->
                if (ip.trim() == "192.168.188.106") "192.168.188.110" else ip.trim()
            }.filter { it.isNotBlank() }.distinct()
            alarm.copy(
                id = safeId,
                label = safeLabel,
                scheduleText = safeSchedule,
                daysOfWeek = safeDays,
                smartPlugIps = fixedIps
            )
        }.distinctBy { it.id }
    }

    fun normalizeTimers(raw: List<TimerPresetItem>?): List<TimerPresetItem> {
        if (raw.isNullOrEmpty()) return defaultTimers()
        val canonicalByDuration = defaultTimers().associateBy { it.durationSeconds }
        val usedIds = HashSet<String>()
        val result = ArrayList<TimerPresetItem>(raw.size)

        raw.forEachIndexed { idx, item ->
            val dur = item.durationSeconds.coerceAtLeast(5)
            val canonical = canonicalByDuration[dur]
            val rawId = (item.id as String?)?.trim()
            val rawLabel = (item.label as String?)?.trim()

            var resolvedId = when {
                !rawId.isNullOrBlank() && rawId != "null" -> rawId
                canonical != null && !usedIds.contains(canonical.id) -> canonical.id
                else -> "timer_${dur}_$idx"
            }
            if (!usedIds.add(resolvedId)) {
                resolvedId = "${resolvedId}_$idx"
                usedIds.add(resolvedId)
            }

            val resolvedLabel = when {
                !rawLabel.isNullOrBlank() && rawLabel != "null" -> rawLabel
                canonical != null -> canonical.label
                dur % 60 == 0 -> "Timer: ${dur / 60} Min."
                dur > 60 -> "Timer: ${dur / 60}m ${dur % 60}s"
                else -> "Timer: ${dur}s"
            }

            result.add(TimerPresetItem(id = resolvedId, label = resolvedLabel, durationSeconds = dur))
        }

        return if (result.isEmpty()) defaultTimers() else result.sortedBy { it.durationSeconds }
    }

    private fun parseTimersJsonSelfHealing(timersJson: String): List<TimerPresetItem> {
        return try {
            val arr = JSONArray(timersJson)
            val parsed = ArrayList<TimerPresetItem>(arr.length())
            for (i in 0 until arr.length()) {
                val obj = arr.optJSONObject(i) ?: continue
                val id = obj.optString("id", "").trim().ifBlank { obj.optString("a", "").trim() }
                val label = obj.optString("label", "").trim().ifBlank { obj.optString("b", "").trim() }
                val dur = obj.optInt("duration_seconds", obj.optInt("c", 300)).coerceAtLeast(5)
                parsed.add(TimerPresetItem(id = id, label = label, durationSeconds = dur))
            }
            normalizeTimers(parsed)
        } catch (_: Exception) {
            try {
                val type = object : TypeToken<List<TimerPresetItem>>() {}.type
                normalizeTimers(gson.fromJson(timersJson, type))
            } catch (_: Exception) {
                defaultTimers()
            }
        }
    }

    fun ensureInitialized(context: Context) {
        if (isInitialized && _alarmsFlow.value.isNotEmpty()) {
            updateActiveAlarmStatusNotification(context.applicationContext, _alarmsFlow.value)
            return
        }
        val appCtx = context.applicationContext
        ServiceLocator.ensureInitialized(appCtx)
        val prefs = appCtx.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)

        // Migrate any legacy plaintext Tapo credentials into AndroidKeyStore AES-256-GCM and wipe from prefs
        val legacyTapoEmail = prefs.getString(KEY_TAPO_EMAIL, null)
        val legacyTapoPass = prefs.getString(KEY_TAPO_PASS, null)
        if (!legacyTapoEmail.isNullOrBlank() && ServiceLocator.getTapoEmail().isBlank()) {
            ServiceLocator.saveTapoEmail(legacyTapoEmail)
        }
        if (!legacyTapoPass.isNullOrBlank() && ServiceLocator.getTapoPassword().isBlank()) {
            ServiceLocator.saveTapoPassword(legacyTapoPass)
        }
        if (prefs.contains(KEY_TAPO_EMAIL) || prefs.contains(KEY_TAPO_PASS)) {
            prefs.edit().remove(KEY_TAPO_EMAIL).remove(KEY_TAPO_PASS).apply()
        }

        // 1. Load or seed default Alarms and migrate legacy plug IPs
        val alarmsJson = prefs.getString(KEY_ALARMS, null)
        val rawAlarms: List<SmartAlarmItem> = if (!alarmsJson.isNullOrBlank()) {
            try {
                val type = object : TypeToken<List<SmartAlarmItem>>() {}.type
                gson.fromJson(alarmsJson, type) ?: defaultAlarms()
            } catch (_: Exception) {
                defaultAlarms()
            }
        } else {
            defaultAlarms()
        }
        val loadedAlarms = normalizeAlarms(rawAlarms).sortedWith(compareBy({ it.hour }, { it.minute }))
        _alarmsFlow.value = loadedAlarms
        prefs.edit().putString(KEY_ALARMS, gson.toJson(loadedAlarms)).apply()

        // 2. Load or seed default Timers (self-heals any R8-corrupted or missing IDs/labels)
        val timersJson = prefs.getString(KEY_TIMERS, null)
        val loadedTimers: List<TimerPresetItem> = if (!timersJson.isNullOrBlank()) {
            parseTimersJsonSelfHealing(timersJson)
        } else {
            defaultTimers()
        }
        _timersFlow.value = loadedTimers
        prefs.edit().putString(KEY_TIMERS, gson.toJson(loadedTimers)).apply()
        restorePersistedTimerState(appCtx, loadedTimers)

        // 3. Load and normalize Smart Plugs to match PC Funkenwerk SmartHome
        val plugsJson = prefs.getString(KEY_SMART_PLUGS, null)
        val rawPlugs: List<SmartPlugDevice> = if (!plugsJson.isNullOrBlank()) {
            try {
                val type = object : TypeToken<List<SmartPlugDevice>>() {}.type
                gson.fromJson(plugsJson, type) ?: defaultSmartPlugs()
            } catch (_: Exception) {
                defaultSmartPlugs()
            }
        } else {
            defaultSmartPlugs()
        }
        val loadedPlugs = normalizeSmartPlugs(rawPlugs)
        _smartPlugsFlow.value = loadedPlugs
        prefs.edit().putString(KEY_SMART_PLUGS, gson.toJson(loadedPlugs)).apply()

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
        prewarmAlarmBriefingsAsync(appCtx)
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
            smartPlugIps = listOf("192.168.188.109", "192.168.188.110"),
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
        SmartPlugDevice(name = "Lichterkette Fußleiste", ip = "192.168.188.109", room = "easy-AP · Heimnetz", model = "Tapo P100 (HW 2.0)"),
        SmartPlugDevice(name = "Packtisch Warm Licht", ip = "192.168.188.110", room = "easy-AP · Heimnetz", model = "Tapo P100/P110"),
        SmartPlugDevice(name = "Lichterkette Spiegel", ip = "192.168.188.123", room = "easy-AP · Heimnetz", model = "Tapo P100/P110")
    )

    fun defaultSmartCameras(): List<SmartCameraDevice> = listOf(
        SmartCameraDevice(name = "Terasse", ip = "192.168.188.105", model = "Tapo C520WS", description = "Terrasse und Gartenbereich"),
        SmartCameraDevice(name = "Einfahrt", ip = "192.168.188.132", model = "Tapo C520WS", description = "Hofeinfahrt und Eingangsbereich"),
        SmartCameraDevice(name = "Garage", ip = "192.168.188.102", model = "Tapo C210", description = "Garagentor und Stellplatz"),
        SmartCameraDevice(name = "Gewerbe", ip = "192.168.188.101", model = "Tapo C210", description = "Gewerbe & Manufaktur")
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
        val sorted = normalizeAlarms(alarms).sortedWith(compareBy({ it.hour }, { it.minute }))
        _alarmsFlow.value = sorted
        context.applicationContext.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
            .edit()
            .putString(KEY_ALARMS, gson.toJson(sorted))
            .apply()
        scheduleAllActiveAlarms(context, sorted)
        prewarmAlarmBriefingsAsync(context.applicationContext)
        sorted.forEach { alarm ->
            val trackId = alarm.alarmSoundTrackId
            if (!trackId.isNullOrBlank() && trackId != "standard" && trackId != "random_music") {
                MusicPlayerManager.cacheTrackForAlarmAsync(context.applicationContext, trackId)
            }
        }
    }

    fun toggleAlarm(context: Context, alarmId: String): SmartAlarmItem? {
        ensureInitialized(context)
        stopAlarmSound(context)
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
        stopAlarmSound(context)
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
        stopAlarmSound(context)
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

    fun isValidPrivateLanIpv4(ip: String): Boolean {
        val parts = ip.trim().split('.')
        if (parts.size != 4) return false
        val octets = parts.map { part ->
            if (part.isEmpty() || part.length > 3 || !part.all { it.isDigit() }) return false
            val v = part.toIntOrNull() ?: return false
            if (v !in 0..255) return false
            v
        }
        return when (octets[0]) {
            10 -> true
            172 -> octets[1] in 16..31
            192 -> octets[1] == 168
            else -> false
        }
    }

    fun addSmartPlug(context: Context, name: String, ip: String, room: String = "Heimnetz") {
        ensureInitialized(context)
        val cleanIp = ip.trim()
        if (!isValidPrivateLanIpv4(cleanIp)) return
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
        val appCtx = context.applicationContext
        ServiceLocator.ensureInitialized(appCtx)
        ServiceLocator.saveTapoEmail(email)
        ServiceLocator.saveTapoPassword(password)
        appCtx.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
            .edit()
            .remove(KEY_TAPO_EMAIL)
            .remove(KEY_TAPO_PASS)
            .apply()
    }

    fun getTapoCredentials(context: Context): Pair<String, String> {
        val appCtx = context.applicationContext
        ServiceLocator.ensureInitialized(appCtx)
        return Pair(ServiceLocator.getTapoEmail(), ServiceLocator.getTapoPassword())
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
        val baseScheduled = existing?.scheduledTime?.takeIf { it.isNotBlank() } ?: scheduledTime
        val entry = WakeUpLogEntry(
            date = todayStr,
            scheduledTime = baseScheduled,
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
            val localById = _alarmsFlow.value.associateBy { it.id }
            val mergedAlarms = normalizeAlarms(alarms).map { remote ->
                val local = localById[remote.id]
                if (remote.alarmSoundTrackId.isNullOrBlank() && local != null && !local.alarmSoundTrackId.isNullOrBlank()) {
                    remote.copy(
                        alarmSoundTrackId = local.alarmSoundTrackId,
                        alarmSoundTitle = local.alarmSoundTitle
                    )
                } else {
                    remote
                }
            }
            val sorted = mergedAlarms.sortedWith(compareBy({ it.hour }, { it.minute }))
            _alarmsFlow.value = sorted
            editor.putString(KEY_ALARMS, gson.toJson(sorted))
            scheduleAllActiveAlarms(context, sorted)
        }
        if (!timers.isNullOrEmpty()) {
            val normalizedTimers = normalizeTimers(timers)
            _timersFlow.value = normalizedTimers
            syncTimerMapsWithPresets(normalizedTimers)
            editor.putString(KEY_TIMERS, gson.toJson(normalizedTimers))
        }
        if (!plugs.isNullOrEmpty()) {
            val normalizedPlugs = normalizeSmartPlugs(plugs)
            _smartPlugsFlow.value = normalizedPlugs
            editor.putString(KEY_SMART_PLUGS, gson.toJson(normalizedPlugs))
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

    /**
     * Pushes the current alarm, smart_plugs, and wake_up_logs package across all reachable Seelenfunke endpoints
     * (LAN + Stage + Live) so /admin/routine is always synchronized even if one endpoint is unreachable.
     */
    fun pushAlarmPackageToAllEndpointsAsync(context: Context) {
        val appCtx = context.applicationContext
        scope.launch(Dispatchers.IO) {
            try {
                ensureInitialized(appCtx)
                val payloadMap = exportSyncPayloadStrings(appCtx)
                val bodyJson = JSONObject().apply {
                    payloadMap.forEach { (k, v) -> put(k, v) }
                }.toString()
                val token = try {
                    ServiceLocator.init(appCtx)
                    ServiceLocator.getAuthToken()
                } catch (_: Exception) {
                    null
                }
                for (base in apiBaseCandidates(appCtx)) {
                    try {
                        val isLan = base.contains("192.168.") || base.contains("10.0.")
                        val conn = (URL("$base/funki/routine/alarm-sync").openConnection() as HttpURLConnection).apply {
                            requestMethod = "POST"
                            connectTimeout = if (isLan) 2200 else 4500
                            readTimeout = 5000
                            doOutput = true
                            setRequestProperty("Content-Type", "application/json")
                            setRequestProperty("Accept", "application/json")
                            if (!token.isNullOrBlank()) {
                                setRequestProperty("Authorization", "Bearer $token")
                            }
                        }
                        conn.outputStream.use { it.write(bodyJson.toByteArray(Charsets.UTF_8)) }
                        val code = conn.responseCode
                        conn.disconnect()
                        if (code in 200..299) {
                            markSyncCompleted(appCtx, SimpleDateFormat("dd.MM.yyyy HH:mm", Locale.GERMANY).format(Date()))
                        }
                    } catch (_: Exception) {
                    }
                }
            } catch (e: Exception) {
                Log.w(TAG, "pushAlarmPackageToAllEndpointsAsync failed: ${e.message}")
            }
        }
    }

    /**
     * Computes today's dynamic routine time shift in minutes based on the active routine-synced alarm or today's wake-up log.
     * Matches ManagementRoutine.php::shiftRoutineTimeString on the web portal (/admin/routine).
     */
    fun computeTodayRoutineShift(firstRoutineBaseStartTime: String): Pair<Int, String?> {
        val todayStr = SimpleDateFormat("yyyy-MM-dd", Locale.US).format(Date())
        val todayWakeLog = _wakeLogsFlow.value[todayStr]?.actualWakeTime?.take(5)
        val syncedAlarm = _alarmsFlow.value
            .filter { it.isEnabled && it.syncWithRoutine }
            .minByOrNull { it.hour * 60 + it.minute }
        val targetWakeTime = todayWakeLog ?: syncedAlarm?.timeFormatted ?: return Pair(0, null)

        return try {
            val baseParts = firstRoutineBaseStartTime.take(5).split(":")
            val baseMins = (baseParts.getOrNull(0)?.toIntOrNull() ?: 7) * 60 + (baseParts.getOrNull(1)?.toIntOrNull() ?: 0)
            val wakeParts = targetWakeTime.take(5).split(":")
            val wakeMins = (wakeParts.getOrNull(0)?.toIntOrNull() ?: 7) * 60 + (wakeParts.getOrNull(1)?.toIntOrNull() ?: 0)
            val shift = (wakeMins - baseMins).coerceIn(-180, 360)
            Pair(shift, targetWakeTime.take(5))
        } catch (_: Exception) {
            Pair(0, null)
        }
    }

    fun shiftRoutineTimeString(baseTimeStr: String, shiftMinutes: Int): String {
        val clean = baseTimeStr.take(5)
        if (shiftMinutes == 0) return clean
        return try {
            val parts = clean.split(":")
            val baseMins = (parts.getOrNull(0)?.toIntOrNull() ?: 0) * 60 + (parts.getOrNull(1)?.toIntOrNull() ?: 0)
            val shifted = if (baseMins >= 21 * 60 && shiftMinutes > 0) {
                minOf(23 * 60 + 30, baseMins + Math.round(shiftMinutes * 0.35f))
            } else {
                (baseMins + shiftMinutes).coerceIn(0, 23 * 60 + 45)
            }
            String.format(Locale.GERMANY, "%02d:%02d", shifted / 60, shifted % 60)
        } catch (_: Exception) {
            clean
        }
    }

    // =========================================================================
    // EXACT ANDROID ALARM CLOCK SCHEDULING & STATUS BAR NOTIFICATIONS
    // =========================================================================
    fun scheduleAllActiveAlarms(context: Context, alarms: List<SmartAlarmItem>? = null) {
        if (alarms == null && (!isInitialized || _alarmsFlow.value.isEmpty())) {
            val prefs = context.applicationContext.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
            val alarmsJson = prefs.getString(KEY_ALARMS, null)
            if (!alarmsJson.isNullOrBlank()) {
                try {
                    val type = object : TypeToken<List<SmartAlarmItem>>() {}.type
                    val loaded: List<SmartAlarmItem>? = gson.fromJson(alarmsJson, type)
                    if (!loaded.isNullOrEmpty()) {
                        _alarmsFlow.value = loaded.sortedWith(compareBy({ it.hour }, { it.minute }))
                    }
                } catch (_: Exception) {
                }
            }
        }
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

            val openIntent = SecurityIntentHelper.signIntent(appCtx, Intent(appCtx, MainActivity::class.java).apply {
                flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
                putExtra("open_tab", 2)
                putExtra("open_subtab", 6)
                putExtra("alarm_inner_tab", 0)
            })
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

            val snoozedUntil = _snoozedUntilTimeFlow.value
            val title = when {
                !snoozedUntil.isNullOrBlank() -> "⏰ Wecker verschoben auf $snoozedUntil Uhr"
                enabledAlarms.size == 1 -> "⏰ Wecker gestellt: ${nextAlarm.timeFormatted} Uhr"
                else -> "⏰ Nächster Wecker: ${nextAlarm.timeFormatted} Uhr (${enabledAlarms.size} aktiv)"
            }

            val badges = buildList {
                add(nextAlarm.label)
                add(nextAlarm.scheduleText)
                if (nextAlarm.triggerPlugOnRing && nextAlarm.smartPlugIps.isNotEmpty()) add("⚡ Smart-Home")
                if (nextAlarm.aiBriefingEnabled) add("🎙️ KI")
            }.joinToString(" · ")

            val stopSoundIntent = Intent(appCtx, SmartAlarmReceiver::class.java).apply {
                action = SmartAlarmReceiver.ACTION_TIMER_STOP_SOUND
            }
            val stopSoundPending = PendingIntent.getBroadcast(
                appCtx,
                91012,
                stopSoundIntent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )

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
                .setDeleteIntent(stopSoundPending)
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

            val openIntent = SecurityIntentHelper.signIntent(appCtx, Intent(appCtx, MainActivity::class.java).apply {
                flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
                putExtra("open_tab", 2)
                putExtra("open_subtab", 6)
                putExtra("alarm_inner_tab", 1)
            })
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
                    setSound(null, null)
                }
                notificationManager.createNotificationChannel(channel)
            }

            val openIntent = SecurityIntentHelper.signIntent(appCtx, Intent(appCtx, MainActivity::class.java).apply {
                flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
                putExtra("open_tab", 2)
                putExtra("open_subtab", 6)
                putExtra("alarm_inner_tab", 1)
            })
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
                .setContentText("Tippe auf Ton Stopp oder wische den Hinweis weg, um den Alarm zu beenden.")
                .setPriority(NotificationCompat.PRIORITY_MAX)
                .setCategory(NotificationCompat.CATEGORY_ALARM)
                .setContentIntent(openPending)
                .setDeleteIntent(stopPending)
                .setOngoing(false)
                .setAutoCancel(true)
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
            putExtra("alarm_sound_track_id", alarm.alarmSoundTrackId ?: "")
        }

        val pendingIntent = PendingIntent.getBroadcast(
            context,
            alarm.id.hashCode(),
            intent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )

        val showIntent = SecurityIntentHelper.signIntent(context, Intent(context, MainActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
            putExtra("open_tab", 2)
            putExtra("open_subtab", 6)
            putExtra("alarm_inner_tab", 0)
        })
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

    fun scheduleSnoozeAlarm(context: Context, alarmId: String, label: String, scheduledTime: String, minutes: Int = 5): String {
        val appCtx = context.applicationContext
        val alarmManager = appCtx.getSystemService(Context.ALARM_SERVICE) as? AlarmManager
        val cleanMinutes = minutes.coerceIn(1, 360)
        val triggerAtMillis = System.currentTimeMillis() + cleanMinutes * 60_000L
        val targetTimeStr = SimpleDateFormat("HH:mm", Locale.GERMANY).format(Date(triggerAtMillis))
        val existingAlarm = _alarmsFlow.value.find { it.id == alarmId }
        val baseLabel = label.replace(" (Schlummern)", "").trim().ifBlank { existingAlarm?.label ?: "Wecker" }

        val intent = Intent(appCtx, SmartAlarmReceiver::class.java).apply {
            action = SmartAlarmReceiver.ACTION_ALARM_TRIGGER
            putExtra("alarm_id", alarmId)
            putExtra("alarm_label", "$baseLabel (Schlummern)")
            putExtra("alarm_time", scheduledTime)
            putExtra("trigger_plugs", existingAlarm?.triggerPlugOnRing ?: true)
            putExtra("plug_ips", (existingAlarm?.smartPlugIps ?: emptyList()).toTypedArray())
            putExtra("ai_briefing", existingAlarm?.aiBriefingEnabled ?: true)
            putExtra("sync_routine", existingAlarm?.syncWithRoutine ?: true)
            putExtra("alarm_sound_track_id", existingAlarm?.alarmSoundTrackId ?: "")
        }

        if (alarmManager != null) {
            val pendingIntent = PendingIntent.getBroadcast(
                appCtx,
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

        _snoozedUntilTimeFlow.value = targetTimeStr
        recordWakeUpLog(
            context = appCtx,
            scheduledTime = scheduledTime,
            actualWakeTime = targetTimeStr,
            snoozeMinutes = cleanMinutes
        )
        updateActiveAlarmStatusNotification(appCtx)
        pushAlarmPackageToAllEndpointsAsync(appCtx)
        return targetTimeStr
    }

    fun cancelSnoozeAlarm(context: Context, alarmId: String? = null) {
        val appCtx = context.applicationContext
        val alarmManager = appCtx.getSystemService(Context.ALARM_SERVICE) as? AlarmManager ?: return
        val ids = if (!alarmId.isNullOrBlank()) {
            listOf(alarmId)
        } else {
            (_alarmsFlow.value.map { it.id } + listOf("alarm_0830", "alarm_0645", "alarm_1300")).distinct()
        }
        for (id in ids) {
            val intent = Intent(appCtx, SmartAlarmReceiver::class.java).apply {
                action = SmartAlarmReceiver.ACTION_ALARM_TRIGGER
            }
            val pendingIntent = PendingIntent.getBroadcast(
                appCtx,
                (id + "_snooze").hashCode(),
                intent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )
            alarmManager.cancel(pendingIntent)
        }
        _snoozedUntilTimeFlow.value = null
        updateActiveAlarmStatusNotification(appCtx)
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
        cancelSnoozeAlarm(context, alarmId)
    }

    fun formatTimeUntilAlarm(alarm: SmartAlarmItem): String {
        val nowMs = System.currentTimeMillis()
        val effectiveDays = if (alarm.daysOfWeek.isEmpty()) listOf(1, 2, 3, 4, 5, 6, 7) else alarm.daysOfWeek
        val triggerMs = computeNextTriggerMillis(alarm.hour, alarm.minute, effectiveDays)
        val diffMs = (triggerMs - nowMs).coerceAtLeast(0L)
        val totalMinutes = ((diffMs + 59_999L) / 60_000L).toInt().coerceAtLeast(1)
        val days = totalMinutes / (24 * 60)
        val remAfterDays = totalMinutes % (24 * 60)
        val hours = remAfterDays / 60
        val minutes = remAfterDays % 60

        return when {
            days > 0 -> {
                val dayPart = if (days == 1) "1 Tag" else "$days Tagen"
                val hourPart = if (hours > 0) {
                    if (hours == 1) ", 1 Stunde" else ", $hours Stunden"
                } else ""
                val minPart = if (minutes > 0) {
                    if (minutes == 1) " und 1 Minute" else " und $minutes Minuten"
                } else ""
                "Wecker klingelt in $dayPart$hourPart$minPart"
            }
            hours > 0 && minutes > 0 -> {
                val hourPart = if (hours == 1) "1 Stunde" else "$hours Stunden"
                val minPart = if (minutes == 1) "1 Minute" else "$minutes Minuten"
                "Wecker klingelt in $hourPart und $minPart"
            }
            hours > 0 -> {
                val hourPart = if (hours == 1) "1 Stunde" else "$hours Stunden"
                "Wecker klingelt in $hourPart"
            }
            minutes > 1 -> "Wecker klingelt in $minutes Minuten"
            else -> "Wecker klingelt in weniger als 1 Minute"
        }
    }

    fun computeNextTriggerMillis(hour: Int, minute: Int, daysOfWeek: List<Int>): Long {
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
    // ALARM RINGTONE & FUNKIRA AI MORNING VOICE BRIEFING (AOEDE / USAGE_ALARM)
    // =========================================================================
    private fun acquireAlarmWakeLock(context: Context, timeoutMs: Long = 180_000L) {
        try {
            val appCtx = context.applicationContext
            val pm = appCtx.getSystemService(Context.POWER_SERVICE) as? PowerManager
            if (pm != null) {
                if (alarmWakeLock?.isHeld == true) {
                    alarmWakeLock?.release()
                }
                val wl = pm.newWakeLock(PowerManager.PARTIAL_WAKE_LOCK, "Funkenwerk:SmartAlarmWakeLock")
                wl.setReferenceCounted(false)
                wl.acquire(timeoutMs)
                alarmWakeLock = wl
            }

            val wm = appCtx.getSystemService(Context.WIFI_SERVICE) as? WifiManager
            if (wm != null) {
                if (alarmWifiLock?.isHeld == true) {
                    alarmWifiLock?.release()
                }
                @Suppress("DEPRECATION")
                val wfl = wm.createWifiLock(WifiManager.WIFI_MODE_FULL_HIGH_PERF, "Funkenwerk:SmartAlarmWifiLock")
                wfl.setReferenceCounted(false)
                wfl.acquire()
                alarmWifiLock = wfl
            }
        } catch (e: Exception) {
            Log.e(TAG, "Failed to acquire alarm WakeLock / WifiLock", e)
        }
    }

    private fun releaseAlarmWakeLock() {
        try {
            if (alarmWakeLock?.isHeld == true) {
                alarmWakeLock?.release()
            }
        } catch (_: Exception) {
        }
        alarmWakeLock = null

        try {
            if (alarmWifiLock?.isHeld == true) {
                alarmWifiLock?.release()
            }
        } catch (_: Exception) {
        }
        alarmWifiLock = null
    }

    private fun ensureAlarmStreamVolume(context: Context) {
        try {
            val am = context.applicationContext.getSystemService(Context.AUDIO_SERVICE) as? AudioManager ?: return
            val maxVol = am.getStreamMaxVolume(AudioManager.STREAM_ALARM)
            val curVol = am.getStreamVolume(AudioManager.STREAM_ALARM)
            val minTarget = (maxVol * 0.65f).toInt().coerceAtLeast(1)
            if (curVol < minTarget) {
                am.setStreamVolume(AudioManager.STREAM_ALARM, minTarget, 0)
            }
        } catch (e: Exception) {
            Log.w(TAG, "Could not adjust STREAM_ALARM volume", e)
        }
    }

    private fun requestAlarmAudioDucking(context: Context) {
        try {
            MusicPlayerManager.setAlarmDucking(true)
        } catch (_: Exception) {
        }
        try {
            val am = context.applicationContext.getSystemService(Context.AUDIO_SERVICE) as? AudioManager ?: return
            val attrs = AudioAttributes.Builder()
                .setUsage(AudioAttributes.USAGE_ALARM)
                .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
                .build()
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                val req = alarmAudioFocusRequest ?: AudioFocusRequest.Builder(AudioManager.AUDIOFOCUS_GAIN_TRANSIENT_MAY_DUCK)
                    .setAudioAttributes(attrs)
                    .setWillPauseWhenDucked(false)
                    .setOnAudioFocusChangeListener { }
                    .build().also { alarmAudioFocusRequest = it }
                am.requestAudioFocus(req)
            } else {
                @Suppress("DEPRECATION")
                am.requestAudioFocus(null, AudioManager.STREAM_ALARM, AudioManager.AUDIOFOCUS_GAIN_TRANSIENT_MAY_DUCK)
            }
        } catch (e: Exception) {
            Log.w(TAG, "Could not request alarm audio ducking", e)
        }
    }

    private fun releaseAlarmAudioDucking(context: Context?) {
        try {
            MusicPlayerManager.setAlarmDucking(false)
        } catch (_: Exception) {
        }
        if (context == null) return
        try {
            val am = context.applicationContext.getSystemService(Context.AUDIO_SERVICE) as? AudioManager ?: return
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                alarmAudioFocusRequest?.let { am.abandonAudioFocusRequest(it) }
            } else {
                @Suppress("DEPRECATION")
                am.abandonAudioFocus(null)
            }
        } catch (_: Exception) {
        }
    }

    private fun startAlarmVibration(context: Context) {
        try {
            val appCtx = context.applicationContext
            val vibrator: Vibrator? = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                val vm = appCtx.getSystemService(Context.VIBRATOR_MANAGER_SERVICE) as? VibratorManager
                vm?.defaultVibrator
            } else {
                @Suppress("DEPRECATION")
                appCtx.getSystemService(Context.VIBRATOR_SERVICE) as? Vibrator
            }
            if (vibrator?.hasVibrator() == true) {
                val pattern = longArrayOf(0L, 500L, 500L, 500L, 800L)
                val attrs = AudioAttributes.Builder()
                    .setUsage(AudioAttributes.USAGE_ALARM)
                    .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
                    .build()
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                    vibrator.vibrate(VibrationEffect.createWaveform(pattern, 0), attrs)
                } else {
                    @Suppress("DEPRECATION")
                    vibrator.vibrate(pattern, 0, attrs)
                }
            }
        } catch (e: Exception) {
            Log.w(TAG, "Could not start alarm vibration", e)
        }
    }

    private fun stopAlarmVibration(context: Context?) {
        if (context == null) return
        try {
            val appCtx = context.applicationContext
            val vibrator: Vibrator? = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                val vm = appCtx.getSystemService(Context.VIBRATOR_MANAGER_SERVICE) as? VibratorManager
                vm?.defaultVibrator
            } else {
                @Suppress("DEPRECATION")
                appCtx.getSystemService(Context.VIBRATOR_SERVICE) as? Vibrator
            }
            vibrator?.cancel()
        } catch (_: Exception) {
        }
    }

    fun startLoopingAlarmPlayerOnly(context: Context, customTrackId: String? = activeAlarmCustomTrackId) {
        val appCtx = context.applicationContext
        activeAlarmCustomTrackId = customTrackId
        ensureAlarmStreamVolume(appCtx)
        stopAlarmRingtoneOnly(appCtx)

        val alarmAttrs = AudioAttributes.Builder()
            .setUsage(AudioAttributes.USAGE_ALARM)
            .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
            .build()

        var started = false

        // 0. If user selected a custom song (or random song) from the Music Library, play it on USAGE_ALARM!
        if (!customTrackId.isNullOrBlank() && customTrackId != "standard") {
            try {
                val trackFile = MusicPlayerManager.resolveAlarmTrackFile(appCtx, customTrackId)
                if (trackFile != null && trackFile.exists() && trackFile.length() > 10240L) {
                    val musicAlarmAttrs = AudioAttributes.Builder()
                        .setUsage(AudioAttributes.USAGE_ALARM)
                        .setContentType(AudioAttributes.CONTENT_TYPE_MUSIC)
                        .build()
                    val mp = MediaPlayer()
                    mp.setAudioAttributes(musicAlarmAttrs)
                    mp.setDataSource(trackFile.absolutePath)
                    mp.isLooping = true
                    mp.setVolume(1.0f, 1.0f)
                    mp.prepare()
                    mp.start()
                    activeAlarmPlayer = mp
                    started = true
                }
            } catch (e: Exception) {
                Log.w(TAG, "Custom alarm song ($customTrackId) failed, falling back to system alarm sound", e)
            }
        }

        if (!started) {
            val candidateUris = listOfNotNull(
                RingtoneManager.getDefaultUri(RingtoneManager.TYPE_ALARM),
                RingtoneManager.getDefaultUri(RingtoneManager.TYPE_RINGTONE),
                RingtoneManager.getDefaultUri(RingtoneManager.TYPE_NOTIFICATION)
            )

            for (uri in candidateUris) {
                try {
                    val mp = MediaPlayer()
                    mp.setAudioAttributes(alarmAttrs)
                    mp.setDataSource(appCtx, uri)
                    mp.isLooping = true
                    mp.setVolume(1.0f, 1.0f)
                    mp.prepare()
                    mp.start()
                    activeAlarmPlayer = mp
                    started = true
                    break
                } catch (e: Exception) {
                    Log.w(TAG, "Alarm URI failed ($uri), trying next fallback", e)
                }
            }
        }

        if (!started) {
            try {
                val afd = appCtx.resources.openRawResourceFd(R.raw.open_project_brain)
                if (afd != null) {
                    val mp = MediaPlayer()
                    mp.setAudioAttributes(alarmAttrs)
                    mp.setDataSource(afd.fileDescriptor, afd.startOffset, afd.length)
                    afd.close()
                    mp.isLooping = true
                    mp.setVolume(1.0f, 1.0f)
                    mp.prepare()
                    mp.start()
                    activeAlarmPlayer = mp
                    started = true
                }
            } catch (e: Exception) {
                Log.e(TAG, "Raw fallback alarm failed", e)
            }
        }

        if (!started) {
            try {
                val uri = RingtoneManager.getDefaultUri(RingtoneManager.TYPE_ALARM)
                    ?: RingtoneManager.getDefaultUri(RingtoneManager.TYPE_NOTIFICATION)
                val r = RingtoneManager.getRingtone(appCtx, uri)
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
                    r?.audioAttributes = alarmAttrs
                }
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) {
                    r?.isLooping = true
                }
                r?.play()
                activeRingtone = r
            } catch (e: Exception) {
                Log.e(TAG, "Ringtone fallback failed", e)
            }
        }

        startAlarmVibration(appCtx)
    }

    private fun pauseAlarmRingtoneOnly(context: Context) {
        try {
            if (activeAlarmPlayer?.isPlaying == true) {
                activeAlarmPlayer?.pause()
            }
        } catch (_: Exception) {
        }
        try {
            activeRingtone?.stop()
        } catch (_: Exception) {
        }
        stopAlarmVibration(context)
    }

    private fun resumeAlarmRingtoneOnly(context: Context) {
        if (isAlarmDismissed) return
        try {
            val mp = activeAlarmPlayer
            if (mp != null) {
                mp.start()
                startAlarmVibration(context)
                return
            }
        } catch (_: Exception) {
        }
        startLoopingAlarmPlayerOnly(context, activeAlarmCustomTrackId)
    }

    private fun stopAlarmRingtoneOnly(context: Context? = null) {
        try {
            activeAlarmPlayer?.stop()
        } catch (_: Exception) {
        }
        try {
            activeAlarmPlayer?.release()
        } catch (_: Exception) {
        }
        activeAlarmPlayer = null

        try {
            activeRingtone?.stop()
        } catch (_: Exception) {
        }
        activeRingtone = null
        stopAlarmVibration(context)
    }

    private fun stopBriefingOnly() {
        try {
            activeBriefingPlayer?.stop()
        } catch (_: Exception) {
        }
        try {
            activeBriefingPlayer?.release()
        } catch (_: Exception) {
        }
        activeBriefingPlayer = null

        try {
            activeTts?.stop()
            activeTts?.shutdown()
        } catch (_: Exception) {
        }
        activeTts = null
    }

    fun stopAllActiveAudio(context: Context? = null) {
        stopAlarmSound(context)
    }

    fun startAlarmSound(context: Context, customTrackId: String? = null) {
        val appCtx = context.applicationContext
        stopAlarmSound(appCtx)
        isAlarmDismissed = false
        _isAlarmRingingFlow.value = true
        acquireAlarmWakeLock(appCtx, 180_000L)
        requestAlarmAudioDucking(appCtx)
        startLoopingAlarmPlayerOnly(appCtx, customTrackId)
    }

    fun isAlarmSoundPlaying(): Boolean {
        return try {
            _isAlarmRingingFlow.value || activeAlarmPlayer?.isPlaying == true || activeRingtone?.isPlaying == true || activeBriefingPlayer?.isPlaying == true
        } catch (_: Exception) {
            false
        }
    }

    private const val INITIAL_ALARM_QUESTION = "Guten Morgen! Soll ich den Wecker direkt verschieben?"
    private const val FOLLOWUP_DURATION_QUESTION = "Um wie viele Minuten soll ich ihn verschieben, oder soll er heute gar nicht mehr klingeln?"
    private const val DISABLE_TODAY_CONFIRMATION = "Alles klar, der Wecker klingelt heute nicht mehr. Ruh dich noch gut aus, bis später!"

    /**
     * Triggered by SmartAlarmReceiver at alarm time:
     * 1. Promotes process to Foreground Service + acquires WakeLock & WifiLock + ducks running music + starts chosen Wecksound.
     * 2. If aiBriefing is enabled, plays Wecksound for ~3.2s while resolving Funkira's opening question ("Guten Morgen! Soll ich den Wecker direkt verschieben?") and Gifhorn weather.
     * 3. Pauses the Wecksound completely (0% overlap!) and asks if the alarm should be shifted.
     * 4. Listens for user response ("Ja" + minutes / "gar nicht klingeln" / "Nein"):
     *    - On "Ja" + minutes: shifts alarm via scheduleSnoozeAlarm, updates /admin/routine, confirms & stops ringing.
     *    - On "Ja" + "gar nicht klingeln": disables/stops alarm for today, updates /admin/routine, confirms & stops ringing.
     *    - On "Ja" without duration: asks how many minutes or if it shouldn't ring at all, listens again, and executes.
     *    - On "Nein": stops alarm ringtone, logs actual wake-up time to /admin/routine, and continues with the Morning Briefing
     *      (including current weekday/date, Gifhorn weather, 1-sentence evening walk outfit recommendation, Smart-Home, calendar, tasks, routine alignment, and warm goodbye).
     */
    fun triggerAlarmSequence(
        context: Context,
        alarmId: String = "alarm_0830",
        alarmLabel: String = "Seelenfunke Fokus-Start",
        scheduledTime: String? = null,
        aiBriefing: Boolean = true,
        plugsSwitched: Boolean = false,
        customTrackId: String? = null
    ) {
        val appCtx = context.applicationContext
        stopAlarmSound(appCtx)
        isAlarmDismissed = false
        lastTriggerPlugsSwitched = plugsSwitched
        _isAlarmRingingFlow.value = true
        _snoozedUntilTimeFlow.value = null
        val effTime = scheduledTime?.takeIf { it.isNotBlank() } ?: SimpleDateFormat("HH:mm", Locale.GERMANY).format(Date())
        _activeRingingAlarmInfoFlow.value = Triple(alarmId, alarmLabel, effTime)
        _alarmDialogStateFlow.value = "Wecker klingelt..."
        acquireAlarmWakeLock(appCtx, 300_000L)
        // Promote process via MusicPlaybackService so Android 14 never freezes the coroutine after BroadcastReceiver returns
        MusicPlaybackService.startOrUpdate(appCtx)
        requestAlarmAudioDucking(appCtx)

        // 1. Start the wake-up alarm sound (custom song or standard tone) immediately on STREAM_ALARM
        startLoopingAlarmPlayerOnly(appCtx, customTrackId)

        if (!aiBriefing) {
            return
        }

        alarmSequenceJob = scope.launch {
            val prevLiveCallState = NavigationBridge.isLiveCallActive
            try {
                // Prevent background WakeWordService from colliding with microphone or false-triggering during alarm dialog
                NavigationBridge.isLiveCallActive = true

                // Pre-fetch opening question WAV & live Gifhorn weather in parallel while alarm tone rings for 3.2s
                val askWavDeferred = async(Dispatchers.IO) {
                    fetchOrSynthesizeFunkiraWav(appCtx, INITIAL_ALARM_QUESTION, "alarm_ask_shift")
                }
                val weatherDeferred = async(Dispatchers.IO) {
                    fetchGifhornWeatherAndWalkSentence(appCtx)
                }

                delay(3200L)
                val askWavFile = withTimeoutOrNull(8000L) { askWavDeferred.await() }

                if (isAlarmDismissed) return@launch

                // 2. Pause wake-up sound & vibration completely before Funkira speaks (0% overlap!)
                withContext(Dispatchers.Main) {
                    pauseAlarmRingtoneOnly(appCtx)
                }
                delay(350L)

                if (isAlarmDismissed) return@launch

                // 3. Funkira asks right at the start: "Guten Morgen! Soll ich den Wecker direkt verschieben?"
                _alarmDialogStateFlow.value = "Funkira: „Guten Morgen, soll ich den Wecker direkt verschieben?“"
                playBriefingAndAwaitCompletion(appCtx, askWavFile, INITIAL_ALARM_QUESTION)

                if (isAlarmDismissed) return@launch

                // 4. Listen for user answer ("Ja", "Ja um 15 Minuten", "Gar nicht klingeln", or "Nein")
                _alarmDialogStateFlow.value = "🎙️ Höre zu: „Ja (+Minuten / gar nicht)“ oder „Nein“..."
                val transcript1 = listenForAlarmVoiceCommand(appCtx, timeoutMs = 8000L)

                if (isAlarmDismissed) return@launch

                when (val decision1 = parseAlarmVoiceCommand(transcript1, defaultNumberAsMinutes = false)) {
                    is AlarmVoiceIntent.DisableToday -> {
                        executeDisableAlarmTodayFlow(appCtx, alarmId, effTime, plugsSwitched)
                        return@launch
                    }
                    is AlarmVoiceIntent.ShiftMinutes -> {
                        executeShiftAlarmMinutesFlow(appCtx, alarmId, alarmLabel, effTime, decision1.minutes, plugsSwitched)
                        return@launch
                    }
                    is AlarmVoiceIntent.YesAskDuration -> {
                        _alarmDialogStateFlow.value = "Funkira: „Wie viele Minuten später oder gar nicht klingeln?“"
                        val followWav = withTimeoutOrNull(8000L) {
                            fetchOrSynthesizeFunkiraWav(appCtx, FOLLOWUP_DURATION_QUESTION, "alarm_ask_duration")
                        }
                        if (isAlarmDismissed) return@launch
                        playBriefingAndAwaitCompletion(appCtx, followWav, FOLLOWUP_DURATION_QUESTION)
                        if (isAlarmDismissed) return@launch

                        _alarmDialogStateFlow.value = "🎙️ Höre zu: Minuten nennen oder „Gar nicht klingeln“..."
                        val transcript2 = listenForAlarmVoiceCommand(appCtx, timeoutMs = 8000L)
                        if (isAlarmDismissed) return@launch

                        when (val decision2 = parseAlarmVoiceCommand(transcript2, defaultNumberAsMinutes = true)) {
                            is AlarmVoiceIntent.DisableToday -> {
                                executeDisableAlarmTodayFlow(appCtx, alarmId, effTime, plugsSwitched)
                                return@launch
                            }
                            is AlarmVoiceIntent.ShiftMinutes -> {
                                executeShiftAlarmMinutesFlow(appCtx, alarmId, alarmLabel, effTime, decision2.minutes, plugsSwitched)
                                return@launch
                            }
                            is AlarmVoiceIntent.No -> {
                                val weatherStr = withTimeoutOrNull(4000L) { weatherDeferred.await() }
                                executeContinueRoutineBriefingFlow(appCtx, alarmId, alarmLabel, effTime, plugsSwitched, weatherStr)
                                return@launch
                            }
                            else -> {
                                // User said "Ja" to shifting but gave no clear duration on second prompt -> default to 10 minutes
                                executeShiftAlarmMinutesFlow(appCtx, alarmId, alarmLabel, effTime, 10, plugsSwitched)
                                return@launch
                            }
                        }
                    }
                    is AlarmVoiceIntent.No -> {
                        val weatherStr = withTimeoutOrNull(4000L) { weatherDeferred.await() }
                        executeContinueRoutineBriefingFlow(appCtx, alarmId, alarmLabel, effTime, plugsSwitched, weatherStr)
                        return@launch
                    }
                    is AlarmVoiceIntent.Unknown -> {
                        // Silence / no spoken answer: speak the full morning briefing (with date, Gifhorn weather & walk outfit)
                        // and then resume the wake-up ringtone so the user doesn't oversleep accidentally.
                        val weatherStr = withTimeoutOrNull(4000L) { weatherDeferred.await() }
                        val nowWakeStr = SimpleDateFormat("HH:mm", Locale.GERMANY).format(Date())
                        val briefingText = buildMorningBriefingText(
                            context = appCtx,
                            alarmLabel = alarmLabel,
                            plugsSwitched = plugsSwitched,
                            overrideTimeStr = nowWakeStr,
                            isContinuationAfterNo = false,
                            preloadedWeatherSentence = weatherStr
                        )
                        _alarmDialogStateFlow.value = "🌅 Morgen-Briefing & Wetter Gifhorn..."
                        val wavFile = withTimeoutOrNull(12000L) {
                            fetchOrSynthesizeFunkiraWav(appCtx, briefingText, alarmId)
                        }
                        if (isAlarmDismissed) return@launch
                        playBriefingAndAwaitCompletion(appCtx, wavFile, briefingText)
                        if (isAlarmDismissed) return@launch

                        _alarmDialogStateFlow.value = "Wecker klingelt (Aufstehen oder Verschieben wählen)"
                        delay(1200L)
                        if (!isAlarmDismissed) {
                            withContext(Dispatchers.Main) {
                                resumeAlarmRingtoneOnly(appCtx)
                            }
                        }
                    }
                }
            } catch (e: Exception) {
                Log.e(TAG, "Error in triggerAlarmSequence", e)
            } finally {
                NavigationBridge.isLiveCallActive = prevLiveCallState
            }
        }
    }

    private suspend fun executeShiftAlarmMinutesFlow(
        context: Context,
        alarmId: String,
        alarmLabel: String,
        scheduledTime: String,
        minutes: Int,
        plugsSwitched: Boolean
    ) {
        val appCtx = context.applicationContext
        val cleanMin = minutes.coerceIn(1, 360)
        val newTargetTimeStr = scheduleSnoozeAlarm(appCtx, alarmId, alarmLabel, scheduledTime, cleanMin)
        recordWakeUpLog(
            context = appCtx,
            scheduledTime = scheduledTime,
            actualWakeTime = newTargetTimeStr,
            snoozeMinutes = 0, // already added inside scheduleSnoozeAlarm
            plugsTriggered = plugsSwitched,
            aiBriefingSpoken = false
        )
        pushAlarmPackageToAllEndpointsAsync(appCtx)

        val confirmText = "Alles klar, ich verschiebe den Wecker um $cleanMin Minuten auf $newTargetTimeStr Uhr. Bis gleich!"
        _alarmDialogStateFlow.value = "⏰ Verschoben um +$cleanMin Min. auf $newTargetTimeStr Uhr"
        val confirmWav = withTimeoutOrNull(8000L) {
            fetchOrSynthesizeFunkiraWav(appCtx, confirmText, "alarm_shift_$cleanMin")
        }
        if (!isAlarmDismissed) {
            playBriefingAndAwaitCompletion(appCtx, confirmWav, confirmText)
        }
        stopAlarmSound(appCtx)
    }

    private suspend fun executeDisableAlarmTodayFlow(
        context: Context,
        alarmId: String,
        scheduledTime: String,
        plugsSwitched: Boolean
    ) {
        val appCtx = context.applicationContext
        cancelSnoozeAlarm(appCtx, alarmId)
        val existing = _alarmsFlow.value.find { it.id == alarmId }
        if (existing != null && existing.daysOfWeek.isEmpty() && existing.isEnabled) {
            toggleAlarm(appCtx, alarmId)
        }
        val nowTimeStr = SimpleDateFormat("HH:mm", Locale.GERMANY).format(Date())
        recordWakeUpLog(
            context = appCtx,
            scheduledTime = scheduledTime,
            actualWakeTime = nowTimeStr,
            snoozeMinutes = 0,
            plugsTriggered = plugsSwitched,
            aiBriefingSpoken = true
        )
        pushAlarmPackageToAllEndpointsAsync(appCtx)

        _alarmDialogStateFlow.value = "🔕 Wecker für heute beendet"
        val confirmWav = withTimeoutOrNull(8000L) {
            fetchOrSynthesizeFunkiraWav(appCtx, DISABLE_TODAY_CONFIRMATION, "alarm_disable_today")
        }
        if (!isAlarmDismissed) {
            playBriefingAndAwaitCompletion(appCtx, confirmWav, DISABLE_TODAY_CONFIRMATION)
        }
        stopAlarmSound(appCtx)
    }

    private suspend fun executeContinueRoutineBriefingFlow(
        context: Context,
        alarmId: String,
        alarmLabel: String,
        scheduledTime: String,
        plugsSwitched: Boolean,
        preloadedWeatherSentence: String? = null
    ) {
        val appCtx = context.applicationContext
        cancelSnoozeAlarm(appCtx, alarmId)
        val nowWakeTimeStr = SimpleDateFormat("HH:mm", Locale.GERMANY).format(Date())
        recordWakeUpLog(
            context = appCtx,
            scheduledTime = scheduledTime,
            actualWakeTime = nowWakeTimeStr,
            snoozeMinutes = 0,
            plugsTriggered = plugsSwitched,
            aiBriefingSpoken = true
        )
        pushAlarmPackageToAllEndpointsAsync(appCtx)

        val briefingText = buildMorningBriefingText(
            context = appCtx,
            alarmLabel = alarmLabel,
            plugsSwitched = plugsSwitched,
            overrideTimeStr = nowWakeTimeStr,
            isContinuationAfterNo = true,
            preloadedWeatherSentence = preloadedWeatherSentence
        )
        _alarmDialogStateFlow.value = "🌅 Morgen-Routine, Wetter Gifhorn & Spaziergang-Tipp..."
        val wavFile = withTimeoutOrNull(12000L) {
            fetchOrSynthesizeFunkiraWav(appCtx, briefingText, "${alarmId}_cont")
        }
        if (!isAlarmDismissed) {
            playBriefingAndAwaitCompletion(appCtx, wavFile, briefingText)
        }
        stopAlarmSound(appCtx)
    }

    /**
     * Interactive UI & Tool Actions so the user can also tap quick buttons or ask Funkira in Live Call.
     */
    fun userRespondShiftAlarm(context: Context, minutes: Int) {
        val appCtx = context.applicationContext
        val info = _activeRingingAlarmInfoFlow.value
        val fallbackAlarm = getNextActiveAlarm(appCtx) ?: _alarmsFlow.value.firstOrNull()
        val alarmId = info?.first ?: fallbackAlarm?.id ?: "alarm_0830"
        val alarmLabel = info?.second ?: fallbackAlarm?.label ?: "Seelenfunke Fokus-Start"
        val scheduledTime = info?.third ?: fallbackAlarm?.timeFormatted ?: SimpleDateFormat("HH:mm", Locale.GERMANY).format(Date())
        val plugs = lastTriggerPlugsSwitched

        try { alarmSequenceJob?.cancel() } catch (_: Exception) {}
        pauseAlarmRingtoneOnly(appCtx)
        isAlarmDismissed = false

        alarmSequenceJob = scope.launch {
            executeShiftAlarmMinutesFlow(appCtx, alarmId, alarmLabel, scheduledTime, minutes, plugs)
        }
    }

    fun userRespondDisableAlarmToday(context: Context) {
        val appCtx = context.applicationContext
        val info = _activeRingingAlarmInfoFlow.value
        val fallbackAlarm = getNextActiveAlarm(appCtx) ?: _alarmsFlow.value.firstOrNull()
        val alarmId = info?.first ?: fallbackAlarm?.id ?: "alarm_0830"
        val scheduledTime = info?.third ?: fallbackAlarm?.timeFormatted ?: SimpleDateFormat("HH:mm", Locale.GERMANY).format(Date())
        val plugs = lastTriggerPlugsSwitched

        try { alarmSequenceJob?.cancel() } catch (_: Exception) {}
        pauseAlarmRingtoneOnly(appCtx)
        isAlarmDismissed = false

        alarmSequenceJob = scope.launch {
            executeDisableAlarmTodayFlow(appCtx, alarmId, scheduledTime, plugs)
        }
    }

    fun userRespondContinueRoutineNow(context: Context) {
        val appCtx = context.applicationContext
        val info = _activeRingingAlarmInfoFlow.value
        val fallbackAlarm = getNextActiveAlarm(appCtx) ?: _alarmsFlow.value.firstOrNull()
        val alarmId = info?.first ?: fallbackAlarm?.id ?: "alarm_0830"
        val alarmLabel = info?.second ?: fallbackAlarm?.label ?: "Seelenfunke Fokus-Start"
        val scheduledTime = info?.third ?: fallbackAlarm?.timeFormatted ?: SimpleDateFormat("HH:mm", Locale.GERMANY).format(Date())
        val plugs = lastTriggerPlugsSwitched

        try { alarmSequenceJob?.cancel() } catch (_: Exception) {}
        pauseAlarmRingtoneOnly(appCtx)
        isAlarmDismissed = false
        _isAlarmRingingFlow.value = true
        acquireAlarmWakeLock(appCtx, 120_000L)
        requestAlarmAudioDucking(appCtx)

        alarmSequenceJob = scope.launch {
            val prevLive = NavigationBridge.isLiveCallActive
            try {
                NavigationBridge.isLiveCallActive = true
                val weatherStr = withTimeoutOrNull(4000L) { fetchGifhornWeatherAndWalkSentence(appCtx) }
                executeContinueRoutineBriefingFlow(appCtx, alarmId, alarmLabel, scheduledTime, plugs, weatherStr)
            } finally {
                NavigationBridge.isLiveCallActive = prevLive
            }
        }
    }

    fun shiftActiveOrNextAlarmFromTool(context: Context, minutes: Int): String {
        val appCtx = context.applicationContext
        ensureInitialized(appCtx)
        val cleanMin = minutes.coerceIn(1, 360)
        val info = _activeRingingAlarmInfoFlow.value
        val targetAlarm = if (info != null) {
            _alarmsFlow.value.find { it.id == info.first }
        } else {
            getNextActiveAlarm(appCtx) ?: _alarmsFlow.value.firstOrNull()
        }
        val alarmId = info?.first ?: targetAlarm?.id ?: "alarm_0830"
        val alarmLabel = info?.second ?: targetAlarm?.label ?: "Seelenfunke Fokus-Start"
        val scheduledTime = info?.third ?: targetAlarm?.timeFormatted ?: SimpleDateFormat("HH:mm", Locale.GERMANY).format(Date())

        stopAlarmSound(appCtx)
        val newTargetTimeStr = scheduleSnoozeAlarm(appCtx, alarmId, alarmLabel, scheduledTime, cleanMin)
        return "Wecker '$alarmLabel' wurde um $cleanMin Minuten auf $newTargetTimeStr Uhr verschoben und mit /admin/routine synchronisiert."
    }

    fun disableAlarmTodayFromTool(context: Context): String {
        val appCtx = context.applicationContext
        ensureInitialized(appCtx)
        val info = _activeRingingAlarmInfoFlow.value
        val targetAlarm = if (info != null) {
            _alarmsFlow.value.find { it.id == info.first }
        } else {
            getNextActiveAlarm(appCtx) ?: _alarmsFlow.value.firstOrNull()
        }
        val alarmId = info?.first ?: targetAlarm?.id ?: "alarm_0830"
        val scheduledTime = info?.third ?: targetAlarm?.timeFormatted ?: SimpleDateFormat("HH:mm", Locale.GERMANY).format(Date())

        stopAlarmSound(appCtx)
        cancelSnoozeAlarm(appCtx, alarmId)
        if (targetAlarm != null && targetAlarm.daysOfWeek.isEmpty() && targetAlarm.isEnabled) {
            toggleAlarm(appCtx, alarmId)
        }
        val nowTimeStr = SimpleDateFormat("HH:mm", Locale.GERMANY).format(Date())
        recordWakeUpLog(
            context = appCtx,
            scheduledTime = scheduledTime,
            actualWakeTime = nowTimeStr,
            snoozeMinutes = 0,
            plugsTriggered = lastTriggerPlugsSwitched,
            aiBriefingSpoken = true
        )
        pushAlarmPackageToAllEndpointsAsync(appCtx)
        return "Der Wecker wurde für heute komplett gestoppt, es klingelt kein Schlummer-Wecker mehr und die Aufstehzeit ($nowTimeStr Uhr) wurde an /admin/routine übermittelt."
    }

    fun stopAlarmSound(context: Context? = null) {
        isAlarmDismissed = true
        _isAlarmRingingFlow.value = false
        _activeRingingAlarmInfoFlow.value = null
        _alarmDialogStateFlow.value = null
        try {
            alarmSequenceJob?.cancel()
        } catch (_: Exception) {
        }
        alarmSequenceJob = null

        stopAlarmRingtoneOnly(context)
        stopBriefingOnly()
        releaseAlarmAudioDucking(context)
        releaseAlarmWakeLock()

        if (context != null) {
            try {
                val nm = context.applicationContext.getSystemService(Context.NOTIFICATION_SERVICE) as? NotificationManager
                nm?.cancel(NOTIFICATION_ID_TIMER_FINISHED)
                val idsToCancel = synchronized(activeAlarmNotificationIds) {
                    val copy = activeAlarmNotificationIds.toSet()
                    activeAlarmNotificationIds.clear()
                    copy
                }
                idsToCancel.forEach { nm?.cancel(it) }
                _alarmsFlow.value.forEach { nm?.cancel(it.id.hashCode()) }
            } catch (_: Exception) {
            }
        }
    }

    fun formatTodayGermanDateSentence(): String {
        val formatted = SimpleDateFormat("EEEE, 'der' d. MMMM", Locale.GERMANY).format(Date())
        return "Heute ist $formatted."
    }

    /**
     * Fetches live weather for Gifhorn (52.4886° N, 10.5458° E) via Open-Meteo and builds a single concise German sentence
     * stating today's weather in Gifhorn, whether an evening walk works based on the weather, and how to dress for the evening temperature.
     * Strictly never suggests walking locations, parks, lakes, or routes.
     */
    suspend fun fetchGifhornWeatherAndWalkSentence(context: Context): String = withContext(Dispatchers.IO) {
        val appCtx = context.applicationContext
        val nowMs = System.currentTimeMillis()
        val cached = cachedGifhornWeatherSentence
        if (!cached.isNullOrBlank() && (nowMs - cachedGifhornWeatherAtMs) < 25 * 60_000L) {
            return@withContext cached
        }

        val todayIso = SimpleDateFormat("yyyy-MM-dd", Locale.US).format(Date())
        val prefs = appCtx.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)

        try {
            val urlStr = "https://api.open-meteo.com/v1/forecast?latitude=52.4886&longitude=10.5458&current=temperature_2m,weather_code&hourly=temperature_2m,precipitation_probability,weather_code,wind_speed_10m&daily=weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max&timezone=Europe%2FBerlin&forecast_days=1"
            val conn = (URL(urlStr).openConnection() as HttpURLConnection).apply {
                requestMethod = "GET"
                connectTimeout = 3500
                readTimeout = 3500
                setRequestProperty("Accept", "application/json")
            }
            if (conn.responseCode in 200..299) {
                val body = conn.inputStream.bufferedReader().use { it.readText() }
                conn.disconnect()
                val root = JSONObject(body)

                val daily = root.optJSONObject("daily")
                val dailyMax = daily?.optJSONArray("temperature_2m_max")?.optDouble(0, 12.0)?.roundToInt() ?: 12
                val dailyCode = daily?.optJSONArray("weather_code")?.optInt(0, 2) ?: 2

                val hourly = root.optJSONObject("hourly")
                val hourlyTemps = hourly?.optJSONArray("temperature_2m")
                val hourlyRain = hourly?.optJSONArray("precipitation_probability")
                val hourlyWind = hourly?.optJSONArray("wind_speed_10m")
                val hourlyCodes = hourly?.optJSONArray("weather_code")

                // Evaluate evening window between 18:00 and 20:00 for Gifhorn weather & walk feasibility
                val eveningHours = listOf(18, 19, 20)
                val eveningTempList = eveningHours.mapNotNull { h ->
                    if (hourlyTemps != null && h < hourlyTemps.length()) {
                        val v = hourlyTemps.optDouble(h, Double.NaN)
                        if (!v.isNaN()) v else null
                    } else null
                }
                val eveningTemp = if (eveningTempList.isNotEmpty()) {
                    eveningTempList.average().roundToInt()
                } else {
                    (dailyMax - 4)
                }

                var eveningRainProb = 0
                var eveningWindMax = 0.0
                var eveningHasThunderstorm = false
                var eveningHasHeavyPrecip = false
                var eveningHasLightPrecip = false
                for (h in eveningHours) {
                    if (hourlyRain != null && h < hourlyRain.length()) {
                        eveningRainProb = maxOf(eveningRainProb, hourlyRain.optInt(h, 0))
                    }
                    if (hourlyWind != null && h < hourlyWind.length()) {
                        eveningWindMax = maxOf(eveningWindMax, hourlyWind.optDouble(h, 0.0))
                    }
                    if (hourlyCodes != null && h < hourlyCodes.length()) {
                        when (hourlyCodes.optInt(h, 0)) {
                            95, 96, 99 -> eveningHasThunderstorm = true
                            65, 67, 75, 82, 86 -> eveningHasHeavyPrecip = true
                            51, 53, 55, 56, 57, 61, 63, 66, 71, 73, 77, 80, 81, 85 -> eveningHasLightPrecip = true
                        }
                    }
                }

                val weatherCondition = mapWmoCodeToGerman(dailyCode)
                val outfitRecommendation = when {
                    eveningTemp <= 2 -> "eine dicke Winterjacke, Schal und Mütze"
                    eveningTemp in 3..8 -> "eine warme Jacke und einen Pullover"
                    eveningTemp in 9..14 -> "eine Übergangsjacke oder einen warmen Hoodie"
                    eveningTemp in 15..19 -> "eine leichte Jacke oder einen dünnen Pullover"
                    else -> "leichte Kleidung"
                }

                val walkFeasibilityClause = when {
                    eveningHasThunderstorm || eveningHasHeavyPrecip || eveningRainProb >= 65 || eveningWindMax >= 42.0 -> {
                        val reason = when {
                            eveningHasThunderstorm -> "wegen Gewittergefahr"
                            eveningWindMax >= 42.0 -> "wegen starkem Wind"
                            else -> "wegen Regen"
                        }
                        "ein Spaziergang heute Abend bietet sich $reason leider eher nicht an, falls du bei rund $eveningTemp Grad doch kurz rausgehst, nimm wetterfest $outfitRecommendation"
                    }
                    eveningRainProb >= 40 || eveningHasLightPrecip || eveningWindMax >= 28.0 -> {
                        val protection = if (eveningRainProb >= 40 || eveningHasLightPrecip) "mit Schirm oder Regenjacke" else "mit etwas Windschutz"
                        "ein Spaziergang heute Abend geht vom Wetter her $protection, zieh dir bei rund $eveningTemp Grad am besten $outfitRecommendation an"
                    }
                    else -> {
                        "ein Spaziergang heute Abend geht vom Wetter her sehr gut, zieh dir bei rund $eveningTemp Grad am besten $outfitRecommendation an"
                    }
                }

                val sentence = "In Gifhorn wird es heute $weatherCondition bei bis zu $dailyMax Grad, und $walkFeasibilityClause."
                cachedGifhornWeatherSentence = sentence
                cachedGifhornWeatherAtMs = nowMs
                prefs.edit()
                    .putString(KEY_LAST_WEATHER_SENTENCE, sentence)
                    .putString(KEY_LAST_WEATHER_DATE, todayIso)
                    .apply()
                return@withContext sentence
            } else {
                conn.disconnect()
            }
        } catch (e: Exception) {
            Log.w(TAG, "Failed to fetch Gifhorn weather from Open-Meteo: ${e.message}")
        }

        val savedDate = prefs.getString(KEY_LAST_WEATHER_DATE, null)
        val savedSentence = prefs.getString(KEY_LAST_WEATHER_SENTENCE, null)
        if (savedDate == todayIso && !savedSentence.isNullOrBlank()) {
            return@withContext savedSentence
        }

        return@withContext "In Gifhorn begleitet dich heute typisches Regionswetter, ein Spaziergang heute Abend ist je nach Schauern machbar, zieh dir der Temperatur entsprechend eine passende Jacke an."
    }

    private fun mapWmoCodeToGerman(code: Int): String {
        return when (code) {
            0 -> "sonnig und klar"
            1 -> "überwiegend heiter"
            2 -> "leicht bewölkt"
            3 -> "bedeckt"
            45, 48 -> "neblig"
            51, 53, 55, 56, 57 -> "leicht regnerisch mit Nieselregen"
            61, 63, 65, 66, 67, 80, 81, 82 -> "wechselhaft mit Regenschauern"
            71, 73, 75, 77, 85, 86 -> "winterlich mit Schneeschauern"
            95, 96, 99 -> "unbeständig mit Gewitterneigung"
            else -> "wechselhaft bewölkt"
        }
    }

    /**
     * Listens for the user's spoken response after Funkira's alarm question.
     * Uses Android SpeechRecognizer on Main thread, and falls back to AudioRecord + Gemini multimodal transcription
     * if SpeechRecognizer fails or returns empty (e.g. on lockscreen).
     */
    private suspend fun listenForAlarmVoiceCommand(context: Context, timeoutMs: Long = 7500L): String {
        val appCtx = context.applicationContext
        if (ContextCompat.checkSelfPermission(appCtx, Manifest.permission.RECORD_AUDIO) != PackageManager.PERMISSION_GRANTED) {
            Log.w(TAG, "RECORD_AUDIO permission not granted for alarm voice command")
            return ""
        }

        // Wait briefly so WakeWordService releases its AudioRecord instance
        delay(250L)

        val startMs = System.currentTimeMillis()
        val speechResult = withTimeoutOrNull(timeoutMs) {
            listenViaAndroidSpeechRecognizer(appCtx)
        }?.trim().orEmpty()

        if (speechResult.isNotBlank()) {
            return speechResult
        }

        // If Android SpeechRecognizer failed immediately (e.g. < 2000ms on lockscreen), use AudioRecord + Gemini audio fallback
        val elapsed = System.currentTimeMillis() - startMs
        if (!isAlarmDismissed && elapsed < 2500L) {
            Log.d(TAG, "SpeechRecognizer returned empty quickly (${elapsed}ms), using AudioRecord + Gemini fallback")
            return recordAndTranscribeViaGemini(appCtx, maxDurationMs = 5000L)
        }
        return ""
    }

    private suspend fun listenViaAndroidSpeechRecognizer(context: Context): String = withContext(Dispatchers.Main) {
        if (!SpeechRecognizer.isRecognitionAvailable(context)) {
            return@withContext ""
        }
        suspendCancellableCoroutine { cont ->
            var recognizer: SpeechRecognizer? = null
            var lastPartial = ""
            var completed = false

            fun finish(text: String) {
                if (completed) return
                completed = true
                try {
                    recognizer?.stopListening()
                    recognizer?.destroy()
                } catch (_: Exception) {
                }
                recognizer = null
                if (cont.isActive) {
                    cont.resume(text)
                }
            }

            try {
                val sr = SpeechRecognizer.createSpeechRecognizer(context)
                recognizer = sr
                sr.setRecognitionListener(object : RecognitionListener {
                    override fun onReadyForSpeech(params: Bundle?) {}
                    override fun onBeginningOfSpeech() {}
                    override fun onRmsChanged(rmsdB: Float) {}
                    override fun onBufferReceived(buffer: ByteArray?) {}
                    override fun onEndOfSpeech() {}
                    override fun onEvent(eventType: Int, params: Bundle?) {}

                    override fun onPartialResults(partialResults: Bundle?) {
                        val matches = partialResults?.getStringArrayList(SpeechRecognizer.RESULTS_RECOGNITION)
                        val candidate = matches?.firstOrNull()?.trim().orEmpty()
                        if (candidate.isNotBlank()) {
                            lastPartial = candidate
                            _alarmDialogStateFlow.value = "🎙️ Verstanden: „$candidate“"
                        }
                    }

                    override fun onResults(results: Bundle?) {
                        val matches = results?.getStringArrayList(SpeechRecognizer.RESULTS_RECOGNITION)
                        val best = matches?.firstOrNull()?.trim().orEmpty().ifBlank { lastPartial }
                        finish(best)
                    }

                    override fun onError(error: Int) {
                        finish(lastPartial)
                    }
                })

                val intent = Intent(RecognizerIntent.ACTION_RECOGNIZE_SPEECH).apply {
                    putExtra(RecognizerIntent.EXTRA_LANGUAGE_MODEL, RecognizerIntent.LANGUAGE_MODEL_FREE_FORM)
                    putExtra(RecognizerIntent.EXTRA_LANGUAGE, "de-DE")
                    putExtra(RecognizerIntent.EXTRA_PARTIAL_RESULTS, true)
                    putExtra(RecognizerIntent.EXTRA_MAX_RESULTS, 3)
                    putExtra(RecognizerIntent.EXTRA_SPEECH_INPUT_COMPLETE_SILENCE_LENGTH_MILLIS, 1400L)
                    putExtra(RecognizerIntent.EXTRA_SPEECH_INPUT_POSSIBLY_COMPLETE_SILENCE_LENGTH_MILLIS, 1200L)
                }
                sr.startListening(intent)

                cont.invokeOnCancellation {
                    try {
                        sr.cancel()
                        sr.destroy()
                    } catch (_: Exception) {
                    }
                }
            } catch (e: Exception) {
                Log.w(TAG, "listenViaAndroidSpeechRecognizer failed: ${e.message}")
                finish(lastPartial)
            }
        }
    }

    private suspend fun recordAndTranscribeViaGemini(context: Context, maxDurationMs: Long = 5000L): String = withContext(Dispatchers.IO) {
        val sampleRate = 16000
        val channelConfig = AudioFormat.CHANNEL_IN_MONO
        val audioFormat = AudioFormat.ENCODING_PCM_16BIT
        val minBuf = AudioRecord.getMinBufferSize(sampleRate, channelConfig, audioFormat).coerceAtLeast(3200)

        var recorder: AudioRecord? = null
        val pcmOut = ByteArrayOutputStream()
        var maxRms = 0.0
        var speechDetectedAtMs = 0L
        var lastAboveThresholdAtMs = 0L

        try {
            recorder = AudioRecord(
                MediaRecorder.AudioSource.MIC,
                sampleRate,
                channelConfig,
                audioFormat,
                minBuf * 2
            )
            if (recorder.state != AudioRecord.STATE_INITIALIZED) {
                return@withContext ""
            }
            recorder.startRecording()
            val shortBuf = ShortArray(1600) // 100ms chunks at 16kHz
            val startAt = System.currentTimeMillis()

            while (!isAlarmDismissed && (System.currentTimeMillis() - startAt) < maxDurationMs) {
                val read = recorder.read(shortBuf, 0, shortBuf.size)
                if (read > 0) {
                    var sum = 0.0
                    val byteBuf = ByteBuffer.allocate(read * 2).order(ByteOrder.LITTLE_ENDIAN)
                    for (i in 0 until read) {
                        val s = shortBuf[i]
                        sum += s * s
                        byteBuf.putShort(s)
                    }
                    pcmOut.write(byteBuf.array())
                    val rms = sqrt(sum / read)
                    if (rms > maxRms) maxRms = rms
                    val now = System.currentTimeMillis()
                    if (rms > 550.0) {
                        if (speechDetectedAtMs == 0L) speechDetectedAtMs = now
                        lastAboveThresholdAtMs = now
                    } else if (speechDetectedAtMs > 0L && (now - lastAboveThresholdAtMs) > 1200L) {
                        // User finished speaking (1.2s of silence after speech)
                        break
                    }
                }
            }
        } catch (e: Exception) {
            Log.w(TAG, "AudioRecord fallback failed: ${e.message}")
            return@withContext ""
        } finally {
            try {
                recorder?.stop()
                recorder?.release()
            } catch (_: Exception) {
            }
        }

        val pcmBytes = pcmOut.toByteArray()
        if (pcmBytes.size < 6400 || maxRms < 450.0) {
            return@withContext ""
        }

        val wavBytes = wrapPcmMono16BitToWav(pcmBytes, sampleRate)
        val b64Wav = Base64.encodeToString(wavBytes, Base64.NO_WRAP)

        try {
            val appCtx = context.applicationContext
            ServiceLocator.ensureInitialized(appCtx)
            val apiKey = ServiceLocator.getGeminiApiKey().trim()
            if (apiKey.isNotBlank()) {
                val prompt = "Transkribiere exakt auf Deutsch, was die Person auf die Frage 'Soll ich den Wecker direkt verschieben?' geantwortet hat (z.B. Ja, Nein, um 15 Minuten, gar nicht klingeln). Gib ausschließlich den gehörten deutschen Text ohne Anführungszeichen zurück."
                val reqJson = JSONObject().apply {
                    put("contents", JSONArray().put(
                        JSONObject().apply {
                            put("role", "user")
                            put("parts", JSONArray().apply {
                                put(JSONObject().put("text", prompt))
                                put(JSONObject().put("inlineData", JSONObject().apply {
                                    put("mimeType", "audio/wav")
                                    put("data", b64Wav)
                                }))
                            })
                        }
                    ))
                }.toString()

                val conn = (URL("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent").openConnection() as HttpURLConnection).apply {
                    requestMethod = "POST"
                    connectTimeout = 4000
                    readTimeout = 6000
                    doOutput = true
                    setRequestProperty("Content-Type", "application/json")
                    setRequestProperty("x-goog-api-key", apiKey)
                }
                conn.outputStream.use { it.write(reqJson.toByteArray(Charsets.UTF_8)) }
                if (conn.responseCode in 200..299) {
                    val resp = conn.inputStream.bufferedReader().use { it.readText() }
                    conn.disconnect()
                    val text = JSONObject(resp)
                        .optJSONArray("candidates")
                        ?.optJSONObject(0)
                        ?.optJSONObject("content")
                        ?.optJSONArray("parts")
                        ?.optJSONObject(0)
                        ?.optString("text", "")
                        ?.trim()
                        .orEmpty()
                    if (text.isNotBlank()) {
                        _alarmDialogStateFlow.value = "🎙️ Verstanden: „$text“"
                        return@withContext text
                    }
                } else {
                    conn.disconnect()
                }
            }

            // Authenticated Server-Side Fallback (keeps Master GEMINI_API_KEY strictly on server)
            val token = ServiceLocator.getAuthToken()
            if (!token.isNullOrBlank()) {
                val payload = JSONObject().apply {
                    put("audio_base64", b64Wav)
                    put("mime_type", "audio/wav")
                }.toString()
                for (base in apiBaseCandidates(appCtx)) {
                    try {
                        val conn = (URL("$base/funki/routine/alarm-transcribe").openConnection() as HttpURLConnection).apply {
                            requestMethod = "POST"
                            connectTimeout = 3500
                            readTimeout = 7500
                            doOutput = true
                            setRequestProperty("Content-Type", "application/json")
                            setRequestProperty("Accept", "application/json")
                            setRequestProperty("Authorization", "Bearer $token")
                        }
                        conn.outputStream.use { it.write(payload.toByteArray(Charsets.UTF_8)) }
                        if (conn.responseCode in 200..299) {
                            val resp = conn.inputStream.bufferedReader().use { it.readText() }
                            conn.disconnect()
                            val text = JSONObject(resp).optString("transcript", "").trim()
                            if (text.isNotBlank()) {
                                _alarmDialogStateFlow.value = "🎙️ Verstanden: „$text“"
                                return@withContext text
                            }
                        } else {
                            conn.disconnect()
                        }
                    } catch (_: Exception) {
                    }
                }
            }
        } catch (e: Exception) {
            Log.w(TAG, "Alarm audio transcription failed: ${e.message}")
        }
        return@withContext ""
    }

    fun parseAlarmVoiceCommand(rawTranscript: String, defaultNumberAsMinutes: Boolean = false): AlarmVoiceIntent {
        val t = rawTranscript.lowercase(Locale.GERMANY).trim()
        if (t.isBlank()) return AlarmVoiceIntent.Unknown

        // 1. Check "gar nicht klingeln" / disable for today
        if (t.contains("gar nicht") ||
            t.contains("nicht mehr klingeln") ||
            t.contains("nicht klingeln") ||
            t.contains("überhaupt nicht") ||
            t.contains("heute nicht") ||
            t.contains("ausschalten") ||
            t.contains("ausmachen") ||
            t.contains("auslassen") ||
            t.contains("komplett aus") ||
            t.contains("wecker aus") ||
            t == "aus"
        ) {
            return AlarmVoiceIntent.DisableToday
        }

        // 2. Check if specific minutes or hours were mentioned (e.g. "Ja, 15 Minuten später", "um 20 Minuten", "halbe Stunde")
        val extractedMinutes = extractMinutesFromGermanText(t, defaultNumberAsMinutes)
        if (extractedMinutes != null && extractedMinutes in 1..360) {
            return AlarmVoiceIntent.ShiftMinutes(extractedMinutes)
        }

        // 3. Check "Nein" -> do not shift, continue with morning routine
        if (t.contains("nein") ||
            t.contains("nee") ||
            t == "ne" ||
            t.startsWith("ne ") ||
            t.contains("nicht verschieben") ||
            t.contains("stehe auf") ||
            t.contains("steh auf") ||
            t.contains("aufstehen") ||
            t.contains("bin wach") ||
            t.contains("schon wach") ||
            t.contains("routine") ||
            t.contains("weiter")
        ) {
            return AlarmVoiceIntent.No
        }

        // 4. Check "Ja" without duration -> ask how many minutes or disable
        if (t.contains("ja") ||
            t.contains("jap") ||
            t.contains("jepp") ||
            t.contains("gerne") ||
            t.contains("bitte") ||
            t.contains("verschieb") ||
            t.contains("später") ||
            t.contains("schlummern") ||
            t.contains("snooze")
        ) {
            return AlarmVoiceIntent.YesAskDuration
        }

        return AlarmVoiceIntent.Unknown
    }

    private fun extractMinutesFromGermanText(t: String, defaultNumberAsMinutes: Boolean): Int? {
        if (t.contains("anderthalb stunde") || t.contains("eineinhalb stunde")) return 90
        if (t.contains("dreiviertelstunde") || t.contains("dreiviertel stunde")) return 45
        if (t.contains("halbe stunde") || t.contains("halben stunde")) return 30
        if (t.contains("viertelstunde") || t.contains("viertel stunde")) return 15
        if (t.contains("zwei stunden")) return 120
        if (t.contains("eine stunde") || t.contains("einer stunde")) return 60

        val digitMatch = Regex("""(\d{1,3})\s*(minuten|minute|min|stunden|stunde|std)?""").find(t)
        if (digitMatch != null) {
            val num = digitMatch.groupValues[1].toIntOrNull()
            val unit = digitMatch.groupValues.getOrNull(2).orEmpty()
            if (num != null && num > 0) {
                if (unit.startsWith("st")) {
                    return (num * 60).coerceIn(1, 360)
                }
                if (unit.startsWith("min") ||
                    defaultNumberAsMinutes ||
                    t.contains("ja") ||
                    t.contains("später") ||
                    t.contains("verschieb") ||
                    t.contains("um ") ||
                    t.contains("in ") ||
                    t.trim().all { it.isDigit() }
                ) {
                    return num.coerceIn(1, 360)
                }
            }
        }

        val wordNumbers = listOf(
            "neunzig" to 90,
            "sechzig" to 60,
            "fünfundvierzig" to 45,
            "vierzig" to 40,
            "fünfunddreißig" to 35,
            "dreißig" to 30,
            "dreissig" to 30,
            "fünfundzwanzig" to 25,
            "zwanzig" to 20,
            "fünfzehn" to 15,
            "zehn" to 10,
            "neun" to 9,
            "acht" to 8,
            "sieben" to 7,
            "sechs" to 6,
            "fünf" to 5,
            "vier" to 4,
            "drei" to 3,
            "zwei" to 2,
            "eine minute" to 1,
            "eins" to 1
        )
        for ((word, mins) in wordNumbers) {
            if (Regex("""\b$word\b""").containsMatchIn(t)) {
                return mins
            }
        }
        return null
    }

    fun buildMorningBriefingText(
        context: Context,
        alarmLabel: String = "Seelenfunke Fokus-Start",
        plugsSwitched: Boolean = false,
        overrideTimeStr: String? = null,
        isContinuationAfterNo: Boolean = false,
        preloadedWeatherSentence: String? = null
    ): String {
        val appCtx = context.applicationContext
        val timeStr = overrideTimeStr?.take(5)?.takeIf { it.isNotBlank() }
            ?: SimpleDateFormat("HH:mm", Locale.GERMANY).format(Date())
        val hourInt = timeStr.substringBefore(":").toIntOrNull() ?: 8
        val greeting = when (hourInt) {
            in 4..10 -> "Guten Morgen Alina!"
            in 11..17 -> "Hallo Alina!"
            else -> "Guten Abend Alina!"
        }

        val todayDateSentence = formatTodayGermanDateSentence()
        val todayIso = SimpleDateFormat("yyyy-MM-dd", Locale.US).format(Date())
        val prefs = appCtx.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        val weatherSentence = preloadedWeatherSentence?.takeIf { it.isNotBlank() }
            ?: cachedGifhornWeatherSentence?.takeIf { it.isNotBlank() }
            ?: prefs.getString(KEY_LAST_WEATHER_SENTENCE, null)?.takeIf {
                prefs.getString(KEY_LAST_WEATHER_DATE, null) == todayIso && it.isNotBlank()
            }
            ?: "In Gifhorn begleitet dich heute typisches Regionswetter, zieh dir heute Abend für den Spaziergang der Temperatur entsprechend eine bequeme Jacke an."

        // Read cached tasks & calendar events for personalized briefing
        var openTasksCount = 0
        var highPrioTasksCount = 0
        try {
            val taskPrefs = appCtx.getSharedPreferences("tasks_widget_prefs", Context.MODE_PRIVATE)
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
            val calPrefs = appCtx.getSharedPreferences("calendar_widget_prefs", Context.MODE_PRIVATE)
            val calJson = calPrefs.getString("calendar_events_cache", null)
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
        if (isContinuationAfterNo) {
            sb.append("Alles klar, dann starten wir um $timeStr Uhr direkt in deine Morgenroutine! ")
        } else {
            sb.append("$greeting Es ist $timeStr Uhr. ")
            if (alarmLabel.isNotBlank()) {
                sb.append("Dein Wecker $alarmLabel ist aktiv. ")
            }
        }

        // 1. Current day / date + 2. Gifhorn weather & evening walk outfit in one concise sentence
        sb.append("$todayDateSentence ")
        sb.append("$weatherSentence ")

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

        sb.append("Deine Tagesroutine richtet sich jetzt nach $timeStr Uhr aus. Wenn sonst nichts weiter ist, geht deine Routine jetzt weiter und ich wünsche dir einen wundervollen Tag, bis später!")
        return sb.toString()
    }

    /**
     * Pre-synthesizes Funkira's (Aoede) Morning Dialog & Briefing WAV files in the background
     * so that when the alarm rings, Funkira's voice is immediately ready on disk with 0ms network latency.
     */
    fun prewarmAlarmBriefingsAsync(context: Context) {
        val appCtx = context.applicationContext
        scope.launch(Dispatchers.IO) {
            try {
                // Clean up any legacy un-hashed briefing_<alarmId>.wav files so stale times can never be played
                val briefingsDir = File(appCtx.filesDir, "funkira_briefings")
                briefingsDir.listFiles { _, name -> name.startsWith("briefing_") && name.endsWith(".wav") }
                    ?.forEach { try { it.delete() } catch (_: Exception) {} }

                // 1. Pre-fetch Gifhorn weather so it's warm in cache
                val weatherSentence = fetchGifhornWeatherAndWalkSentence(appCtx)

                // 2. Pre-warm interactive alarm dialog questions
                fetchOrSynthesizeFunkiraWav(appCtx, INITIAL_ALARM_QUESTION, "alarm_ask_shift")
                fetchOrSynthesizeFunkiraWav(appCtx, FOLLOWUP_DURATION_QUESTION, "alarm_ask_duration")
                fetchOrSynthesizeFunkiraWav(appCtx, DISABLE_TODAY_CONFIRMATION, "alarm_disable_today")

                // 3. Pre-warm full morning briefings for enabled alarms
                val enabledBriefingAlarms = _alarmsFlow.value.filter { it.isEnabled && it.aiBriefingEnabled }
                for (alarm in enabledBriefingAlarms) {
                    val plugsSwitched = alarm.triggerPlugOnRing && alarm.smartPlugIps.isNotEmpty()
                    val textCont = buildMorningBriefingText(
                        context = appCtx,
                        alarmLabel = alarm.label,
                        plugsSwitched = plugsSwitched,
                        overrideTimeStr = alarm.timeFormatted,
                        isContinuationAfterNo = true,
                        preloadedWeatherSentence = weatherSentence
                    )
                    fetchOrSynthesizeFunkiraWav(appCtx, textCont, "${alarm.id}_cont")

                    val textFull = buildMorningBriefingText(
                        context = appCtx,
                        alarmLabel = alarm.label,
                        plugsSwitched = plugsSwitched,
                        overrideTimeStr = alarm.timeFormatted,
                        isContinuationAfterNo = false,
                        preloadedWeatherSentence = weatherSentence
                    )
                    fetchOrSynthesizeFunkiraWav(appCtx, textFull, alarm.id)
                }
            } catch (e: Exception) {
                Log.w(TAG, "prewarmAlarmBriefingsAsync failed: ${e.message}")
            }
        }
    }

    fun speakMorningBriefing(
        context: Context,
        alarmLabel: String = "Seelenfunke Fokus-Start",
        plugsSwitched: Boolean = false,
        overrideTimeStr: String? = null
    ) {
        val appCtx = context.applicationContext
        // Always stop any active alarm tone first so alarm sound and Funkira's voice never overlap
        stopAlarmSound(appCtx)
        isAlarmDismissed = false
        acquireAlarmWakeLock(appCtx, 90_000L)
        ensureAlarmStreamVolume(appCtx)
        requestAlarmAudioDucking(appCtx)

        alarmSequenceJob = scope.launch {
            try {
                val weatherSentence = withTimeoutOrNull(3800L) { fetchGifhornWeatherAndWalkSentence(appCtx) }
                val text = buildMorningBriefingText(
                    context = appCtx,
                    alarmLabel = alarmLabel,
                    plugsSwitched = plugsSwitched,
                    overrideTimeStr = overrideTimeStr,
                    isContinuationAfterNo = false,
                    preloadedWeatherSentence = weatherSentence
                )
                val wavFile = fetchOrSynthesizeFunkiraWav(appCtx, text, "preview")
                if (!isAlarmDismissed) {
                    playBriefingAndAwaitCompletion(appCtx, wavFile, text)
                }
            } finally {
                if (activeAlarmPlayer == null && activeRingtone == null) {
                    releaseAlarmAudioDucking(appCtx)
                    releaseAlarmWakeLock()
                }
            }
        }
    }

    private suspend fun playBriefingAndAwaitCompletion(context: Context, wavFile: File?, fallbackText: String) {
        val appCtx = context.applicationContext
        ensureAlarmStreamVolume(appCtx)

        if (wavFile != null && wavFile.exists() && wavFile.length() > 200L) {
            val playedOk = suspendCancellableCoroutine<Boolean> { cont ->
                try {
                    stopBriefingOnly()
                    val speechAttrs = AudioAttributes.Builder()
                        .setUsage(AudioAttributes.USAGE_ALARM)
                        .setContentType(AudioAttributes.CONTENT_TYPE_SPEECH)
                        .build()
                    val mp = MediaPlayer()
                    mp.setAudioAttributes(speechAttrs)
                    mp.setDataSource(wavFile.absolutePath)
                    mp.setVolume(1.0f, 1.0f)
                    mp.setOnCompletionListener {
                        try {
                            it.release()
                        } catch (_: Exception) {
                        }
                        if (activeBriefingPlayer === it) activeBriefingPlayer = null
                        if (cont.isActive) cont.resume(true)
                    }
                    mp.setOnErrorListener { player, _, _ ->
                        try {
                            player.release()
                        } catch (_: Exception) {
                        }
                        if (activeBriefingPlayer === player) activeBriefingPlayer = null
                        if (cont.isActive) cont.resume(false)
                        true
                    }
                    mp.prepare()
                    activeBriefingPlayer = mp
                    mp.start()
                    cont.invokeOnCancellation {
                        try {
                            mp.stop()
                        } catch (_: Exception) {
                        }
                        try {
                            mp.release()
                        } catch (_: Exception) {
                        }
                    }
                } catch (e: Exception) {
                    Log.e(TAG, "Failed to play Funkira WAV file", e)
                    if (cont.isActive) cont.resume(false)
                }
            }
            if (playedOk) return
        }

        // Offline fallback only if neither Gemini API nor Seelenfunke backend could be reached
        suspendCancellableCoroutine<Unit> { cont ->
            try {
                stopBriefingOnly()
                val speechAttrs = AudioAttributes.Builder()
                    .setUsage(AudioAttributes.USAGE_ALARM)
                    .setContentType(AudioAttributes.CONTENT_TYPE_SPEECH)
                    .build()
                var ttsInstance: TextToSpeech? = null
                ttsInstance = TextToSpeech(appCtx) { status ->
                    if (status == TextToSpeech.SUCCESS && !isAlarmDismissed) {
                        ttsInstance?.language = Locale.GERMANY
                        ttsInstance?.setAudioAttributes(speechAttrs)
                        ttsInstance?.setSpeechRate(1.0f)
                        ttsInstance?.setOnUtteranceProgressListener(object : UtteranceProgressListener() {
                            override fun onStart(utteranceId: String?) {}
                            override fun onDone(utteranceId: String?) {
                                if (cont.isActive) cont.resume(Unit)
                            }
                            @Deprecated("Deprecated in Java")
                            override fun onError(utteranceId: String?) {
                                if (cont.isActive) cont.resume(Unit)
                            }
                        })
                        val params = Bundle().apply {
                            putInt(TextToSpeech.Engine.KEY_PARAM_STREAM, AudioManager.STREAM_ALARM)
                        }
                        ttsInstance?.speak(fallbackText, TextToSpeech.QUEUE_FLUSH, params, "funkenwerk_morning_briefing")
                    } else {
                        if (cont.isActive) cont.resume(Unit)
                    }
                }
                activeTts = ttsInstance
            } catch (_: Exception) {
                if (cont.isActive) cont.resume(Unit)
            }
        }
    }

    private fun apiBaseCandidates(context: Context): List<String> {
        val list = mutableListOf<String>()
        try {
            ServiceLocator.ensureInitialized(context.applicationContext)
            val saved = ServiceLocator.getBaseUrl().trim().removeSuffix("/")
            if (saved.isNotBlank()) list.add(saved)
        } catch (_: Exception) {
        }
        // Never blindly broadcast Bearer tokens over cleartext LAN HTTP unless the user explicitly selected LAN mode
        list.add("https://stage.mein-seelenfunke.de/api")
        list.add("https://mein-seelenfunke.de/api")
        return list.distinct()
    }

    /**
     * Synthesizes Funkira's real voice ("Aoede") as a 24kHz WAV file:
     * 1. Checks persistent filesDir/funkira_briefings by exact text SHA-256 hash (instant 0ms playback when pre-warmed,
     *    and guaranteed never to play a stale time when an alarm's hour/minute is changed!).
     * 2. Uses direct Gemini Native Audio API (Aoede) if Gemini key is stored in ServiceLocator.
     * 3. Falls back to Seelenfunke Backend (/api/funki/routine/morning-briefing-tts) across LAN/Stage/Live.
     */
    private suspend fun fetchOrSynthesizeFunkiraWav(context: Context, text: String, alarmId: String? = null): File? = withContext(Dispatchers.IO) {
        val appCtx = context.applicationContext
        val briefingsDir = File(appCtx.filesDir, "funkira_briefings").apply { if (!exists()) mkdirs() }
        val hash = sha256(text.toByteArray(Charsets.UTF_8)).joinToString("") { "%02x".format(it) }.take(20)
        val cacheFile = File(briefingsDir, "funkira_briefing_$hash.wav")

        if (cacheFile.exists() && cacheFile.length() > 1000L && (System.currentTimeMillis() - cacheFile.lastModified()) < 43_200_000L) {
            return@withContext cacheFile
        }

        // 1. Direct Gemini TTS API with Funkira's voice ("Aoede") if API key is available on device
        try {
            ServiceLocator.init(appCtx)
            val apiKey = ServiceLocator.getGeminiApiKey().trim()
            if (apiKey.isNotBlank()) {
                val wavBytes = synthesizeGeminiAoedeDirect(apiKey, text)
                if (wavBytes != null && wavBytes.size > 1000) {
                    FileOutputStream(cacheFile).use { it.write(wavBytes) }
                    return@withContext cacheFile
                }
            }
        } catch (e: Exception) {
            Log.w(TAG, "Direct Gemini Aoede TTS failed, trying backend", e)
        }

        // 2. Backend /funki/routine/morning-briefing-tts across LAN / Stage / Live
        val token = try {
            ServiceLocator.init(appCtx)
            ServiceLocator.getAuthToken()
        } catch (_: Exception) {
            null
        }

        for (base in apiBaseCandidates(appCtx)) {
            try {
                val isLan = base.contains("192.168.") || base.contains("10.0.")
                val conn = (URL("$base/funki/routine/morning-briefing-tts").openConnection() as HttpURLConnection).apply {
                    requestMethod = "POST"
                    connectTimeout = if (isLan) 2200 else 5000
                    readTimeout = 14000
                    doOutput = true
                    setRequestProperty("Content-Type", "application/json")
                    setRequestProperty("Accept", "application/json")
                    if (!token.isNullOrBlank()) {
                        setRequestProperty("Authorization", "Bearer $token")
                    }
                }
                val payload = JSONObject().apply {
                    put("text", text)
                    put("voice", "Aoede")
                }.toString()
                conn.outputStream.use { it.write(payload.toByteArray(Charsets.UTF_8)) }

                if (conn.responseCode in 200..299) {
                    val bodyStr = conn.inputStream.bufferedReader().use { it.readText() }
                    conn.disconnect()
                    val json = JSONObject(bodyStr)
                    val b64 = json.optString("audio_base64", "")
                    if (b64.isNotBlank()) {
                        val wavBytes = Base64.decode(b64, Base64.DEFAULT)
                        if (wavBytes.size > 1000) {
                            FileOutputStream(cacheFile).use { it.write(wavBytes) }
                            return@withContext cacheFile
                        }
                    }
                } else {
                    conn.disconnect()
                }
            } catch (e: Exception) {
                Log.w(TAG, "Backend morning-briefing-tts failed for $base: ${e.message}")
            }
        }

        return@withContext null
    }

    private fun synthesizeGeminiAoedeDirect(apiKey: String, text: String): ByteArray? {
        val models = listOf("gemini-3.1-flash-tts-preview", "gemini-2.5-flash-preview-tts")
        val promptText = "Lies ausschließlich den folgenden Text Wort für Wort freundlich und natürlich vor, ohne eigene Sätze oder Ortsangaben zu ergänzen: $text"

        val reqJson = JSONObject().apply {
            put("contents", JSONArray().put(
                JSONObject().apply {
                    put("role", "user")
                    put("parts", JSONArray().put(JSONObject().put("text", promptText)))
                }
            ))
            put("generationConfig", JSONObject().apply {
                put("responseModalities", JSONArray().put("AUDIO"))
                put("speechConfig", JSONObject().apply {
                    put("voiceConfig", JSONObject().apply {
                        put("prebuiltVoiceConfig", JSONObject().apply {
                            put("voiceName", "Aoede")
                        })
                    })
                })
            })
        }.toString()

        for (model in models) {
            try {
                val url = URL("https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent")
                val conn = (url.openConnection() as HttpURLConnection).apply {
                    requestMethod = "POST"
                    connectTimeout = 5000
                    readTimeout = 14000
                    doOutput = true
                    setRequestProperty("Content-Type", "application/json")
                    setRequestProperty("x-goog-api-key", apiKey)
                }
                conn.outputStream.use { it.write(reqJson.toByteArray(Charsets.UTF_8)) }
                if (conn.responseCode in 200..299) {
                    val respStr = conn.inputStream.bufferedReader().use { it.readText() }
                    conn.disconnect()
                    val root = JSONObject(respStr)
                    val b64Pcm = root.optJSONArray("candidates")
                        ?.optJSONObject(0)
                        ?.optJSONObject("content")
                        ?.optJSONArray("parts")
                        ?.optJSONObject(0)
                        ?.optJSONObject("inlineData")
                        ?.optString("data", "") ?: ""
                    if (b64Pcm.isNotBlank()) {
                        val pcmBytes = Base64.decode(b64Pcm, Base64.DEFAULT)
                        return wrapPcm24kMono16BitToWav(pcmBytes)
                    }
                } else {
                    conn.disconnect()
                }
            } catch (e: Exception) {
                Log.w(TAG, "Direct Gemini model $model failed: ${e.message}")
            }
        }
        return null
    }

    private fun wrapPcm24kMono16BitToWav(pcmData: ByteArray): ByteArray {
        return wrapPcmMono16BitToWav(pcmData, 24000)
    }

    private fun wrapPcmMono16BitToWav(pcmData: ByteArray, sampleRate: Int): ByteArray {
        val numChannels = 1
        val bitsPerSample = 16
        val byteRate = sampleRate * numChannels * (bitsPerSample / 8)
        val blockAlign = numChannels * (bitsPerSample / 8)
        val subchunk2Size = pcmData.size
        val chunkSize = 36 + subchunk2Size

        val header = ByteBuffer.allocate(44).order(ByteOrder.LITTLE_ENDIAN).apply {
            put("RIFF".toByteArray(Charsets.US_ASCII))
            putInt(chunkSize)
            put("WAVE".toByteArray(Charsets.US_ASCII))
            put("fmt ".toByteArray(Charsets.US_ASCII))
            putInt(16) // Subchunk1Size for PCM
            putShort(1.toShort()) // AudioFormat 1 = PCM
            putShort(numChannels.toShort())
            putInt(sampleRate)
            putInt(byteRate)
            putShort(blockAlign.toShort())
            putShort(bitsPerSample.toShort())
            put("data".toByteArray(Charsets.US_ASCII))
            putInt(subchunk2Size)
        }.array()

        return combine(header, pcmData)
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
        val appCtx = context.applicationContext
        val (email, pass) = getTapoCredentials(appCtx)
        val normalizedIps = ips.map { ip ->
            val clean = ip.trim()
            if (clean == "192.168.188.106") "192.168.188.110" else clean
        }.distinct()
        val plugNamesByIp = _smartPlugsFlow.value.associate { it.ip to it.name }

        scope.launch {
            acquireAlarmWakeLock(appCtx, 30_000L)
            val results = mutableListOf<String>()
            var anySuccess = false
            for (ip in normalizedIps) {
                val (ok, msg) = setPlugStateKlapV2(ip, email, pass, turnOn)
                if (ok) anySuccess = true
                val label = plugNamesByIp[ip] ?: ip
                results.add("$label: $msg")
            }
            val summary = results.joinToString(" · ")
            withContext(Dispatchers.Main) {
                onResult?.invoke(anySuccess, summary)
            }
        }
    }

    suspend fun setPlugStateKlapV2(ip: String, email: String, password: String, turnOn: Boolean): Pair<Boolean, String> =
        withContext(Dispatchers.IO) {
            val cleanIp = ip.trim()
            if (!isValidPrivateLanIpv4(cleanIp)) {
                return@withContext Pair(false, "Nur gültige lokale Heimnetz-IPv4-Adressen erlaubt.")
            }

            val effEmail = email.trim()
            val effPass = password
            if (effEmail.isBlank() || effPass.isBlank()) {
                return@withContext Pair(false, "Bitte Tapo-Zugangsdaten unter Smart-Home & KI hinterlegen.")
            }

            try {
                val localSeed = ByteArray(16)
                SecureRandom().nextBytes(localSeed)

                // 1. Handshake 1 (explicitly capture TP_SESSIONID cookie per plug IP so multi-plug calls never collide)
                val (hs1Bytes, sessionCookie) = postRawBytesWithCookie("http://$cleanIp/app/handshake1", localSeed, "application/octet-stream", null)
                if (hs1Bytes == null) {
                    return@withContext Pair(false, "Handshake1 fehlgeschlagen")
                }
                if (hs1Bytes.size < 48) {
                    return@withContext Pair(false, "Ungültige KLAP v2 Antwort")
                }

                val remoteSeed = hs1Bytes.copyOfRange(0, 16)
                val serverHash = hs1Bytes.copyOfRange(16, 48)

                val userSha1 = sha1(effEmail.toByteArray(Charsets.UTF_8))
                val passSha1 = sha1(effPass.toByteArray(Charsets.UTF_8))
                val authHash = sha256(combine(userSha1, passSha1))

                val expectedServerHash = sha256(combine(localSeed, remoteSeed, authHash))
                if (!MessageDigest.isEqual(expectedServerHash, serverHash)) {
                    return@withContext Pair(false, "Tapo Zugangsdaten stimmen nicht überein")
                }

                // 2. Handshake 2
                val clientHash = sha256(combine(remoteSeed, localSeed, authHash))
                val (hs2Bytes, hs2Cookie) = postRawBytesWithCookie("http://$cleanIp/app/handshake2", clientHash, "application/octet-stream", sessionCookie)
                if (hs2Bytes == null) {
                    return@withContext Pair(false, "Handshake2 fehlgeschlagen")
                }
                val activeCookie = hs2Cookie ?: sessionCookie

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

                val (resp, _) = postRawBytesWithCookie("http://$cleanIp/app/request?seq=$seq", reqBody, "application/octet-stream", activeCookie)
                if (resp != null) {
                    Pair(true, if (turnOn) "EIN geschaltet" else "AUS geschaltet")
                } else {
                    Pair(false, "Befehl fehlgeschlagen")
                }
            } catch (e: Exception) {
                Pair(false, "Fehler: ${e.localizedMessage ?: e.javaClass.simpleName}")
            }
        }

    private fun postRawBytesWithCookie(urlStr: String, body: ByteArray, contentType: String, cookie: String?): Pair<ByteArray?, String?> {
        val conn = (URL(urlStr).openConnection() as HttpURLConnection).apply {
            requestMethod = "POST"
            connectTimeout = 2800
            readTimeout = 2800
            doOutput = true
            useCaches = false
            setRequestProperty("Content-Type", contentType)
            if (!cookie.isNullOrBlank()) {
                setRequestProperty("Cookie", cookie)
            }
        }
        conn.outputStream.use { it.write(body) }
        val code = conn.responseCode
        val setCookieHeader = conn.headerFields["Set-Cookie"]
            ?.firstOrNull()
            ?.substringBefore(";")
            ?.trim()
        if (code !in 200..299) {
            conn.disconnect()
            return Pair(null, setCookieHeader)
        }
        val bytes = conn.inputStream.use { it.readBytes() }
        conn.disconnect()
        return Pair(bytes, setCookieHeader)
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
