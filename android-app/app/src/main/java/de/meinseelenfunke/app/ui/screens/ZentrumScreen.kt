package de.meinseelenfunke.app.ui.screens

import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Image
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
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.ArrowForward
import androidx.compose.material.icons.filled.AutoAwesome
import androidx.compose.material.icons.filled.Bolt
import androidx.compose.material.icons.filled.CalendarToday
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.DarkMode
import androidx.compose.material.icons.filled.ExitToApp
import androidx.compose.material.icons.filled.KeyboardArrowDown
import androidx.compose.material.icons.filled.KeyboardArrowUp
import androidx.compose.material.icons.filled.LightMode
import androidx.compose.material.icons.filled.Mic
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material.icons.filled.Schedule
import androidx.compose.material.icons.filled.ShoppingBag
import androidx.compose.material.icons.filled.TrendingDown
import androidx.compose.material.icons.filled.TrendingUp
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.FloatingActionButton
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.scale
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.viewmodel.compose.viewModel
import de.meinseelenfunke.app.R
import de.meinseelenfunke.app.data.api.CalendarEvent
import de.meinseelenfunke.app.data.api.FinanceKpis
import de.meinseelenfunke.app.data.api.ManagementDayRoutine
import de.meinseelenfunke.app.data.api.ManagementTask
import de.meinseelenfunke.app.data.api.OrderSummary
import de.meinseelenfunke.app.data.api.UltimateCommandResponse
import de.meinseelenfunke.app.di.ServiceLocator
import de.meinseelenfunke.app.ui.theme.AppTheme
import de.meinseelenfunke.app.ui.theme.Copper
import de.meinseelenfunke.app.ui.theme.Emerald500
import de.meinseelenfunke.app.ui.theme.Gold
import de.meinseelenfunke.app.ui.theme.Rose500
import de.meinseelenfunke.app.ui.theme.SpaceBlack
import de.meinseelenfunke.app.ui.theme.SpaceBlue
import de.meinseelenfunke.app.util.SoundManager
import java.util.Locale

@Composable
fun ZentrumScreen(
    onNavigateToLiveChat: () -> Unit,
    onNavigateToFinances: () -> Unit,
    onNavigateToOrganizer: (subTab: Int) -> Unit,
    onNavigateToOrders: () -> Unit = {},
    onNavigateToSettings: () -> Unit = {},
    onLogout: () -> Unit = {},
    isPageVisible: Boolean = true,
    viewModel: ZentrumViewModel = viewModel()
) {
    val uiState by viewModel.uiState.collectAsState()
    val ultimateCommand by viewModel.ultimateCommand.collectAsState()
    val kpis by viewModel.kpis.collectAsState()
    val routines by viewModel.routines.collectAsState()
    val tasks by viewModel.tasks.collectAsState()
    val events by viewModel.events.collectAsState()
    val orders by viewModel.orders.collectAsState()
    val totalOrdersCount by viewModel.totalOrdersCount.collectAsState()
    val userType by ServiceLocator.userTypeState.collectAsState()
    val isAdmin = userType == "admin" || ServiceLocator.getUserType() == "admin"

    LaunchedEffect(isPageVisible) {
        if (isPageVisible) {
            viewModel.loadDashboardData(showLoading = false)
        }
    }

    val infiniteTransition = rememberInfiniteTransition(label = "pulse")
    val pulseScale by infiniteTransition.animateFloat(
        initialValue = 1f,
        targetValue = 1.07f,
        animationSpec = infiniteRepeatable(
            animation = tween(1400, easing = FastOutSlowInEasing),
            repeatMode = RepeatMode.Reverse
        ),
        label = "pulseScale"
    )

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(Brush.verticalGradient(AppTheme.colors.bgGradient))
    ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(horizontal = 16.dp, vertical = 12.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            // Compact Top Header with Logo and Action Icons
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Image(
                    painter = painterResource(id = R.drawable.mein_seelenfunke_logo),
                    contentDescription = "Seelenfunke Logo",
                    modifier = Modifier.height(42.dp),
                    contentScale = ContentScale.Fit
                )
                Row(
                    horizontalArrangement = Arrangement.spacedBy(8.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    // Theme Toggle
                    Box(
                        modifier = Modifier
                            .size(34.dp)
                            .clip(CircleShape)
                            .background(AppTheme.colors.glassSurface)
                            .border(1.dp, AppTheme.colors.glassBorder, CircleShape)
                            .clickable { ServiceLocator.toggleTheme() },
                        contentAlignment = Alignment.Center
                    ) {
                        Icon(
                            imageVector = if (AppTheme.colors.isDark) Icons.Default.LightMode else Icons.Default.DarkMode,
                            contentDescription = "Design wechseln",
                            tint = Gold,
                            modifier = Modifier.size(17.dp)
                        )
                    }
                    // Mic
                    Box(
                        modifier = Modifier
                            .size(34.dp)
                            .clip(CircleShape)
                            .background(AppTheme.colors.glassSurface)
                            .border(1.dp, AppTheme.colors.glassBorder, CircleShape)
                            .clickable { onNavigateToLiveChat() },
                        contentAlignment = Alignment.Center
                    ) {
                        Icon(
                            imageVector = Icons.Default.Mic,
                            contentDescription = "Sprachchat",
                            tint = Gold,
                            modifier = Modifier.size(17.dp)
                        )
                    }
                    // Refresh
                    Box(
                        modifier = Modifier
                            .size(34.dp)
                            .clip(CircleShape)
                            .background(AppTheme.colors.glassSurface)
                            .border(1.dp, AppTheme.colors.glassBorder, CircleShape)
                            .clickable { viewModel.loadDashboardData(showLoading = true) },
                        contentAlignment = Alignment.Center
                    ) {
                        Icon(
                            imageVector = Icons.Default.Refresh,
                            contentDescription = "Aktualisieren",
                            tint = Gold,
                            modifier = Modifier.size(17.dp)
                        )
                    }
                    // Logout
                    Box(
                        modifier = Modifier
                            .size(34.dp)
                            .clip(CircleShape)
                            .background(AppTheme.colors.glassSurface)
                            .border(1.dp, AppTheme.colors.glassBorder, CircleShape)
                            .clickable { onLogout() },
                        contentAlignment = Alignment.Center
                    ) {
                        Icon(
                            imageVector = Icons.Default.ExitToApp,
                            contentDescription = "Ausloggen",
                            tint = Rose500,
                            modifier = Modifier.size(17.dp)
                        )
                    }
                }
            }

            when (uiState) {
                is ZentrumUiState.Loading -> {
                    Box(
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(240.dp),
                        contentAlignment = Alignment.Center
                    ) {
                        CircularProgressIndicator(color = Gold)
                    }
                }

                is ZentrumUiState.Error -> {
                    val msg = (uiState as ZentrumUiState.Error).message
                    Card(
                        modifier = Modifier
                            .fillMaxWidth()
                            .border(1.dp, Rose500.copy(alpha = 0.5f), RoundedCornerShape(16.dp)),
                        colors = CardDefaults.cardColors(containerColor = Color(0x22EF4444)),
                        shape = RoundedCornerShape(16.dp)
                    ) {
                        Column(
                            modifier = Modifier.padding(16.dp),
                            horizontalAlignment = Alignment.CenterHorizontally
                        ) {
                            Text(text = "Fehler beim Laden", fontWeight = FontWeight.Bold, color = Rose500)
                            Spacer(modifier = Modifier.height(4.dp))
                            Text(text = msg, fontSize = 12.sp, color = AppTheme.colors.textPrimary, textAlign = TextAlign.Center)
                            Spacer(modifier = Modifier.height(10.dp))
                            Button(
                                onClick = { viewModel.loadDashboardData(showLoading = true) },
                                colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = SpaceBlack)
                            ) {
                                Text("Erneut versuchen", fontWeight = FontWeight.Bold)
                            }
                        }
                    }
                }

                is ZentrumUiState.Success -> {
                    DashboardContent(
                        ultimateCommand = ultimateCommand,
                        kpis = kpis,
                        routines = routines,
                        tasks = tasks,
                        events = events,
                        orders = orders,
                        totalOrdersCount = totalOrdersCount,
                        isAdmin = isAdmin,
                        onNavigateToFinances = onNavigateToFinances,
                        onNavigateToOrganizer = onNavigateToOrganizer,
                        onNavigateToOrders = onNavigateToOrders,
                        onNavigateToSettings = onNavigateToSettings,
                        viewModel = viewModel
                    )
                }
            }
        }

        // Floating Action Button for Funkira Live Voice Call
        Box(
            modifier = Modifier
                .align(Alignment.BottomEnd)
                .padding(bottom = 18.dp, end = 18.dp),
            contentAlignment = Alignment.Center
        ) {
            Box(
                modifier = Modifier
                    .size(60.dp)
                    .scale(pulseScale)
                    .background(
                        brush = Brush.radialGradient(
                            colors = listOf(Gold.copy(alpha = 0.38f), Color.Transparent)
                        ),
                        shape = CircleShape
                    )
            )

            FloatingActionButton(
                onClick = {
                    SoundManager.playSound(R.raw.open_project_brain)
                    onNavigateToLiveChat()
                },
                containerColor = Gold,
                contentColor = SpaceBlack,
                shape = CircleShape,
                modifier = Modifier.size(52.dp)
            ) {
                Icon(
                    imageVector = Icons.Default.Mic,
                    contentDescription = "Start Live Call",
                    tint = SpaceBlack,
                    modifier = Modifier.size(22.dp)
                )
            }
        }
    }
}

@Composable
fun DashboardContent(
    ultimateCommand: UltimateCommandResponse?,
    kpis: FinanceKpis?,
    routines: List<ManagementDayRoutine>,
    tasks: List<ManagementTask>,
    events: List<CalendarEvent>,
    orders: List<OrderSummary> = emptyList(),
    totalOrdersCount: Int = 0,
    isAdmin: Boolean = false,
    onNavigateToFinances: () -> Unit,
    onNavigateToOrganizer: (subTab: Int) -> Unit,
    onNavigateToOrders: () -> Unit = {},
    onNavigateToSettings: () -> Unit = {},
    viewModel: ZentrumViewModel
) {
    // 1. NÄCHSTER SCHRITT (getUltimateCommand() in einem Satz)
    UltimateCommandCard(
        ultimateCommand = ultimateCommand,
        onActionClick = { actionRoute ->
            when {
                actionRoute.isNullOrBlank() -> onNavigateToOrganizer(0)
                actionRoute.contains("orders", ignoreCase = true) -> {
                    if (isAdmin) onNavigateToOrders() else onNavigateToFinances()
                }
                actionRoute.contains("tasks", ignoreCase = true) -> onNavigateToOrganizer(0)
                actionRoute.contains("calender", ignoreCase = true) || actionRoute.contains("calendar", ignoreCase = true) -> onNavigateToOrganizer(1)
                actionRoute.contains("routine", ignoreCase = true) -> onNavigateToOrganizer(2)
                actionRoute.contains("financial", ignoreCase = true) || actionRoute.contains("invoice", ignoreCase = true) -> onNavigateToFinances()
                actionRoute.contains("user", ignoreCase = true) || actionRoute.contains("setting", ignoreCase = true) -> onNavigateToSettings()
                else -> onNavigateToOrganizer(0)
            }
        }
    )

    // 2. VERFÜGBARES GUTHABEN IM MONAT (Finanzen)
    FinanceDashboardCard(kpis = kpis, onClick = onNavigateToFinances)

    // 3. BELEG ERFASSEN & SCHNELLEINTRAG
    var isQuickEntryExpanded by remember { mutableStateOf(false) }
    val isUploading by viewModel.isUploading.collectAsState()
    val isAiScanning by viewModel.isAiScanning.collectAsState()
    val actionResult by viewModel.actionResult.collectAsState()
    val categories = remember {
        listOf("Lebensmittel", "Miete/Wohnen", "Transport", "Freizeit/Hobby", "Versicherungen", "Investitionen", "Sonstiges")
    }

    LaunchedEffect(actionResult) {
        actionResult?.let { res ->
            res.onSuccess {
                isQuickEntryExpanded = false
                viewModel.clearActionResult()
            }.onFailure {
                viewModel.clearActionResult()
            }
        }
    }

    Card(
        modifier = Modifier
            .fillMaxWidth()
            .border(1.dp, Gold.copy(alpha = 0.55f), RoundedCornerShape(16.dp)),
        colors = CardDefaults.cardColors(containerColor = AppTheme.colors.cardBackground),
        shape = RoundedCornerShape(16.dp)
    ) {
        Column(modifier = Modifier.fillMaxWidth()) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .clickable { isQuickEntryExpanded = !isQuickEntryExpanded }
                    .padding(horizontal = 14.dp, vertical = 12.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Box(
                        modifier = Modifier
                            .size(34.dp)
                            .clip(CircleShape)
                            .background(Gold.copy(alpha = 0.16f)),
                        contentAlignment = Alignment.Center
                    ) {
                        Icon(
                            imageVector = Icons.Default.Add,
                            contentDescription = null,
                            tint = Gold,
                            modifier = Modifier.size(18.dp)
                        )
                    }
                    Spacer(modifier = Modifier.width(10.dp))
                    Column {
                        Text(
                            text = "Beleg erfassen & Schnelleintrag",
                            fontSize = 14.sp,
                            fontWeight = FontWeight.Bold,
                            color = AppTheme.colors.textPrimary
                        )
                        Text(
                            text = "KI-Beleg-Scanner (Kamera / Datei) oder manuell",
                            fontSize = 11.sp,
                            color = AppTheme.colors.textMuted
                        )
                    }
                }
                Icon(
                    imageVector = if (isQuickEntryExpanded) Icons.Default.KeyboardArrowUp else Icons.Default.KeyboardArrowDown,
                    contentDescription = if (isQuickEntryExpanded) "Zuklappen" else "Aufklappen",
                    tint = Gold,
                    modifier = Modifier.size(20.dp)
                )
            }

            AnimatedVisibility(visible = isQuickEntryExpanded) {
                QuickEntryForm(
                    categories = categories,
                    isUploading = isUploading,
                    isAiScanning = isAiScanning,
                    showCard = false,
                    showTitle = false,
                    onDismiss = { isQuickEntryExpanded = false },
                    onScanReceipt = { bytes, fileName, mime, autoSave, onResult ->
                        viewModel.scanReceipt(bytes, fileName, mime, autoSave) { scanData ->
                            scanData?.let { onResult(it) }
                        }
                    },
                    onSubmit = { title, amt, cat, isBiz, date, loc, bytes, name, mime ->
                        viewModel.submitQuickEntry(title, amt, cat, isBiz, date, loc, bytes, name, mime)
                    }
                )
            }
        }
    }

    // 4. KOMPAKTES 2x2 SCHNELLZUGRIFFS-RASTER (Kein Scrollen nötig, keine überladenen Listen)
    val openTasksCount = remember(tasks) { tasks.count { !it.is_completed && it.parent_id == null } }
    val highPrioCount = remember(tasks) {
        tasks.count { !it.is_completed && it.parent_id == null && (it.priority.equals("hoch", true) || it.priority.equals("high", true)) }
    }
    val activeRoutines = remember(routines) { routines.filter { it.is_active } }
    val nextEvent = events.firstOrNull()
    val openOrdersCount = remember(orders) {
        orders.count {
            it.status.equals("pending", ignoreCase = true) ||
                it.status.equals("processing", ignoreCase = true) ||
                it.status.equals("ausstehend", ignoreCase = true) ||
                it.status.equals("in_bearbeitung", ignoreCase = true)
        }
    }
    val expressCount = remember(orders) { orders.count { it.is_express == true } }

    Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
        // Row 1: To-do Liste (Tab 0) & Routine (Tab 2)
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(10.dp)
        ) {
            CompactDashboardTile(
                modifier = Modifier.weight(1f),
                icon = Icons.Default.CheckCircle,
                title = "To-do Liste",
                value = if (openTasksCount > 0) "$openTasksCount offen" else "Erledigt ✓",
                subtitle = if (highPrioCount > 0) "🔥 $highPrioCount hohe Prio" else "Aufgaben öffnen",
                isAlert = highPrioCount > 0,
                onClick = { onNavigateToOrganizer(0) }
            )

            val firstRoutine = activeRoutines.firstOrNull()
            CompactDashboardTile(
                modifier = Modifier.weight(1f),
                icon = Icons.Default.Schedule,
                title = "Routine",
                value = firstRoutine?.title ?: if (routines.isNotEmpty()) "${routines.size} Routinen" else "Keine aktiv",
                subtitle = if (firstRoutine != null) "Ab ${firstRoutine.start_time.take(5)} Uhr" else "Routine-Planer",
                isAlert = false,
                onClick = { onNavigateToOrganizer(2) }
            )
        }

        // Row 2: Kalender (Tab 1) & Bestellungen (für Admins)
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(10.dp)
        ) {
            val nextEventTime = if (nextEvent != null && nextEvent.start.length >= 16) {
                nextEvent.start.substring(11, 16) + " Uhr"
            } else {
                nextEvent?.start?.take(10) ?: "Kalender öffnen"
            }
            CompactDashboardTile(
                modifier = Modifier.weight(1f),
                icon = Icons.Default.CalendarToday,
                title = "Kalender",
                value = nextEvent?.title ?: "Keine Termine",
                subtitle = if (events.size > 1) "$nextEventTime (+${events.size - 1})" else nextEventTime,
                isAlert = false,
                onClick = { onNavigateToOrganizer(1) }
            )

            if (isAdmin) {
                CompactDashboardTile(
                    modifier = Modifier.weight(1f),
                    icon = if (expressCount > 0) Icons.Default.Bolt else Icons.Default.ShoppingBag,
                    title = "Bestellungen",
                    value = if (openOrdersCount > 0) "$openOrdersCount offen" else "$totalOrdersCount gesamt",
                    subtitle = if (expressCount > 0) "⚡ $expressCount Express!" else "$totalOrdersCount Aufträge gesamt",
                    isAlert = expressCount > 0,
                    onClick = onNavigateToOrders
                )
            }
        }
    }
}

@Composable
fun CompactDashboardTile(
    modifier: Modifier = Modifier,
    icon: ImageVector,
    title: String,
    value: String,
    subtitle: String,
    isAlert: Boolean = false,
    onClick: () -> Unit
) {
    Card(
        modifier = modifier
            .border(
                width = 1.dp,
                color = if (isAlert) Rose500.copy(alpha = 0.6f) else AppTheme.colors.cardBorder,
                shape = RoundedCornerShape(14.dp)
            )
            .clickable(onClick = onClick),
        colors = CardDefaults.cardColors(containerColor = AppTheme.colors.cardBackground),
        shape = RoundedCornerShape(14.dp)
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 12.dp, vertical = 11.dp),
            verticalArrangement = Arrangement.spacedBy(4.dp)
        ) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(6.dp)
                ) {
                    Icon(
                        imageVector = icon,
                        contentDescription = null,
                        tint = if (isAlert) Rose500 else Gold,
                        modifier = Modifier.size(15.dp)
                    )
                    Text(
                        text = title,
                        fontSize = 11.sp,
                        fontWeight = FontWeight.Bold,
                        color = AppTheme.colors.textMuted
                    )
                }
                Icon(
                    imageVector = Icons.Default.ArrowForward,
                    contentDescription = null,
                    tint = if (isAlert) Rose500 else Gold,
                    modifier = Modifier.size(13.dp)
                )
            }

            Text(
                text = value,
                fontSize = 14.sp,
                fontWeight = FontWeight.Bold,
                color = AppTheme.colors.textPrimary,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis
            )

            Text(
                text = subtitle,
                fontSize = 10.5.sp,
                color = if (isAlert) Rose500 else AppTheme.colors.textMuted,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis
            )
        }
    }
}

@Composable
fun UltimateCommandCard(
    ultimateCommand: UltimateCommandResponse?,
    onActionClick: (actionRoute: String?) -> Unit
) {
    val rec = ultimateCommand?.recommendation
    val flow = ultimateCommand?.flow
    val isUrgent = (rec?.score ?: 0) >= 85 || flow?.type == "emergency"
    val borderColor = if (isUrgent) Rose500.copy(alpha = 0.7f) else Gold

    Card(
        modifier = Modifier
            .fillMaxWidth()
            .border(1.dp, borderColor, RoundedCornerShape(16.dp))
            .clickable { onActionClick(rec?.action_route) },
        colors = CardDefaults.cardColors(
            containerColor = if (isUrgent) Color(0x1AEF4444) else Color(0x14C5A059)
        ),
        shape = RoundedCornerShape(16.dp)
    ) {
        Column(modifier = Modifier.padding(horizontal = 14.dp, vertical = 12.dp)) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(6.dp)
                ) {
                    Icon(
                        imageVector = Icons.Default.AutoAwesome,
                        contentDescription = null,
                        tint = if (isUrgent) Rose500 else Gold,
                        modifier = Modifier.size(14.dp)
                    )
                    Text(
                        text = "NÄCHSTER SCHRITT",
                        fontSize = 10.5.sp,
                        fontWeight = FontWeight.ExtraBold,
                        letterSpacing = 1.1.sp,
                        color = if (isUrgent) Rose500 else Gold
                    )
                }

                if (!flow?.title.isNullOrBlank()) {
                    Box(
                        modifier = Modifier
                            .clip(RoundedCornerShape(50))
                            .background(AppTheme.colors.glassSurface)
                            .border(1.dp, AppTheme.colors.cardBorder, RoundedCornerShape(50))
                            .padding(horizontal = 8.dp, vertical = 2.dp)
                    ) {
                        Text(
                            text = flow?.title ?: "",
                            fontSize = 9.5.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = AppTheme.colors.textMuted
                        )
                    }
                }
            }

            Spacer(modifier = Modifier.height(6.dp))

            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(10.dp)
            ) {
                Text(
                    text = rec?.icon?.takeIf { it.isNotBlank() } ?: "✨",
                    fontSize = 20.sp
                )
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        text = rec?.title ?: "Fokus wird geladen...",
                        fontSize = 14.sp,
                        fontWeight = FontWeight.Bold,
                        color = AppTheme.colors.textPrimary,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis
                    )
                    Text(
                        text = rec?.message ?: "Alle Systeme bereit.",
                        fontSize = 12.sp,
                        color = AppTheme.colors.textSecondary,
                        lineHeight = 16.sp,
                        maxLines = 2,
                        overflow = TextOverflow.Ellipsis
                    )
                }
                Icon(
                    imageVector = Icons.Default.ArrowForward,
                    contentDescription = null,
                    tint = if (isUrgent) Rose500 else Gold,
                    modifier = Modifier.size(16.dp)
                )
            }
        }
    }
}

@Composable
fun FinanceDashboardCard(kpis: FinanceKpis?, onClick: () -> Unit) {
    var showDetails by remember { mutableStateOf(false) }
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .border(1.dp, AppTheme.colors.cardBorder, RoundedCornerShape(16.dp))
            .clickable(onClick = onClick),
        colors = CardDefaults.cardColors(containerColor = AppTheme.colors.cardBackground),
        shape = RoundedCornerShape(16.dp)
    ) {
        Column(modifier = Modifier.padding(horizontal = 14.dp, vertical = 12.dp)) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = "Verfügbares Guthaben (${kpis?.month_label ?: "Aktueller Monat"})",
                    fontSize = 12.sp,
                    fontWeight = FontWeight.Bold,
                    color = AppTheme.colors.textSecondary
                )
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(4.dp),
                    modifier = Modifier
                        .clip(RoundedCornerShape(6.dp))
                        .clickable { showDetails = !showDetails }
                        .padding(horizontal = 6.dp, vertical = 2.dp)
                ) {
                    Text(
                        text = if (showDetails) "Weniger" else "Details",
                        fontSize = 11.sp,
                        color = Gold,
                        fontWeight = FontWeight.Medium
                    )
                    Icon(
                        imageVector = if (showDetails) Icons.Default.KeyboardArrowUp else Icons.Default.KeyboardArrowDown,
                        contentDescription = null,
                        tint = Gold,
                        modifier = Modifier.size(14.dp)
                    )
                }
            }

            Spacer(modifier = Modifier.height(2.dp))

            val available = kpis?.available ?: 0.0
            Text(
                text = String.format(Locale.GERMANY, "%,.2f €", available),
                fontSize = 26.sp,
                fontWeight = FontWeight.ExtraBold,
                color = if (available < 0.0) Rose500 else Gold
            )

            AnimatedVisibility(visible = showDetails) {
                Column {
                    Spacer(modifier = Modifier.height(10.dp))
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        FinanceSubKpi(
                            title = "Shop Einnahmen",
                            value = kpis?.shop_revenue ?: 0.0,
                            color = Gold,
                            icon = Icons.Default.TrendingUp
                        )
                        FinanceSubKpi(
                            title = "Fixkosten",
                            value = kpis?.fixed_expenses ?: 0.0,
                            color = Copper,
                            icon = Icons.Default.TrendingDown
                        )
                        FinanceSubKpi(
                            title = "Variable",
                            value = kpis?.special_expenses ?: 0.0,
                            color = SpaceBlue,
                            icon = Icons.Default.TrendingDown
                        )
                    }
                }
            }
        }
    }
}

@Composable
fun FinanceSubKpi(title: String, value: Double, color: Color, icon: ImageVector) {
    Column {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Icon(
                imageVector = icon,
                contentDescription = null,
                tint = color,
                modifier = Modifier.size(12.dp)
            )
            Spacer(modifier = Modifier.width(4.dp))
            Text(title, fontSize = 11.sp, color = AppTheme.colors.textMuted)
        }
        Text(
            text = String.format(Locale.GERMANY, "%,.2f €", value),
            fontSize = 14.sp,
            fontWeight = FontWeight.Bold,
            color = AppTheme.colors.textPrimary,
            modifier = Modifier.padding(top = 2.dp)
        )
    }
}
