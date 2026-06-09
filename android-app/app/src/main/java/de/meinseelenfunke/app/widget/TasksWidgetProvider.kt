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
import android.widget.Toast
import de.meinseelenfunke.app.MainActivity
import de.meinseelenfunke.app.R
import de.meinseelenfunke.app.di.ServiceLocator
import de.meinseelenfunke.app.data.api.ManagementTask
import de.meinseelenfunke.app.data.api.ManagementTaskList
import de.meinseelenfunke.app.util.DateUtils
import java.util.Locale
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch

class TasksWidgetProvider : AppWidgetProvider() {

    override fun onUpdate(context: Context, appWidgetManager: AppWidgetManager, appWidgetIds: IntArray) {
        for (appWidgetId in appWidgetIds) {
            updateAppWidget(context, appWidgetManager, appWidgetId)
        }
        triggerBackgroundSync(context)
        super.onUpdate(context, appWidgetManager, appWidgetIds)
    }

    override fun onReceive(context: Context, intent: Intent) {
        var appWidgetId = intent.getIntExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, AppWidgetManager.INVALID_APPWIDGET_ID)
        if (appWidgetId == AppWidgetManager.INVALID_APPWIDGET_ID) {
            val data = intent.data
            if (data != null) {
                try {
                    val idParam = data.getQueryParameter("appWidgetId")
                    if (idParam != null) {
                        appWidgetId = idParam.toInt()
                    }
                } catch (e: Exception) {
                    Log.e("TasksWidget", "Error parsing appWidgetId from URI: $data", e)
                }
            }
        }
        Log.d("TasksWidget", "onReceive: action=${intent.action}, appWidgetId=$appWidgetId")
        super.onReceive(context, intent)
        val appWidgetManager = AppWidgetManager.getInstance(context)
        val componentName = ComponentName(context, TasksWidgetProvider::class.java)
        val appWidgetIds = appWidgetManager.getAppWidgetIds(componentName)

        when (intent.action) {
            ACTION_REFRESH -> {
                for (id in appWidgetIds) {
                    updateAppWidget(context, appWidgetManager, id)
                }
                appWidgetManager.notifyAppWidgetViewDataChanged(appWidgetIds, R.id.tasks_widget_list)
                triggerBackgroundSync(context)
            }
            ACTION_SELECT_LIST -> {
                val listId = intent.getStringExtra("list_id")
                val listName = intent.getStringExtra("list_name")
                if (appWidgetId != AppWidgetManager.INVALID_APPWIDGET_ID && listId != null) {
                    val sharedPrefs = context.getSharedPreferences("tasks_widget_prefs", Context.MODE_PRIVATE)
                    sharedPrefs.edit()
                        .putString("widget_tasks_selected_list_id_$appWidgetId", listId)
                        .putString("widget_tasks_selected_list_name_$appWidgetId", listName)
                        .remove("widget_tasks_editing_task_id_$appWidgetId")
                        .remove("widget_tasks_adding_task_$appWidgetId")
                        .apply()

                    updateAppWidget(context, appWidgetManager, appWidgetId)
                    appWidgetManager.notifyAppWidgetViewDataChanged(appWidgetId, R.id.tasks_widget_list)
                }
            }
            ACTION_BACK_TO_LISTS -> {
                if (appWidgetId != AppWidgetManager.INVALID_APPWIDGET_ID) {
                    val sharedPrefs = context.getSharedPreferences("tasks_widget_prefs", Context.MODE_PRIVATE)
                    val editingTaskId = sharedPrefs.getString("widget_tasks_editing_task_id_$appWidgetId", null)
                    val inlineEditingId = sharedPrefs.getString("widget_tasks_inline_editing_id_$appWidgetId", null)

                    val edit = sharedPrefs.edit()
                    if (inlineEditingId != null) {
                        edit.remove("widget_tasks_inline_editing_id_$appWidgetId")
                            .remove("widget_tasks_inline_editing_field_$appWidgetId")
                            .remove("widget_tasks_inline_editing_title_val_$appWidgetId")
                            .remove("widget_tasks_inline_editing_date_val_$appWidgetId")
                    } else if (editingTaskId != null) {
                        edit.remove("widget_tasks_editing_task_id_$appWidgetId")
                    } else {
                        edit.remove("widget_tasks_selected_list_id_$appWidgetId")
                            .remove("widget_tasks_selected_list_name_$appWidgetId")
                    }
                    edit.remove("widget_tasks_adding_list_$appWidgetId")
                        .remove("widget_tasks_adding_task_$appWidgetId")
                        .remove("widget_tasks_adding_subtask_parent_$appWidgetId")
                        .apply()

                    updateAppWidget(context, appWidgetManager, appWidgetId)
                    appWidgetManager.notifyAppWidgetViewDataChanged(appWidgetId, R.id.tasks_widget_list)
                }
            }
            ACTION_TOGGLE_TASK -> {
                val taskId = intent.getStringExtra("task_id")
                if (taskId != null) {
                    // 1. Optimistic toggle locally
                    ServiceLocator.organizerRepository.optimisticToggleTask(taskId)

                    // 2. Perform network update in the background
                    CoroutineScope(Dispatchers.Main).launch {
                        ServiceLocator.organizerRepository.toggleTask(taskId)
                            .onFailure {
                                // On failure, fetch latest state to sync back
                                ServiceLocator.organizerRepository.getTasks()
                            }
                    }
                }
            }
            ACTION_CYCLE_PRIORITY -> {
                val taskId = intent.getStringExtra("task_id")
                if (taskId != null) {
                    val repository = ServiceLocator.organizerRepository
                    val sharedPrefs = context.getSharedPreferences("tasks_widget_prefs", Context.MODE_PRIVATE)
                    val tasksJson = sharedPrefs.getString("tasks_cache", null)
                    if (tasksJson != null) {
                        try {
                            val gson = com.google.gson.Gson()
                            val type = object : com.google.gson.reflect.TypeToken<List<ManagementTask>>() {}.type
                            val tasks: List<ManagementTask> = gson.fromJson(tasksJson, type)
                            val task = tasks.find { it.id == taskId }
                            if (task != null) {
                                val nextPriority = when (task.priority?.lowercase(Locale.ROOT)) {
                                    "hoch" -> "mittel"
                                    "mittel" -> "niedrig"
                                    else -> "hoch"
                                }
                                val updatedTask = task.copy(priority = nextPriority)
                                val updatedList = tasks.map { if (it.id == taskId) updatedTask else it }
                                repository.saveTasksToCache(updatedList)
                                repository.triggerTasksWidgetUpdate(context)

                                CoroutineScope(Dispatchers.Main).launch {
                                    repository.updateTask(taskId, priority = nextPriority)
                                }
                            }
                        } catch (e: Exception) {
                            Log.e("TasksWidget", "Cycle priority failed", e)
                        }
                    }
                }
            }
            ACTION_EDIT_TASK -> {
                val taskId = intent.getStringExtra("task_id")
                if (appWidgetId != AppWidgetManager.INVALID_APPWIDGET_ID && taskId != null) {
                    val sharedPrefs = context.getSharedPreferences("tasks_widget_prefs", Context.MODE_PRIVATE)
                    sharedPrefs.edit()
                        .putString("widget_tasks_editing_task_id_$appWidgetId", taskId)
                        .apply()

                    updateAppWidget(context, appWidgetManager, appWidgetId)
                    appWidgetManager.notifyAppWidgetViewDataChanged(appWidgetId, R.id.tasks_widget_list)
                }
            }
            ACTION_EXIT_EDIT_MODE -> {
                if (appWidgetId != AppWidgetManager.INVALID_APPWIDGET_ID) {
                    val sharedPrefs = context.getSharedPreferences("tasks_widget_prefs", Context.MODE_PRIVATE)
                    sharedPrefs.edit()
                        .remove("widget_tasks_editing_task_id_$appWidgetId")
                        .apply()

                    updateAppWidget(context, appWidgetManager, appWidgetId)
                    appWidgetManager.notifyAppWidgetViewDataChanged(appWidgetId, R.id.tasks_widget_list)
                }
            }
            ACTION_SET_PRIORITY -> {
                val taskId = intent.getStringExtra("task_id")
                val prio = intent.getStringExtra("priority")
                if (taskId != null && prio != null) {
                    val repository = ServiceLocator.organizerRepository
                    val sharedPrefs = context.getSharedPreferences("tasks_widget_prefs", Context.MODE_PRIVATE)
                    val tasksJson = sharedPrefs.getString("tasks_cache", null)
                    if (tasksJson != null) {
                        try {
                            val gson = com.google.gson.Gson()
                            val type = object : com.google.gson.reflect.TypeToken<List<ManagementTask>>() {}.type
                            val tasks: List<ManagementTask> = gson.fromJson(tasksJson, type)
                            val task = tasks.find { it.id == taskId }
                            if (task != null) {
                                val updatedTask = task.copy(priority = prio)
                                val updatedList = tasks.map { if (it.id == taskId) updatedTask else it }
                                repository.saveTasksToCache(updatedList)
                                repository.triggerTasksWidgetUpdate(context)

                                CoroutineScope(Dispatchers.Main).launch {
                                    repository.updateTask(taskId, priority = prio)
                                }
                            }
                        } catch (e: Exception) {
                            Log.e("TasksWidget", "Set priority failed", e)
                        }
                    }
                }
            }
            ACTION_CLEAR_DATE -> {
                val taskId = intent.getStringExtra("task_id")
                if (taskId != null) {
                    val repository = ServiceLocator.organizerRepository
                    val sharedPrefs = context.getSharedPreferences("tasks_widget_prefs", Context.MODE_PRIVATE)
                    val tasksJson = sharedPrefs.getString("tasks_cache", null)
                    if (tasksJson != null) {
                        try {
                            val gson = com.google.gson.Gson()
                            val type = object : com.google.gson.reflect.TypeToken<List<ManagementTask>>() {}.type
                            val tasks: List<ManagementTask> = gson.fromJson(tasksJson, type)
                            val task = tasks.find { it.id == taskId }
                            if (task != null) {
                                val updatedTask = task.copy(relevant_from = null)
                                val updatedList = tasks.map { if (it.id == taskId) updatedTask else it }
                                repository.saveTasksToCache(updatedList)
                                repository.triggerTasksWidgetUpdate(context)

                                CoroutineScope(Dispatchers.Main).launch {
                                    repository.updateTask(taskId, relevantFrom = "")
                                }
                            }
                        } catch (e: Exception) {
                            Log.e("TasksWidget", "Clear date failed", e)
                        }
                    }
                }
            }
            ACTION_CYCLE_DATE -> {
                val taskId = intent.getStringExtra("task_id")
                if (taskId != null) {
                    val repository = ServiceLocator.organizerRepository
                    val sharedPrefs = context.getSharedPreferences("tasks_widget_prefs", Context.MODE_PRIVATE)
                    val tasksJson = sharedPrefs.getString("tasks_cache", null)
                    if (tasksJson != null) {
                        try {
                            val gson = com.google.gson.Gson()
                            val type = object : com.google.gson.reflect.TypeToken<List<ManagementTask>>() {}.type
                            val tasks: List<ManagementTask> = gson.fromJson(tasksJson, type)
                            val task = tasks.find { it.id == taskId }
                            if (task != null) {
                                val sdf = java.text.SimpleDateFormat("yyyy-MM-dd", Locale.US)
                                val todayStr = sdf.format(java.util.Date())
                                
                                val cal = java.util.Calendar.getInstance()
                                cal.add(java.util.Calendar.DAY_OF_YEAR, 1)
                                val tomorrowStr = sdf.format(cal.time)
                                
                                cal.time = java.util.Date()
                                cal.add(java.util.Calendar.DAY_OF_YEAR, 3)
                                val in3DaysStr = sdf.format(cal.time)
                                
                                cal.time = java.util.Date()
                                cal.add(java.util.Calendar.DAY_OF_YEAR, 7)
                                val in7DaysStr = sdf.format(cal.time)

                                val currentVal = DateUtils.parseUtcToLocalDateString(task.relevant_from)

                                val nextDateStr = when (currentVal) {
                                    null, "" -> todayStr
                                    todayStr -> tomorrowStr
                                    tomorrowStr -> in3DaysStr
                                    in3DaysStr -> in7DaysStr
                                    else -> null
                                }

                                val updatedTask = task.copy(relevant_from = nextDateStr)
                                val updatedList = tasks.map { if (it.id == taskId) updatedTask else it }
                                repository.saveTasksToCache(updatedList)
                                repository.triggerTasksWidgetUpdate(context)

                                CoroutineScope(Dispatchers.Main).launch {
                                    repository.updateTask(taskId, relevantFrom = nextDateStr)
                                }
                            }
                        } catch (e: Exception) {
                            Log.e("TasksWidget", "Cycle date failed", e)
                        }
                    }
                }
            }
            ACTION_ADD_SUBTASK -> {
                val taskId = intent.getStringExtra("task_id")
                if (appWidgetId != AppWidgetManager.INVALID_APPWIDGET_ID && taskId != null) {
                    val sharedPrefs = context.getSharedPreferences("tasks_widget_prefs", Context.MODE_PRIVATE)
                    sharedPrefs.edit()
                        .putString("widget_tasks_adding_subtask_parent_$appWidgetId", taskId)
                        .remove("widget_tasks_adding_subtask_title_$appWidgetId")
                        .apply()
                    updateAppWidget(context, appWidgetManager, appWidgetId)
                    appWidgetManager.notifyAppWidgetViewDataChanged(appWidgetId, R.id.tasks_widget_list)
                }
            }
            ACTION_CANCEL_ADD_SUBTASK -> {
                if (appWidgetId != AppWidgetManager.INVALID_APPWIDGET_ID) {
                    val sharedPrefs = context.getSharedPreferences("tasks_widget_prefs", Context.MODE_PRIVATE)
                    sharedPrefs.edit()
                        .remove("widget_tasks_adding_subtask_parent_$appWidgetId")
                        .remove("widget_tasks_adding_subtask_title_$appWidgetId")
                        .apply()
                    updateAppWidget(context, appWidgetManager, appWidgetId)
                    appWidgetManager.notifyAppWidgetViewDataChanged(appWidgetId, R.id.tasks_widget_list)
                }
            }
            ACTION_SAVE_ADD_SUBTASK -> {
                if (appWidgetId != AppWidgetManager.INVALID_APPWIDGET_ID) {
                    val sharedPrefs = context.getSharedPreferences("tasks_widget_prefs", Context.MODE_PRIVATE)
                    val parentId = sharedPrefs.getString("widget_tasks_adding_subtask_parent_$appWidgetId", null)
                    val title = sharedPrefs.getString("widget_tasks_adding_subtask_title_$appWidgetId", "") ?: ""
                    if (parentId != null && title.isNotBlank()) {
                        val repository = ServiceLocator.organizerRepository
                        CoroutineScope(Dispatchers.Main).launch {
                            repository.addSubtask(parentId, title.trim())
                            repository.getTasks()
                            
                            sharedPrefs.edit()
                                .remove("widget_tasks_adding_subtask_parent_$appWidgetId")
                                .remove("widget_tasks_adding_subtask_title_$appWidgetId")
                                .apply()
                                
                            updateAppWidget(context, appWidgetManager, appWidgetId)
                            appWidgetManager.notifyAppWidgetViewDataChanged(appWidgetId, R.id.tasks_widget_list)
                        }
                    } else {
                        Toast.makeText(context, "Bitte einen Titel eingeben.", Toast.LENGTH_SHORT).show()
                    }
                }
            }
            ACTION_DELETE_LIST -> {
                val listId = intent.getStringExtra("list_id")
                if (listId != null) {
                    val confirmIntent = Intent(context, ConfirmDeleteListActivity::class.java).apply {
                        putExtra("list_id", listId)
                        putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
                        flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
                    }
                    startActivityExempted(context, confirmIntent)
                }
            }
            ACTION_DELETE_TASK -> {
                val taskId = intent.getStringExtra("task_id")
                if (taskId != null) {
                    val repository = ServiceLocator.organizerRepository
                    val sharedPrefs = context.getSharedPreferences("tasks_widget_prefs", Context.MODE_PRIVATE)
                    val edit = sharedPrefs.edit()
                    for (id in appWidgetIds) {
                        val editingTaskId = sharedPrefs.getString("widget_tasks_editing_task_id_$id", null)
                        if (editingTaskId == taskId) {
                            edit.remove("widget_tasks_editing_task_id_$id")
                        }
                    }
                    edit.apply()

                    CoroutineScope(Dispatchers.Main).launch {
                        repository.deleteTask(taskId)
                        repository.getTasks()
                        repository.triggerTasksWidgetUpdate(context)
                    }
                }
            }
            ACTION_START_ADD_LIST -> {
                if (appWidgetId != AppWidgetManager.INVALID_APPWIDGET_ID) {
                    val addListIntent = Intent(context, AddTaskListWidgetActivity::class.java).apply {
                        putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
                        flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
                    }
                    startActivityExempted(context, addListIntent)
                }
            }
            ACTION_CANCEL_ADD_LIST -> {
                if (appWidgetId != AppWidgetManager.INVALID_APPWIDGET_ID) {
                    val sharedPrefs = context.getSharedPreferences("tasks_widget_prefs", Context.MODE_PRIVATE)
                    sharedPrefs.edit()
                        .remove("widget_tasks_adding_list_$appWidgetId")
                        .remove("widget_tasks_adding_list_name_$appWidgetId")
                        .apply()
                    updateAppWidget(context, appWidgetManager, appWidgetId)
                    appWidgetManager.notifyAppWidgetViewDataChanged(appWidgetId, R.id.tasks_widget_list)
                }
            }
            ACTION_SAVE_ADD_LIST -> {
                if (appWidgetId != AppWidgetManager.INVALID_APPWIDGET_ID) {
                    val sharedPrefs = context.getSharedPreferences("tasks_widget_prefs", Context.MODE_PRIVATE)
                    val listName = sharedPrefs.getString("widget_tasks_adding_list_name_$appWidgetId", "") ?: ""
                    if (listName.isNotBlank()) {
                        val repository = ServiceLocator.organizerRepository
                        CoroutineScope(Dispatchers.Main).launch {
                            repository.addTaskList(listName)
                            repository.getTaskLists()
                            
                            sharedPrefs.edit()
                                .remove("widget_tasks_adding_list_$appWidgetId")
                                .remove("widget_tasks_adding_list_name_$appWidgetId")
                                .apply()
                            
                            updateAppWidget(context, appWidgetManager, appWidgetId)
                            appWidgetManager.notifyAppWidgetViewDataChanged(appWidgetId, R.id.tasks_widget_list)
                        }
                    } else {
                        Toast.makeText(context, "Bitte einen Namen eingeben.", Toast.LENGTH_SHORT).show()
                    }
                }
            }
            ACTION_START_ADD_TASK -> {
                if (appWidgetId != AppWidgetManager.INVALID_APPWIDGET_ID) {
                    val sharedPrefs = context.getSharedPreferences("tasks_widget_prefs", Context.MODE_PRIVATE)
                    sharedPrefs.edit()
                        .putBoolean("widget_tasks_adding_task_$appWidgetId", true)
                        .remove("widget_tasks_adding_task_title_$appWidgetId")
                        .putString("widget_tasks_adding_task_prio_$appWidgetId", "niedrig")
                        .remove("widget_tasks_adding_task_date_$appWidgetId")
                        .apply()
                    updateAppWidget(context, appWidgetManager, appWidgetId)
                    appWidgetManager.notifyAppWidgetViewDataChanged(appWidgetId, R.id.tasks_widget_list)
                }
            }
            ACTION_CANCEL_ADD_TASK -> {
                if (appWidgetId != AppWidgetManager.INVALID_APPWIDGET_ID) {
                    val sharedPrefs = context.getSharedPreferences("tasks_widget_prefs", Context.MODE_PRIVATE)
                    sharedPrefs.edit()
                        .remove("widget_tasks_adding_task_$appWidgetId")
                        .remove("widget_tasks_adding_task_title_$appWidgetId")
                        .remove("widget_tasks_adding_task_prio_$appWidgetId")
                        .remove("widget_tasks_adding_task_date_$appWidgetId")
                        .apply()
                    updateAppWidget(context, appWidgetManager, appWidgetId)
                    appWidgetManager.notifyAppWidgetViewDataChanged(appWidgetId, R.id.tasks_widget_list)
                }
            }
            ACTION_SAVE_ADD_TASK -> {
                if (appWidgetId != AppWidgetManager.INVALID_APPWIDGET_ID) {
                    val sharedPrefs = context.getSharedPreferences("tasks_widget_prefs", Context.MODE_PRIVATE)
                    val selectedListId = sharedPrefs.getString("widget_tasks_selected_list_id_$appWidgetId", null)
                    val taskTitle = sharedPrefs.getString("widget_tasks_adding_task_title_$appWidgetId", "") ?: ""
                    val taskPrio = sharedPrefs.getString("widget_tasks_adding_task_prio_$appWidgetId", "niedrig") ?: "niedrig"
                    val taskDate = sharedPrefs.getString("widget_tasks_adding_task_date_$appWidgetId", null)

                    if (selectedListId != null && taskTitle.isNotBlank()) {
                        val repository = ServiceLocator.organizerRepository
                        CoroutineScope(Dispatchers.Main).launch {
                            val result = repository.addTask(selectedListId, taskTitle, taskPrio)
                            result.onSuccess { newTask ->
                                if (!taskDate.isNullOrBlank()) {
                                    repository.updateTask(newTask.id, relevantFrom = taskDate)
                                }
                                repository.getTasks()
                                
                                sharedPrefs.edit()
                                    .remove("widget_tasks_adding_task_$appWidgetId")
                                    .remove("widget_tasks_adding_task_title_$appWidgetId")
                                    .remove("widget_tasks_adding_task_prio_$appWidgetId")
                                    .remove("widget_tasks_adding_task_date_$appWidgetId")
                                    .apply()
                                
                                updateAppWidget(context, appWidgetManager, appWidgetId)
                                appWidgetManager.notifyAppWidgetViewDataChanged(appWidgetId, R.id.tasks_widget_list)
                            }
                        }
                    } else {
                        Toast.makeText(context, "Bitte einen Titel eingeben.", Toast.LENGTH_SHORT).show()
                    }
                }
            }
            ACTION_ADD_TASK_SET_PRIO -> {
                val prio = intent.getStringExtra("priority")
                if (appWidgetId != AppWidgetManager.INVALID_APPWIDGET_ID && prio != null) {
                    val sharedPrefs = context.getSharedPreferences("tasks_widget_prefs", Context.MODE_PRIVATE)
                    sharedPrefs.edit()
                        .putString("widget_tasks_adding_task_prio_$appWidgetId", prio)
                        .apply()
                    updateAppWidget(context, appWidgetManager, appWidgetId)
                    appWidgetManager.notifyAppWidgetViewDataChanged(appWidgetId, R.id.tasks_widget_list)
                }
            }
            ACTION_ADD_TASK_CYCLE_DATE -> {
                if (appWidgetId != AppWidgetManager.INVALID_APPWIDGET_ID) {
                    val sharedPrefs = context.getSharedPreferences("tasks_widget_prefs", Context.MODE_PRIVATE)
                    val currentDate = sharedPrefs.getString("widget_tasks_adding_task_date_$appWidgetId", null)
                    
                    val sdf = java.text.SimpleDateFormat("yyyy-MM-dd", Locale.US)
                    val todayStr = sdf.format(java.util.Date())
                    
                    val cal = java.util.Calendar.getInstance()
                    cal.add(java.util.Calendar.DAY_OF_YEAR, 1)
                    val tomorrowStr = sdf.format(cal.time)
                    
                    cal.time = java.util.Date()
                    cal.add(java.util.Calendar.DAY_OF_YEAR, 3)
                    val in3DaysStr = sdf.format(cal.time)
                    
                    cal.time = java.util.Date()
                    cal.add(java.util.Calendar.DAY_OF_YEAR, 7)
                    val in7DaysStr = sdf.format(cal.time)

                    val nextDate = when (currentDate) {
                        null, "" -> todayStr
                        todayStr -> tomorrowStr
                        tomorrowStr -> in3DaysStr
                        in3DaysStr -> in7DaysStr
                        else -> null
                    }

                    sharedPrefs.edit()
                        .putString("widget_tasks_adding_task_date_$appWidgetId", nextDate)
                        .apply()
                    updateAppWidget(context, appWidgetManager, appWidgetId)
                    appWidgetManager.notifyAppWidgetViewDataChanged(appWidgetId, R.id.tasks_widget_list)
                }
            }
            ACTION_ADD_TASK_CLEAR_DATE -> {
                if (appWidgetId != AppWidgetManager.INVALID_APPWIDGET_ID) {
                    val sharedPrefs = context.getSharedPreferences("tasks_widget_prefs", Context.MODE_PRIVATE)
                    sharedPrefs.edit()
                        .remove("widget_tasks_adding_task_date_$appWidgetId")
                        .apply()
                    updateAppWidget(context, appWidgetManager, appWidgetId)
                    appWidgetManager.notifyAppWidgetViewDataChanged(appWidgetId, R.id.tasks_widget_list)
                }
            }
            ACTION_OPEN_TEXT_INPUT -> {
                val title = intent.getStringExtra("title")
                val prefKey = intent.getStringExtra("pref_key")
                if (title != null && prefKey != null) {
                    val inputIntent = Intent(context, TextInputActivity::class.java).apply {
                        putExtra("title", title)
                        putExtra("pref_key", prefKey)
                        putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
                        flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
                    }
                    startActivityExempted(context, inputIntent)
                }
            }
            ACTION_NONE -> {
                // No-op to consume widget background taps
            }
            ACTION_INLINE_EDIT_TITLE -> {
                val taskId = intent.getStringExtra("task_id")
                val currentTitle = intent.getStringExtra("task_title") ?: ""
                if (appWidgetId != AppWidgetManager.INVALID_APPWIDGET_ID && taskId != null) {
                    val sharedPrefs = context.getSharedPreferences("tasks_widget_prefs", Context.MODE_PRIVATE)
                    sharedPrefs.edit()
                        .putString("widget_tasks_inline_editing_id_$appWidgetId", taskId)
                        .putString("widget_tasks_inline_editing_field_$appWidgetId", "title")
                        .putString("widget_tasks_inline_editing_title_val_$appWidgetId", currentTitle)
                        .apply()
                    updateAppWidget(context, appWidgetManager, appWidgetId)
                    appWidgetManager.notifyAppWidgetViewDataChanged(appWidgetId, R.id.tasks_widget_list)
                }
            }
            ACTION_SAVE_INLINE_TITLE -> {
                val taskId = intent.getStringExtra("task_id")
                if (appWidgetId != AppWidgetManager.INVALID_APPWIDGET_ID && taskId != null) {
                    val sharedPrefs = context.getSharedPreferences("tasks_widget_prefs", Context.MODE_PRIVATE)
                    val newTitle = sharedPrefs.getString("widget_tasks_inline_editing_title_val_$appWidgetId", "") ?: ""
                    if (newTitle.isNotBlank()) {
                        val repository = ServiceLocator.organizerRepository
                        
                        // Optimistic local cache update
                        val tasksJson = sharedPrefs.getString("tasks_cache", null)
                        if (tasksJson != null) {
                            try {
                                val gson = com.google.gson.Gson()
                                val type = object : com.google.gson.reflect.TypeToken<List<ManagementTask>>() {}.type
                                val tasks: List<ManagementTask> = gson.fromJson(tasksJson, type)
                                val updatedTasks = tasks.map { 
                                    if (it.id == taskId) it.copy(title = newTitle.trim()) else it 
                                }
                                repository.saveTasksToCache(updatedTasks)
                            } catch (e: Exception) {}
                        }
                        
                        sharedPrefs.edit()
                            .remove("widget_tasks_inline_editing_id_$appWidgetId")
                            .remove("widget_tasks_inline_editing_field_$appWidgetId")
                            .remove("widget_tasks_inline_editing_title_val_$appWidgetId")
                            .apply()
                        
                        updateAppWidget(context, appWidgetManager, appWidgetId)
                        appWidgetManager.notifyAppWidgetViewDataChanged(appWidgetId, R.id.tasks_widget_list)

                        CoroutineScope(Dispatchers.Main).launch {
                            repository.updateTask(taskId, title = newTitle.trim())
                            repository.getTasks()
                            repository.triggerTasksWidgetUpdate(context)
                        }
                    }
                }
            }
            ACTION_INLINE_EDIT_DATE -> {
                val taskId = intent.getStringExtra("task_id")
                val currentDate = intent.getStringExtra("task_date") // yyyy-MM-dd
                if (appWidgetId != AppWidgetManager.INVALID_APPWIDGET_ID && taskId != null) {
                    val sharedPrefs = context.getSharedPreferences("tasks_widget_prefs", Context.MODE_PRIVATE)
                    sharedPrefs.edit()
                        .putString("widget_tasks_inline_editing_id_$appWidgetId", taskId)
                        .putString("widget_tasks_inline_editing_field_$appWidgetId", "date")
                        .putString("widget_tasks_inline_editing_date_val_$appWidgetId", currentDate)
                        .apply()
                    updateAppWidget(context, appWidgetManager, appWidgetId)
                    appWidgetManager.notifyAppWidgetViewDataChanged(appWidgetId, R.id.tasks_widget_list)
                }
            }
            ACTION_SAVE_INLINE_DATE -> {
                val taskId = intent.getStringExtra("task_id")
                if (appWidgetId != AppWidgetManager.INVALID_APPWIDGET_ID && taskId != null) {
                    val sharedPrefs = context.getSharedPreferences("tasks_widget_prefs", Context.MODE_PRIVATE)
                    val newDate = sharedPrefs.getString("widget_tasks_inline_editing_date_val_$appWidgetId", null)
                    val repository = ServiceLocator.organizerRepository
                    
                    // Optimistic local cache update
                    val tasksJson = sharedPrefs.getString("tasks_cache", null)
                    if (tasksJson != null) {
                        try {
                            val gson = com.google.gson.Gson()
                            val type = object : com.google.gson.reflect.TypeToken<List<ManagementTask>>() {}.type
                            val tasks: List<ManagementTask> = gson.fromJson(tasksJson, type)
                            val updatedTasks = tasks.map { 
                                if (it.id == taskId) it.copy(relevant_from = newDate) else it 
                             }
                            repository.saveTasksToCache(updatedTasks)
                        } catch (e: Exception) {}
                    }
                    
                    sharedPrefs.edit()
                        .remove("widget_tasks_inline_editing_id_$appWidgetId")
                        .remove("widget_tasks_inline_editing_field_$appWidgetId")
                        .remove("widget_tasks_inline_editing_date_val_$appWidgetId")
                        .apply()
                    
                    updateAppWidget(context, appWidgetManager, appWidgetId)
                    appWidgetManager.notifyAppWidgetViewDataChanged(appWidgetId, R.id.tasks_widget_list)

                    CoroutineScope(Dispatchers.Main).launch {
                        repository.updateTask(taskId, relevantFrom = newDate ?: "")
                        repository.getTasks()
                        repository.triggerTasksWidgetUpdate(context)
                    }
                }
            }
            ACTION_CANCEL_INLINE_EDIT -> {
                if (appWidgetId != AppWidgetManager.INVALID_APPWIDGET_ID) {
                    val sharedPrefs = context.getSharedPreferences("tasks_widget_prefs", Context.MODE_PRIVATE)
                    sharedPrefs.edit()
                        .remove("widget_tasks_inline_editing_id_$appWidgetId")
                        .remove("widget_tasks_inline_editing_field_$appWidgetId")
                        .remove("widget_tasks_inline_editing_title_val_$appWidgetId")
                        .remove("widget_tasks_inline_editing_date_val_$appWidgetId")
                        .apply()
                    updateAppWidget(context, appWidgetManager, appWidgetId)
                    appWidgetManager.notifyAppWidgetViewDataChanged(appWidgetId, R.id.tasks_widget_list)
                }
            }
            ACTION_OPEN_DATE_PICKER -> {
                val initialDate = intent.getStringExtra("initial_date")
                val prefKey = intent.getStringExtra("pref_key")
                val taskId = intent.getStringExtra("task_id")
                if (prefKey != null || taskId != null) {
                    val pickerIntent = Intent(context, DatePickerActivity::class.java).apply {
                        putExtra("initial_date", initialDate)
                        putExtra("pref_key", prefKey)
                        putExtra("task_id", taskId)
                        putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
                        flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
                    }
                    startActivityExempted(context, pickerIntent)
                }
            }
            ACTION_MANAGE_FILES -> {
                val taskId = intent.getStringExtra("task_id")
                if (taskId != null) {
                    val editIntent = Intent(context, EditTaskWidgetActivity::class.java).apply {
                        putExtra("task_id", taskId)
                        putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
                        flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
                    }
                    startActivityExempted(context, editIntent)
                }
            }
            // Handled by super.onReceive -> onUpdate
        }
    }

    private fun startActivityExempted(context: Context, intent: Intent) {
        if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.UPSIDE_DOWN_CAKE) {
            val options = android.app.ActivityOptions.makeBasic()
            options.pendingIntentBackgroundActivityStartMode = android.app.ActivityOptions.MODE_BACKGROUND_ACTIVITY_START_ALLOWED
            context.startActivity(intent, options.toBundle())
        } else {
            context.startActivity(intent)
        }
    }

    private fun triggerBackgroundSync(context: Context) {
        if (!ServiceLocator.authRepository.isLoggedIn()) return
        CoroutineScope(Dispatchers.IO).launch {
            try {
                ServiceLocator.organizerRepository.getTaskLists()
                ServiceLocator.organizerRepository.getTasks()
            } catch (e: Exception) {
                Log.e("TasksWidget", "Sync failed", e)
            }
        }
    }

    companion object {
        const val ACTION_REFRESH = "de.meinseelenfunke.app.widget.ACTION_TASKS_REFRESH"
        const val ACTION_SELECT_LIST = "de.meinseelenfunke.app.widget.ACTION_TASKS_SELECT_LIST"
        const val ACTION_BACK_TO_LISTS = "de.meinseelenfunke.app.widget.ACTION_TASKS_BACK"
        const val ACTION_TOGGLE_TASK = "de.meinseelenfunke.app.widget.ACTION_TASKS_TOGGLE"
        const val ACTION_CYCLE_PRIORITY = "de.meinseelenfunke.app.widget.ACTION_TASKS_CYCLE_PRIORITY"
        const val ACTION_CYCLE_DATE = "de.meinseelenfunke.app.widget.ACTION_TASKS_CYCLE_DATE"
        const val ACTION_EDIT_TASK = "de.meinseelenfunke.app.widget.ACTION_TASKS_EDIT"
        const val ACTION_EXIT_EDIT_MODE = "de.meinseelenfunke.app.widget.ACTION_TASKS_EXIT_EDIT_MODE"
        const val ACTION_SET_PRIORITY = "de.meinseelenfunke.app.widget.ACTION_TASKS_SET_PRIORITY"
        const val ACTION_CLEAR_DATE = "de.meinseelenfunke.app.widget.ACTION_TASKS_CLEAR_DATE"
        const val ACTION_ADD_SUBTASK = "de.meinseelenfunke.app.widget.ACTION_TASKS_ADD_SUBTASK"
        const val ACTION_CANCEL_ADD_SUBTASK = "de.meinseelenfunke.app.widget.ACTION_TASKS_CANCEL_ADD_SUBTASK"
        const val ACTION_SAVE_ADD_SUBTASK = "de.meinseelenfunke.app.widget.ACTION_TASKS_SAVE_ADD_SUBTASK"
        const val ACTION_DELETE_LIST = "de.meinseelenfunke.app.widget.ACTION_TASKS_DELETE_LIST"
        const val ACTION_DELETE_TASK = "de.meinseelenfunke.app.widget.ACTION_TASKS_DELETE_TASK"
        
        const val ACTION_START_ADD_LIST = "de.meinseelenfunke.app.widget.ACTION_TASKS_START_ADD_LIST"
        const val ACTION_CANCEL_ADD_LIST = "de.meinseelenfunke.app.widget.ACTION_TASKS_CANCEL_ADD_LIST"
        const val ACTION_SAVE_ADD_LIST = "de.meinseelenfunke.app.widget.ACTION_TASKS_SAVE_ADD_LIST"
        
        const val ACTION_START_ADD_TASK = "de.meinseelenfunke.app.widget.ACTION_TASKS_START_ADD_TASK"
        const val ACTION_CANCEL_ADD_TASK = "de.meinseelenfunke.app.widget.ACTION_TASKS_CANCEL_ADD_TASK"
        const val ACTION_SAVE_ADD_TASK = "de.meinseelenfunke.app.widget.ACTION_TASKS_SAVE_ADD_TASK"
        const val ACTION_ADD_TASK_SET_PRIO = "de.meinseelenfunke.app.widget.ACTION_TASKS_ADD_TASK_SET_PRIO"
        const val ACTION_ADD_TASK_CYCLE_DATE = "de.meinseelenfunke.app.widget.ACTION_TASKS_ADD_TASK_CYCLE_DATE"
        const val ACTION_ADD_TASK_CLEAR_DATE = "de.meinseelenfunke.app.widget.ACTION_TASKS_ADD_TASK_CLEAR_DATE"
        
        const val ACTION_OPEN_TEXT_INPUT = "de.meinseelenfunke.app.widget.ACTION_TASKS_OPEN_TEXT_INPUT"
        const val ACTION_NONE = "de.meinseelenfunke.app.widget.ACTION_TASKS_NONE"

        const val ACTION_INLINE_EDIT_TITLE = "de.meinseelenfunke.app.widget.ACTION_INLINE_EDIT_TITLE"
        const val ACTION_SAVE_INLINE_TITLE = "de.meinseelenfunke.app.widget.ACTION_SAVE_INLINE_TITLE"
        const val ACTION_INLINE_EDIT_DATE = "de.meinseelenfunke.app.widget.ACTION_INLINE_EDIT_DATE"
        const val ACTION_SAVE_INLINE_DATE = "de.meinseelenfunke.app.widget.ACTION_SAVE_INLINE_DATE"
        const val ACTION_CANCEL_INLINE_EDIT = "de.meinseelenfunke.app.widget.ACTION_CANCEL_INLINE_EDIT"
        const val ACTION_OPEN_DATE_PICKER = "de.meinseelenfunke.app.widget.ACTION_OPEN_DATE_PICKER"
        const val ACTION_MANAGE_FILES = "de.meinseelenfunke.app.widget.ACTION_MANAGE_FILES"

        fun updateAppWidget(context: Context, appWidgetManager: AppWidgetManager, appWidgetId: Int) {
            val views = RemoteViews(context.packageName, R.layout.widget_tasks_layout)

            // Background click listener removed to prevent intercepting child view touches

            // Setup ListView Adapter
            val serviceIntent = Intent(context, TasksWidgetService::class.java).apply {
                putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
                data = Uri.parse("widget://tasks/service?appWidgetId=$appWidgetId")
            }
            views.setRemoteAdapter(R.id.tasks_widget_list, serviceIntent)
            views.setEmptyView(R.id.tasks_widget_list, R.id.tasks_widget_empty_view)

            // Check selected list state for this widget ID
            val sharedPrefs = context.getSharedPreferences("tasks_widget_prefs", Context.MODE_PRIVATE)
            val selectedListId = sharedPrefs.getString("widget_tasks_selected_list_id_$appWidgetId", null)
            var selectedListName = sharedPrefs.getString("widget_tasks_selected_list_name_$appWidgetId", null)
            val editingTaskId = sharedPrefs.getString("widget_tasks_editing_task_id_$appWidgetId", null)

            Log.d("TasksWidget", "updateAppWidget: id=$appWidgetId, listId=$selectedListId, listName=$selectedListName, editingTaskId=$editingTaskId")

            // Recover name from lists cache if it was somehow lost
            if (selectedListId != null && selectedListName == null) {
                val listsJson = sharedPrefs.getString("task_lists_cache", null)
                if (listsJson != null) {
                    try {
                        val gson = com.google.gson.Gson()
                        val type = object : com.google.gson.reflect.TypeToken<List<ManagementTaskList>>() {}.type
                        val lists: List<ManagementTaskList> = gson.fromJson(listsJson, type)
                        selectedListName = lists.find { it.id == selectedListId }?.name
                    } catch (e: java.lang.Exception) {}
                }
            }

            val isEditing = editingTaskId != null
            val isInsideList = selectedListId != null

            if (isEditing) {
                views.setViewVisibility(R.id.btn_tasks_back, View.VISIBLE)
                
                // Find task title from cache
                val tasksJson = sharedPrefs.getString("tasks_cache", null)
                val taskTitle = if (tasksJson != null) {
                    try {
                        val gson = com.google.gson.Gson()
                        val type = object : com.google.gson.reflect.TypeToken<List<ManagementTask>>() {}.type
                        val tasks: List<ManagementTask> = gson.fromJson(tasksJson, type)
                        tasks.find { it.id == editingTaskId }?.title ?: "Aufgabe"
                    } catch (e: Exception) {
                        "Aufgabe"
                    }
                } else {
                    "Aufgabe"
                }

                views.setTextViewText(R.id.tasks_widget_title, "SEELENFUNKE")
                views.setTextViewText(R.id.tasks_widget_subtitle, "Aufgaben")
                views.setViewVisibility(R.id.tasks_widget_context, View.VISIBLE)
                views.setTextViewText(R.id.tasks_widget_context, (selectedListName ?: "Aufgaben").uppercase())

                // Back click PendingIntent (Broadcast) - consolidated
                val backIntent = Intent(context, TasksWidgetProvider::class.java).apply {
                    action = ACTION_BACK_TO_LISTS
                    putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
                    data = Uri.parse("widget://tasks/back?appWidgetId=$appWidgetId")
                }
                val pendingBack = PendingIntent.getBroadcast(
                    context, appWidgetId * 10 + 305, backIntent,
                    PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
                )
                views.setOnClickPendingIntent(R.id.btn_tasks_back, pendingBack)
            } else if (isInsideList && selectedListName != null) {
                views.setViewVisibility(R.id.btn_tasks_back, View.VISIBLE)
                views.setTextViewText(R.id.tasks_widget_title, "SEELENFUNKE")
                views.setTextViewText(R.id.tasks_widget_subtitle, "Aufgaben")
                views.setViewVisibility(R.id.tasks_widget_context, View.VISIBLE)
                views.setTextViewText(R.id.tasks_widget_context, selectedListName.uppercase())

                // Back click PendingIntent (Broadcast) - consolidated
                val backIntent = Intent(context, TasksWidgetProvider::class.java).apply {
                    action = ACTION_BACK_TO_LISTS
                    putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
                    data = Uri.parse("widget://tasks/back?appWidgetId=$appWidgetId")
                }
                val pendingBack = PendingIntent.getBroadcast(
                    context, appWidgetId * 10 + 305, backIntent,
                    PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
                )
                views.setOnClickPendingIntent(R.id.btn_tasks_back, pendingBack)
            } else {
                views.setViewVisibility(R.id.btn_tasks_back, View.INVISIBLE)
                views.setTextViewText(R.id.tasks_widget_title, "SEELENFUNKE")
                views.setTextViewText(R.id.tasks_widget_subtitle, "Aufgaben")
                views.setViewVisibility(R.id.tasks_widget_context, View.GONE)
            }

            // Setup PendingIntent template for ListView item clicks (Broadcast)
            // We omit appWidgetId extra from template to avoid merging conflicts on some versions
            val clickIntent = Intent(context, TasksWidgetProvider::class.java).apply {
                data = Uri.parse("widget://tasks/click?appWidgetId=$appWidgetId")
            }
            val clickPendingIntent = PendingIntent.getBroadcast(
                context, appWidgetId * 10 + 302, clickIntent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_MUTABLE
            )
            views.setPendingIntentTemplate(R.id.tasks_widget_list, clickPendingIntent)

            // Refresh button click handler (Broadcast)
            val refreshIntent = Intent(context, TasksWidgetProvider::class.java).apply {
                action = ACTION_REFRESH
                putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
                data = Uri.parse("widget://tasks/refresh?appWidgetId=$appWidgetId")
            }
            val pendingRefresh = PendingIntent.getBroadcast(
                context, appWidgetId * 10 + 303, refreshIntent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )
            views.setOnClickPendingIntent(R.id.btn_tasks_refresh, pendingRefresh)

            // Plus button has been removed from the header layout.

            appWidgetManager.updateAppWidget(appWidgetId, views)

            // Force immediate partial update of header to bypass OS update deferrals
            val partialViews = RemoteViews(context.packageName, R.layout.widget_tasks_layout)
            if (isEditing) {
                partialViews.setViewVisibility(R.id.btn_tasks_back, View.VISIBLE)
                partialViews.setViewVisibility(R.id.tasks_widget_context, View.VISIBLE)
                partialViews.setTextViewText(R.id.tasks_widget_context, (selectedListName ?: "Aufgaben").uppercase())
            } else if (isInsideList && selectedListName != null) {
                partialViews.setViewVisibility(R.id.btn_tasks_back, View.VISIBLE)
                partialViews.setViewVisibility(R.id.tasks_widget_context, View.VISIBLE)
                partialViews.setTextViewText(R.id.tasks_widget_context, selectedListName.uppercase())
            } else {
                partialViews.setViewVisibility(R.id.btn_tasks_back, View.INVISIBLE)
                partialViews.setViewVisibility(R.id.tasks_widget_context, View.GONE)
            }
            try {
                appWidgetManager.partiallyUpdateAppWidget(appWidgetId, partialViews)
            } catch (e: Exception) {
                Log.e("TasksWidget", "Partial update failed", e)
            }
        }
    }
}
