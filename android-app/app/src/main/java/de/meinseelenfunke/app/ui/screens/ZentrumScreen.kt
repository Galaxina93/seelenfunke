package de.meinseelenfunke.app.ui.screens

import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
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
import androidx.compose.material.icons.filled.CalendarToday
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.Lightbulb
import androidx.compose.material.icons.filled.Mic
import androidx.compose.material.icons.filled.PlaylistAddCheck
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material.icons.filled.Schedule
import androidx.compose.material.icons.filled.TrendingDown
import androidx.compose.material.icons.filled.TrendingUp
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.Checkbox
import androidx.compose.material3.CheckboxDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material.icons.filled.KeyboardArrowDown
import androidx.compose.material.icons.filled.KeyboardArrowUp
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.foundation.Image
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.layout.ContentScale
import de.meinseelenfunke.app.R
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.scale
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.viewmodel.compose.viewModel
import de.meinseelenfunke.app.data.api.CalendarEvent
import de.meinseelenfunke.app.data.api.FinanceKpis
import de.meinseelenfunke.app.data.api.ManagementDayRoutine
import de.meinseelenfunke.app.data.api.ManagementTask
import de.meinseelenfunke.app.ui.theme.Gold
import de.meinseelenfunke.app.ui.theme.GoldGlow
import de.meinseelenfunke.app.ui.theme.Copper
import de.meinseelenfunke.app.ui.theme.SpaceBlue
import de.meinseelenfunke.app.ui.theme.SpaceBlack
import de.meinseelenfunke.app.ui.theme.GlassWhite10
import de.meinseelenfunke.app.ui.theme.GlassWhite20
import de.meinseelenfunke.app.ui.theme.Slate400
import de.meinseelenfunke.app.ui.theme.Slate50
import de.meinseelenfunke.app.ui.theme.Slate900
import java.util.Locale

import androidx.compose.material.icons.filled.ExitToApp
import de.meinseelenfunke.app.ui.theme.Rose500

@Composable
fun ZentrumScreen(
    onNavigateToLiveChat: () -> Unit,
    onNavigateToFinances: () -> Unit,
    onNavigateToOrganizer: () -> Unit,
    onLogout: () -> Unit,
    viewModel: ZentrumViewModel = viewModel()
) {
    val dashboardState by viewModel.dashboardState.collectAsState()

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(
                Brush.verticalGradient(
                    colors = listOf(SpaceBlack, Slate900)
                )
            )
            .verticalScroll(rememberScrollState())
            .padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(20.dp)
    ) {
        // Upper Title Header with Logo and Action Icons
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Image(
                painter = painterResource(id = R.drawable.mein_seelenfunke_logo),
                contentDescription = "Seelenfunke Logo",
                modifier = Modifier.height(88.dp),
                contentScale = ContentScale.Fit
            )
            Row(
                horizontalArrangement = Arrangement.spacedBy(8.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                IconButton(
                    onClick = onNavigateToLiveChat,
                    modifier = Modifier
                        .clip(CircleShape)
                        .background(GlassWhite10)
                ) {
                    Icon(
                        imageVector = Icons.Default.Mic,
                        contentDescription = "Sprachchat starten",
                        tint = Gold
                    )
                }
                IconButton(
                    onClick = { viewModel.loadDashboardData() },
                    modifier = Modifier
                        .clip(CircleShape)
                        .background(GlassWhite10)
                ) {
                    Icon(
                        imageVector = Icons.Default.Refresh,
                        contentDescription = "Aktualisieren",
                        tint = Gold
                    )
                }
                IconButton(
                    onClick = onLogout,
                    modifier = Modifier
                        .clip(CircleShape)
                        .background(GlassWhite10)
                ) {
                    Icon(
                        imageVector = Icons.Default.ExitToApp,
                        contentDescription = "Ausloggen",
                        tint = Rose500
                    )
                }
            }
        }

        // Dashboard content depending on state
        when (val state = dashboardState) {
            is DashboardState.Loading -> {
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(200.dp),
                    contentAlignment = Alignment.Center
                ) {
                    CircularProgressIndicator(color = Gold)
                }
            }
            is DashboardState.Success -> {
                DashboardContent(
                    kpis = state.kpis,
                    routines = state.routines,
                    tasks = state.tasks,
                    events = state.events,
                    onToggleTask = { viewModel.toggleTask(it) },
                    onNavigateToFinances = onNavigateToFinances,
                    onNavigateToOrganizer = onNavigateToOrganizer,
                    viewModel = viewModel
                )
            }
            is DashboardState.Error -> {
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    colors = CardDefaults.cardColors(containerColor = Color(0x33FF0000))
                ) {
                    Column(
                        modifier = Modifier.padding(16.dp),
                        horizontalAlignment = Alignment.CenterHorizontally
                    ) {
                        Text(state.message, color = Color.Red, textAlign = TextAlign.Center)
                        Spacer(modifier = Modifier.height(8.dp))
                        Button(
                            onClick = { viewModel.loadDashboardData() },
                            colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = SpaceBlack)
                        ) {
                            Text("Erneut versuchen")
                        }
                    }
                }
            }
        }
        Spacer(modifier = Modifier.height(40.dp))
    }
}

@Composable
fun LiveVoiceCallBanner(onClick: () -> Unit) {
    val infiniteTransition = rememberInfiniteTransition()
    val pulseScale by infiniteTransition.animateFloat(
        initialValue = 1.0f,
        targetValue = 1.08f,
        animationSpec = infiniteRepeatable(
            animation = tween(1200),
            repeatMode = RepeatMode.Reverse
        )
    )

    Card(
        modifier = Modifier
            .fillMaxWidth()
            .border(1.dp, Gold, RoundedCornerShape(16.dp))
            .clickable(onClick = onClick),
        colors = CardDefaults.cardColors(
            containerColor = Color(0x11C5A059)
        ),
        shape = RoundedCornerShape(16.dp)
    ) {
        Row(
            modifier = Modifier.padding(20.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.SpaceBetween
        ) {
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = "Sprich mit Agenten",
                    fontSize = 18.sp,
                    fontWeight = FontWeight.Bold,
                    color = Gold
                )
                Text(
                    text = "Echtzeit-Sprachassistent starten",
                    fontSize = 12.sp,
                    color = Slate400,
                    modifier = Modifier.padding(top = 4.dp)
                )
            }
            Box(
                modifier = Modifier
                    .scale(pulseScale)
                    .size(48.dp)
                    .clip(CircleShape)
                    .background(Brush.radialGradient(listOf(Gold, GoldGlow, Color.Transparent))),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    imageVector = Icons.Default.Mic,
                    contentDescription = "Sprachchat starten",
                    tint = SpaceBlack,
                    modifier = Modifier.size(24.dp)
                )
            }
        }
    }
}

@Composable
fun DashboardContent(
    kpis: FinanceKpis?,
    routines: List<ManagementDayRoutine>,
    tasks: List<ManagementTask>,
    events: List<CalendarEvent>,
    onToggleTask: (String) -> Unit,
    onNavigateToFinances: () -> Unit,
    onNavigateToOrganizer: () -> Unit,
    viewModel: ZentrumViewModel
) {
    // 1. Finance KPIs Card
    FinanceDashboardCard(kpis = kpis, onClick = onNavigateToFinances)

    // 2. Collapsible Quick Entry Card
    var isQuickEntryExpanded by remember { mutableStateOf(false) }
    val isUploading by viewModel.isUploading.collectAsState()
    val actionResult by viewModel.actionResult.collectAsState()
    val categories = listOf("Lebensmittel", "Miete/Wohnen", "Transport", "Freizeit/Hobby", "Versicherungen", "Investitionen", "Sonstiges")

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
            .border(1.dp, GlassWhite10, RoundedCornerShape(16.dp)),
        colors = CardDefaults.cardColors(containerColor = GlassWhite10),
        shape = RoundedCornerShape(16.dp)
    ) {
        Column(modifier = Modifier.fillMaxWidth()) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .clickable { isQuickEntryExpanded = !isQuickEntryExpanded }
                    .padding(16.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(
                        imageVector = Icons.Default.Add,
                        contentDescription = null,
                        tint = Gold,
                        modifier = Modifier.size(20.dp)
                    )
                    Spacer(modifier = Modifier.width(8.dp))
                    Text(
                        text = "Schnelleintrag hinzufügen",
                        fontSize = 15.sp,
                        fontWeight = FontWeight.Bold,
                        color = Slate50
                    )
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
                    onDismiss = { isQuickEntryExpanded = false },
                    onSubmit = { title, amt, cat, isBiz, date, loc, bytes, name, mime ->
                        viewModel.submitQuickEntry(title, amt, cat, isBiz, date, loc, bytes, name, mime)
                    }
                )
            }
        }
    }

    // 4. Calendar Widget
    if (events.isNotEmpty()) {
        CalendarWidget(events = events.take(2), onClick = onNavigateToOrganizer)
    }
}

@Composable
fun FinanceDashboardCard(kpis: FinanceKpis?, onClick: () -> Unit) {
    var showDetails by remember { mutableStateOf(false) }
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .clickable(onClick = onClick),
        colors = CardDefaults.cardColors(containerColor = GlassWhite10),
        shape = RoundedCornerShape(16.dp)
    ) {
        Column(modifier = Modifier.padding(14.dp)) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = "Finanzen",
                    fontSize = 14.sp,
                    fontWeight = FontWeight.Bold,
                    color = Slate50
                )
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(4.dp),
                    modifier = Modifier
                        .clip(RoundedCornerShape(6.dp))
                        .clickable { showDetails = !showDetails }
                        .padding(horizontal = 8.dp, vertical = 4.dp)
                ) {
                    Text(
                        text = if (showDetails) "Weniger Details" else "Mehr Details",
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

            Spacer(modifier = Modifier.height(4.dp))

            val available = kpis?.available ?: 0.0
            Text(
                text = String.format(Locale.GERMANY, "%,.2f €", available),
                fontSize = 24.sp,
                fontWeight = FontWeight.Bold,
                color = Gold
            )
            Text(
                text = "Verfügbares Guthaben (${kpis?.month_label ?: "Aktueller Monat"})",
                fontSize = 10.sp,
                color = Slate400
            )

            AnimatedVisibility(visible = showDetails) {
                Column {
                    Spacer(modifier = Modifier.height(12.dp))
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
            Text(title, fontSize = 11.sp, color = Slate400)
        }
        Text(
            text = String.format(Locale.GERMANY, "%,.2f €", value),
            fontSize = 14.sp,
            fontWeight = FontWeight.Bold,
            color = Slate50,
            modifier = Modifier.padding(top = 2.dp)
        )
    }
}

@Composable
fun RoutineWidget(routine: ManagementDayRoutine, onClick: () -> Unit) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .clickable(onClick = onClick),
        colors = CardDefaults.cardColors(containerColor = GlassWhite10),
        shape = RoundedCornerShape(16.dp)
    ) {
        Row(
            modifier = Modifier.padding(16.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Box(
                modifier = Modifier
                    .size(40.dp)
                    .clip(CircleShape)
                    .background(Color(0x22C5A059)),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    imageVector = Icons.Default.Schedule,
                    contentDescription = null,
                    tint = Gold,
                    modifier = Modifier.size(20.dp)
                )
            }
            Spacer(modifier = Modifier.width(16.dp))
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = "Routine: ${routine.title}",
                    fontSize = 14.sp,
                    fontWeight = FontWeight.Bold,
                    color = Slate50
                )
                Text(
                    text = "Startet um ${routine.start_time.take(5)} Uhr • ${routine.steps.size} Schritte",
                    fontSize = 12.sp,
                    color = Slate400
                )
            }
            Icon(
                imageVector = Icons.Default.CheckCircle,
                contentDescription = "Aktiv",
                tint = Gold,
                modifier = Modifier.size(20.dp)
            )
        }
    }
}

@Composable
fun CalendarWidget(events: List<CalendarEvent>, onClick: () -> Unit) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .clickable(onClick = onClick),
        colors = CardDefaults.cardColors(containerColor = GlassWhite10),
        shape = RoundedCornerShape(16.dp)
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(
                        imageVector = Icons.Default.CalendarToday,
                        contentDescription = null,
                        tint = Gold,
                        modifier = Modifier.size(18.dp)
                    )
                    Spacer(modifier = Modifier.width(8.dp))
                    Text("Anstehende Termine", fontSize = 15.sp, fontWeight = FontWeight.Bold, color = Slate50)
                }
                Icon(
                    imageVector = Icons.Default.ArrowForward,
                    contentDescription = "Kalender öffnen",
                    tint = Gold,
                    modifier = Modifier.size(16.dp)
                )
            }

            Spacer(modifier = Modifier.height(12.dp))

            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                events.forEach { ev ->
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Column(modifier = Modifier.weight(1f)) {
                            Text(
                                text = ev.title,
                                fontSize = 13.sp,
                                fontWeight = FontWeight.SemiBold,
                                color = Slate50
                            )
                            val startTimeText = if (ev.start.length >= 16) {
                                ev.start.substring(11, 16) + " Uhr"
                            } else {
                                ev.start
                            }
                            Text(
                                text = "Start: $startTimeText",
                                fontSize = 11.sp,
                                color = Slate400
                            )
                        }
                        if (ev.category.isNotEmpty()) {
                            Text(
                                text = ev.category.uppercase(Locale.getDefault()),
                                fontSize = 9.sp,
                                color = SpaceBlue,
                                fontWeight = FontWeight.Bold,
                                modifier = Modifier
                                    .border(1.dp, SpaceBlue, RoundedCornerShape(4.dp))
                                    .padding(horizontal = 4.dp, vertical = 2.dp)
                            )
                        }
                    }
                }
            }
        }
    }
}
