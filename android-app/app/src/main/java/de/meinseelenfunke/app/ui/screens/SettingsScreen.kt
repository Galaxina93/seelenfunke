package de.meinseelenfunke.app.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.viewmodel.compose.viewModel
import de.meinseelenfunke.app.ui.theme.Emerald500
import de.meinseelenfunke.app.ui.theme.GlassWhite10
import de.meinseelenfunke.app.ui.theme.Indigo500
import de.meinseelenfunke.app.ui.theme.Slate400
import de.meinseelenfunke.app.ui.theme.Slate50
import de.meinseelenfunke.app.ui.theme.Slate800
import de.meinseelenfunke.app.ui.theme.Slate900

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun SettingsScreen(
    viewModel: SettingsViewModel = viewModel()
) {
    val apiKey by viewModel.apiKey.collectAsState()
    var inputKey by remember { mutableStateOf(apiKey) }

    Scaffold(
        topBar = {
            TopAppBar(
                title = {
                    Column {
                        Text(
                            text = "Einstellungen",
                            fontSize = 18.sp,
                            fontWeight = FontWeight.Bold,
                            color = Emerald500
                        )
                        Text(
                            text = "Konfiguration & API-Schlüssel",
                            fontSize = 12.sp,
                            color = Slate400
                        )
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = Slate800
                )
            )
        }
    ) { innerPadding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(innerPadding)
                .background(
                    Brush.verticalGradient(
                        colors = listOf(Slate900, Color(0xFF020617))
                    )
                )
                .padding(16.dp)
                .verticalScroll(rememberScrollState())
        ) {
            Card(
                modifier = Modifier.fillMaxWidth(),
                colors = CardDefaults.cardColors(containerColor = GlassWhite10),
                shape = RoundedCornerShape(12.dp)
            ) {
                Column(modifier = Modifier.padding(16.dp)) {
                    Text(
                        text = "Google AI Studio API Key",
                        fontSize = 14.sp,
                        fontWeight = FontWeight.Bold,
                        color = Slate50
                    )
                    Text(
                        text = "Erforderlich für die lokale Generierung via SDK.",
                        fontSize = 11.sp,
                        color = Slate400,
                        modifier = Modifier.padding(vertical = 4.dp)
                    )
                    OutlinedTextField(
                        value = inputKey,
                        onValueChange = { inputKey = it },
                        placeholder = { Text("AIzaSy...") },
                        modifier = Modifier.fillMaxWidth(),
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedBorderColor = Indigo500,
                            unfocusedBorderColor = Color.Gray,
                            focusedTextColor = Slate50,
                            unfocusedTextColor = Slate50
                        )
                    )
                    Spacer(modifier = Modifier.height(12.dp))
                    Button(
                        onClick = {
                            viewModel.saveApiKey(inputKey)
                        },
                        colors = ButtonDefaults.buttonColors(containerColor = Indigo500),
                        modifier = Modifier.align(Alignment.End)
                    ) {
                        Text("Speichern")
                    }
                }
            }

            Spacer(modifier = Modifier.height(16.dp))

            val wakeWordEnabled by viewModel.wakeWordEnabled.collectAsState()

            Card(
                modifier = Modifier.fillMaxWidth(),
                colors = CardDefaults.cardColors(containerColor = GlassWhite10),
                shape = RoundedCornerShape(12.dp)
            ) {
                Column(modifier = Modifier.padding(16.dp)) {
                    Text(
                        text = "Hintergrund-Aktivierung (Wake Word)",
                        fontSize = 14.sp,
                        fontWeight = FontWeight.Bold,
                        color = Slate50
                    )
                    Text(
                        text = "Erlaubt es, die App per Sprachbefehl ('Hey Funkira') im Hintergrund oder bei ausgeschaltetem Bildschirm zu aktivieren. Benötigt Mikrofon- und Benachrichtigungsrechte.",
                        fontSize = 11.sp,
                        color = Slate400,
                        modifier = Modifier.padding(vertical = 4.dp)
                    )
                    Spacer(modifier = Modifier.height(8.dp))
                    androidx.compose.foundation.layout.Row(
                        modifier = Modifier.fillMaxWidth(),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = androidx.compose.foundation.layout.Arrangement.SpaceBetween
                    ) {
                        Text(
                            text = if (wakeWordEnabled) "Aktiviert (lauscht im Hintergrund)" else "Deaktiviert",
                            color = if (wakeWordEnabled) Emerald500 else Slate400,
                            fontSize = 13.sp,
                            fontWeight = FontWeight.SemiBold
                        )
                        androidx.compose.material3.Switch(
                            checked = wakeWordEnabled,
                            onCheckedChange = { viewModel.setWakeWordEnabled(it) },
                            colors = androidx.compose.material3.SwitchDefaults.colors(
                                checkedThumbColor = Emerald500,
                                checkedTrackColor = Emerald500.copy(alpha = 0.5f)
                            )
                        )
                    }
                }
            }
        }
    }
}
