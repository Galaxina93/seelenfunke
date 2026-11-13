package de.meinseelenfunke.app.ui.screens

import android.Manifest
import android.app.role.RoleManager
import android.os.Build
import android.widget.Toast
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
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
import androidx.compose.material.icons.filled.Fingerprint
import androidx.compose.material.icons.filled.Info
import androidx.compose.material.icons.filled.LightMode
import androidx.compose.material.icons.filled.Logout
import androidx.compose.material.icons.filled.PhoneInTalk
import androidx.compose.material.icons.filled.Visibility
import androidx.compose.material.icons.filled.VisibilityOff
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Switch
import androidx.compose.material3.SwitchDefaults
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.viewmodel.compose.viewModel
import de.meinseelenfunke.app.di.ServiceLocator
import de.meinseelenfunke.app.di.ThemeMode
import de.meinseelenfunke.app.ui.theme.AppTheme
import de.meinseelenfunke.app.ui.theme.Emerald500
import de.meinseelenfunke.app.ui.theme.Gold
import de.meinseelenfunke.app.ui.theme.Rose500
import de.meinseelenfunke.app.ui.theme.SpaceBlack
import de.meinseelenfunke.app.util.PhoneConciergeManager

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun SettingsScreen(
    viewModel: SettingsViewModel = viewModel(),
    onLogout: () -> Unit = {}
) {
    val context = LocalContext.current
    val apiKey by viewModel.apiKey.collectAsState()
    var inputKey by remember(apiKey) { mutableStateOf(apiKey) }
    var apiKeyVisible by remember { mutableStateOf(false) }
    var biometricEnabled by remember { mutableStateOf(ServiceLocator.isBiometricEnabled()) }
    val currentThemeMode by ServiceLocator.themeModeState.collectAsState()
    val wakeWordEnabled by viewModel.wakeWordEnabled.collectAsState()

    val phoneConciergeEnabled by viewModel.phoneConciergeEnabled.collectAsState()
    val phoneRingDelaySec by viewModel.phoneRingDelaySec.collectAsState()
    val whitelistContacts by viewModel.whitelistContacts.collectAsState()
    var showPhoneInfoDialog by remember { mutableStateOf(false) }
    var permCheckTick by remember { mutableIntStateOf(0) }

    val hasPhonePerms = remember(permCheckTick) { PhoneConciergeManager.hasRequiredPermissions(context) }
    val hasScreeningRole = remember(permCheckTick) { PhoneConciergeManager.hasCallScreeningRole(context) }

    val roleLauncher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.StartActivityForResult()
    ) {
        permCheckTick++
    }

    val permissionLauncher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.RequestMultiplePermissions()
    ) {
        permCheckTick++
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q && !PhoneConciergeManager.hasCallScreeningRole(context)) {
            val rm = context.getSystemService(RoleManager::class.java)
            if (rm != null && rm.isRoleAvailable(RoleManager.ROLE_CALL_SCREENING)) {
                roleLauncher.launch(rm.createRequestRoleIntent(RoleManager.ROLE_CALL_SCREENING))
            }
        }
    }

    if (showPhoneInfoDialog) {
        AlertDialog(
            onDismissRequest = { showPhoneInfoDialog = false },
            containerColor = AppTheme.colors.cardBackground,
            title = {
                Text(
                    text = "Telefon-Assistent",
                    fontWeight = FontWeight.Bold,
                    color = Gold
                )
            },
            text = {
                Text(
                    text = "Funkira nimmt Anrufe freigegebener Kontakte nach der gewählten Klingelzeit automatisch an, leitet das Gespräch per Bluetooth an deinen Mini-PC weiter und legt das Anliegen als Aufgabe auf deinem Dashboard ab. Unbekannte Nummern werden niemals angenommen.",
                    fontSize = 13.sp,
                    color = AppTheme.colors.textPrimary
                )
            },
            confirmButton = {
                TextButton(onClick = { showPhoneInfoDialog = false }) {
                    Text("Verstanden", color = Gold, fontWeight = FontWeight.Bold)
                }
            }
        )
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = {
                    Text(
                        text = "Einstellungen",
                        fontSize = 18.sp,
                        fontWeight = FontWeight.Bold,
                        color = Gold
                    )
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

                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
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

            // Security: Biometric App Lock
            Card(
                modifier = Modifier.fillMaxWidth(),
                colors = CardDefaults.cardColors(containerColor = AppTheme.colors.cardBackground),
                border = BorderStroke(1.dp, AppTheme.colors.cardBorder),
                shape = RoundedCornerShape(16.dp)
            ) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(16.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.SpaceBetween
                ) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Icon(
                            imageVector = Icons.Default.Fingerprint,
                            contentDescription = null,
                            tint = Gold,
                            modifier = Modifier.size(20.dp)
                        )
                        Spacer(modifier = Modifier.width(10.dp))
                        Text(
                            text = "Biometrische App-Sperre",
                            fontSize = 14.sp,
                            fontWeight = FontWeight.Bold,
                            color = AppTheme.colors.textPrimary
                        )
                    }
                    Switch(
                        checked = biometricEnabled,
                        onCheckedChange = {
                            biometricEnabled = it
                            ServiceLocator.setBiometricEnabled(it)
                        },
                        colors = SwitchDefaults.colors(
                            checkedThumbColor = Gold,
                            checkedTrackColor = Gold.copy(alpha = 0.45f)
                        )
                    )
                }
            }

            // Google AI Studio API Key (masked + eye toggle)
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
                    Spacer(modifier = Modifier.height(8.dp))
                    OutlinedTextField(
                        value = inputKey,
                        onValueChange = { inputKey = it },
                        placeholder = { Text("Optionaler Geräteschlüssel") },
                        singleLine = true,
                        visualTransformation = if (apiKeyVisible) VisualTransformation.None else PasswordVisualTransformation(),
                        trailingIcon = {
                            IconButton(onClick = { apiKeyVisible = !apiKeyVisible }) {
                                Icon(
                                    imageVector = if (apiKeyVisible) Icons.Default.VisibilityOff else Icons.Default.Visibility,
                                    contentDescription = if (apiKeyVisible) "Verbergen" else "Anzeigen",
                                    tint = AppTheme.colors.textSecondary
                                )
                            }
                        },
                        modifier = Modifier.fillMaxWidth(),
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedBorderColor = Gold,
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
                            apiKeyVisible = false
                            Toast.makeText(context, "Gespeichert.", Toast.LENGTH_SHORT).show()
                        },
                        colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = SpaceBlack),
                        modifier = Modifier.align(Alignment.End)
                    ) {
                        Text("Speichern", fontWeight = FontWeight.Bold)
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
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(16.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.SpaceBetween
                ) {
                    Text(
                        text = "Wake Word (Hey Funkira)",
                        fontSize = 14.sp,
                        fontWeight = FontWeight.Bold,
                        color = AppTheme.colors.textPrimary
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

            // Funkira Whitelist Phone-Concierge Card
            Card(
                modifier = Modifier.fillMaxWidth(),
                colors = CardDefaults.cardColors(containerColor = AppTheme.colors.cardBackground),
                border = BorderStroke(1.dp, AppTheme.colors.cardBorder),
                shape = RoundedCornerShape(16.dp)
            ) {
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(16.dp),
                    verticalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Icon(
                                imageVector = Icons.Default.PhoneInTalk,
                                contentDescription = null,
                                tint = Gold,
                                modifier = Modifier.size(20.dp)
                            )
                            Spacer(modifier = Modifier.width(8.dp))
                            Text(
                                text = "Telefon-Assistent",
                                fontSize = 14.sp,
                                fontWeight = FontWeight.Bold,
                                color = AppTheme.colors.textPrimary
                            )
                            IconButton(
                                onClick = { showPhoneInfoDialog = true },
                                modifier = Modifier.size(28.dp)
                            ) {
                                Icon(
                                    imageVector = Icons.Default.Info,
                                    contentDescription = "Erklärung anzeigen",
                                    tint = Gold,
                                    modifier = Modifier.size(17.dp)
                                )
                            }
                        }
                        Switch(
                            checked = phoneConciergeEnabled,
                            onCheckedChange = { viewModel.setPhoneConciergeEnabled(it) },
                            colors = SwitchDefaults.colors(
                                checkedThumbColor = Gold,
                                checkedTrackColor = Gold.copy(alpha = 0.45f)
                            )
                        )
                    }

                    // Ring Delay Chips
                    Text(
                        text = "Klingelzeit",
                        fontSize = 12.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = AppTheme.colors.textSecondary
                    )
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        val delayOptions = listOf(0 to "Sofort", 8 to "8 Sek.", 12 to "12 Sek.", 18 to "18 Sek.")
                        delayOptions.forEach { (sec, label) ->
                            val selected = phoneRingDelaySec == sec
                            Box(
                                modifier = Modifier
                                    .weight(1f)
                                    .clip(RoundedCornerShape(10.dp))
                                    .background(if (selected) Gold.copy(alpha = 0.18f) else AppTheme.colors.glassSurface)
                                    .border(
                                        1.dp,
                                        if (selected) Gold else AppTheme.colors.cardBorder,
                                        RoundedCornerShape(10.dp)
                                    )
                                    .clickable { viewModel.setPhoneRingDelaySec(sec) }
                                    .padding(vertical = 9.dp),
                                contentAlignment = Alignment.Center
                            ) {
                                Text(
                                    text = label,
                                    fontSize = 12.sp,
                                    fontWeight = if (selected) FontWeight.Bold else FontWeight.Medium,
                                    color = if (selected) Gold else AppTheme.colors.textPrimary
                                )
                            }
                        }
                    }

                    // Permission & Call Screening Role Setup
                    if (!hasPhonePerms || !hasScreeningRole) {
                        Button(
                            onClick = {
                                val reqPerms = mutableListOf(
                                    Manifest.permission.READ_PHONE_STATE,
                                    Manifest.permission.ANSWER_PHONE_CALLS,
                                    Manifest.permission.READ_CALL_LOG
                                )
                                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                                    reqPerms.add(Manifest.permission.BLUETOOTH_CONNECT)
                                }
                                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
                                    reqPerms.add(Manifest.permission.POST_NOTIFICATIONS)
                                }
                                permissionLauncher.launch(reqPerms.toTypedArray())
                            },
                            colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = SpaceBlack),
                            modifier = Modifier.fillMaxWidth(),
                            shape = RoundedCornerShape(12.dp)
                        ) {
                            Text("Telefon-Freigabe aktivieren", fontWeight = FontWeight.Bold)
                        }
                    } else {
                        Row(
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(6.dp)
                        ) {
                            Icon(
                                imageVector = Icons.Default.Check,
                                contentDescription = null,
                                tint = Emerald500,
                                modifier = Modifier.size(16.dp)
                            )
                            Text(
                                text = "Telefon-Freigabe aktiv",
                                fontSize = 12.sp,
                                fontWeight = FontWeight.SemiBold,
                                color = Emerald500
                            )
                        }
                    }

                    if (whitelistContacts.isNotEmpty()) {
                        HorizontalDivider(color = AppTheme.colors.cardBorder)
                        Text(
                            text = "Freigegebene Kontakte",
                            fontSize = 12.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = AppTheme.colors.textSecondary
                        )
                        Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                            whitelistContacts.forEach { contact ->
                                val title = if (contact.nickname.isNotBlank()) {
                                    "${contact.nickname} · ${contact.fullName}"
                                } else {
                                    contact.fullName
                                }
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    verticalAlignment = Alignment.CenterVertically,
                                    horizontalArrangement = Arrangement.SpaceBetween
                                ) {
                                    Text(
                                        text = title,
                                        fontSize = 13.sp,
                                        fontWeight = FontWeight.Medium,
                                        color = AppTheme.colors.textPrimary,
                                        modifier = Modifier.weight(1f)
                                    )
                                    Switch(
                                        checked = contact.whitelisted,
                                        onCheckedChange = { viewModel.toggleContactWhitelist(contact.id, it) },
                                        colors = SwitchDefaults.colors(
                                            checkedThumbColor = Gold,
                                            checkedTrackColor = Gold.copy(alpha = 0.45f)
                                        )
                                    )
                                }
                            }
                        }
                    }
                }
            }

            // Clean Session Logout Card
            OutlinedButton(
                onClick = onLogout,
                modifier = Modifier.fillMaxWidth(),
                border = BorderStroke(1.dp, Rose500.copy(alpha = 0.6f)),
                shape = RoundedCornerShape(14.dp),
                colors = ButtonDefaults.outlinedButtonColors(contentColor = Rose500)
            ) {
                Icon(Icons.Default.Logout, contentDescription = null, modifier = Modifier.size(18.dp))
                Spacer(modifier = Modifier.width(8.dp))
                Text("Abmelden", fontWeight = FontWeight.Bold)
            }
        }
    }
}
