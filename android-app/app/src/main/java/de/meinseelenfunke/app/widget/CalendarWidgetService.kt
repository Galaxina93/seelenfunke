package de.meinseelenfunke.app.widget

import android.content.Context
import android.content.Intent
import android.view.View
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

    override fun onCreate() {
        loadEventsFromPrefs()
    }

    override fun onDataSetChanged() {
        loadEventsFromPrefs()
    }

    private fun loadEventsFromPrefs() {
        val sharedPrefs = context.getSharedPreferences("calendar_widget_prefs", Context.MODE_PRIVATE)
        val json = sharedPrefs.getString("calendar_events_cache", null)
        if (json != null) {
            try {
                val type = object : TypeToken<List<CalendarEvent>>() {}.type
                val allEvents: List<CalendarEvent> = Gson().fromJson(json, type)

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
                            parseEventDate(endStr) ?: eventDate
                        } else {
                            eventDate
                        }
                        endDate != null && endDate.time >= todayStart.time
                    } catch (_: Exception) {
                        true
                    }
                }.sortedBy { event ->
                    parseEventDate(event.start)?.time ?: 0L
                }
            } catch (_: Exception) {
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

    override fun getViewAt(position: Int): RemoteViews? {
        if (position < 0 || position >= eventsList.size) return null
        val views = RemoteViews(context.packageName, R.layout.widget_item_layout)
        val event = eventsList[position]

        views.setTextViewText(R.id.item_title, event.title)

        if (event.description.isNullOrBlank()) {
            views.setTextViewText(R.id.item_description, "")
            views.setViewVisibility(R.id.item_description, View.GONE)
        } else {
            views.setTextViewText(R.id.item_description, event.description)
            views.setViewVisibility(R.id.item_description, View.VISIBLE)
        }

        views.setTextViewText(R.id.item_time, formatAgendaDateTime(event))

        val categoryColor = when (event.category.lowercase(Locale.US)) {
            "meeting" -> 0xFF0EA5E9.toInt()
            "call" -> 0xFF6366F1.toInt()
            "birthday" -> 0xFFEC4899.toInt()
            "vacation" -> 0xFF10B981.toInt()
            "travel" -> 0xFFF59E0B.toInt()
            "project" -> 0xFF8B5CF6.toInt()
            "customer" -> 0xFFEF4444.toInt()
            "restmuell" -> 0xFF94A3B8.toInt()
            "altpapier" -> 0xFF3B82F6.toInt()
            "biomuell" -> 0xFFF59E0B.toInt()
            "gelber_sack" -> 0xFFEAB308.toInt()
            "schadstoffe" -> 0xFFDC2626.toInt()
            "sperrmuell" -> 0xFFFB923C.toInt()
            "gruen" -> 0xFF16A34A.toInt()
            "baum" -> 0xFF15803D.toInt()
            else -> 0xFFC5A059.toInt()
        }
        views.setInt(R.id.item_category_bar, "setBackgroundColor", categoryColor)

        val datePrefix = if (event.start.length >= 10) event.start.substring(0, 10) else ""
        val fillInIntent = Intent().apply {
            putExtra("open_tab", 2)
            putExtra("open_subtab", 1)
            putExtra("event_id", event.id)
            putExtra("event_title", event.title)
            putExtra("selected_date", datePrefix)
        }
        views.setOnClickFillInIntent(R.id.widget_item_root, fillInIntent)

        return views
    }

    private fun formatAgendaDateTime(event: CalendarEvent): String {
        val startDate = parseEventDate(event.start) ?: return if (event.is_all_day) "Ganztägig" else ""

        val todayCal = Calendar.getInstance().apply {
            set(Calendar.HOUR_OF_DAY, 0)
            set(Calendar.MINUTE, 0)
            set(Calendar.SECOND, 0)
            set(Calendar.MILLISECOND, 0)
        }
        val tomorrowCal = (todayCal.clone() as Calendar).apply {
            add(Calendar.DAY_OF_YEAR, 1)
        }
        val evDayCal = Calendar.getInstance().apply {
            time = startDate
            set(Calendar.HOUR_OF_DAY, 0)
            set(Calendar.MINUTE, 0)
            set(Calendar.SECOND, 0)
            set(Calendar.MILLISECOND, 0)
        }

        val dayLabel = when {
            evDayCal.timeInMillis == todayCal.timeInMillis -> "Heute"
            evDayCal.timeInMillis == tomorrowCal.timeInMillis -> "Morgen"
            else -> SimpleDateFormat("EE, dd.MM.", Locale.GERMANY).format(startDate)
        }

        return if (event.is_all_day) {
            "$dayLabel · Ganztägig"
        } else {
            val timeStr = SimpleDateFormat("HH:mm", Locale.GERMANY).format(startDate)
            "$dayLabel · $timeStr Uhr"
        }
    }

    override fun getLoadingView(): RemoteViews? = null

    override fun getViewTypeCount(): Int = 1

    override fun getItemId(position: Int): Long {
        if (position < 0 || position >= eventsList.size) return position.toLong()
        return eventsList[position].id.hashCode().toLong()
    }

    override fun hasStableIds(): Boolean = true
}
