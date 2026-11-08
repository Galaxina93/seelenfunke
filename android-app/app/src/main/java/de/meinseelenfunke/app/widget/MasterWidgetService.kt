package de.meinseelenfunke.app.widget

import android.appwidget.AppWidgetManager
import android.content.Context
import android.content.Intent
import android.graphics.Color
import android.graphics.Paint
import android.view.View
import android.widget.RemoteViews
import android.widget.RemoteViewsService
import com.google.gson.Gson
import com.google.gson.reflect.TypeToken
import de.meinseelenfunke.app.R
import de.meinseelenfunke.app.data.api.CalendarEvent
import de.meinseelenfunke.app.data.api.ManagementShoppingItem
import de.meinseelenfunke.app.data.api.ManagementTask
import de.meinseelenfunke.app.data.api.ManagementTaskList
import de.meinseelenfunke.app.util.MusicPlayerManager
import de.meinseelenfunke.app.util.MusicTrackDto
import java.text.SimpleDateFormat
import java.time.LocalDate
import java.util.Calendar
import java.util.Date
import java.util.Locale
import java.util.TimeZone

class MasterWidgetService : RemoteViewsService() {
    override fun onGetViewFactory(intent: Intent): RemoteViewsFactory {
        return MasterWidgetViewsFactory(this.applicationContext, intent)
    }
}

sealed class MasterWidgetDisplayItem {
    // Tasks Tab Items
    data class TaskListRow(val list: ManagementTaskList, val openCount: Int) : MasterWidgetDisplayItem()
    data class ParentTaskRow(val task: ManagementTask, val subtasks: List<ManagementTask>) : MasterWidgetDisplayItem()
    data class SubTaskRow(val task: ManagementTask) : MasterWidgetDisplayItem()
    data class TaskActionRow(val label: String, val isAddList: Boolean, val listId: String? = null) : MasterWidgetDisplayItem()

    // Calendar Tab Items
    data class CalendarRow(val event: CalendarEvent) : MasterWidgetDisplayItem()

    // Shopping Tab Items
    data class ShoppingRow(val item: ManagementShoppingItem) : MasterWidgetDisplayItem()

    // Music Tab Items
    data class MusicRow(val track: MusicTrackDto, val isCurrent: Boolean, val isPlaying: Boolean) : MasterWidgetDisplayItem()
}

class MasterWidgetViewsFactory(
    private val context: Context,
    intent: Intent
) : RemoteViewsService.RemoteViewsFactory {

    private val appWidgetId: Int = intent.getIntExtra(
        AppWidgetManager.EXTRA_APPWIDGET_ID,
        AppWidgetManager.INVALID_APPWIDGET_ID
    )
    private var displayItems: List<MasterWidgetDisplayItem> = emptyList()

    override fun onCreate() {
        loadData()
    }

    override fun onDataSetChanged() {
        loadData()
    }

    private fun loadData() {
        val masterPrefs = context.getSharedPreferences(MasterWidgetProvider.PREFS_NAME, Context.MODE_PRIVATE)
        val activeTab = masterPrefs.getInt(
            "${MasterWidgetProvider.PREF_ACTIVE_TAB_PREFIX}$appWidgetId",
            MasterWidgetProvider.TAB_TASKS
        )
        val selectedListId = masterPrefs.getString(
            "${MasterWidgetProvider.PREF_SELECTED_LIST_ID_PREFIX}$appWidgetId",
            null
        )

        val gson = Gson()
        val result = mutableListOf<MasterWidgetDisplayItem>()

        when (activeTab) {
            MasterWidgetProvider.TAB_TASKS -> {
                val tasksPrefs = context.getSharedPreferences("tasks_widget_prefs", Context.MODE_PRIVATE)
                val lists: List<ManagementTaskList> = try {
                    tasksPrefs.getString("task_lists_cache", null)?.let {
                        gson.fromJson(it, object : TypeToken<List<ManagementTaskList>>() {}.type)
                    } ?: emptyList()
                } catch (_: Exception) {
                    emptyList()
                }
                val allTasks: List<ManagementTask> = try {
                    tasksPrefs.getString("tasks_cache", null)?.let {
                        gson.fromJson(it, object : TypeToken<List<ManagementTask>>() {}.type)
                    } ?: emptyList()
                } catch (_: Exception) {
                    emptyList()
                }

                if (selectedListId != null) {
                    val parentTasks = allTasks.filter {
                        it.task_list_id == selectedListId && it.parent_id == null
                    }.sortedWith(
                        compareBy<ManagementTask> { it.is_completed }
                            .thenBy { priorityOrder(it.priority) }
                    )

                    for (parent in parentTasks) {
                        val subtasks = allTasks
                            .filter { it.parent_id == parent.id }
                            .sortedBy { it.is_completed }
                        result.add(MasterWidgetDisplayItem.ParentTaskRow(parent, subtasks))
                        for (sub in subtasks) {
                            result.add(MasterWidgetDisplayItem.SubTaskRow(sub))
                        }
                    }
                    result.add(
                        MasterWidgetDisplayItem.TaskActionRow(
                            label = "Neue Aufgabe hinzufügen",
                            isAddList = false,
                            listId = selectedListId
                        )
                    )
                } else {
                    for (list in lists) {
                        val openCount = allTasks.count {
                            it.task_list_id == list.id && !it.is_completed && it.parent_id == null
                        }
                        result.add(MasterWidgetDisplayItem.TaskListRow(list, openCount))
                    }
                    result.add(
                        MasterWidgetDisplayItem.TaskActionRow(
                            label = "Neue Aufgabenliste erstellen",
                            isAddList = true
                        )
                    )
                }
            }

            MasterWidgetProvider.TAB_CALENDAR -> {
                val calPrefs = context.getSharedPreferences("calendar_widget_prefs", Context.MODE_PRIVATE)
                val allEvents: List<CalendarEvent> = try {
                    calPrefs.getString("calendar_events_cache", null)?.let {
                        gson.fromJson(it, object : TypeToken<List<CalendarEvent>>() {}.type)
                    } ?: emptyList()
                } catch (_: Exception) {
                    emptyList()
                }
                val todayStr = try { LocalDate.now().toString() } catch (_: Exception) { "" }
                val upcoming = allEvents.filter { ev ->
                    val startDate = ev.start.take(10)
                    todayStr.isEmpty() || startDate >= todayStr || (ev.end != null && ev.end.take(10) >= todayStr)
                }.sortedBy { it.start }

                for (ev in upcoming) {
                    result.add(MasterWidgetDisplayItem.CalendarRow(ev))
                }
            }

            MasterWidgetProvider.TAB_SHOPPING -> {
                val shopPrefs = context.getSharedPreferences("shopping_widget_prefs", Context.MODE_PRIVATE)
                val rawItems: List<ManagementShoppingItem> = try {
                    shopPrefs.getString("shopping_items_cache", null)?.let {
                        gson.fromJson(it, object : TypeToken<List<ManagementShoppingItem>>() {}.type)
                    } ?: emptyList()
                } catch (_: Exception) {
                    emptyList()
                }
                val sorted = rawItems.sortedWith(
                    compareBy<ManagementShoppingItem> { if (it.status == "needed") 0 else 1 }
                        .thenBy { it.name.lowercase(Locale.ROOT) }
                )
                for (item in sorted) {
                    result.add(MasterWidgetDisplayItem.ShoppingRow(item))
                }
            }

            MasterWidgetProvider.TAB_MUSIC -> {
                if (MusicPlayerManager.allTracks.value.isEmpty()) {
                    MusicPlayerManager.fetchPlaylists(context)
                }
                val tracks = MusicPlayerManager.allTracks.value
                val currentTrackId = MusicPlayerManager.currentTrack.value?.id
                val isPlaying = MusicPlayerManager.isPlaying.value
                for (tr in tracks) {
                    result.add(
                        MasterWidgetDisplayItem.MusicRow(
                            track = tr,
                            isCurrent = tr.id == currentTrackId,
                            isPlaying = tr.id == currentTrackId && isPlaying
                        )
                    )
                }
            }
        }

        displayItems = result
    }

    private fun priorityOrder(priority: String?): Int {
        return when (priority?.lowercase()) {
            "hoch", "high" -> 0
            "mittel", "medium" -> 1
            else -> 2
        }
    }

    override fun onDestroy() {
        displayItems = emptyList()
    }

    override fun getCount(): Int = displayItems.size

    override fun getViewTypeCount(): Int = 7

    override fun getItemId(position: Int): Long {
        if (position !in displayItems.indices) return position.toLong()
        return when (val item = displayItems[position]) {
            is MasterWidgetDisplayItem.TaskListRow -> ("list_" + item.list.id).hashCode().toLong()
            is MasterWidgetDisplayItem.ParentTaskRow -> ("task_" + item.task.id).hashCode().toLong()
            is MasterWidgetDisplayItem.SubTaskRow -> ("sub_" + item.task.id).hashCode().toLong()
            is MasterWidgetDisplayItem.TaskActionRow -> ("action_" + item.label).hashCode().toLong()
            is MasterWidgetDisplayItem.CalendarRow -> ("cal_" + item.event.id).hashCode().toLong()
            is MasterWidgetDisplayItem.ShoppingRow -> ("shop_" + item.item.id).hashCode().toLong()
            is MasterWidgetDisplayItem.MusicRow -> ("mus_" + item.track.id).hashCode().toLong()
        }
    }

    override fun hasStableIds(): Boolean = true

    override fun getLoadingView(): RemoteViews? = null

    override fun getViewAt(position: Int): RemoteViews? {
        if (position !in displayItems.indices) return null

        return when (val item = displayItems[position]) {
            is MasterWidgetDisplayItem.TaskListRow -> buildTaskListRow(item)
            is MasterWidgetDisplayItem.ParentTaskRow -> buildParentTaskRow(item)
            is MasterWidgetDisplayItem.SubTaskRow -> buildSubTaskRow(item)
            is MasterWidgetDisplayItem.TaskActionRow -> buildTaskActionRow(item)
            is MasterWidgetDisplayItem.CalendarRow -> buildCalendarRow(item.event)
            is MasterWidgetDisplayItem.ShoppingRow -> buildShoppingRow(item.item)
            is MasterWidgetDisplayItem.MusicRow -> buildMusicRow(item)
        }
    }

    private fun buildTaskListRow(item: MasterWidgetDisplayItem.TaskListRow): RemoteViews {
        val views = RemoteViews(context.packageName, R.layout.widget_tasks_list_item)
        val list = item.list

        views.setTextViewText(R.id.task_list_name, list.name)
        views.setTextViewText(R.id.task_list_count, item.openCount.toString())
        views.setTextColor(
            R.id.task_list_count,
            if (item.openCount > 0) Color.parseColor("#C5A059") else Color.parseColor("#64748B")
        )

        val iconRes = when (list.icon) {
            "sun" -> R.drawable.ic_sun
            "moon" -> R.drawable.ic_moon
            "star" -> R.drawable.ic_star
            "heart" -> R.drawable.ic_heart
            "bolt" -> R.drawable.ic_bolt
            "home" -> R.drawable.ic_home
            "briefcase" -> R.drawable.ic_briefcase
            "shopping-bag" -> R.drawable.ic_shopping_bag
            "trophy" -> R.drawable.ic_trophy
            "wrench" -> R.drawable.ic_wrench
            "rocket-launch" -> R.drawable.ic_rocket_launch
            "tag" -> R.drawable.ic_tag
            "flag" -> R.drawable.ic_flag
            else -> R.drawable.ic_bookmark
        }
        views.setImageViewResource(R.id.task_list_icon, iconRes)

        val selectIntent = Intent().apply {
            putExtra(TasksWidgetProvider.EXTRA_CLICK_ACTION, MasterWidgetProvider.ACTION_MASTER_SELECT_LIST)
            putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
            putExtra(TasksWidgetProvider.EXTRA_LIST_ID, list.id)
            putExtra(TasksWidgetProvider.EXTRA_LIST_NAME, list.name)
        }
        views.setOnClickFillInIntent(R.id.task_list_select_area, selectIntent)
        views.setOnClickFillInIntent(R.id.task_list_item_container, selectIntent)

        val renameIntent = Intent().apply {
            putExtra(TasksWidgetProvider.EXTRA_CLICK_ACTION, TasksWidgetProvider.ACTION_RENAME_LIST)
            putExtra(TasksWidgetProvider.EXTRA_LIST_ID, list.id)
        }
        views.setOnClickFillInIntent(R.id.task_list_edit_button, renameIntent)

        val deleteIntent = Intent().apply {
            putExtra(TasksWidgetProvider.EXTRA_CLICK_ACTION, TasksWidgetProvider.ACTION_DELETE_LIST)
            putExtra(TasksWidgetProvider.EXTRA_LIST_ID, list.id)
        }
        views.setOnClickFillInIntent(R.id.task_list_delete_button, deleteIntent)

        return views
    }

    private fun buildParentTaskRow(item: MasterWidgetDisplayItem.ParentTaskRow): RemoteViews {
        val views = RemoteViews(context.packageName, R.layout.widget_tasks_task_item)
        val task = item.task
        val subtasks = item.subtasks

        views.setViewVisibility(R.id.task_checkbox_container, View.VISIBLE)
        views.setViewVisibility(R.id.task_priority_container, View.VISIBLE)
        views.setViewVisibility(R.id.task_edit_button_container, View.VISIBLE)

        views.setTextViewText(R.id.task_title, task.title)

        if (task.is_completed) {
            views.setImageViewResource(R.id.task_checkbox_image, R.drawable.ic_checkbox_checked)
            views.setTextColor(R.id.task_title, Color.parseColor("#64748B"))
            views.setInt(
                R.id.task_title,
                "setPaintFlags",
                Paint.STRIKE_THRU_TEXT_FLAG or Paint.ANTI_ALIAS_FLAG
            )
        } else {
            views.setImageViewResource(R.id.task_checkbox_image, R.drawable.ic_checkbox_unchecked)
            views.setTextColor(R.id.task_title, Color.parseColor("#F8FAFC"))
            views.setInt(R.id.task_title, "setPaintFlags", Paint.ANTI_ALIAS_FLAG)
        }

        val priorityColor = when (task.priority?.lowercase(Locale.ROOT)) {
            "hoch" -> Color.parseColor("#EF4444")
            "mittel" -> Color.parseColor("#F59E0B")
            else -> Color.parseColor("#3B82F6")
        }
        views.setInt(R.id.task_priority_dot, "setColorFilter", priorityColor)

        val metaParts = mutableListOf<String>()
        formatRelevantDateShort(task.relevant_from)?.let { metaParts.add(it) }
        if (subtasks.isNotEmpty()) {
            val doneCount = subtasks.count { it.is_completed }
            metaParts.add("✓ $doneCount/${subtasks.size}")
        }
        val attachmentCount = task.file_paths?.size ?: 0
        if (attachmentCount > 0) {
            metaParts.add("📎 $attachmentCount")
        }

        if (metaParts.isNotEmpty()) {
            views.setViewVisibility(R.id.task_meta, View.VISIBLE)
            views.setTextViewText(R.id.task_meta, metaParts.joinToString("  ·  "))
        } else {
            views.setViewVisibility(R.id.task_meta, View.GONE)
        }

        val toggleIntent = Intent().apply {
            putExtra(TasksWidgetProvider.EXTRA_CLICK_ACTION, TasksWidgetProvider.ACTION_TOGGLE_TASK)
            putExtra(TasksWidgetProvider.EXTRA_TASK_ID, task.id)
        }
        views.setOnClickFillInIntent(R.id.task_checkbox_container, toggleIntent)

        val prioIntent = Intent().apply {
            putExtra(TasksWidgetProvider.EXTRA_CLICK_ACTION, TasksWidgetProvider.ACTION_CYCLE_PRIORITY)
            putExtra(TasksWidgetProvider.EXTRA_TASK_ID, task.id)
            putExtra(TasksWidgetProvider.EXTRA_CURRENT_PRIORITY, task.priority ?: "niedrig")
        }
        views.setOnClickFillInIntent(R.id.task_priority_container, prioIntent)

        val editIntent = Intent().apply {
            putExtra(TasksWidgetProvider.EXTRA_CLICK_ACTION, TasksWidgetProvider.ACTION_EDIT_TASK)
            putExtra(TasksWidgetProvider.EXTRA_TASK_ID, task.id)
        }
        views.setOnClickFillInIntent(R.id.task_content_container, editIntent)
        views.setOnClickFillInIntent(R.id.task_edit_button_container, editIntent)
        views.setOnClickFillInIntent(R.id.task_item_container, editIntent)

        return views
    }

    private fun buildSubTaskRow(item: MasterWidgetDisplayItem.SubTaskRow): RemoteViews {
        val views = RemoteViews(context.packageName, R.layout.widget_tasks_subtask_item)
        val subtask = item.task

        views.setTextViewText(R.id.subtask_title, subtask.title)

        if (subtask.is_completed) {
            views.setImageViewResource(R.id.subtask_checkbox_image, R.drawable.ic_checkbox_checked)
            views.setTextColor(R.id.subtask_title, Color.parseColor("#64748B"))
            views.setInt(
                R.id.subtask_title,
                "setPaintFlags",
                Paint.STRIKE_THRU_TEXT_FLAG or Paint.ANTI_ALIAS_FLAG
            )
        } else {
            views.setImageViewResource(R.id.subtask_checkbox_image, R.drawable.ic_checkbox_unchecked)
            views.setTextColor(R.id.subtask_title, Color.parseColor("#CBD5E1"))
            views.setInt(R.id.subtask_title, "setPaintFlags", Paint.ANTI_ALIAS_FLAG)
        }

        val toggleIntent = Intent().apply {
            putExtra(TasksWidgetProvider.EXTRA_CLICK_ACTION, TasksWidgetProvider.ACTION_TOGGLE_TASK)
            putExtra(TasksWidgetProvider.EXTRA_TASK_ID, subtask.id)
        }
        views.setOnClickFillInIntent(R.id.subtask_checkbox_container, toggleIntent)
        views.setOnClickFillInIntent(R.id.subtask_item_container, toggleIntent)

        return views
    }

    private fun buildTaskActionRow(item: MasterWidgetDisplayItem.TaskActionRow): RemoteViews {
        val views = RemoteViews(context.packageName, R.layout.widget_tasks_action_row)
        views.setTextViewText(R.id.task_action_row_title, item.label)

        val actionIntent = Intent().apply {
            if (item.isAddList) {
                putExtra(TasksWidgetProvider.EXTRA_CLICK_ACTION, TasksWidgetProvider.ACTION_ADD_LIST_CLICK)
            } else {
                putExtra(TasksWidgetProvider.EXTRA_CLICK_ACTION, TasksWidgetProvider.ACTION_ADD_TASK_CLICK)
                if (!item.listId.isNullOrBlank()) {
                    putExtra(TasksWidgetProvider.EXTRA_LIST_ID, item.listId)
                }
            }
        }
        views.setOnClickFillInIntent(R.id.task_action_row_container, actionIntent)
        return views
    }

    private fun formatRelevantDateShort(dateStr: String?): String? {
        val localDateStr = de.meinseelenfunke.app.util.DateUtils.parseUtcToLocalDateString(dateStr) ?: return null
        return try {
            val sdf = SimpleDateFormat("yyyy-MM-dd", Locale.US)
            val date = sdf.parse(localDateStr) ?: return null

            val todayCal = Calendar.getInstance().apply {
                set(Calendar.HOUR_OF_DAY, 0)
                set(Calendar.MINUTE, 0)
                set(Calendar.SECOND, 0)
                set(Calendar.MILLISECOND, 0)
            }
            val tomorrowCal = (todayCal.clone() as Calendar).apply {
                add(Calendar.DAY_OF_YEAR, 1)
            }
            val targetCal = Calendar.getInstance().apply {
                time = date
                set(Calendar.HOUR_OF_DAY, 0)
                set(Calendar.MINUTE, 0)
                set(Calendar.SECOND, 0)
                set(Calendar.MILLISECOND, 0)
            }

            when {
                targetCal.timeInMillis == todayCal.timeInMillis -> "📅 Heute"
                targetCal.timeInMillis == tomorrowCal.timeInMillis -> "📅 Morgen"
                else -> "📅 " + SimpleDateFormat("dd.MM.", Locale.GERMANY).format(date)
            }
        } catch (_: Exception) {
            null
        }
    }

    private fun buildCalendarRow(event: CalendarEvent): RemoteViews {
        val views = RemoteViews(context.packageName, R.layout.widget_item_layout)
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
            putExtra(TasksWidgetProvider.EXTRA_CLICK_ACTION, MasterWidgetProvider.ACTION_OPEN_CALENDAR_EVENT)
            putExtra(MasterWidgetProvider.EXTRA_EVENT_DATE, datePrefix)
        }
        views.setOnClickFillInIntent(R.id.widget_item_root, fillInIntent)
        return views
    }

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

    private fun buildShoppingRow(item: ManagementShoppingItem): RemoteViews {
        val views = RemoteViews(context.packageName, R.layout.widget_shopping_item_layout)
        val isStocked = item.status == "stocked"

        views.setTextViewText(R.id.item_name, item.name)
        if (isStocked) {
            views.setImageViewResource(R.id.item_checkbox, R.drawable.ic_checkbox_checked)
            views.setTextColor(R.id.item_name, Color.parseColor("#64748B"))
            views.setInt(
                R.id.item_name,
                "setPaintFlags",
                Paint.STRIKE_THRU_TEXT_FLAG or Paint.ANTI_ALIAS_FLAG
            )
        } else {
            views.setImageViewResource(R.id.item_checkbox, R.drawable.ic_checkbox_unchecked)
            views.setTextColor(R.id.item_name, Color.parseColor("#F8FAFC"))
            views.setInt(R.id.item_name, "setPaintFlags", Paint.ANTI_ALIAS_FLAG)
        }

        if (item.quantity > 1) {
            views.setViewVisibility(R.id.item_quantity_badge, View.VISIBLE)
            views.setTextViewText(R.id.item_quantity_badge, "${item.quantity}x")
        } else {
            views.setViewVisibility(R.id.item_quantity_badge, View.GONE)
        }

        val categoryColor = when (item.category?.name?.lowercase(Locale.ROOT)) {
            "gemüse", "obst", "frisch" -> 0xFF34D399.toInt()
            "haushalt" -> 0xFFEC4899.toInt()
            "drogerie" -> 0xFFA855F7.toInt()
            "getränke" -> 0xFF3B82F6.toInt()
            "backen", "süßwaren" -> 0xFFF59E0B.toInt()
            null -> if (isStocked) 0xFF334155.toInt() else 0xFFC5A059.toInt()
            else -> 0xFFC5A059.toInt()
        }
        views.setViewVisibility(R.id.item_category_bar, View.VISIBLE)
        views.setInt(R.id.item_category_bar, "setBackgroundColor", categoryColor)

        val toggleFillIn = Intent().apply {
            putExtra(TasksWidgetProvider.EXTRA_CLICK_ACTION, ShoppingListWidgetProvider.ACTION_TOGGLE_ITEM)
            putExtra("item_id", item.id)
        }
        views.setOnClickFillInIntent(R.id.item_toggle_area, toggleFillIn)
        views.setOnClickFillInIntent(R.id.widget_shopping_item_root, toggleFillIn)

        val deleteFillIn = Intent().apply {
            putExtra(TasksWidgetProvider.EXTRA_CLICK_ACTION, ShoppingListWidgetProvider.ACTION_DELETE_ITEM)
            putExtra("item_id", item.id)
        }
        views.setOnClickFillInIntent(R.id.item_delete_button, deleteFillIn)

        return views
    }

    private fun buildMusicRow(item: MasterWidgetDisplayItem.MusicRow): RemoteViews {
        val views = RemoteViews(context.packageName, R.layout.widget_master_music_item)
        val track = item.track

        views.setTextViewText(R.id.widget_master_music_row_title, track.title)
        views.setTextViewText(
            R.id.widget_master_music_row_artist,
            track.artist.ifBlank { "Funkenwerk Audio" }
        )
        views.setTextViewText(R.id.widget_master_music_row_duration, track.formattedDuration)

        if (item.isCurrent) {
            views.setTextViewText(
                R.id.widget_master_music_row_indicator,
                if (item.isPlaying) "❚❚" else "▶\uFE0E"
            )
            views.setTextColor(R.id.widget_master_music_row_title, Color.parseColor("#C5A059"))
        } else {
            views.setTextViewText(R.id.widget_master_music_row_indicator, "▶\uFE0E")
            views.setTextColor(R.id.widget_master_music_row_title, Color.parseColor("#F8FAFC"))
        }

        val playIntent = Intent().apply {
            putExtra(TasksWidgetProvider.EXTRA_CLICK_ACTION, MasterWidgetProvider.ACTION_PLAY_TRACK)
            putExtra(MasterWidgetProvider.EXTRA_TRACK_ID, track.id)
        }
        views.setOnClickFillInIntent(R.id.widget_master_music_row_container, playIntent)

        return views
    }
}
