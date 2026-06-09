package de.meinseelenfunke.app.widget

import android.app.DatePickerDialog
import android.content.Context
import android.content.Intent
import android.os.Bundle
import android.widget.Toast
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.wrapContentHeight
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.Check
import androidx.compose.material.icons.filled.DateRange
import androidx.compose.material.icons.filled.Delete
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Divider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextDecoration
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.google.gson.Gson
import com.google.gson.reflect.TypeToken
import de.meinseelenfunke.app.data.api.ManagementTask
import de.meinseelenfunke.app.di.ServiceLocator
import de.meinseelenfunke.app.ui.theme.Gold
import de.meinseelenfunke.app.ui.theme.Rose500
import de.meinseelenfunke.app.ui.theme.Slate400
import de.meinseelenfunke.app.ui.theme.Slate50
import de.meinseelenfunke.app.ui.theme.Slate800
import de.meinseelenfunke.app.ui.theme.Slate900
import de.meinseelenfunke.app.ui.theme.SpaceBlack
import de.meinseelenfunke.app.ui.theme.GlassWhite10
import kotlinx.coroutines.launch
import java.text.SimpleDateFormat
import java.util.Calendar
import java.util.Date
import java.util.Locale
import android.net.Uri
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.Description
import androidx.compose.material.icons.filled.Image
import androidx.compose.material.icons.filled.Feed
import androidx.compose.material.icons.filled.GridOn
import androidx.compose.material.icons.filled.FolderOpen
import androidx.compose.material.icons.filled.FilePresent
import androidx.compose.material.icons.filled.PushPin

class EditTaskWidgetActivity : ComponentActivity() {

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        val taskId = intent.getStringExtra("task_id")
        if (taskId.isNullOrBlank()) {
            Toast.makeText(this, "Keine Aufgaben-ID übergeben.", Toast.LENGTH_SHORT).show()
            finish()
            return
        }

        // Fetch task and its subtasks from SharedPreferences tasks cache
        val sharedPrefs = getSharedPreferences("tasks_widget_prefs", Context.MODE_PRIVATE)
        val tasksJson = sharedPrefs.getString("tasks_cache", null)
        val gson = Gson()
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

        val task = allTasks.find { it.id == taskId }
        if (task == null) {
            Toast.makeText(this, "Aufgabe nicht im Cache gefunden.", Toast.LENGTH_SHORT).show()
            finish()
            return
        }

        setContent {
            EditTaskScreen(
                task = task,
                initialSubtasks = allTasks.filter { it.parent_id == task.id },
                onDismiss = { finish() }
            )
        }
    }

    @Composable
    fun EditTaskScreen(
        task: ManagementTask,
        initialSubtasks: List<ManagementTask>,
        onDismiss: () -> Unit
    ) {
        val context = LocalContext.current
        val coroutineScope = rememberCoroutineScope()

        var title by remember { mutableStateOf(task.title) }
        var priority by remember { mutableStateOf(task.priority ?: "niedrig") }
        var relevantFrom by remember {
            val localDate = de.meinseelenfunke.app.util.DateUtils.parseUtcToLocalDateString(task.relevant_from)
            mutableStateOf(localDate ?: "")
        }
        var subtasks by remember { mutableStateOf(initialSubtasks) }
        var filePaths by remember { mutableStateOf(task.file_paths ?: emptyList()) }

        var newSubtaskTitle by remember { mutableStateOf("") }
        var isSaving by remember { mutableStateOf(false) }
        var isAddingSubtask by remember { mutableStateOf(false) }
        var isUploading by remember { mutableStateOf(false) }
        var errorMessage by remember { mutableStateOf<String?>(null) }

        val filePickerLauncher = rememberLauncherForActivityResult(
            contract = ActivityResultContracts.GetContent()
        ) { uri: Uri? ->
            uri?.let {
                val contentResolver = context.contentResolver
                val mimeType = contentResolver.getType(uri) ?: "application/octet-stream"
                val fileName = getFileName(context, uri) ?: "upload.bin"
                try {
                    val inputStream = contentResolver.openInputStream(uri)
                    val fileBytes = inputStream?.readBytes()
                    inputStream?.close()
                    if (fileBytes != null) {
                        coroutineScope.launch {
                            isUploading = true
                            ServiceLocator.organizerRepository.uploadTaskFile(task.id, fileBytes, fileName, mimeType)
                                .onSuccess { updatedTask ->
                                    filePaths = updatedTask.file_paths ?: emptyList()
                                    Toast.makeText(context, "Datei hochgeladen.", Toast.LENGTH_SHORT).show()
                                }
                                .onFailure { error ->
                                    Toast.makeText(context, "Fehler beim Hochladen: ${error.localizedMessage}", Toast.LENGTH_SHORT).show()
                                }
                            isUploading = false
                        }
                    }
                } catch (e: Exception) {
                    e.printStackTrace()
                }
            }
        }

        fun handleDeleteFile(path: String) {
            coroutineScope.launch {
                ServiceLocator.organizerRepository.deleteTaskFile(task.id, path)
                    .onSuccess { updatedTask ->
                        filePaths = updatedTask.file_paths ?: emptyList()
                        Toast.makeText(context, "Datei gelöscht.", Toast.LENGTH_SHORT).show()
                    }
                    .onFailure { error ->
                        Toast.makeText(context, "Fehler beim Löschen: ${error.localizedMessage}", Toast.LENGTH_SHORT).show()
                    }
            }
        }

        // Date Picker Launcher helper
        fun showDatePicker() {
            val calendar = Calendar.getInstance()
            if (relevantFrom.isNotEmpty()) {
                try {
                    val sdf = SimpleDateFormat("yyyy-MM-dd", Locale.US)
                    sdf.parse(relevantFrom)?.let {
                        calendar.time = it
                    }
                } catch (e: Exception) {}
            }

            DatePickerDialog(
                context,
                { _, year, month, dayOfMonth ->
                    val formattedMonth = String.format(Locale.US, "%02d", month + 1)
                    val formattedDay = String.format(Locale.US, "%02d", dayOfMonth)
                    relevantFrom = "$year-$formattedMonth-$formattedDay"
                },
                calendar.get(Calendar.YEAR),
                calendar.get(Calendar.MONTH),
                calendar.get(Calendar.DAY_OF_MONTH)
            ).show()
        }

        // Subtask toggle helper
        fun toggleSubtaskCompletion(subtask: ManagementTask) {
            coroutineScope.launch {
                ServiceLocator.organizerRepository.toggleTask(subtask.id)
                    .onSuccess { updatedSubtask ->
                        subtasks = subtasks.map {
                            if (it.id == updatedSubtask.id) updatedSubtask else it
                        }
                    }
            }
        }

        // Add subtask helper
        fun handleAddSubtask() {
            if (newSubtaskTitle.isBlank()) return
            isAddingSubtask = true
            coroutineScope.launch {
                ServiceLocator.organizerRepository.addSubtask(task.id, newSubtaskTitle.trim())
                    .onSuccess { newSubtask ->
                        subtasks = subtasks + newSubtask
                        newSubtaskTitle = ""
                        isAddingSubtask = false
                    }
                    .onFailure { error ->
                        Toast.makeText(context, "Fehler beim Hinzufügen des Unter-Schritts.", Toast.LENGTH_SHORT).show()
                        isAddingSubtask = false
                    }
            }
        }

        // Save task updates
        fun handleSave() {
            if (title.isBlank()) return
            isSaving = true
            errorMessage = null
            coroutineScope.launch {
                val formattedRelevantFrom = if (relevantFrom.isEmpty()) null else relevantFrom
                ServiceLocator.organizerRepository.updateTask(
                    id = task.id,
                    title = title.trim(),
                    priority = priority,
                    relevantFrom = formattedRelevantFrom
                ).onSuccess {
                    isSaving = false
                    Toast.makeText(context, "Aufgabe aktualisiert.", Toast.LENGTH_SHORT).show()
                    onDismiss()
                }.onFailure { error ->
                    isSaving = false
                    errorMessage = error.localizedMessage ?: "Fehler beim Speichern"
                }
            }
        }

        Box(
            modifier = Modifier
                .fillMaxSize()
                .background(Color(0x99030712)) // Dark translucent background overlay
                .clickable { if (!isSaving) onDismiss() },
            contentAlignment = Alignment.Center
        ) {
            Card(
                modifier = Modifier
                    .width(340.dp)
                    .wrapContentHeight()
                    .padding(16.dp)
                    .clickable {}, // Consume card body clicks
                shape = RoundedCornerShape(16.dp),
                border = BorderStroke(1.5.dp, Gold),
                colors = CardDefaults.cardColors(
                    containerColor = Slate900
                ),
                elevation = CardDefaults.cardElevation(defaultElevation = 8.dp)
            ) {
                Column(
                    modifier = Modifier
                        .padding(20.dp)
                        .verticalScroll(rememberScrollState())
                ) {
                    Text(
                        text = "AUFGABE BEARBEITEN",
                        color = Gold,
                        fontSize = 16.sp,
                        fontWeight = FontWeight.Bold,
                        letterSpacing = 0.1.sp,
                        modifier = Modifier.align(Alignment.CenterHorizontally)
                    )

                    Spacer(modifier = Modifier.height(16.dp))

                    // Title Field
                    Text("Titel", color = Slate400, fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
                    Spacer(modifier = Modifier.height(4.dp))
                    OutlinedTextField(
                        value = title,
                        onValueChange = { title = it },
                        singleLine = true,
                        enabled = !isSaving,
                        modifier = Modifier.fillMaxWidth(),
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedBorderColor = Gold,
                            unfocusedBorderColor = Color(0x33FFFFFF),
                            focusedTextColor = Slate50,
                            unfocusedTextColor = Slate50
                        ),
                        shape = RoundedCornerShape(12.dp)
                    )

                    Spacer(modifier = Modifier.height(14.dp))

                    // Priority Row Selection
                    Text("Priorität", color = Slate400, fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
                    Spacer(modifier = Modifier.height(6.dp))
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        listOf("niedrig", "mittel", "hoch").forEach { prioOption ->
                            val isSelected = priority.lowercase(Locale.ROOT) == prioOption
                            OutlinedButton(
                                onClick = { priority = prioOption },
                                enabled = !isSaving,
                                shape = RoundedCornerShape(12.dp),
                                colors = ButtonDefaults.outlinedButtonColors(
                                    containerColor = if (isSelected) Gold else Color.Transparent,
                                    contentColor = if (isSelected) SpaceBlack else Slate400
                                ),
                                border = BorderStroke(1.dp, if (isSelected) Gold else Color(0x33FFFFFF)),
                                modifier = Modifier.weight(1f).padding(horizontal = 4.dp)
                            ) {
                                Text(
                                    prioOption.uppercase(Locale.GERMANY),
                                    fontSize = 10.sp,
                                    fontWeight = FontWeight.Bold,
                                    maxLines = 1,
                                    overflow = TextOverflow.Ellipsis
                                )
                            }
                        }
                    }

                    Spacer(modifier = Modifier.height(14.dp))

                    // Relevant ab Date Selector
                    Text("Relevant ab", color = Slate400, fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
                    Spacer(modifier = Modifier.height(6.dp))
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        OutlinedButton(
                            onClick = { showDatePicker() },
                            enabled = !isSaving,
                            shape = RoundedCornerShape(12.dp),
                            border = BorderStroke(1.dp, Color(0x33FFFFFF)),
                            colors = ButtonDefaults.outlinedButtonColors(
                                contentColor = Slate50
                            ),
                            modifier = Modifier.weight(1f)
                        ) {
                            Icon(Icons.Default.DateRange, contentDescription = null, tint = Gold, modifier = Modifier.size(16.dp))
                            Spacer(modifier = Modifier.width(6.dp))
                            Text(
                                if (relevantFrom.isEmpty()) "Datum wählen" else relevantFrom,
                                fontSize = 12.sp,
                                maxLines = 1,
                                overflow = TextOverflow.Ellipsis
                            )
                        }

                        if (relevantFrom.isNotEmpty()) {
                            Spacer(modifier = Modifier.width(8.dp))
                            IconButton(
                                onClick = { relevantFrom = "" },
                                enabled = !isSaving
                            ) {
                                Icon(Icons.Default.Delete, contentDescription = "Löschen", tint = Rose500)
                            }
                        }
                    }

                    Spacer(modifier = Modifier.height(16.dp))
                    Divider(color = Color(0x1AFFFFFF), thickness = 1.dp)
                    Spacer(modifier = Modifier.height(12.dp))

                    // Unter-Schritte Section
                    Text("UNTER-SCHRITTE", color = Gold, fontSize = 13.sp, fontWeight = FontWeight.Bold, letterSpacing = 0.05.sp)
                    Spacer(modifier = Modifier.height(8.dp))

                    // List currently created subtasks
                    if (subtasks.isEmpty()) {
                        Text(
                            "Keine Unter-Schritte vorhanden.",
                            color = Slate400,
                            fontSize = 11.sp,
                            modifier = Modifier.padding(vertical = 4.dp)
                        )
                    } else {
                        subtasks.forEach { sub ->
                            Row(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .padding(vertical = 3.dp),
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Box(
                                    modifier = Modifier
                                        .size(18.dp)
                                        .background(
                                            if (sub.is_completed) Gold else Color.Transparent,
                                            RoundedCornerShape(4.dp)
                                        )
                                        .border(
                                            1.dp,
                                            if (sub.is_completed) Gold else Color(0x4DFFFFFF),
                                            RoundedCornerShape(4.dp)
                                        )
                                        .clickable { toggleSubtaskCompletion(sub) },
                                    contentAlignment = Alignment.Center
                                ) {
                                    if (sub.is_completed) {
                                        Icon(
                                            Icons.Default.Check,
                                            contentDescription = null,
                                            tint = SpaceBlack,
                                            modifier = Modifier.size(12.dp)
                                        )
                                    }
                                }

                                Spacer(modifier = Modifier.width(8.dp))

                                Text(
                                    text = sub.title,
                                    color = if (sub.is_completed) Slate400 else Slate50,
                                    fontSize = 12.sp,
                                    textDecoration = if (sub.is_completed) TextDecoration.LineThrough else null,
                                    maxLines = 2,
                                    overflow = TextOverflow.Ellipsis,
                                    modifier = Modifier.weight(1f)
                                )
                            }
                        }
                    }

                    Spacer(modifier = Modifier.height(10.dp))

                    // Create Subtask Input
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        OutlinedTextField(
                            value = newSubtaskTitle,
                            onValueChange = { newSubtaskTitle = it },
                            placeholder = { Text("Neuer Unter-Schritt...", fontSize = 11.sp) },
                            singleLine = true,
                            enabled = !isSaving && !isAddingSubtask,
                            colors = OutlinedTextFieldDefaults.colors(
                                focusedBorderColor = Gold,
                                unfocusedBorderColor = Color(0x22FFFFFF),
                                focusedTextColor = Slate50,
                                unfocusedTextColor = Slate50,
                                focusedPlaceholderColor = Slate400,
                                unfocusedPlaceholderColor = Slate400
                            ),
                            shape = RoundedCornerShape(10.dp),
                            modifier = Modifier.weight(1f)
                        )

                        Spacer(modifier = Modifier.width(8.dp))

                        IconButton(
                            onClick = { handleAddSubtask() },
                            enabled = !isSaving && !isAddingSubtask && newSubtaskTitle.isNotBlank(),
                            modifier = Modifier
                                .size(40.dp)
                                .background(
                                    if (newSubtaskTitle.isNotBlank()) Gold else Color(0x1AFFFFFF),
                                    RoundedCornerShape(8.dp)
                                )
                        ) {
                            if (isAddingSubtask) {
                                CircularProgressIndicator(
                                    modifier = Modifier.size(16.dp),
                                    color = SpaceBlack,
                                    strokeWidth = 1.5.dp
                                )
                            } else {
                                Icon(
                                    Icons.Default.Add,
                                    contentDescription = "Hinzufügen",
                                    tint = if (newSubtaskTitle.isNotBlank()) SpaceBlack else Slate400,
                                    modifier = Modifier.size(20.dp)
                                )
                            }
                        }
                    }

                    // Divider and Attachments Section
                    Spacer(modifier = Modifier.height(16.dp))
                    Divider(color = Color(0x1AFFFFFF), thickness = 1.dp)
                    Spacer(modifier = Modifier.height(12.dp))

                    Text(
                        text = "DATEIEN",
                        color = Gold,
                        fontSize = 13.sp,
                        fontWeight = FontWeight.Bold,
                        letterSpacing = 0.05.sp
                    )
                    Spacer(modifier = Modifier.height(8.dp))

                    if (filePaths.isNotEmpty()) {
                        Column(
                            verticalArrangement = Arrangement.spacedBy(6.dp),
                            modifier = Modifier.fillMaxWidth().padding(bottom = 8.dp)
                        ) {
                            filePaths.forEach { path ->
                                val fileName = path.substringAfterLast('/')
                                val ext = fileName.substringAfterLast('.', "").lowercase()
                                val icon = when (ext) {
                                    "pdf" -> Icons.Default.Description
                                    "doc", "docx", "odt", "rtf", "txt" -> Icons.Default.Feed
                                    "xls", "xlsx", "ods", "csv" -> Icons.Default.GridOn
                                    "png", "jpg", "jpeg", "gif", "webp", "svg" -> Icons.Default.Image
                                    "zip", "rar", "7z", "tar", "gz" -> Icons.Default.FolderOpen
                                    else -> Icons.Default.FilePresent
                                }
                                val iconColor = when (ext) {
                                    "pdf" -> Color(0xFFEF4444)
                                    "doc", "docx", "odt", "rtf", "txt" -> Color(0xFF3B82F6)
                                    "xls", "xlsx", "ods", "csv" -> Color(0xFF10B981)
                                    "png", "jpg", "jpeg", "gif", "webp", "svg" -> Color(0xFFA855F7)
                                    "zip", "rar", "7z", "tar", "gz" -> Gold
                                    else -> Slate400
                                }
                                Row(
                                    verticalAlignment = Alignment.CenterVertically,
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .background(GlassWhite10, RoundedCornerShape(8.dp))
                                        .padding(horizontal = 10.dp, vertical = 6.dp)
                                ) {
                                    Row(
                                        verticalAlignment = Alignment.CenterVertically,
                                        modifier = Modifier.weight(1f).clickable {
                                            val token = ServiceLocator.getAuthToken() ?: ""
                                            val baseUrl = ServiceLocator.getBaseUrl()
                                            val url = "${baseUrl}funki/financials/receipt?path=${Uri.encode(path)}&token=${Uri.encode(token)}"
                                            try {
                                                val intent = Intent(Intent.ACTION_VIEW, Uri.parse(url))
                                                context.startActivity(intent)
                                            } catch (e: Exception) {
                                                e.printStackTrace()
                                            }
                                        }
                                    ) {
                                        Icon(
                                            imageVector = icon,
                                            contentDescription = null,
                                            tint = iconColor,
                                            modifier = Modifier.size(16.dp)
                                        )
                                        Spacer(modifier = Modifier.width(8.dp))
                                        Text(
                                            text = fileName,
                                            color = Slate50,
                                            fontSize = 12.sp,
                                            maxLines = 1,
                                            overflow = TextOverflow.Ellipsis
                                        )
                                    }

                                    IconButton(
                                        onClick = { handleDeleteFile(path) },
                                        modifier = Modifier.size(24.dp)
                                    ) {
                                        Icon(
                                            imageVector = Icons.Default.Close,
                                            contentDescription = "Datei löschen",
                                            tint = Color.Red.copy(alpha = 0.7f),
                                            modifier = Modifier.size(14.dp)
                                        )
                                    }
                                }
                            }
                        }
                    } else {
                        Text(
                            "Keine Dateien angeheftet.",
                            color = Slate400,
                            fontSize = 11.sp,
                            modifier = Modifier.padding(vertical = 4.dp)
                        )
                    }

                    Spacer(modifier = Modifier.height(10.dp))

                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(8.dp),
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        Button(
                            onClick = { filePickerLauncher.launch("*/*") },
                            colors = ButtonDefaults.buttonColors(containerColor = GlassWhite10, contentColor = Gold),
                            border = BorderStroke(1.dp, Gold.copy(alpha = 0.3f)),
                            shape = RoundedCornerShape(8.dp),
                            modifier = Modifier.height(36.dp),
                            enabled = !isSaving && !isUploading
                        ) {
                            Icon(
                                imageVector = Icons.Default.PushPin,
                                contentDescription = null,
                                tint = Gold,
                                modifier = Modifier.size(14.dp)
                            )
                            Spacer(modifier = Modifier.width(6.dp))
                            Text("Datei anheften", fontSize = 11.sp, fontWeight = FontWeight.Bold)
                        }

                        if (isUploading) {
                            Spacer(modifier = Modifier.width(8.dp))
                            CircularProgressIndicator(
                                modifier = Modifier.size(16.dp),
                                color = Gold,
                                strokeWidth = 1.5.dp
                            )
                        }
                    }

                    errorMessage?.let { msg ->
                        Spacer(modifier = Modifier.height(8.dp))
                        Text(
                            text = msg,
                            color = Rose500,
                            fontSize = 12.sp,
                            fontWeight = FontWeight.SemiBold
                        )
                    }

                    Spacer(modifier = Modifier.height(24.dp))

                    // Cancel & Save Buttons
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        OutlinedButton(
                            onClick = onDismiss,
                            enabled = !isSaving,
                            shape = RoundedCornerShape(20.dp),
                            border = BorderStroke(1.dp, Color(0x33FFFFFF)),
                            colors = ButtonDefaults.outlinedButtonColors(
                                contentColor = Slate400
                            ),
                            modifier = Modifier.weight(1f)
                        ) {
                            Text("Abbrechen", fontSize = 13.sp)
                        }

                        Spacer(modifier = Modifier.width(12.dp))

                        Button(
                            onClick = { handleSave() },
                            enabled = !isSaving && title.isNotBlank(),
                            shape = RoundedCornerShape(20.dp),
                            colors = ButtonDefaults.buttonColors(
                                containerColor = Gold,
                                contentColor = SpaceBlack,
                                disabledContainerColor = Color(0x33C5A059),
                                disabledContentColor = Slate400
                            ),
                            modifier = Modifier.weight(1f)
                        ) {
                            if (isSaving) {
                                CircularProgressIndicator(
                                    modifier = Modifier.height(18.dp).width(18.dp),
                                    color = SpaceBlack,
                                    strokeWidth = 2.dp
                                )
                            } else {
                                Text("Speichern", fontSize = 13.sp, fontWeight = FontWeight.Bold)
                            }
                        }
                    }
                }
            }
        }
    }
}

private fun getFileName(context: android.content.Context, uri: android.net.Uri): String? {
    var result: String? = null
    if (uri.scheme == "content") {
        val cursor = context.contentResolver.query(uri, null, null, null, null)
        try {
            if (cursor != null && cursor.moveToFirst()) {
                val index = cursor.getColumnIndex(android.provider.OpenableColumns.DISPLAY_NAME)
                if (index != -1) {
                    result = cursor.getString(index)
                }
            }
        } finally {
            cursor?.close()
        }
    }
    if (result == null) {
        result = uri.path
        val cut = result?.lastIndexOf('/')
        if (cut != null && cut != -1) {
            result = result.substring(cut + 1)
        }
    }
    return result
}
