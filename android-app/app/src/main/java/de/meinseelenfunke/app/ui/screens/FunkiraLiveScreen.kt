package de.meinseelenfunke.app.ui.screens

import android.Manifest
import android.content.pm.PackageManager
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.ui.graphics.Path
import kotlin.math.cos
import kotlin.math.sin
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
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CallEnd
import androidx.compose.material.icons.filled.Mic
import androidx.compose.material.icons.filled.MicOff
import androidx.compose.material.icons.filled.VolumeUp
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.rememberCoroutineScope
import kotlinx.coroutines.launch
import kotlinx.coroutines.delay
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.scale
import androidx.compose.ui.draw.drawWithContent
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.BlendMode
import androidx.compose.ui.graphics.CompositingStrategy
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.core.content.ContextCompat
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.compose.material.icons.filled.List
import androidx.compose.material.icons.filled.Delete
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.ContentCopy
import androidx.compose.runtime.setValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import de.meinseelenfunke.app.ui.theme.Copper
import de.meinseelenfunke.app.ui.theme.SpaceBlue
import de.meinseelenfunke.app.ui.theme.SpaceBlack
import de.meinseelenfunke.app.ui.theme.GlassWhite10
import de.meinseelenfunke.app.ui.theme.GlassWhite20
import de.meinseelenfunke.app.ui.theme.Slate400
import de.meinseelenfunke.app.ui.theme.Slate50
import de.meinseelenfunke.app.ui.theme.Slate900
import de.meinseelenfunke.app.ui.theme.Slate800
import de.meinseelenfunke.app.ui.theme.Gold

@Composable
fun FunkiraLiveScreen(
    onBack: () -> Unit,
    viewModel: FunkiraLiveViewModel = viewModel()
) {
    val isConnecting by viewModel.isConnecting.collectAsState()
    val isConnected by viewModel.isConnected.collectAsState()
    val isRecording by viewModel.isRecording.collectAsState()
    val error by viewModel.error.collectAsState()
    val amplitude by viewModel.voiceAmplitude.collectAsState()
    val liveLogs by viewModel.liveLogs.collectAsState()
    val agents by viewModel.agents.collectAsState()
    val selectedAgent by viewModel.selectedAgent.collectAsState()

    val context = LocalContext.current
    val listState = rememberLazyListState()
    var showDetailedLogs by remember { mutableStateOf(false) }
    val clipboardManager = androidx.compose.ui.platform.LocalClipboardManager.current
    val coroutineScope = rememberCoroutineScope()

    val activeAgentName = selectedAgent?.name ?: "Funkira"
    val activeAgentColor = parseAgentColor(selectedAgent?.color)

    // Request permissions launcher
    val permissionLauncher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.RequestPermission()
    ) { isGranted ->
        if (isGranted) {
            viewModel.startLiveChat()
        } else {
            viewModel.addLog("Fehler: Mikrofon-Berechtigung verweigert.")
        }
    }

    // Auto-connect and check permissions on start
    LaunchedEffect(Unit) {
        val permissionCheck = ContextCompat.checkSelfPermission(context, Manifest.permission.RECORD_AUDIO)
        if (permissionCheck == PackageManager.PERMISSION_GRANTED) {
            viewModel.startLiveChat()
        } else {
            permissionLauncher.launch(Manifest.permission.RECORD_AUDIO)
        }
    }

    // Keep logs scrolled to the bottom
    LaunchedEffect(liveLogs.size) {
        if (liveLogs.isNotEmpty()) {
            listState.animateScrollToItem(liveLogs.size - 1)
        }
    }

    val shouldCloseScreen by viewModel.shouldCloseScreen.collectAsState()
    LaunchedEffect(shouldCloseScreen) {
        if (shouldCloseScreen) {
            onBack()
        }
    }

    // Disconnect when leaving screen
    DisposableEffect(Unit) {
        onDispose {
            viewModel.disconnectLiveChat()
        }
    }

    // Auto-reconnect when returning to foreground
    val lifecycleOwner = androidx.compose.ui.platform.LocalLifecycleOwner.current
    DisposableEffect(lifecycleOwner) {
        val observer = androidx.lifecycle.LifecycleEventObserver { _, event ->
            if (event == androidx.lifecycle.Lifecycle.Event.ON_RESUME) {
                val permissionCheck = ContextCompat.checkSelfPermission(context, Manifest.permission.RECORD_AUDIO)
                if (permissionCheck == PackageManager.PERMISSION_GRANTED && !isConnected && !isConnecting) {
                    viewModel.startLiveChat()
                }
            }
        }
        lifecycleOwner.lifecycle.addObserver(observer)
        onDispose {
            lifecycleOwner.lifecycle.removeObserver(observer)
        }
    }

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(
                Brush.verticalGradient(
                    colors = listOf(SpaceBlack, Slate900)
                )
            )
            .padding(top = 16.dp, start = 16.dp, end = 16.dp, bottom = 24.dp)
    ) {
        Column(
            modifier = Modifier.fillMaxSize(),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.SpaceBetween
        ) {
            // Screen Header
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(top = 40.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                IconButton(
                    onClick = {
                        viewModel.disconnectLiveChat()
                        onBack()
                    },
                    modifier = Modifier
                        .size(40.dp)
                        .clip(CircleShape)
                        .background(GlassWhite10)
                ) {
                    Icon(
                        imageVector = androidx.compose.material.icons.Icons.Default.ArrowBack,
                        contentDescription = "Zurück",
                        tint = Slate50
                    )
                }

                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Text(
                        text = "KI LIVECALL",
                        fontSize = 20.sp,
                        fontWeight = FontWeight.Bold,
                        color = activeAgentColor,
                        letterSpacing = 3.sp
                    )
                    Text(
                        text = if (isConnected) "VERBUNDEN" else if (isConnecting) "VERBINDEN..." else "INAKTIV (Tippe Orb zum Starten)",
                        fontSize = 11.sp,
                        fontWeight = FontWeight.Bold,
                        color = if (isConnected) Color.Green else if (isConnecting) activeAgentColor else Slate400,
                        letterSpacing = 1.sp,
                        modifier = Modifier.padding(top = 4.dp)
                    )
                }

                IconButton(
                    onClick = { showDetailedLogs = true },
                    modifier = Modifier
                        .size(40.dp)
                        .clip(CircleShape)
                        .background(GlassWhite10)
                ) {
                    Icon(
                        imageVector = androidx.compose.material.icons.Icons.Default.List,
                        contentDescription = "Logs anzeigen",
                        tint = activeAgentColor
                    )
                }
            }

            // Horizontal Agent Selector
            if (agents.isNotEmpty()) {
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .graphicsLayer(compositingStrategy = CompositingStrategy.Offscreen)
                        .drawWithContent {
                            drawContent()
                            // Fade out left edge
                            drawRect(
                                brush = Brush.horizontalGradient(
                                    colors = listOf(Color.Transparent, Color.Black),
                                    startX = 0f,
                                    endX = 24.dp.toPx()
                                ),
                                blendMode = BlendMode.DstIn
                            )
                            // Fade out right edge
                            drawRect(
                                brush = Brush.horizontalGradient(
                                    colors = listOf(Color.Black, Color.Transparent),
                                    startX = size.width - 24.dp.toPx(),
                                    endX = size.width
                                ),
                                blendMode = BlendMode.DstIn
                            )
                        }
                ) {
                    LazyRow(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(vertical = 12.dp),
                        horizontalArrangement = Arrangement.spacedBy(14.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        items(agents) { agent ->
                            val isSelected = agent.id == selectedAgent?.id
                            val agentColor = parseAgentColor(agent.color)

                            Column(
                                horizontalAlignment = Alignment.CenterHorizontally,
                                modifier = Modifier
                                    .clickable { viewModel.selectAgent(agent) }
                            ) {
                                Box(
                                    modifier = Modifier
                                        .size(48.dp)
                                        .clip(CircleShape)
                                        .background(if (isSelected) agentColor else GlassWhite10)
                                        .border(
                                            width = if (isSelected) 2.dp else 1.dp,
                                            color = if (isSelected) Color.White else agentColor.copy(alpha = 0.5f),
                                            shape = CircleShape
                                        ),
                                    contentAlignment = Alignment.Center
                                ) {
                                    Text(
                                        text = agent.name.take(2).uppercase(),
                                        color = if (isSelected) Slate900 else Slate50,
                                        fontSize = 14.sp,
                                        fontWeight = FontWeight.Bold
                                    )
                                }

                                Spacer(modifier = Modifier.height(4.dp))

                                Text(
                                    text = agent.name,
                                    color = if (isSelected) Slate50 else Slate400,
                                    fontSize = 10.sp,
                                    fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Normal
                                )
                            }
                        }
                    }
                }
            }

            // Morphing Glow Visualizer Orb
            Box(
                modifier = Modifier
                    .weight(1f)
                    .fillMaxWidth()
                    .clickable(
                        enabled = !isConnected && !isConnecting,
                        onClick = {
                            viewModel.startLiveChat()
                        }
                    ),
                contentAlignment = Alignment.Center
            ) {
                MorphingOrb(
                    amplitude = amplitude,
                    agentColor = activeAgentColor,
                    isConnected = isConnected
                )
            }

            // Call Actions Control Bar
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(bottom = 32.dp),
                horizontalArrangement = Arrangement.Center,
                verticalAlignment = Alignment.CenterVertically
            ) {
                // Mute / Unmute Button
                IconButton(
                    onClick = { viewModel.toggleMute() },
                    modifier = Modifier
                        .size(56.dp)
                        .clip(CircleShape)
                        .background(if (isRecording) GlassWhite10 else Color.Red.copy(alpha = 0.2f))
                        .border(1.dp, if (isRecording) activeAgentColor else Color.Red, CircleShape)
                ) {
                    Icon(
                        imageVector = if (isRecording) Icons.Default.Mic else Icons.Default.MicOff,
                        contentDescription = "Mute",
                        tint = if (isRecording) activeAgentColor else Color.Red,
                        modifier = Modifier.size(24.dp)
                    )
                }

                Spacer(modifier = Modifier.width(32.dp))

                // Hang Up Button
                IconButton(
                    onClick = {
                        de.meinseelenfunke.app.util.SoundManager.playSound(de.meinseelenfunke.app.R.raw.click_file_in_project_brain)
                        coroutineScope.launch {
                            delay(500)
                            viewModel.disconnectLiveChat()
                            onBack()
                        }
                    },
                    modifier = Modifier
                        .size(64.dp)
                        .clip(CircleShape)
                        .background(Color.Red)
                ) {
                    Icon(
                        imageVector = Icons.Default.CallEnd,
                        contentDescription = "Auflegen",
                        tint = Slate50,
                        modifier = Modifier.size(28.dp)
                    )
                }
            }
        }
    }

    if (showDetailedLogs) {
        androidx.compose.ui.window.Dialog(
            onDismissRequest = { showDetailedLogs = false }
        ) {
            Card(
                modifier = Modifier
                    .fillMaxWidth()
                    .height(550.dp)
                    .padding(16.dp)
                    .border(1.dp, GlassWhite20, RoundedCornerShape(16.dp)),
                colors = CardDefaults.cardColors(containerColor = Slate900),
                shape = RoundedCornerShape(16.dp)
            ) {
                Column(
                    modifier = Modifier
                        .fillMaxSize()
                        .padding(16.dp)
                ) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text(
                            text = "Detaillierte Live Logs",
                            fontSize = 16.sp,
                            fontWeight = FontWeight.Bold,
                            color = Gold
                        )
                        Row {
                            IconButton(onClick = {
                                val logsText = liveLogs.joinToString("\n")
                                clipboardManager.setText(androidx.compose.ui.text.AnnotatedString(logsText))
                            }) {
                                Icon(
                                    imageVector = androidx.compose.material.icons.Icons.Default.ContentCopy,
                                    contentDescription = "Logs kopieren",
                                    tint = Gold
                                )
                            }
                            IconButton(onClick = { viewModel.clearLogs() }) {
                                Icon(
                                    imageVector = androidx.compose.material.icons.Icons.Default.Delete,
                                    contentDescription = "Logs löschen",
                                    tint = Color.Red
                                )
                            }
                            IconButton(onClick = { showDetailedLogs = false }) {
                                Icon(
                                    imageVector = androidx.compose.material.icons.Icons.Default.Close,
                                    contentDescription = "Schließen",
                                    tint = Slate50
                                )
                            }
                        }
                    }

                    Spacer(modifier = Modifier.height(10.dp))

                    val detailedListState = rememberLazyListState()
                    LaunchedEffect(liveLogs.size) {
                        if (liveLogs.isNotEmpty()) {
                            detailedListState.animateScrollToItem(liveLogs.size - 1)
                        }
                    }

                    LazyColumn(
                        state = detailedListState,
                        modifier = Modifier
                            .weight(1f)
                            .fillMaxWidth()
                            .background(SpaceBlack, RoundedCornerShape(8.dp))
                            .padding(8.dp),
                        verticalArrangement = Arrangement.spacedBy(4.dp)
                    ) {
                        items(liveLogs) { log ->
                            Text(
                                text = log,
                                color = if (log.contains("Fehler") || log.contains("Getrennt") || log.contains("failed") || log.contains("geschlossen")) Color.Red
                                        else if (log.contains("[Empfangen]")) Color.Cyan
                                        else if (log.contains("[WS-Setup]")) Gold
                                        else Slate50,
                                fontSize = 11.sp,
                                modifier = Modifier.fillMaxWidth()
                            )
                        }
                    }
                }
            }
        }
    }
}

@Composable
fun MorphingOrb(
    amplitude: Float,
    agentColor: Color,
    isConnected: Boolean,
    modifier: Modifier = Modifier
) {
    val infiniteTransition = rememberInfiniteTransition()
    
    // Animate phases for morphing waves
    val phase1 by infiniteTransition.animateFloat(
        initialValue = 0f,
        targetValue = (2 * Math.PI).toFloat(),
        animationSpec = infiniteRepeatable(
            animation = tween(4000, easing = LinearEasing),
            repeatMode = RepeatMode.Restart
        )
    )
    val phase2 by infiniteTransition.animateFloat(
        initialValue = 0f,
        targetValue = (2 * Math.PI).toFloat(),
        animationSpec = infiniteRepeatable(
            animation = tween(6000, easing = LinearEasing),
            repeatMode = RepeatMode.Restart
        )
    )
    val phase3 by infiniteTransition.animateFloat(
        initialValue = 0f,
        targetValue = (-2 * Math.PI).toFloat(),
        animationSpec = infiniteRepeatable(
            animation = tween(3000, easing = LinearEasing),
            repeatMode = RepeatMode.Restart
        )
    )

    // Dynamic wave amplitude scale based on voice amplitude
    val audioFactor = amplitude * 50f
    
    Box(
        modifier = modifier.size(260.dp),
        contentAlignment = Alignment.Center
    ) {
        Canvas(modifier = Modifier.fillMaxSize()) {
            val centerX = size.width / 2
            val centerY = size.height / 2
            val baseRadius = size.width * 0.28f

            // Layer 1: Background Glow
            val path1 = Path().apply {
                val waveAmp = 15f + audioFactor * 0.8f
                for (i in 0..100) {
                    val angle = i * (2 * Math.PI / 100)
                    val r = baseRadius * 1.3f + waveAmp * (
                        cos(angle * 2 + phase1).toFloat() * 0.5f +
                        sin(angle * 3 - phase2).toFloat() * 0.5f
                    )
                    val x = centerX + r * cos(angle).toFloat()
                    val y = centerY + r * sin(angle).toFloat()
                    if (i == 0) moveTo(x, y) else lineTo(x, y)
                }
                close()
            }
            drawPath(
                path = path1,
                brush = Brush.radialGradient(
                    colors = listOf(agentColor.copy(alpha = 0.15f), agentColor.copy(alpha = 0.05f), Color.Transparent),
                    center = center
                )
            )

            // Layer 2: Mid-ground Aura
            val path2 = Path().apply {
                val waveAmp = 12f + audioFactor * 0.6f
                for (i in 0..100) {
                    val angle = i * (2 * Math.PI / 100)
                    val r = baseRadius * 1.05f + waveAmp * (
                        sin(angle * 4 - phase3).toFloat() * 0.6f +
                        cos(angle * 2 + phase1).toFloat() * 0.4f
                    )
                    val x = centerX + r * cos(angle).toFloat()
                    val y = centerY + r * sin(angle).toFloat()
                    if (i == 0) moveTo(x, y) else lineTo(x, y)
                }
                close()
            }
            drawPath(
                path = path2,
                brush = Brush.linearGradient(
                    colors = listOf(agentColor.copy(alpha = 0.4f), agentColor.copy(alpha = 0.1f)),
                    start = androidx.compose.ui.geometry.Offset(0f, 0f),
                    end = androidx.compose.ui.geometry.Offset(size.width, size.height)
                )
            )

            // Layer 3: Core Liquid Blob
            val path3 = Path().apply {
                val waveAmp = 8f + audioFactor * 0.4f
                for (i in 0..100) {
                    val angle = i * (2 * Math.PI / 100)
                    val r = baseRadius * 0.85f + waveAmp * (
                        cos(angle * 3 + phase2).toFloat() * 0.5f +
                        sin(angle * 5 - phase1).toFloat() * 0.5f
                    )
                    val x = centerX + r * cos(angle).toFloat()
                    val y = centerY + r * sin(angle).toFloat()
                    if (i == 0) moveTo(x, y) else lineTo(x, y)
                }
                close()
            }
            drawPath(
                path = path3,
                brush = Brush.radialGradient(
                    colors = listOf(agentColor.copy(alpha = 0.9f), agentColor.copy(alpha = 0.5f), Color.Transparent),
                    center = center
                )
            )
        }

        Box(
            modifier = Modifier
                .size(64.dp)
                .clip(CircleShape)
                .background(Color.White.copy(alpha = 0.1f)),
            contentAlignment = Alignment.Center
        ) {
            Icon(
                imageVector = if (isConnected) Icons.Default.VolumeUp else Icons.Default.Mic,
                contentDescription = null,
                tint = SpaceBlack,
                modifier = Modifier.size(28.dp)
            )
        }
    }
}
