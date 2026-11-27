package de.meinseelenfunke.app.ui.screens

import android.Manifest
import android.app.role.RoleManager
import android.content.Intent
import android.net.Uri
import android.os.Build
import android.provider.Settings
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
import androidx.compose.material.icons.filled.Bolt
import androidx.compose.material.icons.filled.BrightnessAuto
import androidx.compose.material.icons.filled.Check
import androidx.compose.material.icons.filled.CloudUpload
import androidx.compose.material.icons.filled.DarkMode
import androidx.compose.material.icons.filled.Fingerprint
import androidx.compose.material.icons.filled.Info
import androidx.compose.material.icons.filled.LightMode
import androidx.compose.material.icons.filled.Logout
import androidx.compose.material.icons.filled.PhoneForwarded
import androidx.compose.material.icons.filled.PhoneInTalk
import androidx.compose.material.icons.filled.SystemUpdate
import androidx.compose.material.icons.filled.Visibility
import androidx.compose.material.icons.filled.VisibilityOff
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.LinearProgressIndicator
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
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
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
import androidx.compose.ui.platform.LocalLifecycleOwner
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.LifecycleEventObserver
import androidx.lifecycle.viewmodel.compose.viewModel
import de.meinseelenfunke.app.di.ServiceLocator
import de.meinseelenfunke.app.di.ThemeMode
import de.meinseelenfunke.app.ui.theme.AppTheme
import de.meinseelenfunke.app.ui.theme.Emerald500
import de.meinseelenfunke.app.ui.theme.Gold
import de.meinseelenfunke.app.ui.theme.Rose500
import de.meinseelenfunke.app.ui.theme.SpaceBlack
import de.meinseelenfunke.app.util.AppUpdateManager
import de.meinseelenfunke.app.util.OtaUpdateState
import de.meinseelenfunke.app.util.PhoneConciergeManager

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun SettingsScreen(
    viewModel: SettingsViewModel = viewModel(),
    onLogout: () -> Unit = {}
) {
    val context = LocalContext.current
    val userType by ServiceLocator.userTypeState.collectAsState(initial = ServiceLocator.getUserType())
    val isAdmin = userType == "admin"
    val apiKey by viewModel.apiKey.collectAsState()
    var inputKey by remember(apiKey) { mutableStateOf(apiKey) }
    var apiKeyVisible by remember { mutableStateOf(false) }
    var biometricEnabled by remember { mutableStateOf(ServiceLocator.isBiometricEnabled()) }
    val currentThemeMode by ServiceLocator.themeModeState.collectAsState()
    val wakeWordEnabled by viewModel.wakeWordEnabled.collectAsState()

    val tapoEmail by viewModel.tapoEmail.collectAsState()
    val tapoPassword by viewModel.tapoPassword.collectAsState()
    var inputTapoEmail by remember(tapoEmail) { mutableStateOf(tapoEmail) }
    var inputTapoPass by remember(tapoPassword) { mutableStateOf(tapoPassword) }
    var tapoPassVisible by remember { mutableStateOf(false) }
    var showTapoInfoDialog by remember { mutableStateOf(false) }

    val kdriveConfig by viewModel.kdriveConfig.collectAsState()
    val kdriveSaving by viewModel.kdriveSaving.collectAsState()
    val kdriveStatusFeedback by viewModel.kdriveStatusFeedback.collectAsState()
    var inputKdriveId by remember(kdriveConfig.drive_id) {
        mutableStateOf(kdriveConfig.drive_id.ifBlank { "2216011" })
    }
    var inputKdrivePass by remember { mutableStateOf("") }
    var kdrivePassVisible by remember { mutableStateOf(false) }
    var showKdriveEditFields by remember { mutableStateOf(false) }
    var showKdriveInfoDialog by remember { mutableStateOf(false) }

    val phoneConciergeEnabled by viewModel.phoneConciergeEnabled.collectAsState()
    val phoneCustomerServiceEnabled by viewModel.phoneCustomerServiceEnabled.collectAsState()
    val phoneRingDelaySec by viewModel.phoneRingDelaySec.collectAsState()
    val phoneAnswerMode by viewModel.phoneAnswerMode.collectAsState()
    val carrierForwardingConfirmed by viewModel.carrierForwardingConfirmed.collectAsState()
    val autoVerifyNotes by viewModel.autoVerifyNotes.collectAsState()
    val whitelistContacts by viewModel.whitelistContacts.collectAsState()
    var showPhoneInfoDialog by remember { mutableStateOf(false) }
    var showCarrierConfirmDialog by remember { mutableStateOf(false) }
    var awaitingCarrierConfirm by remember { mutableStateOf(false) }
    var showOtaInfoDialog by remember { mutableStateOf(false) }
    var permCheckTick by remember { mutableIntStateOf(0) }
    var contactSearchQuery by remember { mutableStateOf("") }

    val updateState by AppUpdateManager.state.collectAsState()
    val (installedVerName, installedVerCode) = remember(permCheckTick) {
        AppUpdateManager.getInstalledVersionInfo(context)
    }

    @Suppress("DEPRECATION")
    val lifecycleOwner = LocalLifecycleOwner.current

    LaunchedEffect(Unit) {
        AppUpdateManager.checkForUpdate(context, silent = true)
    }

    DisposableEffect(lifecycleOwner, isAdmin) {
        val observer = LifecycleEventObserver { _, event ->
            if (event == Lifecycle.Event.ON_RESUME) {
                permCheckTick++
                if (awaitingCarrierConfirm) {
                    awaitingCarrierConfirm = false
                    showCarrierConfirmDialog = true
                }
                if (isAdmin) {
                    viewModel.refreshPhoneConcierge()
                    viewModel.refreshKdriveConfig()
                }
                AppUpdateManager.resumePendingInstallIfPermitted(context)
            }
        }
        lifecycleOwner.lifecycle.addObserver(observer)
        onDispose {
            lifecycleOwner.lifecycle.removeObserver(observer)
        }
    }

    val hasPhonePerms = remember(permCheckTick) { PhoneConciergeManager.hasRequiredPermissions(context) }
    val hasScreeningRole = remember(permCheckTick) { PhoneConciergeManager.hasCallScreeningRole(context) }
    val hasMicPerm = remember(permCheckTick) { PhoneConciergeManager.hasMicrophonePermission(context) }
    val batteryIgnored = remember(permCheckTick) { PhoneConciergeManager.isBatteryOptimizationIgnored(context) }

    val roleLauncher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.StartActivityForResult()
    ) {
        permCheckTick++
    }

    val micPermLauncher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.RequestPermission()
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

    val ensurePhoneAssistantAccess = {
        if (!PhoneConciergeManager.hasRequiredPermissions(context)) {
            permissionLauncher.launch(PhoneConciergeManager.buildRuntimePermissionRequestArray())
        } else if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q && !PhoneConciergeManager.hasCallScreeningRole(context)) {
            val rm = context.getSystemService(RoleManager::class.java)
            if (rm != null && rm.isRoleAvailable(RoleManager.ROLE_CALL_SCREENING)) {
                roleLauncher.launch(rm.createRequestRoleIntent(RoleManager.ROLE_CALL_SCREENING))
            }
        } else if (!PhoneConciergeManager.hasMicrophonePermission(context) &&
            phoneAnswerMode != PhoneConciergeManager.ANSWER_MODE_CARRIER
        ) {
            micPermLauncher.launch(Manifest.permission.RECORD_AUDIO)
        }
    }

    if (showCarrierConfirmDialog) {
        AlertDialog(
            onDismissRequest = { showCarrierConfirmDialog = false },
            containerColor = AppTheme.colors.cardBackground,
            title = {
                Text(
                    text = "Rufumleitung aktiv?",
                    fontWeight = FontWeight.Bold,
                    color = Gold
                )
            },
            text = {
                Text(
                    text = "Hat dein Mobilfunknetz die Rufumleitung nach dem grünen Hörer bestätigt?",
                    fontSize = 13.sp,
                    color = AppTheme.colors.textPrimary
                )
            },
            dismissButton = {
                TextButton(
                    onClick = {
                        viewModel.confirmCarrierForwarding(false)
                        showCarrierConfirmDialog = false
                    }
                ) {
                    Text("Noch nicht", color = AppTheme.colors.textSecondary)
                }
            },
            confirmButton = {
                TextButton(
                    onClick = {
                        viewModel.confirmCarrierForwarding(true)
                        showCarrierConfirmDialog = false
                    }
                ) {
                    Text("Ja, aktiv", color = Gold, fontWeight = FontWeight.Bold)
                }
            }
        )
    }

    if (showPhoneInfoDialog) {
        val diagLines = remember(showPhoneInfoDialog, permCheckTick) {
            PhoneConciergeManager.getDiagnosticTraceLines(context)
        }
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
                Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    Text(
                        text = "Funkira übernimmt Anrufe deiner Whitelist-Kontakte vertraut als persönliche Sekretärin und bei aktivem 'Vollständiger Kundenservice' zusätzlich sämtliche Kunden- und Geschäftsanrufe (inkl. automatischem Support-Ticket, Spam-Filter und App-Protokoll). Alle Anrufer ohne Whitelist werden dabei einheitlich im Kundenservice-Modus betreut.",
                        fontSize = 13.sp,
                        color = AppTheme.colors.textPrimary
                    )
                    Text(
                        text = "Annahme-Modi: 'Auto' nutzt das Mobilfunknetz (sobald Rufumleitung aktiv) und springt sonst direkt am Handy ein. 'Netz' nutzt ausschließlich die Gifhorn-Leitung (**004*). 'Handy' nimmt Anrufe direkt am Smartphone entgegen.",
                        fontSize = 12.sp,
                        color = AppTheme.colors.textSecondary
                    )
                    Text(
                        text = "Einmalige Netz-Einrichtung: Tippe auf 'Rufumleitung verbinden' und drücke im geöffneten Telefonfeld einmal den grünen Hörer.",
                        fontSize = 12.sp,
                        fontWeight = FontWeight.Medium,
                        color = Gold
                    )
                    TextButton(
                        onClick = {
                            showPhoneInfoDialog = false
                            PhoneConciergeManager.openCarrierForwardingDialer(context, activate = false)
                            viewModel.refreshCarrierForwardingState()
                        }
                    ) {
                        Text(
                            text = "Rufumleitung aufheben (##004#)",
                            fontSize = 12.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = Rose500
                        )
                    }
                    if (diagLines.isNotEmpty()) {
                        HorizontalDivider(color = AppTheme.colors.cardBorder)
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Text(
                                text = "Letztes Anruf-Protokoll",
                                fontSize = 12.sp,
                                fontWeight = FontWeight.SemiBold,
                                color = Gold
                            )
                            TextButton(
                                onClick = {
                                    viewModel.clearPhoneDiagnosticTrace()
                                    permCheckTick++
                                }
                            ) {
                                Text("Leeren", fontSize = 11.sp, color = AppTheme.colors.textMuted)
                            }
                        }
                        Text(
                            text = diagLines.takeLast(6).joinToString("\n"),
                            fontSize = 11.sp,
                            color = AppTheme.colors.textMuted
                        )
                    }
                }
            },
            confirmButton = {
                TextButton(onClick = { showPhoneInfoDialog = false }) {
                    Text("Verstanden", color = Gold, fontWeight = FontWeight.Bold)
                }
            }
        )
    }

    if (showOtaInfoDialog) {
        AlertDialog(
            onDismissRequest = { showOtaInfoDialog = false },
            containerColor = AppTheme.colors.cardBackground,
            title = {
                Text(
                    text = "App-Update (Privater Kanal)",
                    fontWeight = FontWeight.Bold,
                    color = Gold
                )
            },
            text = {
                Text(
                    text = "Lädt signierte Funkenwerk-Releases direkt über deine verschlüsselte Admin-Sitzung vom Mein-Seelenfunke-Portal, prüft vor dem Öffnen die SHA-256-Prüfsumme sowie das 4096-Bit-RSA-Release-Zertifikat und übergibt das Paket an den Android-System-Installer.",
                    fontSize = 13.sp,
                    color = AppTheme.colors.textPrimary
                )
            },
            confirmButton = {
                TextButton(onClick = { showOtaInfoDialog = false }) {
                    Text("Verstanden", color = Gold, fontWeight = FontWeight.Bold)
                }
            }
        )
    }

    if (showTapoInfoDialog) {
        AlertDialog(
            onDismissRequest = { showTapoInfoDialog = false },
            containerColor = AppTheme.colors.cardBackground,
            title = {
                Text(
                    text = "Smart-Home (Tapo KLAP v2)",
                    fontWeight = FontWeight.Bold,
                    color = Gold
                )
            },
            text = {
                Text(
                    text = "Ermöglicht die direkte Steuerung von Tapo P100/P110 Steckdosen und Lichterketten im Heimnetzwerk ohne Cloud-Umweg über das sichere KLAP-v2-Protokoll.",
                    fontSize = 13.sp,
                    color = AppTheme.colors.textPrimary
                )
            },
            confirmButton = {
                TextButton(onClick = { showTapoInfoDialog = false }) {
                    Text("Verstanden", color = Gold, fontWeight = FontWeight.Bold)
                }
            }
        )
    }

    if (showKdriveInfoDialog) {
        AlertDialog(
            onDismissRequest = { showKdriveInfoDialog = false },
            containerColor = AppTheme.colors.cardBackground,
            title = {
                Text(
                    text = "kDrive Eingang-Trichter",
                    fontWeight = FontWeight.Bold,
                    color = Gold
                )
            },
            text = {
                Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    Text(
                        text = "Überträgt deine Scans über die verschlüsselte Infomaniak kDrive API v3 (SHA-256 geprüft) direkt in deinen Ordner 'Meine Daten/00_Eingang_Trichter' (24/7, auch bei ausgeschaltetem PC).",
                        fontSize = 13.sp,
                        color = AppTheme.colors.textPrimary
                    )
                    Text(
                        text = "Der API-Token liegt geschützt im Server-Tresor und wird niemals auf dem Smartphone gespeichert.",
                        fontSize = 12.sp,
                        color = Gold
                    )
                }
            },
            confirmButton = {
                TextButton(onClick = { showKdriveInfoDialog = false }) {
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

            // Private OTA App Update Card
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
                                imageVector = Icons.Default.SystemUpdate,
                                contentDescription = null,
                                tint = Gold,
                                modifier = Modifier.size(20.dp)
                            )
                            Spacer(modifier = Modifier.width(10.dp))
                            Text(
                                text = "App-Update",
                                fontSize = 14.sp,
                                fontWeight = FontWeight.Bold,
                                color = AppTheme.colors.textPrimary
                            )
                            IconButton(
                                onClick = { showOtaInfoDialog = true },
                                modifier = Modifier.size(28.dp)
                            ) {
                                Icon(
                                    imageVector = Icons.Default.Info,
                                    contentDescription = "Info",
                                    tint = AppTheme.colors.textMuted,
                                    modifier = Modifier.size(16.dp)
                                )
                            }
                        }

                        Row(
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(6.dp)
                        ) {
                            Text(
                                text = "v$installedVerName · Build $installedVerCode",
                                fontSize = 11.sp,
                                fontWeight = FontWeight.Medium,
                                color = AppTheme.colors.textMuted
                            )
                            when (val st = updateState) {
                                is OtaUpdateState.UpToDate -> {
                                    Box(
                                        modifier = Modifier
                                            .clip(RoundedCornerShape(6.dp))
                                            .background(Emerald500.copy(alpha = 0.15f))
                                            .padding(horizontal = 7.dp, vertical = 2.dp)
                                    ) {
                                        Text(
                                            text = "Aktuell",
                                            fontSize = 11.sp,
                                            fontWeight = FontWeight.Bold,
                                            color = Emerald500
                                        )
                                    }
                                }
                                is OtaUpdateState.Available -> {
                                    Box(
                                        modifier = Modifier
                                            .clip(RoundedCornerShape(6.dp))
                                            .background(Gold.copy(alpha = 0.2f))
                                            .padding(horizontal = 7.dp, vertical = 2.dp)
                                    ) {
                                        Text(
                                            text = "v${st.manifest.versionName} bereit",
                                            fontSize = 11.sp,
                                            fontWeight = FontWeight.Bold,
                                            color = Gold
                                        )
                                    }
                                }
                                else -> Unit
                            }
                        }
                    }

                    when (val st = updateState) {
                        is OtaUpdateState.Downloading -> {
                            Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Text(
                                        text = "v${st.manifest.versionName}",
                                        fontSize = 12.sp,
                                        fontWeight = FontWeight.SemiBold,
                                        color = AppTheme.colors.textPrimary
                                    )
                                    Text(
                                        text = "${st.progressPercent} %",
                                        fontSize = 12.sp,
                                        fontWeight = FontWeight.Bold,
                                        color = Gold
                                    )
                                }
                                LinearProgressIndicator(
                                    progress = { st.progressPercent / 100f },
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .height(6.dp)
                                        .clip(RoundedCornerShape(3.dp)),
                                    color = Gold,
                                    trackColor = AppTheme.colors.glassSurface
                                )
                            }
                        }
                        is OtaUpdateState.Available -> {
                            Button(
                                onClick = { AppUpdateManager.downloadAndInstall(context, st.manifest) },
                                modifier = Modifier.fillMaxWidth(),
                                shape = RoundedCornerShape(10.dp),
                                colors = ButtonDefaults.buttonColors(
                                    containerColor = Gold,
                                    contentColor = SpaceBlack
                                )
                            ) {
                                Text(
                                    text = "Update v${st.manifest.versionName} installieren",
                                    fontWeight = FontWeight.Bold,
                                    fontSize = 13.sp
                                )
                            }
                        }
                        is OtaUpdateState.AwaitingInstallPermission -> {
                            Button(
                                onClick = { AppUpdateManager.continueInstallOrOpenPermission(context) },
                                modifier = Modifier.fillMaxWidth(),
                                shape = RoundedCornerShape(10.dp),
                                colors = ButtonDefaults.buttonColors(
                                    containerColor = Gold,
                                    contentColor = SpaceBlack
                                )
                            ) {
                                Text(
                                    text = "Installation fortsetzen",
                                    fontWeight = FontWeight.Bold,
                                    fontSize = 13.sp
                                )
                            }
                        }
                        else -> {
                            val isChecking = st is OtaUpdateState.Checking
                            OutlinedButton(
                                onClick = { AppUpdateManager.checkForUpdate(context, silent = false) },
                                enabled = !isChecking,
                                modifier = Modifier.fillMaxWidth(),
                                shape = RoundedCornerShape(10.dp),
                                border = BorderStroke(1.dp, Gold.copy(alpha = 0.6f))
                            ) {
                                if (isChecking) {
                                    CircularProgressIndicator(
                                        modifier = Modifier.size(16.dp),
                                        color = Gold,
                                        strokeWidth = 2.dp
                                    )
                                    Spacer(modifier = Modifier.width(8.dp))
                                }
                                Text(
                                    text = if (isChecking) "Prüfe..." else "Auf Updates prüfen",
                                    fontWeight = FontWeight.SemiBold,
                                    fontSize = 13.sp,
                                    color = Gold
                                )
                            }
                        }
                    }

                    when (val st = updateState) {
                        is OtaUpdateState.Error -> {
                            Text(
                                text = st.message,
                                fontSize = 12.sp,
                                fontWeight = FontWeight.Medium,
                                color = Rose500
                            )
                        }
                        is OtaUpdateState.InstallerLaunched -> {
                            Text(
                                text = "Installer geöffnet (v${st.manifest.versionName})",
                                fontSize = 12.sp,
                                fontWeight = FontWeight.Medium,
                                color = Emerald500
                            )
                        }
                        else -> Unit
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

            if (isAdmin) {
            // kDrive Eingang-Trichter (WebDAV Direct Sort) Card
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
                                imageVector = Icons.Default.CloudUpload,
                                contentDescription = null,
                                tint = Gold,
                                modifier = Modifier.size(20.dp)
                            )
                            Spacer(modifier = Modifier.width(8.dp))
                            Text(
                                text = "kDrive Eingang-Trichter",
                                fontSize = 14.sp,
                                fontWeight = FontWeight.Bold,
                                color = AppTheme.colors.textPrimary
                            )
                            IconButton(
                                onClick = { showKdriveInfoDialog = true },
                                modifier = Modifier.size(28.dp)
                            ) {
                                Icon(
                                    imageVector = Icons.Default.Info,
                                    contentDescription = "Erklärung anzeigen",
                                    tint = AppTheme.colors.textMuted,
                                    modifier = Modifier.size(16.dp)
                                )
                            }
                        }

                        when {
                            kdriveConfig.configured -> {
                                Box(
                                    modifier = Modifier
                                        .clip(RoundedCornerShape(6.dp))
                                        .background(Emerald500.copy(alpha = 0.15f))
                                        .padding(horizontal = 7.dp, vertical = 2.dp)
                                ) {
                                    Text(
                                        text = "Aktiv",
                                        fontSize = 11.sp,
                                        fontWeight = FontWeight.Bold,
                                        color = Emerald500
                                    )
                                }
                            }
                            kdriveConfig.pending_relay_count > 0 -> {
                                Box(
                                    modifier = Modifier
                                        .clip(RoundedCornerShape(6.dp))
                                        .background(Gold.copy(alpha = 0.2f))
                                        .padding(horizontal = 7.dp, vertical = 2.dp)
                                ) {
                                    Text(
                                        text = "Wartend (${kdriveConfig.pending_relay_count})",
                                        fontSize = 11.sp,
                                        fontWeight = FontWeight.Bold,
                                        color = Gold
                                    )
                                }
                            }
                            else -> {
                                Box(
                                    modifier = Modifier
                                        .clip(RoundedCornerShape(6.dp))
                                        .background(AppTheme.colors.cardBorder.copy(alpha = 0.3f))
                                        .padding(horizontal = 7.dp, vertical = 2.dp)
                                ) {
                                    Text(
                                        text = "Nicht verbunden",
                                        fontSize = 11.sp,
                                        fontWeight = FontWeight.Bold,
                                        color = AppTheme.colors.textMuted
                                    )
                                }
                            }
                        }
                    }

                    if (kdriveConfig.configured && !showKdriveEditFields) {
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Text(
                                text = "kDrive ${kdriveConfig.drive_id} · 00_Eingang_Trichter",
                                fontSize = 12.5.sp,
                                fontWeight = FontWeight.Medium,
                                color = AppTheme.colors.textSecondary
                            )
                            Row(verticalAlignment = Alignment.CenterVertically) {
                                if (kdriveConfig.pending_relay_count > 0) {
                                    TextButton(
                                        onClick = { viewModel.flushPendingKdriveRelay() },
                                        enabled = !kdriveSaving
                                    ) {
                                        Text(
                                            text = "Wartende (${kdriveConfig.pending_relay_count}) senden",
                                            color = Gold,
                                            fontWeight = FontWeight.SemiBold,
                                            fontSize = 12.sp
                                        )
                                    }
                                }
                                TextButton(onClick = { showKdriveEditFields = true }) {
                                    Text(
                                        text = "Ändern",
                                        color = Gold,
                                        fontWeight = FontWeight.Bold,
                                        fontSize = 12.sp
                                    )
                                }
                            }
                        }
                    } else {
                        OutlinedTextField(
                            value = inputKdriveId,
                            onValueChange = { inputKdriveId = it },
                            label = { Text("kDrive-ID oder Link") },
                            placeholder = { Text("2216011") },
                            singleLine = true,
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

                        OutlinedTextField(
                            value = inputKdrivePass,
                            onValueChange = { inputKdrivePass = it },
                            label = { Text("Infomaniak API-Token") },
                            placeholder = {
                                Text(
                                    if (kdriveConfig.has_password) "Gespeichert (leer lassen zum Behalten)"
                                    else "API-Token (Berechtigung: drive)"
                                )
                            },
                            singleLine = true,
                            visualTransformation = if (kdrivePassVisible) VisualTransformation.None else PasswordVisualTransformation(),
                            trailingIcon = {
                                IconButton(onClick = { kdrivePassVisible = !kdrivePassVisible }) {
                                    Icon(
                                        imageVector = if (kdrivePassVisible) Icons.Default.VisibilityOff else Icons.Default.Visibility,
                                        contentDescription = if (kdrivePassVisible) "Verbergen" else "Anzeigen",
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

                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.End,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            if (kdriveConfig.configured) {
                                TextButton(onClick = { showKdriveEditFields = false }) {
                                    Text("Schließen", color = AppTheme.colors.textMuted, fontSize = 12.sp)
                                }
                                Spacer(modifier = Modifier.width(6.dp))
                            }
                            Button(
                                onClick = {
                                    viewModel.saveKdriveConfig(inputKdriveId, kdriveConfig.email, inputKdrivePass) { ok, msg ->
                                        if (ok) {
                                            inputKdrivePass = ""
                                            kdrivePassVisible = false
                                            showKdriveEditFields = false
                                        }
                                        Toast.makeText(context, msg, Toast.LENGTH_SHORT).show()
                                    }
                                },
                                enabled = !kdriveSaving && inputKdriveId.isNotBlank() &&
                                    (inputKdrivePass.isNotBlank() || kdriveConfig.has_password),
                                colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = SpaceBlack)
                            ) {
                                if (kdriveSaving) {
                                    CircularProgressIndicator(
                                        color = SpaceBlack,
                                        modifier = Modifier.size(16.dp),
                                        strokeWidth = 2.dp
                                    )
                                    Spacer(modifier = Modifier.width(6.dp))
                                }
                                Text(
                                    text = if (kdriveSaving) "Prüfe..." else "Verbinden & Speichern",
                                    fontWeight = FontWeight.Bold
                                )
                            }
                        }
                    }

                    if (kdriveStatusFeedback != null) {
                        val fb = kdriveStatusFeedback!!
                        Text(
                            text = fb.message,
                            fontSize = 12.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = if (fb.isSuccess) Emerald500 else Rose500
                        )
                    } else if (!kdriveConfig.last_error.isNullOrBlank()) {
                        Text(
                            text = kdriveConfig.last_error!!,
                            fontSize = 12.sp,
                            fontWeight = FontWeight.Medium,
                            color = Rose500
                        )
                    }
                }
            }

            // Smart-Home (Tapo) Card
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
                                imageVector = Icons.Default.Bolt,
                                contentDescription = null,
                                tint = Gold,
                                modifier = Modifier.size(20.dp)
                            )
                            Spacer(modifier = Modifier.width(8.dp))
                            Text(
                                text = "Smart-Home (Tapo)",
                                fontSize = 14.sp,
                                fontWeight = FontWeight.Bold,
                                color = AppTheme.colors.textPrimary
                            )
                            IconButton(
                                onClick = { showTapoInfoDialog = true },
                                modifier = Modifier.size(28.dp)
                            ) {
                                Icon(
                                    imageVector = Icons.Default.Info,
                                    contentDescription = "Info",
                                    tint = AppTheme.colors.textMuted,
                                    modifier = Modifier.size(16.dp)
                                )
                            }
                        }

                        if (tapoEmail.isNotBlank() && tapoPassword.isNotBlank()) {
                            Box(
                                modifier = Modifier
                                    .clip(RoundedCornerShape(6.dp))
                                    .background(Emerald500.copy(alpha = 0.15f))
                                    .padding(horizontal = 7.dp, vertical = 2.dp)
                            ) {
                                Text(
                                    text = "Aktiv",
                                    fontSize = 11.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = Emerald500
                                )
                            }
                        } else {
                            Box(
                                modifier = Modifier
                                    .clip(RoundedCornerShape(6.dp))
                                    .background(AppTheme.colors.cardBorder.copy(alpha = 0.3f))
                                    .padding(horizontal = 7.dp, vertical = 2.dp)
                            ) {
                                Text(
                                    text = "Nicht hinterlegt",
                                    fontSize = 11.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = AppTheme.colors.textMuted
                                )
                            }
                        }
                    }

                    OutlinedTextField(
                        value = inputTapoEmail,
                        onValueChange = { inputTapoEmail = it },
                        label = { Text("Tapo E-Mail") },
                        placeholder = { Text("tapo@mein-seelenfunke.de") },
                        singleLine = true,
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

                    OutlinedTextField(
                        value = inputTapoPass,
                        onValueChange = { inputTapoPass = it },
                        label = { Text("Tapo Passwort") },
                        singleLine = true,
                        visualTransformation = if (tapoPassVisible) VisualTransformation.None else PasswordVisualTransformation(),
                        trailingIcon = {
                            IconButton(onClick = { tapoPassVisible = !tapoPassVisible }) {
                                Icon(
                                    imageVector = if (tapoPassVisible) Icons.Default.VisibilityOff else Icons.Default.Visibility,
                                    contentDescription = if (tapoPassVisible) "Verbergen" else "Anzeigen",
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

                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.End
                    ) {
                        Button(
                            onClick = {
                                viewModel.saveTapoCredentials(inputTapoEmail, inputTapoPass)
                                tapoPassVisible = false
                                Toast.makeText(context, "Tapo-Zugangsdaten gespeichert.", Toast.LENGTH_SHORT).show()
                            },
                            colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = SpaceBlack)
                        ) {
                            Text("Speichern", fontWeight = FontWeight.Bold)
                        }
                    }
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
            val wakeWordMicLauncher = rememberLauncherForActivityResult(
                contract = ActivityResultContracts.RequestPermission()
            ) { granted ->
                if (granted) {
                    de.meinseelenfunke.app.services.WakeWordService.syncServiceState(context)
                } else {
                    Toast.makeText(context, "Mikrofon-Berechtigung benötigt.", Toast.LENGTH_SHORT).show()
                }
            }

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
                        onCheckedChange = { enabled ->
                            viewModel.setWakeWordEnabled(enabled)
                            if (enabled && androidx.core.content.ContextCompat.checkSelfPermission(
                                    context,
                                    Manifest.permission.RECORD_AUDIO
                                ) != android.content.pm.PackageManager.PERMISSION_GRANTED
                            ) {
                                wakeWordMicLauncher.launch(Manifest.permission.RECORD_AUDIO)
                            }
                        },
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
                            onCheckedChange = {
                                viewModel.setPhoneConciergeEnabled(it)
                                if (it) ensurePhoneAssistantAccess()
                            },
                            colors = SwitchDefaults.colors(
                                checkedThumbColor = Gold,
                                checkedTrackColor = Gold.copy(alpha = 0.45f)
                            )
                        )
                    }

                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Text(
                            text = "Vollständiger Kundenservice",
                            fontSize = 13.sp,
                            fontWeight = FontWeight.Medium,
                            color = AppTheme.colors.textPrimary
                        )
                        Switch(
                            checked = phoneCustomerServiceEnabled,
                            onCheckedChange = {
                                viewModel.setPhoneCustomerServiceEnabled(it)
                                if (it && phoneConciergeEnabled) ensurePhoneAssistantAccess()
                            },
                            colors = SwitchDefaults.colors(
                                checkedThumbColor = Gold,
                                checkedTrackColor = Gold.copy(alpha = 0.45f)
                            )
                        )
                    }

                    // Answer Mode Chips
                    Text(
                        text = "Annahme-Modus",
                        fontSize = 12.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = AppTheme.colors.textSecondary
                    )
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        val modeOptions = listOf(
                            PhoneConciergeManager.ANSWER_MODE_AUTO to "Auto",
                            PhoneConciergeManager.ANSWER_MODE_CARRIER to "Netz",
                            PhoneConciergeManager.ANSWER_MODE_ON_DEVICE to "Handy"
                        )
                        modeOptions.forEach { (mode, label) ->
                            val selected = phoneAnswerMode == mode
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
                                    .clickable {
                                        viewModel.setPhoneAnswerMode(mode)
                                        if (phoneConciergeEnabled) ensurePhoneAssistantAccess()
                                    }
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
                                    .clickable {
                                        viewModel.setPhoneRingDelaySec(sec)
                                        if (phoneConciergeEnabled) ensurePhoneAssistantAccess()
                                    }
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

                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Text(
                            text = "KI-Notizen-Gegenprüfung",
                            fontSize = 13.sp,
                            fontWeight = FontWeight.Medium,
                            color = AppTheme.colors.textPrimary
                        )
                        Switch(
                            checked = autoVerifyNotes,
                            onCheckedChange = { viewModel.setAutoVerifyNotes(it) },
                            colors = SwitchDefaults.colors(
                                checkedThumbColor = Gold,
                                checkedTrackColor = Gold.copy(alpha = 0.45f)
                            )
                        )
                    }

                    // Permission, Microphone, Call Screening Role & Battery Setup
                    val needsMicForMode = phoneAnswerMode != PhoneConciergeManager.ANSWER_MODE_CARRIER && !hasMicPerm
                    if (!hasPhonePerms || !hasScreeningRole || needsMicForMode) {
                        Button(
                            onClick = { ensurePhoneAssistantAccess() },
                            colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = SpaceBlack),
                            modifier = Modifier.fillMaxWidth(),
                            shape = RoundedCornerShape(12.dp)
                        ) {
                            val btnLabel = when {
                                !hasPhonePerms -> "Telefon-Freigabe aktivieren"
                                !hasScreeningRole -> "Als Anruffilter bestätigen"
                                else -> "Mikrofon-Freigabe aktivieren"
                            }
                            Text(text = btnLabel, fontWeight = FontWeight.Bold)
                        }
                    } else if (!batteryIgnored) {
                        OutlinedButton(
                            onClick = {
                                try {
                                    context.startActivity(
                                        Intent(Settings.ACTION_IGNORE_BATTERY_OPTIMIZATION_SETTINGS).apply {
                                            addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                                        }
                                    )
                                } catch (_: Exception) {
                                    try {
                                        context.startActivity(
                                            Intent(
                                                Settings.ACTION_APPLICATION_DETAILS_SETTINGS,
                                                Uri.fromParts("package", context.packageName, null)
                                            ).apply { addFlags(Intent.FLAG_ACTIVITY_NEW_TASK) }
                                        )
                                    } catch (_: Exception) { }
                                }
                            },
                            modifier = Modifier.fillMaxWidth(),
                            shape = RoundedCornerShape(10.dp),
                            border = BorderStroke(1.dp, Gold.copy(alpha = 0.6f))
                        ) {
                            Text(
                                text = "Akku-Schutz für Anrufe freigeben",
                                fontWeight = FontWeight.SemiBold,
                                fontSize = 12.sp,
                                color = Gold
                            )
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
                                text = "Telefon-Assistent einsatzbereit",
                                fontSize = 12.sp,
                                fontWeight = FontWeight.SemiBold,
                                color = Emerald500
                            )
                        }
                    }

                    OutlinedButton(
                        onClick = {
                            awaitingCarrierConfirm = true
                            PhoneConciergeManager.openCarrierForwardingDialer(context, activate = true)
                            viewModel.refreshCarrierForwardingState()
                        },
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(10.dp),
                        border = BorderStroke(1.dp, (if (carrierForwardingConfirmed) Emerald500 else Gold).copy(alpha = 0.6f))
                    ) {
                        Icon(
                            imageVector = if (carrierForwardingConfirmed) Icons.Default.Check else Icons.Default.PhoneForwarded,
                            contentDescription = null,
                            tint = if (carrierForwardingConfirmed) Emerald500 else Gold,
                            modifier = Modifier.size(17.dp)
                        )
                        Spacer(modifier = Modifier.width(8.dp))
                        Text(
                            text = if (carrierForwardingConfirmed) "Rufumleitung verbunden" else "Rufumleitung verbinden",
                            fontWeight = FontWeight.SemiBold,
                            fontSize = 13.sp,
                            color = if (carrierForwardingConfirmed) Emerald500 else Gold
                        )
                    }

                    if (whitelistContacts.isNotEmpty()) {
                        HorizontalDivider(color = AppTheme.colors.cardBorder)
                        val activeCount = whitelistContacts.count { it.whitelisted }
                        Text(
                            text = "Freigegebene Kontakte ($activeCount)",
                            fontSize = 12.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = AppTheme.colors.textSecondary
                        )
                        if (whitelistContacts.size > 6) {
                            OutlinedTextField(
                                value = contactSearchQuery,
                                onValueChange = { contactSearchQuery = it },
                                placeholder = { Text("Kontakt suchen...", fontSize = 12.sp) },
                                singleLine = true,
                                modifier = Modifier.fillMaxWidth(),
                                colors = OutlinedTextFieldDefaults.colors(
                                    focusedBorderColor = Gold,
                                    unfocusedBorderColor = AppTheme.colors.cardBorder,
                                    focusedTextColor = AppTheme.colors.textPrimary,
                                    unfocusedTextColor = AppTheme.colors.textPrimary
                                )
                            )
                        }
                        val shownContacts = remember(whitelistContacts, contactSearchQuery) {
                            val q = contactSearchQuery.trim()
                            if (q.isEmpty()) whitelistContacts else whitelistContacts.filter {
                                it.fullName.contains(q, ignoreCase = true) || it.nickname.contains(q, ignoreCase = true)
                            }
                        }
                        Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                            shownContacts.forEach { contact ->
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
                                        onCheckedChange = {
                                            viewModel.toggleContactWhitelist(contact.id, it)
                                            if (it && phoneConciergeEnabled) ensurePhoneAssistantAccess()
                                        },
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
            } // end if (isAdmin)

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
