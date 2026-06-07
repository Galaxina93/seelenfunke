package de.meinseelenfunke.app.widget

import android.appwidget.AppWidgetManager
import android.content.Context
import android.content.Intent
import android.widget.RemoteViews
import android.widget.RemoteViewsService
import com.google.gson.Gson
import com.google.gson.reflect.TypeToken
import de.meinseelenfunke.app.R
import de.meinseelenfunke.app.data.api.CalendarEvent
import java.text.SimpleDateFormat
import java.util.Calendar
import java.util.Date
import java.util.Locale
import java.util.TimeZone

class CalendarWidgetService : RemoteViewsService() {
    override fun onGetViewFactory(intent: Intent): RemoteViewsFactory {
        return CalendarWidgetViewsFactory(this.applicationContext)
    }
}

class CalendarWidgetViewsFactory(private val context: Context) : RemoteViewsService.RemoteViewsFactory {
    private var eventsList: List<CalendarEvent> = emptyList()

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

    override fun onCreate() {}

    override fun onDataSetChanged() {
        // Load events from SharedPreferences cache
        val sharedPrefs = context.getSharedPreferences("calendar_widget_prefs", Context.MODE_PRIVATE)
        val json = sharedPrefs.getString("calendar_events_cache", null)
        if (json != null) {
            try {
                val type = object : TypeToken<List<CalendarEvent>>() {}.type
                val allEvents: List<CalendarEvent> = Gson().fromJson(json, type)

                // Filter for today & future events, and sort by start date
                val todayStart = Calendar.getInstance().apply {
                    set(Calendar.HOUR_OF_DAY, 0)
                    set(Calendar.MINUTE, 0)
                    set(Calendar.SECOND, 0)
                    set(Calendar.MILLISECOND, 0)
                }.time

                eventsList = allEvents.filter { event ->
                    try {
                        val eventDate = parseEventDate(event.start)
                        val endStr = event.end
                        val endDate = if (!endStr.isNullOrBlank()) {
                            parseEventDate(endStr)
                        } else {
                            eventDate
                        }

                        endDate != null && endDate.time >= todayStart.time
                    } catch (e: Exception) {
                        true
                    }
                }.sortedBy { event ->
                    try {
                        parseEventDate(event.start)?.time ?: 0L
                    } catch (e: Exception) {
                        0L
                    }
                }
            } catch (e: Exception) {
                eventsList = emptyList()
            }
        } else {
            eventsList = emptyList()
        }
    }

    override fun onDestroy() {
        eventsList = emptyList()
    }

    override fun getCount(): Int = eventsList.size

    override fun getViewAt(position: Int): RemoteViews {
        val views = RemoteViews(context.packageName, R.layout.widget_item_layout)
        val event = eventsList[position]

        // Set Title
        views.setTextViewText(R.id.item_title, event.title)

        // Set Description
        if (event.description.isNullOrBlank()) {
            views.setTextViewText(R.id.item_description, "")
            views.setViewVisibility(R.id.item_description, android.view.View.GONE)
        } else {
            views.setTextViewText(R.id.item_description, event.description)
            views.setViewVisibility(R.id.item_description, android.view.View.VISIBLE)
        }

        // Set Time
        val timeText = if (event.is_all_day) {
            "Ganztägig"
        } else {
            try {
                val eventDate = parseEventDate(event.start)
                val localFormatter = SimpleDateFormat("dd.MM - HH:mm", Locale.GERMANY)
                if (eventDate != null) localFormatter.format(eventDate) else ""
            } catch (e: Exception) {
                ""
            }
        }
        views.setTextViewText(R.id.item_time, timeText)

        // Set Category Color Bar
        val categoryColor = when (event.category.lowercase(Locale.US)) {
            "meeting" -> 0xFF0EA5E9.toInt() // sky-500
            "call" -> 0xFF6366F1.toInt() // indigo-500
            "birthday" -> 0xFFEC4899.toInt() // pink-500
            "vacation" -> 0xFF10B981.toInt() // emerald-500
            "travel" -> 0xFFF59E0B.toInt() // amber-500
            "project" -> 0xFF8B5CF6.toInt() // purple-500
            "customer" -> 0xFFEF4444.toInt() // red-500
            "restmuell" -> 0xFF4B5563.toInt() // gray-600
            "altpapier" -> 0xFF2563EB.toInt() // blue-600
            "biomuell" -> 0xFF78350F.toInt() // brown-800
            "gelber_sack" -> 0xFFEAB308.toInt() // yellow-500
            "schadstoffe" -> 0xFFDC2626.toInt() // red-600
            "sperrmuell" -> 0xFF9CA3AF.toInt() // gray-400
            "gruen" -> 0xFF16A34A.toInt() // green-600
            "baum" -> 0xFF15803D.toInt() // green-700
            else -> 0xFFC5A059.toInt() // Gold default
        }
        views.setInt(R.id.item_category_bar, "setBackgroundColor", categoryColor)

        // Set fill-in intent to pass selected event details and target tab navigation
        val fillInIntent = Intent().apply {
            putExtra("open_tab", 2)       // Organizer index
            putExtra("open_subtab", 1)    // Kalender subtab index
            putExtra("event_id", event.id)
            putExtra("event_title", event.title)
            putExtra("selected_date", event.start.substring(0, 10))
        }
        views.setOnClickFillInIntent(R.id.widget_item_root, fillInIntent)

        return views
    }

    override fun getLoadingView(): RemoteViews? = null

    override fun getViewTypeCount(): Int = 1

    override fun getItemId(position: Int): Long = position.toLong()

    override fun hasStableIds(): Boolean = true
}
