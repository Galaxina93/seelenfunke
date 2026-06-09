package de.meinseelenfunke.app.widget

import android.app.DatePickerDialog
import android.content.Context
import android.os.Bundle
import android.widget.Toast
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
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
import androidx.compose.material.icons.filled.DateRange
import androidx.compose.material.icons.filled.Delete
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.ExposedDropdownMenuBox
import androidx.compose.material3.ExposedDropdownMenuDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.focus.FocusRequester
import androidx.compose.ui.focus.focusRequester
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.google.gson.Gson
import com.google.gson.reflect.TypeToken
import de.meinseelenfunke.app.data.api.ManagementTaskList
import de.meinseelenfunke.app.di.ServiceLocator
import de.meinseelenfunke.app.ui.theme.Gold
import de.meinseelenfunke.app.ui.theme.Rose500
import de.meinseelenfunke.app.ui.theme.Slate400
import de.meinseelenfunke.app.ui.theme.Slate50
import de.meinseelenfunke.app.ui.theme.Slate900
import de.meinseelenfunke.app.ui.theme.SpaceBlack
import kotlinx.coroutines.launch
import java.text.SimpleDateFormat
import java.util.Calendar
import java.util.Locale

class AddTaskWidgetActivity : ComponentActivity() {

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        val defaultListId = intent.getStringExtra("list_id")

        // Fetch task lists from cache
        val sharedPrefs = getSharedPreferences("tasks_widget_prefs", Context.MODE_PRIVATE)
        val listsJson = sharedPrefs.getString("task_lists_cache", null)
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

        if (allLists.isEmpty()) {
            Toast.makeText(this, "Keine Aufgabenlisten gefunden. Bitte App öffnen und synchronisieren.", Toast.LENGTH_LONG).show()
            finish()
            return
        }

        setContent {
            AddTaskScreen(
                allLists = allLists,
                defaultListId = defaultListId,
                onDismiss = { finish() }
            )
        }
    }

    @OptIn(ExperimentalMaterial3Api::class)
    @Composable
    fun AddTaskScreen(
        allLists: List<ManagementTaskList>,
        defaultListId: String?,
        onDismiss: () -> Unit
    ) {
        val context = LocalContext.current
        val coroutineScope = rememberCoroutineScope()
        val focusRequester = remember { FocusRequester() }

        var title by remember { mutableStateOf("") }
        var priority by remember { mutableStateOf("niedrig") }
        var relevantFrom by remember { mutableStateOf("") }

        // Choose selected list (defaulting to widget's list or first list)
        var selectedList by remember {
            val list = allLists.find { it.id == defaultListId } ?: allLists.first()
            mutableStateOf(list)
        }
        var dropdownExpanded by remember { mutableStateOf(false) }

        var isSaving by remember { mutableStateOf(false) }
        var errorMessage by remember { mutableStateOf<String?>(null) }

        // Request keyboard focus immediately on load
        LaunchedEffect(Unit) {
            kotlinx.coroutines.delay(150)
            focusRequester.requestFocus()
        }

        // Date Picker helper
        fun showDatePicker() {
            val calendar = Calendar.getInstance()
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

        // Add task helper
        fun handleAddTask() {
            if (title.isBlank()) return
            isSaving = true
            errorMessage = null
            coroutineScope.launch {
                ServiceLocator.organizerRepository.addTask(selectedList.id, title.trim(), priority)
                    .onSuccess { newTask ->
                        // If relevant date is set, call update task in the background
                        if (relevantFrom.isNotEmpty()) {
                            ServiceLocator.organizerRepository.updateTask(newTask.id, relevantFrom = relevantFrom)
                        }
                        // ALWAYS call getTasks() to pull the full updated tasks list (including the new task)
                        ServiceLocator.organizerRepository.getTasks()
                        isSaving = false
                        Toast.makeText(context, "Aufgabe erstellt.", Toast.LENGTH_SHORT).show()
                        onDismiss()
                    }
                    .onFailure { error ->
                        isSaving = false
                        errorMessage = error.localizedMessage ?: "Fehler beim Erstellen der Aufgabe"
                    }
            }
        }

        Box(
            modifier = Modifier
                .fillMaxSize()
                .background(Color(0x99030712)) // Translucent overlay
                .clickable { if (!isSaving) onDismiss() },
            contentAlignment = Alignment.Center
        ) {
            Card(
                modifier = Modifier
                    .width(340.dp)
                    .wrapContentHeight()
                    .padding(16.dp)
                    .clickable(enabled = false) {}, // Consume clicks
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
                        text = "NEUE AUFGABE",
                        color = Gold,
                        fontSize = 16.sp,
                        fontWeight = FontWeight.Bold,
                        letterSpacing = 0.1.sp,
                        modifier = Modifier.align(Alignment.CenterHorizontally)
                    )

                    Spacer(modifier = Modifier.height(16.dp))

                    // List Selector Dropdown
                    Text("Aufgabenliste", color = Slate400, fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
                    Spacer(modifier = Modifier.height(4.dp))
                    ExposedDropdownMenuBox(
                        expanded = dropdownExpanded,
                        onExpandedChange = { dropdownExpanded = it },
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        OutlinedTextField(
                            value = selectedList.name,
                            onValueChange = {},
                            readOnly = true,
                            enabled = !isSaving,
                            trailingIcon = { ExposedDropdownMenuDefaults.TrailingIcon(expanded = dropdownExpanded) },
                            modifier = Modifier
                                .fillMaxWidth()
                                .menuAnchor(),
                            colors = OutlinedTextFieldDefaults.colors(
                                focusedBorderColor = Gold,
                                unfocusedBorderColor = Color(0x33FFFFFF),
                                focusedTextColor = Slate50,
                                unfocusedTextColor = Slate50,
                                disabledTextColor = Slate50,
                                disabledBorderColor = Color(0x33FFFFFF)
                            ),
                            shape = RoundedCornerShape(12.dp)
                        )
                        ExposedDropdownMenu(
                            expanded = dropdownExpanded,
                            onDismissRequest = { dropdownExpanded = false },
                            modifier = Modifier.background(Slate900).fillMaxWidth()
                        ) {
                            allLists.forEach { listOption ->
                                DropdownMenuItem(
                                    text = { Text(listOption.name, color = Slate50) },
                                    onClick = {
                                        selectedList = listOption
                                        dropdownExpanded = false
                                    },
                                    modifier = Modifier.background(Slate900)
                                )
                            }
                        }
                    }

                    Spacer(modifier = Modifier.height(14.dp))

                    // Title Input
                    Text("Titel", color = Slate400, fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
                    Spacer(modifier = Modifier.height(4.dp))
                    OutlinedTextField(
                        value = title,
                        onValueChange = { title = it },
                        placeholder = { Text("z.B. Besprechung vorbereiten...") },
                        singleLine = true,
                        enabled = !isSaving,
                        modifier = Modifier
                            .fillMaxWidth()
                            .focusRequester(focusRequester),
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedBorderColor = Gold,
                            unfocusedBorderColor = Color(0x33FFFFFF),
                            focusedTextColor = Slate50,
                            unfocusedTextColor = Slate50,
                            focusedPlaceholderColor = Slate400,
                            unfocusedPlaceholderColor = Slate400
                        ),
                        shape = RoundedCornerShape(12.dp)
                    )

                    Spacer(modifier = Modifier.height(14.dp))

                    // Priority Row
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
                            onClick = { handleAddTask() },
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
                                Text("Erstellen", fontSize = 13.sp, fontWeight = FontWeight.Bold)
                            }
                        }
                    }
                }
            }
        }
    }
}
