package de.meinseelenfunke.app.widget

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
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.wrapContentHeight
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
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
import de.meinseelenfunke.app.di.ServiceLocator
import de.meinseelenfunke.app.ui.theme.Gold
import de.meinseelenfunke.app.ui.theme.Rose500
import de.meinseelenfunke.app.ui.theme.Slate400
import de.meinseelenfunke.app.ui.theme.Slate50
import de.meinseelenfunke.app.ui.theme.Slate900
import de.meinseelenfunke.app.ui.theme.SpaceBlack
import kotlinx.coroutines.launch

import androidx.compose.foundation.border
import androidx.compose.foundation.layout.size
import androidx.compose.material3.Icon
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

class AddTaskListWidgetActivity : ComponentActivity() {

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        setContent {
            AddTaskListScreen(
                onDismiss = { finish() }
            )
        }
    }

    @Composable
    fun AddTaskListScreen(onDismiss: () -> Unit) {
        val context = LocalContext.current
        val coroutineScope = rememberCoroutineScope()
        val focusRequester = remember { FocusRequester() }

        var name by remember { mutableStateOf("") }
        var selectedIcon by remember { mutableStateOf("bookmark") }
        var isSaving by remember { mutableStateOf(false) }
        var errorMessage by remember { mutableStateOf<String?>(null) }

        // Request keyboard focus immediately on load
        LaunchedEffect(Unit) {
            kotlinx.coroutines.delay(150)
            focusRequester.requestFocus()
        }

        // Add task list helper
        fun handleAddTaskList() {
            if (name.isBlank()) return
            isSaving = true
            errorMessage = null
            coroutineScope.launch {
                ServiceLocator.organizerRepository.addTaskList(name.trim(), selectedIcon)
                    .onSuccess {
                        // Refresh the lists cache to trigger widget updates
                        ServiceLocator.organizerRepository.getTaskLists()
                        isSaving = false
                        Toast.makeText(context, "Liste erstellt.", Toast.LENGTH_SHORT).show()
                        onDismiss()
                    }
                    .onFailure { error ->
                        isSaving = false
                        errorMessage = error.localizedMessage ?: "Fehler beim Erstellen der Liste"
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
                    .clickable {}, // Consume clicks
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
                        text = "NEUE LISTE ERSTELLEN",
                        color = Gold,
                        fontSize = 15.sp,
                        fontWeight = FontWeight.Bold,
                        letterSpacing = 0.1.sp,
                        modifier = Modifier.align(Alignment.CenterHorizontally)
                    )

                    Spacer(modifier = Modifier.height(20.dp))

                    // List Name Input
                    Text("Name der Liste", color = Slate400, fontSize = 12.sp, fontWeight = FontWeight.SemiBold)
                    Spacer(modifier = Modifier.height(4.dp))
                    OutlinedTextField(
                        value = name,
                        onValueChange = { name = it },
                        placeholder = { Text("z.B. Website, Einkauf, Routine...") },
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

                    // List Icon Selection
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
                            onClick = { handleAddTaskList() },
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
                                Text("Erstellen", fontSize = 13.sp, fontWeight = FontWeight.Bold)
                            }
                        }
                    }
                }
            }
        }
    }
}
