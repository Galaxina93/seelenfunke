package de.meinseelenfunke.app.widget

import android.app.PendingIntent
import android.appwidget.AppWidgetManager
import android.appwidget.AppWidgetProvider
import android.content.ComponentName
import android.content.Context
import android.content.Intent
import android.net.Uri
import android.util.Log
import android.widget.RemoteViews
import com.google.gson.Gson
import com.google.gson.reflect.TypeToken
import de.meinseelenfunke.app.MainActivity
import de.meinseelenfunke.app.R
import de.meinseelenfunke.app.data.api.CalendarEvent
import de.meinseelenfunke.app.di.ServiceLocator
import java.text.SimpleDateFormat
import java.util.Calendar
import java.util.Date
import java.util.Locale
import java.util.TimeZone
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch

class CalendarAppWidgetProvider : AppWidgetProvider() {

    override fun onUpdate(context: Context, appWidgetManager: AppWidgetManager, appWidgetIds: IntArray) {
        for (appWidgetId in appWidgetIds) {
            updateAppWidget(context, appWidgetManager, appWidgetId)
        }
        triggerBackgroundSync(context)
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
        val appWidgetManager = AppWidgetManager.getInstance(appContext)
        val componentName = ComponentName(appContext, CalendarAppWidgetProvider::class.java)
        val appWidgetIds = appWidgetManager.getAppWidgetIds(componentName)

        when (intent.action) {
            ACTION_REFRESH -> {
                appWidgetManager.notifyAppWidgetViewDataChanged(appWidgetIds, R.id.widget_list)
                for (appWidgetId in appWidgetIds) {
                    updateAppWidget(appContext, appWidgetManager, appWidgetId)
                }
                triggerBackgroundSync(appContext)
            }
            AppWidgetManager.ACTION_APPWIDGET_UPDATE -> {
                appWidgetManager.notifyAppWidgetViewDataChanged(appWidgetIds, R.id.widget_list)
                for (appWidgetId in appWidgetIds) {
                    updateAppWidget(appContext, appWidgetManager, appWidgetId)
                }
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
                Log.e("CalendarWidget", "Background sync failed", e)
            } finally {
                pendingResult.finish()
            }
        }
    }

    companion object {
        const val ACTION_REFRESH = "de.meinseelenfunke.app.widget.ACTION_REFRESH"

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

        fun updateAppWidget(context: Context, appWidgetManager: AppWidgetManager, appWidgetId: Int) {
            val appContext = context.applicationContext
            val views = RemoteViews(appContext.packageName, R.layout.widget_layout)

            // Calculate upcoming & today event counts for header subtitle
            val sharedPrefs = appContext.getSharedPreferences("calendar_widget_prefs", Context.MODE_PRIVATE)
            val allEvents: List<CalendarEvent> = try {
                val json = sharedPrefs.getString("calendar_events_cache", null)
                if (json != null) {
                    Gson().fromJson(json, object : TypeToken<List<CalendarEvent>>() {}.type)
                } else emptyList()
            } catch (_: Exception) {
                emptyList()
            }

            val todayStart = Calendar.getInstance().apply {
                set(Calendar.HOUR_OF_DAY, 0)
                set(Calendar.MINUTE, 0)
                set(Calendar.SECOND, 0)
                set(Calendar.MILLISECOND, 0)
            }.time
            val todayEnd = Calendar.getInstance().apply {
                set(Calendar.HOUR_OF_DAY, 23)
                set(Calendar.MINUTE, 59)
                set(Calendar.SECOND, 59)
                set(Calendar.MILLISECOND, 999)
            }.time

            var upcomingCount = 0
            var todayCount = 0
            for (ev in allEvents) {
                val startD = parseEventDate(ev.start) ?: continue
                val endD = if (!ev.end.isNullOrBlank()) parseEventDate(ev.end) ?: startD else startD
                if (endD.time >= todayStart.time) {
                    upcomingCount++
                    if (startD.time <= todayEnd.time) {
                        todayCount++
                    }
                }
            }

            val subtitleText = when {
                upcomingCount == 0 -> "Keine anstehenden Termine"
                todayCount > 0 -> "Heute: $todayCount · Gesamt: $upcomingCount"
                else -> "$upcomingCount anstehende Termine"
            }
            views.setTextViewText(R.id.calendar_widget_subtitle, subtitleText)

            // Setup Adapter Intent for ListView
            val serviceIntent = Intent(appContext, CalendarWidgetService::class.java).apply {
                putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
                data = Uri.parse("funkenwerk://calendar/service/$appWidgetId")
            }
            views.setRemoteAdapter(R.id.widget_list, serviceIntent)
            views.setEmptyView(R.id.widget_list, R.id.widget_empty_view)

            // Header Click -> Open MainActivity in Organizer -> Kalender (SubTab 1)
            val appIntent = Intent(appContext, MainActivity::class.java).apply {
                putExtra("open_tab", 2)
                putExtra("open_subtab", 1)
                data = Uri.parse("funkenwerk://calendar/open_app/$appWidgetId")
                flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
                de.meinseelenfunke.app.util.SecurityIntentHelper.signIntent(appContext, this)
            }
            val pendingAppIntent = PendingIntent.getActivity(
                appContext,
                appWidgetId * 100 + 301,
                appIntent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )
            views.setOnClickPendingIntent(R.id.calendar_widget_header_click, pendingAppIntent)

            // Add Event '+' Button -> Open MainActivity in Organizer -> Kalender with Create Dialog
            val addEventIntent = Intent(appContext, MainActivity::class.java).apply {
                putExtra("open_tab", 2)
                putExtra("open_subtab", 1)
                putExtra("create_event", true)
                data = Uri.parse("funkenwerk://calendar/add_event/$appWidgetId")
                flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
                de.meinseelenfunke.app.util.SecurityIntentHelper.signIntent(appContext, this)
            }
            val pendingAddEvent = PendingIntent.getActivity(
                appContext,
                appWidgetId * 100 + 302,
                addEventIntent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )
            views.setOnClickPendingIntent(R.id.btn_widget_add_event, pendingAddEvent)

            // Setup Mutable PendingIntent template for ListView item clicks (Android 14-17 compliant)
            val clickIntent = Intent(appContext, MainActivity::class.java).apply {
                action = Intent.ACTION_VIEW
                data = Uri.parse("funkenwerk://calendar/item_click/$appWidgetId")
                flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
                de.meinseelenfunke.app.util.SecurityIntentHelper.signIntent(appContext, this)
            }
            val clickPendingIntent = PendingIntent.getActivity(
                appContext,
                appWidgetId * 100 + 303,
                clickIntent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_MUTABLE
            )
            views.setPendingIntentTemplate(R.id.widget_list, clickPendingIntent)

            // Refresh Click intent
            val refreshIntent = Intent(appContext, CalendarAppWidgetProvider::class.java).apply {
                action = ACTION_REFRESH
                data = Uri.parse("funkenwerk://calendar/refresh/$appWidgetId")
                de.meinseelenfunke.app.util.SecurityIntentHelper.signIntent(appContext, this)
            }
            val pendingRefreshIntent = PendingIntent.getBroadcast(
                appContext,
                appWidgetId * 100 + 304,
                refreshIntent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )
            views.setOnClickPendingIntent(R.id.btn_widget_refresh, pendingRefreshIntent)

            appWidgetManager.updateAppWidget(appWidgetId, views)
        }
    }
}
