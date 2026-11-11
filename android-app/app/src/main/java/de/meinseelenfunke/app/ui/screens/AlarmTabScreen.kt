package de.meinseelenfunke.app.ui.screens

import android.widget.Toast
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.slideInVertically
import androidx.compose.animation.slideOutVertically
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.foundation.lazy.grid.items
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.Alarm
import androidx.compose.material.icons.filled.Bolt
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.Delete
import androidx.compose.material.icons.filled.HourglassEmpty
import androidx.compose.material.icons.filled.Mic
import androidx.compose.material.icons.filled.MoreVert
import androidx.compose.material.icons.filled.MusicNote
import androidx.compose.material.icons.filled.Pause
import androidx.compose.material.icons.filled.PlayArrow
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material.icons.filled.Replay
import androidx.compose.material.icons.filled.Search
import androidx.compose.material.icons.filled.Stop
import androidx.compose.material.icons.filled.Sync
import androidx.compose.material.icons.filled.Timer
import androidx.compose.material.icons.filled.Videocam
import androidx.compose.material.icons.filled.Visibility
import androidx.compose.material.icons.filled.VisibilityOff
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Checkbox
import androidx.compose.material3.CheckboxDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Switch
import androidx.compose.material3.SwitchDefaults
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableLongStateOf
import androidx.compose.runtime.mutableStateMapOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import de.meinseelenfunke.app.ui.theme.AppTheme
import de.meinseelenfunke.app.ui.theme.Emerald500
import de.meinseelenfunke.app.ui.theme.Gold
import de.meinseelenfunke.app.ui.theme.Rose500
import de.meinseelenfunke.app.ui.theme.SpaceBlack
import de.meinseelenfunke.app.util.FunkenwerkAlarmManager
import de.meinseelenfunke.app.util.MusicPlayerManager
import de.meinseelenfunke.app.util.MusicTrackDto
import de.meinseelenfunke.app.util.SmartAlarmItem
import de.meinseelenfunke.app.util.SmartPlugDevice
import de.meinseelenfunke.app.util.TimerPresetItem
import kotlinx.coroutines.delay
import java.util.Locale
import java.util.UUID

@Composable
fun AlarmTabContent(
    viewModel: OrganizerViewModel
) {
    val context = LocalContext.current
    LaunchedEffect(Unit) {
        FunkenwerkAlarmManager.ensureInitialized(context)
        MusicPlayerManager.fetchPlaylists(context)
    }

    val alarms by FunkenwerkAlarmManager.alarmsFlow.collectAsState()
    val timers by FunkenwerkAlarmManager.timersFlow.collectAsState()
    val timerRemainingMap by FunkenwerkAlarmManager.timerRemainingFlow.collectAsState()
    val timerRunningMap by FunkenwerkAlarmManager.timerRunningFlow.collectAsState()
    val selectedSubTab by FunkenwerkAlarmManager.activeInnerSubTabFlow.collectAsState() // 0 = Wecker, 1 = Timer, 2 = Stoppuhr
    val smartPlugs by FunkenwerkAlarmManager.smartPlugsFlow.collectAsState()
    val lastSync by FunkenwerkAlarmManager.lastSyncFlow.collectAsState()
    val isAlarmRinging by FunkenwerkAlarmManager.isAlarmRingingFlow.collectAsState()
    val ringingAlarmInfo by FunkenwerkAlarmManager.activeRingingAlarmInfoFlow.collectAsState()

    var isSyncing by remember { mutableStateOf(false) }
    var showSmartHomeSettingsModal by remember { mutableStateOf(false) }
    var editingAlarm by remember { mutableStateOf<SmartAlarmItem?>(null) }
    var showAddTimerModal by remember { mutableStateOf(false) }
    var alarmRingInBannerText by remember { mutableStateOf<String?>(null) }
    var alarmRingInBannerTrigger by remember { mutableIntStateOf(0) }
    var activeAlarmToast by remember { mutableStateOf<Toast?>(null) }

    LaunchedEffect(alarmRingInBannerTrigger) {
        if (alarmRingInBannerTrigger > 0 && !alarmRingInBannerText.isNullOrBlank()) {
            delay(3200L)
            alarmRingInBannerText = null
        }
    }

    // Persistent Stopwatch state (runs accurately across minimize & tab switches)
    val stopwatchRunning by FunkenwerkAlarmManager.stopwatchRunningFlow.collectAsState()
    val stopwatchElapsedMs by FunkenwerkAlarmManager.stopwatchElapsedMsFlow.collectAsState()
    val stopwatchLaps by FunkenwerkAlarmManager.stopwatchLapsFlow.collectAsState()

    val isDark = AppTheme.colors.isDark
    val subTabTitle = when (selectedSubTab) {
        0 -> "Wecker"
        1 -> "Timer"
        else -> "Stoppuhr"
    }

    Column(
        modifier = Modifier.fillMaxSize(),
        verticalArrangement = Arrangement.SpaceBetween
    ) {
        // 1. TOP HEADER BAR (Title left + Routine-Sync & Smart-Home Settings right)
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(bottom = 10.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Text(
                text = subTabTitle,
                fontSize = 22.sp,
                fontWeight = FontWeight.Medium,
                color = AppTheme.colors.textPrimary
            )

            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                // Package Sync Button to /admin/routine
                Box(
                    modifier = Modifier
                        .clip(RoundedCornerShape(12.dp))
                        .background(AppTheme.colors.cardBackground)
                        .border(1.dp, Gold.copy(alpha = 0.5f), RoundedCornerShape(12.dp))
                        .clickable(enabled = !isSyncing) {
                            isSyncing = true
                            viewModel.syncAlarmPackageToPortal { ok, msg ->
                                isSyncing = false
                                Toast.makeText(context, msg, Toast.LENGTH_SHORT).show()
                            }
                        }
                        .padding(horizontal = 10.dp, vertical = 6.dp),
                    contentAlignment = Alignment.Center
                ) {
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(5.dp)
                    ) {
                        if (isSyncing) {
                            CircularProgressIndicator(
                                modifier = Modifier.size(13.dp),
                                strokeWidth = 2.dp,
                                color = Gold
                            )
                        } else {
                            Icon(
                                imageVector = Icons.Default.Sync,
                                contentDescription = "Routine-Sync",
                                tint = Gold,
                                modifier = Modifier.size(14.dp)
                            )
                        }
                        Text(
                            text = if (lastSync != null) "Routine-Sync ✓" else "Routine-Sync",
                            fontSize = 11.sp,
                            fontWeight = FontWeight.Bold,
                            color = AppTheme.colors.textPrimary
                        )
                    }
                }

                // More / Smart-Home & KI Settings Icon (⋮)
                IconButton(
                    onClick = { showSmartHomeSettingsModal = true },
                    modifier = Modifier.size(34.dp)
                ) {
                    Icon(
                        imageVector = Icons.Default.MoreVert,
                        contentDescription = "Smart-Home & KI Einstellungen",
                        tint = AppTheme.colors.textPrimary
                    )
                }
            }
        }

        // 1b. ACTIVE RINGING ALARM BANNER (Always visible when alarm / timer / briefing is playing)
        if (isAlarmRinging) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(bottom = 10.dp)
                    .clip(RoundedCornerShape(16.dp))
                    .background(Rose500.copy(alpha = 0.18f))
                    .border(1.5.dp, Rose500, RoundedCornerShape(16.dp))
                    .padding(horizontal = 14.dp, vertical = 10.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        text = "🔔 ${ringingAlarmInfo?.second ?: "Wecker / Alarm aktiv"}",
                        fontSize = 14.sp,
                        fontWeight = FontWeight.Bold,
                        color = AppTheme.colors.textPrimary
                    )
                    Text(
                        text = if (ringingAlarmInfo != null) "${ringingAlarmInfo!!.third} Uhr" else "Ton läuft",
                        fontSize = 11.5.sp,
                        color = Rose500,
                        fontWeight = FontWeight.SemiBold
                    )
                }
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    if (ringingAlarmInfo != null) {
                        Button(
                            onClick = {
                                val (aId, aLabel, aTime) = ringingAlarmInfo!!
                                FunkenwerkAlarmManager.stopAlarmSound(context)
                                FunkenwerkAlarmManager.scheduleSnoozeAlarm(context, aId, aLabel, aTime, 5)
                                Toast.makeText(context, "💤 5 Min. Schlummern aktiviert", Toast.LENGTH_SHORT).show()
                            },
                            colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = SpaceBlack),
                            contentPadding = PaddingValues(horizontal = 10.dp, vertical = 6.dp)
                        ) {
                            Text("5 Min.", fontSize = 11.5.sp, fontWeight = FontWeight.Bold)
                        }
                    }
                    Button(
                        onClick = {
                            FunkenwerkAlarmManager.stopAlarmSound(context)
                            Toast.makeText(context, "Wecker ausgeschaltet", Toast.LENGTH_SHORT).show()
                        },
                        colors = ButtonDefaults.buttonColors(containerColor = Rose500, contentColor = Color.White),
                        contentPadding = PaddingValues(horizontal = 12.dp, vertical = 6.dp)
                    ) {
                        Text("🛑 Ausschalten", fontSize = 12.sp, fontWeight = FontWeight.Bold)
                    }
                }
            }
        }

        // 2. MAIN CONTENT AREA (Sub-Tab 0 = Wecker, 1 = Timer, 2 = Stoppuhr)
        Box(
            modifier = Modifier
                .weight(1f)
                .fillMaxWidth()
        ) {
            when (selectedSubTab) {
                0 -> WeckerSubTabView(
                    alarms = alarms,
                    smartPlugs = smartPlugs,
                    isDark = isDark,
                    ringInBannerText = alarmRingInBannerText,
                    onToggleAlarm = { alarm ->
                        val updated = FunkenwerkAlarmManager.toggleAlarm(context, alarm.id)
                        if (updated != null) {
                            if (updated.isEnabled) {
                                val ringInMsg = FunkenwerkAlarmManager.formatTimeUntilAlarm(updated)
                                alarmRingInBannerText = ringInMsg
                                alarmRingInBannerTrigger++
                                activeAlarmToast?.cancel()
                                val toast = Toast.makeText(context, "⏰ $ringInMsg", Toast.LENGTH_SHORT)
                                activeAlarmToast = toast
                                toast.show()
                            } else {
                                activeAlarmToast?.cancel()
                                alarmRingInBannerText = null
                            }
                            if (updated.syncWithRoutine) {
                                viewModel.syncAlarmPackageToPortal()
                            }
                        }
                    },
                    onEditAlarm = { alarm -> editingAlarm = alarm },
                    onAddAlarm = {
                        editingAlarm = SmartAlarmItem(
                            id = "alarm_${UUID.randomUUID().toString().take(8)}",
                            hour = 7,
                            minute = 30,
                            label = "Morgen-Wecker",
                            scheduleText = "Jeden Tag",
                            daysOfWeek = listOf(1, 2, 3, 4, 5, 6, 7),
                            isEnabled = true,
                            smartPlugIps = smartPlugs.take(1).map { it.ip },
                            triggerPlugOnRing = true,
                            aiBriefingEnabled = true,
                            syncWithRoutine = true
                        )
                    },
                    onSwitchPlug = { ip, turnOn ->
                        FunkenwerkAlarmManager.triggerSmartPlugsAsync(context, listOf(ip), turnOn = turnOn) { _, msg ->
                            Toast.makeText(context, msg, Toast.LENGTH_SHORT).show()
                        }
                    }
                )

                1 -> TimerSubTabView(
                    timers = timers,
                    remainingMap = timerRemainingMap,
                    runningMap = timerRunningMap,
                    isDark = isDark,
                    onTogglePlayPause = { preset ->
                        FunkenwerkAlarmManager.toggleTimerPlayPause(context, preset.id)
                    },
                    onResetTimer = { preset ->
                        FunkenwerkAlarmManager.resetTimer(context, preset.id)
                    },
                    onDeleteTimer = { preset ->
                        FunkenwerkAlarmManager.deleteTimerPreset(context, preset.id)
                    },
                    onAddTimerClick = { showAddTimerModal = true }
                )

                2 -> StoppuhrSubTabView(
                    elapsedMs = stopwatchElapsedMs,
                    isRunning = stopwatchRunning,
                    laps = stopwatchLaps,
                    isDark = isDark,
                    onToggleStartStop = { FunkenwerkAlarmManager.toggleStopwatch() },
                    onLap = { FunkenwerkAlarmManager.lapStopwatch() },
                    onReset = { FunkenwerkAlarmManager.resetStopwatch() }
                )
            }
        }

        // 3. BOTTOM SUB-TAB NAVIGATION BAR (Wecker | Timer | Stoppuhr - Native AppTheme Dark/Light Mode)
        Spacer(modifier = Modifier.height(8.dp))
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .clip(RoundedCornerShape(20.dp))
                .background(AppTheme.colors.cardBackground)
                .border(
                    width = 1.dp,
                    color = AppTheme.colors.cardBorder,
                    shape = RoundedCornerShape(20.dp)
                )
                .padding(vertical = 8.dp, horizontal = 12.dp),
            horizontalArrangement = Arrangement.SpaceEvenly,
            verticalAlignment = Alignment.CenterVertically
        ) {
            AlarmBottomNavItem(
                icon = Icons.Default.Alarm,
                label = "Wecker",
                selected = selectedSubTab == 0,
                isDark = isDark,
                onClick = { FunkenwerkAlarmManager.setActiveInnerSubTab(0) }
            )
            AlarmBottomNavItem(
                icon = Icons.Default.HourglassEmpty,
                label = "Timer",
                selected = selectedSubTab == 1,
                isDark = isDark,
                onClick = { FunkenwerkAlarmManager.setActiveInnerSubTab(1) }
            )
            AlarmBottomNavItem(
                icon = Icons.Default.Timer,
                label = "Stoppuhr",
                selected = selectedSubTab == 2,
                isDark = isDark,
                onClick = { FunkenwerkAlarmManager.setActiveInnerSubTab(2) }
            )
        }
    }

    // =========================================================================
    // MODAL 1: CREATE / EDIT ALARM (WITH SMART-HOME PLUGS & AI VOICE BRIEFING)
    // =========================================================================
    editingAlarm?.let { alarmToEdit ->
        AlarmEditDialog(
            initialAlarm = alarmToEdit,
            availablePlugs = smartPlugs,
            isExisting = alarms.any { it.id == alarmToEdit.id },
            onDismiss = { editingAlarm = null },
            onSave = { savedAlarm ->
                FunkenwerkAlarmManager.upsertAlarm(context, savedAlarm)
                editingAlarm = null
                if (savedAlarm.isEnabled) {
                    val ringInMsg = FunkenwerkAlarmManager.formatTimeUntilAlarm(savedAlarm)
                    alarmRingInBannerText = ringInMsg
                    alarmRingInBannerTrigger++
                    activeAlarmToast?.cancel()
                    val toast = Toast.makeText(context, "⏰ $ringInMsg", Toast.LENGTH_SHORT)
                    activeAlarmToast = toast
                    toast.show()
                }
                if (savedAlarm.syncWithRoutine) {
                    viewModel.syncAlarmPackageToPortal()
                }
            },
            onDelete = { idToDelete ->
                FunkenwerkAlarmManager.deleteAlarm(context, idToDelete)
                editingAlarm = null
                viewModel.syncAlarmPackageToPortal()
            },
            onTestPlugs = { ips ->
                FunkenwerkAlarmManager.triggerSmartPlugsAsync(context, ips, turnOn = true) { _, summary ->
                    Toast.makeText(context, summary, Toast.LENGTH_LONG).show()
                }
            },
            onTestAiBriefing = { label, hasPlugs, previewTime ->
                FunkenwerkAlarmManager.speakMorningBriefing(context, label, hasPlugs, previewTime)
                Toast.makeText(context, "🎙️ KI-Morgen-Briefing ($previewTime Uhr) wird gesprochen...", Toast.LENGTH_SHORT).show()
            }
        )
    }

    // =========================================================================
    // MODAL 2: ADD CUSTOM TIMER PRESET
    // =========================================================================
    if (showAddTimerModal) {
        AddTimerPresetDialog(
            onDismiss = { showAddTimerModal = false },
            onAdd = { label, totalSeconds ->
                FunkenwerkAlarmManager.addTimerPreset(context, label, totalSeconds)
                showAddTimerModal = false
                viewModel.syncAlarmPackageToPortal()
            }
        )
    }

    // =========================================================================
    // MODAL 3: SMART-HOME (TAPO KLAP LOGIN & PLUGS) & KI-BRIEFING SETTINGS
    // =========================================================================
    if (showSmartHomeSettingsModal) {
        SmartHomeAndAiSettingsDialog(
            smartPlugs = smartPlugs,
            onDismiss = { showSmartHomeSettingsModal = false },
            onAddPlug = { name, ip ->
                FunkenwerkAlarmManager.addSmartPlug(context, name, ip)
            },
            onTestPlug = { ip, turnOn ->
                FunkenwerkAlarmManager.triggerSmartPlugsAsync(context, listOf(ip), turnOn = turnOn) { _, msg ->
                    Toast.makeText(context, msg, Toast.LENGTH_LONG).show()
                }
            },
            onTestAiBriefing = {
                FunkenwerkAlarmManager.speakMorningBriefing(context, "Seelenfunke Fokus-Start", plugsSwitched = true)
            },
            onStopAudio = {
                FunkenwerkAlarmManager.stopAlarmSound(context)
            },
            onLogWakeUpNow = {
                val nowCal = java.util.Calendar.getInstance()
                val timeNow = String.format(
                    Locale.GERMANY,
                    "%02d:%02d",
                    nowCal.get(java.util.Calendar.HOUR_OF_DAY),
                    nowCal.get(java.util.Calendar.MINUTE)
                )
                val firstActive = alarms.firstOrNull { it.isEnabled } ?: alarms.firstOrNull()
                val sched = firstActive?.timeFormatted ?: timeNow
                FunkenwerkAlarmManager.recordWakeUpLog(
                    context = context,
                    scheduledTime = sched,
                    actualWakeTime = timeNow,
                    snoozeMinutes = 0,
                    plugsTriggered = false,
                    aiBriefingSpoken = false
                )
                viewModel.syncAlarmPackageToPortal { _, msg ->
                    Toast.makeText(context, "🌅 Aufstehzeit ($timeNow Uhr) geloggt · $msg", Toast.LENGTH_LONG).show()
                }
            }
        )
    }
}

@Composable
private fun alarmOutlinedTextFieldColors() = OutlinedTextFieldDefaults.colors(
    focusedBorderColor = Gold,
    unfocusedBorderColor = AppTheme.colors.cardBorder,
    focusedTextColor = AppTheme.colors.textPrimary,
    unfocusedTextColor = AppTheme.colors.textPrimary,
    focusedLabelColor = Gold,
    unfocusedLabelColor = AppTheme.colors.textMuted,
    cursorColor = Gold
)

@Composable
private fun AlarmBottomNavItem(
    icon: ImageVector,
    label: String,
    selected: Boolean,
    isDark: Boolean,
    onClick: () -> Unit
) {
    val pillBg = if (selected) {
        Gold.copy(alpha = if (isDark) 0.18f else 0.15f)
    } else {
        Color.Transparent
    }
    val contentTint = if (selected) {
        Gold
    } else {
        AppTheme.colors.textMuted
    }

    Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        modifier = Modifier
            .clip(RoundedCornerShape(14.dp))
            .clickable(onClick = onClick)
            .padding(horizontal = 12.dp, vertical = 2.dp)
    ) {
        Box(
            modifier = Modifier
                .width(56.dp)
                .height(30.dp)
                .clip(RoundedCornerShape(15.dp))
                .background(pillBg),
            contentAlignment = Alignment.Center
        ) {
            Icon(
                imageVector = icon,
                contentDescription = label,
                tint = contentTint,
                modifier = Modifier.size(19.dp)
            )
        }
        Spacer(modifier = Modifier.height(3.dp))
        Text(
            text = label,
            fontSize = 11.sp,
            fontWeight = if (selected) FontWeight.Bold else FontWeight.Medium,
            color = contentTint
        )
    }
}

// =============================================================================
// SUB-TAB 0: WECKER VIEW (100% AppTheme Dark/Light Mode Consistent Kacheln)
// =============================================================================
@Composable
private fun WeckerSubTabView(
    alarms: List<SmartAlarmItem>,
    smartPlugs: List<SmartPlugDevice>,
    isDark: Boolean,
    ringInBannerText: String? = null,
    onToggleAlarm: (SmartAlarmItem) -> Unit,
    onEditAlarm: (SmartAlarmItem) -> Unit,
    onAddAlarm: () -> Unit,
    onSwitchPlug: (String, Boolean) -> Unit
) {
    val plugNamesByIp = remember(smartPlugs) { smartPlugs.associate { it.ip to it.name } }

    Box(modifier = Modifier.fillMaxSize()) {
        LazyColumn(
            modifier = Modifier.fillMaxSize(),
            verticalArrangement = Arrangement.spacedBy(12.dp),
            contentPadding = PaddingValues(bottom = 92.dp)
        ) {
            items(alarms, key = { it.id }) { alarm ->
                val isEnabled = alarm.isEnabled

                val scheduleTextColor = if (isEnabled) Gold else AppTheme.colors.textMuted
                val timeTextColor = if (isEnabled) AppTheme.colors.textPrimary else AppTheme.colors.textMuted
                val cardBorderColor = if (isEnabled) Gold else AppTheme.colors.cardBorder
                val cardBorderWidth = if (isEnabled) 1.5.dp else 1.dp

                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .clip(RoundedCornerShape(24.dp))
                        .background(AppTheme.colors.cardBackground)
                        .background(
                            if (isEnabled) Gold.copy(alpha = if (isDark) 0.10f else 0.07f) else Color.Transparent
                        )
                        .border(cardBorderWidth, cardBorderColor, RoundedCornerShape(24.dp))
                        .clickable { onEditAlarm(alarm) }
                        .padding(horizontal = 22.dp, vertical = 18.dp)
                ) {
                    Column(modifier = Modifier.fillMaxWidth()) {
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Text(
                                text = "${alarm.label} · ${alarm.scheduleText}",
                                fontSize = 13.5.sp,
                                fontWeight = if (isEnabled) FontWeight.Bold else FontWeight.Medium,
                                color = scheduleTextColor,
                                maxLines = 1,
                                overflow = TextOverflow.Ellipsis,
                                modifier = Modifier.weight(1f)
                            )

                            val badges = buildList {
                                if (alarm.triggerPlugOnRing && alarm.smartPlugIps.isNotEmpty()) add("⚡ ${alarm.smartPlugIps.size}")
                                if (alarm.aiBriefingEnabled) add("🎙️ KI")
                                if (alarm.syncWithRoutine) add("🔄 Routine")
                            }
                            if (badges.isNotEmpty()) {
                                Spacer(modifier = Modifier.width(8.dp))
                                Text(
                                    text = badges.joinToString(" · "),
                                    fontSize = 11.sp,
                                    fontWeight = FontWeight.SemiBold,
                                    color = if (isEnabled) Gold.copy(alpha = 0.9f) else AppTheme.colors.textMuted
                                )
                            }
                        }

                        Spacer(modifier = Modifier.height(8.dp))

                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Text(
                                text = alarm.timeFormatted,
                                fontSize = 46.sp,
                                fontWeight = if (isEnabled) FontWeight.SemiBold else FontWeight.Light,
                                color = timeTextColor,
                                letterSpacing = 0.5.sp
                            )

                            ScreenshotStyleAlarmSwitch(
                                checked = isEnabled,
                                isDark = isDark,
                                onCheckedChange = { onToggleAlarm(alarm) }
                            )
                        }

                        // Compact info row showing custom Wecksound and linked Tapo plugs
                        val hasCustomSound = !alarm.alarmSoundTrackId.isNullOrBlank() && alarm.alarmSoundTrackId != "standard"
                        val plugNames = alarm.smartPlugIps.map { plugNamesByIp[it] ?: it }
                        if (hasCustomSound || (alarm.triggerPlugOnRing && plugNames.isNotEmpty())) {
                            Spacer(modifier = Modifier.height(6.dp))
                            val detailParts = buildList {
                                if (hasCustomSound) add("🎵 ${alarm.effectiveSoundDisplay}")
                                if (alarm.triggerPlugOnRing && plugNames.isNotEmpty()) add("⚡ ${plugNames.joinToString(", ")}")
                            }
                            Text(
                                text = detailParts.joinToString("  •  "),
                                fontSize = 11.sp,
                                color = if (isEnabled) AppTheme.colors.textSecondary else AppTheme.colors.textMuted,
                                maxLines = 1,
                                overflow = TextOverflow.Ellipsis
                            )
                        }
                    }
                }
            }

            // Direct Smart-Home Steckdosen Overview Card (matching PC Funkenwerk -> Smarthome)
            if (smartPlugs.isNotEmpty()) {
                item(key = "smarthome_plugs_overview") {
                    Column(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clip(RoundedCornerShape(20.dp))
                            .background(AppTheme.colors.cardBackground)
                            .border(1.dp, AppTheme.colors.cardBorder, RoundedCornerShape(20.dp))
                            .padding(horizontal = 16.dp, vertical = 14.dp),
                        verticalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Row(verticalAlignment = Alignment.CenterVertically) {
                                Icon(
                                    imageVector = Icons.Default.Bolt,
                                    contentDescription = null,
                                    tint = Gold,
                                    modifier = Modifier.size(16.dp)
                                )
                                Spacer(modifier = Modifier.width(6.dp))
                                Text(
                                    text = "Smart-Home Steckdosen (Heimnetz)",
                                    fontSize = 13.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = AppTheme.colors.textPrimary
                                )
                            }
                            Text(
                                text = "${smartPlugs.size} Tapo P100",
                                fontSize = 11.sp,
                                fontWeight = FontWeight.SemiBold,
                                color = Gold
                            )
                        }

                        smartPlugs.forEach { plug ->
                            Row(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .clip(RoundedCornerShape(12.dp))
                                    .background(AppTheme.colors.inputBackground)
                                    .border(1.dp, AppTheme.colors.cardBorder, RoundedCornerShape(12.dp))
                                    .padding(horizontal = 12.dp, vertical = 8.dp),
                                horizontalArrangement = Arrangement.SpaceBetween,
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Column(modifier = Modifier.weight(1f)) {
                                    Text(
                                        text = plug.name,
                                        fontSize = 13.sp,
                                        fontWeight = FontWeight.Bold,
                                        color = AppTheme.colors.textPrimary
                                    )
                                    Text(
                                        text = "${plug.ip} · ${plug.room}",
                                        fontSize = 11.sp,
                                        color = AppTheme.colors.textMuted
                                    )
                                }
                                Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                                    Box(
                                        modifier = Modifier
                                            .clip(RoundedCornerShape(8.dp))
                                            .background(Emerald500.copy(alpha = 0.16f))
                                            .border(1.dp, Emerald500.copy(alpha = 0.5f), RoundedCornerShape(8.dp))
                                            .clickable { onSwitchPlug(plug.ip, true) }
                                            .padding(horizontal = 10.dp, vertical = 5.dp),
                                        contentAlignment = Alignment.Center
                                    ) {
                                        Text("EIN", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = Emerald500)
                                    }
                                    Box(
                                        modifier = Modifier
                                            .clip(RoundedCornerShape(8.dp))
                                            .background(Rose500.copy(alpha = 0.16f))
                                            .border(1.dp, Rose500.copy(alpha = 0.5f), RoundedCornerShape(8.dp))
                                            .clickable { onSwitchPlug(plug.ip, false) }
                                            .padding(horizontal = 10.dp, vertical = 5.dp),
                                        contentAlignment = Alignment.Center
                                    ) {
                                        Text("AUS", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = Rose500)
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // Brief floating pill banner showing remaining hours/minutes when an alarm switch is turned ON
        AnimatedVisibility(
            visible = !ringInBannerText.isNullOrBlank(),
            enter = fadeIn() + slideInVertically(initialOffsetY = { it / 2 }),
            exit = fadeOut() + slideOutVertically(targetOffsetY = { it / 2 }),
            modifier = Modifier
                .align(Alignment.BottomCenter)
                .padding(bottom = 84.dp, start = 16.dp, end = 16.dp)
        ) {
            Row(
                modifier = Modifier
                    .clip(RoundedCornerShape(16.dp))
                    .background(if (isDark) Color(0xF215171E) else Color(0xF5FFFFFF))
                    .border(1.5.dp, Gold, RoundedCornerShape(16.dp))
                    .padding(horizontal = 16.dp, vertical = 10.dp),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                Icon(
                    imageVector = Icons.Default.Alarm,
                    contentDescription = null,
                    tint = Gold,
                    modifier = Modifier.size(18.dp)
                )
                Text(
                    text = ringInBannerText ?: "",
                    fontSize = 13.5.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = AppTheme.colors.textPrimary
                )
            }
        }

        // Floating rounded-square '+' button at bottom right (Gold + SpaceBlack)
        Box(
            modifier = Modifier
                .align(Alignment.BottomEnd)
                .padding(bottom = 12.dp, end = 4.dp)
                .size(64.dp)
                .clip(RoundedCornerShape(20.dp))
                .background(Gold)
                .clickable { onAddAlarm() },
            contentAlignment = Alignment.Center
        ) {
            Icon(
                imageVector = Icons.Default.Add,
                contentDescription = "Wecker hinzufügen",
                tint = SpaceBlack,
                modifier = Modifier.size(28.dp)
            )
        }
    }
}

@Composable
private fun ScreenshotStyleAlarmSwitch(
    checked: Boolean,
    isDark: Boolean,
    onCheckedChange: () -> Unit
) {
    val trackColor = if (checked) Gold else AppTheme.colors.inputBackground
    val borderColor = if (checked) Gold else AppTheme.colors.cardBorder
    val thumbColor = if (checked) SpaceBlack else AppTheme.colors.textMuted

    Box(
        modifier = Modifier
            .width(58.dp)
            .height(32.dp)
            .clip(RoundedCornerShape(16.dp))
            .background(trackColor)
            .border(
                width = 1.5.dp,
                color = borderColor,
                shape = RoundedCornerShape(16.dp)
            )
            .clickable { onCheckedChange() }
            .padding(horizontal = 5.dp),
        contentAlignment = if (checked) Alignment.CenterEnd else Alignment.CenterStart
    ) {
        Box(
            modifier = Modifier
                .size(if (checked) 22.dp else 16.dp)
                .clip(CircleShape)
                .background(thumbColor)
        )
    }
}

// =============================================================================
// SUB-TAB 1: TIMER VIEW (2-Column Grid with AppTheme Dark/Light Mode Kacheln)
// =============================================================================
@Composable
private fun TimerSubTabView(
    timers: List<TimerPresetItem>,
    remainingMap: Map<String, Int>,
    runningMap: Map<String, Boolean>,
    isDark: Boolean,
    onTogglePlayPause: (TimerPresetItem) -> Unit,
    onResetTimer: (TimerPresetItem) -> Unit,
    onDeleteTimer: (TimerPresetItem) -> Unit,
    onAddTimerClick: () -> Unit
) {
    val ringColor = if (isDark) AppTheme.colors.textSecondary.copy(alpha = 0.45f) else AppTheme.colors.cardBorder
    val activeRingColor = Gold

    Box(modifier = Modifier.fillMaxSize()) {
        LazyVerticalGrid(
            columns = GridCells.Fixed(2),
            modifier = Modifier.fillMaxSize(),
            horizontalArrangement = Arrangement.spacedBy(12.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
            contentPadding = PaddingValues(bottom = 92.dp)
        ) {
            items(timers, key = { it.id }) { preset ->
                val remSec = remainingMap[preset.id] ?: preset.durationSeconds
                val isRunning = runningMap[preset.id] == true
                val progress = if (preset.durationSeconds > 0) {
                    remSec.toFloat() / preset.durationSeconds.toFloat()
                } else 1f

                val mins = remSec / 60
                val secs = remSec % 60
                val formattedCountdown = String.format(Locale.GERMANY, "%02d:%02d", mins, secs)

                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .aspectRatio(0.86f)
                        .clip(RoundedCornerShape(24.dp))
                        .background(AppTheme.colors.cardBackground)
                        .background(
                            if (isRunning) Gold.copy(alpha = if (isDark) 0.10f else 0.07f) else Color.Transparent
                        )
                        .border(
                            width = if (isRunning) 1.5.dp else 1.dp,
                            color = if (isRunning) Gold else AppTheme.colors.cardBorder,
                            shape = RoundedCornerShape(24.dp)
                        )
                        .padding(14.dp)
                ) {
                    Column(
                        modifier = Modifier.fillMaxSize(),
                        horizontalAlignment = Alignment.CenterHorizontally,
                        verticalArrangement = Arrangement.SpaceBetween
                    ) {
                        // Top Row: Title left + Close/Reset right
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Text(
                                text = preset.label,
                                fontSize = 13.sp,
                                fontWeight = FontWeight.Bold,
                                color = if (isRunning) Gold else AppTheme.colors.textPrimary,
                                maxLines = 1,
                                overflow = TextOverflow.Ellipsis,
                                modifier = Modifier.weight(1f)
                            )

                            if (remSec != preset.durationSeconds) {
                                Icon(
                                    imageVector = Icons.Default.Replay,
                                    contentDescription = "Zurücksetzen",
                                    tint = AppTheme.colors.textMuted,
                                    modifier = Modifier
                                        .size(18.dp)
                                        .clickable { onResetTimer(preset) }
                                )
                                Spacer(modifier = Modifier.width(6.dp))
                            }

                            Icon(
                                imageVector = Icons.Default.Close,
                                contentDescription = "Löschen",
                                tint = AppTheme.colors.textMuted,
                                modifier = Modifier
                                    .size(18.dp)
                                    .clickable { onDeleteTimer(preset) }
                            )
                        }

                        // Center Circular Ring with Countdown & Play/Pause inside
                        Box(
                            modifier = Modifier
                                .fillMaxWidth(0.82f)
                                .aspectRatio(1f)
                                .clip(CircleShape)
                                .clickable { onTogglePlayPause(preset) },
                            contentAlignment = Alignment.Center
                        ) {
                            Canvas(modifier = Modifier.fillMaxSize().padding(5.dp)) {
                                drawCircle(
                                    color = if (isRunning) Gold.copy(alpha = 0.22f) else ringColor,
                                    style = Stroke(width = 4.5.dp.toPx())
                                )
                                if (isRunning || remSec != preset.durationSeconds) {
                                    drawArc(
                                        color = activeRingColor,
                                        startAngle = -90f,
                                        sweepAngle = 360f * progress,
                                        useCenter = false,
                                        style = Stroke(width = 5.dp.toPx(), cap = StrokeCap.Round)
                                    )
                                }
                            }

                            Column(
                                horizontalAlignment = Alignment.CenterHorizontally,
                                verticalArrangement = Arrangement.Center
                            ) {
                                Text(
                                    text = formattedCountdown,
                                    fontSize = 27.sp,
                                    fontWeight = FontWeight.Medium,
                                    color = AppTheme.colors.textPrimary
                                )
                                Spacer(modifier = Modifier.height(4.dp))
                                Icon(
                                    imageVector = if (isRunning) Icons.Default.Pause else Icons.Default.PlayArrow,
                                    contentDescription = if (isRunning) "Pause" else "Start",
                                    tint = Gold,
                                    modifier = Modifier.size(26.dp)
                                )
                            }
                        }

                        Spacer(modifier = Modifier.height(2.dp))
                    }
                }
            }
        }

        // Floating rounded-square '+' button at bottom right
        Box(
            modifier = Modifier
                .align(Alignment.BottomEnd)
                .padding(bottom = 12.dp, end = 4.dp)
                .size(64.dp)
                .clip(RoundedCornerShape(20.dp))
                .background(Gold)
                .clickable { onAddTimerClick() },
            contentAlignment = Alignment.Center
        ) {
            Icon(
                imageVector = Icons.Default.Add,
                contentDescription = "Timer hinzufügen",
                tint = SpaceBlack,
                modifier = Modifier.size(28.dp)
            )
        }
    }
}

// =============================================================================
// SUB-TAB 2: STOPPUHR VIEW (AppTheme Dark/Light Mode Consistent)
// =============================================================================
@Composable
private fun StoppuhrSubTabView(
    elapsedMs: Long,
    isRunning: Boolean,
    laps: List<Long>,
    isDark: Boolean,
    onToggleStartStop: () -> Unit,
    onLap: () -> Unit,
    onReset: () -> Unit
) {
    val totalCentis = (elapsedMs / 10L) % 100L
    val totalSeconds = (elapsedMs / 1000L) % 60L
    val totalMinutes = (elapsedMs / 60_000L)
    val formattedStoppuhr = String.format(
        Locale.US,
        "%02d:%02d.%02d",
        totalMinutes,
        totalSeconds,
        totalCentis
    )

    Column(
        modifier = Modifier.fillMaxSize(),
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        Spacer(modifier = Modifier.height(if (laps.isEmpty()) 36.dp else 16.dp))

        // Big 00:00.00 Digital Readout
        Text(
            text = formattedStoppuhr,
            fontSize = 60.sp,
            fontWeight = FontWeight.Normal,
            fontFamily = FontFamily.Monospace,
            color = AppTheme.colors.textPrimary,
            letterSpacing = 1.sp
        )

        Spacer(modifier = Modifier.height(if (laps.isEmpty()) 76.dp else 20.dp))

        // Large Rounded Pill Button "Starten"
        Box(
            modifier = Modifier
                .fillMaxWidth(0.92f)
                .height(82.dp)
                .clip(RoundedCornerShape(41.dp))
                .background(if (isRunning) Rose500 else Gold)
                .clickable { onToggleStartStop() },
            contentAlignment = Alignment.Center
        ) {
            Text(
                text = when {
                    isRunning -> "Stopp"
                    elapsedMs > 0L -> "Weiter"
                    else -> "Starten"
                },
                fontSize = 19.sp,
                fontWeight = FontWeight.Bold,
                color = if (isRunning) Color.White else SpaceBlack
            )
        }

        if (elapsedMs > 0L) {
            Spacer(modifier = Modifier.height(14.dp))
            Row(
                modifier = Modifier.fillMaxWidth(0.92f),
                horizontalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                Box(
                    modifier = Modifier
                        .weight(1f)
                        .height(44.dp)
                        .clip(RoundedCornerShape(22.dp))
                        .background(AppTheme.colors.cardBackground)
                        .border(1.dp, AppTheme.colors.cardBorder, RoundedCornerShape(22.dp))
                        .clickable(enabled = isRunning) { onLap() },
                    contentAlignment = Alignment.Center
                ) {
                    Text(
                        text = "Runde",
                        fontSize = 14.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = if (isRunning) AppTheme.colors.textPrimary else AppTheme.colors.textMuted
                    )
                }

                Box(
                    modifier = Modifier
                        .weight(1f)
                        .height(44.dp)
                        .clip(RoundedCornerShape(22.dp))
                        .background(AppTheme.colors.cardBackground)
                        .border(1.dp, AppTheme.colors.cardBorder, RoundedCornerShape(22.dp))
                        .clickable { onReset() },
                    contentAlignment = Alignment.Center
                ) {
                    Text(
                        text = "Zurücksetzen",
                        fontSize = 14.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = AppTheme.colors.textPrimary
                    )
                }
            }
        }

        if (laps.isNotEmpty()) {
            Spacer(modifier = Modifier.height(16.dp))
            LazyColumn(
                modifier = Modifier
                    .fillMaxWidth(0.92f)
                    .weight(1f),
                verticalArrangement = Arrangement.spacedBy(6.dp)
            ) {
                itemsIndexed(laps) { idx, lapMs ->
                    val lapNum = laps.size - idx
                    val c = (lapMs / 10L) % 100L
                    val s = (lapMs / 1000L) % 60L
                    val m = (lapMs / 60_000L)
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clip(RoundedCornerShape(12.dp))
                            .background(AppTheme.colors.cardBackground)
                            .border(1.dp, AppTheme.colors.cardBorder, RoundedCornerShape(12.dp))
                            .padding(horizontal = 14.dp, vertical = 8.dp),
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Text(
                            text = "Runde #$lapNum",
                            fontSize = 12.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = AppTheme.colors.textMuted
                        )
                        Text(
                            text = String.format(Locale.US, "%02d:%02d.%02d", m, s, c),
                            fontSize = 13.sp,
                            fontFamily = FontFamily.Monospace,
                            fontWeight = FontWeight.Bold,
                            color = AppTheme.colors.textPrimary
                        )
                    }
                }
            }
        }
    }
}

// =============================================================================
// DIALOGS: ALARM EDITOR, TIMER CREATOR & SMART-HOME / KI SETTINGS
// =============================================================================
@Composable
private fun AlarmEditDialog(
    initialAlarm: SmartAlarmItem,
    availablePlugs: List<SmartPlugDevice>,
    isExisting: Boolean,
    onDismiss: () -> Unit,
    onSave: (SmartAlarmItem) -> Unit,
    onDelete: (String) -> Unit,
    onTestPlugs: (List<String>) -> Unit,
    onTestAiBriefing: (String, Boolean, String) -> Unit
) {
    val context = LocalContext.current
    var hourStr by remember { mutableStateOf(String.format(Locale.GERMANY, "%02d", initialAlarm.hour)) }
    var minuteStr by remember { mutableStateOf(String.format(Locale.GERMANY, "%02d", initialAlarm.minute)) }
    var label by remember { mutableStateOf(initialAlarm.label) }
    var selectedDays by remember { mutableStateOf(initialAlarm.daysOfWeek.toSet()) }
    var selectedPlugIps by remember { mutableStateOf(initialAlarm.smartPlugIps.toSet()) }
    var triggerPlugs by remember { mutableStateOf(initialAlarm.triggerPlugOnRing) }
    var aiBriefing by remember { mutableStateOf(initialAlarm.aiBriefingEnabled) }
    var syncRoutine by remember { mutableStateOf(initialAlarm.syncWithRoutine) }
    var selectedSoundTrackId by remember { mutableStateOf(initialAlarm.alarmSoundTrackId ?: "standard") }
    var selectedSoundTitle by remember { mutableStateOf(initialAlarm.alarmSoundTitle ?: "Standard-Weckton") }
    var showSoundPickerModal by remember { mutableStateOf(false) }
    var isPreviewingSound by remember { mutableStateOf(false) }

    val dayLabels = listOf(1 to "Mo", 2 to "Di", 3 to "Mi", 4 to "Do", 5 to "Fr", 6 to "Sa", 7 to "So")

    if (showSoundPickerModal) {
        AlarmSoundPickerDialog(
            currentSelectedId = selectedSoundTrackId,
            onDismiss = {
                FunkenwerkAlarmManager.stopAllActiveAudio()
                isPreviewingSound = false
                showSoundPickerModal = false
            },
            onSelect = { trackId, trackTitle ->
                FunkenwerkAlarmManager.stopAllActiveAudio()
                isPreviewingSound = false
                selectedSoundTrackId = trackId
                selectedSoundTitle = trackTitle
                if (trackId != "standard" && trackId != "random_music") {
                    MusicPlayerManager.cacheTrackForAlarmAsync(context, trackId)
                }
                showSoundPickerModal = false
            }
        )
    }

    AlertDialog(
        onDismissRequest = {
            FunkenwerkAlarmManager.stopAllActiveAudio()
            isPreviewingSound = false
            onDismiss()
        },
        containerColor = AppTheme.colors.cardBackground,
        shape = RoundedCornerShape(20.dp),
        title = {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = if (isExisting) "Wecker konfigurieren" else "Neuer Wecker",
                    fontSize = 18.sp,
                    fontWeight = FontWeight.Bold,
                    color = AppTheme.colors.textPrimary
                )
                if (isExisting) {
                    IconButton(onClick = {
                        FunkenwerkAlarmManager.stopAllActiveAudio()
                        onDelete(initialAlarm.id)
                    }) {
                        Icon(
                            imageVector = Icons.Default.Delete,
                            contentDescription = "Wecker löschen",
                            tint = Rose500
                        )
                    }
                }
            }
        },
        text = {
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .verticalScroll(rememberScrollState()),
                verticalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                // Time Input (Hour : Minute)
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.Center,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    OutlinedTextField(
                        value = hourStr,
                        onValueChange = { v -> if (v.length <= 2 && v.all { it.isDigit() }) hourStr = v },
                        label = { Text("Std (0-23)", fontSize = 10.sp) },
                        singleLine = true,
                        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                        modifier = Modifier.width(105.dp),
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedBorderColor = Gold,
                            unfocusedBorderColor = AppTheme.colors.cardBorder,
                            focusedTextColor = AppTheme.colors.textPrimary,
                            unfocusedTextColor = AppTheme.colors.textPrimary
                        )
                    )
                    Text(
                        text = " : ",
                        fontSize = 28.sp,
                        fontWeight = FontWeight.Bold,
                        color = AppTheme.colors.textPrimary,
                        modifier = Modifier.padding(horizontal = 6.dp)
                    )
                    OutlinedTextField(
                        value = minuteStr,
                        onValueChange = { v -> if (v.length <= 2 && v.all { it.isDigit() }) minuteStr = v },
                        label = { Text("Min (0-59)", fontSize = 10.sp) },
                        singleLine = true,
                        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                        modifier = Modifier.width(105.dp),
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedBorderColor = Gold,
                            unfocusedBorderColor = AppTheme.colors.cardBorder,
                            focusedTextColor = AppTheme.colors.textPrimary,
                            unfocusedTextColor = AppTheme.colors.textPrimary
                        )
                    )
                }

                // Label
                OutlinedTextField(
                    value = label,
                    onValueChange = { label = it },
                    label = { Text("Bezeichnung") },
                    singleLine = true,
                    modifier = Modifier.fillMaxWidth(),
                    colors = OutlinedTextFieldDefaults.colors(
                        focusedBorderColor = Gold,
                        unfocusedBorderColor = AppTheme.colors.cardBorder,
                        focusedTextColor = AppTheme.colors.textPrimary,
                        unfocusedTextColor = AppTheme.colors.textPrimary
                    )
                )

                // Days of week selector
                Text(
                    text = "Wochentage (${FunkenwerkAlarmManager.formatDaysScheduleText(selectedDays.toList())})",
                    fontSize = 12.sp,
                    fontWeight = FontWeight.Bold,
                    color = AppTheme.colors.textSecondary
                )
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween
                ) {
                    dayLabels.forEach { (dayNum, shortName) ->
                        val isSel = selectedDays.contains(dayNum)
                        Box(
                            modifier = Modifier
                                .size(34.dp)
                                .clip(CircleShape)
                                .background(if (isSel) Gold else AppTheme.colors.inputBackground)
                                .border(1.dp, if (isSel) Gold else AppTheme.colors.cardBorder, CircleShape)
                                .clickable {
                                    selectedDays = if (isSel) selectedDays - dayNum else selectedDays + dayNum
                                },
                            contentAlignment = Alignment.Center
                        ) {
                            Text(
                                text = shortName,
                                fontSize = 11.sp,
                                fontWeight = FontWeight.Bold,
                                color = if (isSel) SpaceBlack else AppTheme.colors.textPrimary
                            )
                        }
                    }
                }

                // Wecksound Selection (Music Library / Standard)
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .clip(RoundedCornerShape(12.dp))
                        .background(AppTheme.colors.inputBackground)
                        .border(1.dp, AppTheme.colors.cardBorder, RoundedCornerShape(12.dp))
                        .padding(10.dp),
                    verticalArrangement = Arrangement.spacedBy(6.dp)
                ) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Icon(Icons.Default.MusicNote, contentDescription = null, tint = Gold, modifier = Modifier.size(16.dp))
                            Spacer(modifier = Modifier.width(6.dp))
                            Text(
                                text = "Wecksound",
                                fontSize = 13.sp,
                                fontWeight = FontWeight.Bold,
                                color = AppTheme.colors.textPrimary
                            )
                        }
                        TextButton(
                            onClick = {
                                if (isPreviewingSound) {
                                    FunkenwerkAlarmManager.stopAllActiveAudio()
                                    isPreviewingSound = false
                                } else {
                                    FunkenwerkAlarmManager.startLoopingAlarmPlayerOnly(context, selectedSoundTrackId)
                                    isPreviewingSound = true
                                }
                            },
                            contentPadding = PaddingValues(horizontal = 8.dp, vertical = 0.dp)
                        ) {
                            Text(
                                text = if (isPreviewingSound) "⏹ Stopp" else "▶ Probe hören",
                                fontSize = 11.sp,
                                fontWeight = FontWeight.Bold,
                                color = if (isPreviewingSound) Rose500 else Gold
                            )
                        }
                    }

                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clip(RoundedCornerShape(8.dp))
                            .background(AppTheme.colors.cardBackground)
                            .border(1.dp, Gold.copy(alpha = 0.4f), RoundedCornerShape(8.dp))
                            .clickable { showSoundPickerModal = true }
                            .padding(horizontal = 10.dp, vertical = 9.dp),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text(
                            text = "🎵 $selectedSoundTitle",
                            fontSize = 12.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = AppTheme.colors.textPrimary,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis,
                            modifier = Modifier.weight(1f)
                        )
                        Text(
                            text = "Auswählen ▾",
                            fontSize = 11.sp,
                            fontWeight = FontWeight.Bold,
                            color = Gold
                        )
                    }
                }

                // Smart-Home Wi-Fi Plugs Section
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .clip(RoundedCornerShape(12.dp))
                        .background(AppTheme.colors.inputBackground)
                        .border(1.dp, AppTheme.colors.cardBorder, RoundedCornerShape(12.dp))
                        .padding(10.dp),
                    verticalArrangement = Arrangement.spacedBy(6.dp)
                ) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Icon(Icons.Default.Bolt, contentDescription = null, tint = Gold, modifier = Modifier.size(16.dp))
                            Spacer(modifier = Modifier.width(6.dp))
                            Text(
                                text = "Smart-Home Steckdosen",
                                fontSize = 13.sp,
                                fontWeight = FontWeight.Bold,
                                color = AppTheme.colors.textPrimary
                            )
                        }
                        Switch(
                            checked = triggerPlugs,
                            onCheckedChange = { triggerPlugs = it },
                            colors = SwitchDefaults.colors(checkedThumbColor = SpaceBlack, checkedTrackColor = Gold)
                        )
                    }

                    if (triggerPlugs) {
                        availablePlugs.forEach { plug ->
                            val isChecked = selectedPlugIps.contains(plug.ip)
                            Row(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .clickable {
                                        selectedPlugIps = if (isChecked) selectedPlugIps - plug.ip else selectedPlugIps + plug.ip
                                    },
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Checkbox(
                                    checked = isChecked,
                                    onCheckedChange = {
                                        selectedPlugIps = if (it) selectedPlugIps + plug.ip else selectedPlugIps - plug.ip
                                    },
                                    colors = CheckboxDefaults.colors(checkedColor = Gold, checkmarkColor = SpaceBlack)
                                )
                                Text(
                                    text = "${plug.name} (${plug.ip})",
                                    fontSize = 12.sp,
                                    color = AppTheme.colors.textPrimary
                                )
                            }
                        }

                        if (selectedPlugIps.isNotEmpty()) {
                            TextButton(
                                onClick = { onTestPlugs(selectedPlugIps.toList()) },
                                contentPadding = PaddingValues(horizontal = 8.dp, vertical = 2.dp)
                            ) {
                                Text("⚡ Ausgewählte Steckdosen jetzt testen", fontSize = 11.sp, color = Gold, fontWeight = FontWeight.Bold)
                            }
                        }
                    }
                }

                // AI Morning Briefing Section
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .clip(RoundedCornerShape(12.dp))
                        .background(AppTheme.colors.inputBackground)
                        .border(1.dp, AppTheme.colors.cardBorder, RoundedCornerShape(12.dp))
                        .padding(horizontal = 10.dp, vertical = 8.dp),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Column(modifier = Modifier.weight(1f)) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Icon(Icons.Default.Mic, contentDescription = null, tint = Gold, modifier = Modifier.size(16.dp))
                            Spacer(modifier = Modifier.width(6.dp))
                            Text(
                                text = "KI-Morgen-Briefing",
                                fontSize = 13.sp,
                                fontWeight = FontWeight.Bold,
                                color = AppTheme.colors.textPrimary
                            )
                        }
                        TextButton(
                            onClick = {
                                val h = (hourStr.toIntOrNull() ?: initialAlarm.hour).coerceIn(0, 23)
                                val m = (minuteStr.toIntOrNull() ?: initialAlarm.minute).coerceIn(0, 59)
                                val previewTime = String.format(Locale.GERMANY, "%02d:%02d", h, m)
                                onTestAiBriefing(label, triggerPlugs && selectedPlugIps.isNotEmpty(), previewTime)
                            },
                            contentPadding = PaddingValues(0.dp)
                        ) {
                            Text("🎙️ Vorschau anhören", fontSize = 11.sp, color = Gold, fontWeight = FontWeight.Bold)
                        }
                    }
                    Switch(
                        checked = aiBriefing,
                        onCheckedChange = { aiBriefing = it },
                        colors = SwitchDefaults.colors(checkedThumbColor = SpaceBlack, checkedTrackColor = Gold)
                    )
                }

                // Routine Package Sync Switch
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .clip(RoundedCornerShape(12.dp))
                        .background(AppTheme.colors.inputBackground)
                        .border(1.dp, AppTheme.colors.cardBorder, RoundedCornerShape(12.dp))
                        .padding(horizontal = 10.dp, vertical = 8.dp),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.weight(1f)) {
                        Icon(Icons.Default.Sync, contentDescription = null, tint = Emerald500, modifier = Modifier.size(16.dp))
                        Spacer(modifier = Modifier.width(6.dp))
                        Text(
                            text = "Tagesroutine (/admin/routine) takten",
                            fontSize = 12.5.sp,
                            fontWeight = FontWeight.Bold,
                            color = AppTheme.colors.textPrimary
                        )
                    }
                    Switch(
                        checked = syncRoutine,
                        onCheckedChange = { syncRoutine = it },
                        colors = SwitchDefaults.colors(checkedThumbColor = SpaceBlack, checkedTrackColor = Emerald500)
                    )
                }
            }
        },
        confirmButton = {
            Button(
                onClick = {
                    FunkenwerkAlarmManager.stopAllActiveAudio()
                    isPreviewingSound = false
                    val h = (hourStr.toIntOrNull() ?: initialAlarm.hour).coerceIn(0, 23)
                    val m = (minuteStr.toIntOrNull() ?: initialAlarm.minute).coerceIn(0, 59)
                    val daysList = selectedDays.toList().sorted()
                    val schedText = FunkenwerkAlarmManager.formatDaysScheduleText(daysList)
                    onSave(
                        initialAlarm.copy(
                            hour = h,
                            minute = m,
                            label = label.ifBlank { "Wecker" },
                            daysOfWeek = daysList,
                            scheduleText = schedText,
                            isEnabled = true,
                            smartPlugIps = selectedPlugIps.toList(),
                            triggerPlugOnRing = triggerPlugs,
                            aiBriefingEnabled = aiBriefing,
                            syncWithRoutine = syncRoutine,
                            alarmSoundTrackId = selectedSoundTrackId,
                            alarmSoundTitle = selectedSoundTitle
                        )
                    )
                },
                colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = SpaceBlack)
            ) {
                Text("Speichern", fontWeight = FontWeight.Bold)
            }
        },
        dismissButton = {
            TextButton(onClick = {
                FunkenwerkAlarmManager.stopAllActiveAudio()
                isPreviewingSound = false
                onDismiss()
            }) {
                Text("Abbrechen", color = AppTheme.colors.textMuted)
            }
        }
    )
}

@Composable
private fun AlarmSoundPickerDialog(
    currentSelectedId: String,
    onDismiss: () -> Unit,
    onSelect: (trackId: String, trackTitle: String) -> Unit
) {
    val context = LocalContext.current
    val tracks = remember { MusicPlayerManager.getAvailableTracksForAlarm(context) }
    var searchQuery by remember { mutableStateOf("") }
    var previewingId by remember { mutableStateOf<String?>(null) }

    val filteredTracks = remember(tracks, searchQuery) {
        if (searchQuery.isBlank()) tracks
        else tracks.filter {
            it.title.contains(searchQuery, ignoreCase = true) ||
                it.artist.contains(searchQuery, ignoreCase = true)
        }
    }

    AlertDialog(
        onDismissRequest = onDismiss,
        containerColor = AppTheme.colors.cardBackground,
        shape = RoundedCornerShape(20.dp),
        title = {
            Text(
                text = "Wecksound wählen",
                fontSize = 18.sp,
                fontWeight = FontWeight.Bold,
                color = AppTheme.colors.textPrimary
            )
        },
        text = {
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .height(420.dp),
                verticalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                OutlinedTextField(
                    value = searchQuery,
                    onValueChange = { searchQuery = it },
                    label = { Text("Song in Musikbibliothek suchen...", fontSize = 11.sp) },
                    singleLine = true,
                    modifier = Modifier.fillMaxWidth(),
                    colors = alarmOutlinedTextFieldColors()
                )

                LazyColumn(
                    modifier = Modifier.fillMaxSize(),
                    verticalArrangement = Arrangement.spacedBy(6.dp)
                ) {
                    item {
                        SoundOptionRow(
                            title = "Standard-Weckton",
                            subtitle = "Klassischer Seelenfunke Weckton",
                            isSelected = currentSelectedId == "standard",
                            isPreviewing = previewingId == "standard",
                            onSelect = { onSelect("standard", "Standard-Weckton") },
                            onTogglePreview = {
                                if (previewingId == "standard") {
                                    FunkenwerkAlarmManager.stopAllActiveAudio()
                                    previewingId = null
                                } else {
                                    FunkenwerkAlarmManager.startLoopingAlarmPlayerOnly(context, "standard")
                                    previewingId = "standard"
                                }
                            }
                        )
                    }
                    item {
                        SoundOptionRow(
                            title = "Zufallslied aus Musikbibliothek",
                            subtitle = "${tracks.size} Songs verfügbar",
                            isSelected = currentSelectedId == "random_music",
                            isPreviewing = previewingId == "random_music",
                            onSelect = { onSelect("random_music", "Zufallslied (Musikbibliothek)") },
                            onTogglePreview = {
                                if (previewingId == "random_music") {
                                    FunkenwerkAlarmManager.stopAllActiveAudio()
                                    previewingId = null
                                } else {
                                    FunkenwerkAlarmManager.startLoopingAlarmPlayerOnly(context, "random_music")
                                    previewingId = "random_music"
                                }
                            }
                        )
                    }

                    items(filteredTracks, key = { it.id }) { track ->
                        val displayTitle = if (track.artist.isNotBlank() && track.artist != "Mein Seelenfunke") {
                            "${track.title} (${track.artist})"
                        } else {
                            track.title
                        }
                        SoundOptionRow(
                            title = track.title,
                            subtitle = track.artist.ifBlank { "Musikbibliothek" },
                            isSelected = currentSelectedId == track.id,
                            isPreviewing = previewingId == track.id,
                            onSelect = { onSelect(track.id, displayTitle) },
                            onTogglePreview = {
                                if (previewingId == track.id) {
                                    FunkenwerkAlarmManager.stopAllActiveAudio()
                                    previewingId = null
                                } else {
                                    MusicPlayerManager.cacheTrackForAlarmAsync(context, track.id)
                                    FunkenwerkAlarmManager.startLoopingAlarmPlayerOnly(context, track.id)
                                    previewingId = track.id
                                }
                            }
                        )
                    }
                }
            }
        },
        confirmButton = {
            TextButton(onClick = onDismiss) {
                Text("Schließen", color = Gold, fontWeight = FontWeight.Bold)
            }
        }
    )
}

@Composable
private fun SoundOptionRow(
    title: String,
    subtitle: String,
    isSelected: Boolean,
    isPreviewing: Boolean,
    onSelect: () -> Unit,
    onTogglePreview: () -> Unit
) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(10.dp))
            .background(if (isSelected) Gold.copy(alpha = 0.14f) else AppTheme.colors.inputBackground)
            .border(1.dp, if (isSelected) Gold else AppTheme.colors.cardBorder, RoundedCornerShape(10.dp))
            .clickable { onSelect() }
            .padding(horizontal = 10.dp, vertical = 8.dp),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically
    ) {
        Column(modifier = Modifier.weight(1f)) {
            Text(
                text = title,
                fontSize = 12.5.sp,
                fontWeight = FontWeight.Bold,
                color = if (isSelected) Gold else AppTheme.colors.textPrimary,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis
            )
            Text(
                text = subtitle,
                fontSize = 10.5.sp,
                color = AppTheme.colors.textMuted,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis
            )
        }
        IconButton(
            onClick = onTogglePreview,
            modifier = Modifier.size(32.dp)
        ) {
            Icon(
                imageVector = if (isPreviewing) Icons.Default.Stop else Icons.Default.PlayArrow,
                contentDescription = "Vorschau",
                tint = if (isPreviewing) Rose500 else Gold,
                modifier = Modifier.size(18.dp)
            )
        }
    }
}

@Composable
private fun AddTimerPresetDialog(
    onDismiss: () -> Unit,
    onAdd: (label: String, durationSeconds: Int) -> Unit
) {
    var minutesStr by remember { mutableStateOf("10") }
    var secondsStr by remember { mutableStateOf("0") }
    var customLabel by remember { mutableStateOf("") }

    AlertDialog(
        onDismissRequest = onDismiss,
        containerColor = AppTheme.colors.cardBackground,
        shape = RoundedCornerShape(20.dp),
        title = {
            Text("Neuer Timer", fontWeight = FontWeight.Bold, color = AppTheme.colors.textPrimary)
        },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                    OutlinedTextField(
                        value = minutesStr,
                        onValueChange = { if (it.all { c -> c.isDigit() }) minutesStr = it },
                        label = { Text("Minuten") },
                        singleLine = true,
                        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                        modifier = Modifier.weight(1f),
                        colors = alarmOutlinedTextFieldColors()
                    )
                    OutlinedTextField(
                        value = secondsStr,
                        onValueChange = { if (it.all { c -> c.isDigit() }) secondsStr = it },
                        label = { Text("Sekunden") },
                        singleLine = true,
                        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                        modifier = Modifier.weight(1f),
                        colors = alarmOutlinedTextFieldColors()
                    )
                }
                OutlinedTextField(
                    value = customLabel,
                    onValueChange = { customLabel = it },
                    label = { Text("Titel (optional, z.B. Timer: 25 Min.)") },
                    singleLine = true,
                    modifier = Modifier.fillMaxWidth(),
                    colors = alarmOutlinedTextFieldColors()
                )
            }
        },
        confirmButton = {
            Button(
                onClick = {
                    val mins = minutesStr.toIntOrNull() ?: 0
                    val secs = secondsStr.toIntOrNull() ?: 0
                    val total = (mins * 60 + secs).coerceAtLeast(5)
                    val title = customLabel.ifBlank {
                        if (secs == 0) "Timer: $mins Min." else "Timer: ${mins}m ${secs}s"
                    }
                    onAdd(title, total)
                },
                colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = SpaceBlack)
            ) {
                Text("Hinzufügen", fontWeight = FontWeight.Bold)
            }
        },
        dismissButton = {
            TextButton(onClick = onDismiss) {
                Text("Abbrechen", color = AppTheme.colors.textMuted)
            }
        }
    )
}

@Composable
private fun SmartHomeAndAiSettingsDialog(
    smartPlugs: List<SmartPlugDevice>,
    onDismiss: () -> Unit,
    onAddPlug: (String, String) -> Unit,
    onTestPlug: (String, Boolean) -> Unit,
    onTestAiBriefing: () -> Unit,
    onStopAudio: () -> Unit,
    onLogWakeUpNow: () -> Unit
) {
    val context = LocalContext.current
    val initialCreds = remember { FunkenwerkAlarmManager.getTapoCredentials(context) }
    var tapoEmail by remember { mutableStateOf(initialCreds.first) }
    var tapoPass by remember { mutableStateOf(initialCreds.second) }
    var showPassword by remember { mutableStateOf(false) }

    var newPlugName by remember { mutableStateOf("") }
    var newPlugIp by remember { mutableStateOf("") }
    val smartCameras = remember { FunkenwerkAlarmManager.defaultSmartCameras() }

    AlertDialog(
        onDismissRequest = onDismiss,
        containerColor = AppTheme.colors.cardBackground,
        shape = RoundedCornerShape(20.dp),
        title = {
            Text(
                text = "Smart-Home & KI-Wecker",
                fontSize = 18.sp,
                fontWeight = FontWeight.Bold,
                color = AppTheme.colors.textPrimary
            )
        },
        text = {
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .verticalScroll(rememberScrollState()),
                verticalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                Text(
                    text = "Tapo Lokale KLAP-Zugangsdaten (Heimnetz)",
                    fontSize = 12.sp,
                    fontWeight = FontWeight.Bold,
                    color = Gold
                )
                OutlinedTextField(
                    value = tapoEmail,
                    onValueChange = { tapoEmail = it },
                    label = { Text("Tapo E-Mail") },
                    singleLine = true,
                    modifier = Modifier.fillMaxWidth(),
                    colors = alarmOutlinedTextFieldColors()
                )
                OutlinedTextField(
                    value = tapoPass,
                    onValueChange = { tapoPass = it },
                    label = { Text("Tapo Passwort") },
                    singleLine = true,
                    visualTransformation = if (showPassword) VisualTransformation.None else PasswordVisualTransformation(),
                    trailingIcon = {
                        IconButton(onClick = { showPassword = !showPassword }) {
                            Icon(
                                imageVector = if (showPassword) Icons.Default.VisibilityOff else Icons.Default.Visibility,
                                contentDescription = "Passwort anzeigen",
                                tint = if (AppTheme.colors.isDark) Color.White else AppTheme.colors.textPrimary
                            )
                        }
                    },
                    modifier = Modifier.fillMaxWidth(),
                    colors = alarmOutlinedTextFieldColors()
                )

                Text(
                    text = "Tapo Smart-Home Steckdosen (P100)",
                    fontSize = 12.sp,
                    fontWeight = FontWeight.Bold,
                    color = Gold
                )
                smartPlugs.forEach { plug ->
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clip(RoundedCornerShape(10.dp))
                            .background(AppTheme.colors.inputBackground)
                            .border(1.dp, AppTheme.colors.cardBorder, RoundedCornerShape(10.dp))
                            .padding(horizontal = 10.dp, vertical = 6.dp),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Column(modifier = Modifier.weight(1f)) {
                            Text(plug.name, fontSize = 12.sp, fontWeight = FontWeight.Bold, color = AppTheme.colors.textPrimary)
                            Text(plug.ip, fontSize = 11.sp, color = AppTheme.colors.textMuted)
                        }
                        Row(horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                            TextButton(onClick = {
                                FunkenwerkAlarmManager.saveTapoCredentials(context, tapoEmail, tapoPass)
                                onTestPlug(plug.ip, true)
                            }) {
                                Text("EIN", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = Emerald500)
                            }
                            TextButton(onClick = {
                                FunkenwerkAlarmManager.saveTapoCredentials(context, tapoEmail, tapoPass)
                                onTestPlug(plug.ip, false)
                            }) {
                                Text("AUS", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = Rose500)
                            }
                        }
                    }
                }

                // Add new plug
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(6.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    OutlinedTextField(
                        value = newPlugName,
                        onValueChange = { newPlugName = it },
                        label = { Text("Name", fontSize = 10.sp) },
                        singleLine = true,
                        modifier = Modifier.weight(1f),
                        colors = alarmOutlinedTextFieldColors()
                    )
                    OutlinedTextField(
                        value = newPlugIp,
                        onValueChange = { newPlugIp = it },
                        label = { Text("IP (192.168...)", fontSize = 10.sp) },
                        singleLine = true,
                        modifier = Modifier.weight(1f),
                        colors = alarmOutlinedTextFieldColors()
                    )
                    IconButton(onClick = {
                        if (newPlugIp.isNotBlank()) {
                            onAddPlug(newPlugName.ifBlank { "Smart Plug" }, newPlugIp)
                            newPlugName = ""
                            newPlugIp = ""
                        }
                    }) {
                        Icon(Icons.Default.Add, contentDescription = "Hinzufügen", tint = Gold)
                    }
                }

                Text(
                    text = "Tapo Smart-Home Kameras (Funkenwerk)",
                    fontSize = 12.sp,
                    fontWeight = FontWeight.Bold,
                    color = Gold
                )
                smartCameras.forEach { cam ->
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clip(RoundedCornerShape(10.dp))
                            .background(AppTheme.colors.inputBackground)
                            .border(1.dp, AppTheme.colors.cardBorder, RoundedCornerShape(10.dp))
                            .padding(horizontal = 10.dp, vertical = 6.dp),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Column(modifier = Modifier.weight(1f)) {
                            Text("📷 ${cam.name}", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = AppTheme.colors.textPrimary)
                            Text("${cam.ip} • ${cam.description}", fontSize = 11.sp, color = AppTheme.colors.textMuted)
                        }
                    }
                }

                // AI Briefing Test & Stop Audio
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    Button(
                        onClick = onTestAiBriefing,
                        modifier = Modifier.weight(1f),
                        colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = SpaceBlack)
                    ) {
                        Text("🎙️ KI-Briefing testen", fontSize = 11.sp, fontWeight = FontWeight.Bold)
                    }
                    Button(
                        onClick = onStopAudio,
                        colors = ButtonDefaults.buttonColors(containerColor = Rose500, contentColor = Color.White)
                    ) {
                        Text("Ton Stopp", fontSize = 11.sp, fontWeight = FontWeight.Bold)
                    }
                }

                // Manual Wake-Up Log & Sync to /admin/routine
                Button(
                    onClick = onLogWakeUpNow,
                    modifier = Modifier.fillMaxWidth(),
                    colors = ButtonDefaults.buttonColors(
                        containerColor = Emerald500.copy(alpha = 0.2f),
                        contentColor = Emerald500
                    )
                ) {
                    Text(
                        text = "🌅 Jetzt aufgestanden loggen & Routine takten",
                        fontSize = 11.5.sp,
                        fontWeight = FontWeight.Bold
                    )
                }
            }
        },
        confirmButton = {
            Button(
                onClick = {
                    FunkenwerkAlarmManager.saveTapoCredentials(context, tapoEmail, tapoPass)
                    onDismiss()
                },
                colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = SpaceBlack)
            ) {
                Text("Speichern", fontWeight = FontWeight.Bold)
            }
        },
        dismissButton = {
            TextButton(onClick = onDismiss) {
                Text("Schließen", color = AppTheme.colors.textMuted)
            }
        }
    )
}
