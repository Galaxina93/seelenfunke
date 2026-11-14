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
import de.meinseelenfunke.app.di.ServiceLocator
import java.text.SimpleDateFormat
import java.util.*
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch

class CalendarMonthWidgetProvider : AppWidgetProvider() {

    override fun onUpdate(context: Context, appWidgetManager: AppWidgetManager, appWidgetIds: IntArray) {
        ServiceLocator.ensureInitialized(context.applicationContext)
        for (appWidgetId in appWidgetIds) {
            updateAppWidget(context, appWidgetManager, appWidgetId)
        }
        if (!ServiceLocator.getAuthToken().isNullOrBlank()) {
            triggerBackgroundSync(context)
        }
        super.onUpdate(context, appWidgetManager, appWidgetIds)
    }

    override fun onReceive(context: Context, intent: Intent) {
        val action = intent.action
        if (action != null && action.startsWith("de.meinseelenfunke.app.widget.") &&
            !de.meinseelenfunke.app.util.SecurityIntentHelper.isTrustedInternalIntent(context, intent)
        ) {
            return
        }
        super.onReceive(context, intent)
        val appContext = context.applicationContext
        ServiceLocator.ensureInitialized(appContext)
        if (action != null && action != AppWidgetManager.ACTION_APPWIDGET_UPDATE) {
            if (de.meinseelenfunke.app.util.SecurityIntentHelper.isDeviceLocked(context) ||
                ServiceLocator.getAuthToken().isNullOrBlank()
            ) {
                return
            }
        }
        val sharedPrefs = appContext.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        val offset = sharedPrefs.getInt(KEY_MONTH_OFFSET, 0)

        when (intent.action) {
            ACTION_PREV_MONTH -> {
                sharedPrefs.edit().putInt(KEY_MONTH_OFFSET, offset - 1).apply()
                triggerUpdate(appContext)
            }
            ACTION_NEXT_MONTH -> {
                sharedPrefs.edit().putInt(KEY_MONTH_OFFSET, offset + 1).apply()
                triggerUpdate(appContext)
            }
            ACTION_RESET_MONTH -> {
                val todayStr = SimpleDateFormat("yyyy-MM-dd", Locale.US).format(Date())
                sharedPrefs.edit()
                    .putInt(KEY_MONTH_OFFSET, 0)
                    .putString(KEY_SELECTED_DAY, todayStr)
                    .apply()
                triggerUpdate(appContext)
            }
            ACTION_SELECT_DAY -> {
                val selectedDay = intent.getStringExtra("selected_date")
                if (selectedDay != null) {
                    sharedPrefs.edit()
                        .putString(KEY_VIEW_MODE, "month")
                        .putString(KEY_SELECTED_DAY, selectedDay)
                        .apply()
                    triggerUpdate(appContext)
                }
            }
            ACTION_SHOW_MONTH -> {
                sharedPrefs.edit()
                    .putString(KEY_VIEW_MODE, "month")
                    .apply()
                triggerUpdate(appContext)
            }
            ACTION_REFRESH -> {
                triggerUpdate(appContext)
                triggerBackgroundSync(appContext)
            }
            AppWidgetManager.ACTION_APPWIDGET_UPDATE -> {
                triggerUpdate(appContext)
            }
        }
    }

    private fun triggerBackgroundSync(context: Context) {
        val appContext = context.applicationContext
        val pendingResult = goAsync()
        CoroutineScope(Dispatchers.IO).launch {
            try {
                ServiceLocator.organizerRepository.getCalendarEvents()
                ServiceLocator.organizerRepository.triggerCalendarWidgetUpdate(appContext)
            } catch (e: Exception) {
                Log.e("CalendarMonthWidget", "Background sync failed", e)
            } finally {
                pendingResult.finish()
            }
        }
    }

    private fun triggerUpdate(context: Context) {
        val appContext = context.applicationContext
        val appWidgetManager = AppWidgetManager.getInstance(appContext)
        val componentName = ComponentName(appContext, CalendarMonthWidgetProvider::class.java)
        val appWidgetIds = appWidgetManager.getAppWidgetIds(componentName)
        for (appWidgetId in appWidgetIds) {
            updateAppWidget(appContext, appWidgetManager, appWidgetId)
        }
    }

    companion object {
        const val ACTION_PREV_MONTH = "de.meinseelenfunke.app.widget.ACTION_PREV_MONTH"
        const val ACTION_NEXT_MONTH = "de.meinseelenfunke.app.widget.ACTION_NEXT_MONTH"
        const val ACTION_RESET_MONTH = "de.meinseelenfunke.app.widget.ACTION_RESET_MONTH"
        const val ACTION_SELECT_DAY = "de.meinseelenfunke.app.widget.ACTION_SELECT_DAY"
        const val ACTION_SHOW_MONTH = "de.meinseelenfunke.app.widget.ACTION_SHOW_MONTH"
        const val ACTION_REFRESH = "de.meinseelenfunke.app.widget.ACTION_MONTH_REFRESH"

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
            return try {
                if (dateStr.contains("T")) {
                    try {
                        val odt = java.time.OffsetDateTime.parse(dateStr)
                        Date(odt.toInstant().toEpochMilli())
                    } catch (_: Exception) {
                        try {
                            val ldt = java.time.LocalDateTime.parse(dateStr)
                            val zdt = ldt.atZone(java.time.ZoneId.systemDefault())
                            Date(zdt.toInstant().toEpochMilli())
                        } catch (_: Exception) {
                            val sdf = SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss", Locale.US).apply {
                                timeZone = TimeZone.getTimeZone("UTC")
                            }
                            sdf.parse(dateStr)
                        }
                    }
                } else {
                    SimpleDateFormat("yyyy-MM-dd", Locale.US).parse(dateStr)
                }
            } catch (_: Exception) {
                null
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
                "restmuell" -> 0xFF94A3B8.toInt()
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
                else -> 0xFFC5A059.toInt()
            }
        }

        fun updateAppWidget(context: Context, appWidgetManager: AppWidgetManager, appWidgetId: Int) {
            val appContext = context.applicationContext
            val views = RemoteViews(appContext.packageName, R.layout.widget_month_layout)

            ServiceLocator.ensureInitialized(appContext)
            val sharedPrefs = appContext.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
            val offset = sharedPrefs.getInt(KEY_MONTH_OFFSET, 0)
            val json = ServiceLocator.getDecryptedPref(sharedPrefs, CACHE_KEY)
            val eventsList: List<CalendarEvent> = if (json != null) {
                try {
                    val type = object : TypeToken<List<CalendarEvent>>() {}.type
                    Gson().fromJson(json, type)
                } catch (_: Exception) {
                    emptyList()
                }
            } else {
                emptyList()
            }

            val selectedDayStr = sharedPrefs.getString(KEY_SELECTED_DAY, "") ?: ""

            val cal = Calendar.getInstance()
            cal.add(Calendar.MONTH, offset)
            val displayMonth = cal.get(Calendar.MONTH)
            val displayYear = cal.get(Calendar.YEAR)

            views.setTextViewText(R.id.txt_month_title, "${monthNames[displayMonth]} $displayYear")

            // Bind Logo Click -> Open App in Calendar Tab
            val openAppIntent = Intent(appContext, MainActivity::class.java).apply {
                putExtra("open_tab", 2)
                putExtra("open_subtab", 1)
                data = Uri.parse("funkenwerk://calendar_month/open_app/$appWidgetId")
                flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
                de.meinseelenfunke.app.util.SecurityIntentHelper.signIntent(appContext, this)
            }
            val pendingOpenApp = PendingIntent.getActivity(
                appContext,
                appWidgetId * 100 + 100,
                openAppIntent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )
            views.setOnClickPendingIntent(R.id.month_widget_logo, pendingOpenApp)

            // Bind Header Title Click -> Reset to current month & today
            val resetIntent = Intent(appContext, CalendarMonthWidgetProvider::class.java).apply {
                action = ACTION_RESET_MONTH
                data = Uri.parse("funkenwerk://calendar_month/reset/$appWidgetId")
                de.meinseelenfunke.app.util.SecurityIntentHelper.signIntent(appContext, this)
            }
            val pendingReset = PendingIntent.getBroadcast(
                appContext,
                appWidgetId * 100 + 101,
                resetIntent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )
            views.setOnClickPendingIntent(R.id.txt_month_title, pendingReset)

            // Bind Navigation Chevrons
            val prevIntent = Intent(appContext, CalendarMonthWidgetProvider::class.java).apply {
                action = ACTION_PREV_MONTH
                data = Uri.parse("funkenwerk://calendar_month/prev/$appWidgetId")
                de.meinseelenfunke.app.util.SecurityIntentHelper.signIntent(appContext, this)
            }
            val pendingPrev = PendingIntent.getBroadcast(
                appContext,
                appWidgetId * 100 + 102,
                prevIntent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )
            views.setOnClickPendingIntent(R.id.btn_prev_month, pendingPrev)

            val nextIntent = Intent(appContext, CalendarMonthWidgetProvider::class.java).apply {
                action = ACTION_NEXT_MONTH
                data = Uri.parse("funkenwerk://calendar_month/next/$appWidgetId")
                de.meinseelenfunke.app.util.SecurityIntentHelper.signIntent(appContext, this)
            }
            val pendingNext = PendingIntent.getBroadcast(
                appContext,
                appWidgetId * 100 + 103,
                nextIntent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )
            views.setOnClickPendingIntent(R.id.btn_next_month, pendingNext)

            // Bind Refresh Button
            val refreshIntent = Intent(appContext, CalendarMonthWidgetProvider::class.java).apply {
                action = ACTION_REFRESH
                data = Uri.parse("funkenwerk://calendar_month/refresh/$appWidgetId")
                de.meinseelenfunke.app.util.SecurityIntentHelper.signIntent(appContext, this)
            }
            val pendingRefresh = PendingIntent.getBroadcast(
                appContext,
                appWidgetId * 100 + 105,
                refreshIntent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )
            views.setOnClickPendingIntent(R.id.btn_month_refresh, pendingRefresh)

            // Bind Add Event Click ("+" button opens app directly to create event on selected date)
            val todayStr = SimpleDateFormat("yyyy-MM-dd", Locale.US).format(Date())
            val targetDateStr = if (selectedDayStr.isNotEmpty()) selectedDayStr else todayStr
            val addEventIntent = Intent(appContext, MainActivity::class.java).apply {
                putExtra("open_tab", 2)
                putExtra("open_subtab", 1)
                putExtra("create_event", true)
                putExtra("selected_date", targetDateStr)
                data = Uri.parse("funkenwerk://calendar_month/add_event/$appWidgetId/$targetDateStr")
                flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
                de.meinseelenfunke.app.util.SecurityIntentHelper.signIntent(appContext, this)
            }
            val pendingAddEvent = PendingIntent.getActivity(
                appContext,
                appWidgetId * 100 + 104,
                addEventIntent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )
            views.setOnClickPendingIntent(R.id.btn_add_event, pendingAddEvent)

            // 1. Render Month Grid (Monday-start)
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

            val displayDate = try {
                if (selectedDayStr.isNotEmpty()) sdf.parse(selectedDayStr) else Date()
            } catch (_: Exception) {
                Date()
            }

            val selectedCal = Calendar.getInstance().apply {
                if (displayDate != null) time = displayDate
            }

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

                    val cellId = appContext.resources.getIdentifier("cell_${row}_${col}", "id", appContext.packageName)
                    val textId = appContext.resources.getIdentifier("cell_text_${row}_${col}", "id", appContext.packageName)
                    val dotId = appContext.resources.getIdentifier("cell_dot_${row}_${col}", "id", appContext.packageName)

                    if (cellId != 0 && textId != 0 && dotId != 0) {
                        views.setTextViewText(textId, cellDay.toString())

                        if (isToday) {
                            views.setInt(textId, "setBackgroundResource", R.drawable.today_circle)
                            views.setTextColor(textId, 0xFF020617.toInt())
                        } else if (isSelected) {
                            views.setInt(textId, "setBackgroundResource", R.drawable.selected_circle)
                            views.setTextColor(textId, 0xFF020617.toInt())
                        } else {
                            views.setInt(textId, "setBackgroundResource", 0)
                            if (isCurrentMonth) {
                                views.setTextColor(textId, 0xFFF8FAFC.toInt())
                            } else {
                                views.setTextColor(textId, 0xFF475569.toInt())
                            }
                        }

                        val dayEvents = getEventsForDay(cellDate, eventsList)
                        if (dayEvents.isNotEmpty()) {
                            views.setViewVisibility(dotId, View.VISIBLE)
                        } else {
                            views.setViewVisibility(dotId, View.INVISIBLE)
                        }

                        val dateString = sdf.format(cellDate)
                        val cellClickIntent = Intent(appContext, CalendarMonthWidgetProvider::class.java).apply {
                            action = ACTION_SELECT_DAY
                            putExtra("selected_date", dateString)
                            data = Uri.parse("funkenwerk://calendar_month/day/$appWidgetId/$dateString")
                            de.meinseelenfunke.app.util.SecurityIntentHelper.signIntent(appContext, this)
                        }
                        val cellRequestCode = appWidgetId * 10000 + row * 100 + col + 5000
                        val pendingCellClick = PendingIntent.getBroadcast(
                            appContext,
                            cellRequestCode,
                            cellClickIntent,
                            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
                        )
                        views.setOnClickPendingIntent(cellId, pendingCellClick)
                    }

                    gridCal.add(Calendar.DAY_OF_MONTH, 1)
                }
            }

            // 2. Render Day Events List at the bottom
            val displayTitleStr = displayDate?.let {
                SimpleDateFormat("d. MMMM yyyy", Locale.GERMANY).format(it)
            } ?: ""
            val dayEvents = displayDate?.let { getEventsForDay(it, eventsList) } ?: emptyList()
            views.setTextViewText(
                R.id.txt_day_title,
                if (dayEvents.isNotEmpty()) "TERMINE AM ${displayTitleStr.uppercase(Locale.GERMANY)} (${dayEvents.size})"
                else "TERMINE AM ${displayTitleStr.uppercase(Locale.GERMANY)}"
            )

            val maxRows = 4
            for (i in 0 until maxRows) {
                val rowId = appContext.resources.getIdentifier("widget_event_row_$i", "id", appContext.packageName)
                val colorId = appContext.resources.getIdentifier("widget_event_color_$i", "id", appContext.packageName)
                val titleId = appContext.resources.getIdentifier("widget_event_title_$i", "id", appContext.packageName)
                val timeId = appContext.resources.getIdentifier("widget_event_time_$i", "id", appContext.packageName)

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

                        val rowClickIntent = Intent(appContext, MainActivity::class.java).apply {
                            putExtra("open_tab", 2)
                            putExtra("open_subtab", 1)
                            putExtra("selected_date", targetDateStr)
                            data = Uri.parse("funkenwerk://calendar_month/event/$appWidgetId/$i/$targetDateStr")
                            flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
                            de.meinseelenfunke.app.util.SecurityIntentHelper.signIntent(appContext, this)
                        }
                        val pendingRowClick = PendingIntent.getActivity(
                            appContext,
                            appWidgetId * 10000 + i + 200,
                            rowClickIntent,
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
