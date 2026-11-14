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
import de.meinseelenfunke.app.data.api.ManagementTask
import de.meinseelenfunke.app.data.api.ManagementTaskList
import java.text.SimpleDateFormat
import java.util.Calendar
import java.util.Locale

sealed class TaskWidgetItem {
    data class TaskListItem(val list: ManagementTaskList, val openCount: Int) : TaskWidgetItem()
    data class ParentTask(val task: ManagementTask, val subtasks: List<ManagementTask>) : TaskWidgetItem()
    data class SubTask(val subtask: ManagementTask) : TaskWidgetItem()
    data class ActionRow(val label: String, val isAddTask: Boolean, val listId: String? = null) : TaskWidgetItem()
}

class TasksWidgetService : RemoteViewsService() {
    override fun onGetViewFactory(intent: Intent): RemoteViewsFactory {
        return TasksRemoteViewsFactory(this.applicationContext, intent)
    }
}

class TasksRemoteViewsFactory(
    private val context: Context,
    intent: Intent
) : RemoteViewsService.RemoteViewsFactory {

    private val appWidgetId: Int = intent.getIntExtra(
        AppWidgetManager.EXTRA_APPWIDGET_ID,
        AppWidgetManager.INVALID_APPWIDGET_ID
    )

    private var selectedListId: String? = null
    private var displayItems: List<TaskWidgetItem> = emptyList()

    override fun onCreate() {
        loadDataFromPrefs()
    }

    override fun onDataSetChanged() {
        loadDataFromPrefs()
    }

    private fun loadDataFromPrefs() {
        de.meinseelenfunke.app.di.ServiceLocator.ensureInitialized(context.applicationContext)
        val sharedPrefs = context.getSharedPreferences("tasks_widget_prefs", Context.MODE_PRIVATE)
        selectedListId = sharedPrefs.getString("${TasksWidgetProvider.PREF_SELECTED_LIST_ID_PREFIX}$appWidgetId", null)

        val gson = Gson()
        val listsJson = de.meinseelenfunke.app.di.ServiceLocator.getDecryptedPref(sharedPrefs, "task_lists_cache")
        val tasksJson = de.meinseelenfunke.app.di.ServiceLocator.getDecryptedPref(sharedPrefs, "tasks_cache")

        val allLists: List<ManagementTaskList> = if (listsJson != null) {
            try {
                gson.fromJson(listsJson, object : TypeToken<List<ManagementTaskList>>() {}.type)
            } catch (_: Exception) {
                emptyList()
            }
        } else {
            emptyList()
        }

        val allTasks: List<ManagementTask> = if (tasksJson != null) {
            try {
                gson.fromJson(tasksJson, object : TypeToken<List<ManagementTask>>() {}.type)
            } catch (_: Exception) {
                emptyList()
            }
        } else {
            emptyList()
        }

        val currentListId = selectedListId
        if (currentListId == null) {
            // Mode A: Lists Overview
            val items = mutableListOf<TaskWidgetItem>()
            for (list in allLists) {
                val openCount = allTasks.count {
                    it.task_list_id == list.id && it.parent_id == null && !it.is_completed
                }
                items.add(TaskWidgetItem.TaskListItem(list, openCount))
            }
            items.add(TaskWidgetItem.ActionRow("NEUE LISTE ERSTELLEN", isAddTask = false))
            displayItems = items
        } else {
            // Mode B: Tasks in Selected List
            val listTasks = allTasks.filter { it.task_list_id == currentListId }
            val parentTasks = listTasks
                .filter { it.parent_id == null }
                .sortedWith(
                    compareBy<ManagementTask> { it.is_completed }
                        .thenByDescending { priorityWeight(it.priority) }
                )

            val items = mutableListOf<TaskWidgetItem>()
            for (parent in parentTasks) {
                val subtasks = listTasks
                    .filter { it.parent_id == parent.id }
                items.add(TaskWidgetItem.ParentTask(parent, subtasks))
                for (sub in subtasks) {
                    items.add(TaskWidgetItem.SubTask(sub))
                }
            }
            items.add(
                TaskWidgetItem.ActionRow(
                    label = "NEUE AUFGABE HINZUFÜGEN",
                    isAddTask = true,
                    listId = currentListId
                )
            )
            displayItems = items
        }
    }

    private fun priorityWeight(priority: String?): Int {
        return when (priority?.lowercase(Locale.ROOT)) {
            "hoch" -> 3
            "mittel" -> 2
            else -> 1
        }
    }

    override fun onDestroy() {
        displayItems = emptyList()
    }

    override fun getCount(): Int = displayItems.size

    override fun getViewAt(position: Int): RemoteViews? {
        if (position < 0 || position >= displayItems.size) return null

        return when (val item = displayItems[position]) {
            is TaskWidgetItem.TaskListItem -> buildTaskListRow(item)
            is TaskWidgetItem.ParentTask -> buildParentTaskRow(item)
            is TaskWidgetItem.SubTask -> buildSubTaskRow(item)
            is TaskWidgetItem.ActionRow -> buildActionRow(item)
        }
    }

    private fun buildTaskListRow(item: TaskWidgetItem.TaskListItem): RemoteViews {
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

        // Click on list area -> Select list
        val selectIntent = Intent().apply {
            putExtra(TasksWidgetProvider.EXTRA_CLICK_ACTION, TasksWidgetProvider.ACTION_SELECT_LIST)
            putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
            putExtra(TasksWidgetProvider.EXTRA_LIST_ID, list.id)
            putExtra(TasksWidgetProvider.EXTRA_LIST_NAME, list.name)
        }
        views.setOnClickFillInIntent(R.id.task_list_select_area, selectIntent)
        views.setOnClickFillInIntent(R.id.task_list_item_container, selectIntent)

        // Click on edit icon -> Rename list dialog
        val renameIntent = Intent().apply {
            putExtra(TasksWidgetProvider.EXTRA_CLICK_ACTION, TasksWidgetProvider.ACTION_RENAME_LIST)
            putExtra(TasksWidgetProvider.EXTRA_LIST_ID, list.id)
        }
        views.setOnClickFillInIntent(R.id.task_list_edit_button, renameIntent)

        // Click on delete icon -> Confirm delete list dialog
        val deleteIntent = Intent().apply {
            putExtra(TasksWidgetProvider.EXTRA_CLICK_ACTION, TasksWidgetProvider.ACTION_DELETE_LIST)
            putExtra(TasksWidgetProvider.EXTRA_LIST_ID, list.id)
        }
        views.setOnClickFillInIntent(R.id.task_list_delete_button, deleteIntent)

        return views
    }

    private fun buildParentTaskRow(item: TaskWidgetItem.ParentTask): RemoteViews {
        val views = RemoteViews(context.packageName, R.layout.widget_tasks_task_item)
        val task = item.task
        val subtasks = item.subtasks

        // Always set explicit visibilities to avoid ListView view recycling artifacts
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

        // Priority dot color
        val priorityColor = when (task.priority?.lowercase(Locale.ROOT)) {
            "hoch" -> Color.parseColor("#EF4444")
            "mittel" -> Color.parseColor("#F59E0B")
            else -> Color.parseColor("#3B82F6")
        }
        views.setInt(R.id.task_priority_dot, "setColorFilter", priorityColor)

        // Metadata line (Relevant date, Subtask progress, Attachments)
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

        // 1. Checkbox click -> Toggle task
        val toggleIntent = Intent().apply {
            putExtra(TasksWidgetProvider.EXTRA_CLICK_ACTION, TasksWidgetProvider.ACTION_TOGGLE_TASK)
            putExtra(TasksWidgetProvider.EXTRA_TASK_ID, task.id)
        }
        views.setOnClickFillInIntent(R.id.task_checkbox_container, toggleIntent)

        // 2. Priority dot click -> Cycle priority
        val prioIntent = Intent().apply {
            putExtra(TasksWidgetProvider.EXTRA_CLICK_ACTION, TasksWidgetProvider.ACTION_CYCLE_PRIORITY)
            putExtra(TasksWidgetProvider.EXTRA_TASK_ID, task.id)
            putExtra(TasksWidgetProvider.EXTRA_CURRENT_PRIORITY, task.priority ?: "niedrig")
        }
        views.setOnClickFillInIntent(R.id.task_priority_container, prioIntent)

        // 3. Content & Edit button click -> Open EditTaskWidgetActivity dialog
        val editIntent = Intent().apply {
            putExtra(TasksWidgetProvider.EXTRA_CLICK_ACTION, TasksWidgetProvider.ACTION_EDIT_TASK)
            putExtra(TasksWidgetProvider.EXTRA_TASK_ID, task.id)
        }
        views.setOnClickFillInIntent(R.id.task_content_container, editIntent)
        views.setOnClickFillInIntent(R.id.task_edit_button_container, editIntent)
        views.setOnClickFillInIntent(R.id.task_item_container, editIntent)

        return views
    }

    private fun buildSubTaskRow(item: TaskWidgetItem.SubTask): RemoteViews {
        val views = RemoteViews(context.packageName, R.layout.widget_tasks_subtask_item)
        val subtask = item.subtask

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

    private fun buildActionRow(item: TaskWidgetItem.ActionRow): RemoteViews {
        val views = RemoteViews(context.packageName, R.layout.widget_tasks_action_row)
        views.setTextViewText(R.id.task_action_row_title, item.label)

        val actionIntent = Intent().apply {
            if (item.isAddTask) {
                putExtra(TasksWidgetProvider.EXTRA_CLICK_ACTION, TasksWidgetProvider.ACTION_ADD_TASK_CLICK)
                if (!item.listId.isNullOrBlank()) {
                    putExtra(TasksWidgetProvider.EXTRA_LIST_ID, item.listId)
                }
            } else {
                putExtra(TasksWidgetProvider.EXTRA_CLICK_ACTION, TasksWidgetProvider.ACTION_ADD_LIST_CLICK)
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

    override fun getLoadingView(): RemoteViews? = null

    override fun getViewTypeCount(): Int = 4

    override fun getItemId(position: Int): Long {
        if (position < 0 || position >= displayItems.size) return position.toLong()
        return when (val item = displayItems[position]) {
            is TaskWidgetItem.TaskListItem -> item.list.id.hashCode().toLong()
            is TaskWidgetItem.ParentTask -> item.task.id.hashCode().toLong()
            is TaskWidgetItem.SubTask -> item.subtask.id.hashCode().toLong()
            is TaskWidgetItem.ActionRow -> -100L - position
        }
    }

    override fun hasStableIds(): Boolean = true
}
