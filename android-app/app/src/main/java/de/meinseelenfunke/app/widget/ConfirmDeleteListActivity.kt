package de.meinseelenfunke.app.widget

import android.appwidget.AppWidgetManager
import android.content.ComponentName
import android.content.Context
import android.content.Intent
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
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.wrapContentHeight
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.OutlinedButton
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
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import de.meinseelenfunke.app.di.ServiceLocator
import de.meinseelenfunke.app.ui.theme.Gold
import de.meinseelenfunke.app.ui.theme.Rose500
import de.meinseelenfunke.app.ui.theme.Slate400
import de.meinseelenfunke.app.ui.theme.Slate50
import de.meinseelenfunke.app.ui.theme.Slate900
import de.meinseelenfunke.app.ui.theme.SpaceBlack
import kotlinx.coroutines.launch

class ConfirmDeleteListActivity : ComponentActivity() {

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        val listId = intent.getStringExtra("list_id")
        if (listId.isNullOrBlank()) {
            Toast.makeText(this, "Keine Listen-ID übergeben.", Toast.LENGTH_SHORT).show()
            finish()
            return
        }

        // Retrieve list name from cache to display in dialog
        val sharedPrefs = getSharedPreferences("tasks_widget_prefs", Context.MODE_PRIVATE)
        val listsJson = sharedPrefs.getString("task_lists_cache", null)
        var listName = "diese Liste"
        if (listsJson != null) {
            try {
                val gson = com.google.gson.Gson()
                val type = object : com.google.gson.reflect.TypeToken<List<de.meinseelenfunke.app.data.api.ManagementTaskList>>() {}.type
                val lists: List<de.meinseelenfunke.app.data.api.ManagementTaskList> = gson.fromJson(listsJson, type)
                lists.find { it.id == listId }?.let {
                    listName = "'${it.name}'"
                }
            } catch (e: Exception) {}
        }

        setContent {
            val coroutineScope = rememberCoroutineScope()
            ConfirmDeleteScreen(
                listName = listName,
                onCancel = { finish() },
                onConfirm = {
                    coroutineScope.launch {
                        val repository = ServiceLocator.organizerRepository
                        repository.deleteTaskList(listId)
                        repository.getTaskLists()

                        // Remove selected list from widget preferences
                        val appWidgetIds = AppWidgetManager.getInstance(this@ConfirmDeleteListActivity)
                            .getAppWidgetIds(ComponentName(this@ConfirmDeleteListActivity, TasksWidgetProvider::class.java))
                        val edit = sharedPrefs.edit()
                        for (id in appWidgetIds) {
                            val selectedListId = sharedPrefs.getString("widget_tasks_selected_list_id_$id", null)
                            if (selectedListId == listId) {
                                edit.remove("widget_tasks_selected_list_id_$id")
                                    .remove("widget_tasks_selected_list_name_$id")
                                    .remove("widget_tasks_editing_task_id_$id")
                            }
                        }
                        edit.apply()

                        repository.triggerTasksWidgetUpdate(this@ConfirmDeleteListActivity)
                        Toast.makeText(this@ConfirmDeleteListActivity, "Liste gelöscht.", Toast.LENGTH_SHORT).show()
                        finish()
                    }
                }
            )
        }
    }

    @Composable
    fun ConfirmDeleteScreen(
        listName: String,
        onCancel: () -> Unit,
        onConfirm: () -> Unit
    ) {
        var isLoading by remember { mutableStateOf(false) }
        val coroutineScope = rememberCoroutineScope()

        Box(
            modifier = Modifier
                .fillMaxSize()
                .background(Color(0x99030712)) // Dark translucent background overlay
                .clickable { if (!isLoading) onCancel() },
            contentAlignment = Alignment.Center
        ) {
            Card(
                modifier = Modifier
                    .width(320.dp)
                    .wrapContentHeight()
                    .padding(16.dp)
                    .clickable {}, // Consume card clicks
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
                        text = "Wirklich löschen?",
                        color = Slate50,
                        fontSize = 15.sp,
                        fontWeight = FontWeight.Bold,
                        textAlign = TextAlign.Center,
                        lineHeight = 18.sp
                    )

                    Spacer(modifier = Modifier.height(24.dp))

                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        OutlinedButton(
                            onClick = onCancel,
                            enabled = !isLoading,
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
                            onClick = {
                                isLoading = true
                                onConfirm()
                            },
                            enabled = !isLoading,
                            shape = RoundedCornerShape(20.dp),
                            colors = ButtonDefaults.buttonColors(
                                containerColor = Rose500,
                                contentColor = Color.White,
                                disabledContainerColor = Color(0x33EF4444),
                                disabledContentColor = Slate400
                            ),
                            modifier = Modifier.weight(1f)
                        ) {
                            if (isLoading) {
                                CircularProgressIndicator(
                                    modifier = Modifier.height(18.dp).width(18.dp),
                                    color = Color.White,
                                    strokeWidth = 2.dp
                                )
                            } else {
                                Text("Löschen", fontSize = 13.sp, fontWeight = FontWeight.Bold)
                            }
                        }
                    }
                }
            }
        }
    }
}
