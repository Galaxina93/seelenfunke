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
import androidx.compose.material.icons.filled.Bookmark
import androidx.compose.material.icons.filled.Star
import androidx.compose.material.icons.filled.Favorite
import androidx.compose.material.icons.filled.Bolt
import androidx.compose.material.icons.filled.Home
import androidx.compose.material.icons.filled.Work
import androidx.compose.material.icons.filled.ShoppingBag
import androidx.compose.material.icons.filled.EmojiEvents
import androidx.compose.material.icons.filled.WbSunny
import androidx.compose.material.icons.filled.NightsStay
import androidx.compose.material.icons.filled.Build
import androidx.compose.material.icons.filled.RocketLaunch
import androidx.compose.material.icons.filled.LocalOffer
import androidx.compose.material.icons.filled.Flag
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
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
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import de.meinseelenfunke.app.data.api.ManagementTaskList
import de.meinseelenfunke.app.di.ServiceLocator
import de.meinseelenfunke.app.ui.theme.Gold
import de.meinseelenfunke.app.ui.theme.Rose500
import de.meinseelenfunke.app.ui.theme.Slate400
import de.meinseelenfunke.app.ui.theme.Slate50
import de.meinseelenfunke.app.ui.theme.Slate900
import de.meinseelenfunke.app.ui.theme.SpaceBlack
import kotlinx.coroutines.launch

class RenameTaskListWidgetActivity : ComponentActivity() {

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        val listId = intent.getStringExtra("list_id")
        if (listId.isNullOrBlank()) {
            Toast.makeText(this, "Keine Listen-ID übergeben.", Toast.LENGTH_SHORT).show()
            finish()
            return
        }

        // Retrieve existing name and icon from cache
        val sharedPrefs = getSharedPreferences("tasks_widget_prefs", Context.MODE_PRIVATE)
        val listsJson = sharedPrefs.getString("task_lists_cache", null)
        var initialName = ""
        var initialIcon = "bookmark"

        if (listsJson != null) {
            try {
                val gson = com.google.gson.Gson()
                val type = object : com.google.gson.reflect.TypeToken<List<ManagementTaskList>>() {}.type
                val lists: List<ManagementTaskList> = gson.fromJson(listsJson, type)
                lists.find { it.id == listId }?.let {
                    initialName = it.name
                    initialIcon = it.icon ?: "bookmark"
                }
            } catch (e: Exception) {}
        }

        setContent {
            RenameTaskListScreen(
                listId = listId,
                initialName = initialName,
                initialIcon = initialIcon,
                onDismiss = { finish() }
            )
        }
    }

    @Composable
    fun RenameTaskListScreen(
        listId: String,
        initialName: String,
        initialIcon: String,
        onDismiss: () -> Unit
    ) {
        val context = LocalContext.current
        val coroutineScope = rememberCoroutineScope()
        val focusRequester = remember { FocusRequester() }

        var name by remember { mutableStateOf(initialName) }
        var selectedIcon by remember { mutableStateOf(initialIcon) }
        var isSaving by remember { mutableStateOf(false) }
        var errorMessage by remember { mutableStateOf<String?>(null) }

        LaunchedEffect(Unit) {
            kotlinx.coroutines.delay(150)
            focusRequester.requestFocus()
        }

        fun handleRenameTaskList() {
            if (name.isBlank()) return
            isSaving = true
            errorMessage = null
            coroutineScope.launch {
                ServiceLocator.organizerRepository.updateTaskList(listId, name.trim(), selectedIcon)
                    .onSuccess {
                        // Refresh the lists cache to trigger widget updates
                        ServiceLocator.organizerRepository.getTaskLists()

                        // Update the active list name if this list is currently selected in any widget
                        val sharedPrefs = getSharedPreferences("tasks_widget_prefs", Context.MODE_PRIVATE)
                        val appWidgetIds = AppWidgetManager.getInstance(context)
                            .getAppWidgetIds(ComponentName(context, TasksWidgetProvider::class.java))
                        val edit = sharedPrefs.edit()
                        for (id in appWidgetIds) {
                            val selectedListId = sharedPrefs.getString("widget_tasks_selected_list_id_$id", null)
                            if (selectedListId == listId) {
                                edit.putString("widget_tasks_selected_list_name_$id", name.trim())
                            }
                        }
                        edit.apply()

                        ServiceLocator.organizerRepository.triggerTasksWidgetUpdate(context)

                        isSaving = false
                        Toast.makeText(context, "Liste umbenannt.", Toast.LENGTH_SHORT).show()
                        onDismiss()
                    }
                    .onFailure { error ->
                        isSaving = false
                        errorMessage = error.localizedMessage ?: "Fehler beim Umbenennen der Liste"
                    }
            }
        }

        Box(
            modifier = Modifier
                .fillMaxSize()
                .background(Color(0x99030712))
                .clickable { if (!isSaving) onDismiss() },
            contentAlignment = Alignment.Center
        ) {
            Card(
                modifier = Modifier
                    .width(340.dp)
                    .wrapContentHeight()
                    .padding(16.dp)
                    .clickable {},
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
                        text = "LISTE UMBENENNEN",
                        color = Gold,
                        fontSize = 15.sp,
                        fontWeight = FontWeight.Bold,
                        letterSpacing = 0.1.sp,
                        modifier = Modifier.align(Alignment.CenterHorizontally)
                    )

                    Spacer(modifier = Modifier.height(20.dp))

                    Text("Name der Liste", color = Slate400, fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
                    Spacer(modifier = Modifier.height(4.dp))
                    OutlinedTextField(
                        value = name,
                        onValueChange = { name = it },
                        placeholder = { Text("Name eingeben...") },
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

                    Spacer(modifier = Modifier.height(16.dp))

                    Text("Symbol der Liste", color = Slate400, fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
                    Spacer(modifier = Modifier.height(8.dp))

                    val listIcons = listOf(
                        "bookmark" to Icons.Default.Bookmark,
                        "star" to Icons.Default.Star,
                        "heart" to Icons.Default.Favorite,
                        "bolt" to Icons.Default.Bolt,
                        "home" to Icons.Default.Home,
                        "briefcase" to Icons.Default.Work,
                        "shopping-bag" to Icons.Default.ShoppingBag,
                        "trophy" to Icons.Default.EmojiEvents,
                        "sun" to Icons.Default.WbSunny,
                        "moon" to Icons.Default.NightsStay,
                        "wrench" to Icons.Default.Build,
                        "rocket-launch" to Icons.Default.RocketLaunch,
                        "tag" to Icons.Default.LocalOffer,
                        "flag" to Icons.Default.Flag
                    )

                    val rows = listIcons.chunked(5)
                    Column(
                        verticalArrangement = Arrangement.spacedBy(8.dp),
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        rows.forEach { rowItems ->
                            Row(
                                horizontalArrangement = Arrangement.SpaceEvenly,
                                modifier = Modifier.fillMaxWidth()
                            ) {
                                rowItems.forEach { (iconName, vector) ->
                                    val isSelected = selectedIcon == iconName
                                    Box(
                                        modifier = Modifier
                                            .size(40.dp)
                                            .background(
                                                color = if (isSelected) Gold.copy(alpha = 0.2f) else Color.Transparent,
                                                shape = RoundedCornerShape(8.dp)
                                            )
                                            .border(
                                                width = 1.5.dp,
                                                color = if (isSelected) Gold else Color.Transparent,
                                                shape = RoundedCornerShape(8.dp)
                                            )
                                            .clickable { selectedIcon = iconName }
                                            .padding(8.dp),
                                        contentAlignment = Alignment.Center
                                    ) {
                                        Icon(
                                            imageVector = vector,
                                            contentDescription = iconName,
                                            tint = if (isSelected) Gold else Slate400,
                                            modifier = Modifier.size(24.dp)
                                        )
                                    }
                                }
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
                            onClick = { handleRenameTaskList() },
                            enabled = !isSaving && name.isNotBlank(),
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
