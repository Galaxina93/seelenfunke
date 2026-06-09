package de.meinseelenfunke.app.widget

import android.appwidget.AppWidgetManager
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
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.focus.FocusRequester
import androidx.compose.ui.focus.focusRequester
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import de.meinseelenfunke.app.di.ServiceLocator
import de.meinseelenfunke.app.ui.theme.Gold
import de.meinseelenfunke.app.ui.theme.Slate400
import de.meinseelenfunke.app.ui.theme.Slate50
import de.meinseelenfunke.app.ui.theme.Slate900
import de.meinseelenfunke.app.ui.theme.SpaceBlack

class TextInputActivity : ComponentActivity() {

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        val title = intent.getStringExtra("title") ?: "TEXT EINGEBEN"
        val prefKey = intent.getStringExtra("pref_key")
        val appWidgetId = intent.getIntExtra(AppWidgetManager.EXTRA_APPWIDGET_ID, AppWidgetManager.INVALID_APPWIDGET_ID)

        if (prefKey.isNullOrBlank()) {
            Toast.makeText(this, "Kein Zielschlüssel übergeben.", Toast.LENGTH_SHORT).show()
            finish()
            return
        }

        val sharedPrefs = getSharedPreferences("tasks_widget_prefs", Context.MODE_PRIVATE)
        val initialValue = sharedPrefs.getString(prefKey, "") ?: ""

        setContent {
            TextInputScreen(
                title = title,
                initialValue = initialValue,
                onSave = { newValue ->
                    sharedPrefs.edit().putString(prefKey, newValue).apply()
                    // Refresh widget to display new value inline
                    ServiceLocator.organizerRepository.triggerTasksWidgetUpdate(this)
                    finish()
                },
                onDismiss = { finish() }
            )
        }
    }

    @Composable
    fun TextInputScreen(
        title: String,
        initialValue: String,
        onSave: (String) -> Unit,
        onDismiss: () -> Unit
    ) {
        val focusRequester = remember { FocusRequester() }
        var value by remember { mutableStateOf(initialValue) }

        LaunchedEffect(Unit) {
            kotlinx.coroutines.delay(150)
            focusRequester.requestFocus()
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
                        text = title.uppercase(),
                        color = Gold,
                        fontSize = 14.sp,
                        fontWeight = FontWeight.Bold,
                        letterSpacing = 0.05.sp
                    )

                    Spacer(modifier = Modifier.height(16.dp))

                    OutlinedTextField(
                        value = value,
                        onValueChange = { value = it },
                        placeholder = { Text("Hier tippen...") },
                        singleLine = true,
                        keyboardOptions = KeyboardOptions(
                            imeAction = ImeAction.Done
                        ),
                        keyboardActions = KeyboardActions(
                            onDone = { onSave(value.trim()) }
                        ),
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

                    Spacer(modifier = Modifier.height(20.dp))

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
                            onClick = { onSave(value.trim()) },
                            enabled = value.isNotBlank(),
                            shape = RoundedCornerShape(20.dp),
                            colors = ButtonDefaults.buttonColors(
                                containerColor = Gold,
                                contentColor = SpaceBlack,
                                disabledContainerColor = Color(0x33C5A059),
                                disabledContentColor = Slate400
                            ),
                            modifier = Modifier.weight(1f)
                        ) {
                            Text("Übernehmen", fontSize = 13.sp, fontWeight = FontWeight.Bold)
                        }
                    }
                }
            }
        }
    }
}
