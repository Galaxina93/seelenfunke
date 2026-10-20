package de.meinseelenfunke.app.ui.screens

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
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.BrightnessAuto
import androidx.compose.material.icons.filled.Check
import androidx.compose.material.icons.filled.DarkMode
import androidx.compose.material.icons.filled.LightMode
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Switch
import androidx.compose.material3.SwitchDefaults
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
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.viewmodel.compose.viewModel
import de.meinseelenfunke.app.di.ServiceLocator
import de.meinseelenfunke.app.di.ThemeMode
import de.meinseelenfunke.app.ui.theme.AppTheme
import de.meinseelenfunke.app.ui.theme.Emerald500
import de.meinseelenfunke.app.ui.theme.Gold
import de.meinseelenfunke.app.ui.theme.Indigo500
import de.meinseelenfunke.app.ui.theme.SpaceBlack

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun SettingsScreen(
    viewModel: SettingsViewModel = viewModel()
) {
    val apiKey by viewModel.apiKey.collectAsState()
    var inputKey by remember { mutableStateOf(apiKey) }
    val currentThemeMode by ServiceLocator.themeModeState.collectAsState()
    val wakeWordEnabled by viewModel.wakeWordEnabled.collectAsState()

    Scaffold(
        topBar = {
            TopAppBar(
                title = {
                    Column {
                        Text(
                            text = "Einstellungen",
                            fontSize = 18.sp,
                            fontWeight = FontWeight.Bold,
                            color = Gold
                        )
                        Text(
                            text = "Erscheinungsbild, KI & Konfiguration",
                            fontSize = 12.sp,
                            color = AppTheme.colors.textMuted
                        )
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = AppTheme.colors.topBarBackground
                )
            )
        }
    ) { innerPadding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(innerPadding)
                .background(Brush.verticalGradient(AppTheme.colors.bgGradient))
                .padding(16.dp)
                .verticalScroll(rememberScrollState()),
            verticalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            // Theme Appearance Card (Dark / Light Mode)
            Card(
                modifier = Modifier.fillMaxWidth(),
                colors = CardDefaults.cardColors(containerColor = AppTheme.colors.cardBackground),
                border = BorderStroke(1.dp, AppTheme.colors.cardBorder),
                shape = RoundedCornerShape(16.dp)
            ) {
                Column(modifier = Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                    Text(
                        text = "Erscheinungsbild",
                        fontSize = 15.sp,
                        fontWeight = FontWeight.Bold,
                        color = AppTheme.colors.textPrimary
                    )
                    Text(
                        text = "Wähle deinen bevorzugten Design-Modus für die gesamte App:",
                        fontSize = 11.sp,
                        color = AppTheme.colors.textMuted
                    )

                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        // Dark Mode Option
                        val isDarkSelected = currentThemeMode == ThemeMode.DARK
                        Column(
                            modifier = Modifier
                                .weight(1f)
                                .clip(RoundedCornerShape(12.dp))
                                .background(if (isDarkSelected) Gold.copy(alpha = 0.15f) else AppTheme.colors.glassSurface)
                                .border(
                                    1.dp,
                                    if (isDarkSelected) Gold else AppTheme.colors.cardBorder,
                                    RoundedCornerShape(12.dp)
                                )
                                .clickable { ServiceLocator.setThemeMode(ThemeMode.DARK) }
                                .padding(12.dp),
                            horizontalAlignment = Alignment.CenterHorizontally,
                            verticalArrangement = Arrangement.spacedBy(6.dp)
                        ) {
                            Icon(
                                imageVector = Icons.Default.DarkMode,
                                contentDescription = null,
                                tint = if (isDarkSelected) Gold else AppTheme.colors.textMuted,
                                modifier = Modifier.size(24.dp)
                            )
                            Text(
                                text = "Dunkel",
                                fontSize = 12.sp,
                                fontWeight = if (isDarkSelected) FontWeight.Bold else FontWeight.Medium,
                                color = if (isDarkSelected) Gold else AppTheme.colors.textPrimary
                            )
                            if (isDarkSelected) {
                                Icon(Icons.Default.Check, contentDescription = null, tint = Gold, modifier = Modifier.size(14.dp))
                            }
                        }

                        // Light Mode Option
                        val isLightSelected = currentThemeMode == ThemeMode.LIGHT
                        Column(
                            modifier = Modifier
                                .weight(1f)
                                .clip(RoundedCornerShape(12.dp))
                                .background(if (isLightSelected) Gold.copy(alpha = 0.15f) else AppTheme.colors.glassSurface)
                                .border(
                                    1.dp,
                                    if (isLightSelected) Gold else AppTheme.colors.cardBorder,
                                    RoundedCornerShape(12.dp)
                                )
                                .clickable { ServiceLocator.setThemeMode(ThemeMode.LIGHT) }
                                .padding(12.dp),
                            horizontalAlignment = Alignment.CenterHorizontally,
                            verticalArrangement = Arrangement.spacedBy(6.dp)
                        ) {
                            Icon(
                                imageVector = Icons.Default.LightMode,
                                contentDescription = null,
                                tint = if (isLightSelected) Gold else AppTheme.colors.textMuted,
                                modifier = Modifier.size(24.dp)
                            )
                            Text(
                                text = "Hell",
                                fontSize = 12.sp,
                                fontWeight = if (isLightSelected) FontWeight.Bold else FontWeight.Medium,
                                color = if (isLightSelected) Gold else AppTheme.colors.textPrimary
                            )
                            if (isLightSelected) {
                                Icon(Icons.Default.Check, contentDescription = null, tint = Gold, modifier = Modifier.size(14.dp))
                            }
                        }

                        // System Mode Option
                        val isSystemSelected = currentThemeMode == ThemeMode.SYSTEM
                        Column(
                            modifier = Modifier
                                .weight(1f)
                                .clip(RoundedCornerShape(12.dp))
                                .background(if (isSystemSelected) Gold.copy(alpha = 0.15f) else AppTheme.colors.glassSurface)
                                .border(
                                    1.dp,
                                    if (isSystemSelected) Gold else AppTheme.colors.cardBorder,
                                    RoundedCornerShape(12.dp)
                                )
                                .clickable { ServiceLocator.setThemeMode(ThemeMode.SYSTEM) }
                                .padding(12.dp),
                            horizontalAlignment = Alignment.CenterHorizontally,
                            verticalArrangement = Arrangement.spacedBy(6.dp)
                        ) {
                            Icon(
                                imageVector = Icons.Default.BrightnessAuto,
                                contentDescription = null,
                                tint = if (isSystemSelected) Gold else AppTheme.colors.textMuted,
                                modifier = Modifier.size(24.dp)
                            )
                            Text(
                                text = "System",
                                fontSize = 12.sp,
                                fontWeight = if (isSystemSelected) FontWeight.Bold else FontWeight.Medium,
                                color = if (isSystemSelected) Gold else AppTheme.colors.textPrimary
                            )
                            if (isSystemSelected) {
                                Icon(Icons.Default.Check, contentDescription = null, tint = Gold, modifier = Modifier.size(14.dp))
                            }
                        }
                    }
                }
            }

            // Google AI Studio API Key
            Card(
                modifier = Modifier.fillMaxWidth(),
                colors = CardDefaults.cardColors(containerColor = AppTheme.colors.cardBackground),
                border = BorderStroke(1.dp, AppTheme.colors.cardBorder),
                shape = RoundedCornerShape(16.dp)
            ) {
                Column(modifier = Modifier.padding(16.dp)) {
                    Text(
                        text = "Google AI Studio API Key",
                        fontSize = 14.sp,
                        fontWeight = FontWeight.Bold,
                        color = AppTheme.colors.textPrimary
                    )
                    Text(
                        text = "Erforderlich für die lokale Generierung via SDK.",
                        fontSize = 11.sp,
                        color = AppTheme.colors.textMuted,
                        modifier = Modifier.padding(vertical = 4.dp)
                    )
                    OutlinedTextField(
                        value = inputKey,
                        onValueChange = { inputKey = it },
                        placeholder = { Text("AIzaSy...") },
                        modifier = Modifier.fillMaxWidth(),
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedBorderColor = Indigo500,
                            unfocusedBorderColor = AppTheme.colors.inputBorder,
                            focusedTextColor = AppTheme.colors.textPrimary,
                            unfocusedTextColor = AppTheme.colors.textPrimary,
                            focusedContainerColor = AppTheme.colors.inputBackground,
                            unfocusedContainerColor = AppTheme.colors.inputBackground
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

            // Background Wake Word Card
            Card(
                modifier = Modifier.fillMaxWidth(),
                colors = CardDefaults.cardColors(containerColor = AppTheme.colors.cardBackground),
                border = BorderStroke(1.dp, AppTheme.colors.cardBorder),
                shape = RoundedCornerShape(16.dp)
            ) {
                Column(modifier = Modifier.padding(16.dp)) {
                    Text(
                        text = "Hintergrund-Aktivierung (Wake Word)",
                        fontSize = 14.sp,
                        fontWeight = FontWeight.Bold,
                        color = AppTheme.colors.textPrimary
                    )
                    Text(
                        text = "Erlaubt es, die App per Sprachbefehl ('Hey Funkira') im Hintergrund oder bei ausgeschaltetem Bildschirm zu aktivieren. Benötigt Mikrofon- und Benachrichtigungsrechte.",
                        fontSize = 11.sp,
                        color = AppTheme.colors.textMuted,
                        modifier = Modifier.padding(vertical = 4.dp)
                    )
                    Spacer(modifier = Modifier.height(8.dp))
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Text(
                            text = if (wakeWordEnabled) "Aktiviert (lauscht im Hintergrund)" else "Deaktiviert",
                            color = if (wakeWordEnabled) Emerald500 else AppTheme.colors.textMuted,
                            fontSize = 13.sp,
                            fontWeight = FontWeight.SemiBold
                        )
                        Switch(
                            checked = wakeWordEnabled,
                            onCheckedChange = { viewModel.setWakeWordEnabled(it) },
                            colors = SwitchDefaults.colors(
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
