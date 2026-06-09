package de.meinseelenfunke.app.data.repository

import android.content.Context
import de.meinseelenfunke.app.data.api.CalendarEvent
import de.meinseelenfunke.app.data.api.ManagementDayRoutine
import de.meinseelenfunke.app.data.api.ManagementDayRoutineStep
import de.meinseelenfunke.app.data.api.ManagementTask
import de.meinseelenfunke.app.data.api.ManagementTaskList
import de.meinseelenfunke.app.data.api.ManagementShoppingItem
import de.meinseelenfunke.app.di.ServiceLocator
import okhttp3.MediaType.Companion.toMediaTypeOrNull
import okhttp3.MultipartBody
import okhttp3.RequestBody.Companion.toRequestBody

class OrganizerRepository(private val serviceLocator: ServiceLocator) {

    suspend fun getTaskLists(): Result<List<ManagementTaskList>> {
        return try {
            val response = serviceLocator.getOrganizerApi().getTaskLists()
            if (response.success) {
                saveTaskListsToCache(response.data)
                triggerTasksWidgetUpdate(serviceLocator.context)
                Result.success(response.data)
            } else {
                Result.failure(Exception("Konnte Aufgabenlisten nicht laden."))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun getTasks(): Result<List<ManagementTask>> {
        return try {
            val response = serviceLocator.getOrganizerApi().getTasks()
            if (response.success) {
                saveTasksToCache(response.data)
                triggerTasksWidgetUpdate(serviceLocator.context)
                Result.success(response.data)
            } else {
                Result.failure(Exception("Konnte Aufgaben nicht laden."))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun toggleTask(id: String): Result<ManagementTask> {
        return try {
            val response = serviceLocator.getOrganizerApi().toggleTask(id)
            if (response.success) {
                updateCachedTask(response.data)
                Result.success(response.data)
            } else {
                Result.failure(Exception("Konnte Aufgabe nicht umstellen."))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun updateTask(
        id: String,
        title: String? = null,
        priority: String? = null,
        isCompleted: Boolean? = null,
        relevantFrom: String? = null
    ): Result<ManagementTask> {
        return try {
            val response = serviceLocator.getOrganizerApi().updateTask(id, title, priority, isCompleted, relevantFrom)
            if (response.success) {
                updateCachedTask(response.data)
                Result.success(response.data)
            } else {
                Result.failure(Exception("Konnte Aufgabe nicht aktualisieren."))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun addTask(listId: String, title: String, priority: String = "niedrig"): Result<ManagementTask> {
        return try {
            val response = serviceLocator.getOrganizerApi().addTask(listId, title, priority)
            if (response.success) {
                Result.success(response.data)
            } else {
                Result.failure(Exception("Konnte Aufgabe nicht hinzufügen."))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun deleteTask(id: String): Result<Unit> {
        return try {
            val response = serviceLocator.getOrganizerApi().deleteTask(id)
            if (response.success) {
                Result.success(Unit)
            } else {
                Result.failure(Exception("Konnte Aufgabe nicht löschen."))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun deleteTaskList(id: String): Result<Unit> {
        return try {
            val response = serviceLocator.getOrganizerApi().deleteTaskList(id)
            if (response.success) {
                Result.success(Unit)
            } else {
                Result.failure(Exception("Konnte Aufgabenliste nicht löschen."))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun addTaskList(name: String, icon: String? = "bookmark"): Result<ManagementTaskList> {
        return try {
            val response = serviceLocator.getOrganizerApi().addTaskList(name, icon)
            if (response.success) {
                Result.success(response.data)
            } else {
                Result.failure(Exception("Konnte Aufgabenliste nicht hinzufügen."))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun getRoutines(): Result<List<ManagementDayRoutine>> {
        return try {
            val response = serviceLocator.getOrganizerApi().getRoutines()
            if (response.success) {
                Result.success(response.data)
            } else {
                Result.failure(Exception("Konnte Routinen nicht laden."))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun createRoutine(
        title: String,
        message: String?,
        durationMinutes: Int,
        startTime: String,
        icon: String? = null
    ): Result<ManagementDayRoutine> {
        return try {
            val response = serviceLocator.getOrganizerApi().createRoutine(title, message, durationMinutes, startTime, icon)
            if (response.success) {
                Result.success(response.data)
            } else {
                Result.failure(Exception("Konnte Routine nicht erstellen."))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun updateRoutine(
        id: String,
        title: String? = null,
        message: String? = null,
        durationMinutes: Int? = null,
        startTime: String? = null,
        icon: String? = null,
        isActive: Boolean? = null
    ): Result<ManagementDayRoutine> {
        return try {
            val response = serviceLocator.getOrganizerApi().updateRoutine(id, title, message, durationMinutes, startTime, icon, isActive)
            if (response.success) {
                Result.success(response.data)
            } else {
                Result.failure(Exception("Konnte Routine nicht aktualisieren."))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun deleteRoutine(id: String): Result<Unit> {
        return try {
            val response = serviceLocator.getOrganizerApi().deleteRoutine(id)
            if (response.success) {
                Result.success(Unit)
            } else {
                Result.failure(Exception("Konnte Routine nicht löschen."))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun addRoutineStep(routineId: String, title: String, durationMinutes: Int): Result<ManagementDayRoutineStep> {
        return try {
            val response = serviceLocator.getOrganizerApi().addRoutineStep(routineId, title, durationMinutes)
            if (response.success) {
                Result.success(response.data)
            } else {
                Result.failure(Exception("Konnte Routine-Schritt nicht hinzufügen."))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun deleteRoutineStep(stepId: String): Result<Unit> {
        return try {
            val response = serviceLocator.getOrganizerApi().deleteRoutineStep(stepId)
            if (response.success) {
                Result.success(Unit)
            } else {
                Result.failure(Exception("Konnte Routine-Schritt nicht löschen."))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    fun saveShoppingItemsToCache(items: List<ManagementShoppingItem>) {
        try {
            val context = serviceLocator.context
            val sharedPrefs = context.getSharedPreferences("shopping_widget_prefs", android.content.Context.MODE_PRIVATE)
            val json = com.google.gson.Gson().toJson(items)
            sharedPrefs.edit().putString("shopping_items_cache", json).apply()
        } catch (e: Exception) {
            de.meinseelenfunke.app.util.AppLogger.error(serviceLocator.context, "OrganizerRepo", "saveShoppingItemsToCache error", e)
        }
    }

    private fun updateCachedItem(updatedItem: ManagementShoppingItem) {
        try {
            val context = serviceLocator.context
            val sharedPrefs = context.getSharedPreferences("shopping_widget_prefs", android.content.Context.MODE_PRIVATE)
            val json = sharedPrefs.getString("shopping_items_cache", null)
            if (json != null) {
                val type = object : com.google.gson.reflect.TypeToken<List<ManagementShoppingItem>>() {}.type
                val items: List<ManagementShoppingItem> = com.google.gson.Gson().fromJson(json, type)
                val updatedList = items.map { if (it.id == updatedItem.id) updatedItem else it }
                saveShoppingItemsToCache(updatedList)
                triggerShoppingWidgetUpdate(context)
            }
        } catch (e: Exception) {
            de.meinseelenfunke.app.util.AppLogger.error(serviceLocator.context, "OrganizerRepo", "updateCachedItem error", e)
        }
    }

    private fun addCachedItem(newItem: ManagementShoppingItem) {
        try {
            val context = serviceLocator.context
            val sharedPrefs = context.getSharedPreferences("shopping_widget_prefs", android.content.Context.MODE_PRIVATE)
            val json = sharedPrefs.getString("shopping_items_cache", null)
            val items: List<ManagementShoppingItem> = if (json != null) {
                val type = object : com.google.gson.reflect.TypeToken<List<ManagementShoppingItem>>() {}.type
                com.google.gson.Gson().fromJson(json, type)
            } else {
                emptyList()
            }
            val updatedList = listOf(newItem) + items
            saveShoppingItemsToCache(updatedList)
            triggerShoppingWidgetUpdate(context)
        } catch (e: Exception) {
            de.meinseelenfunke.app.util.AppLogger.error(serviceLocator.context, "OrganizerRepo", "addCachedItem error", e)
        }
    }

    private fun deleteCachedItem(id: String) {
        try {
            val context = serviceLocator.context
            val sharedPrefs = context.getSharedPreferences("shopping_widget_prefs", android.content.Context.MODE_PRIVATE)
            val json = sharedPrefs.getString("shopping_items_cache", null)
            if (json != null) {
                val type = object : com.google.gson.reflect.TypeToken<List<ManagementShoppingItem>>() {}.type
                val items: List<ManagementShoppingItem> = com.google.gson.Gson().fromJson(json, type)
                val updatedList = items.filter { it.id != id }
                saveShoppingItemsToCache(updatedList)
                triggerShoppingWidgetUpdate(context)
            }
        } catch (e: Exception) {
            de.meinseelenfunke.app.util.AppLogger.error(serviceLocator.context, "OrganizerRepo", "deleteCachedItem error", e)
        }
    }

    fun optimisticToggleShoppingItem(id: String) {
        try {
            val context = serviceLocator.context
            val sharedPrefs = context.getSharedPreferences("shopping_widget_prefs", android.content.Context.MODE_PRIVATE)
            val json = sharedPrefs.getString("shopping_items_cache", null)
            if (json != null) {
                val type = object : com.google.gson.reflect.TypeToken<List<ManagementShoppingItem>>() {}.type
                val items: List<ManagementShoppingItem> = com.google.gson.Gson().fromJson(json, type)
                val updatedList = items.map { item ->
                    if (item.id == id) {
                        val newStatus = if (item.status == "needed") "stocked" else "needed"
                        item.copy(status = newStatus)
                    } else {
                        item
                    }
                }
                saveShoppingItemsToCache(updatedList)
                triggerShoppingWidgetUpdate(context)
            }
        } catch (e: Exception) {
            de.meinseelenfunke.app.util.AppLogger.error(serviceLocator.context, "OrganizerRepo", "optimisticToggle error", e)
        }
    }

    fun triggerShoppingWidgetUpdate(context: Context) {
        val appWidgetManager = android.appwidget.AppWidgetManager.getInstance(context)
        val componentName = android.content.ComponentName(context, de.meinseelenfunke.app.widget.ShoppingListWidgetProvider::class.java)
        val ids = appWidgetManager.getAppWidgetIds(componentName)
        if (ids.isNotEmpty()) {
            for (id in ids) {
                de.meinseelenfunke.app.widget.ShoppingListWidgetProvider.updateAppWidget(context, appWidgetManager, id)
            }
            appWidgetManager.notifyAppWidgetViewDataChanged(ids, de.meinseelenfunke.app.R.id.shopping_widget_list)
        }
    }

    fun saveTaskListsToCache(lists: List<ManagementTaskList>) {
        try {
            val context = serviceLocator.context
            val sharedPrefs = context.getSharedPreferences("tasks_widget_prefs", android.content.Context.MODE_PRIVATE)
            val json = com.google.gson.Gson().toJson(lists)
            sharedPrefs.edit().putString("task_lists_cache", json).apply()
        } catch (e: Exception) {
            de.meinseelenfunke.app.util.AppLogger.error(serviceLocator.context, "OrganizerRepo", "saveTaskListsToCache error", e)
        }
    }

    fun saveTasksToCache(tasks: List<ManagementTask>) {
        try {
            val context = serviceLocator.context
            val sharedPrefs = context.getSharedPreferences("tasks_widget_prefs", android.content.Context.MODE_PRIVATE)
            val json = com.google.gson.Gson().toJson(tasks)
            sharedPrefs.edit().putString("tasks_cache", json).apply()
        } catch (e: Exception) {
            de.meinseelenfunke.app.util.AppLogger.error(serviceLocator.context, "OrganizerRepo", "saveTasksToCache error", e)
        }
    }

    private fun updateCachedTask(updatedTask: ManagementTask) {
        try {
            val context = serviceLocator.context
            val sharedPrefs = context.getSharedPreferences("tasks_widget_prefs", android.content.Context.MODE_PRIVATE)
            val json = sharedPrefs.getString("tasks_cache", null)
            if (json != null) {
                val type = object : com.google.gson.reflect.TypeToken<List<ManagementTask>>() {}.type
                val items: List<ManagementTask> = com.google.gson.Gson().fromJson(json, type)
                val updatedList = items.map { if (it.id == updatedTask.id) updatedTask else it }
                saveTasksToCache(updatedList)
                triggerTasksWidgetUpdate(context)
            }
        } catch (e: Exception) {
            de.meinseelenfunke.app.util.AppLogger.error(serviceLocator.context, "OrganizerRepo", "updateCachedTask error", e)
        }
    }

    private fun addCachedTask(newTask: ManagementTask) {
        try {
            val context = serviceLocator.context
            val sharedPrefs = context.getSharedPreferences("tasks_widget_prefs", android.content.Context.MODE_PRIVATE)
            val json = sharedPrefs.getString("tasks_cache", null)
            val items: List<ManagementTask> = if (json != null) {
                val type = object : com.google.gson.reflect.TypeToken<List<ManagementTask>>() {}.type
                com.google.gson.Gson().fromJson(json, type)
            } else {
                emptyList()
            }
            val updatedList = items + newTask
            saveTasksToCache(updatedList)
            triggerTasksWidgetUpdate(context)
        } catch (e: Exception) {
            de.meinseelenfunke.app.util.AppLogger.error(serviceLocator.context, "OrganizerRepo", "addCachedTask error", e)
        }
    }


    fun optimisticToggleTask(id: String) {
        try {
            val context = serviceLocator.context
            val sharedPrefs = context.getSharedPreferences("tasks_widget_prefs", android.content.Context.MODE_PRIVATE)
            val json = sharedPrefs.getString("tasks_cache", null)
            if (json != null) {
                val type = object : com.google.gson.reflect.TypeToken<List<ManagementTask>>() {}.type
                val items: List<ManagementTask> = com.google.gson.Gson().fromJson(json, type)
                val updatedList = items.map { item ->
                    if (item.id == id) {
                        item.copy(is_completed = !item.is_completed)
                    } else {
                        item
                    }
                }
                saveTasksToCache(updatedList)
                triggerTasksWidgetUpdate(context)
            }
        } catch (e: Exception) {
            de.meinseelenfunke.app.util.AppLogger.error(serviceLocator.context, "OrganizerRepo", "optimisticToggleTask error", e)
        }
    }

    fun triggerTasksWidgetUpdate(context: Context) {
        val appWidgetManager = android.appwidget.AppWidgetManager.getInstance(context)
        val componentName = android.content.ComponentName(context, de.meinseelenfunke.app.widget.TasksWidgetProvider::class.java)
        val ids = appWidgetManager.getAppWidgetIds(componentName)
        if (ids.isNotEmpty()) {
            for (id in ids) {
                de.meinseelenfunke.app.widget.TasksWidgetProvider.updateAppWidget(context, appWidgetManager, id)
            }
            appWidgetManager.notifyAppWidgetViewDataChanged(ids, de.meinseelenfunke.app.R.id.tasks_widget_list)
        }
    }

    suspend fun getShoppingItems(): Result<List<ManagementShoppingItem>> {
        return try {
            val response = serviceLocator.getOrganizerApi().getShoppingItems()
            if (response.success) {
                saveShoppingItemsToCache(response.data)
                triggerShoppingWidgetUpdate(serviceLocator.context)
                Result.success(response.data)
            } else {
                Result.failure(Exception("Konnte Einkaufsliste nicht laden."))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun toggleShoppingItem(id: String): Result<ManagementShoppingItem> {
        return try {
            val response = serviceLocator.getOrganizerApi().toggleShoppingItem(id)
            if (response.success) {
                updateCachedItem(response.data)
                Result.success(response.data)
            } else {
                Result.failure(Exception("Konnte Einkaufsartikel nicht umstellen."))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun addShoppingItem(name: String, categoryId: String? = null): Result<ManagementShoppingItem> {
        return try {
            val response = serviceLocator.getOrganizerApi().addShoppingItem(name, categoryId)
            if (response.success) {
                addCachedItem(response.data)
                Result.success(response.data)
            } else {
                Result.failure(Exception("Konnte Einkaufsartikel nicht hinzufügen."))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun deleteShoppingItem(id: String): Result<Unit> {
        return try {
            val response = serviceLocator.getOrganizerApi().deleteShoppingItem(id)
            if (response.success) {
                deleteCachedItem(id)
                Result.success(Unit)
            } else {
                Result.failure(Exception("Konnte Einkaufsartikel nicht löschen."))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun getCalendarEvents(): Result<List<CalendarEvent>> {
        val context = serviceLocator.context
        de.meinseelenfunke.app.util.AppLogger.info(context, "OrganizerRepo", "getCalendarEvents: requesting calendar events from API")
        return try {
            val response = serviceLocator.getOrganizerApi().getCalendarEvents()
            if (response.success) {
                val events = response.data
                de.meinseelenfunke.app.util.AppLogger.info(context, "OrganizerRepo", "getCalendarEvents: successfully loaded ${events.size} events")
                saveCalendarEventsToCache(events)
                Result.success(events)
            } else {
                val err = Exception("Server error response: success = false")
                de.meinseelenfunke.app.util.AppLogger.error(context, "OrganizerRepo", "getCalendarEvents failed: API success false", err)
                Result.failure(err)
            }
        } catch (e: Exception) {
            de.meinseelenfunke.app.util.AppLogger.error(context, "OrganizerRepo", "getCalendarEvents exception", e)
            Result.failure(e)
        }
    }

    private fun saveCalendarEventsToCache(events: List<CalendarEvent>) {
        try {
            val context = serviceLocator.context
            val sharedPrefs = context.getSharedPreferences("calendar_widget_prefs", android.content.Context.MODE_PRIVATE)

            // Reschedule local alarms (looks up the old cache in SharedPreferences before we write the new one)
            de.meinseelenfunke.app.util.CalendarAlarmScheduler.scheduleAlarmsForEvents(context, events)

            // Save new cache
            val json = com.google.gson.Gson().toJson(events)
            sharedPrefs.edit().putString("calendar_events_cache", json).apply()

            // Trigger Widget Update
            val widgetIntent = android.content.Intent(context, de.meinseelenfunke.app.widget.CalendarAppWidgetProvider::class.java).apply {
                action = android.appwidget.AppWidgetManager.ACTION_APPWIDGET_UPDATE
            }
            val appWidgetManager = android.appwidget.AppWidgetManager.getInstance(context)
            val componentName = android.content.ComponentName(context, de.meinseelenfunke.app.widget.CalendarAppWidgetProvider::class.java)
            val ids = appWidgetManager.getAppWidgetIds(componentName)
            if (ids.isNotEmpty()) {
                widgetIntent.putExtra(android.appwidget.AppWidgetManager.EXTRA_APPWIDGET_IDS, ids)
                context.sendBroadcast(widgetIntent)
                appWidgetManager.notifyAppWidgetViewDataChanged(ids, de.meinseelenfunke.app.R.id.widget_list)
            }

            // Trigger Month Widget Update
            val monthWidgetIntent = android.content.Intent(context, de.meinseelenfunke.app.widget.CalendarMonthWidgetProvider::class.java).apply {
                action = android.appwidget.AppWidgetManager.ACTION_APPWIDGET_UPDATE
            }
            val monthComponentName = android.content.ComponentName(context, de.meinseelenfunke.app.widget.CalendarMonthWidgetProvider::class.java)
            val monthIds = appWidgetManager.getAppWidgetIds(monthComponentName)
            if (monthIds.isNotEmpty()) {
                monthWidgetIntent.putExtra(android.appwidget.AppWidgetManager.EXTRA_APPWIDGET_IDS, monthIds)
                context.sendBroadcast(monthWidgetIntent)
            }
        } catch (e: Exception) {
            android.util.Log.e("OrganizerRepository", "Failed to cache calendar events", e)
        }
    }

    suspend fun addCalendarEvent(
        title: String,
        start: String,
        end: String?,
        isAllDay: Boolean,
        category: String,
        description: String?,
        recurrence: String? = null,
        reminderMinutes: Int? = null,
        priority: String? = null,
        sendEmail: Boolean = false
    ): Result<Unit> {
        return try {
            val response = serviceLocator.getOrganizerApi().addCalendarEvent(
                title, start, end, if (isAllDay) 1 else 0, category, description, recurrence, reminderMinutes, priority, if (sendEmail) 1 else 0
            )
            if (response.success) {
                Result.success(Unit)
            } else {
                Result.failure(Exception("Konnte Termin nicht hinzufügen."))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun deleteCalendarEvent(id: String): Result<Unit> {
        return try {
            val response = serviceLocator.getOrganizerApi().deleteCalendarEvent(id)
            if (response.success) {
                Result.success(Unit)
            } else {
                Result.failure(Exception("Konnte Termin nicht löschen."))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun updateCalendarEvent(
        id: String,
        title: String,
        start: String,
        end: String?,
        isAllDay: Boolean,
        category: String,
        description: String?,
        recurrence: String? = null,
        reminderMinutes: Int? = null,
        priority: String? = null
    ): Result<Unit> {
        return try {
            val response = serviceLocator.getOrganizerApi().updateCalendarEvent(
                id, title, start, end, if (isAllDay) 1 else 0, category, description, recurrence, reminderMinutes, priority
            )
            if (response.success) {
                Result.success(Unit)
            } else {
                Result.failure(Exception("Konnte Termin nicht anpassen."))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun addSubtask(parentId: String, title: String): Result<ManagementTask> {
        return try {
            val response = serviceLocator.getOrganizerApi().addSubtask(parentId, title)
            if (response.success) {
                addCachedTask(response.data)
                Result.success(response.data)
            } else {
                Result.failure(Exception("Konnte Unteraufgabe nicht hinzufügen."))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun uploadTaskFile(
        id: String,
        fileBytes: ByteArray,
        fileName: String,
        mimeType: String
    ): Result<ManagementTask> {
        return try {
            val requestFile = fileBytes.toRequestBody(mimeType.toMediaTypeOrNull())
            val filePart = MultipartBody.Part.createFormData("file", fileName, requestFile)
            val response = serviceLocator.getOrganizerApi().uploadTaskFile(id, filePart)
            if (response.success) {
                updateCachedTask(response.data)
                Result.success(response.data)
            } else {
                Result.failure(Exception("Konnte Datei nicht hochladen."))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun deleteTaskFile(id: String, filePath: String): Result<ManagementTask> {
        return try {
            val response = serviceLocator.getOrganizerApi().deleteTaskFile(id, filePath)
            if (response.success) {
                updateCachedTask(response.data)
                Result.success(response.data)
            } else {
                Result.failure(Exception("Konnte Datei nicht löschen."))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
}
