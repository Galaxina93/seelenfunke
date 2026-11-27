package de.meinseelenfunke.app.ui.screens

import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.res.painterResource
import de.meinseelenfunke.app.R
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.graphics.CompositingStrategy
import androidx.compose.ui.graphics.BlendMode
import androidx.compose.foundation.layout.PaddingValues
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
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Bolt
import androidx.compose.material.icons.filled.Check
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.ExitToApp
import androidx.compose.material.icons.filled.ExpandLess
import androidx.compose.material.icons.filled.ExpandMore
import androidx.compose.material.icons.filled.HourglassEmpty
import androidx.compose.material.icons.filled.Image
import androidx.compose.material.icons.filled.PhotoCamera
import androidx.compose.material.icons.filled.Send
import androidx.compose.material.icons.filled.PushPin
import androidx.compose.material.icons.rounded.CheckCircle
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.style.TextOverflow
import android.Manifest
import android.content.Context
import android.content.pm.PackageManager
import android.net.Uri
import android.provider.OpenableColumns
import android.util.Base64
import android.widget.Toast
import androidx.core.content.ContextCompat
import androidx.core.content.FileProvider
import java.io.File
import de.meinseelenfunke.app.ui.components.formatAgTimestamp
import de.meinseelenfunke.app.util.AgDirectDisplayItem
import de.meinseelenfunke.app.util.AgDirectInboxManager
import de.meinseelenfunke.app.util.AgDirectStatus
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.runBlocking
import java.io.InputStream
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TopAppBar
import androidx.compose.material3.TopAppBarDefaults
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.drawWithContent
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.viewmodel.compose.viewModel
import de.meinseelenfunke.app.data.api.BehaviorQuickChipDto
import de.meinseelenfunke.app.data.api.ChatMessage
import de.meinseelenfunke.app.data.repository.AiRepository
import de.meinseelenfunke.app.ui.components.BehaviorQuickReasonChipsRow
import de.meinseelenfunke.app.ui.components.BehaviorThumbActions
import de.meinseelenfunke.app.ui.components.FunkiraBehaviorProposalBar
import de.meinseelenfunke.app.ui.theme.AppTheme
import de.meinseelenfunke.app.ui.theme.Cyan500
import de.meinseelenfunke.app.ui.theme.Emerald500
import de.meinseelenfunke.app.ui.theme.Gold
import de.meinseelenfunke.app.ui.theme.Rose500
import de.meinseelenfunke.app.ui.theme.SpaceBlack

fun parseAgentColor(colorName: String?): Color {
    return when (colorName) {
        "sky-500", "cyan-500" -> Cyan500
        "emerald-500", "teal-500" -> Emerald500
        "indigo-500", "blue-500" -> Color(0xFF6366F1)
        "purple-500" -> Color(0xFFA855F7)
        "amber-500", "yellow-500" -> Color(0xFFF59E0B)
        "red-500" -> Rose500
        "orange-500" -> Color(0xFFF97316)
        else -> Cyan500
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ChatScreen(
    onLogout: () -> Unit,
    viewModel: ChatViewModel = viewModel()
) {
    val messages by viewModel.messages.collectAsState()
    val isLoading by viewModel.isLoading.collectAsState()
    val error by viewModel.error.collectAsState()
    val canRetryLastMessage by viewModel.canRetryLastMessage.collectAsState()
    val agents by viewModel.agents.collectAsState()
    val selectedAgent by viewModel.selectedAgent.collectAsState()
    val messageReactions by viewModel.messageReactions.collectAsState()
    val behaviorQuickChips by viewModel.behaviorQuickChips.collectAsState()
    val pendingProposals by viewModel.pendingProposals.collectAsState()

    var textInput by remember { mutableStateOf("") }
    val listState = rememberLazyListState()

    val activeAgentName = selectedAgent?.name ?: "Funkira"
    val activeAgentColor = parseAgentColor(selectedAgent?.color)

    val context = LocalContext.current
    val coroutineScope = rememberCoroutineScope()
    val agItems by AgDirectInboxManager.itemsFlow.collectAsState()

    DisposableEffect(Unit) {
        AgDirectInboxManager.acquireRealtimeObserver(context)
        onDispose {
            AgDirectInboxManager.releaseRealtimeObserver()
        }
    }

    // Auto-scroll to the bottom when new messages arrive
    LaunchedEffect(messages.size) {
        if (messages.isNotEmpty()) {
            listState.animateScrollToItem(messages.size - 1)
        }
    }

    Scaffold(
        // topBar has been removed to give Chat more space, agent description and title are removed
    ) { innerPadding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(innerPadding)
                .background(
                    Brush.verticalGradient(
                        colors = AppTheme.colors.bgGradient
                    )
                )
        ) {
            // Horizontal Agent Selector (hidden when Funkira is the sole executive chat contact)
            if (agents.size > 1) {
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
                                        .background(if (isSelected) agentColor else AppTheme.colors.glassSurface)
                                        .border(
                                            width = if (isSelected) 2.dp else 1.dp,
                                            color = if (isSelected) Gold else agentColor.copy(alpha = 0.5f),
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
                                            color = if (isSelected) SpaceBlack else AppTheme.colors.textPrimary,
                                            fontSize = 14.sp,
                                            fontWeight = FontWeight.Bold
                                        )
                                    }
                                }

                                Spacer(modifier = Modifier.height(4.dp))

                                Text(
                                    text = agent.name,
                                    color = if (isSelected) Gold else AppTheme.colors.textMuted,
                                    fontSize = 10.sp,
                                    fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Normal
                                )
                            }
                        }
                    }
                }
            }

            // Echtzeit-Statusleiste / Karte für Antigravity-Tickets
            AgLiveTicketStatusBar(
                items = agItems,
                onGrantGo = { item ->
                    coroutineScope.launch {
                        val ok = AgDirectInboxManager.resolveBlockade(
                            context = context,
                            itemId = item.id,
                            decision = "approved",
                            answerText = "Go erteilt"
                        )
                        Toast.makeText(
                            context,
                            if (ok) "✓ Go an Antigravity übermittelt!" else "Offline als Direktauftrag gespeichert",
                            Toast.LENGTH_SHORT
                        ).show()
                        AgDirectInboxManager.refreshFromServer(context)
                    }
                },
                onReplyAndApprove = { item, answer ->
                    coroutineScope.launch {
                        val ok = AgDirectInboxManager.resolveBlockade(
                            context = context,
                            itemId = item.id,
                            decision = "info_provided",
                            answerText = answer
                        )
                        Toast.makeText(
                            context,
                            if (ok) "✓ Antwort & Freigabe übermittelt!" else "Offline als Antwort gespeichert",
                            Toast.LENGTH_SHORT
                        ).show()
                        AgDirectInboxManager.refreshFromServer(context)
                    }
                }
            )

            // Kompakte 1-Klick-Freigabe fuer offene Funkira-Lernvorschlaege (niemals Funki)
            if (!AiRepository.isExcludedSupportAgent(activeAgentName)) {
                FunkiraBehaviorProposalBar(
                    proposals = pendingProposals,
                    onApprove = { proposal ->
                        viewModel.approveBehaviorProposal(proposal)
                        Toast.makeText(context, "✓ Regel aktiviert", Toast.LENGTH_SHORT).show()
                    },
                    onDismiss = { proposal ->
                        viewModel.dismissBehaviorProposal(proposal)
                    }
                )
            }

            // Chat message list
            LazyColumn(
                state = listState,
                modifier = Modifier
                    .weight(1f)
                    .fillMaxWidth()
                    .padding(horizontal = 16.dp),
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                item { Spacer(modifier = Modifier.height(16.dp)) }

                itemsIndexed(messages) { index, message ->
                    val messageKey = ChatViewModel.messageFeedbackKey(index, message)
                    val previousUserPrompt = messages
                        .take(index)
                        .lastOrNull { it.role == "user" }
                        ?.content
                    val canReact = message.role != "user" &&
                        !AiRepository.isExcludedSupportAgent(activeAgentName) &&
                        !message.content.trim().startsWith(ChatViewModel.AG_LIVE_STATUS_PREFIX) &&
                        !(index == 0 && message.memoryId == null && message.content.startsWith("Hallo Alina!"))

                    ChatMessageRow(
                        message = message,
                        activeAgentName = activeAgentName,
                        activeAgentColor = activeAgentColor,
                        canReact = canReact,
                        reaction = messageReactions[messageKey],
                        quickChips = behaviorQuickChips,
                        onFeedback = { polarity, reasonCode ->
                            viewModel.submitMessageFeedback(
                                messageKey = messageKey,
                                message = message,
                                previousUserPrompt = previousUserPrompt,
                                polarity = polarity,
                                reasonCode = reasonCode
                            )
                        }
                    )
                }

                if (isLoading) {
                    item {
                        Box(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(vertical = 8.dp),
                            contentAlignment = Alignment.CenterStart
                        ) {
                            Text(
                                text = "$activeAgentName tippt...",
                                fontSize = 13.sp,
                                color = Emerald500,
                                modifier = Modifier.padding(start = 12.dp)
                            )
                        }
                    }
                }

                error?.let { errText ->
                    item {
                        Row(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(vertical = 8.dp)
                                .clip(RoundedCornerShape(12.dp))
                                .background(AppTheme.colors.cardBackground)
                                .border(1.dp, Rose500.copy(alpha = 0.55f), RoundedCornerShape(12.dp))
                                .padding(horizontal = 12.dp, vertical = 10.dp),
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.SpaceBetween
                        ) {
                            Text(
                                text = errText,
                                color = Rose500,
                                fontSize = 13.sp,
                                lineHeight = 18.sp,
                                modifier = Modifier
                                    .weight(1f)
                                    .padding(end = if (canRetryLastMessage && !isLoading) 10.dp else 0.dp)
                            )
                            if (canRetryLastMessage && !isLoading) {
                                Button(
                                    onClick = { viewModel.retryLastMessage() },
                                    shape = RoundedCornerShape(8.dp),
                                    colors = ButtonDefaults.buttonColors(
                                        containerColor = Gold,
                                        contentColor = SpaceBlack
                                    ),
                                    contentPadding = PaddingValues(horizontal = 12.dp, vertical = 6.dp),
                                    modifier = Modifier.height(32.dp)
                                ) {
                                    Text(
                                        text = "Erneut senden",
                                        fontSize = 12.sp,
                                        fontWeight = FontWeight.Bold,
                                        color = SpaceBlack
                                    )
                                }
                            }
                        }
                    }
                }
            }

            // Attachment Preview Banner
            val selectedFile by viewModel.selectedFile.collectAsState()
            selectedFile?.let { file ->
                val isImage = file.mimeType?.startsWith("image/") == true
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .background(AppTheme.colors.cardBackground)
                        .padding(horizontal = 16.dp, vertical = 8.dp)
                        .border(1.dp, activeAgentColor.copy(alpha = 0.3f), RoundedCornerShape(8.dp))
                        .padding(8.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.SpaceBetween
                ) {
                    Row(
                        verticalAlignment = Alignment.CenterVertically,
                        modifier = Modifier.weight(1f)
                    ) {
                        Icon(
                            imageVector = if (isImage) Icons.Default.Image else Icons.Default.PushPin,
                            contentDescription = if (isImage) "Bild angehängt" else "Datei angehängt",
                            tint = activeAgentColor,
                            modifier = Modifier.size(20.dp)
                        )
                        Spacer(modifier = Modifier.width(8.dp))
                        Column {
                            Text(
                                text = file.name,
                                color = AppTheme.colors.textPrimary,
                                fontSize = 13.sp,
                                fontWeight = FontWeight.Bold,
                                maxLines = 1
                            )
                            Text(
                                text = "${file.mimeType} • ${(file.sizeBytes / 1024.0).toInt()} KB",
                                color = AppTheme.colors.textMuted,
                                fontSize = 11.sp
                            )
                        }
                    }
                    IconButton(
                        onClick = { viewModel.selectFile(null) },
                        modifier = Modifier.size(24.dp)
                    ) {
                        Icon(
                            imageVector = Icons.Default.Close,
                            contentDescription = "Entfernen",
                            tint = Rose500,
                            modifier = Modifier.size(16.dp)
                        )
                    }
                }
            }

            // Input Row
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .background(AppTheme.colors.cardBackground)
                    .border(1.dp, AppTheme.colors.cardBorder)
                    .padding(horizontal = 12.dp, vertical = 8.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                val context = LocalContext.current
                val filePickerLauncher = rememberLauncherForActivityResult(
                    contract = ActivityResultContracts.GetContent()
                ) { uri ->
                    uri?.let {
                        val file = getSelectedFileFromUri(context, it)
                        viewModel.selectFile(file)
                    }
                }

                val imagePickerLauncher = rememberLauncherForActivityResult(
                    contract = ActivityResultContracts.GetContent()
                ) { uri ->
                    uri?.let {
                        val file = getSelectedFileFromUri(context, it)
                        viewModel.selectFile(file)
                    }
                }

                var pendingChatCameraUri by remember { mutableStateOf<Uri?>(null) }
                val chatCameraLauncher = rememberLauncherForActivityResult(
                    contract = ActivityResultContracts.TakePicture()
                ) { success ->
                    val uri = pendingChatCameraUri
                    pendingChatCameraUri = null
                    if (success && uri != null) {
                        val file = getSelectedFileFromUri(context, uri)
                        viewModel.selectFile(file)
                    }
                }
                val launchChatCamera: () -> Unit = {
                    try {
                        val dir = File(context.cacheDir, "camera_captures").apply { mkdirs() }
                        val outFile = File(dir, "chat_cam_${System.currentTimeMillis()}.jpg")
                        val uri = FileProvider.getUriForFile(context, "${context.packageName}.fileprovider", outFile)
                        pendingChatCameraUri = uri
                        chatCameraLauncher.launch(uri)
                    } catch (_: Throwable) {
                        Toast.makeText(context, "Kamera nicht verfügbar.", Toast.LENGTH_SHORT).show()
                    }
                }
                val chatCameraPermLauncher = rememberLauncherForActivityResult(
                    contract = ActivityResultContracts.RequestPermission()
                ) { granted ->
                    if (granted) launchChatCamera() else Toast.makeText(context, "Kamera-Berechtigung erforderlich.", Toast.LENGTH_SHORT).show()
                }

                IconButton(
                    onClick = {
                        val hasCam = ContextCompat.checkSelfPermission(context, Manifest.permission.CAMERA) == PackageManager.PERMISSION_GRANTED
                        if (hasCam) launchChatCamera() else chatCameraPermLauncher.launch(Manifest.permission.CAMERA)
                    }
                ) {
                    Icon(
                        imageVector = Icons.Default.PhotoCamera,
                        contentDescription = "Foto aufnehmen",
                        tint = activeAgentColor
                    )
                }

                IconButton(
                    onClick = { imagePickerLauncher.launch("image/*") }
                ) {
                    Icon(
                        imageVector = Icons.Default.Image,
                        contentDescription = "Bild anhängen",
                        tint = activeAgentColor
                    )
                }

                IconButton(
                    onClick = { filePickerLauncher.launch("*/*") }
                ) {
                    Icon(
                        imageVector = Icons.Default.PushPin,
                        contentDescription = "Datei anhängen",
                        tint = activeAgentColor.copy(alpha = 0.85f)
                    )
                }

                OutlinedTextField(
                    value = textInput,
                    onValueChange = { textInput = it },
                    placeholder = { Text("Schreibe...", color = AppTheme.colors.textMuted) },
                    modifier = Modifier.weight(1f),
                    colors = OutlinedTextFieldDefaults.colors(
                        focusedBorderColor = activeAgentColor,
                        unfocusedBorderColor = AppTheme.colors.inputBorder,
                        focusedTextColor = AppTheme.colors.textPrimary,
                        unfocusedTextColor = AppTheme.colors.textPrimary,
                        focusedPlaceholderColor = AppTheme.colors.textMuted,
                        unfocusedPlaceholderColor = AppTheme.colors.textMuted
                    ),
                    maxLines = 4,
                    shape = RoundedCornerShape(24.dp)
                )

                Spacer(modifier = Modifier.width(8.dp))

                IconButton(
                    onClick = {
                        if (textInput.isNotBlank() || selectedFile != null) {
                            viewModel.sendMessage(textInput)
                            textInput = ""
                        }
                    },
                    modifier = Modifier
                        .background(activeAgentColor, shape = RoundedCornerShape(50))
                        .padding(8.dp)
                ) {
                    Icon(
                        imageVector = Icons.Default.Send,
                        contentDescription = "Senden",
                        tint = SpaceBlack
                    )
                }
            }
        }
    }
}

@Composable
fun AgLiveTicketStatusBar(
    items: List<AgDirectDisplayItem>,
    onGrantGo: (AgDirectDisplayItem) -> Unit,
    onReplyAndApprove: (AgDirectDisplayItem, String) -> Unit = { item, _ -> onGrantGo(item) },
    modifier: Modifier = Modifier
) {
    val goItems = remember(items) {
        items.filter { it.status == AgDirectStatus.AWAITING_GO }
    }
    val inProgressItems = remember(items) {
        items.filter {
            it.status == AgDirectStatus.QUEUED ||
                it.status == AgDirectStatus.PENDING ||
                it.status == AgDirectStatus.DELIVERED
        }
    }
    val completedItems = remember(items) {
        items.filter { it.status == AgDirectStatus.COMPLETED }
    }

    if (goItems.isEmpty() && inProgressItems.isEmpty() && completedItems.isEmpty()) {
        return
    }

    var isExpanded by remember { mutableStateOf(false) }

    Column(
        modifier = modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp, vertical = 4.dp)
            .clip(RoundedCornerShape(12.dp))
            .background(AppTheme.colors.cardBackground)
            .border(
                width = 1.dp,
                color = if (goItems.isNotEmpty()) Gold.copy(alpha = 0.65f) else AppTheme.colors.cardBorder,
                shape = RoundedCornerShape(12.dp)
            )
            .padding(horizontal = 12.dp, vertical = 8.dp),
        verticalArrangement = Arrangement.spacedBy(8.dp)
    ) {
        // Kompakte Status-Zeile mit 3 Pills
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .clickable { isExpanded = !isExpanded },
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.SpaceBetween
        ) {
            Row(
                modifier = Modifier
                    .weight(1f)
                    .padding(end = 6.dp)
                    .horizontalScroll(rememberScrollState()),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(6.dp)
            ) {
                // Pill 1: Go benötigt
                if (goItems.isNotEmpty()) {
                    Row(
                        modifier = Modifier
                            .clip(RoundedCornerShape(50))
                            .background(Gold.copy(alpha = 0.16f))
                            .border(1.dp, Gold.copy(alpha = 0.85f), RoundedCornerShape(50))
                            .padding(horizontal = 8.dp, vertical = 3.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(4.dp)
                    ) {
                        Icon(
                            imageVector = Icons.Default.Bolt,
                            contentDescription = null,
                            tint = Gold,
                            modifier = Modifier.size(13.dp)
                        )
                        Text(
                            text = "${goItems.size} Go benötigt",
                            fontSize = 11.sp,
                            fontWeight = FontWeight.Bold,
                            color = Gold,
                            maxLines = 1,
                            softWrap = false
                        )
                    }
                }

                // Pill 2: Aktiv in Arbeit (Echtzeit-Status)
                if (inProgressItems.isNotEmpty()) {
                    Row(
                        modifier = Modifier
                            .clip(RoundedCornerShape(50))
                            .background(Gold.copy(alpha = 0.14f))
                            .border(1.dp, Gold.copy(alpha = 0.55f), RoundedCornerShape(50))
                            .padding(horizontal = 8.dp, vertical = 3.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(5.dp)
                    ) {
                        Text(
                            text = "${inProgressItems.size} in Arbeit",
                            fontSize = 11.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = AppTheme.colors.textPrimary,
                            maxLines = 1,
                            softWrap = false
                        )
                        CircularProgressIndicator(
                            color = Gold,
                            strokeWidth = 1.6.dp,
                            modifier = Modifier.size(11.dp)
                        )
                    }
                }

                // Pill 3: Kürzlich fertig
                if (completedItems.isNotEmpty()) {
                    Row(
                        modifier = Modifier
                            .clip(RoundedCornerShape(50))
                            .background(Emerald500.copy(alpha = 0.14f))
                            .border(1.dp, Emerald500.copy(alpha = 0.55f), RoundedCornerShape(50))
                            .padding(horizontal = 8.dp, vertical = 3.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(4.dp)
                    ) {
                        Text(
                            text = "${completedItems.size} fertig",
                            fontSize = 11.sp,
                            fontWeight = FontWeight.SemiBold,
                            color = Emerald500,
                            maxLines = 1,
                            softWrap = false
                        )
                        Icon(
                            imageVector = Icons.Rounded.CheckCircle,
                            contentDescription = "Fertig",
                            tint = Emerald500,
                            modifier = Modifier.size(13.dp)
                        )
                    }
                }
            }

            Icon(
                imageVector = if (isExpanded) Icons.Default.ExpandLess else Icons.Default.ExpandMore,
                contentDescription = if (isExpanded) "Schließen" else "Öffnen",
                tint = if (isExpanded) Gold else AppTheme.colors.textMuted,
                modifier = Modifier.size(20.dp)
            )
        }

        // Schlanke Übersichtskarte beim Aufklappen
        AnimatedVisibility(visible = isExpanded) {
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .heightIn(max = 260.dp)
                    .verticalScroll(rememberScrollState())
                    .padding(top = 4.dp),
                verticalArrangement = Arrangement.spacedBy(10.dp)
            ) {
                // Gruppe 1: Go benötigt
                if (goItems.isNotEmpty()) {
                    Text(
                        text = "Go benötigt (${goItems.size})",
                        fontSize = 11.5.sp,
                        fontWeight = FontWeight.Bold,
                        color = Gold
                    )
                    goItems.forEach { item ->
                        Card(
                            modifier = Modifier.fillMaxWidth(),
                            colors = CardDefaults.cardColors(containerColor = AppTheme.colors.glassSurface),
                            border = BorderStroke(1.dp, Gold.copy(alpha = 0.40f)),
                            shape = RoundedCornerShape(10.dp)
                        ) {
                            Column(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .padding(9.dp),
                                verticalArrangement = Arrangement.spacedBy(6.dp)
                            ) {
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    val channelBadgeLabel = AgDirectInboxManager.resolveChannelEnum(item.channel).chipLabel
                                    Box(
                                        modifier = Modifier
                                            .clip(RoundedCornerShape(6.dp))
                                            .background(AppTheme.colors.cardBackground)
                                            .border(1.dp, Gold.copy(alpha = 0.40f), RoundedCornerShape(6.dp))
                                            .padding(horizontal = 6.dp, vertical = 2.dp)
                                    ) {
                                        Text(
                                            text = channelBadgeLabel,
                                            fontSize = 10.sp,
                                            fontWeight = FontWeight.SemiBold,
                                            color = AppTheme.colors.textPrimary
                                        )
                                    }
                                    formatAgTimestamp(item.createdAt)?.let { ts ->
                                        Text(
                                            text = ts,
                                            fontSize = 10.5.sp,
                                            color = AppTheme.colors.textMuted
                                        )
                                    }
                                }

                                Text(
                                    text = item.title,
                                    fontSize = 12.5.sp,
                                    fontWeight = FontWeight.SemiBold,
                                    color = AppTheme.colors.textPrimary,
                                    maxLines = 2,
                                    overflow = TextOverflow.Ellipsis
                                )

                                val questionOrBody = item.pendingQuestion?.takeIf { it.isNotBlank() }
                                    ?: item.agResponseSummary?.takeIf { it.isNotBlank() }
                                    ?: item.body.takeIf { it.isNotBlank() && it != item.title }
                                var inlineReply by remember(item.id) { mutableStateOf("") }

                                if (!questionOrBody.isNullOrBlank()) {
                                    Text(
                                        text = questionOrBody,
                                        fontSize = 11.5.sp,
                                        color = AppTheme.colors.textSecondary,
                                        maxLines = 3,
                                        overflow = TextOverflow.Ellipsis
                                    )
                                }

                                OutlinedTextField(
                                    value = inlineReply,
                                    onValueChange = { inlineReply = it },
                                    modifier = Modifier.fillMaxWidth(),
                                    placeholder = {
                                        Text(
                                            text = "Kurze Antwort...",
                                            fontSize = 11.5.sp,
                                            color = AppTheme.colors.textMuted
                                        )
                                    },
                                    maxLines = 2,
                                    shape = RoundedCornerShape(8.dp),
                                    colors = OutlinedTextFieldDefaults.colors(
                                        focusedBorderColor = Gold,
                                        unfocusedBorderColor = AppTheme.colors.inputBorder,
                                        cursorColor = Gold,
                                        focusedContainerColor = AppTheme.colors.inputBackground,
                                        unfocusedContainerColor = AppTheme.colors.inputBackground
                                    )
                                )

                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.spacedBy(6.dp)
                                ) {
                                    Button(
                                        onClick = { onGrantGo(item) },
                                        modifier = Modifier
                                            .weight(1f)
                                            .height(32.dp),
                                        shape = RoundedCornerShape(8.dp),
                                        colors = ButtonDefaults.buttonColors(
                                            containerColor = Gold,
                                            contentColor = SpaceBlack
                                        ),
                                        contentPadding = PaddingValues(horizontal = 8.dp, vertical = 2.dp)
                                    ) {
                                        Text(
                                            text = "⚡ Go",
                                            fontSize = 11.5.sp,
                                            fontWeight = FontWeight.Bold,
                                            color = SpaceBlack
                                        )
                                    }

                                    Button(
                                        onClick = {
                                            val clean = inlineReply.trim()
                                            if (clean.isNotEmpty()) {
                                                inlineReply = ""
                                                onReplyAndApprove(item, clean)
                                            } else {
                                                onGrantGo(item)
                                            }
                                        },
                                        modifier = Modifier
                                            .weight(1.45f)
                                            .height(32.dp),
                                        shape = RoundedCornerShape(8.dp),
                                        colors = ButtonDefaults.buttonColors(
                                            containerColor = Emerald500,
                                            contentColor = Color.White
                                        ),
                                        contentPadding = PaddingValues(horizontal = 8.dp, vertical = 2.dp)
                                    ) {
                                        Text(
                                            text = "✓ Absenden & Freigeben",
                                            fontSize = 11.sp,
                                            fontWeight = FontWeight.Bold,
                                            color = Color.White,
                                            maxLines = 1,
                                            overflow = TextOverflow.Ellipsis
                                        )
                                    }
                                }
                            }
                        }
                    }
                }

                // Gruppe 2: Aktiv in Arbeit
                if (inProgressItems.isNotEmpty()) {
                    Text(
                        text = "⚡ Antigravity arbeitet aktiv (${inProgressItems.size})",
                        fontSize = 11.5.sp,
                        fontWeight = FontWeight.Bold,
                        color = AppTheme.colors.textPrimary
                    )
                    inProgressItems.forEach { item ->
                        Card(
                            modifier = Modifier.fillMaxWidth(),
                            colors = CardDefaults.cardColors(containerColor = AppTheme.colors.glassSurface),
                            border = BorderStroke(1.dp, AppTheme.colors.cardBorder),
                            shape = RoundedCornerShape(10.dp)
                        ) {
                            Column(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .padding(9.dp),
                                verticalArrangement = Arrangement.spacedBy(4.dp)
                            ) {
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    val channelBadgeLabel = AgDirectInboxManager.resolveChannelEnum(item.channel).chipLabel
                                    Box(
                                        modifier = Modifier
                                            .clip(RoundedCornerShape(6.dp))
                                            .background(AppTheme.colors.cardBackground)
                                            .border(1.dp, Gold.copy(alpha = 0.35f), RoundedCornerShape(6.dp))
                                            .padding(horizontal = 6.dp, vertical = 2.dp)
                                    ) {
                                        Text(
                                            text = channelBadgeLabel,
                                            fontSize = 10.sp,
                                            fontWeight = FontWeight.SemiBold,
                                            color = AppTheme.colors.textPrimary
                                        )
                                    }
                                    Row(
                                        verticalAlignment = Alignment.CenterVertically,
                                        horizontalArrangement = Arrangement.spacedBy(5.dp),
                                        modifier = Modifier
                                            .clip(RoundedCornerShape(50))
                                            .background(Gold.copy(alpha = 0.14f))
                                            .border(1.dp, Gold.copy(alpha = 0.50f), RoundedCornerShape(50))
                                            .padding(horizontal = 7.dp, vertical = 2.dp)
                                    ) {
                                        Text(
                                            text = item.status.badgeLabel,
                                            fontSize = 10.sp,
                                            fontWeight = FontWeight.Bold,
                                            color = Gold
                                        )
                                        if (item.status == AgDirectStatus.DELIVERED) {
                                            CircularProgressIndicator(
                                                color = Gold,
                                                strokeWidth = 1.6.dp,
                                                modifier = Modifier.size(10.dp)
                                            )
                                        }
                                    }
                                }
                                Text(
                                    text = item.title,
                                    fontSize = 12.5.sp,
                                    fontWeight = FontWeight.SemiBold,
                                    color = AppTheme.colors.textPrimary,
                                    maxLines = 2,
                                    overflow = TextOverflow.Ellipsis
                                )
                            }
                        }
                    }
                }

                // Gruppe 3: Kürzlich erledigt
                if (completedItems.isNotEmpty()) {
                    Text(
                        text = "Kürzlich erledigt (${completedItems.size})",
                        fontSize = 11.5.sp,
                        fontWeight = FontWeight.Bold,
                        color = Emerald500
                    )
                    completedItems.take(5).forEach { item ->
                        Card(
                            modifier = Modifier.fillMaxWidth(),
                            colors = CardDefaults.cardColors(containerColor = AppTheme.colors.glassSurface),
                            border = BorderStroke(1.dp, Emerald500.copy(alpha = 0.30f)),
                            shape = RoundedCornerShape(10.dp)
                        ) {
                            Column(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .padding(9.dp),
                                verticalArrangement = Arrangement.spacedBy(4.dp)
                            ) {
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    val channelBadgeLabel = AgDirectInboxManager.resolveChannelEnum(item.channel).chipLabel
                                    Box(
                                        modifier = Modifier
                                            .clip(RoundedCornerShape(6.dp))
                                            .background(AppTheme.colors.cardBackground)
                                            .border(1.dp, Emerald500.copy(alpha = 0.35f), RoundedCornerShape(6.dp))
                                            .padding(horizontal = 6.dp, vertical = 2.dp)
                                    ) {
                                        Text(
                                            text = channelBadgeLabel,
                                            fontSize = 10.sp,
                                            fontWeight = FontWeight.SemiBold,
                                            color = AppTheme.colors.textPrimary
                                        )
                                    }
                                    Row(
                                        verticalAlignment = Alignment.CenterVertically,
                                        horizontalArrangement = Arrangement.spacedBy(4.dp),
                                        modifier = Modifier
                                            .clip(RoundedCornerShape(50))
                                            .background(Emerald500.copy(alpha = 0.14f))
                                            .border(1.dp, Emerald500.copy(alpha = 0.50f), RoundedCornerShape(50))
                                            .padding(horizontal = 7.dp, vertical = 2.dp)
                                    ) {
                                        Text(
                                            text = item.status.badgeLabel,
                                            fontSize = 10.sp,
                                            fontWeight = FontWeight.Bold,
                                            color = Emerald500
                                        )
                                        Icon(
                                            imageVector = Icons.Rounded.CheckCircle,
                                            contentDescription = "Fertig",
                                            tint = Emerald500,
                                            modifier = Modifier.size(12.dp)
                                        )
                                    }
                                }
                                Text(
                                    text = item.title,
                                    fontSize = 12.5.sp,
                                    fontWeight = FontWeight.SemiBold,
                                    color = AppTheme.colors.textPrimary,
                                    maxLines = 2,
                                    overflow = TextOverflow.Ellipsis
                                )
                                if (!item.agResponseSummary.isNullOrBlank()) {
                                    Box(
                                        modifier = Modifier
                                            .fillMaxWidth()
                                            .clip(RoundedCornerShape(6.dp))
                                            .background(Gold.copy(alpha = 0.08f))
                                            .border(1.dp, Gold.copy(alpha = 0.35f), RoundedCornerShape(6.dp))
                                            .padding(6.dp)
                                    ) {
                                        Text(
                                            text = item.agResponseSummary,
                                            fontSize = 11.5.sp,
                                            color = AppTheme.colors.textPrimary,
                                            lineHeight = 15.sp
                                        )
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

@Composable
fun ChatMessageRow(
    message: ChatMessage,
    activeAgentName: String,
    activeAgentColor: Color,
    canReact: Boolean = false,
    reaction: MessageReactionState? = null,
    quickChips: List<BehaviorQuickChipDto> = AiRepository.DEFAULT_BEHAVIOR_QUICK_CHIPS,
    onFeedback: (polarity: String, reasonCode: String?) -> Unit = { _, _ -> }
) {
    val isUser = message.role == "user"
    val bubbleColor = if (isUser) activeAgentColor else AppTheme.colors.cardBackground
    val textColor = if (isUser) SpaceBlack else AppTheme.colors.textPrimary
    val alignment = if (isUser) Alignment.End else Alignment.Start
    var showReasonChips by remember(message.memoryId, message.content) { mutableStateOf(false) }

    Column(
        modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = 4.dp),
        horizontalAlignment = alignment
    ) {
        Box(
            modifier = Modifier
                .clip(
                    RoundedCornerShape(
                        topStart = 16.dp,
                        topEnd = 16.dp,
                        bottomStart = if (isUser) 16.dp else 4.dp,
                        bottomEnd = if (isUser) 4.dp else 16.dp
                    )
                )
                .background(bubbleColor)
                .border(
                    width = if (isUser) 0.dp else 1.dp,
                    color = if (isUser) Color.Transparent else AppTheme.colors.cardBorder,
                    shape = RoundedCornerShape(
                        topStart = 16.dp,
                        topEnd = 16.dp,
                        bottomStart = if (isUser) 16.dp else 4.dp,
                        bottomEnd = if (isUser) 4.dp else 16.dp
                    )
                )
                .padding(horizontal = 16.dp, vertical = 10.dp)
        ) {
            Text(
                text = message.content,
                color = textColor,
                fontSize = 15.sp,
                lineHeight = 20.sp
            )
        }

        Row(
            modifier = Modifier.padding(top = 2.dp, start = 4.dp, end = 4.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            Text(
                text = if (isUser) "Du" else activeAgentName,
                fontSize = 10.sp,
                color = AppTheme.colors.textMuted
            )

            if (canReact) {
                BehaviorThumbActions(
                    reaction = reaction,
                    onPraise = {
                        showReasonChips = false
                        onFeedback("praise", null)
                    },
                    onCriticismToggle = {
                        val opening = !showReasonChips
                        showReasonChips = opening
                        if (opening && reaction?.polarity != "criticism" && reaction?.polarity != "correction") {
                            onFeedback("criticism", null)
                        }
                    }
                )
                val selectedChipLabel = reaction?.reasonCode?.let { code ->
                    quickChips.firstOrNull { it.code == code }?.let { it.short_label ?: it.label }
                }
                if (!selectedChipLabel.isNullOrBlank() && !showReasonChips) {
                    Text(
                        text = selectedChipLabel,
                        fontSize = 10.sp,
                        fontWeight = FontWeight.Medium,
                        color = Gold
                    )
                }
            }
        }

        if (canReact) {
            BehaviorQuickReasonChipsRow(
                visible = showReasonChips,
                chips = quickChips,
                selectedReasonCode = reaction?.reasonCode,
                onSelectChip = { chip ->
                    onFeedback(chip.polarity ?: "criticism", chip.code)
                    showReasonChips = false
                }
            )
        }
    }
}

fun getSelectedFileFromUri(context: Context, uri: Uri): SelectedFile? {
    val contentResolver = context.contentResolver
    val rawMimeType = contentResolver.getType(uri) ?: "application/octet-stream"
    var name = "attachment"
    var size = 0L

    contentResolver.query(uri, null, null, null, null)?.use { cursor ->
        if (cursor.moveToFirst()) {
            val nameIndex = cursor.getColumnIndex(OpenableColumns.DISPLAY_NAME)
            if (nameIndex != -1) {
                name = cursor.getString(nameIndex)
            }
            val sizeIndex = cursor.getColumnIndex(OpenableColumns.SIZE)
            if (sizeIndex != -1) {
                size = cursor.getLong(sizeIndex)
            }
        }
    }

    val isImage = rawMimeType.startsWith("image/") ||
        name.endsWith(".jpg", ignoreCase = true) ||
        name.endsWith(".jpeg", ignoreCase = true) ||
        name.endsWith(".png", ignoreCase = true) ||
        name.endsWith(".webp", ignoreCase = true)

    if (isImage) {
        val prepared = try {
            runBlocking(Dispatchers.IO) {
                AgDirectInboxManager.prepareAttachmentFromUri(context, uri)
            }
        } catch (_: Throwable) {
            null
        }

        if (prepared != null) {
            return SelectedFile(
                uriString = uri.toString(),
                name = prepared.fileName.takeIf { it.isNotBlank() && it != "screenshot.jpg" } ?: name,
                mimeType = prepared.mimeType,
                sizeBytes = prepared.base64Jpeg.length.toLong(),
                base64Content = prepared.base64Jpeg,
                textContent = null
            )
        }
    }

    return try {
        val inputStream: InputStream? = contentResolver.openInputStream(uri)
        if (inputStream != null) {
            val bytes = inputStream.readBytes()
            inputStream.close()
            
            val base64Content = if (rawMimeType.startsWith("image/")) {
                Base64.encodeToString(bytes, Base64.NO_WRAP)
            } else {
                null
            }

            val textContent = if (!rawMimeType.startsWith("image/") && (rawMimeType.startsWith("text/") || name.endsWith(".txt") || name.endsWith(".json") || name.endsWith(".csv") || name.endsWith(".xml") || name.endsWith(".md") || name.endsWith(".log") || name.endsWith(".js") || name.endsWith(".ts") || name.endsWith(".html") || name.endsWith(".css") || name.endsWith(".yaml") || name.endsWith(".yml"))) {
                String(bytes, Charsets.UTF_8)
            } else {
                null
            }

            SelectedFile(
                uriString = uri.toString(),
                name = name,
                mimeType = rawMimeType,
                sizeBytes = size.takeIf { it > 0 } ?: bytes.size.toLong(),
                base64Content = base64Content,
                textContent = textContent
            )
        } else {
            null
        }
    } catch (e: Exception) {
        e.printStackTrace()
        null
    }
}
