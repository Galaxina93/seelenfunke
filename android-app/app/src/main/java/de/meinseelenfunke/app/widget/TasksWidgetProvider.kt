package de.meinseelenfunke.app.widget

import android.app.ActivityOptions
import android.app.PendingIntent
import android.appwidget.AppWidgetManager
import android.appwidget.AppWidgetProvider
import android.content.ComponentName
import android.content.Context
import android.content.Intent
import android.net.Uri
import android.os.Build
import android.view.View
import android.widget.RemoteViews
import com.google.gson.Gson
import com.google.gson.reflect.TypeToken
import de.meinseelenfunke.app.MainActivity
import de.meinseelenfunke.app.R
import de.meinseelenfunke.app.data.api.ManagementTask
import de.meinseelenfunke.app.data.api.ManagementTaskList
import de.meinseelenfunke.app.di.ServiceLocator
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch

class TasksWidgetProvider : AppWidgetProvider() {

    override fun onUpdate(context: Context, appWidgetManager: AppWidgetManager, appWidgetIds: IntArray) {
        triggerBackgroundSync(context)
        for (appWidgetId in appWidgetIds) {
            updateAppWidget(context, appWidgetManager, appWidgetId)
        }
        super.onUpdate(context, appWidgetManager, appWidgetIds)
    }

    override fun onReceive(context: Context, intent: Intent) {
        val rawAction = intent.action
        val clickAction = try { intent.getStringExtra(EXTRA_CLICK_ACTION) } catch (_: Exception) { null }
        if (((rawAction != null && rawAction.startsWith("de.meinseelenfunke.app.widget.")) || clickAction != null) &&
            !de.meinseelenfunke.app.util.SecurityIntentHelper.isTrustedInternalIntent(context, intent)
        ) {
            return
        }
        super.onReceive(context, intent)

        val appContext = context.applicationContext
        ServiceLocator.ensureInitialized(appContext)
        val appWidgetManager = AppWidgetManager.getInstance(appContext)
        val componentName = ComponentName(appContext, TasksWidgetProvider::class.java)
        val appWidgetIds = appWidgetManager.getAppWidgetIds(componentName)
        val sharedPrefs = appContext.getSharedPreferences("tasks_widget_prefs", Context.MODE_PRIVATE)

        val resolvedAction = clickAction ?: rawAction

        when (resolvedAction) {
            ACTION_REFRESH -> {
                triggerBackgroundSync(appContext)
            }

            ACTION_SELECT_LIST -> {
                val appWidgetId = intent.getIntExtra(
                    AppWidgetManager.EXTRA_APPWIDGET_ID,
                    AppWidgetManager.INVALID_APPWIDGET_ID
                )
                val listId = intent.getStringExtra(EXTRA_LIST_ID)
                val listName = intent.getStringExtra(EXTRA_LIST_NAME)
                if (appWidgetId != AppWidgetManager.INVALID_APPWIDGET_ID && listId != null) {
                    sharedPrefs.edit()
                        .putString("$PREF_SELECTED_LIST_ID_PREFIX$appWidgetId", listId)
                        .putString("$PREF_SELECTED_LIST_NAME_PREFIX$appWidgetId", listName ?: "Aufgaben")
                        .apply()
                    updateAppWidget(appContext, appWidgetManager, appWidgetId)
                    appWidgetManager.notifyAppWidgetViewDataChanged(appWidgetId, R.id.tasks_widget_list)
                }
            }

            ACTION_BACK -> {
                val appWidgetId = intent.getIntExtra(
                    AppWidgetManager.EXTRA_APPWIDGET_ID,
                    AppWidgetManager.INVALID_APPWIDGET_ID
                )
                if (appWidgetId != AppWidgetManager.INVALID_APPWIDGET_ID) {
                    sharedPrefs.edit()
                        .remove("$PREF_SELECTED_LIST_ID_PREFIX$appWidgetId")
                        .remove("$PREF_SELECTED_LIST_NAME_PREFIX$appWidgetId")
                        .apply()
                    updateAppWidget(appContext, appWidgetManager, appWidgetId)
                    appWidgetManager.notifyAppWidgetViewDataChanged(appWidgetId, R.id.tasks_widget_list)
                }
            }

            ACTION_TOGGLE_TASK -> {
                val taskId = intent.getStringExtra(EXTRA_TASK_ID)
                if (!taskId.isNullOrBlank()) {
                    ServiceLocator.organizerRepository.optimisticToggleTask(taskId)
                    val pendingResult = goAsync()
                    CoroutineScope(Dispatchers.IO).launch {
                        try {
                            ServiceLocator.organizerRepository.toggleTask(taskId)
                            ServiceLocator.organizerRepository.getTaskLists()
                            ServiceLocator.organizerRepository.triggerTasksWidgetUpdate(appContext)
                        } finally {
                            pendingResult.finish()
                        }
                    }
                }
            }

            ACTION_CYCLE_PRIORITY -> {
                val taskId = intent.getStringExtra(EXTRA_TASK_ID)
                val currentPriority = intent.getStringExtra(EXTRA_CURRENT_PRIORITY) ?: "niedrig"
                if (!taskId.isNullOrBlank()) {
                    val nextPriority = when (currentPriority.lowercase()) {
                        "niedrig" -> "mittel"
                        "mittel" -> "hoch"
                        "hoch" -> "niedrig"
                        else -> "mittel"
                    }
                    optimisticUpdateTaskPriority(appContext, taskId, nextPriority)
                    val pendingResult = goAsync()
                    CoroutineScope(Dispatchers.IO).launch {
                        try {
                            ServiceLocator.organizerRepository.updateTask(taskId, priority = nextPriority)
                        } finally {
                            pendingResult.finish()
                        }
                    }
                }
            }

            ACTION_EDIT_TASK -> {
                val taskId = intent.getStringExtra(EXTRA_TASK_ID)
                if (!taskId.isNullOrBlank()) {
                    val editIntent = Intent(appContext, EditTaskWidgetActivity::class.java).apply {
                        putExtra("task_id", taskId)
                        flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
                    }
                    startActivityExempted(appContext, editIntent)
                }
            }

            ACTION_ADD_TASK_CLICK -> {
                val listId = intent.getStringExtra(EXTRA_LIST_ID)
                val addIntent = Intent(appContext, AddTaskWidgetActivity::class.java).apply {
                    if (!listId.isNullOrBlank()) {
                        putExtra("list_id", listId)
                    }
                    flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
                }
                startActivityExempted(appContext, addIntent)
            }

            ACTION_ADD_LIST_CLICK -> {
                val addListIntent = Intent(appContext, AddTaskListWidgetActivity::class.java).apply {
                    flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
                }
                startActivityExempted(appContext, addListIntent)
            }

            ACTION_RENAME_LIST -> {
                val listId = intent.getStringExtra(EXTRA_LIST_ID)
                if (!listId.isNullOrBlank()) {
                    val renameIntent = Intent(appContext, RenameTaskListWidgetActivity::class.java).apply {
                        putExtra("list_id", listId)
                        flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
                    }
                    startActivityExempted(appContext, renameIntent)
                }
            }

            ACTION_DELETE_LIST -> {
                val listId = intent.getStringExtra(EXTRA_LIST_ID)
                if (!listId.isNullOrBlank()) {
                    val confirmIntent = Intent(appContext, ConfirmDeleteListActivity::class.java).apply {
                        putExtra("list_id", listId)
                        flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
                    }
                    startActivityExempted(appContext, confirmIntent)
                }
            }

            ACTION_DELETE_TASK -> {
                val taskId = intent.getStringExtra(EXTRA_TASK_ID)
                if (!taskId.isNullOrBlank()) {
                    val pendingResult = goAsync()
                    CoroutineScope(Dispatchers.IO).launch {
                        try {
                            ServiceLocator.organizerRepository.deleteTask(taskId)
                            ServiceLocator.organizerRepository.getTasks()
                            ServiceLocator.organizerRepository.getTaskLists()
                            ServiceLocator.organizerRepository.triggerTasksWidgetUpdate(appContext)
                        } finally {
                            pendingResult.finish()
                        }
                    }
                }
            }

            AppWidgetManager.ACTION_APPWIDGET_UPDATE -> {
                appWidgetManager.notifyAppWidgetViewDataChanged(appWidgetIds, R.id.tasks_widget_list)
            }
        }
    }

    private fun optimisticUpdateTaskPriority(context: Context, taskId: String, nextPriority: String) {
        try {
            val sharedPrefs = context.getSharedPreferences("tasks_widget_prefs", Context.MODE_PRIVATE)
            val json = sharedPrefs.getString("tasks_cache", null) ?: return
            val gson = Gson()
            val type = object : TypeToken<List<ManagementTask>>() {}.type
            val tasks: List<ManagementTask> = gson.fromJson(json, type)
            val updated = tasks.map { if (it.id == taskId) it.copy(priority = nextPriority) else it }
            ServiceLocator.organizerRepository.saveTasksToCache(updated)
            ServiceLocator.organizerRepository.triggerTasksWidgetUpdate(context)
        } catch (_: Exception) {}
    }

    private fun triggerBackgroundSync(context: Context) {
        val appContext = context.applicationContext
        val pendingResult = goAsync()
        CoroutineScope(Dispatchers.IO).launch {
            try {
                ServiceLocator.organizerRepository.getTaskLists()
                ServiceLocator.organizerRepository.getTasks()
                ServiceLocator.organizerRepository.triggerTasksWidgetUpdate(appContext)
            } catch (e: Exception) {
                e.printStackTrace()
            } finally {
                pendingResult.finish()
            }
        }
    }

    private fun startActivityExempted(context: Context, intent: Intent) {
        try {
            if (Build.VERSION.SDK_INT >= 34) {
                val options = ActivityOptions.makeBasic()
                options.setPendingIntentBackgroundActivityStartMode(
                    ActivityOptions.MODE_BACKGROUND_ACTIVITY_START_ALLOWED
                )
                context.startActivity(intent, options.toBundle())
            } else {
                context.startActivity(intent)
            }
        } catch (e: Exception) {
            try {
                context.startActivity(intent)
            } catch (ex: Exception) {
                ex.printStackTrace()
            }
        }
    }

    companion object {
        const val ACTION_ITEM_CLICK = "de.meinseelenfunke.app.widget.ACTION_TASKS_ITEM_CLICK"
        const val EXTRA_CLICK_ACTION = "widget_click_action"

        const val ACTION_REFRESH = "de.meinseelenfunke.app.widget.ACTION_TASKS_REFRESH"
        const val ACTION_SELECT_LIST = "de.meinseelenfunke.app.widget.ACTION_TASKS_SELECT_LIST"
        const val ACTION_BACK = "de.meinseelenfunke.app.widget.ACTION_TASKS_BACK"
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
        const val ACTION_ADD_LIST_CLICK = "de.meinseelenfunke.app.widget.ACTION_TASKS_ADD_LIST_CLICK"
        const val ACTION_ADD_TASK_CLICK = "de.meinseelenfunke.app.widget.ACTION_TASKS_ADD_TASK_CLICK"
        const val ACTION_RENAME_LIST = "de.meinseelenfunke.app.widget.ACTION_TASKS_RENAME_LIST"
        const val ACTION_NONE = "de.meinseelenfunke.app.widget.ACTION_TASKS_NONE"

        const val EXTRA_LIST_ID = "list_id"
        const val EXTRA_LIST_NAME = "list_name"
        const val EXTRA_TASK_ID = "task_id"
        const val EXTRA_CURRENT_PRIORITY = "current_priority"
        const val EXTRA_CURRENT_DATE = "current_date"
        const val EXTRA_NEW_PRIORITY = "new_priority"

        const val PREF_SELECTED_LIST_ID_PREFIX = "widget_tasks_selected_list_id_"
        const val PREF_SELECTED_LIST_NAME_PREFIX = "widget_tasks_selected_list_name_"
        const val PREF_EDITING_TASK_ID_PREFIX = "widget_tasks_editing_task_id_"

        fun updateAppWidget(context: Context, appWidgetManager: AppWidgetManager, appWidgetId: Int) {
            val appContext = context.applicationContext
            val sharedPrefs = appContext.getSharedPreferences("tasks_widget_prefs", Context.MODE_PRIVATE)
            val selectedListId = sharedPrefs.getString("$PREF_SELECTED_LIST_ID_PREFIX$appWidgetId", null)
            val selectedListName = sharedPrefs.getString("$PREF_SELECTED_LIST_NAME_PREFIX$appWidgetId", null)

            // Parse cached lists & tasks for live subtitle counts
            val gson = Gson()
            val lists: List<ManagementTaskList> = try {
                val json = sharedPrefs.getString("task_lists_cache", null)
                if (json != null) {
                    gson.fromJson(json, object : TypeToken<List<ManagementTaskList>>() {}.type)
                } else emptyList()
            } catch (_: Exception) {
                emptyList()
            }

            val allTasks: List<ManagementTask> = try {
                val json = sharedPrefs.getString("tasks_cache", null)
                if (json != null) {
                    gson.fromJson(json, object : TypeToken<List<ManagementTask>>() {}.type)
                } else emptyList()
            } catch (_: Exception) {
                emptyList()
            }

            val views = RemoteViews(appContext.packageName, R.layout.widget_tasks_layout)

            if (selectedListId != null) {
                // Single List View Mode
                val openInList = allTasks.count {
                    it.task_list_id == selectedListId && it.parent_id == null && !it.is_completed
                }
                val totalInList = allTasks.count {
                    it.task_list_id == selectedListId && it.parent_id == null
                }

                views.setViewVisibility(R.id.widget_tasks_back_button, View.VISIBLE)
                views.setViewVisibility(R.id.widget_tasks_logo, View.GONE)
                views.setTextViewText(R.id.widget_tasks_title, (selectedListName ?: "Aufgaben").uppercase())
                views.setTextViewText(
                    R.id.widget_tasks_subtitle,
                    "$openInList offen (von $totalInList) · ← Alle Listen"
                )
                views.setTextViewText(R.id.tasks_widget_empty_view, "Keine Aufgaben in dieser Liste")

                // Back button & Title click -> Return to Lists Overview
                val backIntent = Intent(appContext, TasksWidgetProvider::class.java).apply {
                    action = ACTION_BACK
                    putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
                    data = Uri.parse("funkenwerk://tasks/back/$appWidgetId")
                    de.meinseelenfunke.app.util.SecurityIntentHelper.signIntent(appContext, this)
                }
                val backPendingIntent = PendingIntent.getBroadcast(
                    appContext,
                    appWidgetId * 100 + 1,
                    backIntent,
                    PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
                )
                views.setOnClickPendingIntent(R.id.widget_tasks_back_button, backPendingIntent)
                views.setOnClickPendingIntent(R.id.widget_tasks_title_click_area, backPendingIntent)

                // Header '+' Button -> Directly open AddTaskWidgetActivity for this list
                val addTaskIntent = Intent(appContext, AddTaskWidgetActivity::class.java).apply {
                    putExtra("list_id", selectedListId)
                    data = Uri.parse("funkenwerk://tasks/add_task/$appWidgetId/$selectedListId")
                    flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
                }
                val addTaskPendingIntent = PendingIntent.getActivity(
                    appContext,
                    appWidgetId * 100 + 2,
                    addTaskIntent,
                    PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
                )
                views.setOnClickPendingIntent(R.id.widget_tasks_add_button, addTaskPendingIntent)
            } else {
                // Lists Overview Mode
                val totalOpen = allTasks.count { it.parent_id == null && !it.is_completed }
                views.setViewVisibility(R.id.widget_tasks_back_button, View.GONE)
                views.setViewVisibility(R.id.widget_tasks_logo, View.VISIBLE)
                views.setTextViewText(R.id.widget_tasks_title, "AUFGABEN")
                views.setTextViewText(
                    R.id.widget_tasks_subtitle,
                    if (lists.isNotEmpty()) "${lists.size} Listen · $totalOpen offen" else "Listen-Übersicht"
                )
                views.setTextViewText(R.id.tasks_widget_empty_view, "Keine Aufgabenlisten vorhanden")

                // Header Title / Logo -> Open App in Organizer -> Aufgaben (SubTab 0)
                val openAppIntent = Intent(appContext, MainActivity::class.java).apply {
                    putExtra("open_tab", 2)
                    putExtra("open_subtab", 0)
                    data = Uri.parse("funkenwerk://tasks/open_app/$appWidgetId")
                    flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
                    de.meinseelenfunke.app.util.SecurityIntentHelper.signIntent(appContext, this)
                }
                val openAppPendingIntent = PendingIntent.getActivity(
                    appContext,
                    appWidgetId * 100 + 3,
                    openAppIntent,
                    PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
                )
                views.setOnClickPendingIntent(R.id.widget_tasks_title_click_area, openAppPendingIntent)
                views.setOnClickPendingIntent(R.id.widget_tasks_logo, openAppPendingIntent)

                // Header '+' Button -> Directly open AddTaskListWidgetActivity
                val addListIntent = Intent(appContext, AddTaskListWidgetActivity::class.java).apply {
                    data = Uri.parse("funkenwerk://tasks/add_list/$appWidgetId")
                    flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
                }
                val addListPendingIntent = PendingIntent.getActivity(
                    appContext,
                    appWidgetId * 100 + 4,
                    addListIntent,
                    PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
                )
                views.setOnClickPendingIntent(R.id.widget_tasks_add_button, addListPendingIntent)
            }

            // Refresh Button -> Background API Sync
            val refreshIntent = Intent(appContext, TasksWidgetProvider::class.java).apply {
                action = ACTION_REFRESH
                putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
                data = Uri.parse("funkenwerk://tasks/refresh/$appWidgetId")
                de.meinseelenfunke.app.util.SecurityIntentHelper.signIntent(appContext, this)
            }
            val refreshPendingIntent = PendingIntent.getBroadcast(
                appContext,
                appWidgetId * 100 + 5,
                refreshIntent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )
            views.setOnClickPendingIntent(R.id.widget_tasks_refresh_button, refreshPendingIntent)

            // RemoteViews Service Adapter for ListView
            val serviceIntent = Intent(appContext, TasksWidgetService::class.java).apply {
                putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
                data = Uri.parse("funkenwerk://tasks/service/$appWidgetId/${selectedListId ?: "all"}")
            }
            views.setRemoteAdapter(R.id.tasks_widget_list, serviceIntent)
            views.setEmptyView(R.id.tasks_widget_list, R.id.tasks_widget_empty_view)

            // Mutable PendingIntent Template for ListView Item Clicks (Android 14-17 BAL compliant via Trampoline Activity)
            val clickIntent = Intent(appContext, TasksWidgetActionActivity::class.java).apply {
                action = ACTION_ITEM_CLICK
                putExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, appWidgetId)
                data = Uri.parse("funkenwerk://tasks/item_click/$appWidgetId")
                flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
                de.meinseelenfunke.app.util.SecurityIntentHelper.signIntent(appContext, this)
            }
            val clickPendingIntent = PendingIntent.getActivity(
                appContext,
                appWidgetId * 100 + 6,
                clickIntent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_MUTABLE
            )
            views.setPendingIntentTemplate(R.id.tasks_widget_list, clickPendingIntent)

            appWidgetManager.updateAppWidget(appWidgetId, views)
        }
    }
}
