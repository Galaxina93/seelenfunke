package de.meinseelenfunke.app.ui.screens

import android.Manifest
import android.content.pm.PackageManager
import android.net.Uri
import android.widget.Toast
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.core.content.FileProvider
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.expandVertically
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.shrinkVertically
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.Image
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.res.painterResource
import de.meinseelenfunke.app.R
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
import androidx.compose.material.icons.filled.Inventory2
import androidx.compose.material.icons.filled.PhotoCamera
import androidx.compose.material.icons.filled.PhotoLibrary
import androidx.compose.material.icons.filled.Psychology
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import de.meinseelenfunke.app.data.repository.AiRepository
import de.meinseelenfunke.app.ui.components.BehaviorQuickReasonChipsRow
import de.meinseelenfunke.app.ui.components.BehaviorThumbActions
import de.meinseelenfunke.app.util.AgDirectInboxManager
import de.meinseelenfunke.app.util.FalconCameraController
import de.meinseelenfunke.app.util.FunkiraLocalMemoryStore
import de.meinseelenfunke.app.util.FunkiraPackageStore
import de.meinseelenfunke.app.util.PendingCameraAction
import java.io.File
import androidx.compose.runtime.setValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import de.meinseelenfunke.app.ui.theme.Emerald500
import de.meinseelenfunke.app.ui.theme.Rose500
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
    val liveSubtitle by viewModel.liveSubtitle.collectAsState()
    val liveFeedbackState by viewModel.liveFeedbackState.collectAsState()
    val behaviorQuickChips by viewModel.behaviorQuickChips.collectAsState()
    val agents by viewModel.agents.collectAsState()
    val selectedAgent by viewModel.selectedAgent.collectAsState()

    val context = LocalContext.current
    val listState = rememberLazyListState()
    var showDetailedLogs by remember { mutableStateOf(false) }
    val clipboardManager = androidx.compose.ui.platform.LocalClipboardManager.current
    val coroutineScope = rememberCoroutineScope()

    val activeAgentName = selectedAgent?.name ?: "Funkira"
    val activeAgentColor = parseAgentColor(selectedAgent?.color)
    val draftPackages by FunkiraPackageStore.packagesFlow.collectAsState()
    val memoryCount by FunkiraLocalMemoryStore.memoryCountFlow.collectAsState()
    val stagedAttachment by AgDirectInboxManager.stagedAttachmentFlow.collectAsState()
    val totalPoolPhotos = draftPackages.sumOf { it.attachmentCount } + (if (stagedAttachment != null) 1 else 0)

    var pendingCameraUri by remember { mutableStateOf<Uri?>(null) }
    var pendingCameraAction by remember { mutableStateOf<PendingCameraAction?>(null) }

    val takePictureLauncher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.TakePicture()
    ) { success ->
        val uri = pendingCameraUri
        pendingCameraUri = null
        if (success && uri != null) {
            coroutineScope.launch {
                val photo = FalconCameraController.importPhotoFromUri(context, uri)
                if (photo != null) {
                    Toast.makeText(context, "✓ Foto für Funkira hinterlegt", Toast.LENGTH_SHORT).show()
                }
            }
        }
    }

    val launchCameraCapture: () -> Unit = {
        try {
            val dir = File(context.cacheDir, "camera_captures").apply { mkdirs() }
            val outFile = File(dir, "live_${System.currentTimeMillis()}.jpg")
            val uri = FileProvider.getUriForFile(context, "${context.packageName}.fileprovider", outFile)
            pendingCameraUri = uri
            takePictureLauncher.launch(uri)
        } catch (e: Throwable) {
            Toast.makeText(context, "Kamera nicht verfügbar.", Toast.LENGTH_SHORT).show()
        }
    }

    val cameraPermissionLauncher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.RequestPermission()
    ) { granted ->
        val action = pendingCameraAction
        pendingCameraAction = null
        if (granted) {
            if (action == null) {
                launchCameraCapture()
            } else {
                coroutineScope.launch {
                    when (action.action) {
                        "take_photo" -> FalconCameraController.captureHighResPhoto(context, action.lens)
                        "switch_camera" -> FalconCameraController.switchCameraAsync(context, action.lens)
                        else -> FalconCameraController.startVision(context, action.lens ?: "back")
                    }
                }
            }
        } else {
            Toast.makeText(context, "Kamera-Berechtigung erforderlich.", Toast.LENGTH_SHORT).show()
        }
    }

    val galleryPickerLauncher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.OpenMultipleDocuments()
    ) { uris: List<Uri> ->
        if (uris.isNotEmpty()) {
            coroutineScope.launch {
                var importedCount = 0
                for (u in uris.take(FalconCameraController.MAX_POOL_IMAGES + 1)) {
                    val media = FalconCameraController.importMediaFromUri(context, u)
                    if (media != null) importedCount++
                }
                if (importedCount > 0) {
                    Toast.makeText(context, "✓ $importedCount Medium/Medien für Funkira hinterlegt", Toast.LENGTH_SHORT).show()
                }
            }
        }
    }

    LaunchedEffect(Unit) {
        FunkiraPackageStore.hydrate(context)
        FunkiraLocalMemoryStore.hydrate(context)
        FalconCameraController.permissionRequestEvents.collect { req ->
            pendingCameraAction = req
            cameraPermissionLauncher.launch(Manifest.permission.CAMERA)
        }
    }

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

    val agItems by de.meinseelenfunke.app.util.AgDirectInboxManager.itemsFlow.collectAsState()

    // Keep screen awake during active hands-free Live-Call so Android never locks & drops the mic/WebSocket
    val hostView = androidx.compose.ui.platform.LocalView.current
    DisposableEffect(isConnected || isConnecting) {
        val prevKeepScreenOn = hostView.keepScreenOn
        if (isConnected || isConnecting) {
            hostView.keepScreenOn = true
        }
        onDispose {
            hostView.keepScreenOn = prevKeepScreenOn
        }
    }

    // Disconnect when leaving screen & run adaptive realtime Antigravity status sync
    DisposableEffect(Unit) {
        de.meinseelenfunke.app.util.AgDirectInboxManager.acquireRealtimeObserver(context)
        onDispose {
            de.meinseelenfunke.app.util.AgDirectInboxManager.releaseRealtimeObserver()
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
                                    if (agent.name.equals("Funkira", ignoreCase = true)) {
                                        Image(
                                            painter = painterResource(id = R.drawable.funkira_avatar),
                                            contentDescription = "Funkira",
                                            contentScale = ContentScale.Crop,
                                            modifier = Modifier
                                                .fillMaxSize()
                                                .clip(CircleShape)
                                        )
                                    } else {
                                        Text(
                                            text = agent.name.take(2).uppercase(),
                                            color = if (isSelected) Slate900 else Slate50,
                                            fontSize = 14.sp,
                                            fontWeight = FontWeight.Bold
                                        )
                                    }
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

            // Morphing Glow Visualizer Orb & Live Subtitle Banner
            Column(
                modifier = Modifier
                    .weight(1f)
                    .fillMaxWidth(),
                horizontalAlignment = Alignment.CenterHorizontally,
                verticalArrangement = Arrangement.Center
            ) {
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .weight(1f, fill = false)
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

                // Live Subtitle Banner
                val liveSubText = liveSubtitle?.trim().orEmpty()
                val canRateBannerSub = liveSubText.isNotEmpty() &&
                    !liveSubText.startsWith("Du:") &&
                    !AiRepository.isExcludedSupportAgent(activeAgentName)
                var showBannerReasonChips by remember(liveSubText) { mutableStateOf(false) }

                AnimatedVisibility(
                    visible = liveSubText.isNotEmpty(),
                    enter = fadeIn() + expandVertically(),
                    exit = fadeOut() + shrinkVertically(),
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = 20.dp, vertical = 8.dp)
                ) {
                    Column(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clip(RoundedCornerShape(16.dp))
                            .background(Slate900.copy(alpha = 0.85f))
                            .border(1.dp, activeAgentColor.copy(alpha = 0.4f), RoundedCornerShape(16.dp))
                            .padding(horizontal = 16.dp, vertical = 12.dp),
                        horizontalAlignment = Alignment.CenterHorizontally
                    ) {
                        Text(
                            text = liveSubText,
                            color = Slate50,
                            fontSize = 14.sp,
                            lineHeight = 20.sp,
                            textAlign = androidx.compose.ui.text.style.TextAlign.Center,
                            fontWeight = FontWeight.Medium
                        )
                        if (canRateBannerSub) {
                            Spacer(modifier = Modifier.height(6.dp))
                            BehaviorThumbActions(
                                reaction = liveFeedbackState,
                                mutedTint = Slate400,
                                onPraise = {
                                    showBannerReasonChips = false
                                    viewModel.submitLiveTurnFeedback("praise", null)
                                },
                                onCriticismToggle = {
                                    val opening = !showBannerReasonChips
                                    showBannerReasonChips = opening
                                    if (opening && liveFeedbackState?.polarity != "criticism" && liveFeedbackState?.polarity != "correction") {
                                        viewModel.submitLiveTurnFeedback("criticism", null)
                                    }
                                }
                            )
                            BehaviorQuickReasonChipsRow(
                                visible = showBannerReasonChips,
                                chips = behaviorQuickChips,
                                selectedReasonCode = liveFeedbackState?.reasonCode,
                                darkHudSurface = true,
                                onSelectChip = { chip ->
                                    viewModel.submitLiveTurnFeedback(chip.polarity ?: "criticism", chip.code)
                                    showBannerReasonChips = false
                                }
                            )
                        }
                    }
                }

                // Live Antigravity Ticket Status Bar directly inside the Live-Call stream
                AgLiveTicketStatusBar(
                    items = agItems,
                    onGrantGo = { item ->
                        coroutineScope.launch {
                            val ok = de.meinseelenfunke.app.util.AgDirectInboxManager.resolveBlockade(
                                context = context,
                                itemId = item.id,
                                decision = "approved",
                                answerText = "Go erteilt"
                            )
                            Toast.makeText(
                                context,
                                if (ok) "✓ Go an Antigravity übermittelt!" else "Offline gespeichert",
                                Toast.LENGTH_SHORT
                            ).show()
                        }
                    },
                    onReplyAndApprove = { item, answer ->
                        coroutineScope.launch {
                            val ok = de.meinseelenfunke.app.util.AgDirectInboxManager.resolveBlockade(
                                context = context,
                                itemId = item.id,
                                decision = "info_provided",
                                answerText = answer
                            )
                            Toast.makeText(
                                context,
                                if (ok) "✓ Antwort & Freigabe übermittelt!" else "Offline gespeichert",
                                Toast.LENGTH_SHORT
                            ).show()
                        }
                    }
                )

                // Compact Sammelpaket & Memory indicator pill
                if (draftPackages.isNotEmpty() || memoryCount > 0) {
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(horizontal = 20.dp, vertical = 4.dp),
                        horizontalArrangement = Arrangement.Center,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        if (draftPackages.isNotEmpty()) {
                            val topPkg = draftPackages.first()
                            Row(
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.spacedBy(8.dp),
                                modifier = Modifier
                                    .clip(RoundedCornerShape(14.dp))
                                    .background(Slate900.copy(alpha = 0.9f))
                                    .border(1.dp, Gold.copy(alpha = 0.7f), RoundedCornerShape(14.dp))
                                    .padding(horizontal = 10.dp, vertical = 6.dp)
                            ) {
                                Icon(Icons.Default.Inventory2, contentDescription = null, tint = Gold, modifier = Modifier.size(15.dp))
                                Text(
                                    text = "${topPkg.title.take(26)} (${topPkg.itemCount})",
                                    color = Slate50,
                                    fontSize = 11.5.sp,
                                    fontWeight = FontWeight.Bold
                                )
                                Button(
                                    onClick = {
                                        coroutineScope.launch {
                                            val res = FunkiraPackageStore.releasePackage(context, topPkg.uuid)
                                            Toast.makeText(
                                                context,
                                                if (res.sentLive) "✓ Paket gesendet" else "Offline gespeichert",
                                                Toast.LENGTH_SHORT
                                            ).show()
                                        }
                                    },
                                    colors = ButtonDefaults.buttonColors(containerColor = Gold, contentColor = SpaceBlack),
                                    shape = RoundedCornerShape(8.dp),
                                    contentPadding = androidx.compose.foundation.layout.PaddingValues(horizontal = 8.dp, vertical = 2.dp),
                                    modifier = Modifier.height(26.dp)
                                ) {
                                    Text("Senden", fontSize = 11.sp, fontWeight = FontWeight.Bold)
                                }
                            }
                        } else if (memoryCount > 0) {
                            Row(
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.spacedBy(5.dp),
                                modifier = Modifier
                                    .clip(RoundedCornerShape(50))
                                    .background(GlassWhite10)
                                    .padding(horizontal = 10.dp, vertical = 4.dp)
                            ) {
                                Icon(Icons.Default.Psychology, contentDescription = null, tint = Gold, modifier = Modifier.size(13.dp))
                                Text(
                                    text = "Gedächtnis ($memoryCount)",
                                    color = Slate400,
                                    fontSize = 11.sp,
                                    fontWeight = FontWeight.SemiBold
                                )
                            }
                        }
                    }
                }
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

                Spacer(modifier = Modifier.width(20.dp))

                // Hang Up Button
                var lastHangupTapMs by remember { mutableStateOf(0L) }
                IconButton(
                    onClick = {
                        val nowTapMs = android.os.SystemClock.uptimeMillis()
                        if (!FunkiraLiveViewModel.shouldAllowCallButtonTap(lastHangupTapMs, nowTapMs)) {
                            return@IconButton
                        }
                        lastHangupTapMs = nowTapMs
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

                Spacer(modifier = Modifier.width(20.dp))

                // Camera Photo Capture Button (with badge)
                Box {
                    IconButton(
                        onClick = {
                            pendingCameraAction = null
                            val hasCam = ContextCompat.checkSelfPermission(
                                context,
                                Manifest.permission.CAMERA
                            ) == PackageManager.PERMISSION_GRANTED
                            if (hasCam) launchCameraCapture() else cameraPermissionLauncher.launch(Manifest.permission.CAMERA)
                        },
                        modifier = Modifier
                            .size(52.dp)
                            .clip(CircleShape)
                            .background(GlassWhite10)
                            .border(1.dp, Gold.copy(alpha = 0.65f), CircleShape)
                    ) {
                        Icon(
                            imageVector = Icons.Default.PhotoCamera,
                            contentDescription = "Foto aufnehmen",
                            tint = Gold,
                            modifier = Modifier.size(22.dp)
                        )
                    }
                    if (totalPoolPhotos > 0) {
                        Box(
                            contentAlignment = Alignment.Center,
                            modifier = Modifier
                                .align(Alignment.TopEnd)
                                .size(18.dp)
                                .clip(CircleShape)
                                .background(Gold)
                        ) {
                            Text(
                                text = totalPoolPhotos.toString(),
                                color = SpaceBlack,
                                fontSize = 10.sp,
                                fontWeight = FontWeight.ExtraBold
                            )
                        }
                    }
                }

                Spacer(modifier = Modifier.width(10.dp))

                // Gallery Picker Button (Multi-Photo & Short Video Pool)
                IconButton(
                    onClick = { galleryPickerLauncher.launch(arrayOf("image/*", "video/*")) },
                    modifier = Modifier
                        .size(46.dp)
                        .clip(CircleShape)
                        .background(GlassWhite10)
                        .border(1.dp, GlassWhite20, CircleShape)
                ) {
                    Icon(
                        imageVector = Icons.Default.PhotoLibrary,
                        contentDescription = "Bild aus Galerie",
                        tint = Slate50,
                        modifier = Modifier.size(20.dp)
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
                            val textColor = when {
                                log.contains("Fehler", ignoreCase = true) || log.contains("Getrennt") || log.contains("failed", ignoreCase = true) || log.contains("Verbindung geschlossen") -> Rose500
                                log.startsWith("Du:") -> Color(0xFF60A5FA)
                                log.startsWith("Funkira:") || log.startsWith("Funki:") || log.contains("[Transkript gesichert]") -> Emerald500
                                log.contains("[Aktion]") || log.contains("[Aktion-Ergebnis]") -> Gold
                                log.contains("[WS-Setup]") || log.contains("[System]") -> Color(0xFF38BDF8)
                                else -> Slate50
                            }
                            Text(
                                text = log,
                                color = textColor,
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
