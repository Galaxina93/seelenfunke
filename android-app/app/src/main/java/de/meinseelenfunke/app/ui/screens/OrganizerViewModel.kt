package de.meinseelenfunke.app.ui.screens

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import de.meinseelenfunke.app.data.api.CalendarEvent
import de.meinseelenfunke.app.data.api.ManagementDayRoutine
import de.meinseelenfunke.app.data.api.ManagementDayRoutineStep
import de.meinseelenfunke.app.data.api.ManagementTask
import de.meinseelenfunke.app.data.api.ManagementTaskList
import de.meinseelenfunke.app.data.api.ManagementShoppingItem
import de.meinseelenfunke.app.di.ServiceLocator
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import kotlinx.coroutines.async
import kotlinx.coroutines.awaitAll

sealed class OrganizerUiState {
    object Loading : OrganizerUiState()
    object Success : OrganizerUiState()
    data class Error(val message: String) : OrganizerUiState()
}

class OrganizerViewModel : ViewModel() {

    private val repository = ServiceLocator.organizerRepository

    private val _uiState = MutableStateFlow<OrganizerUiState>(OrganizerUiState.Loading)
    val uiState: StateFlow<OrganizerUiState> = _uiState.asStateFlow()

    private val _taskLists = MutableStateFlow<List<ManagementTaskList>>(emptyList())
    val taskLists: StateFlow<List<ManagementTaskList>> = _taskLists.asStateFlow()

    private val _tasks = MutableStateFlow<List<ManagementTask>>(emptyList())
    val tasks: StateFlow<List<ManagementTask>> = _tasks.asStateFlow()

    private val _routines = MutableStateFlow<List<ManagementDayRoutine>>(emptyList())
    val routines: StateFlow<List<ManagementDayRoutine>> = _routines.asStateFlow()

    private val _shoppingItems = MutableStateFlow<List<ManagementShoppingItem>>(emptyList())
    val shoppingItems: StateFlow<List<ManagementShoppingItem>> = _shoppingItems.asStateFlow()

    private val _calendarEvents = MutableStateFlow<List<CalendarEvent>>(emptyList())
    val calendarEvents: StateFlow<List<CalendarEvent>> = _calendarEvents.asStateFlow()

    private val _isProcessingFile = MutableStateFlow(false)
    val isProcessingFile: StateFlow<Boolean> = _isProcessingFile.asStateFlow()

    init {
        loadAllOrganizerData()
    }

    fun loadAllOrganizerData(showLoading: Boolean = true) {
        viewModelScope.launch {
            if (showLoading) {
                _uiState.value = OrganizerUiState.Loading
            }
            try {
                var errorMsg: String? = null
                
                repository.getTaskLists()
                    .onSuccess { _taskLists.value = it }
                    .onFailure { 
                        android.util.Log.e("OrganizerViewModel", "TaskLists load failed", it)
                        errorMsg = "Task-Listen konnten nicht geladen werden: " + it.localizedMessage 
                    }
                
                repository.getTasks()
                    .onSuccess { _tasks.value = it }
                    .onFailure { 
                        android.util.Log.e("OrganizerViewModel", "Tasks load failed", it)
                        if (errorMsg == null) errorMsg = "Aufgaben konnten nicht geladen werden: " + it.localizedMessage 
                    }
                
                repository.getRoutines()
                    .onSuccess { 
                        _routines.value = it 
                        android.util.Log.d("OrganizerViewModel", "Routines loaded successfully: ${it.size} items")
                    }
                .onFailure { 
                    android.util.Log.e("OrganizerViewModel", "Routines load failed", it)
                    if (errorMsg == null) errorMsg = "Routinen konnten nicht geladen werden: " + it.localizedMessage 
                }
            
            repository.getShoppingItems()
                .onSuccess { _shoppingItems.value = it }
                .onFailure { 
                    android.util.Log.e("OrganizerViewModel", "Shopping items load failed", it)
                    if (errorMsg == null) errorMsg = "Einkaufsliste konnte nicht geladen werden: " + it.localizedMessage 
                }
            
            repository.getCalendarEvents()
                .onSuccess { _calendarEvents.value = it }
                .onFailure { 
                    android.util.Log.e("OrganizerViewModel", "Calendar events load failed", it)
                    if (errorMsg == null) errorMsg = "Kalender konnte nicht geladen werden: " + it.localizedMessage 
                }

            if (errorMsg != null) {
                _uiState.value = OrganizerUiState.Error(errorMsg!!)
            } else {
                _uiState.value = OrganizerUiState.Success
            }
        } catch (e: Exception) {
            android.util.Log.e("OrganizerViewModel", "Exception in loadAllOrganizerData", e)
            _uiState.value = OrganizerUiState.Error(
                e.localizedMessage ?: "Fehler beim Laden des Organizers."
            )
        }
    }
}

    // --- Tasks actions ---
    fun toggleTask(id: String) {
        viewModelScope.launch {
            repository.toggleTask(id).onSuccess {
                loadAllOrganizerData(showLoading = false)
            }
        }
    }

    fun updateTask(
        id: String,
        title: String? = null,
        priority: String? = null,
        isCompleted: Boolean? = null,
        relevantFrom: String? = null
    ) {
        viewModelScope.launch {
            repository.updateTask(id, title, priority, isCompleted, relevantFrom).onSuccess {
                loadAllOrganizerData(showLoading = false)
            }
        }
    }

    fun addTask(listId: String, title: String) {
        if (title.isBlank()) return
        viewModelScope.launch {
            repository.addTask(listId, title).onSuccess {
                loadAllOrganizerData(showLoading = false)
            }
        }
    }

    fun deleteTask(id: String) {
        viewModelScope.launch {
            repository.deleteTask(id).onSuccess {
                loadAllOrganizerData(showLoading = false)
            }
        }
    }

    fun uploadTaskFile(id: String, fileBytes: ByteArray, fileName: String, mimeType: String) {
        viewModelScope.launch {
            repository.uploadTaskFile(id, fileBytes, fileName, mimeType).onSuccess {
                loadAllOrganizerData(showLoading = false)
            }
        }
    }

    fun deleteTaskFile(id: String, filePath: String) {
        viewModelScope.launch {
            repository.deleteTaskFile(id, filePath).onSuccess {
                loadAllOrganizerData(showLoading = false)
            }
        }
    }

    fun deleteTaskList(id: String) {
        viewModelScope.launch {
            repository.deleteTaskList(id).onSuccess {
                loadAllOrganizerData(showLoading = false)
            }
        }
    }

    fun addTaskList(name: String, icon: String? = "bookmark") {
        if (name.isBlank()) return
        viewModelScope.launch {
            repository.addTaskList(name, icon).onSuccess {
                loadAllOrganizerData(showLoading = false)
            }
        }
    }

    fun updateTaskList(id: String, name: String, icon: String? = null) {
        if (name.isBlank()) return
        viewModelScope.launch {
            repository.updateTaskList(id, name, icon).onSuccess {
                loadAllOrganizerData(showLoading = false)
            }
        }
    }

    // --- Shopping Actions ---
    fun toggleShoppingItem(id: String) {
        viewModelScope.launch {
            repository.toggleShoppingItem(id).onSuccess {
                loadAllOrganizerData(showLoading = false)
            }
        }
    }

    fun addShoppingItem(name: String) {
        if (name.isBlank()) return
        viewModelScope.launch {
            repository.addShoppingItem(name).onSuccess {
                loadAllOrganizerData(showLoading = false)
            }
        }
    }

    fun deleteShoppingItem(id: String) {
        viewModelScope.launch {
            repository.deleteShoppingItem(id).onSuccess {
                loadAllOrganizerData(showLoading = false)
            }
        }
    }

    // --- Calendar Actions ---
    fun addCalendarEvent(
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
    ) {
        if (title.isBlank()) return
        viewModelScope.launch {
            repository.addCalendarEvent(title, start, end, isAllDay, category, description, recurrence, reminderMinutes, priority, sendEmail).onSuccess {
                loadAllOrganizerData(showLoading = false)
            }
        }
    }

    fun deleteCalendarEvent(id: String) {
        viewModelScope.launch {
            repository.deleteCalendarEvent(id).onSuccess {
                loadAllOrganizerData(showLoading = false)
            }
        }
    }

    fun updateCalendarEvent(
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
    ) {
        if (title.isBlank()) return
        viewModelScope.launch {
            repository.updateCalendarEvent(id, title, start, end, isAllDay, category, description, recurrence, reminderMinutes, priority).onSuccess {
                loadAllOrganizerData(showLoading = false)
            }
        }
    }

    // --- Routine Actions ---
    fun createRoutine(title: String, message: String?, durationMinutes: Int, startTime: String, icon: String? = null) {
        if (title.isBlank()) return
        viewModelScope.launch {
            repository.createRoutine(title, message, durationMinutes, startTime, icon).onSuccess {
                loadAllOrganizerData(showLoading = false)
            }
        }
    }

    fun updateRoutine(id: String, title: String? = null, message: String? = null, durationMinutes: Int? = null, startTime: String? = null, icon: String? = null, isActive: Boolean? = null) {
        viewModelScope.launch {
            repository.updateRoutine(id, title, message, durationMinutes, startTime, icon, isActive).onSuccess {
                loadAllOrganizerData(showLoading = false)
            }
        }
    }

    fun deleteRoutine(id: String) {
        viewModelScope.launch {
            repository.deleteRoutine(id).onSuccess {
                loadAllOrganizerData(showLoading = false)
            }
        }
    }

    fun addRoutineStep(routineId: String, title: String, durationMinutes: Int) {
        if (title.isBlank()) return
        viewModelScope.launch {
            repository.addRoutineStep(routineId, title, durationMinutes).onSuccess {
                loadAllOrganizerData(showLoading = false)
            }
        }
    }

    fun deleteRoutineStep(stepId: String) {
        viewModelScope.launch {
            repository.deleteRoutineStep(stepId).onSuccess {
                loadAllOrganizerData(showLoading = false)
            }
        }
    }

    // --- Subtask Actions ---
    fun addSubtask(parentId: String, title: String) {
        if (title.isBlank()) return
        viewModelScope.launch {
            repository.addSubtask(parentId, title).onSuccess {
                loadAllOrganizerData(showLoading = false)
            }
        }
    }

    fun importCalendarEventsFromIcs(
        context: android.content.Context,
        uri: android.net.Uri,
        onSuccess: () -> Unit,
        onFailure: (Throwable) -> Unit
    ) {
        _isProcessingFile.value = true
        viewModelScope.launch {
            try {
                kotlinx.coroutines.withContext(kotlinx.coroutines.Dispatchers.IO) {
                    val contentResolver = context.contentResolver
                    val inputStream = contentResolver.openInputStream(uri)
                    val rawText = inputStream?.bufferedReader()?.use { it.readText() } ?: ""
                    
                    val unfoldedText = rawText.replace(Regex("\\r?\\n[ \\t]"), "")
                    val lines = unfoldedText.lines().map { it.trim() }.filter { it.isNotEmpty() }
                    
                    val eventsList = mutableListOf<Map<String, String>>()
                    var insideEvent = false
                    var currentEventLines = mutableListOf<String>()
                    
                    for (line in lines) {
                        if (line.startsWith("BEGIN:VEVENT", ignoreCase = true)) {
                            insideEvent = true
                            currentEventLines.clear()
                        } else if (line.startsWith("END:VEVENT", ignoreCase = true)) {
                            if (insideEvent) {
                                eventsList.add(parseIcsEventLines(currentEventLines))
                                insideEvent = false
                            }
                        } else if (insideEvent) {
                            currentEventLines.add(line)
                        }
                    }
                    
                    if (eventsList.isEmpty()) {
                        throw Exception("Keine Termine (VEVENT) in der ICS-Datei gefunden.")
                    }
                    
                    val categoriesList = listOf(
                        "general", "meeting", "call", "birthday", "vacation", "travel", "project", "customer",
                        "restmuell", "altpapier", "biomuell", "gelber_sack", "schadstoffe", "sperrmuell", "gruen", "baum"
                    )
                    
                    val jobs = eventsList.map { eventMap ->
                        val titleRaw = eventMap["SUMMARY"] ?: ""
                        if (titleRaw.isNotBlank()) {
                            val title = unescapeIcsField(titleRaw)
                            val description = eventMap["DESCRIPTION"]?.let { unescapeIcsField(it) }?.ifBlank { null }
                            
                            val isAllDay = eventMap["DTSTART;VALUE=DATE"] == "true" || 
                                           eventMap["DTEND;VALUE=DATE"] == "true" ||
                                           (!eventMap.containsKey("DTSTART") || !eventMap["DTSTART"]!!.contains("T"))
                            
                            val dtstart = eventMap["DTSTART"] ?: ""
                            val dtend = eventMap["DTEND"]
                            
                            val start = formatIcsDateToIso(dtstart, isAllDay)
                            val end = dtend?.let { formatIcsDateToIso(it, isAllDay) }?.ifBlank { null }
                            
                            val categoryRaw = eventMap["CATEGORIES"]?.lowercase() ?: "general"
                            val category = if (categoriesList.contains(categoryRaw)) categoryRaw else "general"
                            
                            val rrule = eventMap["RRULE"]?.uppercase() ?: ""
                            val recurrence = when {
                                rrule.contains("FREQ=DAILY") -> "daily"
                                rrule.contains("FREQ=WEEKLY") -> "weekly"
                                rrule.contains("FREQ=MONTHLY") -> "monthly"
                                rrule.contains("FREQ=YEARLY") -> "yearly"
                                else -> "none"
                            }
                            
                            val trigger = eventMap["TRIGGER"] ?: ""
                            val reminderMinutes = if (trigger.isNotBlank()) parseTriggerToMinutes(trigger) else null
                            
                            val priorityRaw = eventMap["PRIORITY"]?.lowercase() ?: "low"
                            val priority = when {
                                priorityRaw.contains("high") || priorityRaw == "1" || priorityRaw == "2" || priorityRaw == "3" || priorityRaw == "4" -> "high"
                                priorityRaw.contains("medium") || priorityRaw == "5" -> "medium"
                                else -> "low"
                            }
                            
                            async {
                                repository.addCalendarEvent(
                                    title = title,
                                    start = start,
                                    end = end,
                                    isAllDay = isAllDay,
                                    category = category,
                                    description = description,
                                    recurrence = recurrence,
                                    reminderMinutes = reminderMinutes,
                                    priority = priority,
                                    sendEmail = false
                                )
                            }
                        } else {
                            null
                        }
                    }.filterNotNull()
                    jobs.awaitAll()
                }
                loadAllOrganizerData(showLoading = false)
                _isProcessingFile.value = false
                onSuccess()
            } catch (e: Exception) {
                _isProcessingFile.value = false
                onFailure(e)
            }
        }
    }

    fun exportCalendarEventsToIcs(
        context: android.content.Context,
        onSuccess: (android.net.Uri) -> Unit,
        onFailure: (Throwable) -> Unit
    ) {
        _isProcessingFile.value = true
        viewModelScope.launch(kotlinx.coroutines.Dispatchers.IO) {
            try {
                val events = _calendarEvents.value
                val icsBuilder = java.lang.StringBuilder()
                icsBuilder.append("BEGIN:VCALENDAR\n")
                icsBuilder.append("VERSION:2.0\n")
                icsBuilder.append("PRODID:-//Mein Seelenfunke//Organizer//DE\n")
                icsBuilder.append("CALSCALE:GREGORIAN\n")
                
                events.forEach { ev ->
                    icsBuilder.append("BEGIN:VEVENT\n")
                    icsBuilder.append("SUMMARY:${escapeIcsField(ev.title)}\n")
                    if (!ev.description.isNullOrBlank()) {
                        icsBuilder.append("DESCRIPTION:${escapeIcsField(ev.description)}\n")
                    }
                    
                    val dtstart = formatIsoToIcsDate(ev.start, ev.is_all_day)
                    if (ev.is_all_day) {
                        icsBuilder.append("DTSTART;VALUE=DATE:$dtstart\n")
                    } else {
                        icsBuilder.append("DTSTART:$dtstart\n")
                    }
                    
                    if (!ev.end.isNullOrBlank()) {
                        val dtend = formatIsoToIcsDate(ev.end, ev.is_all_day)
                        if (ev.is_all_day) {
                            icsBuilder.append("DTEND;VALUE=DATE:$dtend\n")
                        } else {
                            icsBuilder.append("DTEND:$dtend\n")
                        }
                    }
                    
                    icsBuilder.append("CATEGORIES:${ev.category}\n")
                    
                    if (!ev.recurrence.isNullOrBlank() && ev.recurrence != "none") {
                        icsBuilder.append("RRULE:FREQ=${ev.recurrence.uppercase()}\n")
                    }
                    
                    val priorityVal = when (ev.priority?.lowercase()) {
                        "high" -> "1"
                        "medium" -> "5"
                        else -> "9"
                    }
                    icsBuilder.append("PRIORITY:$priorityVal\n")
                    
                    if (ev.reminder_minutes != null) {
                        icsBuilder.append("BEGIN:VALARM\n")
                        icsBuilder.append("ACTION:DISPLAY\n")
                        icsBuilder.append("DESCRIPTION:Reminder\n")
                        icsBuilder.append("TRIGGER:-PT${ev.reminder_minutes}M\n")
                        icsBuilder.append("END:VALARM\n")
                    }
                    
                    icsBuilder.append("END:VEVENT\n")
                }
                
                icsBuilder.append("END:VCALENDAR\n")
                
                val fileName = "seelenfunke_calendar_export.ics"
                val cacheFile = java.io.File(context.cacheDir, fileName)
                cacheFile.writeText(icsBuilder.toString(), Charsets.UTF_8)
                
                val authority = "de.meinseelenfunke.app.fileprovider"
                val uri = androidx.core.content.FileProvider.getUriForFile(context, authority, cacheFile)
                
                kotlinx.coroutines.withContext(kotlinx.coroutines.Dispatchers.Main) {
                    _isProcessingFile.value = false
                    onSuccess(uri)
                }
            } catch (e: Exception) {
                kotlinx.coroutines.withContext(kotlinx.coroutines.Dispatchers.Main) {
                    _isProcessingFile.value = false
                    onFailure(e)
                }
            }
        }
    }

    private fun parseIcsEventLines(lines: List<String>): Map<String, String> {
        val eventMap = mutableMapOf<String, String>()
        for (line in lines) {
            val colonIdx = line.indexOf(':')
            if (colonIdx != -1) {
                val keyPart = line.substring(0, colonIdx)
                val value = line.substring(colonIdx + 1)
                val propertyName = keyPart.split(';').first().trim().uppercase()
                eventMap[propertyName] = value
                if (keyPart.contains("VALUE=DATE", ignoreCase = true)) {
                    eventMap["$propertyName;VALUE=DATE"] = "true"
                }
            }
        }
        return eventMap
    }

    private fun escapeIcsField(value: String): String {
        return value
            .replace("\\", "\\\\")
            .replace(",", "\\,")
            .replace(";", "\\;")
            .replace("\n", "\\n")
            .replace("\r", "")
    }

    private fun unescapeIcsField(value: String): String {
        return value
            .replace("\\\\", "\\")
            .replace("\\,", ",")
            .replace("\\;", ";")
            .replace("\\n", "\n")
            .replace("\\N", "\n")
    }

    private fun formatIcsDateToIso(icsDate: String, isAllDay: Boolean): String {
        val clean = icsDate.trim()
        if (clean.length < 8) return ""
        val year = clean.substring(0, 4)
        val month = clean.substring(4, 6)
        val day = clean.substring(6, 8)
        val suffix = if (clean.endsWith("Z")) "Z" else ""
        if (isAllDay || clean.length < 15) {
            return "${year}-${month}-${day}T00:00:00$suffix"
        }
        val hour = clean.substring(9, 11)
        val min = clean.substring(11, 13)
        val sec = clean.substring(13, 15)
        return "${year}-${month}-${day}T${hour}:${min}:${sec}$suffix"
    }

    private fun formatIsoToIcsDate(isoDate: String, isAllDay: Boolean): String {
        val clean = isoDate.replace("-", "").replace(":", "")
        val tClean = clean.replace(" ", "T")
        if (isAllDay) {
            val tIdx = tClean.indexOf('T')
            return if (tIdx != -1) tClean.substring(0, tIdx) else tClean.take(8)
        }
        val withZ = if (tClean.endsWith("Z")) tClean else tClean + "Z"
        val dotIdx = withZ.indexOf('.')
        if (dotIdx != -1) {
            return withZ.substring(0, dotIdx) + "Z"
        }
        return withZ
    }

    private fun parseTriggerToMinutes(trigger: String): Int? {
        val clean = trigger.uppercase().trim()
        if (clean.contains("PT0S") || clean.contains("PT0M")) return 0
        
        // Match duration format like -P0DT7H0M0S or -PT15M
        val regex = Regex("^-?P(?:(\\d+)D)?(?:T(?:(\\d+)H)?(?:(\\d+)M)?(?:(\\d+)S)?)?$")
        val matchResult = regex.matchEntire(clean)
        if (matchResult != null) {
            val days = matchResult.groups[1]?.value?.toIntOrNull() ?: 0
            val hours = matchResult.groups[2]?.value?.toIntOrNull() ?: 0
            val minutes = matchResult.groups[3]?.value?.toIntOrNull() ?: 0
            val totalMinutes = days * 1440 + hours * 60 + minutes
            if (totalMinutes > 0) return totalMinutes
        }

        if (!clean.startsWith("-P")) return null
        val valuePart = clean.removePrefix("-P")
        return when {
            valuePart.endsWith("M") -> {
                val numStr = valuePart.substringAfter("T").removeSuffix("M")
                numStr.toIntOrNull()
            }
            valuePart.endsWith("H") -> {
                val numStr = valuePart.substringAfter("T").removeSuffix("H")
                numStr.toIntOrNull()?.let { it * 60 }
            }
            valuePart.endsWith("D") -> {
                val numStr = valuePart.removeSuffix("D")
                numStr.toIntOrNull()?.let { it * 1440 }
            }
            else -> null
        }
    }
}
