package de.meinseelenfunke.app.widget

import android.app.PendingIntent
import android.appwidget.AppWidgetManager
import android.appwidget.AppWidgetProvider
import android.content.ComponentName
import android.content.Context
import android.content.Intent
import android.net.Uri
import android.view.View
import android.widget.RemoteViews
import com.google.gson.Gson
import com.google.gson.reflect.TypeToken
import de.meinseelenfunke.app.MainActivity
import de.meinseelenfunke.app.R
import de.meinseelenfunke.app.data.api.CalendarEvent
import de.meinseelenfunke.app.data.api.ManagementShoppingItem
import de.meinseelenfunke.app.data.api.ManagementTask
import de.meinseelenfunke.app.data.api.ManagementTaskList
import de.meinseelenfunke.app.di.ServiceLocator
import de.meinseelenfunke.app.util.MusicPlayerManager
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import java.time.LocalDate

/**
 * In-memory RAM cache so rapid tab switching in the Master-Hub Widget
 * performs zero redundant Gson JSON parsing on the UI/Binder thread.
 */
object MasterWidgetRamCache {
    private val gson = Gson()

    @Volatile private var lastTaskListsJson: String? = null
    @Volatile var taskLists: List<ManagementTaskList> = emptyList()
        private set

    @Volatile private var lastTasksJson: String? = null
    @Volatile var allTasks: List<ManagementTask> = emptyList()
        private set

    @Volatile private var lastEventsJson: String? = null
    @Volatile var allEvents: List<CalendarEvent> = emptyList()
        private set

    @Volatile private var lastShoppingJson: String? = null
    @Volatile var shoppingItems: List<ManagementShoppingItem> = emptyList()
        private set

    fun refreshFromPrefsIfChanged(context: Context) {
        val appContext = context.applicationContext

        val tasksPrefs = appContext.getSharedPreferences("tasks_widget_prefs", Context.MODE_PRIVATE)
        val listsJson = tasksPrefs.getString("task_lists_cache", null)
        if (listsJson != lastTaskListsJson) {
            lastTaskListsJson = listsJson
            taskLists = if (listsJson != null) {
                try {
                    gson.fromJson(listsJson, object : TypeToken<List<ManagementTaskList>>() {}.type)
                } catch (_: Exception) {
                    emptyList()
                }
            } else emptyList()
        }

        val tasksJson = tasksPrefs.getString("tasks_cache", null)
        if (tasksJson != lastTasksJson) {
            lastTasksJson = tasksJson
            allTasks = if (tasksJson != null) {
                try {
                    gson.fromJson(tasksJson, object : TypeToken<List<ManagementTask>>() {}.type)
                } catch (_: Exception) {
                    emptyList()
                }
            } else emptyList()
        }

        val calPrefs = appContext.getSharedPreferences("calendar_widget_prefs", Context.MODE_PRIVATE)
        val eventsJson = calPrefs.getString("calendar_events_cache", null)
        if (eventsJson != lastEventsJson) {
            lastEventsJson = eventsJson
            allEvents = if (eventsJson != null) {
                try {
                    gson.fromJson(eventsJson, object : TypeToken<List<CalendarEvent>>() {}.type)
                } catch (_: Exception) {
                    emptyList()
                }
            } else emptyList()
        }

        val shopPrefs = appContext.getSharedPreferences("shopping_widget_prefs", Context.MODE_PRIVATE)
        val shopJson = shopPrefs.getString("shopping_items_cache", null)
        if (shopJson != lastShoppingJson) {
            lastShoppingJson = shopJson
            shoppingItems = if (shopJson != null) {
                try {
                    gson.fromJson(shopJson, object : TypeToken<List<ManagementShoppingItem>>() {}.type)
                } catch (_: Exception) {
                    emptyList()
                }
            } else emptyList()
        }
    }
}

class MasterWidgetProvider : AppWidgetProvider() {

    override fun onUpdate(context: Context, appWidgetManager: AppWidgetManager, appWidgetIds: IntArray) {
        triggerGlobalSync(context)
        for (appWidgetId in appWidgetIds) {
            updateAppWidget(context, appWidgetManager, appWidgetId, isPartialUpdate = false)
        }
        super.onUpdate(context, appWidgetManager, appWidgetIds)
    }

    override fun onReceive(context: Context, intent: Intent) {
        super.onReceive(context, intent)

        val appContext = context.applicationContext
        val appWidgetManager = AppWidgetManager.getInstance(appContext)
        val prefs = appContext.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        val resolvedAction = intent.getStringExtra(TasksWidgetProvider.EXTRA_CLICK_ACTION) ?: intent.action

        when (resolvedAction) {
            ACTION_SWITCH_TAB -> {
                val appWidgetId = intent.getIntExtra(
                    AppWidgetManager.EXTRA_APPWIDGET_ID,
                    AppWidgetManager.INVALID_APPWIDGET_ID
                )
                val targetTab = intent.getIntExtra(EXTRA_TAB_INDEX, TAB_TASKS)
                if (appWidgetId != AppWidgetManager.INVALID_APPWIDGET_ID) {
                    val currentTab = prefs.getInt("$PREF_ACTIVE_TAB_PREFIX$appWidgetId", TAB_TASKS)
                    if (currentTab != targetTab) {
                        prefs.edit().putInt("$PREF_ACTIVE_TAB_PREFIX$appWidgetId", targetTab).commit()
                        updateAppWidget(appContext, appWidgetManager, appWidgetId, isPartialUpdate = true)
                        appWidgetManager.notifyAppWidgetViewDataChanged(appWidgetId, R.id.master_widget_list)
                    }
                }
            }

            ACTION_MASTER_SELECT_LIST -> {
                val appWidgetId = intent.getIntExtra(
                    AppWidgetManager.EXTRA_APPWIDGET_ID,
                    AppWidgetManager.INVALID_APPWIDGET_ID
                )
                val listId = intent.getStringExtra(TasksWidgetProvider.EXTRA_LIST_ID)
                val listName = intent.getStringExtra(TasksWidgetProvider.EXTRA_LIST_NAME)
                if (appWidgetId != AppWidgetManager.INVALID_APPWIDGET_ID && listId != null) {
                    prefs.edit()
                        .putString("$PREF_SELECTED_LIST_ID_PREFIX$appWidgetId", listId)
                        .putString("$PREF_SELECTED_LIST_NAME_PREFIX$appWidgetId", listName ?: "Aufgaben")
                        .commit()
                    updateAppWidget(appContext, appWidgetManager, appWidgetId, isPartialUpdate = true)
                    appWidgetManager.notifyAppWidgetViewDataChanged(appWidgetId, R.id.master_widget_list)
                }
            }

            ACTION_MASTER_BACK_LISTS -> {
                val appWidgetId = intent.getIntExtra(
                    AppWidgetManager.EXTRA_APPWIDGET_ID,
                    AppWidgetManager.INVALID_APPWIDGET_ID
                )
                if (appWidgetId != AppWidgetManager.INVALID_APPWIDGET_ID) {
                    prefs.edit()
                        .remove("$PREF_SELECTED_LIST_ID_PREFIX$appWidgetId")
                        .remove("$PREF_SELECTED_LIST_NAME_PREFIX$appWidgetId")
                        .commit()
                    updateAppWidget(appContext, appWidgetManager, appWidgetId, isPartialUpdate = true)
                    appWidgetManager.notifyAppWidgetViewDataChanged(appWidgetId, R.id.master_widget_list)
                }
            }

            ACTION_MASTER_REFRESH -> {
                triggerGlobalSync(appContext)
            }

            ACTION_PLAY_TRACK -> {
                val trackId = intent.getStringExtra(EXTRA_TRACK_ID)
                if (!trackId.isNullOrBlank()) {
                    val allTracks = MusicPlayerManager.allTracks.value
                    val target = allTracks.firstOrNull { it.id == trackId }
                    if (target != null) {
                        MusicPlayerManager.playTrack(appContext, target, allTracks)
                    }
                }
                updateAllWidgets(appContext, isPartial = true)
            }

            MusicWidgetProvider.ACTION_PLAY_PAUSE -> {
                MusicPlayerManager.togglePlayPause(appContext)
                updateAllWidgets(appContext, isPartial = true)
            }

            MusicWidgetProvider.ACTION_NEXT -> {
                MusicPlayerManager.playNext(appContext)
                updateAllWidgets(appContext, isPartial = true)
            }

            MusicWidgetProvider.ACTION_PREV -> {
                MusicPlayerManager.playPrevious(appContext)
                updateAllWidgets(appContext, isPartial = true)
            }

            MusicWidgetProvider.ACTION_SHUFFLE -> {
                MusicPlayerManager.toggleShuffle(appContext)
                updateAllWidgets(appContext, isPartial = true)
            }

            AppWidgetManager.ACTION_APPWIDGET_UPDATE -> {
                updateAllWidgets(appContext, isPartial = false)
            }
        }
    }

    private fun triggerGlobalSync(context: Context) {
        val appContext = context.applicationContext
        if (MusicPlayerManager.allTracks.value.isEmpty()) {
            MusicPlayerManager.fetchPlaylists(appContext)
        }
        val pendingResult = goAsync()
        CoroutineScope(Dispatchers.IO).launch {
            try {
                ServiceLocator.organizerRepository.getTaskLists()
                ServiceLocator.organizerRepository.getTasks()
                ServiceLocator.organizerRepository.getCalendarEvents()
                ServiceLocator.organizerRepository.getShoppingItems()
                MasterWidgetRamCache.refreshFromPrefsIfChanged(appContext)
                updateAllWidgets(appContext, isPartial = true)
            } catch (e: Exception) {
                e.printStackTrace()
            } finally {
                pendingResult.finish()
            }
        }
    }

    companion object {
        const val PREFS_NAME = "master_widget_prefs"
        const val PREF_ACTIVE_TAB_PREFIX = "master_active_tab_"
        const val PREF_SELECTED_LIST_ID_PREFIX = "master_selected_list_id_"
        const val PREF_SELECTED_LIST_NAME_PREFIX = "master_selected_list_name_"

        const val TAB_TASKS = 0
        const val TAB_CALENDAR = 1
        const val TAB_SHOPPING = 2
        const val TAB_MUSIC = 3

        const val ACTION_SWITCH_TAB = "de.meinseelenfunke.app.widget.ACTION_MASTER_SWITCH_TAB"
        const val ACTION_MASTER_SELECT_LIST = "de.meinseelenfunke.app.widget.ACTION_MASTER_SELECT_LIST"
        const val ACTION_MASTER_BACK_LISTS = "de.meinseelenfunke.app.widget.ACTION_MASTER_BACK_LISTS"
        const val ACTION_MASTER_REFRESH = "de.meinseelenfunke.app.widget.ACTION_MASTER_REFRESH"
        const val ACTION_PLAY_TRACK = "de.meinseelenfunke.app.widget.ACTION_MASTER_PLAY_TRACK"
        const val ACTION_OPEN_CALENDAR_EVENT = "de.meinseelenfunke.app.widget.ACTION_MASTER_OPEN_CALENDAR_EVENT"

        const val EXTRA_TAB_INDEX = "extra_tab_index"
        const val EXTRA_TRACK_ID = "extra_track_id"
        const val EXTRA_EVENT_DATE = "extra_event_date"

        fun updateAllWidgets(context: Context, isPartial: Boolean = true) {
            val appContext = context.applicationContext
            val appWidgetManager = AppWidgetManager.getInstance(appContext)
            val componentName = ComponentName(appContext, MasterWidgetProvider::class.java)
            val appWidgetIds = appWidgetManager.getAppWidgetIds(componentName)
            if (appWidgetIds.isEmpty()) return

            MasterWidgetRamCache.refreshFromPrefsIfChanged(appContext)
            for (id in appWidgetIds) {
                updateAppWidget(appContext, appWidgetManager, id, isPartialUpdate = isPartial)
            }
            appWidgetManager.notifyAppWidgetViewDataChanged(appWidgetIds, R.id.master_widget_list)
        }

        fun updateAppWidget(
            context: Context,
            appWidgetManager: AppWidgetManager,
            appWidgetId: Int,
            isPartialUpdate: Boolean = false
        ) {
            val appContext = context.applicationContext
            val masterPrefs = appContext.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
            val activeTab = masterPrefs.getInt("$PREF_ACTIVE_TAB_PREFIX$appWidgetId", TAB_TASKS)
            val selectedListId = masterPrefs.getString("$PREF_SELECTED_LIST_ID_PREFIX$appWidgetId", null)
            val selectedListName = masterPrefs.getString("$PREF_SELECTED_LIST_NAME_PREFIX$appWidgetId", null)

            MasterWidgetRamCache.refreshFromPrefsIfChanged(appContext)
            val taskLists = MasterWidgetRamCache.taskLists
            val allTasks = MasterWidgetRamCache.allTasks
            val allEvents = MasterWidgetRamCache.allEvents
            val shoppingItems = MasterWidgetRamCache.shoppingItems

            val openTasksCount = allTasks.count { it.parent_id == null && !it.is_completed }
            val todayStr = try { LocalDate.now().toString() } catch (_: Exception) { "" }
            val upcomingEvents = allEvents.filter { ev ->
                val d = ev.start.take(10)
                todayStr.isEmpty() || d >= todayStr || (ev.end != null && ev.end.take(10) >= todayStr)
            }
            val todayEventsCount = upcomingEvents.count { it.start.startsWith(todayStr) }
            val neededShoppingCount = shoppingItems.count { it.status == "needed" }
            val stockedShoppingCount = shoppingItems.count { it.status == "stocked" }

            val views = RemoteViews(appContext.packageName, R.layout.widget_master_layout)

            // 1. Style the 4 Icon Tabs + Badges
            val goldColor = 0xFFC5A059.toInt()
            val mutedColor = 0xFF94A3B8.toInt()

            views.setInt(
                R.id.tab_master_tasks,
                "setBackgroundResource",
                if (activeTab == TAB_TASKS) R.drawable.widget_tab_active_shape else R.drawable.widget_tab_inactive_shape
            )
            views.setInt(R.id.tab_master_tasks_icon, "setColorFilter", if (activeTab == TAB_TASKS) goldColor else mutedColor)
            views.setTextColor(R.id.tab_master_tasks_badge, if (activeTab == TAB_TASKS) goldColor else mutedColor)
            views.setTextViewText(R.id.tab_master_tasks_badge, openTasksCount.toString())

            views.setInt(
                R.id.tab_master_calendar,
                "setBackgroundResource",
                if (activeTab == TAB_CALENDAR) R.drawable.widget_tab_active_shape else R.drawable.widget_tab_inactive_shape
            )
            views.setInt(R.id.tab_master_calendar_icon, "setColorFilter", if (activeTab == TAB_CALENDAR) goldColor else mutedColor)
            views.setTextColor(R.id.tab_master_calendar_badge, if (activeTab == TAB_CALENDAR) goldColor else mutedColor)
            views.setTextViewText(
                R.id.tab_master_calendar_badge,
                if (todayEventsCount > 0) todayEventsCount.toString() else upcomingEvents.size.toString()
            )

            views.setInt(
                R.id.tab_master_shopping,
                "setBackgroundResource",
                if (activeTab == TAB_SHOPPING) R.drawable.widget_tab_active_shape else R.drawable.widget_tab_inactive_shape
            )
            views.setInt(R.id.tab_master_shopping_icon, "setColorFilter", if (activeTab == TAB_SHOPPING) goldColor else mutedColor)
            views.setTextColor(R.id.tab_master_shopping_badge, if (activeTab == TAB_SHOPPING) goldColor else mutedColor)
            views.setTextViewText(R.id.tab_master_shopping_badge, neededShoppingCount.toString())

            views.setInt(
                R.id.tab_master_music,
                "setBackgroundResource",
                if (activeTab == TAB_MUSIC) R.drawable.widget_tab_active_shape else R.drawable.widget_tab_inactive_shape
            )
            views.setInt(R.id.tab_master_music_icon, "setColorFilter", if (activeTab == TAB_MUSIC) goldColor else mutedColor)

            views.setOnClickPendingIntent(
                R.id.tab_master_tasks,
                buildTabSwitchPendingIntent(appContext, appWidgetId, TAB_TASKS)
            )
            views.setOnClickPendingIntent(
                R.id.tab_master_calendar,
                buildTabSwitchPendingIntent(appContext, appWidgetId, TAB_CALENDAR)
            )
            views.setOnClickPendingIntent(
                R.id.tab_master_shopping,
                buildTabSwitchPendingIntent(appContext, appWidgetId, TAB_SHOPPING)
            )
            views.setOnClickPendingIntent(
                R.id.tab_master_music,
                buildTabSwitchPendingIntent(appContext, appWidgetId, TAB_MUSIC)
            )

            // 2. Receipt Scanner & Quick Entry Button ("Beleg erfassen & Schnelleintrag")
            val receiptIntent = Intent(appContext, QuickEntryWidgetActivity::class.java).apply {
                data = Uri.parse("funkenwerk://master/receipt/$appWidgetId")
                flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
            }
            views.setOnClickPendingIntent(
                R.id.btn_master_receipt,
                PendingIntent.getActivity(
                    appContext,
                    appWidgetId * 100 + 10,
                    receiptIntent,
                    PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
                )
            )

            // 3. Global Refresh Button
            val refreshIntent = Intent(appContext, MasterWidgetProvider::class.java).apply {
                action = ACTION_MASTER_REFRESH
                putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
                data = Uri.parse("funkenwerk://master/refresh/$appWidgetId")
            }
            views.setOnClickPendingIntent(
                R.id.btn_master_refresh,
                PendingIntent.getBroadcast(
                    appContext,
                    appWidgetId * 100 + 11,
                    refreshIntent,
                    PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
                )
            )

            // 4. Configure Subheader, '+' Button, and 'App ↗' Button per Active Tab
            when (activeTab) {
                TAB_TASKS -> {
                    views.setViewVisibility(R.id.btn_master_add, View.VISIBLE)
                    val openAppPending = buildOpenAppPendingIntent(appContext, appWidgetId, openTab = 2, openSubTab = 0)
                    views.setOnClickPendingIntent(R.id.master_subheader_open_app_btn, openAppPending)

                    if (selectedListId != null) {
                        val openInList = allTasks.count {
                            it.task_list_id == selectedListId && it.parent_id == null && !it.is_completed
                        }
                        val totalInList = allTasks.count {
                            it.task_list_id == selectedListId && it.parent_id == null
                        }
                        views.setViewVisibility(R.id.master_subheader_back_btn, View.VISIBLE)
                        views.setTextViewText(R.id.master_subheader_title, (selectedListName ?: "Aufgaben").uppercase())
                        views.setTextViewText(R.id.master_subheader_subtitle, "$openInList offen (von $totalInList) · ← Listen")
                        views.setTextViewText(R.id.master_widget_empty_view, "Keine Aufgaben in dieser Liste")

                        val backIntent = Intent(appContext, MasterWidgetProvider::class.java).apply {
                            action = ACTION_MASTER_BACK_LISTS
                            putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
                            data = Uri.parse("funkenwerk://master/back_lists/$appWidgetId")
                        }
                        val backPending = PendingIntent.getBroadcast(
                            appContext,
                            appWidgetId * 100 + 12,
                            backIntent,
                            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
                        )
                        views.setOnClickPendingIntent(R.id.master_subheader_back_btn, backPending)
                        views.setOnClickPendingIntent(R.id.master_subheader_click_area, backPending)

                        val addTaskIntent = Intent(appContext, AddTaskWidgetActivity::class.java).apply {
                            putExtra("list_id", selectedListId)
                            data = Uri.parse("funkenwerk://master/add_task/$appWidgetId/$selectedListId")
                            flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
                        }
                        views.setOnClickPendingIntent(
                            R.id.btn_master_add,
                            PendingIntent.getActivity(
                                appContext,
                                appWidgetId * 100 + 13,
                                addTaskIntent,
                                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
                            )
                        )
                    } else {
                        views.setViewVisibility(R.id.master_subheader_back_btn, View.GONE)
                        views.setTextViewText(R.id.master_subheader_title, "AUFGABEN")
                        views.setTextViewText(
                            R.id.master_subheader_subtitle,
                            "${taskLists.size} Listen · $openTasksCount offen"
                        )
                        views.setTextViewText(R.id.master_widget_empty_view, "Keine Aufgabenlisten vorhanden")
                        views.setOnClickPendingIntent(R.id.master_subheader_click_area, openAppPending)

                        val addListIntent = Intent(appContext, AddTaskListWidgetActivity::class.java).apply {
                            data = Uri.parse("funkenwerk://master/add_list/$appWidgetId")
                            flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
                        }
                        views.setOnClickPendingIntent(
                            R.id.btn_master_add,
                            PendingIntent.getActivity(
                                appContext,
                                appWidgetId * 100 + 14,
                                addListIntent,
                                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
                            )
                        )
                    }
                }

                TAB_CALENDAR -> {
                    views.setViewVisibility(R.id.master_subheader_back_btn, View.GONE)
                    views.setViewVisibility(R.id.btn_master_add, View.VISIBLE)
                    views.setTextViewText(R.id.master_subheader_title, "KALENDER")
                    views.setTextViewText(
                        R.id.master_subheader_subtitle,
                        "Heute: $todayEventsCount · Kommende: ${upcomingEvents.size}"
                    )
                    views.setTextViewText(R.id.master_widget_empty_view, "Keine anstehenden Termine")

                    val openCalendarPending = buildOpenAppPendingIntent(appContext, appWidgetId, openTab = 2, openSubTab = 1)
                    views.setOnClickPendingIntent(R.id.master_subheader_click_area, openCalendarPending)
                    views.setOnClickPendingIntent(R.id.master_subheader_open_app_btn, openCalendarPending)

                    val addEventIntent = Intent(appContext, MainActivity::class.java).apply {
                        putExtra("open_tab", 2)
                        putExtra("open_subtab", 1)
                        putExtra("create_event", true)
                        data = Uri.parse("funkenwerk://master/add_event/$appWidgetId")
                        flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
                    }
                    views.setOnClickPendingIntent(
                        R.id.btn_master_add,
                        PendingIntent.getActivity(
                            appContext,
                            appWidgetId * 100 + 15,
                            addEventIntent,
                            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
                        )
                    )
                }

                TAB_SHOPPING -> {
                    views.setViewVisibility(R.id.master_subheader_back_btn, View.GONE)
                    views.setViewVisibility(R.id.btn_master_add, View.VISIBLE)
                    views.setTextViewText(R.id.master_subheader_title, "EINKAUFSLISTE")
                    views.setTextViewText(
                        R.id.master_subheader_subtitle,
                        "$neededShoppingCount benötigt · $stockedShoppingCount vorrätig"
                    )
                    views.setTextViewText(R.id.master_widget_empty_view, "Einkaufsliste ist leer")

                    val openShoppingPending = buildOpenAppPendingIntent(appContext, appWidgetId, openTab = 2, openSubTab = 3)
                    views.setOnClickPendingIntent(R.id.master_subheader_click_area, openShoppingPending)
                    views.setOnClickPendingIntent(R.id.master_subheader_open_app_btn, openShoppingPending)

                    val addShoppingIntent = Intent(appContext, AddShoppingItemActivity::class.java).apply {
                        data = Uri.parse("funkenwerk://master/add_shopping/$appWidgetId")
                        flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
                    }
                    views.setOnClickPendingIntent(
                        R.id.btn_master_add,
                        PendingIntent.getActivity(
                            appContext,
                            appWidgetId * 100 + 16,
                            addShoppingIntent,
                            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
                        )
                    )
                }

                TAB_MUSIC -> {
                    views.setViewVisibility(R.id.master_subheader_back_btn, View.GONE)
                    views.setViewVisibility(R.id.btn_master_add, View.GONE)
                    val totalTracks = MusicPlayerManager.allTracks.value.size
                    views.setTextViewText(R.id.master_subheader_title, "MUSIK-BIBLIOTHEK")
                    views.setTextViewText(
                        R.id.master_subheader_subtitle,
                        if (totalTracks > 0) "$totalTracks Titel · Antippen für Direktstart" else "Synchronisiere Musik..."
                    )
                    views.setTextViewText(R.id.master_widget_empty_view, "Keine Songs im Musik-Cache")

                    val openMusicPending = buildOpenAppPendingIntent(appContext, appWidgetId, openTab = 2, openSubTab = 5)
                    views.setOnClickPendingIntent(R.id.master_subheader_click_area, openMusicPending)
                    views.setOnClickPendingIntent(R.id.master_subheader_open_app_btn, openMusicPending)
                }
            }

            // 5. Configure Persistent Bottom Mini-Player Bar
            val currentTrack = MusicPlayerManager.currentTrack.value
            val isPlaying = MusicPlayerManager.isPlaying.value
            val isShuffle = MusicPlayerManager.isShuffle.value
            val totalSongs = MusicPlayerManager.allTracks.value.size

            val miniTitle = if (currentTrack != null) {
                val artistPart = currentTrack.artist.takeIf { it.isNotBlank() }?.let { " · $it" } ?: ""
                "${currentTrack.title}$artistPart"
            } else if (totalSongs > 0) {
                "Musik bereit ($totalSongs Titel) · ▶ zum Starten"
            } else {
                "Funkenwerk Soundplayer bereit"
            }
            views.setTextViewText(R.id.master_mini_player_title, miniTitle)
            views.setTextViewText(R.id.btn_master_music_play_pause, if (isPlaying) "❚❚" else "▶\uFE0E")
            views.setInt(
                R.id.btn_master_music_shuffle,
                "setColorFilter",
                if (isShuffle) 0xFFC5A059.toInt() else 0x7794A3B8
            )

            if (activeTab == TAB_MUSIC) {
                views.setOnClickPendingIntent(
                    R.id.master_mini_player_click_area,
                    buildOpenAppPendingIntent(appContext, appWidgetId, openTab = 2, openSubTab = 5)
                )
            } else {
                views.setOnClickPendingIntent(
                    R.id.master_mini_player_click_area,
                    buildTabSwitchPendingIntent(appContext, appWidgetId, TAB_MUSIC)
                )
            }

            views.setOnClickPendingIntent(
                R.id.btn_master_music_play_pause,
                buildMusicActionPendingIntent(appContext, appWidgetId, MusicWidgetProvider.ACTION_PLAY_PAUSE, 20)
            )
            views.setOnClickPendingIntent(
                R.id.btn_master_music_next,
                buildMusicActionPendingIntent(appContext, appWidgetId, MusicWidgetProvider.ACTION_NEXT, 21)
            )
            views.setOnClickPendingIntent(
                R.id.btn_master_music_prev,
                buildMusicActionPendingIntent(appContext, appWidgetId, MusicWidgetProvider.ACTION_PREV, 22)
            )
            views.setOnClickPendingIntent(
                R.id.btn_master_music_shuffle,
                buildMusicActionPendingIntent(appContext, appWidgetId, MusicWidgetProvider.ACTION_SHUFFLE, 23)
            )

            // 6. Bind RemoteAdapter ONLY on full initialization (with constant URI per appWidgetId!)
            // On tab switches (isPartialUpdate = true), use partiallyUpdateAppWidget so the Launcher
            // never destroys/rebinds the ListView adapter (eliminating all flicker & lag!).
            if (isPartialUpdate) {
                appWidgetManager.partiallyUpdateAppWidget(appWidgetId, views)
            } else {
                val serviceIntent = Intent(appContext, MasterWidgetService::class.java).apply {
                    putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
                    data = Uri.parse("funkenwerk://master/service/$appWidgetId")
                }
                views.setRemoteAdapter(R.id.master_widget_list, serviceIntent)
                views.setEmptyView(R.id.master_widget_list, R.id.master_widget_empty_view)

                val clickIntent = Intent(appContext, TasksWidgetActionActivity::class.java).apply {
                    action = TasksWidgetProvider.ACTION_ITEM_CLICK
                    putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
                    data = Uri.parse("funkenwerk://master/item_click/$appWidgetId")
                    flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
                }
                val clickPendingIntent = PendingIntent.getActivity(
                    appContext,
                    appWidgetId * 100 + 30,
                    clickIntent,
                    PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_MUTABLE
                )
                views.setPendingIntentTemplate(R.id.master_widget_list, clickPendingIntent)

                appWidgetManager.updateAppWidget(appWidgetId, views)
            }
        }

        private fun buildTabSwitchPendingIntent(context: Context, appWidgetId: Int, tabIndex: Int): PendingIntent {
            val intent = Intent(context, MasterWidgetProvider::class.java).apply {
                action = ACTION_SWITCH_TAB
                putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
                putExtra(EXTRA_TAB_INDEX, tabIndex)
                data = Uri.parse("funkenwerk://master/switch_tab/$appWidgetId/$tabIndex")
            }
            return PendingIntent.getBroadcast(
                context,
                appWidgetId * 100 + tabIndex,
                intent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )
        }

        private fun buildOpenAppPendingIntent(
            context: Context,
            appWidgetId: Int,
            openTab: Int,
            openSubTab: Int
        ): PendingIntent {
            val intent = Intent(context, MainActivity::class.java).apply {
                putExtra("open_tab", openTab)
                putExtra("open_subtab", openSubTab)
                data = Uri.parse("funkenwerk://master/open_app/$appWidgetId/$openTab/$openSubTab")
                flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
            }
            return PendingIntent.getActivity(
                context,
                appWidgetId * 100 + 40 + openSubTab,
                intent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )
        }

        private fun buildMusicActionPendingIntent(
            context: Context,
            appWidgetId: Int,
            action: String,
            offset: Int
        ): PendingIntent {
            val intent = Intent(context, MasterWidgetProvider::class.java).apply {
                this.action = action
                putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
                data = Uri.parse("funkenwerk://master/music/$appWidgetId/$offset")
            }
            return PendingIntent.getBroadcast(
                context,
                appWidgetId * 100 + offset,
                intent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )
        }
    }
}
