package de.meinseelenfunke.app.widget

import android.app.DatePickerDialog
import android.appwidget.AppWidgetManager
import android.content.Context
import android.os.Bundle
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
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.wrapContentHeight
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import de.meinseelenfunke.app.di.ServiceLocator
import de.meinseelenfunke.app.ui.theme.Gold
import de.meinseelenfunke.app.ui.theme.Slate400
import de.meinseelenfunke.app.ui.theme.Slate50
import de.meinseelenfunke.app.ui.theme.Slate900
import de.meinseelenfunke.app.ui.theme.SpaceBlack
import java.text.SimpleDateFormat
import java.util.Calendar
import java.util.Date
import java.util.Locale
import kotlinx.coroutines.launch

class DatePickerActivity : ComponentActivity() {

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        val prefKey = intent.getStringExtra("pref_key")
        val taskId = intent.getStringExtra("task_id")
        val appWidgetId = intent.getIntExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, AppWidgetManager.INVALID_APPWIDGET_ID)
        val rawInitialDateStr = intent.getStringExtra("initial_date")
        val initialDateStr = if (rawInitialDateStr != null && (rawInitialDateStr.contains("T") || rawInitialDateStr.length > 10)) {
            de.meinseelenfunke.app.util.DateUtils.parseUtcToLocalDateString(rawInitialDateStr)
        } else {
            rawInitialDateStr
        }

        if (prefKey.isNullOrBlank() && taskId.isNullOrBlank()) {
            finish()
            return
        }

        val sharedPrefs = getSharedPreferences("tasks_widget_prefs", Context.MODE_PRIVATE)

        setContent {
            DatePickerScreen(
                initialDate = initialDateStr,
                onSave = { selectedDate ->
                    if (!taskId.isNullOrBlank()) {
                        val repository = ServiceLocator.organizerRepository
                        
                        // Optimistic local cache update
                        val tasksJson = sharedPrefs.getString("tasks_cache", null)
                        if (tasksJson != null) {
                            try {
                                val gson = com.google.gson.Gson()
                                val type = object : com.google.gson.reflect.TypeToken<List<de.meinseelenfunke.app.data.api.ManagementTask>>() {}.type
                                val tasks: List<de.meinseelenfunke.app.data.api.ManagementTask> = gson.fromJson(tasksJson, type)
                                val updatedTasks = tasks.map { 
                                    if (it.id == taskId) it.copy(relevant_from = selectedDate) else it 
                                }
                                repository.saveTasksToCache(updatedTasks)
                            } catch (e: Exception) {}
                        }

                        // Run update in background and close
                        kotlinx.coroutines.CoroutineScope(kotlinx.coroutines.Dispatchers.Main).launch {
                            repository.updateTask(taskId, relevantFrom = selectedDate ?: "")
                            repository.getTasks()
                            repository.triggerTasksWidgetUpdate(this@DatePickerActivity)
                            finish()
                        }
                    } else if (!prefKey.isNullOrBlank()) {
                        if (selectedDate == null) {
                            sharedPrefs.edit().remove(prefKey).apply()
                        } else {
                            sharedPrefs.edit().putString(prefKey, selectedDate).apply()
                        }
                        ServiceLocator.organizerRepository.triggerTasksWidgetUpdate(this@DatePickerActivity)
                        finish()
                    }
                },
                onDismiss = { finish() }
            )
        }
    }

    @Composable
    fun DatePickerScreen(
        initialDate: String?,
        onSave: (String?) -> Unit,
        onDismiss: () -> Unit
    ) {
        var selectedDate by remember { mutableStateOf(initialDate) }
        val context = this

        val sdf = SimpleDateFormat("yyyy-MM-dd", Locale.US)
        val todayStr = sdf.format(Date())

        val cal = Calendar.getInstance()
        cal.add(Calendar.DAY_OF_YEAR, 1)
        val tomorrowStr = sdf.format(cal.time)

        cal.time = Date()
        cal.add(Calendar.DAY_OF_YEAR, 3)
        val in3DaysStr = sdf.format(cal.time)

        cal.time = Date()
        cal.add(Calendar.DAY_OF_YEAR, 7)
        val in7DaysStr = sdf.format(cal.time)

        fun showSystemDatePicker() {
            val c = Calendar.getInstance()
            if (!selectedDate.isNullOrBlank()) {
                try {
                    val parts = selectedDate!!.split("-")
                    if (parts.size == 3) {
                        c.set(Calendar.YEAR, parts[0].toInt())
                        c.set(Calendar.MONTH, parts[1].toInt() - 1)
                        c.set(Calendar.DAY_OF_MONTH, parts[2].toInt())
                    }
                } catch (e: Exception) {}
            }
            DatePickerDialog(
                context,
                android.R.style.Theme_DeviceDefault_Dialog,
                { _, year, month, dayOfMonth ->
                    selectedDate = String.format(Locale.US, "%04d-%02d-%02d", year, month + 1, dayOfMonth)
                },
                c.get(Calendar.YEAR),
                c.get(Calendar.MONTH),
                c.get(Calendar.DAY_OF_MONTH)
            ).show()
        }

        Box(
            modifier = Modifier
                .fillMaxSize()
                .background(Color(0x99030712)) // Translucent overlay
                .clickable { onDismiss() },
            contentAlignment = Alignment.Center
        ) {
            Card(
                modifier = Modifier
                    .width(320.dp)
                    .wrapContentHeight()
                    .padding(16.dp)
                    .clickable {}, // Consume clicks
                shape = RoundedCornerShape(16.dp),
                border = BorderStroke(1.5.dp, Gold),
                colors = CardDefaults.cardColors(
                    containerColor = Slate900
                ),
                elevation = CardDefaults.cardElevation(defaultElevation = 8.dp)
            ) {
                Column(
                    modifier = Modifier.padding(20.dp),
                    horizontalAlignment = Alignment.CenterHorizontally
                ) {
                    Text(
                        text = "DATUM ANPASSEN",
                        color = Gold,
                        fontSize = 14.sp,
                        fontWeight = FontWeight.Bold,
                        letterSpacing = 0.05.sp
                    )

                    Spacer(modifier = Modifier.height(16.dp))

                    // Quick Selection Buttons
                    Column(
                        modifier = Modifier.fillMaxWidth(),
                        verticalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        Row(modifier = Modifier.fillMaxWidth()) {
                            DateButton(
                                text = "Heute",
                                isSelected = selectedDate == todayStr,
                                modifier = Modifier.weight(1f),
                                onClick = { selectedDate = todayStr }
                            )
                            Spacer(modifier = Modifier.width(8.dp))
                            DateButton(
                                text = "Morgen",
                                isSelected = selectedDate == tomorrowStr,
                                modifier = Modifier.weight(1f),
                                onClick = { selectedDate = tomorrowStr }
                            )
                        }

                        Row(modifier = Modifier.fillMaxWidth()) {
                            DateButton(
                                text = "In 3 Tagen",
                                isSelected = selectedDate == in3DaysStr,
                                modifier = Modifier.weight(1f),
                                onClick = { selectedDate = in3DaysStr }
                            )
                            Spacer(modifier = Modifier.width(8.dp))
                            DateButton(
                                text = "In 7 Tagen",
                                isSelected = selectedDate == in7DaysStr,
                                modifier = Modifier.weight(1f),
                                onClick = { selectedDate = in7DaysStr }
                            )
                        }

                        Row(modifier = Modifier.fillMaxWidth()) {
                            val isCustom = selectedDate != null &&
                                    selectedDate != todayStr &&
                                    selectedDate != tomorrowStr &&
                                    selectedDate != in3DaysStr &&
                                    selectedDate != in7DaysStr
                            
                            val customLabel = if (isCustom) {
                                try {
                                    val parsed = sdf.parse(selectedDate!!)
                                    if (parsed != null) {
                                        SimpleDateFormat("dd.MM.yyyy", Locale.GERMANY).format(parsed)
                                    } else {
                                        "Anderes..."
                                    }
                                } catch (e: Exception) {
                                    "Anderes..."
                                }
                            } else {
                                "Anderes..."
                            }

                            DateButton(
                                text = customLabel,
                                isSelected = isCustom,
                                modifier = Modifier.weight(1f),
                                onClick = { showSystemDatePicker() }
                            )
                            Spacer(modifier = Modifier.width(8.dp))
                            DateButton(
                                text = "Kein Datum",
                                isSelected = selectedDate == null || selectedDate == "",
                                modifier = Modifier.weight(1f),
                                onClick = { selectedDate = null }
                            )
                        }
                    }

                    Spacer(modifier = Modifier.height(24.dp))

                    // Cancel & Save
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        OutlinedButton(
                            onClick = onDismiss,
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
                            onClick = { onSave(selectedDate) },
                            shape = RoundedCornerShape(20.dp),
                            colors = ButtonDefaults.buttonColors(
                                containerColor = Gold,
                                contentColor = SpaceBlack
                            ),
                            modifier = Modifier.weight(1f)
                        ) {
                            Text("Ok", fontSize = 13.sp, fontWeight = FontWeight.Bold)
                        }
                    }
                }
            }
        }
    }
    @Composable
    fun DateButton(
        text: String,
        isSelected: Boolean,
        modifier: Modifier = Modifier,
        onClick: () -> Unit
    ) {
        Button(
            onClick = onClick,
            shape = RoundedCornerShape(8.dp),
            colors = ButtonDefaults.buttonColors(
                containerColor = if (isSelected) Gold else Color(0x1AFFFFFF),
                contentColor = if (isSelected) SpaceBlack else Slate50
            ),
            border = if (isSelected) null else BorderStroke(1.dp, Color(0x1AFFFFFF)),
            modifier = modifier
        ) {
            Text(text = text, fontSize = 12.sp, maxLines = 1)
        }
    }
}
