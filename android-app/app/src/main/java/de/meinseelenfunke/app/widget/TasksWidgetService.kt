package de.meinseelenfunke.app.widget

import android.appwidget.AppWidgetManager
import android.content.Context
import android.content.Intent
import android.text.SpannableString
import android.text.Spanned
import android.text.style.StrikethroughSpan
import android.view.View
import android.widget.RemoteViews
import android.widget.RemoteViewsService
import com.google.gson.Gson
import com.google.gson.reflect.TypeToken
import de.meinseelenfunke.app.R
import de.meinseelenfunke.app.data.api.ManagementTask
import de.meinseelenfunke.app.data.api.ManagementTaskList
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale
import java.util.TimeZone

class TasksWidgetService : RemoteViewsService() {
    override fun onGetViewFactory(intent: Intent): RemoteViewsFactory {
        return TasksWidgetViewsFactory(this.applicationContext, intent)
    }
}

sealed class TasksWidgetItem {
    data class TaskList(val list: ManagementTaskList, val openCount: Int) : TasksWidgetItem()
    data class ParentTask(val task: ManagementTask) : TasksWidgetItem()
    data class Subtask(val task: ManagementTask) : TasksWidgetItem()
    data class EditControls(val task: ManagementTask) : TasksWidgetItem()
    data class AddNewListDummy(val text: String = "+ NEUE LISTE HINZUFÜGEN") : TasksWidgetItem()
    data class AddNewTaskDummy(val listId: String, val text: String = "+ NEUE AUFGABE HINZUFÜGEN") : TasksWidgetItem()
    data class AddListControls(val tempName: String) : TasksWidgetItem()
    data class AddTaskControls(val tempTitle: String, val priority: String, val relevantFrom: String?) : TasksWidgetItem()
    data class AddSubtaskControls(val parentTaskId: String, val tempTitle: String) : TasksWidgetItem()
}

class TasksWidgetViewsFactory(
    private val context: Context,
    intent: Intent
) : RemoteViewsService.RemoteViewsFactory {

    private val appWidgetId: Int = run {
        var id = intent.getIntExtra(
            AppWidgetManager.EXTRA_APPWIDGET_ID,
            AppWidgetManager.INVALID_APPWIDGET_ID
        )
        if (id == AppWidgetManager.INVALID_APPWIDGET_ID) {
            val data = intent.data
            if (data != null) {
                try {
                    val idParam = data.getQueryParameter("appWidgetId")
                    if (idParam != null) {
                        id = idParam.toInt()
                    }
                } catch (e: Exception) {}
            }
        }
        id
    }

    private var itemsList: List<TasksWidgetItem> = emptyList()

    private fun getIconDrawableRes(iconName: String?): Int {
        return when (iconName?.lowercase(Locale.ROOT)) {
            "bookmark" -> R.drawable.ic_bookmark
            "star" -> R.drawable.ic_star
            "heart" -> R.drawable.ic_heart
            "bolt" -> R.drawable.ic_bolt
            "home" -> R.drawable.ic_home
            "briefcase" -> R.drawable.ic_briefcase
            "shopping-bag" -> R.drawable.ic_shopping_bag
            "trophy" -> R.drawable.ic_trophy
            "sun" -> R.drawable.ic_sun
            "moon" -> R.drawable.ic_moon
            "wrench" -> R.drawable.ic_wrench
            "rocket-launch" -> R.drawable.ic_rocket_launch
            "tag" -> R.drawable.ic_tag
            "flag" -> R.drawable.ic_flag
            else -> android.R.drawable.ic_menu_agenda
        }
    }

    override fun onCreate() {}

    override fun onDataSetChanged() {
        val sharedPrefs = context.getSharedPreferences("tasks_widget_prefs", Context.MODE_PRIVATE)
        val selectedListId = sharedPrefs.getString("widget_tasks_selected_list_id_$appWidgetId", null)
        val editingTaskId = sharedPrefs.getString("widget_tasks_editing_task_id_$appWidgetId", null)

        val listsJson = sharedPrefs.getString("task_lists_cache", null)
        val tasksJson = sharedPrefs.getString("tasks_cache", null)

        val gson = Gson()
        val allLists: List<ManagementTaskList> = if (listsJson != null) {
            try {
                val type = object : TypeToken<List<ManagementTaskList>>() {}.type
                gson.fromJson(listsJson, type)
            } catch (e: Exception) {
                emptyList()
            }
        } else {
            emptyList()
        }

        val allTasks: List<ManagementTask> = if (tasksJson != null) {
            try {
                val type = object : TypeToken<List<ManagementTask>>() {}.type
                gson.fromJson(tasksJson, type)
            } catch (e: Exception) {
                emptyList()
            }
        } else {
            emptyList()
        }

        if (selectedListId == null) {
            // Lists Overview mode
            val isAddingList = sharedPrefs.getBoolean("widget_tasks_adding_list_$appWidgetId", false)
            val tempName = sharedPrefs.getString("widget_tasks_adding_list_name_$appWidgetId", "") ?: ""

            val listItems = allLists.map { list ->
                val openCount = allTasks.count { it.task_list_id == list.id && !it.is_completed }
                TasksWidgetItem.TaskList(list, openCount)
            }
            val flattened = mutableListOf<TasksWidgetItem>()
            if (isAddingList) {
                flattened.add(TasksWidgetItem.AddListControls(tempName))
            }
            flattened.addAll(listItems)
            flattened.add(TasksWidgetItem.AddNewListDummy())
            itemsList = flattened
        } else if (editingTaskId != null) {
            // Edit Mode: show edit controls card first, then its subtasks
            val parentTask = allTasks.find { it.id == editingTaskId }
            if (parentTask != null) {
                val flattened = mutableListOf<TasksWidgetItem>()
                flattened.add(TasksWidgetItem.EditControls(parentTask))
                
                val addingSubtaskParent = sharedPrefs.getString("widget_tasks_adding_subtask_parent_$appWidgetId", null)
                val tempSubtaskTitle = sharedPrefs.getString("widget_tasks_adding_subtask_title_$appWidgetId", "") ?: ""
                
                if (addingSubtaskParent == editingTaskId) {
                    flattened.add(TasksWidgetItem.AddSubtaskControls(editingTaskId, tempSubtaskTitle))
                }

                // Find all subtasks belonging to this task
                val children = allTasks.filter { it.parent_id == parentTask.id }
                    .sortedWith(
                        compareBy<ManagementTask> { it.is_completed }
                            .thenBy { it.title.lowercase(Locale.ROOT) }
                    )
                for (child in children) {
                    flattened.add(TasksWidgetItem.Subtask(child))
                }
                itemsList = flattened
            } else {
                itemsList = emptyList()
            }
        } else {
            // Tasks View (Inline) mode
            val isAddingTask = sharedPrefs.getBoolean("widget_tasks_adding_task_$appWidgetId", false)
            val tempTitle = sharedPrefs.getString("widget_tasks_adding_task_title_$appWidgetId", "") ?: ""
            val tempPrio = sharedPrefs.getString("widget_tasks_adding_task_prio_$appWidgetId", "niedrig") ?: "niedrig"
            val tempDate = sharedPrefs.getString("widget_tasks_adding_task_date_$appWidgetId", null)

            val filteredTasks = allTasks.filter { it.task_list_id == selectedListId }
            val rootTasks = filteredTasks.filter { it.parent_id == null }
            val subtasks = filteredTasks.filter { it.parent_id != null }

            // Sort root tasks: incomplete first, then by priority (hoch, mittel, niedrig), then by title
            val sortedRootTasks = rootTasks.sortedWith(
                compareBy<ManagementTask> { it.is_completed }
                    .thenBy {
                        when (it.priority?.lowercase(Locale.ROOT)) {
                            "hoch" -> 0
                            "mittel" -> 1
                            else -> 2
                        }
                    }
                    .thenBy { it.title.lowercase(Locale.ROOT) }
            )

            val flattened = mutableListOf<TasksWidgetItem>()
            if (isAddingTask) {
                flattened.add(TasksWidgetItem.AddTaskControls(tempTitle, tempPrio, tempDate))
            }
            for (rootTask in sortedRootTasks) {
                flattened.add(TasksWidgetItem.ParentTask(rootTask))

                // Find all subtasks belonging to this root task and sort them
                val children = subtasks.filter { it.parent_id == rootTask.id }
                    .sortedWith(
                        compareBy<ManagementTask> { it.is_completed }
                            .thenBy { it.title.lowercase(Locale.ROOT) }
                    )

                for (child in children) {
                    flattened.add(TasksWidgetItem.Subtask(child))
                }
            }
            flattened.add(TasksWidgetItem.AddNewTaskDummy(selectedListId))
            itemsList = flattened
        }
    }

    override fun onDestroy() {
        itemsList = emptyList()
    }

    override fun getCount(): Int = itemsList.size

    override fun getViewAt(position: Int): RemoteViews {
        val item = itemsList[position]

        return when (item) {
            is TasksWidgetItem.TaskList -> {
                val views = RemoteViews(context.packageName, R.layout.widget_tasks_list_item)
                views.setTextViewText(R.id.list_name, item.list.name)
                views.setViewVisibility(R.id.btn_delete_list, View.VISIBLE)
                views.setImageViewResource(R.id.list_icon, getIconDrawableRes(item.list.icon))

                // Click action to open list
                val fillInIntent = Intent().apply {
                    action = TasksWidgetProvider.ACTION_SELECT_LIST
                    putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
                    putExtra("list_id", item.list.id)
                    putExtra("list_name", item.list.name)
                }
                views.setOnClickFillInIntent(R.id.widget_tasks_list_item_root, fillInIntent)

                // Click action to delete list
                val deleteIntent = Intent().apply {
                    action = TasksWidgetProvider.ACTION_DELETE_LIST
                    putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
                    putExtra("list_id", item.list.id)
                }
                views.setOnClickFillInIntent(R.id.btn_delete_list, deleteIntent)
                views
            }
            is TasksWidgetItem.AddNewListDummy -> {
                val views = RemoteViews(context.packageName, R.layout.widget_tasks_list_item)
                views.setTextViewText(R.id.list_name, item.text)
                views.setTextColor(R.id.list_name, 0xFFC5A059.toInt()) // Gold
                views.setViewVisibility(R.id.btn_delete_list, View.GONE)
                views.setImageViewResource(R.id.list_icon, android.R.drawable.ic_input_add)

                // Click action to start inline adding lists
                val fillInIntent = Intent().apply {
                    action = TasksWidgetProvider.ACTION_START_ADD_LIST
                    putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
                }
                views.setOnClickFillInIntent(R.id.widget_tasks_list_item_root, fillInIntent)
                views
            }
            is TasksWidgetItem.AddListControls -> {
                val views = RemoteViews(context.packageName, R.layout.widget_tasks_add_list_control)
                
                val displayName = if (item.tempName.isEmpty()) "[Tippen zum Schreiben]" else item.tempName
                views.setTextViewText(R.id.btn_add_list_name, displayName)
                views.setTextColor(R.id.btn_add_list_name, if (item.tempName.isEmpty()) 0xFF94A3B8.toInt() else 0xFFF8FAFC.toInt())

                // Click action to open name editor activity
                val nameEditorIntent = Intent().apply {
                    action = TasksWidgetProvider.ACTION_OPEN_TEXT_INPUT
                    putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
                    putExtra("title", "Name der Liste")
                    putExtra("pref_key", "widget_tasks_adding_list_name_$appWidgetId")
                }
                views.setOnClickFillInIntent(R.id.btn_add_list_name, nameEditorIntent)

                // Click action for Abbrechen (Cancel)
                val cancelIntent = Intent().apply {
                    action = TasksWidgetProvider.ACTION_CANCEL_ADD_LIST
                    putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
                }
                views.setOnClickFillInIntent(R.id.btn_add_list_cancel, cancelIntent)

                // Click action for Speichern (Save)
                val saveIntent = Intent().apply {
                    action = TasksWidgetProvider.ACTION_SAVE_ADD_LIST
                    putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
                }
                views.setOnClickFillInIntent(R.id.btn_add_list_save, saveIntent)
                views
            }
            is TasksWidgetItem.ParentTask -> {
                val views = RemoteViews(context.packageName, R.layout.widget_tasks_task_item)
                val task = item.task

                views.setViewVisibility(R.id.task_checkbox_container, View.VISIBLE)
                views.setViewVisibility(R.id.task_meta, View.VISIBLE)
                views.setViewVisibility(R.id.task_priority_container, View.VISIBLE)

                // Set Title
                if (task.is_completed) {
                    val spannable = SpannableString(task.title)
                    spannable.setSpan(StrikethroughSpan(), 0, task.title.length, Spanned.SPAN_EXCLUSIVE_EXCLUSIVE)
                    views.setTextViewText(R.id.task_title, spannable)
                    views.setTextColor(R.id.task_title, 0xFF94A3B8.toInt()) // slate-400
                } else {
                    views.setTextViewText(R.id.task_title, task.title)
                    views.setTextColor(R.id.task_title, 0xFFF8FAFC.toInt()) // slate-50
                }

                // Set Checkbox icon
                if (task.is_completed) {
                    views.setImageViewResource(R.id.task_checkbox, R.drawable.ic_checkbox_checked)
                } else {
                    views.setImageViewResource(R.id.task_checkbox, R.drawable.ic_checkbox_unchecked)
                }

                // Click pending intent on checkbox container to toggle
                val toggleIntent = Intent().apply {
                    action = TasksWidgetProvider.ACTION_TOGGLE_TASK
                    putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
                    putExtra("task_id", task.id)
                }
                views.setOnClickFillInIntent(R.id.task_checkbox_container, toggleIntent)

                // Set Metadata text (Priority, Offen seit, Relevant ab)
                val metaBuilder = StringBuilder()
                val priorityLabel = when (task.priority?.lowercase(Locale.ROOT)) {
                    "hoch" -> "HOCH"
                    "mittel" -> "MITTEL"
                    else -> "NIEDRIG"
                }
                metaBuilder.append(priorityLabel)

                val offen = formatOffenSeit(task.created_at)
                if (offen.isNotEmpty()) {
                    metaBuilder.append(" • ").append(offen)
                }

                val relevant = formatRelevantAb(task.relevant_from)
                if (relevant.isNotEmpty()) {
                    metaBuilder.append(" • ").append(relevant)
                }
                views.setTextViewText(R.id.task_meta, metaBuilder.toString())

                // Set Priority indicator bar color
                val priorityColor = when (task.priority?.lowercase(Locale.ROOT)) {
                    "hoch" -> 0xFFEF4444.toInt() // Red-500
                    "mittel" -> 0xFFF97316.toInt() // Orange-500
                    else -> 0xFF64748B.toInt() // Slate-500
                }
                views.setInt(R.id.task_priority_bar, "setBackgroundColor", priorityColor)

                // Tapping priority container cycles priority directly
                val cycleIntent = Intent().apply {
                    action = TasksWidgetProvider.ACTION_CYCLE_PRIORITY
                    putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
                    putExtra("task_id", task.id)
                }
                views.setOnClickFillInIntent(R.id.task_priority_container, cycleIntent)

                // Tapping metadata cycles relevant date directly
                val cycleDateIntent = Intent().apply {
                    action = TasksWidgetProvider.ACTION_CYCLE_DATE
                    putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
                    putExtra("task_id", task.id)
                }
                views.setOnClickFillInIntent(R.id.task_meta, cycleDateIntent)

                // Clicking the text/body or gear icon opens the edit dialog
                val editIntent = Intent().apply {
                    action = TasksWidgetProvider.ACTION_EDIT_TASK
                    putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
                    putExtra("task_id", task.id)
                }
                views.setOnClickFillInIntent(R.id.widget_tasks_item_root, editIntent)
                views.setOnClickFillInIntent(R.id.task_edit_button_container, editIntent)
                views
            }
            is TasksWidgetItem.AddNewTaskDummy -> {
                val views = RemoteViews(context.packageName, R.layout.widget_tasks_task_item)
                views.setTextViewText(R.id.task_title, item.text)
                views.setTextColor(R.id.task_title, 0xFFC5A059.toInt()) // Gold
                views.setViewVisibility(R.id.task_checkbox_container, View.GONE)
                views.setViewVisibility(R.id.task_meta, View.GONE)
                views.setViewVisibility(R.id.task_priority_container, View.GONE)
                views.setInt(R.id.task_priority_bar, "setBackgroundColor", android.graphics.Color.TRANSPARENT)

                val fillInIntent = Intent().apply {
                    action = TasksWidgetProvider.ACTION_START_ADD_TASK
                    putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
                }
                views.setOnClickFillInIntent(R.id.widget_tasks_item_root, fillInIntent)
                views
            }
            is TasksWidgetItem.AddTaskControls -> {
                val views = RemoteViews(context.packageName, R.layout.widget_tasks_add_task_control)

                val displayTitle = if (item.tempTitle.isEmpty()) "[Tippen zum Schreiben]" else item.tempTitle
                views.setTextViewText(R.id.btn_add_task_title, displayTitle)
                views.setTextColor(R.id.btn_add_task_title, if (item.tempTitle.isEmpty()) 0xFF94A3B8.toInt() else 0xFFF8FAFC.toInt())

                // Highlight Selected Priority
                val currentPrio = item.priority.lowercase(Locale.ROOT)

                // Hoch button
                if (currentPrio == "hoch") {
                    views.setInt(R.id.btn_add_task_prio_hoch, "setBackgroundColor", 0xFFEF4444.toInt()) // Solid Red
                    views.setInt(R.id.btn_add_task_prio_hoch, "setTextColor", 0xFFFFFFFF.toInt()) // White
                } else {
                    views.setInt(R.id.btn_add_task_prio_hoch, "setBackgroundColor", 0x1AEF4444.toInt()) // Translucent Red
                    views.setInt(R.id.btn_add_task_prio_hoch, "setTextColor", 0xFFEF4444.toInt()) // Red
                }

                // Mittel button
                if (currentPrio == "mittel") {
                    views.setInt(R.id.btn_add_task_prio_mittel, "setBackgroundColor", 0xFFF97316.toInt()) // Solid Orange
                    views.setInt(R.id.btn_add_task_prio_mittel, "setTextColor", 0xFFFFFFFF.toInt()) // White
                } else {
                    views.setInt(R.id.btn_add_task_prio_mittel, "setBackgroundColor", 0x1AF97316.toInt()) // Translucent Orange
                    views.setInt(R.id.btn_add_task_prio_mittel, "setTextColor", 0xFFF97316.toInt()) // Orange
                }

                // Niedrig button
                if (currentPrio == "niedrig") {
                    views.setInt(R.id.btn_add_task_prio_niedrig, "setBackgroundColor", 0xFF64748B.toInt()) // Solid Slate
                    views.setInt(R.id.btn_add_task_prio_niedrig, "setTextColor", 0xFFFFFFFF.toInt()) // White
                } else {
                    views.setInt(R.id.btn_add_task_prio_niedrig, "setBackgroundColor", 0x1A64748B.toInt()) // Translucent Slate
                    views.setInt(R.id.btn_add_task_prio_niedrig, "setTextColor", 0xFF64748B.toInt()) // Slate
                }

                // Date Selection
                val formatterInput = SimpleDateFormat("yyyy-MM-dd", Locale.US)
                val dateText = if (!item.relevantFrom.isNullOrBlank()) {
                    try {
                        val parsed = formatterInput.parse(item.relevantFrom)
                        if (parsed != null) {
                            val formatterOutput = SimpleDateFormat("dd.MM.yyyy", Locale.GERMANY)
                            formatterOutput.format(parsed)
                        } else {
                            item.relevantFrom
                        }
                    } catch (e: Exception) {
                        item.relevantFrom
                    }
                } else {
                    "Planen"
                }
                views.setTextViewText(R.id.btn_add_task_date, dateText)

                // Hide clear date trash icon when no date is set
                if (item.relevantFrom.isNullOrBlank()) {
                    views.setViewVisibility(R.id.btn_add_task_clear_date, View.GONE)
                } else {
                    views.setViewVisibility(R.id.btn_add_task_clear_date, View.VISIBLE)
                }

                // Click action to open title editor activity
                val titleEditorIntent = Intent().apply {
                    action = TasksWidgetProvider.ACTION_OPEN_TEXT_INPUT
                    putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
                    putExtra("title", "Titel der Aufgabe")
                    putExtra("pref_key", "widget_tasks_adding_task_title_$appWidgetId")
                }
                views.setOnClickFillInIntent(R.id.btn_add_task_title, titleEditorIntent)

                // Click actions to change priority inline
                val prioHochIntent = Intent().apply {
                    action = TasksWidgetProvider.ACTION_ADD_TASK_SET_PRIO
                    putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
                    putExtra("priority", "hoch")
                }
                views.setOnClickFillInIntent(R.id.btn_add_task_prio_hoch, prioHochIntent)

                val prioMittelIntent = Intent().apply {
                    action = TasksWidgetProvider.ACTION_ADD_TASK_SET_PRIO
                    putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
                    putExtra("priority", "mittel")
                }
                views.setOnClickFillInIntent(R.id.btn_add_task_prio_mittel, prioMittelIntent)

                val prioNiedrigIntent = Intent().apply {
                    action = TasksWidgetProvider.ACTION_ADD_TASK_SET_PRIO
                    putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
                    putExtra("priority", "niedrig")
                }
                views.setOnClickFillInIntent(R.id.btn_add_task_prio_niedrig, prioNiedrigIntent)

                // Click action to cycle date inline
                val cycleDateIntent = Intent().apply {
                    action = TasksWidgetProvider.ACTION_ADD_TASK_CYCLE_DATE
                    putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
                }
                views.setOnClickFillInIntent(R.id.btn_add_task_date, cycleDateIntent)

                // Click action to clear date inline
                val clearDateIntent = Intent().apply {
                    action = TasksWidgetProvider.ACTION_ADD_TASK_CLEAR_DATE
                    putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
                }
                views.setOnClickFillInIntent(R.id.btn_add_task_clear_date, clearDateIntent)

                // Click action for Abbrechen (Cancel)
                val cancelIntent = Intent().apply {
                    action = TasksWidgetProvider.ACTION_CANCEL_ADD_TASK
                    putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
                }
                views.setOnClickFillInIntent(R.id.btn_add_task_cancel, cancelIntent)

                // Click action for Speichern (Save)
                val saveIntent = Intent().apply {
                    action = TasksWidgetProvider.ACTION_SAVE_ADD_TASK
                    putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
                }
                views.setOnClickFillInIntent(R.id.btn_add_task_save, saveIntent)
                views
            }
            is TasksWidgetItem.Subtask -> {
                val views = RemoteViews(context.packageName, R.layout.widget_tasks_subtask_item)
                val subtask = item.task

                // Set Title
                if (subtask.is_completed) {
                    val spannable = SpannableString(subtask.title)
                    spannable.setSpan(StrikethroughSpan(), 0, subtask.title.length, Spanned.SPAN_EXCLUSIVE_EXCLUSIVE)
                    views.setTextViewText(R.id.subtask_title, spannable)
                    views.setTextColor(R.id.subtask_title, 0xFF64748B.toInt()) // slate-500
                } else {
                    views.setTextViewText(R.id.subtask_title, subtask.title)
                    views.setTextColor(R.id.subtask_title, 0xFFE2E8F0.toInt()) // slate-200
                }

                // Set Checkbox icon
                if (subtask.is_completed) {
                    views.setImageViewResource(R.id.subtask_checkbox, R.drawable.ic_checkbox_checked)
                } else {
                    views.setImageViewResource(R.id.subtask_checkbox, R.drawable.ic_checkbox_unchecked)
                }

                // Click pending intent on checkbox container to toggle subtask
                val toggleIntent = Intent().apply {
                    action = TasksWidgetProvider.ACTION_TOGGLE_TASK
                    putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
                    putExtra("task_id", subtask.id)
                }
                views.setOnClickFillInIntent(R.id.subtask_checkbox_container, toggleIntent)

                // Clicking subtask row body or gear icon opens parent task's edit dialog
                val editParentIntent = Intent().apply {
                    action = TasksWidgetProvider.ACTION_EDIT_TASK
                    putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
                    putExtra("task_id", subtask.parent_id ?: subtask.id)
                }
                views.setOnClickFillInIntent(R.id.widget_subtask_item_root, editParentIntent)
                views.setOnClickFillInIntent(R.id.subtask_edit_button_container, editParentIntent)
                views
            }
            is TasksWidgetItem.EditControls -> {
                val views = RemoteViews(context.packageName, R.layout.widget_tasks_edit_control)
                val task = item.task

                // Set Title
                views.setTextViewText(R.id.edit_task_title, task.title)

                // Highlight Selected Priority
                val currentPrio = task.priority?.lowercase(Locale.ROOT) ?: "niedrig"

                // Hoch button
                if (currentPrio == "hoch") {
                    views.setInt(R.id.btn_prio_hoch, "setBackgroundColor", 0xFFEF4444.toInt()) // Solid Red
                    views.setInt(R.id.btn_prio_hoch, "setTextColor", 0xFFFFFFFF.toInt()) // White
                } else {
                    views.setInt(R.id.btn_prio_hoch, "setBackgroundColor", 0x1AEF4444.toInt()) // Translucent Red
                    views.setInt(R.id.btn_prio_hoch, "setTextColor", 0xFFEF4444.toInt()) // Red
                }

                // Mittel button
                if (currentPrio == "mittel") {
                    views.setInt(R.id.btn_prio_mittel, "setBackgroundColor", 0xFFF97316.toInt()) // Solid Orange
                    views.setInt(R.id.btn_prio_mittel, "setTextColor", 0xFFFFFFFF.toInt()) // White
                } else {
                    views.setInt(R.id.btn_prio_mittel, "setBackgroundColor", 0x1AF97316.toInt()) // Translucent Orange
                    views.setInt(R.id.btn_prio_mittel, "setTextColor", 0xFFF97316.toInt()) // Orange
                }

                // Niedrig button
                if (currentPrio == "niedrig") {
                    views.setInt(R.id.btn_prio_niedrig, "setBackgroundColor", 0xFF64748B.toInt()) // Solid Slate
                    views.setInt(R.id.btn_prio_niedrig, "setTextColor", 0xFFFFFFFF.toInt()) // White
                } else {
                    views.setInt(R.id.btn_prio_niedrig, "setBackgroundColor", 0x1A64748B.toInt()) // Translucent Slate
                    views.setInt(R.id.btn_prio_niedrig, "setTextColor", 0xFF64748B.toInt()) // Slate
                }

                // Relevant Ab Date text
                val date = parseDate(task.relevant_from)
                val dateText = if (date != null) {
                    val formatter = SimpleDateFormat("dd.MM.yyyy", Locale.GERMANY)
                    formatter.format(date)
                } else {
                    "Planen"
                }
                views.setTextViewText(R.id.btn_edit_date, dateText)

                // Hide clear date trash icon when no date is set
                if (task.relevant_from.isNullOrBlank()) {
                    views.setViewVisibility(R.id.btn_clear_date, View.GONE)
                } else {
                    views.setViewVisibility(R.id.btn_clear_date, View.VISIBLE)
                }

                // Click action for Hoch
                val setPrioHochIntent = Intent().apply {
                    action = TasksWidgetProvider.ACTION_SET_PRIORITY
                    putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
                    putExtra("task_id", task.id)
                    putExtra("priority", "hoch")
                }
                views.setOnClickFillInIntent(R.id.btn_prio_hoch, setPrioHochIntent)

                // Click action for Mittel
                val setPrioMittelIntent = Intent().apply {
                    action = TasksWidgetProvider.ACTION_SET_PRIORITY
                    putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
                    putExtra("task_id", task.id)
                    putExtra("priority", "mittel")
                }
                views.setOnClickFillInIntent(R.id.btn_prio_mittel, setPrioMittelIntent)

                // Click action for Niedrig
                val setPrioNiedrigIntent = Intent().apply {
                    action = TasksWidgetProvider.ACTION_SET_PRIORITY
                    putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
                    putExtra("task_id", task.id)
                    putExtra("priority", "niedrig")
                }
                views.setOnClickFillInIntent(R.id.btn_prio_niedrig, setPrioNiedrigIntent)

                // Click action for Date Cycle
                val cycleDateIntent = Intent().apply {
                    action = TasksWidgetProvider.ACTION_CYCLE_DATE
                    putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
                    putExtra("task_id", task.id)
                }
                views.setOnClickFillInIntent(R.id.btn_edit_date, cycleDateIntent)

                // Click action for Clear Date
                val clearDateIntent = Intent().apply {
                    action = TasksWidgetProvider.ACTION_CLEAR_DATE
                    putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
                    putExtra("task_id", task.id)
                }
                views.setOnClickFillInIntent(R.id.btn_clear_date, clearDateIntent)

                // Click action for Add Subtask
                val addSubtaskIntent = Intent().apply {
                    action = TasksWidgetProvider.ACTION_ADD_SUBTASK
                    putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
                    putExtra("task_id", task.id)
                }
                views.setOnClickFillInIntent(R.id.btn_add_subtask, addSubtaskIntent)

                // Click action for Delete Task
                val deleteTaskIntent = Intent().apply {
                    action = TasksWidgetProvider.ACTION_DELETE_TASK
                    putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
                    putExtra("task_id", task.id)
                }
                views.setOnClickFillInIntent(R.id.btn_delete_task, deleteTaskIntent)
                views
            }
            is TasksWidgetItem.AddSubtaskControls -> {
                val views = RemoteViews(context.packageName, R.layout.widget_tasks_add_subtask_control)
                
                val displayName = if (item.tempTitle.isEmpty()) "[Tippen zum Schreiben]" else item.tempTitle
                views.setTextViewText(R.id.btn_add_subtask_name, displayName)
                views.setTextColor(R.id.btn_add_subtask_name, if (item.tempTitle.isEmpty()) 0xFF94A3B8.toInt() else 0xFFF8FAFC.toInt())

                // Click action to open subtask title editor activity
                val titleEditorIntent = Intent().apply {
                    action = TasksWidgetProvider.ACTION_OPEN_TEXT_INPUT
                    putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
                    putExtra("title", "Titel des Schritts")
                    putExtra("pref_key", "widget_tasks_adding_subtask_title_$appWidgetId")
                }
                views.setOnClickFillInIntent(R.id.btn_add_subtask_name, titleEditorIntent)

                // Click action for Abbrechen (Cancel)
                val cancelIntent = Intent().apply {
                    action = TasksWidgetProvider.ACTION_CANCEL_ADD_SUBTASK
                    putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
                }
                views.setOnClickFillInIntent(R.id.btn_add_subtask_cancel, cancelIntent)

                // Click action for Speichern (Save)
                val saveIntent = Intent().apply {
                    action = TasksWidgetProvider.ACTION_SAVE_ADD_SUBTASK
                    putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
                }
                views.setOnClickFillInIntent(R.id.btn_add_subtask_save, saveIntent)
                views
            }
        }
    }

    override fun getLoadingView(): RemoteViews? = null

    override fun getViewTypeCount(): Int = 7

    override fun getItemId(position: Int): Long {
        if (position >= itemsList.size) return position.toLong()
        return when (val item = itemsList[position]) {
            is TasksWidgetItem.TaskList -> item.list.id.hashCode().toLong()
            is TasksWidgetItem.ParentTask -> item.task.id.hashCode().toLong()
            is TasksWidgetItem.Subtask -> item.task.id.hashCode().toLong()
            is TasksWidgetItem.EditControls -> item.task.id.hashCode().toLong() + 100000L
            is TasksWidgetItem.AddNewListDummy -> 999999L
            is TasksWidgetItem.AddNewTaskDummy -> item.listId.hashCode().toLong() + 888888L
            is TasksWidgetItem.AddListControls -> 777777L
            is TasksWidgetItem.AddTaskControls -> 666666L
            is TasksWidgetItem.AddSubtaskControls -> 555555L
        }
    }

    override fun hasStableIds(): Boolean = true

    private fun parseDate(dateStr: String?): Date? {
        if (dateStr.isNullOrBlank()) return null
        val formats = arrayOf(
            "yyyy-MM-dd'T'HH:mm:ss.SSSSSS'Z'",
            "yyyy-MM-dd'T'HH:mm:ss'Z'",
            "yyyy-MM-dd HH:mm:ss",
            "yyyy-MM-dd"
        )
        for (format in formats) {
            try {
                val sdf = SimpleDateFormat(format, Locale.US).apply {
                    timeZone = TimeZone.getTimeZone("UTC")
                }
                return sdf.parse(dateStr)
            } catch (e: Exception) {}
        }
        return null
    }

    private fun formatOffenSeit(createdAtStr: String?): String {
        val date = parseDate(createdAtStr) ?: return ""
        val now = Date()
        val diffMs = now.time - date.time
        if (diffMs <= 0) return "Offen seit: gerade eben"

        val minutes = diffMs / (1000 * 60)
        if (minutes < 60) return "Offen seit: $minutes Min."

        val hours = minutes / 60
        if (hours < 24) return "Offen seit: $hours Std."

        val days = hours / 24
        return "Offen seit: $days Tg."
    }

    private fun formatRelevantAb(relevantStr: String?): String {
        val date = parseDate(relevantStr) ?: return ""
        val formatter = SimpleDateFormat("dd.MM.yyyy", Locale.GERMANY)
        return "Relevant ab: ${formatter.format(date)}"
    }
}
