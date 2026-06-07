package de.meinseelenfunke.app.widget

import android.app.PendingIntent
import android.appwidget.AppWidgetManager
import android.appwidget.AppWidgetProvider
import android.content.ComponentName
import android.content.Context
import android.content.Intent
import android.net.Uri
import android.util.Log
import android.view.View
import android.widget.RemoteViews
import com.google.gson.Gson
import com.google.gson.reflect.TypeToken
import de.meinseelenfunke.app.MainActivity
import de.meinseelenfunke.app.R
import de.meinseelenfunke.app.data.api.CalendarEvent
import java.text.SimpleDateFormat
import java.util.*
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch

class CalendarMonthWidgetProvider : AppWidgetProvider() {

    override fun onUpdate(context: Context, appWidgetManager: AppWidgetManager, appWidgetIds: IntArray) {
        for (appWidgetId in appWidgetIds) {
            updateAppWidget(context, appWidgetManager, appWidgetId)
        }
        super.onUpdate(context, appWidgetManager, appWidgetIds)
    }

    override fun onReceive(context: Context, intent: Intent) {
        super.onReceive(context, intent)
        val sharedPrefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        var offset = sharedPrefs.getInt(KEY_MONTH_OFFSET, 0)

        when (intent.action) {
            ACTION_PREV_MONTH -> {
                sharedPrefs.edit().putInt(KEY_MONTH_OFFSET, offset - 1).apply()
                triggerUpdate(context)
            }
            ACTION_NEXT_MONTH -> {
                sharedPrefs.edit().putInt(KEY_MONTH_OFFSET, offset + 1).apply()
                triggerUpdate(context)
            }
            ACTION_RESET_MONTH -> {
                sharedPrefs.edit().putInt(KEY_MONTH_OFFSET, 0).apply()
                triggerUpdate(context)
            }
            ACTION_SELECT_DAY -> {
                val selectedDay = intent.getStringExtra("selected_date")
                if (selectedDay != null) {
                    sharedPrefs.edit()
                        .putString(KEY_VIEW_MODE, "month")
                        .putString(KEY_SELECTED_DAY, selectedDay)
                        .apply()
                    triggerUpdate(context)
                }
            }
            ACTION_SHOW_MONTH -> {
                sharedPrefs.edit()
                    .putString(KEY_VIEW_MODE, "month")
                    .apply()
                triggerUpdate(context)
            }

            AppWidgetManager.ACTION_APPWIDGET_UPDATE -> {
                triggerUpdate(context)
            }
        }
    }

    private fun triggerUpdate(context: Context) {
        val appWidgetManager = AppWidgetManager.getInstance(context)
        val componentName = ComponentName(context, CalendarMonthWidgetProvider::class.java)
        val appWidgetIds = appWidgetManager.getAppWidgetIds(componentName)
        for (appWidgetId in appWidgetIds) {
            updateAppWidget(context, appWidgetManager, appWidgetId)
        }
    }

    companion object {
        const val ACTION_PREV_MONTH = "de.meinseelenfunke.app.widget.ACTION_PREV_MONTH"
        const val ACTION_NEXT_MONTH = "de.meinseelenfunke.app.widget.ACTION_NEXT_MONTH"
        const val ACTION_RESET_MONTH = "de.meinseelenfunke.app.widget.ACTION_RESET_MONTH"
        const val ACTION_SELECT_DAY = "de.meinseelenfunke.app.widget.ACTION_SELECT_DAY"
        const val ACTION_SHOW_MONTH = "de.meinseelenfunke.app.widget.ACTION_SHOW_MONTH"

        private const val PREFS_NAME = "calendar_widget_prefs"
        private const val KEY_MONTH_OFFSET = "calendar_month_offset"
        private const val CACHE_KEY = "calendar_events_cache"
        private const val KEY_VIEW_MODE = "widget_view_mode"
        private const val KEY_SELECTED_DAY = "widget_selected_day"

        private val monthNames = arrayOf(
            "Januar", "Februar", "März", "April", "Mai", "Juni",
            "Juli", "August", "September", "Oktober", "November", "Dezember"
        )

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
                return null
            }
        }

        private fun getEventsForDay(date: Date, events: List<CalendarEvent>): List<CalendarEvent> {
            val cal = Calendar.getInstance().apply { time = date }
            val year = cal.get(Calendar.YEAR)
            val month = cal.get(Calendar.MONTH)
            val day = cal.get(Calendar.DAY_OF_MONTH)

            return events.filter { event ->
                val startD = parseEventDate(event.start) ?: return@filter false
                val endD = if (!event.end.isNullOrBlank()) parseEventDate(event.end) else startD
                if (endD == null) return@filter false

                val startCal = Calendar.getInstance().apply { time = startD }
                val endCal = Calendar.getInstance().apply { time = endD }

                val checkCal = Calendar.getInstance().apply {
                    set(year, month, day, 12, 0, 0)
                }
                val startMidnight = Calendar.getInstance().apply {
                    time = startD
                    set(Calendar.HOUR_OF_DAY, 0)
                    set(Calendar.MINUTE, 0)
                    set(Calendar.SECOND, 0)
                    set(Calendar.MILLISECOND, 0)
                }
                val endMidnight = Calendar.getInstance().apply {
                    time = endD
                    set(Calendar.HOUR_OF_DAY, 23)
                    set(Calendar.MINUTE, 59)
                    set(Calendar.SECOND, 59)
                    set(Calendar.MILLISECOND, 999)
                }

                checkCal.timeInMillis in startMidnight.timeInMillis..endMidnight.timeInMillis
            }
        }

        private fun formatEventTime(event: CalendarEvent): String {
            if (event.is_all_day) return "Ganztägig"

            val startD = parseEventDate(event.start) ?: return ""
            val endD = if (!event.end.isNullOrBlank()) parseEventDate(event.end) else null

            val timeFmt = SimpleDateFormat("HH:mm", Locale.GERMANY)
            val startTimeStr = timeFmt.format(startD)

            return if (endD != null) {
                val endTimeStr = timeFmt.format(endD)
                "$startTimeStr - $endTimeStr"
            } else {
                startTimeStr
            }
        }

        private fun getCategoryColor(category: String?): Int {
            return when (category?.lowercase(Locale.ROOT)) {
                "restmuell" -> 0xFFF3F4F6.toInt()
                "altpapier" -> 0xFF60A5FA.toInt()
                "biomuell" -> 0xFFF59E0B.toInt()
                "gelber_sack" -> 0xFFFBBF24.toInt()
                "schadstoffe" -> 0xFFF87171.toInt()
                "sperrmuell" -> 0xFFFB923C.toInt()
                "gruen" -> 0xFF34D399.toInt()
                "baum" -> 0xFF2DD4BF.toInt()
                "call" -> 0xFFE879F9.toInt()
                "meeting" -> 0xFF818CF8.toInt()
                "birthday" -> 0xFFF472B6.toInt()
                "vacation" -> 0xFF22D3EE.toInt()
                "travel" -> 0xFFFBBF24.toInt()
                "project" -> 0xFFC5A059.toInt()
                "customer" -> 0xFF10B981.toInt()
                else -> 0xFF9CA3AF.toInt()
            }
        }

        fun updateAppWidget(context: Context, appWidgetManager: AppWidgetManager, appWidgetId: Int) {
            val views = RemoteViews(context.packageName, R.layout.widget_month_layout)

            // Read SharedPreferences values
            val sharedPrefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
            val offset = sharedPrefs.getInt(KEY_MONTH_OFFSET, 0)
            val json = sharedPrefs.getString(CACHE_KEY, null)
            val eventsList: List<CalendarEvent> = if (json != null) {
                try {
                    val type = object : TypeToken<List<CalendarEvent>>() {}.type
                    Gson().fromJson(json, type)
                } catch (e: Exception) {
                    emptyList()
                }
            } else {
                emptyList()
            }

            val selectedDayStr = sharedPrefs.getString(KEY_SELECTED_DAY, "") ?: ""

            // Calculate display date
            val cal = Calendar.getInstance()
            cal.add(Calendar.MONTH, offset)
            val displayMonth = cal.get(Calendar.MONTH)
            val displayYear = cal.get(Calendar.YEAR)

            // Set Header Text
            views.setTextViewText(R.id.txt_month_title, "${monthNames[displayMonth]} $displayYear")

            // Bind Header Click (Reset to current month)
            val resetIntent = Intent(context, CalendarMonthWidgetProvider::class.java).apply {
                action = ACTION_RESET_MONTH
            }
            val pendingReset = PendingIntent.getBroadcast(
                context, appWidgetId * 10 + 101, resetIntent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )
            views.setOnClickPendingIntent(R.id.txt_month_title, pendingReset)

            // Bind Navigation Chevrons
            val prevIntent = Intent(context, CalendarMonthWidgetProvider::class.java).apply {
                action = ACTION_PREV_MONTH
            }
            val pendingPrev = PendingIntent.getBroadcast(
                context, appWidgetId * 10 + 102, prevIntent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )
            views.setOnClickPendingIntent(R.id.btn_prev_month, pendingPrev)

            val nextIntent = Intent(context, CalendarMonthWidgetProvider::class.java).apply {
                action = ACTION_NEXT_MONTH
            }
            val pendingNext = PendingIntent.getBroadcast(
                context, appWidgetId * 10 + 103, nextIntent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )
            views.setOnClickPendingIntent(R.id.btn_next_month, pendingNext)

            // Bind Add Event Click ("+" button opens app directly to create event)
            val todayStr = SimpleDateFormat("yyyy-MM-dd", Locale.US).format(Date())
            val targetDateStr = if (selectedDayStr.isNotEmpty()) selectedDayStr else todayStr
            val addEventIntent = Intent(context, MainActivity::class.java).apply {
                putExtra("open_tab", 2)
                putExtra("open_subtab", 1)
                putExtra("create_event", true)
                putExtra("selected_date", targetDateStr)
            }
            val pendingAddEvent = PendingIntent.getActivity(
                context, appWidgetId * 10 + 104, addEventIntent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )
            views.setOnClickPendingIntent(R.id.btn_add_event, pendingAddEvent)

            // 1. Render Month Grid
            // Calculate grid dates (Monday-start)
            val gridCal = Calendar.getInstance().apply {
                set(Calendar.YEAR, displayYear)
                set(Calendar.MONTH, displayMonth)
                set(Calendar.DAY_OF_MONTH, 1)
            }
            val firstDayOfWeek = gridCal.get(Calendar.DAY_OF_WEEK)
            val prevDays = when (firstDayOfWeek) {
                Calendar.MONDAY -> 0
                Calendar.TUESDAY -> 1
                Calendar.WEDNESDAY -> 2
                Calendar.THURSDAY -> 3
                Calendar.FRIDAY -> 4
                Calendar.SATURDAY -> 5
                Calendar.SUNDAY -> 6
                else -> 0
            }
            gridCal.add(Calendar.DAY_OF_MONTH, -prevDays)

            val today = Calendar.getInstance()
            val sdf = SimpleDateFormat("yyyy-MM-dd", Locale.US)

            // Selected date parsing
            val displayDate = try {
                if (selectedDayStr.isNotEmpty()) sdf.parse(selectedDayStr) else Date()
            } catch (e: Exception) {
                Date()
            }

            val selectedCal = Calendar.getInstance().apply {
                if (displayDate != null) time = displayDate
            }

            // Bind cells
            for (row in 0 until 6) {
                for (col in 0 until 7) {
                    val cellDate = gridCal.time
                    val cellDay = gridCal.get(Calendar.DAY_OF_MONTH)
                    val cellMonth = gridCal.get(Calendar.MONTH)
                    val cellYear = gridCal.get(Calendar.YEAR)

                    val isCurrentMonth = (cellMonth == displayMonth)
                    val isToday = (cellYear == today.get(Calendar.YEAR) &&
                            cellMonth == today.get(Calendar.MONTH) &&
                            cellDay == today.get(Calendar.DAY_OF_MONTH))

                    val isSelected = (cellYear == selectedCal.get(Calendar.YEAR) &&
                            cellMonth == selectedCal.get(Calendar.MONTH) &&
                            cellDay == selectedCal.get(Calendar.DAY_OF_MONTH))

                    // Find View IDs
                    val cellId = context.resources.getIdentifier("cell_${row}_${col}", "id", context.packageName)
                    val textId = context.resources.getIdentifier("cell_text_${row}_${col}", "id", context.packageName)
                    val dotId = context.resources.getIdentifier("cell_dot_${row}_${col}", "id", context.packageName)

                    if (cellId != 0 && textId != 0 && dotId != 0) {
                        // Set text
                        views.setTextViewText(textId, cellDay.toString())

                        // Set Today / Selection Highlight / Month Text Color
                        if (isToday) {
                            views.setInt(textId, "setBackgroundResource", R.drawable.today_circle)
                            views.setTextColor(textId, 0xFF020617.toInt()) // Black text on Gold background
                        } else if (isSelected) {
                            views.setInt(textId, "setBackgroundResource", R.drawable.selected_circle)
                            views.setTextColor(textId, 0xFF020617.toInt()) // Black text on White background
                        } else {
                            views.setInt(textId, "setBackgroundResource", 0) // Clear background
                            if (isCurrentMonth) {
                                views.setTextColor(textId, 0xFFF8FAFC.toInt()) // Bright white
                            } else {
                                views.setTextColor(textId, 0xFF64748B.toInt()) // Dimmed slate
                            }
                        }

                        // Check for events to show dot
                        val dayEvents = getEventsForDay(cellDate, eventsList)
                        if (dayEvents.isNotEmpty()) {
                            views.setViewVisibility(dotId, View.VISIBLE)
                        } else {
                            views.setViewVisibility(dotId, View.INVISIBLE)
                        }

                        // Attach Day Click Intent
                        val dateString = sdf.format(cellDate)
                        val cellClickIntent = Intent(context, CalendarMonthWidgetProvider::class.java).apply {
                            action = ACTION_SELECT_DAY
                            putExtra("selected_date", dateString)
                        }
                        val cellRequestCode = appWidgetId * 10000 + row * 100 + col + 5000
                        val pendingCellClick = PendingIntent.getBroadcast(
                            context, cellRequestCode, cellClickIntent,
                            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
                        )
                        views.setOnClickPendingIntent(cellId, pendingCellClick)
                    }

                    // Move to next day
                    gridCal.add(Calendar.DAY_OF_MONTH, 1)
                }
            }

            // 2. Render Day Events List at the bottom
            val displayTitleStr = displayDate?.let {
                SimpleDateFormat("d. MMMM yyyy", Locale.GERMANY).format(it)
            } ?: ""
            views.setTextViewText(R.id.txt_day_title, "Termine am $displayTitleStr")

            val dayEvents = displayDate?.let { getEventsForDay(it, eventsList) } ?: emptyList()
            val maxRows = 4
            for (i in 0 until maxRows) {
                val rowId = context.resources.getIdentifier("widget_event_row_$i", "id", context.packageName)
                val colorId = context.resources.getIdentifier("widget_event_color_$i", "id", context.packageName)
                val titleId = context.resources.getIdentifier("widget_event_title_$i", "id", context.packageName)
                val timeId = context.resources.getIdentifier("widget_event_time_$i", "id", context.packageName)

                if (rowId != 0) {
                    if (i < dayEvents.size) {
                        val event = dayEvents[i]
                        views.setViewVisibility(rowId, View.VISIBLE)

                        if (titleId != 0) {
                            views.setTextViewText(titleId, event.title)
                        }
                        if (timeId != 0) {
                            views.setTextViewText(timeId, formatEventTime(event))
                        }
                        if (colorId != 0) {
                            views.setInt(colorId, "setBackgroundColor", getCategoryColor(event.category))
                        }

                        // Tapping a specific event row opens the app to that selected date
                        val rowClickIntent = Intent(context, MainActivity::class.java).apply {
                            putExtra("open_tab", 2)
                            putExtra("open_subtab", 1)
                            putExtra("selected_date", targetDateStr)
                        }
                        val pendingRowClick = PendingIntent.getActivity(
                            context, appWidgetId * 10000 + i + 200, rowClickIntent,
                            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
                        )
                        views.setOnClickPendingIntent(rowId, pendingRowClick)
                    } else {
                        views.setViewVisibility(rowId, View.GONE)
                    }
                }
            }

            if (dayEvents.isEmpty()) {
                views.setViewVisibility(R.id.txt_widget_no_events, View.VISIBLE)
            } else {
                views.setViewVisibility(R.id.txt_widget_no_events, View.GONE)
            }

            appWidgetManager.updateAppWidget(appWidgetId, views)
        }
    }
}
