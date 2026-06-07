package de.meinseelenfunke.app.util

import android.app.AlarmManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.os.Build
import android.util.Log
import com.google.gson.Gson
import com.google.gson.reflect.TypeToken
import de.meinseelenfunke.app.data.api.CalendarEvent
import de.meinseelenfunke.app.receiver.CalendarAlarmReceiver
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale
import java.util.TimeZone

object CalendarAlarmScheduler {
    private const val TAG = "CalendarAlarmScheduler"
    private const val PREFS_NAME = "calendar_widget_prefs"
    private const val CACHE_KEY = "calendar_events_cache"

    private fun parseEventDate(dateStr: String): Date? {
        if (dateStr.isBlank()) return null
        try {
            if (dateStr.contains("T")) {
                return try {
                    val odt = java.time.OffsetDateTime.parse(dateStr)
                    Date(odt.toInstant().toEpochMilli())
                } catch (e: Exception) {
                    try {
                        val ldt = java.time.LocalDateTime.parse(dateStr)
                        val zdt = ldt.atZone(java.time.ZoneId.systemDefault())
                        Date(zdt.toInstant().toEpochMilli())
                    } catch (e2: Exception) {
                        val sdf = SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss", Locale.US).apply {
                            timeZone = TimeZone.getTimeZone("UTC")
                        }
                        sdf.parse(dateStr)
                    }
                }
            } else {
                return SimpleDateFormat("yyyy-MM-dd", Locale.US).parse(dateStr)
            }
        } catch (e: Exception) {
            Log.e(TAG, "Failed to parse date string: $dateStr", e)
            return null
        }
    }

    fun scheduleAlarmsForEvents(context: Context, events: List<CalendarEvent>) {
        val alarmManager = context.getSystemService(Context.ALARM_SERVICE) as? AlarmManager ?: return
        val now = Date()

        events.forEach { event ->
            try {
                val eventDate = parseEventDate(event.start) ?: return@forEach

                // Calculate reminder offset
                val reminderMinutes = event.reminder_minutes ?: 0
                val alarmTimeMillis = eventDate.time - (reminderMinutes.toLong() * 60 * 1000)

                // Only schedule if the alarm time is in the future
                if (alarmTimeMillis > now.time) {
                    val intent = Intent(context, CalendarAlarmReceiver::class.java).apply {
                        putExtra("event_id", event.id)
                        putExtra("event_title", event.title)
                        putExtra("event_desc", event.description ?: "")
                        
                        val localSdf = SimpleDateFormat("HH:mm", Locale.GERMANY)
                        putExtra("event_time", localSdf.format(eventDate))
                    }

                    val pendingIntent = PendingIntent.getBroadcast(
                        context,
                        event.id.hashCode(),
                        intent,
                        PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
                    )

                    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
                        alarmManager.setExactAndAllowWhileIdle(
                            AlarmManager.RTC_WAKEUP,
                            alarmTimeMillis,
                            pendingIntent
                        )
                    } else {
                        alarmManager.setExact(
                            AlarmManager.RTC_WAKEUP,
                            alarmTimeMillis,
                            pendingIntent
                        )
                    }
                    Log.d(TAG, "Scheduled alarm for event: ${event.title} at ${Date(alarmTimeMillis)} (event starts at $eventDate)")
                }
            } catch (e: Exception) {
                Log.e(TAG, "Failed to schedule alarm for event: ${event.title}", e)
            }
        }
    }

    fun rescheduleAlarmsFromCache(context: Context) {
        val sharedPrefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        val json = sharedPrefs.getString(CACHE_KEY, null) ?: return
        try {
            val type = object : TypeToken<List<CalendarEvent>>() {}.type
            val events: List<CalendarEvent> = Gson().fromJson(json, type)
            scheduleAlarmsForEvents(context, events)
        } catch (e: Exception) {
            Log.e(TAG, "Failed to reschedule alarms from cache", e)
        }
    }
}
